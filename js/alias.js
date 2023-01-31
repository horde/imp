/**
 * Author of passphrase dialog
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2014-2015 Horde LLC
 * @license    GPL-2 (http://www.horde.org/licenses/gpl)
 */

/**
 * Reimplemented passphrase dialog for alias dialog
 * 
 * @author     Rafael te Boekhorst <boekhorst@b1-systems.de>
 * @copyright  2014-2015 Horde LLC
 * @license    GPL-2 (http://www.horde.org/licenses/gpl)
 *  
 */

var ImpAliasDialog = {
    display: function (data) {
        HordeDialog.display(Object.extend(data, {
            form_id: 'imp_alias',
            password: false
        }));
    },

    onClick: function (e) {
        switch (e.element().identify()) {
            case 'imp_alias':
                HordeCore.doAction(
                    'checkAlias',
                    e.findElement('FORM').serialize(true),
                    { callback: this.callback.bind(this) }
                );
                break;
        }
    },

    callback: function (r) {
        if (r) {
            $('imp_alias').fire('ImpAliasDialog:success');
            HordeDialog.close();
        }
    }

};

document.observe('HordeDialog:onClick', ImpAliasDialog.onClick.bindAsEventListener(ImpAliasDialog));
