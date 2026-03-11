/**
 * Responsive Login Page - Vanilla JavaScript
 * Handles login form functionality without framework dependencies
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2). If you
 * did not receive this file, see https://www.horde.org/licenses/lgpl.
 */

document.addEventListener('DOMContentLoaded', function() {
    // Get translated strings from global scope (set by login.php)
    var strings = window.HordeLoginStrings || {
        username: "Please enter a username.",
        password: "Please enter a password.",
        capsLock: "Caps Lock is on"
    };

    // Show mode selector if it exists
    var modeDiv = document.getElementById('horde_select_view_div');
    var modeSelect = document.getElementById('horde_select_view');
    if (modeDiv && modeSelect) {
        // Remove mobile_nojs option (requires JavaScript)
        var noJsOption = modeSelect.querySelector('option[value="mobile_nojs"]');
        if (noJsOption) {
            noJsOption.remove();
        }

        // Set selected value from cookie or default
        var preSelected = window.HordeLoginPreSelected || "auto";
        if (preSelected) {
            var option = modeSelect.querySelector('option[value="' + preSelected + '"]');
            if (option) {
                modeSelect.selectedIndex = option.index;
            }
        }

        // Show the mode selector
        modeDiv.style.display = '';
    }

    // Capture hash for anchor navigation
    if (location.hash) {
        var anchorInput = document.getElementById('anchor_string');
        if (anchorInput) {
            anchorInput.value = location.hash.substring(1);
        }
    }

    // Focus appropriate field
    var userField = document.getElementById('horde_user');
    var passField = document.getElementById('horde_pass');
    var loginButton = document.getElementById('login-button');

    if (userField && !userField.value) {
        userField.focus();
    } else if (passField && !passField.value) {
        passField.focus();
    } else if (loginButton) {
        loginButton.focus();
    }

    // Handle form submission
    var form = document.getElementById('horde_login');
    if (form) {
        form.addEventListener('submit', function(e) {
            var loginPost = document.getElementById('login_post');
            if (loginPost) {
                loginPost.value = '1';
            }

            // Validate fields
            if (userField && !userField.value) {
                e.preventDefault();
                alert(strings.username);
                userField.focus();
                return false;
            }
            if (passField && !passField.value) {
                e.preventDefault();
                alert(strings.password);
                passField.focus();
                return false;
            }

            // Disable button to prevent double-submit
            if (loginButton) {
                loginButton.disabled = true;
            }
        });
    }

    // Handle language change
    var langSelect = document.getElementById('new_lang');
    if (langSelect) {
        langSelect.addEventListener('change', function() {
            // Only reload if user hasn't entered credentials yet
            if ((!userField || !userField.value) && (!passField || !passField.value)) {
                window.location = 'login.php?new_lang=' + encodeURIComponent(this.value);
            }
        });
    }

    // Caps lock detection for password field
    if (passField) {
        passField.addEventListener('keypress', function(e) {
            var capsWarning = document.getElementById('horde-login-pass-capslock');
            if (!capsWarning) {
                capsWarning = document.createElement('div');
                capsWarning.id = 'horde-login-pass-capslock';
                capsWarning.className = 'alert alert-warning';
                capsWarning.style.display = 'none';
                capsWarning.style.marginTop = '0.5rem';
                capsWarning.textContent = strings.capsLock;
                passField.parentNode.appendChild(capsWarning);
            }

            var charCode = e.keyCode || e.which;
            var shiftKey = e.shiftKey;

            // Check if caps lock is on
            if ((charCode >= 65 && charCode <= 90 && !shiftKey) ||
                (charCode >= 97 && charCode <= 122 && shiftKey)) {
                capsWarning.style.display = 'block';
            } else {
                capsWarning.style.display = 'none';
            }
        });
    }
});
