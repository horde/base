/**
 * Javascript based Twitter client for Horde.
 *
 * @author     Michael J. Rubinsky <mrubinsk@horde.org>
 * @copyright  2014-2015 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl LGPL-2
 */

var Horde_Twitter = function(opts) {
   this.inReplyTo = '';
   this.oldestId = null;
   this.newestId = null;
   this.oldestMention = null;
   this.newestMention = null;
   this.instanceid = null;
   this.activeTab = 'stream';
   this.overlay = null;

   this.opts = Object.assign({
       refreshrate: 300
   }, opts);

   var input = document.getElementById(this.opts.input);
   input.addEventListener('focus', function() { this.clearInput(); }.bind(this));
   input.addEventListener('blur', function() {
       if (!input.value.length) {
           input.value = this.opts.strings.defaultText;
       }
   }.bind(this));

   input.addEventListener('keyup', function() {
       document.getElementById(this.opts.counter).textContent = 140 - input.value.length;
   }.bind(this));

   document.getElementById(this.opts.getmore).addEventListener('click', function(e) {
       this.getOlderEntries();
       e.preventDefault();
   }.bind(this));

   this.instanceid = opts.instanceid;

   document.getElementById(this.instanceid + '_updatebutton').addEventListener('click', function(e) {
       this.updateStatus(document.getElementById(this.instanceid + '_newStatus').value);
       e.preventDefault();
   }.bind(this));

   document.getElementById(this.instanceid + '_showcontenttab').addEventListener('click', function(e) {
       this.showStream();
       e.preventDefault();
   }.bind(this));

   document.getElementById(this.instanceid + '_showmentiontab').addEventListener('click', function(e) {
       this.showMentions();
       e.preventDefault();
   }.bind(this));

   this.overlay = document.createElement('div');
   this.overlay.className = 'hordeSmOverlay';
   this.overlay.innerHTML = '&nbsp;';
   this.overlay.hidden = true;
   var preview = document.getElementById(this.instanceid + '_preview');
   preview.parentNode.insertBefore(this.overlay, preview);
   preview.addEventListener('click', this.hidePreview.bind(this));
   /* Get the first page */
   this.getNewEntries();
};

