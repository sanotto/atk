<?php

namespace Sintattica\Atk\Session;

use Sintattica\Atk\Core\Config;
use Sintattica\Atk\Core\Language;
use Sintattica\Atk\Core\NodeManager;
use Sintattica\Atk\Core\Tools;
use Sintattica\Atk\Ui\Ui;
use Sintattica\Atk\Utils\Debugger;
use Sintattica\Atk\Utils\Redirect;

/**
 * The atk session manager.
 */
class SessionManager
{
    const SESSION_DEFAULT = 0; // stay at current stacklevel
    const SESSION_NEW = 1;     // new stack
    const SESSION_NESTED = 2;  // new item on current stack
    const SESSION_BACK = 3;    // move one level down on stack
    const SESSION_REPLACE = 4; // replace current stacklevel
    const SESSION_PARTIAL = 5; // same as replace, but ignore atknodeuri and atkaction
    protected static $s_instance = null;
    public $atklevel; // are we escaping?
    public $atkprevlevel; // should we use a session stack
    public $atkstackid;
    protected $atkVars;
    protected $ui;
    protected $debugger;
    protected $redirect;

    /** @var  NodeManager $nodeManager */
    protected $nodeManager;

    /** @var  MessageQueue $messageQueue */
    protected $messageQueue;

    /** @var  SessionStoreFactory $sessionStoreFactory */
    protected $sessionStoreFactory;
    
    /** @var  Language $language */
    protected $language;

    /**
     * @var string
     */
    private $m_namespace;
    /**
     * @var bool
     */
    private $m_escapemode = false;
    /**
     * @var bool
     */
    private $m_usestack = true;


    /**
     * Default constructor.
     *
     * @param string $namespace If multiple scripts/applications are
     *                          installed on thesame url, they can each use
     *                          a different namespace to make sure they
     *                          don't share session data.
     * @param bool $usestack Tell the sessionmanager to use the session
     *                          stack manager (back/forth navigation in
     *                          screens, remembering vars over multiple
     *                          pages etc). This comes with a slight
     *                          performance impact, so scripts not using
     *                          the stack should pass false here.
     *
     * @param Ui $ui
     * @param Debugger $debugger
     * @param Redirect $redirect
     * @param Language $language
     */
    public function __construct($namespace = 'default', $usestack = true, Ui $ui, Debugger $debugger, Redirect $redirect, Language $language)
    {
        self::$s_instance = $this;

        $this->m_namespace = $namespace;
        $this->m_usestack = $usestack;
        $this->ui = $ui;
        $this->debugger = $debugger;
        $this->redirect = $redirect;
        $this->language = $language;

        $this->debugger->addDebug("creating sessionManager (namespace: $namespace)");
    }

    /**
     * Get direct access to the php session.
     *
     * The advantage of using SessionManager::getSession over php's
     * $_SESSION directly, is that this method is application aware.
     * If multiple applications are stored on the same server, and each has
     * a unique $config_identifier set, the session returned by this method
     * is specific to only the current application, whereas php's $_SESSION
     * is global on the url where the session cookie was set.
     *
     * @static
     *
     * @return array The application aware php session.
     */
    public static function &getSession()
    {
        if (!isset($_SESSION[Config::getGlobal('identifier')]) || !is_array($_SESSION[Config::getGlobal('identifier')])) {
            $_SESSION[Config::getGlobal('identifier')] = [];
        }

        return $_SESSION[Config::getGlobal('identifier')];
    }

    public static function getSessionAutoRefreshJs()
    {
        $url = Config::getGlobal('dispatcher').'?'.Config::getGlobal('session_autorefresh_key');
        $time = Config::getGlobal('session_autorefresh_time', 3600);

        return 'jQuery(function($){window.setInterval(function(){$.ajax({cache:false,type:"GET",url:"'.$url.'"});},'.$time.');});';
    }

    public function setNodeManager(NodeManager $nodeManager)
    {
        $this->nodeManager = $nodeManager;
    }

    public function setMessageQueue(MessageQueue $messageQueue)
    {
        $this->messageQueue = $messageQueue;
    }

    public function getMessageQueue()
    {
        return $this->messageQueue;
    }

    public function setSessionStoreFactory(SessionStoreFactory $sessionStoreFactory)
    {
        $this->sessionStoreFactory = $sessionStoreFactory;
    }

    public function getSessionStoreFactory()
    {
        return $this->sessionStoreFactory;
    }

