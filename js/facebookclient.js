/**
 * Facebook client javascript.
 *
 * @author     Michael J. Rubinsky <mrubinsk@horde.org>
 * @copyright  2014-2015 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl LGPL-2
 */

var Horde_Facebook = function(opts) {
    this.oldest = '';
    this.newest = '';
    this.opts = Object.assign({
        refreshrate: 300,
        count: 10,
        filter: 'nf'
    }, opts);

    this.getNewEntries();
    var self = this;
    document.getElementById(this.opts.getmore).addEventListener('click', function(e) { self.getOlderEntries(); e.preventDefault(); });
};

Horde_Facebook.prototype = {

    /**
     * Update FB status.
     */
    updateStatus: function()
    {
        var input = document.getElementById(this.opts.input);
        if (!input.value) {
            return;
        }
        var spinner = document.getElementById(this.opts.spinner);
        spinner.hidden = !spinner.hidden;
        var params = {
            statusText: input.value,
            instance: this.opts.instance
        };
        HordeCore.doAction('facebookUpdateStatus',
            params,
            { callback: this._updateStatusCallback.bind(this) }
        );
    },

    _updateStatusCallback: function(r)
    {
        document.getElementById(this.opts.input).value = '';
        var spinner = document.getElementById(this.opts.spinner);
        spinner.hidden = !spinner.hidden;
        var content = document.getElementById(this.opts.content);
        content.insertAdjacentHTML('afterbegin', r);
    },

    addLike: function(post_id)
    {
        var spinner = document.getElementById(this.opts.spinner);
        spinner.hidden = !spinner.hidden;
        var params = {
          post_id: post_id,
          instance: this.opts.instance
        };
        HordeCore.doAction('facebookAddLike',
            params,
            { callback: this._addLikeCallback.bind(this, post_id) }
        );
    },

    _addLikeCallback: function(post_id, r)
    {
        document.getElementById('fb' + post_id).innerHTML = r;
        var spinner = document.getElementById(this.opts.spinner);
        spinner.hidden = !spinner.hidden;
    },

    getOlderEntries: function() {
        var params = {
            'newest': this.oldest,
            'instance': this.opts.instance,
            'filter': this.opts.filter
        };
        HordeCore.doAction('facebookGetStream',
            params,
            { callback: this._getOlderEntriesCallback.bind(this) }
        );
    },

    _getOlderEntriesCallback: function(response)
    {
        var content = document.getElementById(this.opts.content),
            h = content.scrollHeight;
        this.oldest = response.o;
        content.insertAdjacentHTML('beforeend', response.c);
        content.scrollTop = h;
    },

    getNewEntries: function()
    {
        var params = {
            'oldest': this.oldest,
            'newest': this.newest,
            'instance': this.opts.instance,
            'filter': this.opts.filter
        };
        HordeCore.doAction('facebookGetStream',
            params,
            { callback: this._getNewEntriesCallback.bind(this) }
        );
    },

    _getNewEntriesCallback: function(response)
    {
        document.getElementById(this.opts.content).insertAdjacentHTML('afterbegin', response.c);

        this.newest = response.n;
        if (!this.oldest) {
            this.oldest = response.o;
        }
    }

};
