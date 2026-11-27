/**
 * Javascript code for finding all tables with classname "striped" and
 * dynamically striping their row colors.
 *
 * @author     Chuck Hagenbuch <chuck@horde.org>
 * @author     Matt Warden <mwarden@gmail.com>
 * @copyright  2014-2015 Horde LLC
 * @license    LGPL-2 (http://www.horde.org/licenses/lgpl)
 */

window.Horde = window.Horde || {};

Horde.stripeAllElements = function()
{
    var self = this;
    document.querySelectorAll('.striped').forEach(function(elt) {
        self.stripeElement(elt);
    });
};

Horde.stripeElement = function(elt)
{
    // Accept either a DOM element or an element id (Prototype's $() did both).
    if (typeof elt === 'string') {
        elt = document.getElementById(elt);
    }
    if (!elt) {
        return;
    }

    var classes = [ 'rowEven', 'rowOdd' ],
        i = 0;

    var stripe = function(children) {
        Array.prototype.forEach.call(children, function(c) {
            if (c.nodeType !== 1) {
                return;
            }
            c.classList.remove(classes[i % 2]);
            c.classList.add(classes[++i % 2]);
        });
    };

    if (elt.tagName == 'TABLE') {
        // Tables can have more than one tbody element; get all child
        // tbody tags and iterate through them.
        Array.prototype.forEach.call(elt.children, function(child) {
            stripe(child.children);
        });
    } else {
        // Toggle the classname of any child node that is an element.
        stripe(elt.children);
    }
};

/* We have to wait for the full DOM to be loaded to ensure we don't
 * miss anything. */
document.addEventListener('DOMContentLoaded', function() {
    Horde.stripeAllElements();
});