    /**
     * Initializes the sessionmanager.
     * @param array $atkVars
     * @return bool
     */
    public function start(&$atkVars)
    {
        if (php_sapi_name() == 'cli') {
            return false; // command-line
        }

        $this->atkVars = &$atkVars;

        if (isset($this->atkVars['atklevel'])) {
            $this->atklevel = trim($this->atkVars['atklevel']);
        }
        if (isset($this->atkVars['atkprevlevel'])) {
            $this->atkprevlevel = trim($this->atkVars['atkprevlevel']);
        }
        if (isset($this->atkVars['atkstackid'])) {
            $this->atkstackid = trim($this->atkVars['atkstackid']);
        }

        //session init
        $cookie_params = session_get_cookie_params();
        $cookiepath = Config::getGlobal('cookie_path');
        $cookiedomain = (Config::getGlobal('cookiedomain') != '') ? Config::getGlobal('cookiedomain') : null;
        session_set_cookie_params($cookie_params['lifetime'], $cookiepath, $cookiedomain);

        session_cache_expire(Config::getGlobal('session_cache_expire'));

        // set the cache limiter (used for caching)
        session_cache_limiter(Config::getGlobal('session_cache_limiter'));

        // start the session
        $sessionname = Config::getGlobal('session_name');
        if (!$sessionname) {
            $sessionname = Config::getGlobal('identifier');
        }
        session_name($sessionname);
        session_start();

        $this->session_read($this->atkVars);

        // Escape check
        if (isset($this->atkVars['atkescape']) && $this->atkVars['atkescape'] != '') {
            $this->redirect->redirect(Tools::atkurldecode($this->atkVars['atkescape']));
        } // Nested URL check
        else {
            if (isset($this->atkVars['atknested']) && $this->atkVars['atknested'] != '') {
                $this->redirect->redirect($this->sessionUrl($this->atkVars['atknested'], self::SESSION_NESTED));
            } // Back check
            elseif (isset($this->atkVars['atkback']) && $this->atkVars['atkback'] != '') {
                // When we go back, we go one level deeper than the level we came from.
                $this->redirect->redirect($this->sessionUrl(Config::getGlobal('dispatcher').'?atklevel='.($this->atkprevlevel - 1)));
            }
        }

        return true;
    }

    public function destroy()
    {
        unset($_SESSION[Config::getGlobal('identifier')]);
        session_destroy();

        $cookie_params = session_get_cookie_params();
        $cookiepath = Config::getGlobal('cookie_path');
        $cookiedomain = (Config::getGlobal('cookiedomain') != '') ? Config::getGlobal('cookiedomain') : null;
        session_set_cookie_params($cookie_params['lifetime'], $cookiepath, $cookiedomain);
    }

    /**
     * Get the name of the current session (as was passed to atksession()).
     *
     * @return string namespace
     */
    public function getNameSpace()
    {
        return $this->m_namespace;
    }

    /**
     * Read session variables from the stack and the global scope.
     *
     * @param array $postvars Any variables passed in the http request.
     */
    public function session_read(&$postvars)
    {
        $this->_globalscope($postvars);
        if ($this->m_usestack) {
            $this->_stackscope($postvars);
        }
    }

    /**
     * Register a global variable.
     *
     * Saves a value in the current namespace.
     *
     * @param string $var The name of the variable to save.
     * @param mixed $value The value of the variable to save. If omitted,
     *                             the value is retrieved from the http request.
     * @param bool $no_namespace If set to false, the variable is saved
     *                             in the current namespace. If set to true,
     *                             the variable is available in all
     *                             namespaces.
     *
     * @return mixed
     */
    public function globalVar($var, $value = '', $no_namespace = false)
    {
        $sessionData = &self::getSession();

        if ($value == '' && isset($this->atkVars[$var])) {
            $value = $this->atkVars[$var];
        }

        if ($no_namespace) {
            $sessionData['globals'][$var] = $value;
        } else {
            $sessionData[$this->m_namespace]['globals'][$var] = $value;
        }

        return $value;
    }

    /**
     * Retrieve the value of a session variable.
     *
     * @param string $var The name of the variable to retrieve.
     * @param string $namespace The namespace from which to retrieve the
     *                          variable, or "globals" if the global value
     *                          needs to be retrieved.
     *
     * @return mixed The retrieved value.
     */
    public function getValue($var, $namespace = '')
    {
        $sessionData = &self::getSession();

        if ($namespace == 'globals') {
            return isset($sessionData['globals'][$var]) ? $sessionData['globals'][$var] : null;
        } else {
            if ($namespace != '') {
                return isset($sessionData[$namespace]['globals'][$var]) ? $sessionData[$namespace]['globals'][$var] : null;
            } else {
                return isset($sessionData[$this->m_namespace]['globals'][$var]) ? $sessionData[$this->m_namespace]['globals'][$var] : null;
            }
        }
    }

