/**
 * Handling of the passphrase dialog.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2014-2015 Horde LLC
 * @license    GPL-2 (http://www.horde.org/licenses/gpl)
 */

var ImpPassphraseDialog = {

    display: function(data)
    {
        HordeDialog.display(Object.assign(data, {
            form_id: 'imp_passphrase',
            password: true
        }));
    },

    onClick: function(e)
    {
        switch (e.target.id || (e.target.closest('[id]') || {}).id) {
        case 'imp_passphrase':
            HordeCore.doAction(
                'checkPassphrase',
                Object.fromEntries(new FormData(e.target.closest('FORM'))),
                { callback: this.callback.bind(this) }
            );
            break;
        }
    },

    callback: function(r)
    {
        if (r) {
            document.getElementById('imp_passphrase').dispatchEvent(new CustomEvent('ImpPassphraseDialog:success', { bubbles: true }));
            HordeDialog.close();
        }
    }

};

document.addEventListener('HordeDialog:onClick', ImpPassphraseDialog.onClick.bind(ImpPassphraseDialog));
