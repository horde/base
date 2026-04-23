/**
 * Provides the javascript for managing ActiveSync partner devices.
 *
 * @copyright  2014-2015 Horde LLC
 * @license    LGPL-2 (http://www.horde.org/licenses/lgpl)
 */

var HordeActiveSyncPrefs = {

    // Set in lib/Prefs/Ui.php: devices

    clickHandler: function(e)
    {
        var id = e.target.id;

        if (!id) {
            var closest = e.target.closest('[id]');
            id = closest ? closest.id : null;
        }

        if (!id) {
            return;
        }

        var prefix, action;
        if (id.startsWith('wipe_')) {
            prefix = 5; action = 'wipeid';
        } else if (id.startsWith('cancel_')) {
            prefix = 7; action = 'cancelwipe';
        } else if (id.startsWith('remove_')) {
            prefix = 7; action = 'removedevice';
        } else {
            return;
        }

        document.getElementById(action).value = this.devices[id.substr(prefix)].id;
        document.getElementById('actionID').value = 'update_special';
        document.getElementById('prefs').submit();
        e.preventDefault();
    },

    onDomLoad: function()
    {
        document.getElementById('prefs').addEventListener('click', this.clickHandler.bind(this));
    }
};

document.addEventListener('DOMContentLoaded', HordeActiveSyncPrefs.onDomLoad.bind(HordeActiveSyncPrefs));