    /**
     * Store a variable in the session stack. The variable is available in the
     * current page (even after reload), and any screen that is deeper on the
     * session stack.
     *
     * The method can be used to transparantly both store and retrieve the value.
     * If a value gets passed in a url, the following statement is useful:
     *
     * <code>
     *   $view = $sessionManager->stackVar("view");
     * </code>
     *
     * This statement makes sure that $view is always filled. If view is passed
     * in the url, it is stored as the new default stack value. If it's not
     * passed in the url, the last known value is retrieved from the session.
     *
     * Also note that if you set a stackvar to A in level 0, then in level 1
     * reset it to B, when you return to level 0, the stackvar will still be A.
     * However for level 1 and deeper it will be B.
     *
     * @param string $var The name of the variable to store.
     * @param mixed $value The value to store. If omitted, the session manager
     *                      tries to read the value from the http request.
     * @param int $level Get/Set var on this level, will be current level by
     *                      default.
     *
     * @return mixed The current value in the session stack.
     */
    public function stackVar($var, $value = '', $level = null)
    {
        if ($this->m_escapemode) {
            return;
        }

        // If no level is supplied we use the var from the current level
        if ($level === null) {
            $level = $this->atkLevel();
        }

        $sessionData = &$this->getSession();
        $currentitem = &$sessionData[$this->m_namespace]['stack'][$this->atkStackID()][$level];
        if (!is_array($currentitem)) {
            return;
        }

        if ($level === $this->atkLevel() && $value === '' && Tools::atkArrayNvl($this->atkVars, $var, '') !== '') {
            // Only read the value of the stack var from the request if this is the first
            // call to stackVar for this var in this request without an explicit value. If
            // we would this for every call without an explicit value we would overwrite values
            // that are set somewhere between those calls.
            static $requestStackVars = [];
            if (!in_array($var, $requestStackVars)) {
                $value = $this->atkVars[$var];
                $requestStackVars[] = $var;
            }
        }

        if ($value !== '') {
            $currentitem[$var] = $value;
        }

        if (!is_array(Tools::atkArrayNvl($currentitem, 'defined_stackvars')) || !in_array($var, $currentitem['defined_stackvars'])) {
            $currentitem['defined_stackvars'][] = $var;
        }

        // We always return the current value..
        return Tools::atkArrayNvl($currentitem, $var);
    }

    /**
     * Store a global variable for the current stack in the session.
     * Unlike stackvars, this variable occurs only once for a given stack.
     *
     * For example with a stackvar, if you store a variable x in level 0,
     * then in level 1 you modify that variable, the variable in level 0
     * will not be modified. With a globalStackVar it will.
     *
     * @param string $var Variable name
     * @param mixed $value Variable value
     *
     * @return mixed Value of the global stackvar
     */
    public function globalStackVar($var, $value = '')
    {
        if (!$var || $this->m_escapemode) {
            return;
        }

        $sessionData = &$this->getSession();
        $top_stack_level = &$sessionData[$this->m_namespace]['globals']['#STACK#'][$this->atkStackID()];
        if (!is_array($top_stack_level)) {
            $top_stack_level = [];
        }

        if ($value === '') {
            if (Tools::atkArrayNvl($this->atkVars, $var, '') !== '') {
                $value = $this->atkVars[$var];
            } else {
                if ($this->stackVar($var)) {
                    $value = $this->stackVar($var);
                }
            }
        }

        if ($value !== '') {
            $top_stack_level[$var] = $value;
        }

        return Tools::atkArrayNvl($top_stack_level, $var);
    }

    /**
     * Store a variable in the session stack. The variable is available only in
     * the current page (even after reload). In contrast with stackVar(), the
     * variable is invisible in deeper screens.
     *
     * The method can be used to transparantly both store and retrieve the value.
     * If a value gets passed in a url, the following statement is useful:
     * <code>
     *   $view = $sessionManager->pageVar("view");
     * </code>
     * This statement makes sure that $view is always filled. If view is passed
     * in the url, it is stored as the new default stack value. If it's not
     * passed in the url, the last known value is retrieved from the session.
     *
     * @param string $var The name of the variable to store.
     * @param mixed $value The value to store. If omitted, the session manager
     *                      tries to read the value from the http request.
     *
     * @return mixed The current value in the session stack.
     */
    public function pageVar($var, $value = '')
    {
        if (!$this->m_escapemode) {
            $sessionData = &self::getSession();

            $currentitem = &$sessionData[$this->m_namespace]['stack'][$this->atkStackID()][$this->atkLevel()];

            if ($value == '') {
                if (isset($this->atkVars[$var])) {
                    $this->debugger->addDebug('Setting current item');
                    $currentitem[$var] = $this->atkVars[$var];
                }
            } else {
                $currentitem[$var] = $value;
            }
            if (!isset($currentitem['defined_pagevars']) || !is_array($currentitem['defined_pagevars']) || !in_array($var, $currentitem['defined_pagevars'])) {
                $currentitem['defined_pagevars'][] = $var;
            }
            // We always return the current value..
            if (isset($currentitem[$var])) {
                return $currentitem[$var];
            }

            return '';
        }

        return;
    }

    /**
     * Retrieve a trace of the current session stack.
     *
     * @return array Array containing the title and url for each stacklevel.
     *               The url can be used to directly move back on the session
     *               stack.
     */
    public function stackTrace()
    {
        $sessionData = &$this->getSession();

        $res = [];
        $stack = $sessionData[$this->m_namespace]['stack'][$this->atkStackID()];

        for ($i = 0; $i < count($stack); ++$i) {
            if (!isset($stack[$i]['atknodeuri'])) {
                continue;
            }

            $node = $stack[$i]['atknodeuri'];
            $module = Tools::getNodeModule($node);
            $type = Tools::getNodeType($node);
            $action = $stack[$i]['atkaction'];
            $title = $this->ui->nodeTitle($this->nodeManager->getNode($node), $action);
            $descriptor = Tools::atkArrayNvl($stack[$i], 'descriptor', '');

            $entry = array(
                'url' => '',
                'title' => $title,
                'descriptor' => $descriptor,
                'node' => $node,
                'nodetitle' => $this->language->text($type, $module, $type),
                'action' => $action,
                'actiontitle' => $this->language->text($action, $module, $type),
            );

            if ($i < count($stack) - 1) {
                $entry['url'] = $this->sessionUrl(Config::getGlobal('dispatcher').'?atklevel='.$i);
            }

            $res[] = $entry;
        }

        return $res;
    }

