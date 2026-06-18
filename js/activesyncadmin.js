/**
 * Provides the javascript for administering ActiveSync partner devices.
 *
 * @copyright  2014-2015 Horde LLC
 * @license    LGPL-2 (http://www.horde.org/licenses/lgpl)
 */

var HordeActiveSyncAdmin = {

    // Set in admin/activesync.php: devices

    clickHandler: function(e)
    {
        var id = e.target.id;

        if (!id) {
            var closest = e.target.closest('[id]');
            id = closest ? closest.id : null;
        }

        var form = document.getElementById('activesyncadmin');

        switch (id) {
        case 'reset':
            document.getElementById('actionID').value = 'reset';
            form.submit();
            e.preventDefault();
            break;

        case 'search':
            document.getElementById('actionID').value = 'search';
            form.submit();
            e.preventDefault();
            break;

        default:
            if (id) {
                var prefixes = [
                    { prefix: 'wipe_', len: 5, action: 'wipe' },
                    { prefix: 'awipe_', len: 6, action: 'accountwipe' },
                    { prefix: 'cancel_', len: 7, action: 'cancelwipe' },
                    { prefix: 'remove_', len: 7, action: 'delete' },
                    { prefix: 'block_', len: 6, action: 'block' },
                    { prefix: 'unblock_', len: 8, action: 'unblock' }
                ];

                for (var i = 0; i < prefixes.length; i++) {
                    if (id.startsWith(prefixes[i].prefix)) {
                        var device = this.devices[id.substr(prefixes[i].len)];
                        document.getElementById('deviceID').value = device.id;
                        document.getElementById('actionID').value = prefixes[i].action;
                        if (prefixes[i].action === 'delete') {
                            document.getElementById('uid').value = device.user;
                        }
                        form.submit();
                        e.preventDefault();
                        break;
                    }
                }
            }
            break;
        }
    },

    onDomLoad: function()
    {
        document.getElementById('activesyncadmin').addEventListener('click', this.clickHandler.bind(this));
    }
};

document.addEventListener('DOMContentLoaded', HordeActiveSyncAdmin.onDomLoad.bind(HordeActiveSyncAdmin));
