/**
 * Horde sidebar javascript.
 *
 * @copyright  2014-2015 Horde LLC
 * @license    LGPL-2 (http://www.horde.org/licenses/lgpl)
 */

var HordeSidebar = {

    // Vars set in Horde_Sidebar
    //   opts, text

    refreshEvents: function()
    {
        $('horde-sidebar').select('div.horde-resources div').each(function(s) {
            s.observe('mouseover', s.addClassName.curry('horde-resource-over'));
            s.observe('mouseout', s.removeClassName.curry('horde-resource-over'));
        });
    },

    clickHandler: function(e)
    {
        if (e.isRightClick() || typeof e.element != 'function') {
            return;
        }

        var elt = e.element(),
            params = ';DOMAIN=' + this.opts.cookieDomain
                + ';PATH=' + this.opts.cookiePath + ';';

        while (Object.isElement(elt)) {
            switch (elt.className) {
            case 'horde-collapse':
                elt.up().next().blindUp({ duration: 0.5, queue: 'end' });
                elt.title = this.text.expand;
                elt.removeClassName('horde-collapse');
                elt.addClassName('horde-expand');
                document.cookie = 'horde_sidebar_c_' + elt.identify() + '=1' + params;
                return;

            case 'horde-expand':
                elt.up().next().blindDown({ duration: 0.5, queue: 'end' });
                elt.title = this.text.collapse;
                elt.removeClassName('horde-expand');
                elt.addClassName('horde-collapse');
                document.cookie = 'horde_sidebar_c_' + elt.identify() + '=0' + params;
                return;
            }

            elt = elt.up();
        }
        // Workaround Firebug bug.
        Prototype.emptyFunction();
    },

    onDomLoad: function()
    {
        this.refreshEvents();

        var s = $('horde-sidebar');
        if (s) {
            var saved = localStorage.getItem('horde_sidebar_width');
            var w = saved
                ? parseInt(saved)
                : (s.offsetWidth || parseInt(s.style.width) || 250);
            document.documentElement.style.setProperty('--horde-sidebar-width', w + 'px');
        }

        /* Drag-resize sidebar globale */
        var handle = document.getElementById('horde-slideleftcursor');
        if (!handle) return;

        handle.addEventListener('mousedown', function(e) {
            e.preventDefault();
            var root = document.documentElement;
            var cs = getComputedStyle(root);
            var startX = e.clientX;
            var startW = parseInt(cs.getPropertyValue('--horde-sidebar-width')) || 250;
            var getMin = function() { return parseInt(cs.getPropertyValue('--horde-sidebar-min-width')) || 250; };
            var getMax = function() { return parseInt(cs.getPropertyValue('--horde-sidebar-max-width')) || 500; };

            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';

            function onMove(e) {
                var newW = Math.min(Math.max(startW + (e.clientX - startX), getMin()), getMax());
                root.style.setProperty('--horde-sidebar-width', newW + 'px');
            }
            function onUp() {
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
                localStorage.setItem('horde_sidebar_width',
                    parseInt(getComputedStyle(root).getPropertyValue('--horde-sidebar-width')));
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
            }
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        });
    }
};

document.observe('dom:loaded', HordeSidebar.onDomLoad.bind(HordeSidebar));
document.observe('click', HordeSidebar.clickHandler.bindAsEventListener(HordeSidebar));
