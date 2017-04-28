<?php

namespace Sintattica\Atk\Session;

use Sintattica\Atk\Utils\Debugger;

class SessionStoreFactory
{
    /**
     * Instances of the session store, indexed by key.
     *
     * @var array
     */
    protected static $_instances = [];

    /** @var SessionManager $sessionManager */
    protected $sessionManager;

    /** @var Debugger $debugger */
    protected $debugger;

    public function __construct(SessionManager $sessionManager, Debugger $debugger)
    {
        $this->sessionManager = $sessionManager;
        $this->debugger = $debugger;
    }

    /**
     * Get the current instance for the session storage.
     *
     * @param mixed $key Key to use
     * @param bool $reset Wether to reset the singleton
     *
     * @return SessionStore Storage
     */
    public function getSessionStore($key = false, $reset = false)
    {
        if (!$key) {
            $key = $this->getKeyFromSession();
        }
        if (!isset(self::$_instances[$key]) || $reset) {
            self::$_instances[$key] = new SessionStore($key, $reset, $this->debugger, $this->sessionManager);
        }

        return self::$_instances[$key];
    }

    /**
     * Try to get the current key from the session.
     *
     * @return mixed Key to use, false if we don't have a key
     */
    protected function getKeyFromSession()
    {
        return $this->sessionManager->globalStackVar('atkstore_key');
    }

}