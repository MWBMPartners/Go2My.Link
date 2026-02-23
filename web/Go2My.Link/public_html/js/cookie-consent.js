/**
 * ============================================================================
 * 🍪 Go2My.Link — Cookie Consent JavaScript
 * ============================================================================
 *
 * Handles cookie banner interactions and sends consent preferences to the
 * server via AJAX (POST /api/consent/).
 *
 * @package    Go2My.Link
 * @subpackage JavaScript
 * @version    0.7.0
 * @since      Phase 6
 * ============================================================================
 */

(function () {
    'use strict';

    var banner = document.getElementById('g2ml-cookie-banner');
    if (!banner) return;

    var csrfInput     = document.getElementById('g2ml-consent-csrf');
    var csrfToken     = csrfInput ? csrfInput.value : '';
    var acceptAllBtn  = document.getElementById('g2ml-cookie-accept-all');
    var rejectBtn     = document.getElementById('g2ml-cookie-reject');
    var savePrefsBtn  = document.getElementById('g2ml-cookie-save-preferences');

    /**
     * Send consent choices to the server.
     *
     * @param {Object} choices — { analytics: bool, functional: bool, marketing: bool }
     */
    function submitConsent(choices) {
        var data = {
            csrf_token: csrfToken,
            essential: true,
            analytics: !!choices.analytics,
            functional: !!choices.functional,
            marketing: !!choices.marketing
        };

        // Try AJAX first, fall back to form POST
        if (typeof fetch !== 'undefined') {
            fetch('/api/consent/', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            })
            .then(function (response) { return response.json(); })
            .then(function (result) {
                if (result.success) {
                    hideBanner();
                }
            })
            .catch(function () {
                // Fallback: submit via form POST
                submitViaForm(data);
            });
        } else {
            submitViaForm(data);
        }
    }

    /**
     * Fallback: submit consent via a hidden form POST.
     */
    function submitViaForm(data) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '/api/consent/';
        form.style.display = 'none';

        for (var key in data) {
            if (data.hasOwnProperty(key)) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = data[key] ? '1' : '0';
                form.appendChild(input);
            }
        }

        document.body.appendChild(form);
        form.submit();
    }

    /**
     * Hide the cookie banner with a fade transition.
     */
    function hideBanner() {
        banner.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        banner.style.opacity = '0';
        banner.style.transform = 'translateY(100%)';
        setTimeout(function () {
            banner.style.display = 'none';
            // Return focus to main content after dismissal (WCAG 2.4.3)
            var mainContent = document.getElementById('main-content');
            if (mainContent) { mainContent.focus(); }
        }, 300);
    }

    // ========================================================================
    // Event Listeners
    // ========================================================================

    // Accept All
    if (acceptAllBtn) {
        acceptAllBtn.addEventListener('click', function () {
            submitConsent({ analytics: true, functional: true, marketing: true });
        });
    }

    // Reject Non-Essential
    if (rejectBtn) {
        rejectBtn.addEventListener('click', function () {
            submitConsent({ analytics: false, functional: false, marketing: false });
        });
    }

    // Save Custom Preferences (from modal)
    if (savePrefsBtn) {
        savePrefsBtn.addEventListener('click', function () {
            var analytics  = document.getElementById('g2ml-consent-analytics');
            var functional = document.getElementById('g2ml-consent-functional');
            var marketing  = document.getElementById('g2ml-consent-marketing');

            submitConsent({
                analytics:  analytics  ? analytics.checked  : false,
                functional: functional ? functional.checked : false,
                marketing:  marketing  ? marketing.checked  : false
            });

            // Close the modal
            var modalEl = document.getElementById('g2ml-cookie-modal');
            if (modalEl && typeof bootstrap !== 'undefined') {
                var modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
            }
        });
    }
})();
