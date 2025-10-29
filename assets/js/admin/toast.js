/**
 * Toast Notifications System
 * Simple, modern toast notifications for admin actions
 */

(function() {
    'use strict';

    const FPExpToast = {
        container: null,

        init() {
            // Create container if not exists
            if (!this.container) {
                this.container = document.createElement('div');
                this.container.className = 'fp-exp-toast-container';
                document.body.appendChild(this.container);
            }
        },

        /**
         * Show a toast notification
         * @param {string} message - Toast message
         * @param {string} type - Type: success, error, warning, info
         * @param {number} duration - Duration in ms (0 = persistent)
         */
        show(message, type = 'success', duration = 4000) {
            this.init();

            const toast = document.createElement('div');
            toast.className = `fp-exp-toast fp-exp-toast--${type}`;
            
            const icon = this.getIcon(type);
            toast.innerHTML = `
                <span class="fp-exp-toast__icon dashicons dashicons-${icon}"></span>
                <span class="fp-exp-toast__message">${this.escapeHtml(message)}</span>
                <button class="fp-exp-toast__close" aria-label="Close">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            `;

            this.container.appendChild(toast);

            // Close button
            toast.querySelector('.fp-exp-toast__close').addEventListener('click', () => {
                this.hide(toast);
            });

            // Auto-hide after duration
            if (duration > 0) {
                setTimeout(() => {
                    this.hide(toast);
                }, duration);
            }

            // Trigger animation
            setTimeout(() => {
                toast.classList.add('fp-exp-toast--show');
            }, 10);
        },

        hide(toast) {
            toast.classList.remove('fp-exp-toast--show');
            setTimeout(() => {
                toast.remove();
            }, 300);
        },

        getIcon(type) {
            const icons = {
                success: 'yes-alt',
                error: 'dismiss',
                warning: 'warning',
                info: 'info'
            };
            return icons[type] || 'info';
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        // Convenience methods
        success(message, duration) {
            this.show(message, 'success', duration);
        },

        error(message, duration) {
            this.show(message, 'error', duration);
        },

        warning(message, duration) {
            this.show(message, 'warning', duration);
        },

        info(message, duration) {
            this.show(message, 'info', duration);
        }
    };

    // Expose globally
    window.fpExpToast = FPExpToast;

    // Auto-init on load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => FPExpToast.init());
    } else {
        FPExpToast.init();
    }

    // Hook into WordPress notices to show as toasts
    document.addEventListener('DOMContentLoaded', function() {
        // Listen for WordPress admin notices
        const notices = document.querySelectorAll('.notice.is-dismissible');
        notices.forEach(notice => {
            const message = notice.textContent.trim();
            let type = 'info';
            
            if (notice.classList.contains('notice-success')) {
                type = 'success';
            } else if (notice.classList.contains('notice-error')) {
                type = 'error';
            } else if (notice.classList.contains('notice-warning')) {
                type = 'warning';
            }

            // Show as toast and hide original
            if (message && notice.closest('.fp-exp-admin')) {
                setTimeout(() => {
                    FPExpToast.show(message, type);
                    notice.style.display = 'none';
                }, 500);
            }
        });
    });

})();

