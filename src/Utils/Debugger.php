<?php

namespace Sintattica\Atk\Utils;

use Sintattica\Atk\Core\Atk;
use Sintattica\Atk\Core\Tools;
use Sintattica\Atk\Core\Config;

/**
 * This class implements the ATK debug console for analysing queries
 * performed in a page.
 *
 * @author Ivo Jansch <ivo@achievo.org>
 */
class Debugger
{

    /**
     * Converts applicable characters to html entities so they aren't
     * interpreted by the browser.
     */
    const DEBUG_HTML = 1;

    /**
     * Wraps the self::text into html bold tags in order to make warnings more
     * clearly visible.
     */
    const DEBUG_WARNING = 2;

    /**
     * Hides the debug unless in level 2.
     * This should be used for debug you only really want to see if you
     * are developing (like deprecation warnings).
     */
    const DEBUG_NOTICE = 4;

    /**
     * Error message.
     */
    const DEBUG_ERROR = 8;


    public $m_isconsole = true;
    public $m_redirectUrl = null;
    protected $m_debug_msg = [];
    protected $m_error_msg = [];
    private static $s_queryCount = 0;
    private static $s_systemQueryCount = 0;
    private static $s_startTime;
    protected $debugLevel;
    protected $debugLog;

    protected static $s_instance = null;

    /**
     * Get an instance of this class.
     *
     * @return Debugger Instance of atkDebugger
     */
    public static function getInstance()
    {
        return self::$s_instance;
    }

    /**
     * Constructor.
     * @param int $debugLevel
     * @param string $debugLog file to write logs
     */
    public function __construct($debugLevel, $debugLog)
    {
        $this->debugLevel = $debugLevel;
        $this->debugLog = $debugLog;
        self::$s_instance = $this;
        self::$s_startTime = microtime(true);
        $this->m_debug_msg[] = $this->atkGetTimingInfo().'Debugger initialized.';
    }


    /**
     * Add a query string to the debugger.
     *
     * @param string $query
     * @param bool $isSystemQuery is system query? (e.g. for retrieving metadata, warnings, setting locks etc.)
     *
     * @return bool Indication if query is added
     */
    public function addQuery($query, $isSystemQuery = false)
    {
        self::$s_queryCount += !$isSystemQuery ? 1 : 0;
        self::$s_systemQueryCount += $isSystemQuery ? 1 : 0;

        $this->addDebug(htmlentities($query));
        return true;
    }

    /**
     * Convert a params array to a querystring to add to the url.
     *
     * @param array $params
     *
     * @return string
     */
    public function urlParams($params)
    {
        if (count($params)) {
            $res = '';
            foreach ($params as $key => $value) {
                $res .= '&'.$key.'='.rawurlencode($value);
            }

            return $res;
        }

        return '';
    }

    /**
     * Renders error messages for the user.
     *
     * @return string error messages string
     * @private
     */
    public function renderPlainErrorMessages()
    {
        if (php_sapi_name() == 'cli') {
            $output = 'error: '.implode("\nerror: ", $this->m_error_msg)."\n";
        } else {
            $output = '<br><div style="font-family: monospace; font-size: 11px; color: #FF0000" align="left">error: '.implode("<br>\nerror: ",
                    $this->m_error_msg).'</div>';
        }

        return $output;
    }

    /**
     * Render debug block for the current debug information.
     *
     * @param bool $expanded Display debugblock expanded?
     *
     * @return string debug block string
     * @private
     */
    public function renderDebugBlock($expanded)
    {
        $time = strftime('%H:%M:%S', self::$s_startTime);
        $duration = sprintf('%02.05f', self::getMicroTime() - self::$s_startTime);
        $usage = sprintf('%02.02f', (memory_get_usage() / 1024 / 1024));
        $method = $_SERVER['REQUEST_METHOD'];
        $protocol = empty($_SERVER['HTTPS']) || strtolower($_SERVER['HTTPS']) == 'off' ? 'http' : 'https';
        $url = $protocol.'://'.$_SERVER['HTTP_HOST'].($_SERVER['SERVER_PORT'] != 80 ? ':'.$_SERVER['SERVER_PORT'] : '').$_SERVER['REQUEST_URI'];

        $label = "[{$time}h / {$duration}s / {$usage}MB / ".self::$s_queryCount.' Queries / '.self::$s_systemQueryCount." System Queries] $method $url";

        $output = '
        <div class="atkDebugBlock'.(count($this->m_error_msg) > 0 ? ' atkDebugBlockContainsErrors' : '').' atkDebug'.($expanded ? 'Expanded' : 'Collapsed').'">
          <div class="atkDebugToggle" onclick="ATK.Debug.toggle(this)">
           '.$label.'
          </div>
          <div class="atkDebugData">
            '.(count($this->m_debug_msg) > 0 ? '<div class="atkDebugLine">'.implode($this->m_debug_msg, '</div><div class="atkDebugLine">').'</div>' : '').'
          </div>
        </div>';

        return $output;
    }

    /**
     * Set redirect URL.
     *
     * @param string $url The redirect url
     * @param bool $force Force to set this redirect url
     */
    public function setRedirectUrl($url, $force = false)
    {
        // normally we only save the first redirect url, but using the force
        // parameter you can force setting another redirect url
        if ($this->m_redirectUrl === null || $force) {
            $this->m_redirectUrl = $url;
        }
    }

