/**
 * Utility methods used by viewport.js.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2012-2015 Horde LLC
 * @license    GPL-2 (http://www.horde.org/licenses/gpl)
 */

Object.assign(Array.prototype, {

    // Need our own diff() function because prototypejs's without() function
    // does not handle array input.
    diff: function(values)
    {
        return this.filter(function(value) {
            return values.indexOf(value) === -1;
        });
    },

    numericSort: function()
    {
        return this.map(Number).sort(function(a, b) {
            return (a > b) ? 1 : ((a < b) ? -1 : 0);
        });
    },

    // opts = (object) Additional options:
    //   - raw: (boolean) Force into parsing in raw mode (no sorting).
    toViewportUidString: function(opts)
    {
        opts = opts || {};

        var u = (opts.raw ? this.slice() : this.numericSort()),
            first = u.shift(),
            last = first,
            out = [];

        u.forEach(function(k) {
            if (!opts.raw && (last + 1 == k)) {
                last = k;
            } else {
                out.push(first + (last == first ? '' : (':' + last)));
                first = last = k;
            }
        });
        out.push(first + (last == first ? '' : (':' + last)));

        return out.join(',');
    }

});

Object.assign(String.prototype, {

    parseViewportUidString: function()
    {
        var out = [];

        this.trim().split(',').forEach(function(e) {
            var r = e.split(':');
            if (r.length == 1) {
                out.push(Number(e));
            } else {
                var start = Number(r[0]), end = Number(r[1]);
                for (var i = start; i <= end; i++) {
                    out.push(i);
                }
            }
        });

        return out;
    }

});
