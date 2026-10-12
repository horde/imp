/**
 * Managing message flags.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2014-2015 Horde LLC
 * @license    GPL-2 (http://www.horde.org/licenses/gpl)
 */

var ImpFlagPrefs = {

    // Variables set by other code: confirm_delete, new_prompt

    addFlag: function()
    {
        var category = window.prompt(this.new_prompt, '');
        if (category) {
            this._sendData('add', category);
        }
    },

    _sendData: function(a, d)
    {
        document.getElementById('flag_action').value = a;
        document.getElementById('flag_data').value = d;
        document.getElementById('prefs').submit();
    },

    changeHandler: function(e, elt)
    {
        if (elt.id.startsWith('bg_')) {
            elt.style.background = elt.value;
        }
    },

    clickHandler: function(e)
    {
        var cnames, elt2,
            elt = e.detail.element();

        if (elt.id == 'new_button') {
            this.addFlag();
        } else {
            cnames = elt.className.split(/\s+/);

            if (cnames.indexOf('flagcolorpicker') !== -1) {
                elt2 = elt.previousElementSibling;
                new ColorPicker({
                    color: elt2.value,
                    draggable: true,
                    offsetParent: elt,
                    resizable: true,
                    update: [
                        [ elt2, 'value' ],
                        [ elt2, 'background' ]
                    ]
                });
                e.detail.stop();
            } else if (cnames.indexOf('flagdelete') !== -1) {
                if (window.confirm(this.confirm_delete)) {
                    this._sendData('delete', elt.previousElementSibling.id);
                }
                e.detail.stop();
            }
        }
    },

    resetHandler: function()
    {
        document.getElementById('prefs').querySelectorAll('input[type="text"]').forEach(function(i) {
            if (i.id.startsWith('color_')) {
                i.style.backgroundColor = i.value;
            }
        });
    },

    onDomLoad: function()
    {
        HordeCore.initHandler('click');
        document.getElementById('prefs').addEventListener('reset', function() {
            setTimeout(this.resetHandler.bind(this), 100);
        }.bind(this));
    }

};

document.addEventListener('DOMContentLoaded', ImpFlagPrefs.onDomLoad.bind(ImpFlagPrefs));
document.addEventListener('HordeCore:click', ImpFlagPrefs.clickHandler.bind(ImpFlagPrefs));
document.addEventListener('change', function(e) {
    if (e.target.matches('INPUT')) {
        ImpFlagPrefs.changeHandler(e, e.target);
    }
});
