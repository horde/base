/**
 * Javascript for the Vatid block.
 *
 * @copyright  2014-2015 Horde LLC
 * @license    LGPL-2 (http://www.horde.org/licenses/lgpl)
 */

var HordeBlockVatid = {

    onSubmit: function(e)
    {
        var form = e.target;

        form.querySelector('img').hidden = false;

        fetch(form.action, {
            method: form.method || 'POST',
            headers: { 'Accept': 'application/json' },
            body: new FormData(form)
        }).then(function(r) {
            return r.json();
        }).then(function(json) {
            form.querySelector('div.vatidResults').innerHTML = json.response;
            form.querySelector('div.vatidResults').scrollIntoView();
            form.querySelector('img').hidden = true;
        }).catch(function() {
            form.querySelector('img').hidden = true;
        });

        e.preventDefault();
    }

};
