<?php

/**
 * Copyright 1999-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category  Horde
 * @copyright 1999-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */

/**
 * This class provides authentication for IMP.
 *
 * @author    Chuck Hagenbuch <chuck@horde.org>
 * @author    Jon Parise <jon@horde.org>
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 1999-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */
class IMP_Auth
{
    /**
     * Authenticate to the mail server.
     *
     * @param array $credentials  An array of login credentials. If empty,
     *                            attempts to login to the cached session.
     *   - password: (string) The user password.
     *   - server: (string) The server key to use (from backends.php).
     *   - userId: (string) The username.
     *   - xoauth2_token: (Horde_Imap_Client_Password_Xoauth2) XOAUTH2 token
     *                    (alternative to password, used with OIDC auth).
     *
     * @throws Horde_Auth_Exception
     */
    public static function authenticate($credentials = [])
    {
        global $injector, $registry;

        // Do 'horde' authentication.
        $imp_app = $registry->getApiInstance('imp', 'application');
        if (!empty($imp_app->initParams['authentication'])
            && ($imp_app->initParams['authentication'] == 'horde')) {
            if ($registry->getAuth()) {
                return;
            }
            throw new Horde_Auth_Exception('', Horde_Auth::REASON_FAILED);
        }

        if (!isset($credentials['server'])) {
            $credentials['server'] = self::getAutoLoginServer();
        }

        $imp_imap = $injector->getInstance('IMP_Factory_Imap')->create();

        // Check for valid IMAP Client object.
        if (!$imp_imap->init) {
            if (!isset($credentials['userId'])
                || (!isset($credentials['password'])
                    && !isset($credentials['xoauth2_token']))) {
                throw new Horde_Auth_Exception('', Horde_Auth::REASON_BADLOGIN);
            }

            // Run imap_preauthenticate hook.
            try {
                $credentials = $injector->getInstance('Horde_Core_Hooks')->callHook(
                    'imap_preauthenticate',
                    'imp',
                    [$credentials]
                );
            } catch (Horde_Exception_HookNotSet $e) {
            }

            try {
                $imp_imap->createBaseImapObject(
                    $credentials['userId'],
                    $credentials['xoauth2_token'] ?? $credentials['password'],
                    $credentials['server']
                );
            } catch (IMP_Imap_Exception $e) {
                self::_log(false, $imp_imap);
                throw $e->authException();
            }
        }

        try {
            $imp_imap->login();
        } catch (IMP_Imap_Exception $e) {
            self::_log(false, $imp_imap);
            throw $e->authException();
        }
    }

    /**
     * Ensure a live IMAP connection exists.
     *
     * Unlike authenticate(), this always opens the mail server connection when
     * needed. Stateless entry points (ActiveSync, RPC) authenticate to Horde
     * first but still require an explicit IMAP login for hordeauth backends.
     *
     * @param array $credentials  Optional fallback credentials:
     *   - userId: (string) Username.
     *   - password: (string) Password.
     *   - server: (string) Server key from backends.php.
     *
     * @throws Horde_Auth_Exception
     *
     * @author Torben Dannhauer <torben@dannhauer.de>
     */
    public static function ensureImapConnection(array $credentials = [])
    {
        global $injector, $registry;

        $imp_imap = $injector->getInstance('IMP_Factory_Imap')->create();
        if ($imp_imap->init) {
            try {
                $imp_imap->login();
            } catch (IMP_Imap_Exception $e) {
                self::_log(false, $imp_imap);
                throw $e->authException();
            }
            return;
        }

        if (!isset($credentials['server'])) {
            $credentials['server'] = self::getAutoLoginServer();
        }
        if (empty($credentials['server'])) {
            throw new Horde_Auth_Exception(
                _('No IMAP backend is available for auto-login.'),
                Horde_Auth::REASON_MESSAGE
            );
        }

        $servers = IMP_Imap::loadServerConfig();
        if ($servers === false) {
            throw new Horde_Auth_Exception(
                _('Could not load IMAP backend configuration.'),
                Horde_Auth::REASON_MESSAGE
            );
        }
        $serverConfig = $servers[$credentials['server']] ?? null;
        if ((empty($credentials['userId']) || !isset($credentials['password']))
            && $registry->getAuth()
            && $serverConfig
            && !empty($serverConfig->hordeauth)) {
            $hordeauth = $serverConfig->hordeauth;
            $credentials['userId'] = $registry->getAuth(
                strcasecmp($hordeauth, 'full') === 0 ? null : 'bare'
            );
            $stored = $registry->getAuthCredential('password');
            if ($stored !== false) {
                $credentials['password'] = $stored;
            }
        }

        if (empty($credentials['userId'])
            || !isset($credentials['password'])
            || (!is_string($credentials['password']) && !($credentials['password'] instanceof Horde_Imap_Client_Password_Xoauth2))
            || (is_string($credentials['password']) && $credentials['password'] === '')) {
            throw new Horde_Auth_Exception('', Horde_Auth::REASON_BADLOGIN);
        }

        try {
            $credentials = $injector->getInstance('Horde_Core_Hooks')->callHook(
                'imap_preauthenticate',
                'imp',
                [$credentials]
            );
        } catch (Horde_Exception_HookNotSet $e) {
        }

        try {
            $imp_imap->createBaseImapObject(
                $credentials['userId'],
                $credentials['password'],
                $credentials['server']
            );
            $imp_imap->login();
        } catch (IMP_Imap_Exception $e) {
            self::_log(false, $imp_imap);
            throw $e->authException();
        }
    }

