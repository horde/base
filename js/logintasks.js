/**
 * Provides the javascript for the logintasks confirmation page.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2014-2015 Horde LLC
 * @license    LGPL-2 (http://www.horde.org/licenses/lgpl)
 */

document.addEventListener('DOMContentLoaded', function() {
    var skipBtn = document.getElementById('logintasks_skip');
    skipBtn.hidden = false;
    skipBtn.addEventListener('click', function() {
        var form = document.getElementById('logintasks_confirm');
        Array.from(form.querySelectorAll('input[type="checkbox"]')).forEach(function(cb) {
            cb.checked = false;
        });
        form.submit();
    });
});
