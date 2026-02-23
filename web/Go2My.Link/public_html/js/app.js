/**
 * ============================================================================
 * GoToMyLink — Component A Custom JavaScript
 * ============================================================================
 *
 * Custom JS for go2my.link (Main Website).
 * jQuery 3.7 and Bootstrap 5.3 are loaded via CDN with local fallback.
 *
 * Features:
 *   - Page load accessibility announcements
 *   - AJAX URL shortening form handler
 *   - Copy-to-clipboard with fallback
 *   - CAPTCHA callbacks (Turnstile / reCAPTCHA)
 *
 * @version 0.4.0
 * @since   Phase 2 (URL form added Phase 3)
 * ============================================================================
 */

'use strict';

// ============================================================================
// CAPTCHA Callbacks (Global — must be available before widget loads)
// ============================================================================
// These are referenced by the Turnstile/reCAPTCHA widget's data-callback and
// data-expired-callback attributes in home.php.
//
// Reference: https://developers.cloudflare.com/turnstile/get-started/client-side-rendering/
// Reference: https://developers.google.com/recaptcha/docs/display#render_param
// ============================================================================

window.onCaptchaSuccess = function(token) {
    var submitBtn = document.getElementById('shorten-submit');
    if (submitBtn) {
        submitBtn.disabled = false;
    }
};

window.onCaptchaExpired = function() {
    var submitBtn = document.getElementById('shorten-submit');
    if (submitBtn) {
        submitBtn.disabled = true;
    }
};

// ============================================================================
// DOM Ready
// ============================================================================

