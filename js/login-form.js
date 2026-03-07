/**
 * Login Form Enhancements
 *
 * Handles URL capture from query params/hash and focuses first input field.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * @category Horde
 * @package  Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

(function() {
    'use strict';

    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLoginForm);
    } else {
        initLoginForm();
    }

    function initLoginForm() {
        // Capture redirect URL from hash or query params if not already set
        const urlInput = document.querySelector('input[name="url"]');
        const anchorInput = document.querySelector('input[name="anchor_string"]');

        if (urlInput && !urlInput.value) {
            const params = new URLSearchParams(window.location.search);
            const returnUrl = params.get('url');
            if (returnUrl) {
                urlInput.value = returnUrl;
            }
        }

        if (anchorInput && !anchorInput.value && window.location.hash) {
            anchorInput.value = window.location.hash.substring(1);
        }

        // Focus first input field
        const firstInput = document.querySelector('input[type="text"], input[type="password"]');
        if (firstInput) {
            firstInput.focus();
        }
    }
})();
