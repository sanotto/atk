<?php

namespace Sintattica\Atk\Ui;

use Sintattica\Atk\Core\Atk;
use Sintattica\Atk\Core\Config;
use Sintattica\Atk\Core\Language;
use Sintattica\Atk\Core\Menu;
use Sintattica\Atk\Core\Node;
use Sintattica\Atk\Core\NodeManager;
use Sintattica\Atk\Core\Tools;
use Sintattica\Atk\Security\SecurityManager;
use Sintattica\Atk\Session\SessionManager;
use Sintattica\Atk\Utils\Debugger;

/**
 * Class that generates an index page.
 */
class IndexPage
{
    /*
     * @var Page
     */
    public $m_page;

    /*
     * @var Ui
     */
    public $_ui;

    /*
     * @var Output
     */
    public $m_output;
    public $m_user;
    public $m_title;
    public $m_extrabodyprops;
    public $m_extraheaders;


    /*
     * @var array
     */
    public $m_username;
    public $m_defaultDestination;
    public $m_flags;
    protected $m_menu;
    protected $securityManager;
    protected $language;
    protected $debugger;
    protected $nodeManager;
    protected $sessionManager;

    /**
     * Constructor
     * @param Page $page
     * @param Ui $ui
     * @param Output $output
     * @param Menu $menu
     * @param SecurityManager $securityManager
     * @param Language $language
     * @param Debugger $debugger
     * @param NodeManager $nodeManager
     * @param SessionManager $sessionManager
     * @return IndexPage
     */
    public function __construct(Page $page, Ui $ui, Output $output, Menu $menu, SecurityManager $securityManager, Language $language, Debugger $debugger, NodeManager $nodeManager, SessionManager $sessionManager)
    {
        $this->m_page = $page;
        $this->m_ui = $ui;
        $this->m_output = $output;
        $this->m_menu = $menu;
        $this->securityManager = $securityManager;
        $this->language = $language;
        $this->debugger = $debugger;
        $this->nodeManager = $nodeManager;
        $this->sessionManager = $sessionManager;
        $this->m_user = $securityManager->atkGetUser();
        $this->m_flags = array_key_exists('atkpartial', Atk::$ATK_VARS) ? Page::HTML_PARTIAL : Page::HTML_STRICT;
    }

    /**
     * Does the IndexPage has this flag?
     *
     * @param int $flag The flag
     *
     * @return bool
     */
    public function hasFlag($flag)
    {
        return Tools::hasFlag($this->m_flags, $flag);
    }

    /**
     * Generate the page.
     */
    public function generate()
    {
        if (!$this->hasFlag(Page::HTML_PARTIAL)) {
            $user = $this->m_username ?: $this->m_user['name'];

            if (Config::getGlobal('menu_show_user') && $user) {
                $this->m_menu->addMenuItem($user, '', 'main', true, 0, '', '', 'right', true);
            }

            if (Config::getGlobal('menu_show_logout_link') && $user) {
                $this->m_menu->addMenuItem('<span class="glyphicon glyphicon-log-out"></span>',
                    Config::getGlobal('dispatcher').'?atklogout=1', 'main', true, 0, '', '', 'right', true
                );
            }

            $top = $this->m_ui->renderBox(array(
                'title' => ($this->m_title != '' ?: $this->language->trans('app_title')),
                'app_title' => $this->language->trans('app_title'),
                'menu' => $this->m_menu->getMenu(),
            ), 'top');
            $this->m_page->addContent($top);
        }

        $this->atkGenerateDispatcher();

        $title = $this->m_title != '' ?: null;
        $bodyprops = $this->m_extrabodyprops != '' ?: null;
        $headers = $this->m_extraheaders != '' ?: null;
        $content = $this->m_page->render($title, $this->m_flags, $bodyprops, $headers);

        $this->m_output->output($content);
        $this->m_output->outputFlush();
    }

    /**
     * Set the title of the page.
     *
     * @param string $title
     */
    public function setTitle($title)
    {
        $this->m_title = $title;
    }

    /**
     * Set the extra body properties of the page.
     *
     * @param string $extrabodyprops
     */
    public function setBodyprops($extrabodyprops)
    {
        $this->m_extrabodyprops = $extrabodyprops;
    }

    /**
     * Set the extra headers of the page.
     *
     * @param string $extraheaders
     */
    public function setExtraheaders($extraheaders)
    {
        $this->m_extraheaders = $extraheaders;
    }

    /**
     * Set the username.
     *
     * @param string $username
     */
    public function setUsername($username)
    {
        $this->m_username = $username;
    }

