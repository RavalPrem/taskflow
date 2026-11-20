<?php
require_once __DIR__ . '/../config.php';

/**
 * Enforces role-based access control on protected pages.
 * If user's current session role is not in the allowed list,
 * redirects to dashboard with an error flash message.
 *
 * @param array $allowedRoles List of permissible roles (e.g. ['admin'], ['admin', 'project_manager'])
 */
function require_role(array $allowedRoles): void {
    $current_role = $_SESSION['role'] ?? null;
    
    if (!$current_role || !in_array($current_role, $allowedRoles, true)) {
        $_SESSION['flash_error'] = "You don't have permission to view that page.";
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }
}
