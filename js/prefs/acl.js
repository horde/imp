/**
 * Provides the javascript for the ACL preferences management view.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2014-2015 Horde LLC
 * @license    GPL-2 (http://www.horde.org/licenses/gpl)
 */

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('aclmbox').addEventListener('change', function() {
        document.getElementById('prefs').querySelectorAll('input[type="checkbox"]').forEach(function(i) {
            i.disabled = true;
        });
        document.getElementById('change_acl_mbox').value = 1;
        document.getElementById('prefs').submit();
    });

    /* Disable selection of container elements. */
    document.getElementById('aclmbox').querySelectorAll('option[value=""]').forEach(function(o) {
        o.disabled = true;
    });

    document.querySelector('table.prefsAclTable').addEventListener('change', function(e) {
        var elt = e.target;
        if (!elt.matches('select.aclTemplate')) {
            return;
        }

        var acl = elt.value;
        elt.closest('tr').querySelectorAll('input[type=checkbox]').forEach(function(i) {
            i.checked = acl.includes(i.value);
        });

        elt.selectedIndex = 0;
    });
});
