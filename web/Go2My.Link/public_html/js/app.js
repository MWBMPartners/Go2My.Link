/**
 * ============================================================================
 * GoToMyLink — Component A Custom JavaScript
 * ============================================================================
 *
 * Custom JS for go2my.link (Main Website).
 * jQuery 3.7 and Bootstrap 5.3 are loaded via CDN with local fallback.
 *
 * @version 0.3.0
 * @since   Phase 2
 * ============================================================================
 */

'use strict';

// Initialise when DOM is ready
document.addEventListener('DOMContentLoaded', function() {

    // ========================================================================
    // Accessibility: Announce page title changes to screen readers
    // ========================================================================
    var statusRegion = document.getElementById('global-status');
    if (statusRegion) {
        statusRegion.textContent = document.title + ' loaded';
    }

});