    /**
     * Gets the node and the descriptor for the current item
     * and returns a trace of that.
     *
     * So for instance, if we were adding a grade to a student,
     * it would show:
     * Student [ Teknoman ] - Grade [ A+ ]
     *
     * @return array The descriptortrace
     */
    public function descriptorTrace()
    {
        $sessionData = &$this->getSession();

        $stack = $sessionData[$this->m_namespace]['stack'][$this->atkStackID()];
        $res = [];
        $node = null;
        $module = null;
        $nodename = null;
        $stackcount = count($stack);
        for ($i = 0; $i < $stackcount; ++$i) {
            if (isset($stack[$i]['descriptor']) || $i == ($stackcount - 1)) {
                if ($stack[$i]['atknodeuri'] != '') {
                    $node = $this->nodeManager->getNode($stack[$i]['atknodeuri']);
                    $module = Tools::getNodeModule($stack[$i]['atknodeuri']);
                    $nodename = Tools::getNodeType($stack[$i]['atknodeuri']);
                }

                if (is_object($node)) {
                    $txt = $this->ui->nodeTitle($node);
                } else {
                    $txt = $this->language->text($nodename, $module);
                }

                $res[] = $txt.(isset($stack[$i]['descriptor']) ? " [ {$stack[$i]['descriptor']} ] " : '');
            }
        }

        return $res;
    }

    /**
     * Calculate a new session level based on current level and
     * a passed sessionstatus.
     *
     * @param int $sessionstatus the session flags
     *                           (SessionManager::SESSION_DEFAULT (default)|SessionManager::SESSION_NEW|SessionManager::SESSION_REPLACE|
     *                           SessionManager::SESSION_NESTED|SessionManager::SESSION_BACK)
     * @param int $levelskip how many levels to skip when we use SessionManager::SESSION_BACK,
     *                           default 1
     * @static
     *
     * @return int the new session level
     */
    public function newLevel($sessionstatus = self::SESSION_DEFAULT, $levelskip = null)
    {
        $currentlevel = $this->atkLevel();

        switch ($sessionstatus) {
            case self::SESSION_NEW: {
                $newlevel = -1;
                break;
            }
            case self::SESSION_REPLACE: {
                $newlevel = -2;
                break;
            }
            case self::SESSION_PARTIAL: {
                $newlevel = -3;
                break;
            }
            case self::SESSION_NESTED: {
                $newlevel = $currentlevel + 1;
                break;
            }
            case self::SESSION_BACK: {
                if ($levelskip === null) {
                    $levelskip = 1;
                }

                $newlevel = max(0, $currentlevel - $levelskip);
                break;
            }
            default: {
                $newlevel = $currentlevel;
            }
        }

        return $newlevel;
    }

    /**
     * Calculate old session level based on current level and
     * a passed sessionstatus.
     *
     * @param int $sessionstatus the session flags
     *                           (SessionManager::SESSION_DEFAULT (default)|SessionManager::SESSION_NEW|SessionManager::SESSION_REPLACE|
     *                           SessionManager::SESSION_NESTED|SessionManager::SESSION_BACK)
     * @param int $levelskip how many levels to skip when we use SessionManager::SESSION_REPLACE,
     * @static
     *
     * @return int the new session level
     */
    public function oldLevel($sessionstatus = self::SESSION_DEFAULT, $levelskip = null)
    {
        $level = $this->atkLevel();
        if ($sessionstatus == self::SESSION_REPLACE && $levelskip !== null) {
            $level = $level - $levelskip;
        }

        return max($level, 0);
    }

    /**
     * Adds session information to a form.
     *
     * @param int $sessionstatus the session flags
     *                                (SessionManager::SESSION_DEFAULT (default)|SessionManager::SESSION_NEW|SessionManager::SESSION_REPLACE|
     *                                SessionManager::SESSION_NESTED|SessionManager::SESSION_BACK)
     * @param int $returnbehaviour When SessionManager::SESSION_NESTED is used, this is used to
     *                                indicate where to return to.
     * @param string $fieldprefix
     *
     * @return string the HTML formcode with the session info
     */
    public function formState(
        $sessionstatus = self::SESSION_DEFAULT,
        $returnbehaviour = null,
        $fieldprefix = ''
    ) {

        $res = '';

        $newlevel = $this->newLevel($sessionstatus);

        if ($newlevel != 0) {
            $res = '<input type="hidden" name="atklevel" value="'.$newlevel.'" />';
        }
        $res .= '<input type="hidden" name="atkprevlevel" value="'.$this->atkLevel().'" />';

        if ($sessionstatus != self::SESSION_NEW) {
            $res .= '<input type="hidden" name="atkstackid" value="'.$this->atkStackID().'" />';
        }

        if (!is_null($returnbehaviour)) {
            $res .= '<input type="hidden" name="'.$fieldprefix.'atkreturnbehaviour" value="'.$returnbehaviour.'" />';
        }

        $res .= '<input type="hidden" name="'.session_name().'" value="'.session_id().'" />';
        $res .= '<input type="hidden" name="atkescape" value="" autocomplete="off" />';

        return $res;
    }