Horde_Twitter.prototype = {

   /**
    * Post a new tweet.
    */
   updateStatus: function(statusText) {
        var spinner = document.getElementById(this.opts.spinner);
        spinner.hidden = !spinner.hidden;
        var params = {
            actionID: 'updateStatus',
            statusText: statusText,
            inReplyTo: this.inReplyTo
        };

        HordeCore.doAction('updateStatus',
            params,
            { callback: this.updateCallback.bind(this) }
        );
    },

    /**
     * Retweet the specifed tweet id.
     */
    retweet: function(id) {
        var spinner = document.getElementById(this.opts.spinner);
        spinner.hidden = !spinner.hidden;
        var params = {
            actionID: 'retweet',
            tweetId: id
        };
        HordeCore.doAction('retweet',
            params,
            { callback: this.updateCallback.bind(this) }
        );
    },

    /**
     * Favorite a tweet
     */
    favorite: function(id)
    {
        var spinner = document.getElementById(this.opts.spinner);
        spinner.hidden = !spinner.hidden;

        HordeCore.doAction('favorite',
            { tweetId: id },
            { callback: this.favoriteCallback.bind(this) }
        );
    },

    unfavorite: function(id)
    {
        var spinner = document.getElementById(this.opts.spinner);
        spinner.hidden = !spinner.hidden;

        HordeCore.doAction('unfavorite',
            { tweetId: id },
            { callback: this.unfavoriteCallback.bind(this) }
        );
    },

    favoriteCallback: function(r)
    {
        var spinner = document.getElementById(this.opts.spinner);
        spinner.hidden = !spinner.hidden;
        var el = document.getElementById('favorite' + this.instanceid + r.id_str);
        el.textContent = this.opts.strings.unfavorite;
        el.removeAttribute('onclick');
        el.addEventListener('click', function(e) { this.unfavorite(r.id_str); e.preventDefault(); }.bind(this));
    },

    unfavoriteCallback: function(r)
    {
        var spinner = document.getElementById(this.opts.spinner);
        spinner.hidden = !spinner.hidden;
        var el = document.getElementById('favorite' + this.instanceid + r.id_str);
        el.textContent = this.opts.strings.favorite;
        el.removeAttribute('onclick');
        el.addEventListener('click', function(e) { this.favorite(r.id_str); e.preventDefault(); }.bind(this));
    },

    /**
     * Update the timeline stream.
     */
    getOlderEntries: function() {
        var callback, params = {
            actionID: 'getPage',
            i: this.instanceid
        };

        switch (this.activeTab) {
        case 'stream':
            if (this.oldestId) {
                params.max_id = this.oldestId;
            }
            callback = this._getOlderEntriesCallback.bind(this);
            break;
        case 'mentions':
            if (this.oldestMention) {
                params.max_id = this.oldestMention;
            }
            callback = this._getOlderMentionsCallback.bind(this);
            params.mentions = 1;
            break;
        }
        HordeCore.doAction('twitterUpdate',
            params,
            { callback: callback }
        );
    },

    /**
     * Get newer entries, or the first page of entries if this is the first
     * request.
     */
    getNewEntries: function(type) {
        var callback, params = {
            actionID: 'getPage',
            i: this.instanceid
        };
        if (type == 'mentions') {
          if (this.newestMention) {
              params.since_id = this.newestMention;
          } else {
              params.page = 1;
          }
          params.mentions = 1;
          callback = this._getNewMentionsCallback.bind(this);
        } else {
          if (this.newestId) {
              params.since_id = this.newestId;
          } else {
              params.page = 1;
          }
          callback = this._getNewEntriesCallback.bind(this);
        }
        HordeCore.doAction('twitterUpdate',
            params,
            { callback: callback }
        );
    },

    showPreview: function(url)
    {
        var preview = document.getElementById(this.instanceid + '_preview');
        preview.hidden = true;
        preview.innerHTML = '';
        var img = document.createElement('img');
        img.src = url;
        preview.appendChild(img);
        this.overlay.hidden = false;
        preview.hidden = false;

        return false;
    },

    hidePreview: function(e) {
      document.getElementById(this.instanceid + '_preview').hidden = true;
      this.overlay.hidden = true;
    },

    /**
     * Callback for updateStream request for older stream entries.
     */
    _getOlderEntriesCallback: function(response) {
        var h, content = response.c;
        if (response.o) {
            this.oldestId = response.o;
            var el = document.getElementById(this.opts.content);
            h = el.scrollHeight;
            el.insertAdjacentHTML('beforeend', content);
            el.scrollTop = h;
        }
    },

    /**
     * Callback for updateStream request for older mentions.
     */
    _getOlderMentionsCallback: function(response) {
        var h, content = response.c;
        if (response.o) {
            this.oldestMention = response.o;
            var el = document.getElementById(this.opts.mentions);
            h = el.scrollHeight;
            el.insertAdjacentHTML('beforeend', content);
            el.scrollTop = h;
        }
    },

    /**
     * Callback for retrieving new entries.
     */
    _getNewEntriesCallback: function(response) {
        var h, content = response.c;

        if (response.n != this.newestId) {
            var el = document.getElementById(this.opts.content);
            h = el.scrollHeight;
            el.insertAdjacentHTML('afterbegin', content);
            if (this.activeTab != 'stream') {
                document.getElementById(this.opts.contenttab).classList.add('hordeSmNew');
            } else {
                if (this.newestId) {
                    el.scrollTop = h;
                } else {
                    el.scrollTop = 0;
                }
            }

            this.newestId = response.n;

            if (!this.oldestId) {
                this.oldestId = response.o;
            }
        }
        setTimeout(function() { this.getNewEntries(); }.bind(this), this.opts.refreshrate * 1000);
    },

    /**
     * Callback for retrieving new mentions.
     */
    _getNewMentionsCallback: function(response) {
        var h, content = response.c;

        if (response.n != this.newestMention) {
            var el = document.getElementById(this.opts.mentions);
            h = el.scrollHeight;
            el.insertAdjacentHTML('afterbegin', content);
            if (this.activeTab != 'mentions') {
                document.getElementById(this.opts.mentiontab).classList.add('hordeSmNew');
            } else {
                if (this.newestMention) {
                    el.scrollTop = h;
                } else {
                    el.scrollTop = 0;
                }
            }

            this.newestMention = response.n;

            if (!this.oldestMention) {
                this.oldestMention = response.o;
            }
        }
        setTimeout(function() { this.getNewEntries('mentions'); }.bind(this), this.opts.refreshrate * 1000);
    },

    /**
     * Build the reply structure
     */
    buildReply: function(id, userid, usertext) {
        this.inReplyTo = id;
        var input = document.getElementById(this.opts.input);
        input.focus();
        input.value = '@' + userid + ' ';
        document.getElementById(this.opts.inreplyto).textContent = this.opts.strings.inreplyto + usertext;
    },

    /**
     * Callback for after a new tweet is posted.
     */
    updateCallback: function(response) {
       var contentEl = document.getElementById(this.opts.content);
       contentEl.insertAdjacentHTML('afterbegin', response);
       document.getElementById(this.opts.input).value = this.opts.strings.defaultText;
       var spinner = document.getElementById(this.opts.spinner);
       spinner.hidden = !spinner.hidden;
       this.inReplyTo = '';
       document.getElementById(this.opts.inreplyto).textContent = '';
    },

    showMentions: function()
    {
        if (this.activeTab != 'mentions') {
            document.getElementById(this.opts.mentiontab).classList.remove('hordeSmNew');
            this.toggleTabs();
            document.getElementById(this.opts.content).hidden = true;
            if (!this.oldestMention) {
                this.getNewEntries('mentions');
            }
            document.getElementById(this.opts.mentions).hidden = false;
            this.activeTab = 'mentions';
        }
    },

    showStream: function()
    {
        if (this.activeTab != 'stream') {
            document.getElementById(this.opts.contenttab).classList.remove('hordeSmNew');
            this.toggleTabs();
            document.getElementById(this.opts.mentions).hidden = true;
            document.getElementById(this.opts.content).hidden = false;
            this.activeTab = 'stream';
        }
    },

    toggleTabs: function()
    {
        document.getElementById(this.opts.contenttab).classList.toggle('horde-active'); // eslint-disable-line horde/no-prototype-methods -- native DOMTokenList.toggle
        document.getElementById(this.opts.mentiontab).classList.toggle('horde-active'); // eslint-disable-line horde/no-prototype-methods -- native DOMTokenList.toggle
    },

    /**
     * Clear the input field.
     */
    clearInput: function() {
        document.getElementById(this.opts.input).value = '';
    }
};
