/**
 * Dynamic message view.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2005-2015 Horde LLC
 * @license    GPL-2 (http://www.horde.org/licenses/gpl)
 */

var ImpMessage = {

    // buid,
    // mbox,
    // msg_atc,
    // msg_md,

    quickreply: function(type)
    {
        var func;

        switch (type) {
        case 'reply':
        case 'reply_all':
        case 'reply_auto':
        case 'reply_list':
            document.getElementById('compose').hidden = false;
            document.getElementById('redirect').hidden = true;
            func = 'getReplyData';
            break;

        case 'forward_auto':
        case 'forward_attach':
        case 'forward_body':
        case 'forward_both':
            document.getElementById('compose').hidden = false;
            document.getElementById('redirect').hidden = true;
            func = 'getForwardData';
            break;

        case 'forward_editasnew':
            document.getElementById('compose').hidden = false;
            document.getElementById('redirect').hidden = true;
            func = 'getResumeData';
            type = 'editasnew';
            break;

        case 'forward_redirect':
            document.getElementById('compose').hidden = true;
            document.getElementById('redirect').hidden = false;
            func = 'getRedirectData';
            break;
        }

        document.getElementById('msgData').hidden = true;
        document.getElementById('qreply').hidden = false;

        ImpCore.doAction(func, {
            imp_compose: document.getElementById('composeCache').value,
            type: type,
            view: this.mbox
        }, {
            callback: ImpCompose.fillForm.bind(ImpCompose),
            uids: [ this.buid ]
        });
    },

    updateAddressHeader: function(e)
    {
        var tr = e.target.closest('TR');
        ImpCore.doAction('addressHeader', {
            header: tr.id.substring(9).toLowerCase(),
            view: this.mbox
        }, {
            callback: this._updateAddressHeaderCallback.bind(this),
            uids: [ this.buid ]
        });
    },

    _updateAddressHeaderCallback: function(r)
    {
        Object.entries(r.hdr_data).forEach(function(d) {
            this.updateHeader(d[0], d[1]);
        }, this);
    },

    updateHeader: function(hdr, data, limit)
    {
        var elt = document.getElementById('msgHeader' + hdr.charAt(0).toUpperCase() + hdr.slice(1));
        if (elt) {
            elt.hidden = false;
            var td = elt.querySelector('TD:last-child');
            ImpCore.buildAddressLinks(data, td, limit);
            if (hdr === 'from' && this.resent) {
                ImpCore.buildResentHeader(td, this.resent);
                delete this.resent;
            }
        }
    },

    reloadPart: function(mimeid, params)
    {
        ImpCore.doAction('inlineMessageOutput', Object.assign(params, {
            mimeid: mimeid,
            view: this.mbox
        }), {
            callback: function(r) {
                var target = document.getElementById('messageBody')
                    .querySelector('DIV[impcontentsmimeid="' + r.mimeid + '"]');
                target.outerHTML = r.text;
            },
            uids: [ this.buid ]
        });
    },

    /* Click handlers. */
    clickHandler: function(e)
    {
        var base, cnames,
            id = e.target.id || (e.target.closest('[id]') || {}).id;

        switch (id) {
        case 'windowclose':
            window.close();
            e.detail.hordecore_stop = true;
            break;

        case 'forward_link':
            this.quickreply('forward_auto');
            e.detail.stop();
            break;

        case 'reply_link':
            this.quickreply('reply_auto');
            e.detail.stop();
            break;

        case 'button_delete':
        case 'button_innocent':
        case 'button_spam':
            if ((base = ImpCore.baseAvailable())) {
                base.focus();
                if (id == 'button_delete') {
                    base.ImpBase.deleteMsg({
                        mailbox: this.mbox,
                        uid: this.buid
                    });
                } else {
                    base.ImpBase.reportSpam(id == 'button_spam', {
                        mailbox: this.mbox,
                        uid: this.buid
                    });
                }
            } else {
                if (id == 'button_delete') {
                    ImpCore.doAction('deleteMessages', {
                        view: this.mbox
                    }, {
                        uids: [ this.buid ],
                        view: this.mbox
                    });
                } else {
                    ImpCore.doAction('reportSpam', {
                        spam: ~~(id == 'button_spam'),
                        view: this.mbox
                    }, {
                        uids: [ this.buid ],
                        view: this.mbox
                    });
                }
            }
            window.close();
            e.detail.hordecore_stop = true;
            break;

        case 'msg_view_source':
            HordeCore.popupWindow(ImpCore.conf.URI_VIEW, {
                actionID: 'view_source',
                buid: this.buid,
                id: 0,
                mailbox: this.mbox
            }, {
                name: this.buid + '|' + this.mbox
            });
            break;

        case 'msg_all_parts':
            ImpCore.doAction('messageMimeTree', {
                view: this.mbox
            }, {
                callback: this._mimeTreeCallback.bind(this),
                uids: [ this.buid ]
            });
            break;

        case 'qreply':
            if (e.detail.element().match('DIV.headercloseimg IMG')) {
                ImpCompose.confirmCancel();
            }
            break;

        case 'send_mdn_link':
            ImpCore.doAction('sendMDN', {
                view: this.mbox
            }, {
                callback: function(r) {
                    var msg = document.getElementById('sendMdnMessage');
                    if (msg) {
                        msg.parentElement.hidden = true;
                    }
                },
                uids: [ this.buid ]
            });
            e.detail.stop();
            break;

        default:
            cnames = (e.target.className || '').split(/\s+/);

            if (cnames.indexOf('printAtc') !== -1) {
                HordeCore.popupWindow(ImpCore.conf.URI_VIEW, {
                    actionID: 'print_attach',
                    buid: this.buid,
                    id: e.target.getAttribute('mimeid'),
                    mailbox: this.mbox
                }, {
                    name: this.buid + '|' + this.mbox + '|print',
                    onload: IMP_JS.printWindow
                });
                e.detail.stop();
            } else if (cnames.indexOf('stripAtc') !== -1) {
                if (window.confirm(ImpCore.text.strip_warn)) {
                    ImpCore.reloadMessage({
                        actionID: 'strip_attachment',
                        buid: this.buid,
                        id: e.target.getAttribute('mimeid'),
                        mailbox: this.mbox
                    });
                }
                e.detail.stop();
            }
            break;
        }
    },

    contextOnClick: function(e)
    {
        var id = e.detail.elt.id;

        switch (id) {
        case 'ctx_reply_reply':
        case 'ctx_reply_reply_all':
        case 'ctx_reply_reply_list':
            this.quickreply(id.substring(10));
            break;

        case 'ctx_forward_attach':
        case 'ctx_forward_body':
        case 'ctx_forward_both':
        case 'ctx_forward_editasnew':
        case 'ctx_forward_redirect':
            this.quickreply(id.substring(4));
            break;
        }
    },

    resizeWindow: function()
    {
        var mb = document.getElementById('msgData').querySelector('DIV.messageBody');

        mb.style.height = Math.max(
                        document.documentElement.clientHeight -
                            mb.getBoundingClientRect().top -
                            parseInt(window.getComputedStyle(mb).paddingTop, 10) -
                            parseInt(window.getComputedStyle(mb).paddingBottom, 10),
                         0
                    ) + 'px';
    },

    _mimeTreeCallback: function(r)
    {
        var allParts = document.getElementById('msg_all_parts');
        if (allParts) { allParts.parentElement.hidden = true; }

        var partlist = document.getElementById('partlist');
        partlist.hidden = false;
        partlist.innerHTML = r.tree;

        this.resizeWindow();
    },

    onDomLoad: function()
    {
        var base;

        HordeCore.initHandler('click');

        if (ImpCore.conf.disable_compose) {
            ['reply_link', 'forward_link'].forEach(function(id) {
                var elt = document.getElementById(id);
                if (elt) {
                    var span = elt.closest('SPAN');
                    if (span) { span.remove(); }
                }
            });
            delete ImpCore.context.ctx_contacts['new'];
        } else {
            ImpCore.addPopdown('reply_link', 'reply');
            ImpCore.addPopdown('forward_link', 'forward');
            if (!this.reply_list) {
                delete ImpCore.context.ctx_reply.reply_list;
            }
        }

        /* Set up address linking. */
        [ 'from', 'to', 'cc', 'bcc' ].forEach(function(a) {
            if (this[a]) {
                this.updateHeader(a, this[a], true);
                delete this[a];
            }
        }, this);

        if ((base = ImpCore.baseAvailable())) {
            if (this.strip) {
                base.ImpBase.poll();
            } else if (this.tasks) {
                if (this.tasks['imp:maillog']) {
                    this.tasks['imp:maillog'].forEach(function(l) {
                        if (this.mbox == l.mbox &&
                            this.buid == l.buid) {
                            ImpCore.updateMsgLog(l.log);
                        }
                    }, this);
                    delete this.tasks['imp:maillog'];
                }
                base.ImpBase.tasksHandler({ tasks: this.tasks });
            }
        }

        ImpCore.msgMetadata(this.msg_md);
        delete this.msg_md;

        ImpCore.updateAtcList(this.msg_atc);
        delete this.msg_atc;

        document.getElementById('impLoading').hidden = true;
        document.getElementById('msgData').hidden = false;

        this.resizeWindow();
    }

};

/* Attach event handlers. */
/* Initialize onload handler. */
document.addEventListener('DOMContentLoaded', function() {
    ImpMessage.onDomLoad();
});
document.addEventListener('HordeCore:click', ImpMessage.clickHandler.bind(ImpMessage));
window.addEventListener('resize', ImpMessage.resizeWindow.bind(ImpMessage));

/* ContextSensitive events. */
document.addEventListener('ContextSensitive:click', ImpMessage.contextOnClick.bind(ImpMessage));

/* ImpCore handlers. */
document.addEventListener('ImpCore:updateAddressHeader', ImpMessage.updateAddressHeader.bind(ImpMessage));

/* Define reloadMessage() method for this page. */
ImpCore.reloadMessage = function(params) {
    window.location = HordeCore.addURLParam(document.location.href, params);
};

/* Define reloadPart() method for this page. */
ImpCore.reloadPart = ImpMessage.reloadPart.bind(ImpMessage);
