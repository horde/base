/**
 * TextareaResize: a library that automatically resizes a text area based on
 * its contents.
 *
 * Usage:
 * ------
 * cs = new TextareaResize(id[, options]);
 *
 *   id = (string|Element) DOM ID/Element object of textarea.
 *   options = (object) Additional options:
 *      'max_rows' - (Number) The maximum number of rows to display.
 *
 * Custom Events:
 * --------------
 * TextareaResize:resize
 *   Fired when the textarea is resized.
 *   params: NONE
 *
 * @author Michael Slusarz <slusarz@horde.org>
 * @copyright  2014-2015 Horde LLC
 * @license    LGPL-2 (http://www.horde.org/licenses/lgpl)
 */

var TextareaResize = function(id, opts) {
    opts = opts || {};

    this.elt = (typeof id === 'string') ? document.getElementById(id) : id;
    this.max_rows = opts.max_rows || 5;
    this.size = -1;

    this.elt.addEventListener('input', this.resize.bind(this));

    this.resize();
};

TextareaResize.prototype = {

    resize: function()
    {
        var old_rows, rows,
            size = this.elt.value.length;

        if (size == this.size) {
            return;
        }

        old_rows = rows = Number(this.elt.getAttribute('rows') || 1);

        if (size > this.size) {
            while (rows < this.max_rows) {
                if (this.elt.scrollHeight == this.elt.clientHeight) {
                    break;
                }
                this.elt.setAttribute('rows', ++rows);
            }
        } else if (rows > 1) {
            do {
                this.elt.setAttribute('rows', --rows);
                if (this.elt.scrollHeight != this.elt.clientHeight) {
                    this.elt.setAttribute('rows', ++rows);
                    break;
                }
            } while (rows > 1);
        }

        this.size = size;

        if (rows != old_rows) {
            this.elt.dispatchEvent(new CustomEvent('TextareaResize:resize', { bubbles: true }));
        }
    }

};
