<?php
/**
 * This file is part of the ATK distribution on GitHub.
 * Detailed copyright and licensing information can be found
 * in the doc/COPYRIGHT and doc/LICENSE files which should be
 * included in the distribution.
 *
 * @package atk
 * @subpackage handlers
 *
 * @copyright (c)2000-2004 Ivo Jansch
 * @license http://www.achievo.org/atk/licensing ATK Open Source License
 *
 * @version $Revision: 6310 $
 * $Id$
 */
/**
 * Handler class for the select action of a node. The handler draws a
 * generic select form for searching through the records and selecting
 * multiple records.
 *
 * @author Lineke Kerckhoffs-Willems <lineke@ibuildings.nl>
 * @package atk
 * @subpackage handlers
 *
 */
atkTools::atkimport("atk.handlers.atkadminhandler");

class Atk_MultiSelectHandler extends Atk_AdminHandler
{

    /**
     * The action handler method.
     */
    function action_multiselect()
    {
        if (!empty($this->m_partial)) {
            $this->partial($this->m_partial);
            return;
        }

        if (isset($this->m_postvars['atkselector'])) {
            $output = $this->invoke("handleMultiselect");
        } else
            $output = $this->invoke("multiSelectPage");

        if ($output != "") {
            $page = &$this->getPage();
            $page->addContent($this->m_node->renderActionPage("multiselect", $output));
        }
    }

    /**
     * Parse atkselectors in postvars into atktarget using atktargetvartpl and atktargetvar
     * Then redirect to atktarget
     */
    function handleMultiselect()
    {
        $node = &$this->getNode();
        $columnConfig = &$node->getColumnConfig();
        $recordset = $node->selectDb(implode(' OR ', $this->m_postvars['atkselector']), $columnConfig->getOrderByStatement(), "", $node->m_listExcludes, "", "multiselect");

        // loop recordset to parse atktargetvar
        $atktarget = atkTools::atkurldecode($node->m_postvars['atktarget']);
        $atktargetvar = $node->m_postvars['atktargetvar'];
        $atktargettpl = $node->m_postvars['atktargetvartpl'];

        for ($i = 0; $i < count($recordset); $i++) {
            if ($i == 0 && strpos($atktarget, '&') === false)
                $atktarget.= '?';
            else
                $atktarget .= '&';
            $atktarget.= $atktargetvar . '[]=' . $this->parseString($atktargettpl, $recordset[$i]);
        }
        $node->redirect($atktarget);
    }

    /**
     * Parse the target string
     *
     * @param String $string The string to parse
     * @param Array $recordset The recordset to use for parsing the string
     * @return String The parsed string
     */
    function parseString($string, $recordset)
    {
        atkTools::atkimport("atk.utils.atkstringparser");
        $parser = new Atk_StringParser($string);

        // for backwardscompatibility reasons, we also support the '[pk]' var.
        $recordset['pk'] = $this->getNode()->primaryKey($recordset);
        $output = $parser->parse($recordset, true);
        return $output;
    }

    /**
     * This method returns an html page containing a recordlist to select
     * records from. The recordlist can be searched, sorted etc. like an
     * admin screen.
     *
     * @return String The html select page.
     */
    function multiSelectPage()
    {
        // add the postvars to the form
        global $g_stickyurl;
        $g_stickyurl[] = 'atktarget';
        $g_stickyurl[] = 'atktargetvar';
        $g_stickyurl[] = 'atktargetvartpl';
        $GLOBALS['atktarget'] = $this->getNode()->m_postvars['atktarget'];
        $GLOBALS['atktargetvar'] = $this->getNode()->m_postvars['atktargetvar'];
        $GLOBALS['atktargetvartpl'] = $this->getNode()->m_postvars['atktargetvartpl'];

        $this->getNode()->addStyle("style.css");

        $params["header"] = atkTools::atktext("title_multiselect", $this->getNode()->m_module, $this->getNode()->m_type);

        $actions['actions'] = array();
        $actions['mra'][] = 'multiselect';

        atkTools::atkimport('atk.datagrid.atkdatagrid');
        $grid = atkDataGrid::create($this->getNode(), 'multiselect');
        /**
         * At first the changes below looked like the solution for the error
         * on the contact multiselect page. Except this is not the case, because
         * the MRA actions will not be shown, which is a must.
         */
        if (is_array($actions['actions'])) {
            $grid->setDefaultActions($actions['actions']);
        } else {
            $grid->setDefaultActions($actions);
        }

        $grid->removeFlag(atkDataGrid::EXTENDED_SEARCH);
        $grid->addFlag(atkDataGrid::MULTI_RECORD_ACTIONS);
        $params["list"] = $grid->render();

        if (atkSessionManager::atkLevel() > 0) {
            $backlinkurl = atkSessionManager::sessionUrl(atkTools::atkSelf() . '?atklevel=' . atkSessionManager::newLevel(SESSION_BACK));
            $params["footer"] = '<br><div style="text-align: center"><input type="button" class="btn btn-default" onclick="window.location=\'' . $backlinkurl . '\';" value="' . atkTools::atktext('cancel') . '"></div>';
        }

        $output = $this->getUi()->renderList("multiselect", $params);

        return $this->getUi()->renderBox(array("title" => $this->getNode()->actionTitle('multiselect'),
                "content" => $output));
    }

}

