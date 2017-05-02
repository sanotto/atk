<?php

namespace Sintattica\Atk\Ui;

use Sintattica\Atk\Core\Language;
use Sintattica\Atk\Errors\AtkErrorException;
use Sintattica\Atk\Utils\Debugger;
use Smarty;

class SmartyFactory
{

    public static function createSmarty(Debugger $debugger, Language $language, $compileDir, $templateDir, $forceCompile)
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

        $smarty->registerPlugin('function', 'atktext', [new SmartyPluginText($language), 'plugin']);

        $debugger->addDebug('Instantiated new Smarty');

        return $smarty;
    }
}
