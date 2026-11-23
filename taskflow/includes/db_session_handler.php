<?php
/**
 * Database-backed PHP session handler.
 *
 * Why this file exists:
 * Wasmer (and most serverless/edge PHP runtimes) do not guarantee that the
 * same request handler/instance serves two consecutive requests from the
 * same visitor, and the local filesystem used by PHP's default "files"
 * session handler is not reliably shared or persisted between instances.
 * That means a session written during login can be invisible on the very
 * next request, so $_SESSION['user_id'] is empty again and every protected
 * page (auth_check.php) redirects straight back to the login screen - which
 * looks like "the page won't open".
 *
 * Storing sessions in MySQL instead of the filesystem fixes this, because
 * every instance talks to the same database.
 *
 * This file must be required BEFORE session_start() is called.
 * It expects $conn (a mysqli connection) to already exist - include it
 * after includes/db.php.
 *
 * IMPORTANT: since PHP 8.1, mysqli's default error mode throws a
 * mysqli_sql_exception on any failed query instead of returning false
 * (e.g. if the `sessions` table from database/schema.sql hasn't been
 * created yet on this database). Every method below is wrapped in
 * try/catch so a DB hiccup here degrades to "sessions don't persist"
 * instead of a fatal error that takes down every page on the site.
 */

class DbSessionHandler implements SessionHandlerInterface
{
    private mysqli $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    public function open($savePath, $sessionName): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read($id): string
    {
        try {
            $stmt = mysqli_prepare($this->conn, "SELECT data FROM sessions WHERE id = ? AND last_access > (NOW() - INTERVAL 1440 MINUTE) LIMIT 1");
            if (!$stmt) {
                return '';
            }
            mysqli_stmt_bind_param($stmt, "s", $id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);

            return $row ? (string)$row['data'] : '';
        } catch (\Throwable $e) {
            error_log('DbSessionHandler::read failed: ' . $e->getMessage());
            return '';
        }
    }

    public function write($id, $data): bool
    {
        try {
            $stmt = mysqli_prepare(
                $this->conn,
                "INSERT INTO sessions (id, data, last_access) VALUES (?, ?, NOW())
                 ON DUPLICATE KEY UPDATE data = VALUES(data), last_access = NOW()"
            );
            if (!$stmt) {
                return false;
            }
            mysqli_stmt_bind_param($stmt, "ss", $id, $data);
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            return $ok;
        } catch (\Throwable $e) {
            error_log('DbSessionHandler::write failed: ' . $e->getMessage());
            return false;
        }
    }

    public function destroy($id): bool
    {
        try {
            $stmt = mysqli_prepare($this->conn, "DELETE FROM sessions WHERE id = ?");
            if (!$stmt) {
                return false;
            }
            mysqli_stmt_bind_param($stmt, "s", $id);
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            return $ok;
        } catch (\Throwable $e) {
            error_log('DbSessionHandler::destroy failed: ' . $e->getMessage());
            return false;
        }
    }

    public function gc($max_lifetime): int|false
    {
        try {
            $stmt = mysqli_prepare($this->conn, "DELETE FROM sessions WHERE last_access < (NOW() - INTERVAL ? SECOND)");
            if (!$stmt) {
                return false;
            }
            mysqli_stmt_bind_param($stmt, "i", $max_lifetime);
            mysqli_stmt_execute($stmt);
            $affected = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);

            return $affected;
        } catch (\Throwable $e) {
            error_log('DbSessionHandler::gc failed: ' . $e->getMessage());
            return false;
        }
    }
}

// Register the handler and start the session using it.
// This registration itself is also wrapped: if anything here goes wrong,
// fall back to PHP's normal file-based sessions rather than crash the page.
try {
    if (session_status() === PHP_SESSION_NONE && isset($conn) && $conn instanceof mysqli) {
        $db_session_handler = new DbSessionHandler($conn);
        session_set_save_handler($db_session_handler, true);
    }
} catch (\Throwable $e) {
    error_log('DbSessionHandler setup failed, falling back to default sessions: ' . $e->getMessage());
}
