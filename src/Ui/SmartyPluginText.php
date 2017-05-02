<?php

namespace Sintattica\Atk\Ui;

use Sintattica\Atk\Core\Tools;
use Sintattica\Atk\Utils\StringParser;

/**
 * function to get multilanguage strings.
 *
 * @author Ivo Jansch <ivo@achievo.org>
 *
 * Example: {atktext id="users.userinfo.description"}
 *          {atktext id="userinfo.description" module="users"}
 *          {atktext id="description" module="users" node="userinfo"}
 */
class SmartyPluginText
{

    /** @var  \Sintattica\Atk\Core\Language $language */
    protected $language;

    public function __construct($language)
    {
        $this->language = $language;
    }

    function plugin($params)
    {
        if (!isset($params['id'])) {
            $params['id'] = $params[0];
        }

        switch (substr_count($params['id'], '.')) {
            case 1: {
                list($module, $id) = explode('.', $params['id']);
                $str = $this->language->text($id, $module, isset($params['node']) ? $params['node'] : '');
                break;
            }
            case 2: {
                list($module, $node, $id) = explode('.', $params['id']);
                $str = $this->language->text($id, $module, $node);
                break;
            }
            default:
                $str = $this->language->text($params['id'], Tools::atkArrayNvl($params, 'module', ''), Tools::atkArrayNvl($params, 'node', ''),
                    Tools::atkArrayNvl($params, 'lng', ''));
        }

        if (isset($params['filter'])) {
            $fn = $params['filter'];
            $str = $fn($str);
        }

        // parse the rest of the params in the string
        $parser = new StringParser($str);

        return $parser->parse($params);
    }
}
