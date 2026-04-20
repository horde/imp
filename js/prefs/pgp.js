/**
 * PGP preferences screen.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2014-2015 Horde LLC
 * @license    GPL-2 (http://www.horde.org/licenses/gpl)
 */

var ImpPgp = {

    replaceDate: function(d)
    {
        var elt = document.getElementById('generate_expire_date');
        elt.value = d.getTime();
        elt.nextElementSibling.textContent = this.months[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();
    },

    clickHandler: function(e)
    {
        var elt = e.detail.element();

        switch (elt.id) {
        case 'generate_expire':
            var sib = elt.nextElementSibling;
            sib.hidden = !sib.hidden;
            break;

        default:
            if (elt.classList.contains('calendarImg')) {
                Horde_Calendar.open(elt.id, new Date(Number(document.getElementById('generate_expire_date').value)));
                e.detail.stop();
            }
            break;
        }
    },

    calendarSelectHandler: function(e)
    {
        this.replaceDate(e.detail);
    },

    onDomLoad: function()
    {
        HordeCore.initHandler('click');

        var now = new Date();
        now.setFullYear(now.getFullYear() + 1);
        this.replaceDate(now);
    }

};

document.addEventListener('DOMContentLoaded', ImpPgp.onDomLoad.bind(ImpPgp));
document.addEventListener('HordeCore:click', ImpPgp.clickHandler.bind(ImpPgp));
document.addEventListener('Horde_Calendar:select', ImpPgp.calendarSelectHandler.bind(ImpPgp));
