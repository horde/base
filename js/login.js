/**
 * Provides the javascript for the login.php script.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2006-2015 Horde LLC
 * @license    LGPL-2 (http://www.horde.org/licenses/lgpl)
 */

var HordeLogin = {

    // Variables set by outside code: user_error, pass_error

    submit: function()
    {
        var user = document.getElementById('horde_user'),
            pass = document.getElementById('horde_pass');

        if (user && !user.value) {
            alert(HordeLogin.user_error);
            user.focus();
        } else if (pass && !pass.value) {
            alert(HordeLogin.pass_error);
            pass.focus();
        } else {
            document.getElementById('login-button').disabled = true;
            document.getElementById('login_post').value = 1;
            document.getElementById('horde_login').submit();
        }
    },

    selectLang: function()
    {
        var user = document.getElementById('horde_user'),
            pass = document.getElementById('horde_pass');
        if ((!user || !user.value) &&
            (!pass || !pass.value)) {
            var params = new URLSearchParams({ new_lang: document.getElementById('new_lang').value });
            self.location = 'login.php?' + params.toString();
        }
    },

    loginButton: function(e)
    {
        if (e.button === 2) {
            return;
        }

        if (!e.target.disabled) {
            this.submit();
        }
        e.preventDefault();
    },

    keypressPassword: function(e)
    {
        var kc = e.keyCode || e.charCode;

        if (((kc >= 65 & kc <= 90) && !e.shiftKey) ||
            ((kc >= 97 & kc <= 122) && e.shiftKey)) {
            document.getElementById('horde-login-pass-capslock').hidden = false;
        } else {
            document.getElementById('horde-login-pass-capslock').hidden = true;
        }
    },

    /* Removes any leading hash that might be on a location string. */
    _removeHash: function(h)
    {
        return (typeof h === 'string' && h.startsWith("#")) ? h.substring(1) : h;
    },

    onDomLoad: function()
    {
        var s = document.getElementById('horde_select_view'),
            user = document.getElementById('horde_user'),
            pass = document.getElementById('horde_pass');

        // Need to capture hash information if it exists in URL
        if (location.hash) {
            document.getElementById('anchor_string').value = this._removeHash(location.hash);
        }

        if (user && !user.value) {
            user.focus();
        } else if (pass && !pass.value) {
            pass.focus();
        } else {
            document.getElementById('login-button').focus();
        }

        /* Programatically activate views that require javascript. */
        if (s) {
            var nojs = s.querySelector('option[value="mobile_nojs"]');
            if (nojs) {
                nojs.remove();
            }
            if (this.pre_sel) {
                var opt = s.querySelector('option[value="' + this.pre_sel + '"]');
                if (opt) {
                    s.selectedIndex = opt.index;
                }
            }
            document.getElementById('horde_select_view_div').hidden = false;
        }
    }

};

document.addEventListener('DOMContentLoaded', HordeLogin.onDomLoad.bind(HordeLogin));
document.addEventListener('change', function(e) {
    if (e.target.id === 'new_lang') {
        HordeLogin.selectLang();
    }
});
document.addEventListener('click', function(e) {
    if (e.target.id === 'login-button' || e.target.closest('#login-button')) {
        HordeLogin.loginButton(e);
    }
});
document.addEventListener('keypress', function(e) {
    if (e.target.id === 'horde_pass') {
        HordeLogin.keypressPassword(e);
    }
});