    /**
     * Generate the dispatcher.
     */
    public function atkGenerateDispatcher()
    {
        $session = &$this->sessionManager->getSession();

        if ($session['login'] != 1) {
            // no nodetype passed, or session expired

            $destination = '';
            if (isset(Atk::$ATK_VARS['atknodeuri']) && isset(Atk::$ATK_VARS['atkaction'])) {
                $destination = '&atknodeuri='.Atk::$ATK_VARS['atknodeuri'].'&atkaction='.Atk::$ATK_VARS['atkaction'];
                if (isset(Atk::$ATK_VARS['atkselector'])) {
                    $destination .= '&atkselector='.Atk::$ATK_VARS['atkselector'];
                }
            }

            $box = $this->m_ui->renderBox(array(
                'title' => $this->language->trans('title_session_expired'),
                'content' => '<br><br>'.$this->language->trans('explain_session_expired').'<br><br><br><br>
                                           <a href="'.Config::getGlobal('dispatcher').'?atklogout=true'.$destination.'" target="_top">'.$this->language->trans('relogin').'</a><br><br>',
            ));

            $this->m_page->addContent($box);

            $this->m_output->output($this->m_page->render($this->language->trans('title_session_expired'), true));
        } else {

            // Create node
            if (isset(Atk::$ATK_VARS['atknodeuri'])) {
                $node = $this->nodeManager->getNode(Atk::$ATK_VARS['atknodeuri']);
                $this->loadDispatchPage(Atk::$ATK_VARS, $node);
            } else {
                if (is_array($this->m_defaultDestination)) {
                    // using dispatch_url to redirect to the node
                    $isIndexed = array_values($this->m_defaultDestination) === $this->m_defaultDestination;
                    if ($isIndexed) {
                        $destination = Tools::dispatch_url($this->m_defaultDestination[0], $this->m_defaultDestination[1],
                            $this->m_defaultDestination[2] ? $this->m_defaultDestination[2] : array());
                    } else {
                        $destination = Tools::dispatch_url($this->m_defaultDestination['atknodeuri'], $this->m_defaultDestination['atkaction'],
                            $this->m_defaultDestination[0] ? $this->m_defaultDestination[0] : array());
                    }
                    header('Location: '.$destination);
                    exit;
                } else {
                    $this->renderContent();
                }
            }
        }
    }

    public function renderContent()
    {
        $content = $this->getContent();
        $this->m_page->addContent('<div class="container-fluid">'.$content.'</div>');
    }

    public function getContent()
    {
        $box = $this->m_ui->renderBox([
            'title' => $this->language->trans('app_shorttitle'),
            'content' => $this->language->trans('app_description'),

        ]);

        return $box;
    }

    /**
     * Set the default destination.
     *
     * @param array $destination The default destination
     */
    public function setDefaultDestination($destination)
    {
        if (is_array($destination)) {
            $this->m_defaultDestination = $destination;
        }
    }

    /**
     * Does the actual loading of the dispatch page
     * And adds it to the page for the dispatch() method to render.
     *
     * @param array $postvars The request variables for the node.
     * @param Node $node
     */
    public function loadDispatchPage($postvars, Node $node)
    {
        $node->m_postvars = $postvars;
        $node->m_action = $postvars['atkaction'];
        if (isset($postvars['atkpartial'])) {
            $node->m_partial = $postvars['atkpartial'];
        }

        $page = $node->getPage();
        $page->setTitle($this->language->trans('app_shorttitle').' - '.$node->getUi()->nodeTitle($node, $node->m_action));

        if ($node->allowed($node->m_action)) {
            $this->securityManager->logAction($node->m_type, $node->m_action);
            $node->callHandler($node->m_action);
            $id = '';

            if (isset($node->m_postvars['atkselector']) && is_array($node->m_postvars['atkselector'])) {
                $atkSelectorDecoded = [];

                foreach ($node->m_postvars['atkselector'] as $rowIndex => $selector) {
                    list(, $pk) = explode('=', $selector);
                    $atkSelectorDecoded[] = $pk;
                    $id = implode(',', $atkSelectorDecoded);
                }
            } else {
                list(, $id) = explode('=', Tools::atkArrayNvl($node->m_postvars, 'atkselector', '='));
            }

            $page->register_hiddenvars(array(
                'atknodeuri' => $node->m_module.'.'.$node->m_type,
                'atkselector' => str_replace("'", '', $id),
            ));
        } else {
            $page->addContent($this->accessDeniedPage($node->getType()));
        }
    }


    /**
     * Render a generic access denied page.
     *
     * @param string $nodeType
     *
     * @return string A complete html page with generic access denied message.
     */
    private function accessDeniedPage($nodeType)
    {
        $content = '<br><br>'.$this->language->trans('error_node_action_access_denied', '', $nodeType).'<br><br><br>';

        $blocks = [
            $this->m_ui->renderBox(array(
                'title' => $this->language->trans('access_denied'),
                'content' => $content,
            )),
        ];

        return $this->m_ui->render('actionpage.tpl', array('blocks' => $blocks, 'title' => $this->language->trans('access_denied')));
    }
}
