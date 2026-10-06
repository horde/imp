/**
 * Managing remote accounts.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2013-2015 Horde LLC
 * @license    GPL-2 (http://www.horde.org/licenses/gpl)
 */

var ImpRemotePrefs = {

    // Variables set by PHP code: confirm_delete, empty_email, empty_password,
    //     next, wait

    _sendData: function(a, d, c)
    {
        document.getElementById('remote_action').value = a;
        document.getElementById('remote_data').value = d;
        if (c) {
            document.querySelector('#prefs input[type="hidden"][name="actionID"]').value = '';
        }
        document.getElementById('prefs').submit();
    },

    _autoconfigCallback: function(r)
    {
        if (r.success) {
            document.getElementById('remote_type').value = r.mconfig.imap ? 'imap' : 'pop3';
            document.getElementById('remote_server').value = r.mconfig.host;
            document.getElementById('remote_user').value = r.mconfig.username;
            document.getElementById('remote_port').value = r.mconfig.port;
            document.getElementById('remote_secure_autoconfig').value = r.mconfig.tls;

            if (!document.getElementById('remote_label').value.trim()) {
                document.getElementById('remote_label').value = r.mconfig.label;
            }

            document.getElementById('remote_password').remove();
            document.getElementById('autoconfig_button').hidden = true;
            document.getElementById('add_button').hidden = false;
        } else {
            document.getElementById('autoconfig_button').value = this.next;
        }

        Array.from(document.getElementById('prefs').elements).forEach(function(el) {
            el.disabled = false;
        });
    },

    clickHandler: function(e)
    {
        if (e.button === 2) {
            return;
        }

        var elt = e.target;

        while (elt instanceof Element) {
            if (elt.classList.contains('remotedelete')) {
                if (window.confirm(this.confirm_delete)) {
                    this._sendData('delete', elt.dataset.id);
                }
                e.preventDefault();
                return;
            }

            switch (elt.id) {
            case 'add_button':
                this._sendData('add', '');
                break;

            case 'autoconfig_button':
                if (!document.getElementById('remote_email').value.trim()) {
                    window.alert(this.empty_email);
                } else if (!document.getElementById('remote_password').value) {
                    window.alert(this.empty_password);
                } else {
                    HordeCore.doAction(
                        'autoconfigAccount',
                        {
                            email: document.getElementById('remote_email').value,
                            password: Base64.encode(document.getElementById('remote_password').value),
                            password_base64: true,
                            secure: ~~(document.getElementById('remote_secure').value == 'yes')
                        },
                        {
                            callback: this._autoconfigCallback.bind(this)
                        }
                    );
                    elt.value = this.wait;
                    Array.from(document.getElementById('prefs').elements).forEach(function(el) {
                        el.disabled = true;
                    });
                }
                e.preventDefault();
                break;

            case 'advanced_show':
                document.querySelectorAll('#prefs .imp-remote-autoconfig').forEach(function(el) {
                    el.hidden = true;
                });
                document.getElementById('remote_secure_autoconfig').remove();
                document.querySelectorAll('#prefs .imp-remote-advanced').forEach(function(el) {
                    el.hidden = false;
                });
                break;

            case 'cancel_button':
                this._sendData('', '', true);
                break;

            case 'new_button':
                this._sendData('new', '', true);
                break;
            }

            elt = elt.parentElement;
        }
    }

};

document.addEventListener('click', ImpRemotePrefs.clickHandler.bind(ImpRemotePrefs));
