/**
 * Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
 * All rights reserved.
 *
 * This source code is proprietary and confidential.
 * Unauthorised copying, modification, or distribution is strictly prohibited.
 */

/**
 * ============================================================================
 * 🎨 Go2My.Link — Theme Controller (Dark/Light Mode)
 * ============================================================================
 *
 * Manages dark/light mode switching using Bootstrap 5.3 colour modes.
 * Three states: auto (follow system), light (forced), dark (forced).
 *
 * Persistence:
 *   - localStorage key 'g2ml-theme' (for instant client-side reads)
 *   - Cookie 'g2ml_theme' (for server-side FOUC prevention in header.php)
 *
 * Cycle order: auto → light → dark → auto
 *
 * Dependencies: Font Awesome 6 (for toggle button icons)
 *
 * @package    Go2My.Link
 * @subpackage JavaScript
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.4.0
 * @since      Phase 3
 *
 * 📖 References:
 *     - Bootstrap Color Modes: https://getbootstrap.com/docs/5.3/customize/color-modes/
 *     - prefers-color-scheme:  https://developer.mozilla.org/en-US/docs/Web/CSS/@media/prefers-color-scheme
 *     - localStorage:          https://developer.mozilla.org/en-US/docs/Web/API/Window/localStorage
 * ============================================================================
 */

'use strict';