    /**
     * Gets the session vars.
     *
     * @param int $sessionstatus the session flags
     *                              (SessionManager::SESSION_DEFAULT (default)|SessionManager::SESSION_NEW|SessionManager::SESSION_REPLACE|
     *                              SessionManager::SESSION_NESTED|SessionManager::SESSION_BACK)
     * @param int $levelskip the amount of levels to skip if we go back
     * @param string $url the URL
     *
     * @return string the vars of the session
     */
    public function sessionVars($sessionstatus = self::SESSION_DEFAULT, $levelskip = null, $url = '')
    {
        $newlevel = $this->newLevel($sessionstatus, $levelskip);
        $oldlevel = $this->oldLevel($sessionstatus, $levelskip);

        $vars = '';
        // atklevel is already set manually, we don't append it..
        if ($newlevel != 0 && !strpos($url, 'atklevel=') > 0) {
            $vars .= 'atklevel='.$newlevel.'&';
        }
        $vars .= 'atkprevlevel='.$oldlevel;
        if ($sessionstatus != self::SESSION_NEW) {
            $vars .= '&atkstackid='.$this->atkStackID();
        }

        return $vars;
    }

    /**
     * Makes a session-aware URL.
     *
     * @param string $url the url to make session-aware
     * @param int $sessionstatus the session flags
     *                              (SessionManager::SESSION_DEFAULT (default)|SessionManager::SESSION_NEW|SessionManager::SESSION_REPLACE|
     *                              SessionManager::SESSION_NESTED|SessionManager::SESSION_BACK)
     * @param int $levelskip the amount of levels to skip if we go back
     * @static
     *
     * @return string the session aware URL
     */
    public function sessionUrl($url, $sessionstatus = self::SESSION_DEFAULT, $levelskip = null)
    {
        if (strpos($url, '?') !== false) {
            $start = '&';
        } else {
            $start = '?';
        }

        $url .= $start;
        $url .= $this->sessionVars($sessionstatus, $levelskip, $url);

        return $url;
    }

    /**
     * Used by the session manager to retrieve a unique id for the current atk stack.
     * @param bool $new
     *
     * @return string atkstackid
     */
    public function atkStackID($new = false)
    {
        if (!isset($this->atkstackid) || $this->atkstackid == '' || $new) {
            // No stack id yet, or forced creation of a new one.
            $this->atkstackid = uniqid('');
        }

        return $this->atkstackid;
    }

    /**
     * Retrieve the current atkLevel of the session stack.
     *
     * Level 0 is the 'entry screen' of a stack. Any screen deeper from the
     * entry screen (following an edit link for example) has its atklevel
     * increased by 1. This method is useful for checking if a 'back' button
     * should be displayed. A backbutton will work for any screen whose
     * atklevel is bigger than 0.
     *
     * @return int The current atk level.
     */
    public function atkLevel()
    {
        if (!isset($this->atklevel) || $this->atklevel == '') {
            $this->atklevel = 0; // assume bottom level.
        }

        return $this->atklevel;
    }

    public function atkPrevLevel()
    {
        if (!isset($this->atkprevlevel) || $this->atkprevlevel == '') {
            $this->atkprevlevel = 0; // assume bottom level.
        }

        return $this->atkprevlevel;
    }

    public function partial_url(
        $node,
        $action,
        $partial,
        $params = [],
        $sessionStatus = SessionManager::SESSION_PARTIAL
    ) {
        if (!is_array($params)) {
            $params = [];
        }
        $params['atkpartial'] = $partial;

        return $this->sessionUrl(Tools::dispatch_url($node, $action, $params), $sessionStatus);
    }

    /**
     * Makes a session-aware href url.
     * When using hrefs in the editform, you can set saveform to true. This will save your
     * form variables in the session and restore them whenever you come back.
     *
     * @param string $url the url to make session aware
     * @param string $name the name to display (will not be escaped!)
     * @param int $sessionstatus the session flags
     *                              (SessionManager::SESSION_DEFAULT (default)|SessionManager::SESSION_NEW|SessionManager::SESSION_REPLACE|
     *                              SessionManager::SESSION_NESTED|SessionManager::SESSION_BACK)
     * @param bool $saveform wether or not to save the form
     * @param string $extraprops extra props you can add in the link such as
     *                              'onChange="doSomething()"'
     * @static
     *
     * @return string the HTML link for the session aware URI
     */
    public function href(
        $url,
        $name = '',
        $sessionstatus = self::SESSION_DEFAULT,
        $saveform = false,
        $extraprops = ''
    ) {
        if ($saveform) {
            $str = 'atkSubmit("'.Tools::atkurlencode($this->sessionUrl($url, $sessionstatus)).'", true);';

            return '<a href="javascript:void(0)" onclick="'.htmlentities($str).'" '.$extraprops.'>'.$name.'</a>';
        }

        $str = $this->sessionUrl($url, $sessionstatus);

        return '<a href="'.htmlentities($str).'" '.$extraprops.'>'.$name.'</a>';
    }

