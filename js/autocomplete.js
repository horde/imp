/**
 * An autocompleter implementation that provides a more advanced UI
 * (completed elements are stored in separate DIV elements).
 *
 * Events handled by this class:
 *   - AutoComplete:focus
 *   - AutoComplete:reset
 *   - AutoComplete:update
 *
 * Events triggered by this class (on input element):
 *   - AutoComplete:resize
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @author     Michael J Rubinsky <mrubinsk@horde.org>
 * @copyright  2008-2015 Horde LLC
 * @license    GPL-2 (http://www.horde.org/licenses/gpl)
 */
var IMP_Autocompleter = function(elt, params) {
    var active;

    this.cache = {};
    this.itemid = 0;
    this.lastinput = '';
    this.p = Object.assign({
        autocompleterParams: {},
        // Outer div/fake input box and CSS class
        // box (created below)
        boxClass: 'hordeACBox',
        boxClassFocus: '',
        entryDelay: 0.4,
        // CSS class for real input field
        growingInputClass: 'hordeACTrigger',
        // input, (created below)
        // <ul> CSS class
        listClass: 'hordeACList',
        listClassItem: 'hordeACListItem',
        loadingText: 'Loading...',
        loadingTextClass: '',
        maxItemSize: 50,
        minChars: 3,
        noResultsText: 'No Results Found',
        noResultsTextClass: '',
        onAdd: function() {},
        onBeforeServerRequest: function() {},
        onEntryClick: function() {},
        onServerSuggestion: function() {},
        processValueCallback: function() {},
        removeClass: 'hordeACItemRemove',
        requireSelection: false,
        shortDisplayCallback: function(x) { return x; }
    }, params || {});

    // The original input element is transformed into a hidden input
    // field that holds the raw return value.
    this.elt = document.getElementById(elt);
    this.elt.setAttribute('autocomplete', 'off');

    // Create an autocomplete input element that holds the JSON encoded
    // return value.
    this.elt_ac = document.createElement('INPUT');
    this.elt_ac.name = this.elt.id + '_ac';
    this.elt_ac.type = 'hidden';
    this.elt.after(this.elt_ac);

    this.box = document.createElement('DIV');
    this.box.className = this.p.boxClass;

    // The input element and the <li> wrapper
    this.input = document.createElement('INPUT');
    this.input.autocomplete = 'off';
    this.input.className = this.p.growingInputClass;

    // Build the outer box
    var ul = document.createElement('UL');
    ul.className = this.p.listClass;
    var li = document.createElement('LI');
    li.appendChild(this.input);
    ul.appendChild(li);
    this.box.appendChild(ul);

    // Replace the single input element with the new structure and
    // move the old element into the structure while making sure it's
    // hidden.
    active = this.checkActiveElt(this.elt);
    this.elt.replaceWith(this.box);
    this.elt.hidden = true;
    this.box.appendChild(this.elt);
    if (active) {
        this.focus();
    }

    // Look for clicks on the box to simulate clicking in an input box
    this.box.addEventListener('click', this.clickHandler.bind(this));

    // Double-clicks cause an edit on existing entries.
    this.box.addEventListener('dblclick', this.dblclickHandler.bind(this));

    this.input.addEventListener('blur', this.blur.bind(this));
    this.input.addEventListener('keydown', this.keydownHandler.bind(this));

    this._watchInterval = setInterval(this.inputWatcher.bind(this), 250);

    document.addEventListener('AutoComplete:focus', this.triggerEvent.bind(this, this.focus.bind(this)));
    document.addEventListener('AutoComplete:reset', this.triggerEvent.bind(this, this.reset.bind(this)));
    document.addEventListener('AutoComplete:update', this.triggerEvent.bind(this, this.processInput.bind(this)));

    this.reset();
};