var G2ML_Theme = (function() {

    // ========================================================================
    // Configuration
    // ========================================================================
    var STORAGE_KEY  = 'g2ml-theme';
    var COOKIE_NAME  = 'g2ml_theme';
    var COOKIE_DAYS  = 365;
    var TOGGLE_ID    = 'g2ml-theme-toggle';
    var ICON_ID      = 'g2ml-theme-icon';
    var LABEL_ID     = 'g2ml-theme-label';
    var LIVE_REGION  = 'global-status';

    // Icon classes for each state
    // 📖 Reference: https://fontawesome.com/icons
    var ICONS = {
        auto:  'fas fa-circle-half-stroke',
        light: 'fas fa-sun',
        dark:  'fas fa-moon'
    };

    // Display labels for each state (used for ARIA and screen reader)
    var LABELS = {
        auto:  'Auto (system)',
        light: 'Light',
        dark:  'Dark'
    };

    // Cycle order
    var CYCLE = ['auto', 'light', 'dark'];

    // ========================================================================
    // Core Functions
    // ========================================================================

    /**
     * Get the stored theme preference.
     *
     * @return {string} 'auto', 'light', or 'dark'
     */
    function getStoredTheme()
    {
        try
        {
            return localStorage.getItem(STORAGE_KEY) || 'auto';
        }
        catch (e)
        {
            // localStorage may be blocked (private browsing, etc.)
            return 'auto';
        }
    }

    /**
     * Get the system's preferred colour scheme.
     *
     * @return {string} 'dark' or 'light'
     */
    function getSystemTheme()
    {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)
        {
            return 'dark';
        }

        return 'light';
    }

    /**
     * Resolve a preference into an actual theme to apply.
     *
     * @param  {string} preference  'auto', 'light', or 'dark'
     * @return {string}             'light' or 'dark'
     */
    function resolveTheme(preference)
    {
        if (preference === 'auto')
        {
            return getSystemTheme();
        }

        return preference;
    }

    /**
     * Apply a resolved theme to the document.
     *
     * @param {string} resolvedTheme  'light' or 'dark'
     */
    function applyTheme(resolvedTheme)
    {
        document.documentElement.setAttribute('data-bs-theme', resolvedTheme);
    }

    /**
     * Store the theme preference in localStorage and cookie.
     *
     * @param {string} preference  'auto', 'light', or 'dark'
     */
    function setStoredTheme(preference)
    {
        // localStorage
        try
        {
            localStorage.setItem(STORAGE_KEY, preference);
        }
        catch (e)
        {
            // Silently fail if localStorage unavailable
        }

        // Cookie (for server-side reading in header.php)
        // 📖 Reference: https://developer.mozilla.org/en-US/docs/Web/API/Document/cookie
        var expires = new Date(Date.now() + COOKIE_DAYS * 86400000).toUTCString();

        document.cookie = COOKIE_NAME + '=' + encodeURIComponent(preference)
            + ';expires=' + expires
            + ';path=/;SameSite=Lax;Secure';
    }

    /**
     * Update the toggle button icon and ARIA label.
     *
     * @param {string} preference  'auto', 'light', or 'dark'
     */
    function updateToggleButton(preference)
    {
        var icon  = document.getElementById(ICON_ID);
        var label = document.getElementById(LABEL_ID);
        var btn   = document.getElementById(TOGGLE_ID);

        if (icon)
        {
            icon.className = ICONS[preference] || ICONS.auto;
        }

        if (label)
        {
            label.textContent = LABELS[preference] || LABELS.auto;
        }

        if (btn)
        {
            // ARIA label announces what will happen on next click
            var nextIndex = (CYCLE.indexOf(preference) + 1) % CYCLE.length;
            var nextLabel = LABELS[CYCLE[nextIndex]];

            btn.setAttribute('aria-label', 'Theme: ' + (LABELS[preference] || 'Auto') + '. Click for ' + nextLabel);
        }
    }

    /**
     * Announce theme change to screen readers via ARIA live region.
     *
     * @param {string} resolvedTheme  'light' or 'dark'
     */
    function announceTheme(resolvedTheme)
    {
        var liveRegion = document.getElementById(LIVE_REGION);

        if (liveRegion)
        {
            liveRegion.textContent = 'Theme changed to ' + resolvedTheme + ' mode';

            // Clear after a delay so the same message can be announced again
            setTimeout(function() {
                liveRegion.textContent = '';
            }, 3000);
        }
    }

    /**
     * Cycle to the next theme in the sequence: auto → light → dark → auto.
     */
    function cycleTheme()
    {
        var current   = getStoredTheme();
        var nextIndex = (CYCLE.indexOf(current) + 1) % CYCLE.length;
        var next      = CYCLE[nextIndex];

        setStoredTheme(next);

        var resolved = resolveTheme(next);

        applyTheme(resolved);
        updateToggleButton(next);
        announceTheme(resolved);
    }

    // ========================================================================
    // Initialisation
    // ========================================================================

    /**
     * Initialise the theme system.
     * Called on DOMContentLoaded.
     */
    function init()
    {
        // 1. Apply the stored preference
        var preference = getStoredTheme();
        var resolved   = resolveTheme(preference);

        applyTheme(resolved);
        updateToggleButton(preference);

        // 2. Bind the toggle button click
        var toggleBtn = document.getElementById(TOGGLE_ID);

        if (toggleBtn)
        {
            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                cycleTheme();
            });
        }

        // 3. Listen for system preference changes
        //    If the user is in 'auto' mode, automatically switch when system changes.
        // 📖 Reference: https://developer.mozilla.org/en-US/docs/Web/API/MediaQueryList/change_event
        if (window.matchMedia)
        {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function() {
                if (getStoredTheme() === 'auto')
                {
                    var newResolved = getSystemTheme();

                    applyTheme(newResolved);
                    announceTheme(newResolved);
                }
            });
        }
    }

    // ========================================================================
    // Auto-initialise on DOMContentLoaded
    // ========================================================================
    if (document.readyState === 'loading')
    {
        document.addEventListener('DOMContentLoaded', init);
    }
    else
    {
        // DOM already loaded (e.g., script loaded with defer after DOM ready)
        init();
    }

    // ========================================================================
    // Public API
    // ========================================================================
    return {
        init:           init,
        getStoredTheme: getStoredTheme,
        getSystemTheme: getSystemTheme,
        resolveTheme:   resolveTheme,
        applyTheme:     applyTheme,
        cycleTheme:     cycleTheme
    };

})();