    /**
     * Perform transparent authentication.
     *
     * @param Horde_Auth_Application $auth_ob  The authentication object.
     *
     * @return boolean  True on successful transparent authentication.
     */
    public static function transparent($auth_ob)
    {
        $credentials = $auth_ob->getCredential('credentials');

        if (empty($credentials['transparent'])) {
            /* Attempt hordeauth authentication. */
            $credentials = self::_canAutoLogin();
            if ($credentials === false) {
                return false;
            }
        } else {
            /* It is possible that preauthenticate() set the credentials.
             * If so, use that information instead of hordeauth. */
            $credentials['userId'] = $auth_ob->getCredential('userId');
        }

        if (empty($credentials['xoauth2_token'])
            && empty($credentials['password'])) {
            return false;
        }

        try {
            self::authenticate($credentials);
        } catch (Horde_Auth_Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Log login related message.
     *
     * @param boolean $status    True on success, false on failure.
     * @param IMP_Imap $imap_ob  The IMP_Imap object to use.
     */
    protected static function _log($status, $imap_ob)
    {
        $msg = $status
            ? 'Login success'
            : 'FAILED LOGIN';
        $user = $imap_ob->getParam('username');

        if (($auth_id = $GLOBALS['registry']->getAuth())
            && ($user != $auth_id)) {
            $user .= ' (Horde user ' . $auth_id . ')';
        }

        Horde::log(
            sprintf(
                $msg . ' for %s (%s)%s to {%s}',
                $user,
                $_SERVER['REMOTE_ADDR'],
                empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? '' : ' (forwarded for [' . $_SERVER['HTTP_X_FORWARDED_FOR'] . '])',
                $imap_ob->url
            ),
            $status ? 'NOTICE' : 'INFO'
        );
    }

    /**
     * Returns the autologin server key.
     *
     * @return string  The server key, or null if none available.
     */
    public static function getAutoLoginServer()
    {
        global $session;

        if (($servers = IMP_Imap::loadServerConfig()) === false) {
            return null;
        }

        // Honor a server explicitly selected on the Horde login screen, even
        // when Horde itself authenticated through a driver unrelated to IMP
        // (LDAP, SQL, ...). horde/base's login.php stores raw posted values
        // per app in the session; only IMP interprets the 'imp_server_key'
        // field.
        $login_params = $session->exists('horde', 'login_app_params')
            ? $session->get('horde', 'login_app_params')
            : [];
        $selected = $login_params['imp']['imp_server_key'] ?? null;
        if (!empty($selected) && isset($servers[$selected])) {
            return $selected;
        }

        $server_key = null;

        foreach ($servers as $key => $val) {
            if (is_null($server_key) && (substr($key, 0, 1) != '_')) {
                $server_key = $key;
            }

            /* Determines if the given mail server is the "preferred" mail
             * server for this web server. This decision is based on the
             * global 'SERVER_NAME' and 'HTTP_HOST' server variables and the
             * contents of the 'preferred' field in the backend's config. */
            $serverName = $_SERVER['SERVER_NAME'] ?? null;
            $httpHost = $_SERVER['HTTP_HOST'] ?? null;
            if (($preferred = $val->preferred)
                && (($serverName !== null && in_array($serverName, $preferred))
                 || ($httpHost !== null && in_array($httpHost, $preferred)))) {
                return $key;
            }
        }

        return $server_key;
    }

    /**
     * Returns whether we can log in without a login screen for $server_key.
     *
     * @param string $server_key  The server to check. Defaults to the
     *                            autologin server.
     * @param boolean $force      If true, check $server_key even if there is
     *                            more than one server available.
     *
     * @return array  The credentials needed to login ('userId', 'password',
     *                 'server') or false if autologin not available.
     */
    protected static function _canAutoLogin($server_key = null, $force = false)
    {
        global $injector, $registry;
        if (($servers = $injector->getInstance('IMP_Factory_Imap')->create()->loadServerConfig()) === false) {
            return false;
        }
        if (is_null($server_key) || !$force) {
            $auto_server = self::getAutoLoginServer();
            if (is_null($server_key)) {
                $server_key = $auto_server;
            }
        }
        if (empty($auto_server) && !$force) {
            return false;
        }
        if (!$registry->getAuth()) {
            return false;
        }

        // If this backend declares an associated OAuth provider via
        // 'oauth' => 'provider-id' in backends.php, try XOAUTH2.
        if (!empty($servers[$server_key]->oauth)) {
            $username = $registry->getAuth();
            $tokenService   = $injector->getInstance(\Horde\Core\Service\OAuthTokenService::class);
            $providerConfig = $injector->getInstance(\Horde\Core\Service\OAuthProviderConfigRepository::class);

            try {
                $row = $providerConfig->get($servers[$server_key]->oauth);
            } catch (\Throwable $e) {
                Horde::log(
                    sprintf('IMP: backend "%s" declares oauth provider "%s" which does not exist: %s',
                        $server_key, $servers[$server_key]->oauth, $e->getMessage()),
                    'ERR'
                );
                return false;
            }
            if ($tokenService->hasTokens($username, $row['provider_id'])) {
                $accessToken = \Horde\Core\Service\OidcHookHelper::getValidAccessToken(
                    $username, $row, $tokenService, $injector
                );

                if ($accessToken !== null) {
                    $xoauth2User = \Horde\Core\Service\OidcHookHelper::xoauth2Username(
                        $username, $row
                    );
                    return [
                        'userId' => $xoauth2User,
                        'password' => new Horde_Imap_Client_Password_Xoauth2(
                            $xoauth2User, $accessToken
                        ),
                        'server' => $server_key,
                    ];
                }
            }
            return false;
        }

        if (!empty($servers[$server_key]->hordeauth)) {
            return [
                'userId' => $registry->getAuth((strcasecmp($servers[$server_key]->hordeauth, 'full') === 0) ? null : 'bare'),
                'password' => $registry->getAuthCredential('password'),
                'server' => $server_key,
            ];
        }

        return false;
    }

    /**
     * Validate that any required OAuth tokens are still present for the
     * currently active backend.
     *
     * Called via IMP_Application::authValidate() on every request
     * (checkExistingAuth()), so that a backchannel logout / token
     * revocation is detected promptly rather than only at the next full
     * re-authentication.
     *
     * @return boolean  True if valid (or if this backend doesn't require
     *                  OAuth), false if OAuth is required but no tokens
     *                  remain.
     */
    public static function validateOauth()
    {
        global $injector, $registry;

        $imp_imap = $injector->getInstance('IMP_Factory_Imap')->create();
        if (!$imp_imap->init) {
            // No active connection to validate against.
            return true;
        }

        $server_key = $imp_imap->server_key;
        if (($servers = $imp_imap->loadServerConfig()) === false
            || empty($servers[$server_key]->oauth)) {
            // Not an OAuth-backed backend — nothing to validate here.
            return true;
        }

        $username = $registry->getAuth();
        if (!$username) {
            return true;
        }

        $tokenService   = $injector->getInstance(\Horde\Core\Service\OAuthTokenService::class);
        $providerConfig = $injector->getInstance(\Horde\Core\Service\OAuthProviderConfigRepository::class);

        try {
            $row = $providerConfig->get($servers[$server_key]->oauth);
        } catch (\Throwable $e) {
            Horde::log(
                sprintf('IMP: validateOauth() could not resolve oauth provider "%s" for backend "%s": %s',
                    $servers[$server_key]->oauth, $server_key, $e->getMessage()),
                'ERR'
            );
            return true; // fail-open: infra issue shouldn't log the user out
        }

        return $tokenService->hasTokens($username, $row['provider_id']);
    }

    /**
     * Perform post-login tasks. Session creation requires the full IMP
     * environment, which is not available until this callback.
     *
     * @throws Horde_Exception
     */
    public static function authenticateCallback()
    {
        global $injector;

        $imp_imap = $injector->getInstance('IMP_Factory_Imap')->create();

        /* Perform post-login tasks for IMAP object. */
        $imp_imap->doPostLoginTasks();

        self::_log(true, $imp_imap);
    }

}
