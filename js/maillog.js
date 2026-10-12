/**
 * Maillog display for dynamic view.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2015-2016 Horde LLC
 * @license    GPL-2 (http://www.horde.org/licenses/gpl)
 */

var ImpMaillog = {

    // Vars set by calling code:
    //   error_msg

    onDomLoad: function()
    {
        var base;

        if (base = ImpCore.baseAvailable()) {
            base.HordeCore.notify(this.error_msg, 'horde.error');
            window.close();
        } else {
            var tmp = document.createElement('SPAN');
            tmp.textContent = this.error_msg;
            document.body.appendChild(tmp);
        }
    }

};

/* Initialize onload handler. */
document.addEventListener('DOMContentLoaded', ImpMaillog.onDomLoad.bind(ImpMaillog));
