/**
 * Advanced search page.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2014-2015 Horde LLC
 * @license    GPL-2 (http://www.horde.org/licenses/gpl)
 */

var ImpSearch = {

    // The following variables are defined in PHP code:
    //   data, i_criteria, i_mboxes, i_recent, text

    criteria: {},
    mbox_to_add: $H(),
    mboxes: $H(),
    saved_searches: {},

    updateRecentSearches: function(searches)
    {
        var fragment = document.createDocumentFragment(),
            node = new Element('OPTION');

        $('recent_searches_div').show();

        this.saved_searches = $H(searches);
        this.saved_searches.each(function(s) {
            fragment.appendChild($(node.clone(false)).writeAttribute({ value: s.key.escapeHTML() }).update(s.value.l.escapeHTML()));
        }, this);

        $('recent_searches').appendChild(fragment);
    },

    // Criteria actions

    showOr: function(show)
    {
        var or = $('search_criteria_add').down('[value="or"]');
        if (show) {
            or.show().next().show();
        } else {
            or.hide().next().hide();
        }
    },

    updateCriteria: function(criteria)
    {
        this.resetCriteria();

        criteria.each(function(c) {
            var crit = c.criteria;

            switch (c.element) {
            case 'IMP_Search_Element_Attachment':
                this.insertFilter('attach', crit);
                break;

            case 'IMP_Search_Element_Bulk':
                this.insertFilter('bulk', crit);
                break;

            case 'IMP_Search_Element_Daterange':
                // JS Date() requires timestamp in ms; PHP value is in secs
                this.insertDate(crit.b ? new Date(crit.b * 1000) : 0, crit.e ? new Date(crit.e * 1000) : 0, crit.n);
                break;

            case 'IMP_Search_Element_Flag':
                this.insertFlag(encodeURIComponent(decodeURIComponent(crit.f)), !crit.s);
                break;

            case 'IMP_Search_Element_Header':
                switch (crit.h) {
                case 'from':
                case 'to':
                case 'cc':
                case 'bcc':
                case 'subject':
                    this.insertText(crit.h, crit.t, crit.n);
                    break;

                default:
                    this.insertCustomHdr({ h: crit.h.capitalize(), s: crit.t }, crit.n);
                    break;
                }
                break;

            case 'IMP_Search_Element_Mailinglist':
                this.insertFilter('mailinglist', crit);
                break;

            case 'IMP_Search_Element_Or':
                this.insertOr();
                break;

            case 'IMP_Search_Element_Personal':
                this.insertFilter('personal', crit);
                break;

            case 'IMP_Search_Element_Recipient':
                this.insertText('recip', crit.t, crit.n);
                break;

            case 'IMP_Search_Element_Size':
                this.insertSize(crit.l ? 'size_larger' : 'size_smaller', crit.s);
                break;

            case 'IMP_Search_Element_Text':
                this.insertText(crit.b ? 'body' : 'text', crit.t, crit.n);
                break;

            case 'IMP_Search_Element_Within':
                this.insertWithin(crit.o ? 'older' : 'younger', { l: this.data.constants.within.index(crit.t), v: crit.v });
                break;
            }
        }, this);

        if ($('search_criteria').childElements().size()) {
            this.showOr(true);
        }
    },

    getCriteriaLabel: function(id, nocolon)
    {
        var sca = $('search_criteria_add').down('[value="' + RegExp.escape(id) + '"]');
        return (sca.textContent || sca.innerText) + (nocolon ? ' ' : ': ');
    },

    deleteCriteria: function(div)
    {
        var elts, tmp;

        delete this.criteria[div.identify()];
        div.remove();

        elts = $('search_criteria').childElements();

        if (elts.size()) {
            tmp = elts.first();
            if (!tmp.down('EM.join')) {
                tmp = elts.last();
            }

            if (tmp.down('EM.join')) {
                if (tmp.down('EM.joinOr')) {
                    delete this.criteria[tmp.identify()];
                    this.showOr(true);
                }
                tmp.down('EM.join').remove();
            }
        } else {
            this.showOr(false);
        }
    },

    resetCriteria: function()
    {
        var elts = $('search_criteria').childElements();
        if (elts.size()) {
            elts.invoke('remove');
            this.criteria = {};
            this.showOr(false);
        }
    },

    insertCriteria: function(tds)
    {
        var div = new Element('DIV', { className: 'searchId' }),
            div2 = new Element('DIV', { className: 'searchElement' });

        if ($('search_criteria').childElements().size()) {
            if (this.criteria[$('search_criteria').childElements().last().readAttribute('id')].t != 'or') {
                div.insert(
                    new Element('EM', { className: 'join' })
                        .insert(this.text.and)
                );
            }
        } else {
            this.showOr(true);
        }

        div.insert(div2);

        tds.each(function(node) {
            div2.insert(node);
        });

        div2.insert(new Element('A', { href: '#', className: 'iconImg searchuiImg searchuiDelete' }));

        $('search_criteria_add').clear();
        $('search_criteria').insert(div);

        return div.identify();
    },

    insertOr: function()
    {
        var div = new Element('DIV').insert(
            new Element('EM', { className: 'join joinOr' })
                .insert('--&nbsp;' + this.text.or + '&nbsp;--')
        );

        $('search_criteria_add').clear();
        $('search_criteria').insert(div);
        this.criteria[div.identify()] = { t: 'or' };
    },

    insertText: function(id, text, not)
    {
        var tmp = [
            new Element('EM').insert(this.getCriteriaLabel(id)),
            new Element('INPUT', { type: 'text', size: 25 }).setValue(text),
            new Element('SPAN', { className: 'notMatch' }).insert(new Element('INPUT', { className: 'checkbox', type: 'checkbox' }).setValue(not)).insert(this.text.not_match)
        ];
        this.criteria[this.insertCriteria(tmp)] = { t: id };
        tmp[1].activate();
    },

    insertCustomHdr: function(text, not)
    {
        text = text || { h: '', s: '' };

        var tmp = [
            new Element('EM').insert(this.text.customhdr),
            new Element('INPUT', { type: 'text', size: 25 }).setValue(text.h),
            new Element('SPAN').insert(new Element('EM').insert(this.text.search_term + ' ')).insert(new Element('INPUT', { type: 'text', size: 25 }).setValue(text.s)),
            new Element('SPAN', { className: 'notMatch' }).insert(new Element('INPUT', { className: 'checkbox', type: 'checkbox' }).setValue(not)).insert(this.text.not_match)
        ];
        this.criteria[this.insertCriteria(tmp)] = { t: 'customhdr' };
        tmp[1].activate();
    },

    insertSize: function(id, size)
    {
        var tmp = [
            new Element('EM').insert(this.getCriteriaLabel(id)),
            // Convert from bytes to KB
            new Element('INPUT', { type: 'text', size: 10 }).setValue(Object.isNumber(size) ? Math.round(size / 1024) : '')
        ];
        this.criteria[this.insertCriteria(tmp)] = { t: id };
        tmp[1].activate();
    },

    insertDate: function(begin, end, not)
    {
        var elt1, elt2, tmp, tmp2;

        elt1 = new Element('SPAN').insert(
            new Element('SPAN')
        ).insert(
            new Element('A', { href: '#', className: 'dateReset', title: this.text.datereset }).insert(
                new Element('SPAN', { className: 'iconImg searchuiImg closeImg' })
            )
        ).insert(
            new Element('A', { href: '#', className: 'calendarPopup', title: this.text.dateselection }).insert(
                new Element('SPAN', { className: 'iconImg searchuiImg calendarImg' })
            )
        );
        elt2 = elt1.clone(true);

        tmp = [
            new Element('EM').insert(this.getCriteriaLabel('date_range')),
            elt1.addClassName('beginDate'),
            new Element('SPAN').insert(this.text.to),
            elt2.addClassName('endDate'),
            new Element('SPAN', { className: 'notMatch' }).insert(new Element('INPUT', { className: 'checkbox', type: 'checkbox' }).setValue(not)).insert(this.text.not_match)
        ];

        tmp2 = this.insertCriteria(tmp);
        this.updateDate(tmp2, elt1, begin);
        this.updateDate(tmp2, elt2, end);
    },

    updateDate: function(id, elt, data)
    {
        if (data) {
            elt.down('SPAN').update(this.data.months[data.getMonth()] + ' ' + data.getDate() + ', ' + data.getFullYear());
            elt.down('A.dateReset').show();

            // Convert Date object to a UTC object, since JSON outputs in UTC.
            data = new Date(Date.UTC(data.getFullYear(), data.getMonth(), data.getDate()));
        } else {
            elt.down('SPAN').update('-----');
            elt.down('A.dateReset').hide();
        }

        // Need to store date information at all times in criteria, since
        // there is no other way to track this information (there is no
        // form field for this type). Also, convert Date object to a UTC
        // object, since JSON outputs in UTC.
        if (!this.criteria[id]) {
            this.criteria[id] = { t: 'date_range' };
        }
        this.criteria[id][elt.hasClassName('beginDate') ? 'b' : 'e'] = data;
    },

    insertWithin: function(id, data)
    {
        data = data || { l: '', v: '' };

        var tmp = [
            new Element('EM').insert(this.getCriteriaLabel(id)),
            new Element('SPAN').insert(new Element('INPUT', { type: 'text', size: 8 }).setValue(data.v)).insert(' ').insert($($('within_criteria').clone(true)).writeAttribute({ id: null }).show().setValue(data.l))
        ];
        this.criteria[this.insertCriteria(tmp)] = { t: id };
        tmp[1].down().activate();
    },

    insertFilter: function(id, not)
    {
        var tmp = [
            new Element('EM').insert(this.getCriteriaLabel(id, true)),
            new Element('SPAN', { className: 'notMatch' }).insert(new Element('INPUT', { className: 'checkbox', type: 'checkbox' }).setValue(not)).insert(this.text.not_match)
        ];
        this.criteria[this.insertCriteria(tmp)] = { t: id };
    },

    insertFlag: function(id, not)
    {
        var tmp = [
            new Element('EM').insert(this.text.flag),
            new Element('SPAN', { className: 'searchFlag' }).insert(this.getCriteriaLabel(id).slice(0, -2).escapeHTML()),
            new Element('SPAN', { className: 'notMatch' }).insert(new Element('INPUT', { className: 'checkbox', type: 'checkbox' }).setValue(not)).insert(this.text.not_match)
        ];
        this.criteria[this.insertCriteria(tmp)] = { t: id };
    },

    // Mailbox actions

    // mboxes = (object) m: mailboxes, s: subfolders
    updateMailboxes: function(mboxes)
    {
        this.resetMailboxes();
        mboxes.m.each(function(f) {
            this.insertMailbox(f, false);
        }, this);
        mboxes.s.each(function(f) {
            this.insertMailbox(f, true);
        }, this);
    },

    deleteMailbox: function(div)
    {
        var first, keys,
            id = div.identify();

        this.disableMailbox(false, this.mboxes.get(id));
        this.mboxes.unset(id);
        div.remove();

        keys = $('search_mboxes').childElements().pluck('id');

        if (keys.size()) {
            first = keys.first();
            if ($(first).down().hasClassName('join')) {
                $(first).down().remove();
            }
        } else {
            $('search_mboxes_add').up().show();
            $('search_mboxes').hide();
        }
    },

    resetMailboxes: function()
    {
        elts = $('search_mboxes').childElements();

        if (elts.size()) {
            this.mboxes.values().each(this.disableMailbox.bind(this, false));
            elts.invoke('remove');
            $('search_mboxes_add').clear().up().show();
            this.mboxes = $H();
        }

        this.mbox_to_add = $H();
    },

    insertMailbox: function(mbox, checked)
    {
        if (!$('search_loaded').visible()) {
            this.mbox_to_add.set(mbox, checked);
            return;
        }

        var div = new Element('DIV', { className: 'searchId' }),
            div2 = new Element('DIV', { className: 'searchElement' });

        if (mbox == this.allsearch) {
            this.resetMailboxes();
            $('search_mboxes_add').show().up().hide();
            div2.insert(
                new Element('EM').insert(this.text.search_all.escapeHTML())
            ).insert(
                new Element('A', { href: '#', className: 'iconImg searchuiImg searchuiDelete' })
            );
        } else {
            if ($('search_mboxes').show().childElements().size()) {
                div.insert(new Element('EM', { className: 'join' }).insert(this.text.and));
            }

            div2.insert(
                new Element('EM').insert(this.getMailboxLabel(mbox).escapeHTML())
            ).insert(
                new Element('SPAN', { className: 'subfolders' }).insert(new Element('INPUT', { className: 'checkbox', type: 'checkbox' }).setValue(checked)).insert(this.text.subfolder_search).setStyle(mbox == this.data.inbox ? { display: 'none' } : {})
            ).insert(
                new Element('A', { href: '#', className: 'iconImg searchuiImg searchuiDelete' })
            );

            this.disableMailbox(true, mbox);
            $('search_mboxes_add').clear();
        }

        div.insert(div2);
        $('search_mboxes').insert(div);

        this.mboxes.set(div.identify(), mbox);
    },

    getMailboxLabel: function(mbox)
    {
        return this.data.mbox_list[mbox];
    },

    disableMailbox: function(disable, mbox)
    {
        $('search_mboxes_add').down('[value="' + mbox + '"]').writeAttribute({ disabled: disable });
    },

    // Miscellaneous actions

    submit: function()
    {
        var criteria,
            data = [],
            f_out = { mbox: [], subfolder: [] },
            type = $F('search_type');

        if (type && !$('search_label').present()) {
            alert(this.text.need_label);
            return;
        }

        if (type != 'filter' && !this.mboxes.size()) {
            alert(this.text.need_mbox);
            return;
        }

        criteria = $('search_criteria').childElements().pluck('id');
        if (!criteria.size()) {
            alert(this.text.need_criteria);
            return;
        }

        criteria.each(function(c) {
            var tmp;

            if (this.criteria[c].t == 'or') {
                data.push(this.criteria[c]);
                return;
            }

            switch (this.data.types[this.criteria[c].t]) {
            case 'header':
            case 'text':
                this.criteria[c].n = ~~(!!$F($(c).down('INPUT[type=checkbox]')));
                this.criteria[c].v = $F($(c).down('INPUT[type=text]'));
                data.push(this.criteria[c]);
                break;

            case 'customhdr':
                this.criteria[c].n = ~~(!!$F($(c).down('INPUT[type=checkbox]')));
                this.criteria[c].v = { h: $F($(c).down('INPUT')), s: $F($(c).down('INPUT', 1)) };
                data.push(this.criteria[c]);
                break;

            case 'size':
                tmp = Number($F($(c).down('INPUT')));
                if (!isNaN(tmp)) {
                    // Convert KB to bytes
                    this.criteria[c].v = tmp * 1024;
                    data.push(this.criteria[c]);
                }
                break;

            case 'date':
                if (!this.criteria[c].b && !this.criteria[c].e) {
                    alert(this.text.need_date);
                    return;
                }
                this.criteria[c].n = ~~(!!$F($(c).down('INPUT[type=checkbox]')));
                data.push(this.criteria[c]);
                break;

            case 'within':
                this.criteria[c].v = { l: $F($(c).down('SELECT')), v: parseInt($F($(c).down('INPUT')), 10) };
                data.push(this.criteria[c]);
                break;

            case 'filter':
                this.criteria[c].n = ~~(!!$F($(c).down('INPUT[type=checkbox]')));
                data.push(this.criteria[c]);
                break;

            case 'flag':
                this.criteria[c].n = ~~(!!$F($(c).down('INPUT[type=checkbox]')));
                data.push({
                    n: this.criteria[c].n,
                    t: 'flag',
                    v: this.criteria[c].t
                });
                break;
            }
        }, this);

        $('criteria_form').setValue(Object.toJSON(data));

        if ($('search_mboxes_add').up().visible()) {
            this.mboxes.each(function(f) {
                var type = $F($(f.key).down('INPUT[type=checkbox]'))
                    ? 'subfolder'
                    : 'mbox';
                f_out[type].push(f.value);
            });
        } else {
            f_out.mbox.push(this.allsearch);
        }
        $('mboxes_form').setValue(Object.toJSON(f_out));

        $('search_form').submit();
    },

    clickHandler: function(e)
    {
        var cnames,
            elt = e.element();

        switch (elt.readAttribute('id')) {
        case 'search_submit':
            this.submit();
            e.memo.stop();
            break;

        case 'search_reset':
            this.resetCriteria();
            this.resetMailboxes();
            return;

        case 'search_return':
            e.memo.hordecore_stop = true;
            window.parent.ImpBase.go('mbox', this.data.searchmbox);
            break;

        case 'search_edit_query_cancel':
            e.memo.hordecore_stop = true;
            if (this.data.dynamic_view) {
                window.parent.ImpBase.go();
            } else {
                document.location.href = this.prefsurl;
            }
            break;

        case 'show_unsub':
            this.loadMailboxList(1);
            elt.remove();
            e.memo.stop();
            break;

        default:
            cnames = $w(elt.className);

            if (cnames.indexOf('searchuiDelete') !== -1) {
                if (elt.up('#search_criteria')) {
                    this.deleteCriteria(elt.up('DIV.searchId'));
                } else {
                    this.deleteMailbox(elt.up('DIV.searchId'));
                }
                e.memo.stop();
            } else if (cnames.indexOf('calendarImg') !== -1) {
                Horde_Calendar.open(elt.identify(), this.criteria[elt.up('DIV.searchId').identify()].v);
                e.memo.stop();
            } else if ((cnames.indexOf('closeImg') !== -1) &&
                       (elt.up('SPAN.beginDate') || elt.up('SPAN.endDate'))) {
                this.updateDate(
                    elt.up('DIV.searchId').identify(),
                    elt.up('SPAN'),
                    0
                );
                e.memo.stop();
            }
            break;
        }
    },

    changeHandler: function(e)
    {
        var tmp,
            elt = e.element(),
            val = $F(elt);

        switch (elt.readAttribute('id')) {
        case 'recent_searches':
            tmp = this.saved_searches.get(val);
            this.updateCriteria(tmp.c);
            this.updateMailboxes(tmp.f);
            elt.clear();
            break;

        case 'search_criteria_add':
            if (val == 'or') {
                this.insertOr();
                break;
            }

            switch (this.data.types[val]) {
            case 'header':
            case 'text':
                this.insertText(val);
                break;

            case 'customhdr':
                this.insertCustomHdr();
                break;

            case 'size':
                this.insertSize(val);
                break;

            case 'date':
                this.insertDate();
                break;

            case 'within':
                this.insertWithin(val);
                break;

            case 'filter':
                this.insertFilter(val);
                break;

            case 'flag':
                this.insertFlag(val);
                break;
            }
            break;

        case 'search_mboxes_add':
            this.insertMailbox(val);
            break;

        case 'search_type':
            $('search_label').up('DIV').show();
            break;
        }

        e.stop();
    },

    calendarSelectHandler: function(e)
    {
        this.updateDate(
            e.findElement('DIV.searchId').identify(),
            e.element().up('SPAN'),
            e.memo
        );
    },

    loadMailboxList: function(unsub)
    {
        HordeCore.doAction('searchMailboxList', {
            unsub: unsub
        }, {
            callback: function(r) {
                var sfa = $('search_mboxes_add'),
                    vals = sfa.select('[disabled]').pluck('value');

                this.data.mbox_list = r.mbox_list;
                sfa.update(r.tree);

                $('search_loading').hide();
                $('search_loaded').show();

                vals.each(function(v) {
                    if (v.length) {
                        this.disableMailbox(true, v);
                    }
                }, this);

                this.mbox_to_add.each(function(pair) {
                    this.insertMailbox(pair.key, pair.value);
                }, this);

                this.mbox_to_add = $H();
            }.bind(this)
        });
    },

    onDomLoad: function()
    {
        if (!this.data) {
            this.onDomLoad.bind(this).delay(0.1);
            return;
        }

        /* Asynchronously load the mailbox list. */
        if ($('search_loading')) {
            this.loadMailboxList(0);
        }

        HordeCore.initHandler('click');

        if (Prototype.Browser.IE) {
            $$('SELECT').compact().invoke('observe', 'change', this.changeHandler.bindAsEventListener(this));
        } else {
            document.observe('change', this.changeHandler.bindAsEventListener(this));
        }

        this.data.constants.within = $H(this.data.constants.within);

        if (this.i_recent) {
            this.updateRecentSearches(this.i_recent);
            delete this.i_recent;
        }

        if (this.i_criteria) {
            this.updateCriteria(this.i_criteria);
            delete this.i_criteria;
        }

        if (this.i_mboxes) {
            this.updateMailboxes(this.i_mboxes);
            delete this.i_mboxes;
        }
    }

};

document.observe('dom:loaded', ImpSearch.onDomLoad.bind(ImpSearch));
document.observe('HordeCore:click', ImpSearch.clickHandler.bindAsEventListener(ImpSearch));
document.observe('Horde_Calendar:select', ImpSearch.calendarSelectHandler.bindAsEventListener(ImpSearch));