document.addEventListener('DOMContentLoaded', function() {

    // ========================================================================
    // Accessibility: Announce page title changes to screen readers
    // ========================================================================
    var statusRegion = document.getElementById('global-status');
    if (statusRegion) {
        statusRegion.textContent = document.title + ' loaded';
    }

    // ========================================================================
    // URL Shortening Form — AJAX Handler
    // ========================================================================
    // Intercepts form submission and posts to /api/create/ via XMLHttpRequest.
    // Falls back to standard form POST if JavaScript is disabled (the form's
    // action and method attributes handle this natively).
    //
    // Reference: https://developer.mozilla.org/en-US/docs/Web/API/XMLHttpRequest
    // ========================================================================

    var form       = document.getElementById('shorten-form');
    var submitBtn  = document.getElementById('shorten-submit');
    var resultDiv  = document.getElementById('shorten-result');
    var errorDiv   = document.getElementById('shorten-error');
    var resultLink = document.getElementById('result-url');
    var copyBtn    = document.getElementById('copy-url-btn');
    var copyText   = document.getElementById('copy-btn-text');

    if (form) {
        var captchaType = form.getAttribute('data-captcha-type') || 'none';

        // Disable submit button if CAPTCHA is required (re-enabled by callback)
        if (captchaType !== 'none' && submitBtn) {
            submitBtn.disabled = true;
        }

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            // Disable submit button and show loading state
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML =
                    '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> '
                    + 'Shortening...';
            }

            // Hide any previous results/errors
            if (resultDiv) { resultDiv.classList.add('d-none'); }
            if (errorDiv)  { errorDiv.classList.add('d-none'); }

            // Build FormData from the form (includes CSRF token + CAPTCHA response)
            var formData = new FormData(form);

            // Send AJAX POST
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '/api/create/', true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            xhr.onload = function() {
                var response;

                try {
                    response = JSON.parse(xhr.responseText);
                } catch (parseError) {
                    showError('An unexpected error occurred. Please try again.');
                    resetSubmitButton();
                    return;
                }

                if (xhr.status === 201 && response.success) {
                    showResult(response.shortURL);
                } else {
                    showError(response.error || 'Failed to create short URL.');
                }

                resetSubmitButton();
                resetCaptcha();
            };

            xhr.onerror = function() {
                showError('Network error. Please check your connection and try again.');
                resetSubmitButton();
                resetCaptcha();
            };

            xhr.timeout = 15000; // 15 second timeout
            xhr.ontimeout = function() {
                showError('Request timed out. Please try again.');
                resetSubmitButton();
                resetCaptcha();
            };

            xhr.send(formData);
        });
    }

    // ========================================================================
    // Copy to Clipboard
    // ========================================================================
    // Uses the Clipboard API with a textarea fallback for older browsers.
    //
    // Reference: https://developer.mozilla.org/en-US/docs/Web/API/Clipboard/writeText
    // ========================================================================

    if (copyBtn) {
        copyBtn.addEventListener('click', function() {
            var url = resultLink ? resultLink.textContent : '';

            if (!url) { return; }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function() {
                    showCopySuccess();
                }).catch(function() {
                    copyFallback(url);
                });
            } else {
                copyFallback(url);
            }
        });
    }

    // ========================================================================
    // Helper Functions
    // ========================================================================

    /**
     * Display the shortened URL result.
     */
    function showResult(shortURL) {
        if (!resultDiv || !resultLink) { return; }

        var safeURL = escapeHTML(shortURL);
        resultLink.href        = safeURL;
        resultLink.textContent = shortURL;

        resultDiv.classList.remove('d-none');

        // Scroll result into view smoothly
        resultDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

        announceStatus('Short URL created: ' + shortURL);
    }

    /**
     * Display an error message.
     */
    function showError(message) {
        if (!errorDiv) { return; }

        errorDiv.innerHTML =
            '<div class="alert alert-danger alert-dismissible" role="alert">'
            + '<i class="fas fa-exclamation-circle" aria-hidden="true"></i> '
            + escapeHTML(message)
            + '<button type="button" class="btn-close" data-bs-dismiss="alert"'
            + ' aria-label="Close"></button>'
            + '</div>';

        errorDiv.classList.remove('d-none');
        announceStatus(message);
    }

    /**
     * Reset the submit button to its default state.
     */
    function resetSubmitButton() {
        if (!submitBtn) { return; }

        submitBtn.innerHTML =
            '<i class="fas fa-magic" aria-hidden="true"></i> Shorten URL';

        // Re-enable only if no CAPTCHA is required
        if (!form || form.getAttribute('data-captcha-type') === 'none') {
            submitBtn.disabled = false;
        }
    }

    /**
     * Reset the CAPTCHA widget after form submission.
     */
    function resetCaptcha() {
        if (!form) { return; }

        var captchaType = form.getAttribute('data-captcha-type') || 'none';

        if (captchaType === 'turnstile' && window.turnstile) {
            window.turnstile.reset();
        } else if (captchaType === 'recaptcha' && window.grecaptcha) {
            window.grecaptcha.reset();
        }
    }

    /**
     * Fallback copy method using a temporary textarea.
     */
    function copyFallback(text) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity  = '0';
        document.body.appendChild(textarea);
        textarea.select();

        try {
            document.execCommand('copy');
            showCopySuccess();
        } catch (err) {
            announceStatus('Failed to copy URL. Please select and copy manually.');
        }

        document.body.removeChild(textarea);
    }

    /**
     * Show copy success feedback on the button.
     */
    function showCopySuccess() {
        if (!copyBtn || !copyText) { return; }

        var originalText = copyText.textContent;
        copyText.textContent = 'Copied!';
        copyBtn.classList.remove('btn-outline-success');
        copyBtn.classList.add('btn-success');

        announceStatus('Short URL copied to clipboard.');

        setTimeout(function() {
            copyText.textContent = originalText;
            copyBtn.classList.remove('btn-success');
            copyBtn.classList.add('btn-outline-success');
        }, 2000);
    }

    /**
     * Announce a status message to screen readers via the global live region.
     */
    function announceStatus(message) {
        var region = document.getElementById('global-status');
        if (region) {
            region.textContent = message;
        }
    }

    /**
     * Escape HTML entities to prevent XSS in dynamic content.
     *
     * Reference: https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html
     */
    function escapeHTML(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

});
