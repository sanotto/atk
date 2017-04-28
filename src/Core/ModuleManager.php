<?php

namespace Sintattica\Atk\Core;

use Sintattica\Atk\Utils\Debugger;

class ModuleManager
{

    protected $menu;
    protected $debugger;
    protected $nodeManager;

    protected $modules = [];
    protected $moduleRepository = [];

    public function __construct(Debugger $debugger, NodeManager $nodeManager, Menu $menu)
    {
        $this->debugger = $debugger;
        $this->nodeManager = $nodeManager;
        $this->menu = $menu;
    }


    /**
     * Retrieve the Module with the given name.
     *
     * @param string $moduleName The name of the module
     *
     * @return Module An instance of the Module
     */
    public function atkGetModule($moduleName)
    {
        return $this->moduleRepository[$moduleName];
    }

    public function isModule($moduleName)
    {
        return array_key_exists($moduleName, $this->moduleRepository) && is_object($this->moduleRepository[$moduleName]);
    }

    /**
     * Return the physical directory of a module.
     *
     * @param string $moduleName name of the module.
     *
     * @return string The path to the module.
     */
    public function moduleDir($moduleName)
    {
        $modules = $this->modules;
        if (isset($modules[$moduleName])) {
            $class = $modules[$moduleName];

            $reflection = new \ReflectionClass($class);
            $dir = dirname($reflection->getFileName());
            if (substr($dir, -1) != '/') {
                $dir .= '/';
            }

            return $dir;
        }

        return '';
    }

    public function atkGetModules()
    {
        return $this->modules;
    }

    public function registerModule($moduleClass)
    {
        $reflection = new \ReflectionClass($moduleClass);
        $name = $reflection->getStaticPropertyValue('module');
        $this->modules[$name] = $moduleClass;

        if (!self::isModule($name)) {
            $this->debugger->addDebug("Constructing a new module - $name");

            /* @var \Sintattica\Atk\Core\Module $module */
            $module = new $moduleClass($this->nodeManager, $this->menu);
            $this->moduleRepository[$name] = $module;
            $module->register();
        }
    }

    public function bootModules()
    {
        foreach ($this->moduleRepository as $module) {
            $module->boot();
        }
    }
}