    /**
     * Creates a session aware button.
     *
     * @param string $text the self::text to display on the button
     * @param string $url the url to use for the button
     * @param int $sessionstatus the session flags
     * @param bool $embedded it's an embedded button
     * @param string $cssclass the css class the button should get
     *
     * @return string html button
     */
    public function button(
        $text,
        $url = '',
        $sessionstatus = self::SESSION_DEFAULT,
        $embedded = true,
        $cssclass = ''
    ) {

        static $cnt = 0;

        if ($cssclass == '') {
            $cssclass = 'btn btn-default';
        }

        $cssclass = ' class="'.$cssclass.'"';
        $script = 'atkSubmit("'.Tools::atkurlencode($this->sessionUrl($url, $sessionstatus)).'")';
        $button = '<input type="button" name="atkbtn'.(++$cnt).'" value="'.$text.'" onClick=\''.$script.'\''.$cssclass.'>';

        if (!$embedded) {
            $res = '<form name="entryform">';
            $res .= $this->formState();
            $res .= $button.'</form>';

            return $res;
        } else {
            return $button;
        }
    }

    /**
     * Process the global variable scope.
     *
     * @param array $postvars The http request variables.
     */
    protected function _globalscope(&$postvars)
    {
        $sessionData = &self::getSession();

        $current = &$sessionData[$this->m_namespace]['globals'];
        if (!is_array($current)) {
            $current = [];
        }

        // Posted vars always overwrite anything in the current session..
        foreach ($current as $var => $value) {
            if (isset($postvars[$var]) && $postvars[$var] != '') {
                $current[$var] = $postvars[$var];
            }
        }

        foreach ($current as $var => $value) {
            $postvars[$var] = $value;
        }
    }

    /**
     * Update the last modified timestamp for the curren stack.
     */
    protected function _touchCurrentStack()
    {
        $sessionData = &self::getSession();
        $sessionData[$this->m_namespace]['stack_stamp'][$this->atkStackID()] = time();
    }

    /**
     * Removes any stacks which have been inactive for a period >
     * Config::getGlobal('session_max_stack_inactivity_period').
     */
    protected function _removeExpiredStacks()
    {
        $sessionData = &self::getSession();

        $maxAge = Config::getGlobal('session_max_stack_inactivity_period', 0);
        if ($maxAge <= 0) {
            $this->debugger->addWarning(__METHOD__.': removing expired stacks disabled, enable by setting $config_session_max_stack_inactivity_period to a value > 0');

            return;
        }

        $now = time();
        $stacks = &$sessionData[$this->m_namespace]['stack'];
        $stackStamps = $sessionData[$this->m_namespace]['stack_stamp'];
        $stackIds = array_keys($stacks);
        $removed = false;

        foreach ($stackIds as $stackId) {
            // don't remove the current stack or stacks that are, for some reason, not stamped
            if ($stackId == $this->atkStackID() || !isset($stackStamps[$stackId])) {
                continue;
            }

            $stamp = $stackStamps[$stackId];
            $age = $now - $stamp;

            if ($age > $maxAge) {
                $this->debugger->addDebug(__METHOD__.': removing expired stack "'.$stackId.'" (age '.$age.'s)');
                unset($stacks[$stackId]);
                unset($stackStamps[$stackId]);
                $removed = true;
            }
        }

        if (!$removed) {
            $this->debugger->addDebug(__METHOD__.': no expired stacks, nothing removed');
        }
    }