IMP_Autocompleter.prototype = {

    triggerEvent: function(func, e)
    {
        var elt = e.target;

        switch (elt) {
        case this.elt:
            e.preventDefault();
            e.stopPropagation();
            /* falls through */

        case document:
            func();
            break;
        }
    },

    checkActiveElt: function(elt)
    {
        try {
            return (document.activeElement &&
                    (document.activeElement == elt));
        } catch (e) {
            return false;
        }
    },

    focus: function()
    {
        try {
            this.input.focus();
            this.box.classList.add(this.p.boxClassFocus);
        } catch (e) {
            setTimeout(this.focus.bind(this), 0);
        }
    },

    blur: function()
    {
        this.box.classList.remove(this.p.boxClassFocus);
    },

    reset: function()
    {
        this.data = [];
        this.currentEntries().forEach(function(el) { el.remove(); });
        this.processValue(this.elt.value);
        this.processInput();
        this.updateHiddenInput();
    },

    processInput: function()
    {
        var tmp = this.input.value;

        if (tmp !== '') {
            this.addNewItems([ new IMP_Autocompleter_Elt(tmp) ]);
            this.updateInput('');
        }
    },

    processValue: function(val)
    {
        var tmp;

        if (!this.p.requireSelection) {
            tmp = this.p.processValueCallback(val.replace(/^\s+/, ''));
            this.addNewItems(tmp[0]);
            this.updateInput(tmp[1]);
        }
    },

    getElts: function()
    {
        return this.data.map(function(v) { return v.elt; });
    },

    getEntryByElt: function(elt)
    {
        return this.getEntryById(elt._itemid);
    },

    getEntryById: function(id)
    {
        for (var i = 0; i < this.data.length; i++) {
            if (this.data[i].id == id) {
                return this.data[i];
            }
        }
        return null;
    },

    addNewItems: function(value)
    {
        value = this.filterChoices(value);

        if (!value.length) {
            return false;
        }

        value.forEach(function(v) {
            v.elt = document.createElement('LI');
            v.elt.className = this.p.listClassItem;
            v.elt.title = v.label;
            v.id = ++this.itemid;

            var displayText = (v.short_d || this.p.shortDisplayCallback(v.label));
            if (displayText.length > this.p.maxItemSize) {
                displayText = displayText.substring(0, this.p.maxItemSize) + '...';
            }
            v.elt.appendChild(document.createTextNode(displayText));
            var delImg = this.deleteImg().cloneNode(true);
            delImg.hidden = false;
            v.elt.appendChild(delImg);
            v.elt._itemid = v.id;

            this.input.closest('LI').before(v.elt);

            this.data.push(v);
            this.p.onAdd(v);
        }, this);

        // Add to hidden input field.
        this.updateHiddenInput();

        if (this.knl) {
            this.knl.hide(); // eslint-disable-line horde/no-prototype-methods -- KeyNavList.hide()
        }

        return true;
    },

    filterChoices: function(c)
    {
        var cv = this.data.map(function(v) { return v.value; });

        return c.filter(function(v) {
            return cv.indexOf(v.value) === -1;
        });
    },

    currentEntries: function()
    {
        return Array.from(this.input.closest('UL').querySelectorAll('LI.' + this.p.listClassItem));
    },

    updateInput: function(input)
    {
        var entry;

        if (input instanceof HTMLElement) {
            entry = this.getEntryByElt(input);
            this.input.value = entry.value;
            this.removeEntry(entry);
        } else {
            if (this.input.value != input) {
                this.input.value = input;
                this.resize();
            }
            this.focus();
        }
    },

    removeEntry: function(entry)
    {
        if (!entry) {
            return;
        }

        entry.elt.remove();
        this.data = this.data.filter(function(v) {
            return (v.id != entry.id);
        });
        this.updateHiddenInput();
        this.resize();
    },

    // Format: value [, value [, ...]]
    // AC Format: [ [ value, ID ], [ ... ], ... ]
    updateHiddenInput: function()
    {
        var val = this.data.map(function(v) { return v.value; }).filter(function(v) { return v !== ''; });

        this.elt.value = val.join();
        this.elt_ac.value = JSON.stringify(val.map(function(v, i) { return [v, this.data[i].id]; }, this));
    },

    resize: function()
    {
        this.input.style.width = Math.max(80, this.input.value.length * 9) + 'px';
        this.input.dispatchEvent(new CustomEvent('AutoComplete:resize', { bubbles: true }));
    },

    deleteImg: function()
    {
        if (!this.dimg) {
            this.dimg = document.createElement('IMG');
            this.dimg.className = this.p.removeClass;
            this.dimg.src = this.p.deleteIcon;
            this.dimg.hidden = true;
            this.box.appendChild(this.dimg);
        }

        return this.dimg;
    },

    /* Event handlers. */

    clickHandler: function(e)
    {
        var elt = e.target,
            li = elt.closest('LI');

        if (!this.p.onEntryClick({ ac: this, elt: elt, entry: li })) {
            if (elt.classList.contains(this.p.removeClass)) {
                this.removeEntry(this.getEntryByElt(li));
            }
        }

        this.focus();
    },

    dblclickHandler: function(e)
    {
        var elt = e.target.closest('LI');

        this.processInput();

        if (elt && elt.classList.contains(this.p.listClassItem)) {
            this.updateInput(elt);
        } else {
            this.focus();
        }
    },

    keydownHandler: function(e)
    {
        var tmp;

        switch (e.key) {
        case 'Delete':
        case 'Backspace':
            if (!this.input.value.length) {
                var entries = this.currentEntries();
                tmp = entries.length ? entries[entries.length - 1] : null;
                if (tmp) {
                    this.updateInput(tmp);
                    e.preventDefault();
                }
            }
            break;
        }
    },

    inputWatcher: function()
    {
        var input = this.input.value;

        if (input != this.lastinput) {
            this.processValue(input);
            this.lastinput = this.input.value;
            if (this.acTimeout) {
                window.clearTimeout(this.acTimeout);
            }
            this.acTimeout = setTimeout(this.doAutocomplete.bind(this, this.lastinput), this.p.entryDelay * 1000);
            this.resize();
        }
    },

    doAutocomplete: function(t)
    {
        if (!this.checkActiveElt(this.input)) {
            return;
        }

        var c = this.cache[t], tmp;

        if (c) {
            this.updateAutocomplete(t, c);
        } else if (t.length >= this.p.minChars) {
            tmp = this.p.onBeforeServerRequest(t, this.cache);
            if (tmp) {
                this.cache[t] = tmp;
                this.updateAutocomplete(t, tmp);
                return;
            }

            this.initKnl();
            this.knl_status = 'loading';
            this.knl.show([]); // eslint-disable-line horde/no-prototype-methods -- KeyNavList.show()

            ImpCore.doAction(
                'autocompleteSearch',
                Object.assign(this.p.autocompleterParams, { search: t }),
                {
                    callback: function(r, ajax) {
                        this.cache[t] = r.results;
                        if (ajax.request.parameters.search == this.input.value) {
                            this.updateAutocomplete(t, r.results);
                        }
                    }.bind(this)
                }
            );

            // Pre-load the delete image now.
            this.deleteImg();
        }
    },

    updateAutocomplete: function(search, r)
    {
        var re,
            c = [],
            obs = [];

        if (!this.checkActiveElt(this.input)) {
            return;
        }

        r.forEach(function(e) {
            var elt = new IMP_Autocompleter_Elt(e.v, e.l, e.s);
            this.p.onServerSuggestion(e, elt);
            obs.push(elt);
        }, this);

        obs = this.filterChoices(obs);
        if (obs.length) {
            re = new RegExp(search, "i");

            obs.forEach(function(o) {
                var l = o.label,
                    l2 = '';

                (l.match(re) || []).forEach(function(m2) {
                    var idx = l.indexOf(m2),
                        tmp = document.createElement('span');
                    tmp.textContent = l.substr(0, idx);
                    l2 += tmp.innerHTML + "<strong>";
                    tmp.textContent = m2;
                    l2 += tmp.innerHTML + "</strong>";
                    l = l.substr(idx + m2.length);
                });

                if (l.length) {
                    var tmp = document.createElement('span');
                    tmp.textContent = l;
                    l2 += tmp.innerHTML;
                }

                c.push({ l: l2, v: o });
            });

            this.knl_status = false;
        } else {
            this.knl_status = 'noresults';
        }

        this.initKnl();
        this.knl.show(c); // eslint-disable-line horde/no-prototype-methods -- KeyNavList.show()
    },

    initKnl: function()
    {
        if (!this.knl) {
            this.knl = new KeyNavList(this.input, {
                onChoose: function(item) {
                    if (!this.knl_status && this.addNewItems([ item ])) {
                        this.updateInput('');
                    }
                }.bind(this),
                onShow: function(elt) {
                    switch (this.knl_status) {
                    case 'loading':
                        var loadLi = document.createElement('LI');
                        loadLi.textContent = this.p.loadingText;
                        loadLi.className = this.p.loadingTextClass;
                        elt.querySelector(':first-child').appendChild(loadLi);
                        break;

                    case 'noresults':
                        var noLi = document.createElement('LI');
                        noLi.textContent = this.p.noResultsText;
                        noLi.className = this.p.noResultsTextClass;
                        elt.querySelector(':first-child').appendChild(noLi);
                        break;
                    }
                }.bind(this)
            });
        }
    }

};

var IMP_Autocompleter_Elt = function(value, label, short_d) {
    this.value = value;
    this.label = label || value;
    if (short_d) {
        this.short_d = short_d;
    }
};
