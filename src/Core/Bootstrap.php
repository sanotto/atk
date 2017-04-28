<?php

namespace Sintattica\Atk\Core;

use Sintattica\Atk\Errors\AtkErrorException;
use Sintattica\Atk\Security\SecurityManager;
use Sintattica\Atk\Security\SqlWhereclauseBlacklistChecker;
use Sintattica\Atk\Utils\Debugger;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class Bootstrap
{
    protected $container;
    private $environment;

    public function __construct($environment, $basedir)
    {
        $this->container = new ContainerBuilder();
        $loader = new YamlFileLoader($this->container, new FileLocator(__DIR__.'/../Resources/config/'));
        $loader->load('services.yml');

        if(!$this->container->hasParameter('atk.root_dir')) {
            $this->container->setParameter('atk.root_dir', realpath(__DIR__.'/../../../../../'));
        }

        // force the debugger instantiation
        /** @var Debugger $debugger */
        $debugger = $this->container->get('atk.debugger');

        $this->environment = $environment;

        Config::init();


        /** @var Language $language */
        $language = $this->container->get('atk.language');

        // set locale
        $locale = $language->trans('locale', 'atk', '', '', true);
        if ($locale) {
            setlocale(LC_TIME, $locale);
        }

        if (!Config::getGlobal('meta_caching')) {
            $debugger->addWarning("Table metadata caching is disabled. Turn on \$config_meta_caching to improve your application's performance!");
        }
    }

    protected function bootModules()
    {
        /** @var ModuleManager $moduleManager */
        $moduleManager = $this->container->get('atk.module_manager');
        $modulesClasses = $this->container->getParameter('atk.modules');
        if (is_array($modulesClasses) && $modulesClasses) {
            foreach ($modulesClasses as $moduleClass) {
                $moduleManager->registerModule($moduleClass);
            }
        }

        $moduleManager->bootModules();
    }

    public function boot()
    {

        $debug = 'Created a new Atk ('.Atk::VERSION.') instance.';
        $debug .= ' Environment: '.$this->container->getParameter('atk.environment').'.';
        if (isset($_SERVER['SERVER_NAME']) && isset($_SERVER['SERVER_ADDR'])) {
            $debug .= ' Server info: '.$_SERVER['SERVER_NAME'].' ('.$_SERVER['SERVER_ADDR'].')';
        }
        $this->container->get('atk.debugger')->addDebug($debug);

        $atkVars = &Atk::createAtkVarsFromGlobals();

        if (Config::getGlobal('use_atkerrorhandler', true)) {
            $errorHandler = $this->container->get('atk.error_manager');
            set_error_handler([$errorHandler, 'errorHandler']);
            set_exception_handler([$errorHandler, 'exceptionHandler']);
            register_shutdown_function([$errorHandler, 'fatalHandler']);
        }

        // filter the request
        $error = 'Unsafe WHERE clause in REQUEST variable';
        if (!SqlWhereclauseBlacklistChecker::filter_request_where_clause_is_safe('atkselector', $atkVars['r'])) {
            throw new AtkErrorException($error.' atkselector');
        } elseif (!SqlWhereclauseBlacklistChecker::filter_request_where_clause_is_safe('atkfilter', $atkVars['r'])) {
            throw new AtkErrorException($error.' atkfilter');
        }

        $sessionManager = $this->container->get('atk.session_manager');
        $sessionManager->start($atkVars);

        if (Config::getGlobal('session_autorefresh') && array_key_exists(Config::getGlobal('session_autorefresh_key'), $atkVars['g'])) {
            die(session_id());
        }

        /** @var SecurityManager $securityManager */
        $securityManager = $this->container->get('atk.security_manager');
        $securityManager->run();

        if ($securityManager->isAuthenticated()) {
            $this->bootModules();

            $indexPage = $this->container->get('atk.ui.index_page');
            $indexPage->generate();
        }
    }
}
