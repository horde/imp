/**
 * Login page.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2014-2015 Horde LLC
 * @license    GPL-2 (http://www.horde.org/licenses/gpl)
 */

(function() {
    var origSubmit = HordeLogin.submit;
    HordeLogin.submit = function() {
        var k = document.getElementById('imp_server_key');
        if (k && k.value.startsWith('_')) {
            alert(HordeLogin.server_key_error);
            k.focus();
        } else {
            origSubmit.call(HordeLogin);
        }
    };
})();