    /**
     * Process the variable stack scope (pagevars, stackvars).
     *
     * @param array $postvars The http request variables.
     */
    protected function _stackscope(&$postvars)
    {
        $sessionData = &self::getSession();

        // session vars are valid until they are set to something else. if you go a session level higher,
        // the next level will still contain these vars (unless overriden in the url)
        $sessionVars = array(
            'atknodeuri',
            'atkfilter',
            'atkaction',
            'atkpkret',
            'atkstore',
            'atkstore_key',
        );

        // pagevars are valid on a page. if you go a session level higher, the pagevars are no longer
        // visible until you return. if the name ends in a * the pagevar is treated as an array that
        // needs to be merged recursive with new postvar values
        $pageVars = array(
            'atkdg*',
            'atkdgsession',
            'atksearch',
            'atkselector',
            'atksearchmode',
            'atkorderby',
            'atkstartat',
            'atklimit',
            'atktarget',
            'atkformdata',
            'atksuppress',
            'atktab',
            'atksmartsearch',
            'atkindex',
        );

        // lockedvars are session or page vars that will not be overwritten in partial mode
        // e.g., the values that are already known in the session will be used
        $lockedVars = array('atknodeuri', 'atkaction', 'atkselector');

        // Mental note: We have an $this->atkLevel() function for retrieving the atklevel,
        // but we use the global var itself here, because it gets modified in
        // the stackscope function.

        if (!isset($this->atklevel) || $this->atklevel == '') {
            $this->atklevel = 0;
        }

        $this->debugger->addDebug('ATKLevel: '.$this->atklevel);

        if ($this->_verifyStackIntegrity() && $this->atklevel == -1) {
            // New stack, new stackid, if level = -1.
            $stackid = $this->atkStackID($this->atklevel == -1);
        } else {
            $stackid = $this->atkStackID();
        }

        $stack = &$sessionData[$this->m_namespace]['stack'][$stackid];

        // garbage collect
        $this->_touchCurrentStack();
        $this->_removeExpiredStacks();

        // Prevent going more than 1 level above the current stack top which
        // causes a new stackitem to be pushed onto the stack at the wrong
        // location.
        if ($this->atklevel > count($stack)) {
            $this->debugger->addDebug('Requested ATKLevel ('.$this->atklevel.') too high for stack, lowering to '.count($stack));
            $this->atklevel = count($stack);
        }

        if (isset($postvars['atkescape']) && $postvars['atkescape'] != '') {
            $this->m_escapemode = true;
            $this->debugger->addDebug('ATK session escapemode');

            $currentitem = &$stack[count($stack) - 1];

            $this->debugger->addDebug('Saving formdata in session');

            unset($currentitem['atkreject']); // clear old reject info

            $atkformdata = [];
            foreach (array_keys($postvars) as $varname) {
                // Only save formdata itself, hence no $atk.. variables.
                // Except atktab because it could be changed in the page load.
                // but why don't we save all page vars here? What is the reason for
                // not doing this? TODO: Ask Ivo.
                if (substr($varname, 0, 3) != 'atk' || $varname == 'atktab') {
                    $atkformdata[$varname] = $postvars[$varname];
                }
            }
            $currentitem['atkformdata'] = $atkformdata;

            // also remember getvars that were passed in the url
            // this *may not be* $this->atkVars, because then the posted vars
            // will be overwritten, which may not be done in escape mode,
            // I wonder if the next few lines are necessary at all, but
            // I think I needed them once, so I'll leave it in place.
            foreach (array_keys($this->atkVars['g']) as $var) {
                if (isset($postvars[$var]) && $postvars[$var] != '') {
                    $currentitem[$var] = $postvars[$var];
                }
            }

            // finally, reset atkescape to prevent atk from keeping escaping upon return
            unset($currentitem['atkescape']);
        } else {
            // partial mode?
            $partial = false;

            if ($this->atklevel == -1 || !is_array($stack)) { // SessionManager::SESSION_NEW
                $this->debugger->addDebug('Cleaning stack');
                $stack = [];
                $this->atklevel = 0;
            } else {
                if ($this->atklevel == -2) { // SessionManager::SESSION_REPLACE
                    // Replace top level.
                    array_pop($stack);

                    // Note that the atklevel is now -2. This is actually wrong. We are at
                    // some level in the stack. We can determine the real level by
                    // counting the stack.
                    $this->atklevel = count($stack);
                } else {
                    if ($this->atklevel == -3) { // SessionManager::SESSION_PARTIAL
                        $partial = true;

                        // Note that the atklevel is now -3. This is actually wrong. We are at
                        // some level in the stack. We can determine the real level by
                        // counting the stack.
                        $this->atklevel = count($stack) - 1;
                    }
                }
            }

            if (isset($stack[$this->atklevel])) {
                $currentitem = $stack[$this->atklevel];
            }

            if (!isset($currentitem) || $currentitem == '') {
                $this->debugger->addDebug('New level on session stack');
                // Initialise
                $currentitem = [];
                // new level.. always based on the previous level
                if (isset($stack[count($stack) - 1])) {
                    $copieditem = $stack[count($stack) - 1];
                }

                if (isset($copieditem) && is_array($copieditem)) {
                    foreach ($copieditem as $key => $value) {
                        if (in_array($key,
                                $sessionVars) || (isset($copieditem['defined_stackvars']) && is_array($copieditem['defined_stackvars']) && in_array($key,
                                    $copieditem['defined_stackvars']))
                        ) {
                            $currentitem[$key] = $value;
                        }
                    }

                    if (isset($copieditem['defined_stackvars'])) {
                        $currentitem['defined_stackvars'] = $copieditem['defined_stackvars'];
                    }
                }

                // Posted vars always overwrite anything in the current session..
                foreach (array_merge($pageVars, $sessionVars) as $var) {
                    $recursive = $var{strlen($var) - 1} == '*';
                    $var = $recursive ? substr($var, 0, -1) : $var;

                    if (isset($postvars[$var]) && $postvars[$var] != '') {
                        if ($postvars[$var] == 'clear') {
                            $currentitem[$var] = '';
                        } else {
                            if ($recursive && is_array($currentitem[$var]) && is_array($postvars[$var])) {
                                $currentitem[$var] = array_merge_recursive($currentitem[$var], $postvars[$var]);
                            } else {
                                $currentitem[$var] = $postvars[$var];
                            }
                        }
                    }
                }
                array_push($stack, $currentitem);
            } else {
                // Stay at the current level..
                // If we are getting back from a higher level, we may now delete everything above
                $deletecount = (count($stack) - 1) - $this->atklevel;
                for ($i = 0; $i < $deletecount; ++$i) {
                    $this->debugger->addDebug('popped an item out of the stack');
                    array_pop($stack);
                }

                foreach ($pageVars as $var) {
                    $recursive = $var{strlen($var) - 1} == '*';
                    $var = $recursive ? substr($var, 0, -1) : $var;

                    if (isset($postvars[$var]) && count($postvars[$var]) > 0 && (!$partial || !in_array($var, $lockedVars))) {
                        if ($recursive && isset($currentitem[$var]) && is_array($currentitem[$var]) && is_array($postvars[$var])) {
                            $currentitem[$var] = Tools::atk_array_merge_recursive($currentitem[$var], $postvars[$var]);
                        } else {
                            $currentitem[$var] = $postvars[$var];
                        }
                    }
                }

                // page vars must overwrite the current stack..
                $stack[$this->atklevel] = &$currentitem;

                // session vars need not be remembered..
                foreach ($sessionVars as $var) {
                    if (isset($postvars[$var]) && count($postvars[$var]) > 0 && (!$partial || !in_array($var, $lockedVars))) {
                        $currentitem[$var] = $postvars[$var];
                    }
                }
            }

            if (isset($currentitem['atkformdata']) && is_array($currentitem['atkformdata'])) {
                $this->debugger->addDebug('Session formdata present');
                foreach ($currentitem['atkformdata'] as $var => $value) {

                    // don't override what was passed in the url.
                    if (!isset($postvars[$var])) {
                        $postvars[$var] = $value;
                    } else {
                        if (is_array($postvars[$var]) && is_array($value)) {
                            // Formdata that was posted earlier needs to be merged with the current
                            // formdata. We use a custom array_merge here to preserve key=>value pairs.
                            $postvars[$var] = Tools::atk_array_merge_keys($value, $postvars[$var]);
                        }
                    }
                }

                // We leave atkformdata in the current stack entry untouched so that
                // when the stack might be forked of whatsoever the form data is still
                // present. However, this data should not be directly accessed in the node!
            }

            if (is_array($currentitem)) {
                foreach ($currentitem as $var => $value) {
                    $recursive = in_array("{$var}*", $pageVars);

                    // don't override what was passed in the url except for
                    // recursive mergeable pagevars
                    if ($recursive || !isset($postvars[$var])) {
                        $postvars[$var] = $value;
                    }
                }
            }
        } // end if atkescape
    }

