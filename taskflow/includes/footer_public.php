<?php
require_once __DIR__ . '/../config.php';
?>
    </main>

    <!-- Full Multi-Column Marketing Footer (Public Landing Page) -->
    <footer class="public-footer">
        <div class="container">
            <div class="footer-grid">
                <!-- Brand Column -->
                <div class="footer-col">
                    <div class="flex items-center gap-sm mb-md">
                        <span class="brand-icon">TF</span>
                        <span class="brand-text">TaskFlow</span>
                    </div>
                    <p class="body-sm" style="max-width: 320px; margin-bottom: 20px;">
                        Enterprise-grade project and task management for high-velocity software engineering teams. Focused precision, role-driven security, and zero bloat.
                    </p>
                    <div class="flex items-center gap-xs">
                        <span class="pill pill-success" style="font-size: 11px;">
                            <span style="width: 6px; height: 6px; background: #15803D; border-radius: 50%;"></span>
                            Operational
                        </span>
                        <span class="body-sm" style="margin-left: 8px;">v1.0.0 Stable</span>
                    </div>
                </div>

                <!-- Product Column -->
                <div class="footer-col">
                    <h4>Product</h4>
                    <ul class="footer-links">
                        <li><a href="<?= BASE_URL ?>/#features">Core Features</a></li>
                        <li><a href="<?= BASE_URL ?>/#roles">Role Architecture</a></li>
                        <li><a href="<?= BASE_URL ?>/#how-it-works">Kanban Workflow</a></li>
                        <li><a href="<?= BASE_URL ?>/auth/login.php">Live Workspace</a></li>
                        <li><a href="<?= BASE_URL ?>/auth/register.php">Create Account</a></li>
                    </ul>
                </div>

                <!-- Roles & Security Column -->
                <div class="footer-col">
                    <h4>Security & Roles</h4>
                    <ul class="footer-links">
                        <li><a href="<?= BASE_URL ?>/auth/login.php">Administrator Access</a></li>
                        <li><a href="<?= BASE_URL ?>/auth/login.php">Project Manager Suite</a></li>
                        <li><a href="<?= BASE_URL ?>/auth/login.php">Team Member Portal</a></li>
                        <li><a href="<?= BASE_URL ?>/#features">Audit & Activity Log</a></li>
                        <li><a href="<?= BASE_URL ?>/#features">Data Encryption</a></li>
                    </ul>
                </div>

                <!-- Deployment & Resources Column -->
                <div class="footer-col">
                    <h4>Deployment</h4>
                    <ul class="footer-links">
                        <li><a href="<?= BASE_URL ?>/database/seed.php">Database Seeder</a></li>
                        <li><a href="#">XAMPP Architecture</a></li>
                        <li><a href="#">MySQLi Schema</a></li>
                        <li><a href="#">System Diagnostics</a></li>
                        <li><a href="#">Apache Configuration</a></li>
                    </ul>
                </div>
            </div>

            <!-- Bottom Legal & Copyright Bar -->
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> TaskFlow Systems Inc. All rights reserved. Procedural PHP & MySQLi.</p>
                <div class="flex items-center gap-md">
                    <a href="#" style="color: var(--text-secondary); text-decoration: none;">Privacy Policy</a>
                    <a href="#" style="color: var(--text-secondary); text-decoration: none;">Terms of Service</a>
                    <a href="#" style="color: var(--text-secondary); text-decoration: none;">Security Center</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- App Scripts -->
    <script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
