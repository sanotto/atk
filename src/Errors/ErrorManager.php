<?php

namespace Sintattica\Atk\Errors;

use Sintattica\Atk\Core\Config;
use Sintattica\Atk\Core\Tools;
use Sintattica\Atk\Ui\Output;
use Sintattica\Atk\Utils\Debugger;

class ErrorManager
{
    protected $debugger;
    protected $output;

    public function __construct(Debugger $debugger, Output $output)
    {
        $this->debugger = $debugger;
        $this->output = $output;
    }

    /**
     * This function catches PHP parse errors etc, and passes
     * them to atkError, so errors can be mailed and output
     * can be regulated.
     * This funtion must be registered with set_error_handler("self::atkErrorHandler");.
     *
     * @param $errtype : One of the PHP errortypes (E_PARSE, E_USER_ERROR, etc)
     * (See http://www.php.net/manual/en/function.error-reporting.php)
     * @param $errstr : Error self::text
     * @param $errfile : The php file in which the error occured.
     * @param $errline : The line in the file on which the error occured.
     */
    public function errorHandler($errtype, $errstr, $errfile, $errline)
    {
        $errortype = array(
            E_ERROR => "Error",
            E_WARNING => "Warning",
            E_PARSE => "Parsing Error",
            E_NOTICE => "Notice",
            E_CORE_ERROR => "Core Error",
            E_CORE_WARNING => "Core Warning",
            E_COMPILE_ERROR => "Compile Error",
            E_COMPILE_WARNING => "Compile Warning",
            E_USER_ERROR => "User Error",
            E_USER_WARNING => "User Warning",
            E_USER_NOTICE => "User Notice",
            E_STRICT => "Strict Notice",
            E_RECOVERABLE_ERROR => "Recoverable Error",
            E_DEPRECATED => "Deprecated",
            E_USER_DEPRECATED => "User Deprecated",
        );

        $errortypestring = $errortype[$errtype];

        if ($errtype == E_NOTICE) {
            // Just show notices
            $this->debugger->addDebug("[$errortypestring] $errstr in $errfile (line $errline)", $this->debugger::DEBUG_NOTICE);

            return;
        }

        if (($errtype & (E_DEPRECATED | E_USER_DEPRECATED)) > 0) {
            // Just show deprecation warnings in the debug log, but don't influence the program flow
            $this->debugger->addDebug("[$errortypestring] $errstr in $errfile (line $errline)", $this->debugger::DEBUG_NOTICE);

            return;
        }

        if (($errtype & (E_WARNING | E_USER_WARNING)) > 0) {
            // This is something we should pay attention to, but we don't need to die.
            $this->error("[$errortypestring] $errstr in $errfile (line $errline)");

            return;
        }

        $this->error("[$errortypestring] $errstr in $errfile (line $errline)");
        $this->output->outputFlush();
        exit("Halted...\n");
    }

    /**
     * Default ATK exception handler.
     * Handles uncaught exceptions and exit.
     *
     * @param \Exception $exception uncaught exception
     */
    public function exceptionHandler($exception)
    {
        $errstr = $exception->getMessage();
        $errfile = $exception->getFile();
        $errline = $exception->getLine();
        $this->error("[EXCEPTION] $errstr in $errfile (line $errline)");
        $this->output->outputFlush();
        exit("Halted...\n");
    }

    /**
     * Default ATK fatal handler
     */
    public function fatalHandler()
    {
        $error = error_get_last();
        if ($error) {
            $this->errorHandler(E_ERROR, $error['message'], $error['file'], $error['line']);
        }
    }

    /**
     * this displays a message at the bottom of the screen.
     *
     * If error reporting by email is turned on, the error messages are also
     * send by e-mail.
     *
     * @param string $error the error text or exception to display
     *
     */
    protected function error($error)
    {
        $this->debugger->addErrorMessage('['.$this->debugger->elapsed().'] '.$error);
        $this->debugger->addDebug($error, $this->debugger::DEBUG_ERROR);

        $this->debugger->addDebug('Trace:'.Tools::atkGetTrace(), $this->debugger::DEBUG_ERROR);

        $default_error_handlers = [];
        $mailReport = Config::getGlobal('mailreport');
        if ($mailReport) {
            $default_error_handlers['Mail'] = array('mailto' => $mailReport);
        }

        $errorHandlers = Config::getGlobal('error_handlers', $default_error_handlers);
        foreach ($errorHandlers as $key => $value) {
            if (is_numeric($key)) {
                $key = $value;
            }
            $errorHandlerObject = ErrorHandlerBase::get($key, $value);
            $errorHandlerObject->handle($this->debugger->getErrorMessages(), $this->debugger->getDebugMessages());
        }
    }
}