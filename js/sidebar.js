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

    /* Safe read of the stored width (localStorage may throw in private mode). */
    readStoredWidth: function()
    {
        try {
            var n = parseInt(localStorage.getItem('horde_sidebar_width'), 10);
            return Number.isFinite(n) ? n : null;
        } catch (e) {
            return null;
        }
    },

    /* Safe write of the width to localStorage. */
    storeWidth: function(w)
    {
        try {
            localStorage.setItem('horde_sidebar_width', w);
        } catch (e) {}
    },

    /* Clamp a width between the min/max bounds exposed as CSS custom properties. */
    clampWidth: function(w, cs)
    {
        var min = parseInt(cs.getPropertyValue('--horde-sidebar-min-width'), 10) || 250;
        var max = parseInt(cs.getPropertyValue('--horde-sidebar-max-width'), 10) || 500;
        return Math.min(Math.max(w, min), max);
    },

    onDomLoad: function()
    {
        this.refreshEvents();

        var self = this;
        var root = document.documentElement;
        var cs = getComputedStyle(root);

        var s = $('horde-sidebar');
        if (s) {
            var stored = this.readStoredWidth();
            var w = (stored !== null)
                ? stored
                : (s.offsetWidth || parseInt(s.style.width, 10) || 250);
            if (!Number.isFinite(w)) {
                w = 250;
            }
            w = this.clampWidth(w, cs);
            root.style.setProperty('--horde-sidebar-width', w + 'px');
        }

        /* Drag-resize global de la sidebar */
        var handle = document.getElementById('horde-slideleftcursor');
        if (!handle) return;

        /* ARIA semantics: the handle acts as a resizable separator. */
        var min = parseInt(cs.getPropertyValue('--horde-sidebar-min-width'), 10) || 250;
        var max = parseInt(cs.getPropertyValue('--horde-sidebar-max-width'), 10) || 500;
        handle.setAttribute('role', 'separator');
        handle.setAttribute('aria-orientation', 'vertical');
        handle.setAttribute('aria-label', 'Resize sidebar');
        handle.setAttribute('aria-valuemin', min);
        handle.setAttribute('aria-valuemax', max);
        if (!handle.hasAttribute('tabindex')) {
            handle.setAttribute('tabindex', '0');
        }

        var applyWidth = function(w) {
            w = self.clampWidth(w, cs);
            root.style.setProperty('--horde-sidebar-width', w + 'px');
            handle.setAttribute('aria-valuenow', w);
            return w;
        };

        /* Mouse drag resize.
         * Kept as a plain mousedown (not Pointer Events) so it coexists with
         * application-level handlers bound to the same splitbar — e.g. IMP's
         * Scriptaculous Drag on #horde-slideleft, which relies on the native
         * mouse event sequence. */
        handle.addEventListener('mousedown', function(e) {
            e.preventDefault();
            var startX = e.clientX;
            var startW = parseInt(cs.getPropertyValue('--horde-sidebar-width'), 10) || 250;

            var prevCursor = document.body.style.cursor;
            var prevSelect = document.body.style.userSelect;
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';

            function onMove(ev) {
                applyWidth(startW + (ev.clientX - startX));
            }
            function onUp() {
                document.body.style.cursor = prevCursor;
                document.body.style.userSelect = prevSelect;
                self.storeWidth(parseInt(cs.getPropertyValue('--horde-sidebar-width'), 10));
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
            }
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        });

        /* Touch drag resize (separate path so the mouse handling above — and
         * any app handler relying on mouse events — stays untouched). */
        handle.addEventListener('touchstart', function(e) {
            if (!e.touches || e.touches.length !== 1) {
                return;
            }
            var startX = e.touches[0].clientX;
            var startW = parseInt(cs.getPropertyValue('--horde-sidebar-width'), 10) || 250;

            function onMove(ev) {
                if (!ev.touches || ev.touches.length !== 1) {
                    return;
                }
                /* Prevent scrolling only while actively resizing. */
                ev.preventDefault();
                applyWidth(startW + (ev.touches[0].clientX - startX));
            }
            function onEnd() {
                self.storeWidth(parseInt(cs.getPropertyValue('--horde-sidebar-width'), 10));
                document.removeEventListener('touchmove', onMove);
                document.removeEventListener('touchend', onEnd);
                document.removeEventListener('touchcancel', onEnd);
            }
            document.addEventListener('touchmove', onMove, { passive: false });
            document.addEventListener('touchend', onEnd);
            document.addEventListener('touchcancel', onEnd);
        }, { passive: true });

        /* Keyboard operability: left/right arrows adjust the width. */
        handle.addEventListener('keydown', function(e) {
            var step = e.shiftKey ? 32 : 8;
            var cur = parseInt(cs.getPropertyValue('--horde-sidebar-width'), 10) || 250;
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                self.storeWidth(applyWidth(cur - step));
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                self.storeWidth(applyWidth(cur + step));
            }
        });
    }
};

document.observe('dom:loaded', HordeSidebar.onDomLoad.bind(HordeSidebar));
document.observe('click', HordeSidebar.clickHandler.bindAsEventListener(HordeSidebar));
