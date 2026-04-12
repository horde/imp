/**
 * Ckeditor normalization object.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2014-2015 Horde LLC
 * @license    GPL-2 (http://www.horde.org/licenses/gpl)
 */

var IMP_Editor = function(id, config) {
    this.config = Object.assign({}, config);
    this.id = id;

    this.start();

    this.editor.on('instanceReady', function(evt) {
        this.iready = true;
        document.dispatchEvent(new CustomEvent('IMP_Editor:ready', { detail: evt.editor }));
    }.bind(this));
    this.editor.on('dataReady', function(evt) {
        if (!this.dready) {
            document.dispatchEvent(new CustomEvent('IMP_Editor:dataReady', { detail: evt.editor }));
            this.dready = true;
        }
    }.bind(this));
    this.editor.on('instanceDestroyed', function(evt) {
        this.dready = this.iready = this.editor = false;
        document.dispatchEvent(new CustomEvent('IMP_Editor:destroy', { detail: evt.editor }));
    }.bind(this));
};

IMP_Editor.prototype = {

    start: function()
    {
        if (!this.editor) {
            if (typeof this.config.height === 'undefined') {
                var elt = document.getElementById(this.id);
                this.config.height = Math.max(elt.offsetHeight, 200) - 75;
            }
            this.editor = CKEDITOR.replace(this.id, this.config);
        }
    },

    destroy: function()
    {
        if (this.editor) {
            this.editor.destroy(true);
        }
    },

    busy: function()
    {
        return this.wait || !this.iready || !this.dready;
    },

    getData: function()
    {
        return this.editor.getData();
    },

    setData: function(data)
    {
        if (this.busy()) {
            setTimeout(this.setData.bind(this, data), 100);
        } else {
            this.wait = true;
            this.editor.setData(data, function() {
                this.wait = false;
            }.bind(this));
        }
    },

    resize: function(width, height)
    {
        if (this.busy()) {
            setTimeout(this.resize.bind(this, width, height), 100);
        } else {
            this.editor.resize(width, height);
        }
    },

    focus: function()
    {
        if (this.busy()) {
            setTimeout(this.focus.bind(this), 100);
        } else {
            this.editor.focus();
        }
    },

    updateElement: function()
    {
        if (this.busy()) {
            setTimeout(this.updateElement.bind(this), 100);
        } else {
            this.editor.updateElement();
        }
    }

};
