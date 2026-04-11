/**
 * Provides the javascript for managing categories.
 *
 * @copyright  2014-2015 Horde LLC
 * @license    LGPL-2 (http://www.horde.org/licenses/lgpl)
 */

var HordeCategoryPrefs = {

    // Variables defaulting to null: category_text

    removeCategory: function(e)
    {
        var p = document.getElementById('prefs');
        p.cAction.value = 'remove';
        p.category.value = e.target.closest('[category]').getAttribute('category');
        p.submit();
    },

    addCategory: function()
    {
        var category = window.prompt(this.category_text, ''), p;
        if (category && category !== '') {
            p = document.getElementById('prefs');
            p.cAction.value = 'add';
            p.category.value = category;
            p.submit();
        }
    },

    resetBackgrounds: function()
    {
        Array.from(document.getElementById('prefs').querySelectorAll('input[type="text"]')).forEach(function(i) {
            if (i.id.startsWith('color_')) {
                i.style.backgroundColor = i.value;
            }
        });
    },

    colorPicker: function(e)
    {
        var elt = e.target,
            input = elt.parentElement.previousElementSibling;
        while (input && input.tagName !== 'INPUT') {
            input = input.previousElementSibling;
        }

        new ColorPicker({
            color: input.value,
            offsetParent: elt,
            update: [ [ input, 'value' ], [ input, 'background' ] ]
        });

        e.preventDefault();
    },

    onDomLoad: function()
    {
        document.getElementById('prefs').addEventListener('reset', function() {
            setTimeout(HordeCategoryPrefs.resetBackgrounds, 0);
        });
        document.getElementById('add_category').addEventListener('click', this.addCategory.bind(this));

        document.querySelectorAll('.categoryColorPicker').forEach(function(el) {
            el.addEventListener('click', this.colorPicker.bind(this));
        }, this);
        document.querySelectorAll('.categoryDelete').forEach(function(el) {
            el.addEventListener('click', this.removeCategory.bind(this));
        }, this);
    }

};

document.addEventListener('DOMContentLoaded', HordeCategoryPrefs.onDomLoad.bind(HordeCategoryPrefs));
