/**
 * Provides the javascript for managing syncml sessions.
 *
 * @copyright  2014-2015 Horde LLC
 * @license    LGPL-2 (http://www.horde.org/licenses/lgpl)
 */

var HordeSyncMLPrefs = {

    removeAnchor: function(device, db) {
        document.getElementById('removedevice').value = device;
        document.getElementById('removedb').value = db;
        document.forms.prefs.actionID = 'update_special';
        document.forms.prefs.submit();
    }

};
