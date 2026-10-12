/**
 * Contacts page.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2014-2015 Horde LLC
 * @license    GPL-2 (http://www.horde.org/licenses/gpl)
 */

var ImpContacts = {

    // initial,
    // searchGhost,
    // text,

    selectedAddresses: function()
    {
        return Array.from(document.getElementById('selected_addresses').querySelectorAll('[value]'));
    },

    addAddress: function(f)
    {
        var df = document.createDocumentFragment(),
            sa = this.selectedAddresses(),
            sr = document.getElementById('search_results'),
            sel = Array.from(sr.selectedOptions).map(function(o) { return o.value; });

        if (!sel.length) {
            HordeCore.notify(this.text.select, 'horde.warning');
            return;
        }

        sel.forEach(function(s) {
            if (!sa.some(function(j) {
                return (j.getAttribute('value') == s) &&
                       (j._header == f);
            })) {
                var opt = document.createElement('OPTION');
                opt.value = s;
                opt._header = f;
                var em = document.createElement('EM');
                em.textContent = this.text.rcpt[f] + ': ';
                opt.appendChild(em);
                opt.appendChild(document.createTextNode(s));
                df.appendChild(opt);
            }
        }, this);

        document.getElementById('selected_addresses').appendChild(df);
    },

    updateMessage: function()
    {
        var addr = {},
            sa = this.selectedAddresses();

        if (!parent.opener) {
            alert(this.text.closed);
            window.close();
            return;
        }

        if (!sa.length) {
            HordeCore.notify(this.text.no_contacts_selected, 'horde.warning');
            return;
        }

        sa.forEach(function(s) {
            var field = s._header;

            if (typeof addr[field] === 'undefined') {
                addr[field] = [];
            }

            addr[field].push(s.getAttribute('value'));
        });

        $(parent.opener.document).fire('ImpContacts:update', addr);
        window.close();
    },

    removeAddress: function()
    {
        Array.from(document.getElementById('selected_addresses').children).forEach(function(o) {
            if (o.selected) {
                o.remove();
            }
        });
    },

    contactsSearch: function()
    {
        var sr = document.getElementById('search_results');

        Array.from(sr.querySelectorAll('[value]')).forEach(function(o) { o.remove(); });
        Array.from(sr.children).forEach(function(o) { o.hidden = true; });
        var opt = document.createElement('OPTION');
        opt.disabled = true;
        opt.value = "";
        opt.textContent = this.text.searching;
        sr.appendChild(opt);

        HordeCore.doAction('contactsSearch', {
            search: this.searchGhost.hasinput ? document.getElementById('search').value : '',
            source: document.getElementById('source').value
        }, {
            callback: function(r) {
                Array.from(sr.querySelectorAll(':not([value])')).forEach(function(o) { o.hidden = false; });
                this.updateResults(r.results);
            }.bind(this)
        });
    },

    updateResults: function(r)
    {
        var df = document.createDocumentFragment(),
            sr = document.getElementById('search_results');

        r.forEach(function(addr) {
            var opt = document.createElement('OPTION');
            opt.value = addr;
            opt.textContent = addr;
            df.appendChild(opt);
        });

        Array.from(sr.querySelectorAll('[value]')).forEach(function(o) { o.remove(); });

        if (r.length) {
            sr.appendChild(df);
        }
    },

    resize: function()
    {
        window.resizeBy(
            0,
            Math.max(0, document.body.clientHeight - document.documentElement.clientHeight)
        );
    },

    onDomLoad: function()
    {
        HordeCore.initHandler('click');
        HordeCore.initHandler('dblclick');

        document.getElementById('contacts').addEventListener('FormGhost:submit', function(e) {
            if (this.searchGhost.hasinput) {
                this.contactsSearch();
            }
        }.bind(this));

        if (this.initial) {
            this.updateResults(this.initial);
            delete this.initial;
        }

        this.searchGhost = new FormGhost('search');

        setTimeout(this.resize.bind(this), 100);
    },

    clickHandler: function(e)
    {
        var id = e.target.id || (e.target.closest('[id]') || {}).id;

        switch (id) {
        case 'btn_add_bcc':
            this.addAddress('bcc');
            break;

        case 'btn_add_cc':
            this.addAddress('cc');
            break;

        case 'btn_add_to':
            this.addAddress('to');
            break;

        case 'btn_cancel':
            window.close();
            e.detail.hordecore_stop = true;
            break;

        case 'btn_clear':
            this.searchGhost.reset();
            break;

        case 'btn_delete':
            this.removeAddress();
            break;

        case 'btn_search_all':
            document.getElementById('search').value = '';
            this.contactsSearch();
            break;

        case 'btn_update':
            this.updateMessage();
            break;
        }
    },

    dblclickHandler: function(e)
    {
        var id = e.target.id || (e.target.closest('[id]') || {}).id;

        switch (id) {
        case 'search_results':
            this.addAddress('to');
            break;

        case 'selected_addresses':
            this.removeAddress();
            break;
        }
    }

};

document.addEventListener('DOMContentLoaded', ImpContacts.onDomLoad.bind(ImpContacts));
document.addEventListener('HordeCore:click', ImpContacts.clickHandler.bind(ImpContacts));
document.addEventListener('HordeCore:dblclick', ImpContacts.dblclickHandler.bind(ImpContacts));
