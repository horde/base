/**
 * Provides the javascript for the admin user update page.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2014-2015 Horde LLC
 * @license    LGPL-2 (http://www.horde.org/licenses/lgpl)
 */

var HordeAdminUserUpdate = {

    // Set in admin/user.php: pass_error

    onSubmit: function(e)
    {
        var pass1 = document.getElementById('user_pass_1'),
            pass2 = document.getElementById('user_pass_2');
        if (pass1 && pass1.value !== pass2.value) {
            pass1.value = '';
            pass2.value = '';
            window.alert(this.pass_error);
            e.preventDefault();
        }
    }

};

var updateForm = document.getElementById('updateuser');
if (updateForm) {
    updateForm.addEventListener('submit', HordeAdminUserUpdate.onSubmit.bind(HordeAdminUserUpdate));
}
