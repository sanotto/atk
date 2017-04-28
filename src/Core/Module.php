<?php

namespace Sintattica\Atk\Core;

/**
 * The Module abstract base class.
 *
 * All modules in an ATK application should derive from this class
 */
abstract class Module
{
    public static $module;

    /** @var NodeManager $nodeManager */
    private $nodeManager;

    /** @var Menu $menu */
    private $menu;

    public function __construct(NodeManager $nodeManager, Menu $menu)
    {
        $this->nodeManager = $nodeManager;
        $this->menu = $menu;
    }

    protected function getMenu()
    {
        return $this->menu;
    }

    protected function getNodeManager()
    {
        return $this->nodeManager;
    }

    abstract public function register();

    public function boot()
    {
        //noop
    }

    public function registerNode($nodeName, $nodeClass, $actions = null)
    {
        $this->nodeManager->registerNode(static::$module.'.'.$nodeName, $nodeClass, $actions);
    }

    public function addNodeToMenu($menuName, $nodeName, $action, $parent = 'main', $enable = null, $order = 0, $navbar = 'left')
    {
        if ($enable === null) {
            $enable = [static::$module.'.'.$nodeName, $action];
        }
        $this->menu->addMenuItem($menuName, Tools::dispatch_url(static::$module.'.'.$nodeName, $action), $parent, $enable, $order, static::$module, '', $navbar);
    }

    public function addMenuItem($name = '', $url = '', $parent = 'main', $enable = 1)
    {
        $this->menu->addMenuItem($name, $url, $parent, $enable, 0, static::$module);
    }
}
