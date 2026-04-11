/**
 * Javascript code for attaching an onkeydown listener to textarea and
 * text input elements to prevent loss of data when the user hits the
 * ESC key.
 *
 * @copyright  2014-2015 Horde LLC
 * @license    LGPL-2 (http://www.horde.org/licenses/lgpl)
 */

document.addEventListener('keydown', function(e) {
    var tag = e.target.tagName;

    if (e.key === 'Escape' &&
        (tag === 'TEXTAREA' || (tag === 'INPUT' && e.target.type === 'text'))) {
        e.preventDefault();
    }
});
