<?php

/**
 * Copyright 2014-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category  Horde
 * @copyright 2014-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */

/**
 * A serializable wrapper for the IMAP cache backend. Ensures that IMAP object
 * uses global Horde object for caching.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2014-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */
class IMP_Imap_Cache_Wrapper implements Serializable
{
    /**
     * Cache object.
     *
     * @var Horde_Imap_Client_Cache_Backend
     */
    public $backend;

    /**
     * Cache parameters:
     *   - driver (string)
     *   - lifetime (integer)
     *
     * @var array
     */
    protected $_params = [];

    /**
     * Cache lifetime.
     */

    /**
     * Constructor.
     *
     * @param string $driver     Cache driver to use.
     * @param integer $lifetime  Cache lifetime.
     */
    public function __construct($driver, $lifetime = null)
    {
        $params = ['driver' => $driver];
        if (!is_null($lifetime)) {
            $params['lifetime'] = intval($lifetime);
        }

        $this->_initOb($params);
    }

    /**
     */
    protected function _initOb($params)
    {
        global $injector;

        $this->_params = $params;

        switch ($this->_params['driver']) {
            case 'cache':
            case 'hashtable':
                /* The 'hashtable' driver is retired. The legacy
                 * Horde_Imap_Client_Cache_Backend_Hashtable bypassed
                 * Horde_Cache to write one entry per UID, optimized for
                 * Memcache 1.0's poor multi-get performance. Modern Redis
                 * (with MGET/pipelining) handles the sliced strategy of
                 * Backend_Cache equally well, and going through Horde_Cache
                 * gives one code path with consistent TTL/age handling. Old
                 * configurations that still set driver='hashtable' fall
                 * through here so users do not need to update their config
                 * during the migration period. */
                $ob = new Horde_Imap_Client_Cache_Backend_Cache(array_filter([
                    'cacheob' => $injector->getInstance('Horde_Cache'),
                    'lifetime' => ($this->_params['lifetime'] ?? null),
                ]));
                break;

            case 'none':
                $ob = new Horde_Imap_Client_Cache_Backend_Null();
                break;

            case 'nosql':
                $ob = new Horde_Imap_Client_Cache_Backend_Mongo([
                    'mongo_db' => $injector->getInstance('Horde_Nosql_Adapter'),
                ]);
                break;

            case 'sql':
                $ob = new Horde_Imap_Client_Cache_Backend_Db([
                    'db' => $injector->getInstance('Horde_Db_Adapter'),
                ]);
                break;

            default:
                $this->_params['driver'] = 'none';
                Horde::log(
                    'IMAP caching has been disabled for this session due to an error',
                    'WARN'
                );
                $ob = new Horde_Imap_Client_Cache_Backend_Null();
                break;
        }

        $this->backend = $ob;
    }

    /**
     * Redirects calls to the logger object.
     */
    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->backend, $name], $arguments);
    }

    /* Serializable methods. */

    /**
     */
    public function serialize()
    {
        return array_shift($this->__serialize());
    }
    public function __serialize(): array
    {
        return
        [
            json_encode($this->_params),
        ];
    }

    /**
     */
    public function unserialize($data)
    {
        $this->__unserialize([$data]);
    }
    public function __unserialize(array $data): void
    {
        $this->_initOb(json_decode($data[0], true));
    }
}
