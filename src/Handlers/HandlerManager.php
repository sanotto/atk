<?php

namespace Sintattica\Atk\Handlers;

use Sintattica\Atk\Core\Node;
use Sintattica\Atk\Session\SessionManager;
use Sintattica\Atk\Utils\Debugger;

class HandlerManager
{
    protected $debugger;
    protected $g_nodeHandlers = [];

    protected $sessionManager;

    public function __construct(Debugger $debugger, SessionManager $sessionManager)
    {
        $this->debugger = $debugger;
        $this->sessionManager = $sessionManager;
    }

    public function setSessionManager(SessionManager $sessionManager)
    {
        $this->sessionManager = $sessionManager;
    }

    /**
     * Returns a registered node action handler.
     *
     * @param string $nodeUri the uri of the node
     * @param string $action the node action
     *
     * @return ActionHandler full class or object instance (subclass of ActionHandler) or NULL if no handler exists for the specified action
     */
    public function getNodeHandler($nodeUri, $action)
    {
        if (isset($this->g_nodeHandlers[$nodeUri][$action])) {
            $handler = $this->g_nodeHandlers[$nodeUri][$action];
            if (!is_object($handler)) {
                $handler = new $handler();
                $this->g_nodeHandlers[$nodeUri][$action] = $handler;
            }
        } elseif (isset($this->g_nodeHandlers['*'][$action])) {
            $handler = $this->g_nodeHandlers['*'][$action];
            if (!is_object($handler)) {
                $handler = new $handler();
                $this->g_nodeHandlers['*'][$action] = $handler;
            }
        } else {
            return null;
        }

        return $handler;
    }

    /**
     * Registers a new node action handler.
     *
     * @param string $nodeUri the uri of the node (* matches all)
     * @param string $action the node action
     * @param string /atkActionHandler $handler handler functionname or object (is_subclass_of atkActionHandler)
     *
     * @return bool true if there is no known handler
     */
    public function registerNodeHandler($nodeUri, $action, $handler)
    {
        if (isset($this->g_nodeHandlers[$nodeUri][$action])) {
            return false;
        } else {
            $this->g_nodeHandlers[$nodeUri][$action] = $handler;
        }

        return true;
    }

    /**
     * Get the ActionHandler object for a certain action.
     *
     * @param string $action The action for which the handler is retrieved.
     * @param Node $node
     *
     * @return ActionHandler The action handler.
     */
    public function getHandler($action, Node $node)
    {
        $this->debugger->addDebug('self::getHandler(); action: '.$action);

        //check if a handler exists registered including the module name
        $handler = $this->getNodeHandler($node->atkNodeUri(), $action);

        // The node handler might return a class, then we need to instantiate the handler
        if (is_string($handler) && class_exists($handler)) {
            $handler = new $handler($this->debugger);
        }

        if ($handler != null && is_subclass_of($handler, 'ActionHandler')) {
            $this->debugger->addDebug('self::getHandler: Using existing ActionHandler '.get_class($handler)." class for '".$action."'");
            $handler->setNode($node);
            $handler->setAction($action);
        } else {
            $class = __NAMESPACE__.'\\'.ucfirst($action).'Handler';
            if (class_exists($class)) {
                $handler = new $class($this->debugger);
            } else {
                $handler = new ActionHandler($this->debugger);
            }

            $handler->setNode($node);
            $handler->setPostvars($node->m_postvars);
            $handler->setAction($action);
            $handler->setSessionManager($this->sessionManager);

            //If we use a default handler we need to register it to this node because we might call it a second time.
            $this->debugger->addDebug('self::getHandler: Register default ActionHandler for '.$node->m_type." action: '".$action."'");
            $this->registerNodeHandler($node->m_type, $action, $handler);
        }

        return $handler;
    }
}
