<?php

namespace Sintattica\Atk\Ui;

use Sintattica\Atk\Errors\AtkErrorException;
use Sintattica\Atk\Utils\Debugger;
use Smarty;

class SmartyFactory
{
    public static function createSmarty(Debugger $debugger, $compileDir, $templateDir, $forceCompile)
    {
        $debugger->addDebug('Creating Smarty instance');

        if (!is_dir($compileDir) && !mkdir($compileDir, 0755, true)) {
            throw new AtkErrorException("Unable to create template compile directory: $compileDir");
        }

        $smarty = new Smarty();
        $smarty->setTemplateDir($templateDir);
        $smarty->autoload_filters = [];
        $smarty->setCompileDir(realpath($compileDir));
        $smarty->setForceCompile($forceCompile);
        $smarty->addPluginsDir([__DIR__.'/plugins']);

        $debugger->addDebug('Instantiated new Smarty');

        return $smarty;
    }
}
