<?php

namespace Sintattica\Atk\Security\Auth;

use Sintattica\Atk\Security\SecurityManager;

/**
 * Dummy driver for non-authentication. When using 'none' as authentication
 * method, any loginname and any password will be accepted.
 */
class NoneAuth extends AuthInterface
{

    protected $securityManager;

    public function __construct(SecurityManager $securityManager)
    {
        $this->securityManager = $securityManager;
    }


    public function validateUser($user, $passwd)
    {
        return SecurityManager::AUTH_SUCCESS;
    }

    public function isValidUser($user)
    {
        return true;
    }

    public function getUser($user)
    {
        return $this->securityManager->getSystemUser('administrator');
    }
}