    /**
     * Renders the redirect link if applicable.
     */
    public function renderRedirectLink()
    {
        if ($this->m_redirectUrl == null) {
            return '';
        }

        $output = '
        <div class="atkDebugRedirect">
           Non-debug version would have redirected to <a href="'.$this->m_redirectUrl.'">'.$this->m_redirectUrl.'</a>
        </div>';

        return $output;
    }

    /**
     * Renders the debug and error messages to a nice HTML string.
     *
     * @return string html string
     */
    public function renderDebugAndErrorMessages()
    {
        // check if this is an Ajax request
        $isPartial = isset(Atk::$ATK_VARS['atkpartial']);

        // only display error messages
        if (count($this->m_error_msg) > 0 && Config::getGlobal('display_errors') && $this->debugLevel <= 0 && !$isPartial) {
            return $this->renderPlainErrorMessages();
        } // no debug messages or error messages to output
        elseif ($this->debugLevel <= 0 || (count($this->m_debug_msg) == 0 && count($this->m_error_msg) == 0)) {
            return '';
        }

        $expanded = !$isPartial;
        if ($expanded && array_key_exists('atkdebugstate', $_COOKIE) && @$_COOKIE['atkdebugstate'] == 'collapsed') {
            $expanded = false;
        }

        // render debug block
        $block = $this->renderDebugBlock($expanded);

        if ($isPartial) {
            $output = '<script type="text/javascript">ATK.Debug.addContent('.Json::encode($block).');</script>';
        } else {
            $script = Config::getGlobal('assets_url').'javascript/class.atkdebug.js';
            $redirect = $this->renderRedirectLink();
            $output = '
          <script type="text/javascript" src="'.$script.'"></script>
          <div id="atk_debugging_div">
            '.$redirect.'
            '.$block.'
          </div>';
        }

        return $output;
    }

    /**
     * Gets the microtime.
     *
     * @static
     *
     * @return int the microtime
     */
    public static function getMicroTime()
    {
        list($usec, $sec) = explode(' ', microtime());

        return (float)$usec + (float)$sec;
    }

    /**
     * Gets the elapsed time.
     *
     * @return string The elapsed time
     */
    public static function elapsed()
    {
        static $offset = null, $previous = null;

        if ($offset === null) {
            $offset = self::$s_startTime;
            $previous = $offset;
        }

        $new = self::getMicroTime();
        $res = '+'.sprintf('%02.05f', $new - $offset).'s / '.sprintf('%02.05f', $new - $previous).'s';
        $previous = $new;

        return $res;
    }


    public static function addDebugMessage($txt)
    {
        self::$s_instance->m_debug_msg[] = $txt;
    }

    public static function getDebugMessages()
    {
        return self::$s_instance->m_debug_msg;
    }

    public static function addErrorMessage($txt)
    {
        self::$s_instance->m_error_msg[] = $txt;
    }

    public static function getErrorMessages()
    {
        return self::$s_instance->m_error_msg;
    }

    public function addDebug($txt, $flags = 0){
        if ($this->debugLevel >= 0) {
            if (Tools::hasFlag($flags, self::DEBUG_HTML)) {
                $txt = htmlentities($txt);
            }
            if (Tools::hasFlag($flags, self::DEBUG_WARNING)) {
                $txt = '<b>'.$txt.'</b>';
            }

            $line = $this->atkGetTimingInfo().$txt;
            $this->writeLog($line);

            if (Tools::hasFlag($flags, self::DEBUG_ERROR)) {
                $line = '<span class="atkDebugError">'.$line.'</span>';
            }

            if (!Tools::hasFlag($flags, self::DEBUG_NOTICE)) {
                $this->addDebugMessage($line);
            }
        } elseif ($this->debugLevel > -1) {
            // at 0 we still collect the info so we
            // have it in error reports. At -1, we don't collect
            $this->addDebugMessage($txt);
        }
    }

    /**
     * Send a warning to the debug log.
     * A warning gets shown more prominently than a normal debug line.
     *
     * @param string $txt
     */
    public function addWarning($txt)
    {
        $this->addDebug($txt, self::DEBUG_WARNING);
    }

    /**
     * Send a notice to the debug log.
     * A notice doesn't get show unless your debug level is 3 or higher.
     *
     * @param string $txt The text that will be added to the log
     */
    public function addNotice($txt)
    {
        $this->addDebug($txt, self::DEBUG_NOTICE);
    }

    public function getDebugLevel()
    {
        return $this->debugLevel;
    }

    protected function atkGetTimingInfo()
    {
        $ret = '[';
        $ret .= $this->elapsed();
        if($this->debugLevel > 0){
            $ret .= ' / '.sprintf('%02.02f', (memory_get_usage() / 1024 / 1024)).'MB';
        }
        $ret .= ']';

        return  $ret;
    }

    /**
     * Writes info to the optional debug logfile.
     * Please notice this feature will heavily decrease the performance
     * and should therefore only be used for debugging and development
     * purposes.
     *
     * @param string $text self::text to write to the logfile
     */
    protected function writeLog($text)
    {
        if ($this->debugLevel > 0 && $this->debugLog) {
            Tools::atkWriteToFile($text, $this->debugLog);
        }
    }
}