    /**
     * Verify the integrity of the session stack.
     *
     * Fixes the stack in case a user opens links in a new window, which would
     * normally confuse the session manager. In the case we detect a new
     * window, we fork the session stack so both windows have their own
     * stacks.
     *
     *
     * @return bool stack integrity ok? (false means we created a new stack)
     */
    protected function _verifyStackIntegrity()
    {
        $stack = '';
        $sessionData = &self::getSession();

        if (isset($sessionData[$this->m_namespace]['stack'][$this->atkStackID()])) {
            $stack = $sessionData[$this->m_namespace]['stack'][$this->atkStackID()];
        }
        if (!is_array($stack)) {
            $prevlevelfromstack = 0;
        } else {
            $prevlevelfromstack = count($stack) - 1;
        }

        $oldStackId = $this->atkStackID();

        if ($this->atkprevlevel != $prevlevelfromstack) {
            // What we think we came from (as indicated in the url by atkprevlevel)
            // and what the REAL situation on the stack was when we got here (prevlevelfromstack)
            // is different. Let's fork the stack.
            // @TODO: If an error occurs and forking is required, the rejection info is not forked right, since it is currently stored
            //        in session['atkreject'] and not directly in the stack. See also atk/handlers/class.atkactionhandler.inc.
            $this->debugger->addDebug("Multiple windows detected: levelstack forked (atkprevlevel={$this->atkprevlevel}, real: $prevlevelfromstack)");
            $newid = $this->atkStackID(true);

            // We must also make this stack 'ok' with the atkprevlevel.
            // (there may be more levels on the stack than we should have, because
            // we forked from another window which might already be at a higher
            // stack level).
            $deletecount = (count($stack) - 1) - $this->atkprevlevel;
            for ($i = 0; $i < $deletecount; ++$i) {
                $this->debugger->addDebug('popped an item out of the forked stack');
                array_pop($stack);
            }

            $sessionData[$this->m_namespace]['stack'][$newid] = $stack;

            // Copy the global stackvars for the stack too.
            if (isset($sessionData[$this->m_namespace]['globals']['#STACK#'][$oldStackId])) {
                $sessionData[$this->m_namespace]['globals']['#STACK#'][$newid] = $sessionData[$this->m_namespace]['globals']['#STACK#'][$oldStackId];
            }

            return false;
        }

        return true;
    }
}
