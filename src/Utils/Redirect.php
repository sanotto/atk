<?php
/**
 * User: michele
 * Date: 21/04/17
 * Time: 17:35
 */

namespace Sintattica\Atk\Utils;

use Sintattica\Atk\Ui\Output;

class Redirect
{
    protected $debugLevel;
    protected $debugger;
    protected $output;


    public function __construct($debugLevel, Debugger $debugger, Output $output)
    {
        $this->debugLevel = $debugLevel;
        $this->debugger = $debugger;
        $this->output = $output;
    }

    public function redirect($location, $exit = true)
    {
        if ($this->debugLevel >= 2) {
            $this->debugger->setRedirectUrl($location);
            $this->debugger->addDebug('Non-debug version would have redirected to <a href="'.$location.'">'.$location.'</a>');
            if ($exit) {
                $this->output->outputFlush();
                exit();
            }
        } else {
            $this->debugger->addDebug('redirecting to: '.$location);

            if (substr($location, -1) == '&') {
                $location = substr($location, 0, -1);
            }
            if (substr($location, -1) == '?') {
                $location = substr($location, 0, -1);
            }

            header('Location: '.$location);
            if ($exit) {
                exit();
            }
        }
    }
}
