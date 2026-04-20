/**
 * Basic IMP javascript functions.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2014-2015 Horde LLC
 * @license    GPL-2 (http://www.horde.org/licenses/gpl)
 */

var IMP_JS = {

    iframeresize_run: {},
    iframe_y: {},
    lazyload_run: {},
    resize_delay: 0.01,

    /**
     * Use DOM manipulation to un-block images.
     */
    unblockImages: function(e)
    {
        var a, callback, doc,
            elt = e.target,
            box = elt.closest('.mimeStatusMessageTable').parentElement,
            iframe = elt.closest('.mimePartBase').querySelector('.mimePartData IFRAME.htmlMsgData');

        e.preventDefault();

        if (elt.getAttribute('noUnblockImageAdd')) {
            box.hidden = true;
            box.remove();
        } else {
            a = document.createElement('A');
            a.textContent = IMP_JS.unblock_image_text;
            a.addEventListener('click', function() {
                HordeCore.doAction('imageUnblockAdd', {
                    muid: elt.getAttribute('muid')
                });

                box.hidden = true;
                box.remove();
            });

            var tbody = elt.closest('TBODY');
            var tr = document.createElement('TR');
            var td = document.createElement('TD');
            td.appendChild(a);
            tr.appendChild(td);
            tbody.innerHTML = '';
            tbody.appendChild(tr);
        }

        callback = this.iframeResize.bind(this, iframe);
        doc = this.iframeDoc(iframe);

        doc.querySelectorAll('[htmlimgblocked]').forEach(function(img) {
            var src = img.getAttribute('htmlimgblocked');
            img.removeAttribute('htmlimgblocked');

            if (img.getAttribute('src')) {
                img.onload = callback;
                img.setAttribute('data-src', src);
            } else {
                if (img.getAttribute('background')) {
                    img.setAttribute('background', src);
                }
                if (img.style.backgroundImage) {
                    if (img.style.setProperty) {
                        img.style.setProperty('background-image', 'url(' + src + ')', '');
                    } else {
                        img.style.backgroundImage = 'url(' + src + ')';
                    }
                }
            }
        });

        doc.querySelectorAll('[htmlimgblocked_srcset]').forEach(function(img) {
            img.setAttribute('srcset', img.getAttribute('htmlimgblocked_srcset'));
            img.removeAttribute('htmlimgblocked_srcset');
        });

        doc.querySelectorAll('[htmlcssblocked]').forEach(function(link) {
            link.setAttribute('href', link.getAttribute('htmlcssblocked'));
            link.removeAttribute('htmlcssblocked');
        });

        doc.querySelectorAll('STYLE[type="text/x-imp-cssblocked"]').forEach(function(style) {
            style.setAttribute('type', 'text/css');
        });

        this.iframeResize(iframe);
    },

    iframeInject: function(id, data)
    {
        if (typeof id === 'string') {
            id = document.getElementById(id);
        }
        if (!id) {
            return;
        }

        var d = this.iframeDoc(id), ev;

        id.onload = function() {
            this.iframeResize(id);
            this.iframeOverflowY(id, true);
        }.bind(this);

        d.open();
        d.write(data);
        d.close();

        ev = function(name, e) {
            $(id).fire('IMP_JS:' + name, e);
        };

        d.addEventListener('click', ev.bind(null, 'htmliframe_click'), false);
        d.addEventListener('keydown', ev.bind(null, 'htmliframe_keydown'), false);

        this.iframeOverflowY(id, false);
        id.hidden = false;
        var prev = id.previousElementSibling;
        if (prev) { prev.remove(); }
        this.iframeResize(id);
    },

    // iframe = (Element)
    iframeResize: function(iframe)
    {
        var id = iframe.id || (iframe.id = 'horde_' + Date.now());

        if (!this.iframeresize_run[id]) {
            this.iframeresize_run[id] = true;
            setTimeout(this.iframeResizeRun.bind(this, iframe), this.resize_delay * 1000);
        }
    },

    iframeResizeRun: function(id)
    {
        var body, h1, h2, html, iHeight,
            doc = this.iframeDoc(id);

        if (!doc) {
            var eid = id.id || (id.id = 'horde_' + Date.now());
            this.iframeresize_run[eid] = false;
            return;
        }

        body = doc.body;
        html = body.parentNode;
        iHeight = function() {
            return Math.max(
                body.offsetHeight,
                html.offsetHeight,
                html.scrollHeight
            );
        };

        body.style.height = '';

        h1 = iHeight();
        id.style.height = h1 + 'px';

        h2 = iHeight();
        if (h2 > h1) {
            id.style.height = h2 + 'px';
        }

        this.iframeImgLazyLoad(id);

        var eid2 = id.id || (id.id = 'horde_' + Date.now());
        this.iframeresize_run[eid2] = false;
    },

    iframeImgLazyLoad: function(iframe)
    {
        var id = iframe.id || (iframe.id = 'horde_' + Date.now());

        if (!this.lazyload_run[id]) {
            this.lazyload_run[id] = true;
            setTimeout(this.iframeImgLazyLoadRun.bind(this, iframe),
                this.resize_delay * 1000);
        }
    },

    iframeImgLazyLoadRun: function(iframe)
    {
        var error, imgs, mb_height, range_top, range_bottom, resize,
            doc = this.iframeDoc(iframe),
            mb = this.messageBody();

        if (!doc) {
            var eid = iframe.id || (iframe.id = 'horde_' + Date.now());
            this.lazyload_run[eid] = false;
            return;
        }

        /* Load messages within 1 scrolled page of range boundaries. */
        mb_height = mb.offsetHeight;
        range_top = mb.scrollTop - mb_height;
        range_bottom = mb.scrollTop + (2 * mb_height);

        imgs = Array.from(doc.querySelectorAll('IMG[data-src]')).filter(function(img) {
            return !img.hidden && img.offsetWidth > 0;
        });

        if (imgs.length) {
            iframe.style.overflowY = 'hidden';

            error = this.iframeOverflowY.bind(this, iframe);
            resize = function() {
                this.iframeResize(iframe);
                this.iframeOverflowY(iframe, true);
            }.bind(this);

            imgs.forEach(function(img) {
                var rect = img.getBoundingClientRect();
                var co_top = rect.top + (doc.defaultView ? doc.defaultView.pageYOffset : 0);
                if (co_top > range_top && co_top < range_bottom) {
                    this.iframeOverflowY(iframe, false);
                    img.onerror = error;
                    img.onload = resize;
                    img.setAttribute('src', img.getAttribute('data-src'));
                    img.removeAttribute('data-src');
                }
            }, this);
        }

        var eid2 = iframe.id || (iframe.id = 'horde_' + Date.now());
        this.lazyload_run[eid2] = false;
    },

    iframeDoc: function(i)
    {
        return i.contentDocument ||
               (i.contentWindow && i.contentWindow.document);
    },

    iframeOverflowY: function(id, show)
    {
        var key = id.id || (id.id = 'horde_' + Date.now());

        if (show) {
            if (this.iframe_y[key] && !(--this.iframe_y[key])) {
                id.style.overflowY = '';
                delete this.iframe_y[key];
            }
        } else {
            if (this.iframe_y[key]) {
                ++this.iframe_y[key];
            } else {
                id.style.overflowY = 'hidden';
                this.iframe_y[key] = 1;
            }
        }
    },

    messageBody: function()
    {
        return document.getElementById('previewPane') || document.getElementById('messageBody');
    },

    printWindow: function(win)
    {
        win.print();
        // Bug #12833: Fixes closing print window in Chrome.
        setTimeout(function() { win.close(); }, 0);
    },

    resizePopup: function(win)
    {
        var b = win.document.body,
            h = 0,
            w = 0;

        w = b.scrollWidth - b.clientWidth;
        if (w) {
            w = Math.min(w, screen.availWidth - win.outerWidth - 100);
        }
        h = b.scrollHeight - b.clientHeight;
        if (h) {
            h = Math.min(h, screen.availHeight - win.outerHeight - 100);
        }

        if (w || h) {
            win.resizeBy(w, h);
        }
    },

    fnv_1a: function(str)
    {
        var i, l,
            hash = 0x811c9dc5;

        for (i = 0, l = str.length; i < l; ++i) {
            hash ^= str.charCodeAt(i);
            hash += (hash << 1) + (hash << 4) + (hash << 7) + (hash << 8) + (hash << 24);
        }

        return hash >>> 0;
    },

    onDomLoad: function()
    {
        var mb = this.messageBody();

        if (mb) {
            mb.addEventListener('scroll', function() {
                Array.from(document.getElementById('messageBody').querySelectorAll('IFRAME.htmlMsgData')).forEach(this.iframeImgLazyLoad.bind(this));
            }.bind(this));
        }
    }

};

document.addEventListener('DOMContentLoaded', IMP_JS.onDomLoad.bind(IMP_JS));
