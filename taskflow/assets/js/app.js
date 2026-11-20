/**
 * TaskFlow — Client-side Interactivity
 * Minimalist, dependency-free JavaScript
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. User Profile Dropdown Toggle
    const userDropdowns = document.querySelectorAll('.user-dropdown');
    userDropdowns.forEach(function (dropdown) {
        const btn = dropdown.querySelector('.user-dropdown-btn');
        if (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                dropdown.classList.toggle('open');
            });
        }
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', function (e) {
        userDropdowns.forEach(function (dropdown) {
            if (!dropdown.contains(e.target)) {
                dropdown.classList.remove('open');
            }
        });
    });

    // Close on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            userDropdowns.forEach(function (dropdown) {
                dropdown.classList.remove('open');
            });
        }
    });

    // 2. Confirm dialogs on destructive actions
    const confirmButtons = document.querySelectorAll('[data-confirm]');
    confirmButtons.forEach(function (button) {
        button.addEventListener('click', function (e) {
            const message = button.getAttribute('data-confirm') || 'Are you sure you want to proceed?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    // 3. Auto-dismiss alerts after 5 seconds if not hovered
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-4px)';
            setTimeout(function () {
                if (alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            }, 500);
        }, 5000);
    });

    // 4. Status select auto-submit handlers
    const autoSubmitSelects = document.querySelectorAll('select[data-auto-submit]');
    autoSubmitSelects.forEach(function (select) {
        select.addEventListener('change', function () {
            if (select.form) {
                select.form.submit();
            }
        });
    });
});
