/**
 * DragHandler library.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2013-2015 Horde LLC
 * @license    GPL-2 (http://www.horde.org/licenses/gpl)
 */

var DragHandler = {

    // dropelt,
    // droptarget,
    // hoverclass,
    // leave,

    to: -1,

    isFileDrag: function(e)
    {
        return (e.dataTransfer &&
                e.dataTransfer.types &&
                Array.from(e.dataTransfer.types).indexOf('Files') !== -1 &&
                ((e.type != 'drop') || e.dataTransfer.files.length));
    },

    handleObserve: function(e)
    {
        if (this.dropelt &&
            (e.dataTransfer ||
             (e.detail && e.detail.dataTransfer) ||
             !this.dropelt.hidden)) {
            switch (e.type) {
            case 'dragleave':
                this.handleLeave();
                break;

            case 'dragover':
                this.handleOver(e);
                break;

            case 'drop':
                this.handleDrop(e);
                break;
            }
        }
    },

    handleDrop: function(e)
    {
        this.leave = true;
        this.hide(); // eslint-disable-line horde/no-prototype-methods -- DragHandler.hide()

        if (this.isFileDrag(e)) {
            if (this.dropelt.classList.contains(this.hoverclass)) {
                this.dropelt.dispatchEvent(new CustomEvent('DragHandler:drop', { bubbles: true, detail: e.dataTransfer.files }));
            }
            e.preventDefault();
        } else if (!e.target.closest('TEXTAREA') && !e.target.closest('INPUT')) {
            e.preventDefault();
        }
    },

    hide: function()
    {
        if (this.leave) {
            this.dropelt.hidden = true;
            this.droptarget.hidden = false;
            this.leave = false;
        }
    },

    handleLeave: function()
    {
        clearTimeout(this.to);
        this.to = setTimeout(this.hide.bind(this), 250);
        this.leave = true;
    },

    handleOver: function(e)
    {
        var file = this.isFileDrag(e);

        if (file && this.dropelt.hidden) {
            // Position dropelt over droptarget
            var rect = this.droptarget.getBoundingClientRect();
            this.dropelt.style.position = 'absolute';
            this.dropelt.style.left = rect.left + 'px';
            this.dropelt.style.top = rect.top + 'px';
            this.dropelt.style.width = rect.width + 'px';
            this.dropelt.style.height = rect.height + 'px';
            this.dropelt.hidden = false;
            this.droptarget.hidden = true;
        }

        this.leave = false;

        if (file && (e.target == this.dropelt)) {
            this.dropelt.classList.add(this.hoverclass);
            e.preventDefault();
        } else {
            this.dropelt.classList.remove(this.hoverclass);
        }
    }

};

document.addEventListener('dragleave', DragHandler.handleObserve.bind(DragHandler));
document.addEventListener('dragover', DragHandler.handleObserve.bind(DragHandler));
document.addEventListener('drop', DragHandler.handleObserve.bind(DragHandler));
