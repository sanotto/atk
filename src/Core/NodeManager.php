<?php

namespace Sintattica\Atk\Core;

use Sintattica\Atk\Db\Db;
use Sintattica\Atk\Handlers\HandlerManager;
use Sintattica\Atk\Security\SecurityManager;
use Sintattica\Atk\Session\SessionManager;
use Sintattica\Atk\Ui\Page;
use Sintattica\Atk\Utils\Debugger;
use Sintattica\Atk\Utils\Redirect;

class NodeManager
{
    protected $nodes = [];
    protected $nodesClasses = [];
    protected $nodeRepository = [];

    protected $db;
    protected $debugger;
    protected $language;
    protected $handlerManager;
    protected $redirect;
    protected $page;
    protected $sessionManager;
    protected $securityManager;

    public function __construct(Db $db, Debugger $debugger, Redirect $redirect, Page $page, Language $language)
    {
        $this->db = $db;
        $this->debugger = $debugger;
        $this->redirect = $redirect;
        $this->page = $page;
        $this->language = $language;
    }

    public function setHandlerManager(HandlerManager $handlerManager)
    {
        $this->handlerManager = $handlerManager;
    }

    public function setSessionManager(SessionManager $sessionManager)
    {
        $this->sessionManager = $sessionManager;
    }

    public function setSecurityManager(SecurityManager $securityManager)
    {
        $this->securityManager = $securityManager;
    }


    /**
     * Tells ATK that a node exists, and what actions are available to
     * perform on that node.  Note that registerNode() is not involved in
     * deciding which users can do what, only in establishing the full set
     * of actions that can potentially be performed on the node.
     *
     * @param string $nodeUri uri of the node
     * @param string $class class of the node
     * @param array $actions actions that can be performed on the node
     * @param array $tabs tabnames for which security should be handled.
     *              Note that tabs that every user may see need not be
     *              registered.
     * @param string $section
     */
    public function registerNode($nodeUri, $class, $actions = null, $tabs = [], $section = null)
    {
        if (!is_array($tabs)) {
            $section = $tabs;
            $tabs = [];
        }

        $module = Tools::getNodeModule($nodeUri);
        $type = Tools::getNodeType($nodeUri);
        $this->nodesClasses[$nodeUri] = $class;

        if ($actions) {
            // prefix tabs with tab_
            for ($i = 0, $_i = count($tabs); $i < $_i; ++$i) {
                $tabs[$i] = 'tab_'.$tabs[$i];
            }

            if ($module == '') {
                $module = 'main';
            }
            if ($section == null) {
                $section = $module;
            }

            $this->nodes[$section][$module][$type] = array_merge($actions, $tabs);
        }
    }

    public function getNodes()
    {
        return $this->nodes;
    }

    /**
     * Get an instance of a node. If an instance doesn't exist, it is created.  Note that nodes
     * are cached (unless $reset is true); multiple requests for the same node will return exactly
     * the same node object.
     *
     * @param string $nodeUri The node uri
     * @param bool $init Initialize the node?
     * @param string $cache_id The cache id in the node repository
     * @param bool $reset Whether or not to reset the particular node in the repository
     *
     * @return Node the node
     */
    public function getNode($nodeUri, $init = true, $cache_id = 'default', $reset = false)
    {
        if (!isset($this->nodeRepository[$cache_id][$nodeUri]) || !is_object($this->nodeRepository[$cache_id][$nodeUri]) || $reset) {
            $this->debugger->addDebug("Constructing a new node $nodeUri ($cache_id)");
            $this->nodeRepository[$cache_id][$nodeUri] = $this->createNode($nodeUri, $init);
        }

        return $this->nodeRepository[$cache_id][$nodeUri];
    }

    /**
     * Construct a new node.
     *
     * @param string $nodeUri the node uri
     * @param bool $init initialize the node?
     *
     * @return Node new node object
     */
    public function createNode($nodeUri, $init = true)
    {
        $nodeClass = $this->nodesClasses[$nodeUri];

        $this->debugger->addDebug("Creating a new node: $nodeUri class: $nodeClass");

        /** @var Node $node */
        $node = new $nodeClass($nodeUri);
        $node->setDb($this->db);
        $node->setDebugger($this->debugger);
        $node->setLanguage($this->language);
        $node->setHandlerManager($this->handlerManager);
        $node->setRedirect($this->redirect);
        $node->setNodeManager($this);
        $node->setPage($this->page);
        $node->setSessionManager($this->sessionManager);
        $node->setSecurityManager($this->securityManager);

        if ($init && $node != null) {
            $node->init();
        }

        return $node;
    }
}