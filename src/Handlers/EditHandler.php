<?php namespace Sintattica\Atk\Handlers;

use Sintattica\Atk\Core\Controller;
use Sintattica\Atk\Core\Tools;
use Sintattica\Atk\Core\Config;
use Sintattica\Atk\Utils\JSON;
use Sintattica\Atk\Ui\Theme;
use Sintattica\Atk\Core\Node;
use Sintattica\Atk\Session\SessionManager;

/**
 * Handler class for the edit action of a node. The handler draws a
 * generic edit form for the given node.
 *
 * @author Ivo Jansch <ivo@achievo.org>
 * @author Peter C. Verhage <peter@ibuildings.nl>
 * @package atk
 * @subpackage handlers
 *
 */
class EditHandler extends ViewEditBase
{
    var $m_dialogSaveUrl = null;
    var $m_buttonsource = null;

    /**
     * Update action.
     *
     * @var string
     */
    private $m_updateAction = 'update';
    private $m_updateSessionStatus = SessionManager::SESSION_NESTED;

    /**
     * The action handler method.
     */
    function action_edit()
    {
        if (!empty($this->m_partial)) {
            $this->partial($this->m_partial);
            return;
        }

        $node = $this->m_node;

        $record = $this->getRecord();

        if ($record === null) {
            $location = $node->feedbackUrl("edit", self::ACTION_FAILED, $record);
            $node->redirect($location);
        }

        // allowed to edit record?
        if (!$this->allowed($record)) {
            $this->renderAccessDeniedPage();
            return;
        }

        $record = $this->mergeWithPostvars($record);

        $this->notify("edit", $record);
        if ($node->hasFlag(Node::NF_LOCK)) {
            if ($node->m_lock->lock($node->primaryKey($record), $node->m_table, $node->getLockMode())) {
                $res = $this->invoke("editPage", $record, true);
            } else {
                $res = $node->lockPage();
            }
        } else {
            $res = $this->invoke("editPage", $record, false);
        }

        $page = $this->getPage();
        $page->addContent($node->renderActionPage("edit", $res));
    }

    /**
     * Returns the update action, which is called when posting the edit form.
     *
     * Defaults to the 'update' action.
     *
     * @return string update action
     */
    public function getUpdateAction()
    {
        return $this->m_updateAction;
    }

    /**
     * Sets the update action which should be called when posting the edit form.
     *
     * @param string $action action name
     */
    public function setUpdateAction($action)
    {
        $this->m_updateAction = $action;
    }

    /**
     * check if there are postvars set that overwrite the record contents, this can
     * happen when a new selection is made using the select handler etc.
     *
     * @param array $record The record
     * @return array Record The merged record
     */
    function mergeWithPostvars($record)
    {
        $fetchedRecord = $this->m_node->updateRecord('', null, null, true);

        /*
         * If any of the attributes is set to need a reload, we don't merge
         * with te postvars for that attribute
         */
        foreach ($fetchedRecord as $attrName => $value) {
            if ($attr = $this->m_node->getAttribute($attrName)) {
                if ($attr->needsReload($record)) {
                    unset($fetchedRecord[$attrName]);
                }
            }
        }

        if (is_array($record)) {
            $record = array_merge($record, $fetchedRecord);
        }

        return $record;
    }

    /**
     * Register external files
     *
     */
    function registerExternalFiles()
    {
        $page = $this->getPage();
        $ui = $this->getUi();
        $page->register_script(Config::getGlobal("assets_url") . "javascript/tools.js");
        $page->register_script(Config::getGlobal("assets_url") . "javascript/formfocus.js");
        $page->register_loadscript("placeFocus();");
        $page->register_script(Config::getGlobal("assets_url") . "javascript/dhtml_formtools.js");
    }

    /**
     * Render the edit page
     *
     * @param array $record The record to edit
     * @param Bool $locked Indicates whether the record is locked by the
     *                        current user.
     * @return String HTML code for the edit page
     */
    function editPage($record, $locked = false)
    {
        $result = $this->getEditPage($record, $locked);

        if ($result !== false) {
            return $result;
        }
    }

    /**
     * Get the params for the edit page
     *
     * @param array $record The record to edit
     * @param Bool $locked Indicates whether the record is locked by the
     *                        current user.
     * @return array Array with parameters
     */
    function getEditParams($record, $locked = false)
    {
        $node = $this->m_node;
        $ui = $node->getUi();

        if (!is_object($ui)) {
            Tools::atkerror("ui object failure");
            return false;
        }

        $params = $node->getDefaultActionParams($locked);
        $params['title'] = $node->actionTitle('edit', $record);
        $params["formstart"] = $this->getFormStart();
        $params["header"] = $this->invoke("editHeader", $record);
        $params["content"] = $this->getContent($record);
        $params["buttons"] = $this->getFormButtons($record);
        $params["formend"] = $this->getFormEnd();
        return $params;
    }

    /**
     * This method draws a generic edit-page for a given record.
     *
     * @param array $record The record to edit.
     * @param boolean $locked Indicates whether the record is locked by the
     *                        current user.
     * @return String The rendered page as a string.
     */
    function getEditPage($record, $locked = false)
    {
        $this->registerExternalFiles();

        $params = $this->getEditParams($record, $locked);

        if ($params === false) {
            return false;
        }

        return $this->renderEditPage($record, $params);
    }

    /**
     * Get the content
     *
     * @param array $record
     * @return String The content
     */
    function getContent($record)
    {
        $node = $this->m_node;

        $forceList = array();
        if (isset($node->m_postvars['atkfilter'])) {
            $forceList = Tools::decodeKeyValueSet($node->m_postvars['atkfilter']);
        }

        $suppressList = array();
        if (isset($node->m_postvars['atksuppress'])) {
            $suppressList = $node->m_postvars['atksuppress'];
        }

        $form = $this->editForm("edit", $record, $forceList, $suppressList, $node->getEditFieldPrefix());

        return $node->tabulate("edit", $form);
    }

    /**
     * Render the edit page
     *
     * @param array $record
     * @param array $params
     * @return String The rendered edit page
     */
    function renderEditPage($record, $params)
    {
        $node = $this->m_node;
        $ui = &$node->getUi();

        if (is_object($ui)) {
            $this->getPage()->setTitle(Tools::atktext('app_shorttitle') . " - " . $node->actionTitle('edit',
                    $record));

            $output = $ui->renderAction("edit", $params, $node->m_module);
            $this->addRenderBoxVar("title", $node->actionTitle('edit', $record));
            $this->addRenderBoxVar("content", $output);

            if ($this->getRenderMode() == "dialog") {
                $total = $ui->renderDialog($this->m_renderBoxVars);
            } else {
                $total = $ui->renderBox($this->m_renderBoxVars, $this->m_boxTemplate);
            }

            return $total;
        }
    }

    /**
     * Returns the current update session status.
     *
     * @see EditHandler::setUpdateSessionStatus
     *
     * @return int session status
     */
    public function getUpdateSessionStatus()
    {
        return $this->m_updateSessionStatus;
    }

    /**
     * Sets the session status in which the update action gets executed.
     * By default the update action is called nested in the session stack.
     *
     * @param int $sessionStatus session status (e.g. SessionManager::SESSION_NESTED, SessionManager::SESSION_DEFAULT etc.)
     */
    public function setUpdateSessionStatus($sessionStatus)
    {
        $this->m_updateSessionStatus = $sessionStatus;
    }

    /**
     * Get the start of the form.
     *
     * @return String HTML The forms' start
     */
    function getFormStart()
    {
        $controller = Controller::getInstance();
        $controller->setNode($this->m_node);
        $sm = SessionManager::getInstance();

        $formIdentifier = ((isset($this->m_partial) && $this->m_partial != "")) ? "dialogform"
            : "entryform";
        $formstart = '<form id="' . $formIdentifier . '" name="' . $formIdentifier . '" enctype="multipart/form-data" action="' . $controller->getPhpFile() . '?' . SID . '"' .
            ' method="post" onsubmit="return globalSubmit(this,false)" class="form-horizontal" role="form" autocomplete="off">' .
            $sm->formState($this->getUpdateSessionStatus());

        $formstart .= '<input type="hidden" name="' . $this->getNode()->getEditFieldPrefix() . 'atkaction" value="' . $this->getUpdateAction() . '" />';
        $formstart .= '<input type="hidden" name="' . $this->getNode()->getEditFieldPrefix() . 'atkprevaction" value="' . $this->getNode()->m_action . '" />';
        $formstart .= '<input type="hidden" name="' . $this->getNode()->getEditFieldPrefix() . 'atkcsrftoken" value="' . $this->getCSRFToken() . '" />';
        $formstart .= '<input type="hidden" class="atksubmitaction" />';

        $formstart .= $controller->getHiddenVarsString();

        return $formstart;
    }

    /**
     * Get the end of the form.
     *
     * @return String HTML The forms' end
     */
    function getFormEnd()
    {
        return '</form>';
    }

    /**
     * Get the buttons for the current action form.
     *
     * @param array $record
     * @return array Array with buttons
     */
    function getFormButtons($record = null)
    {
        if ($this->m_partial == 'dialog' || $this->m_partial == 'editdialog') {
            $controller = Controller::getInstance();
            $result = array();
            $result[] = $controller->getDialogButton('save', null, $this->getDialogSaveUrl(),
                $this->getDialogSaveParams());
            $result[] = $controller->getDialogButton('cancel');
            return $result;
        }

        // If no custom button source is given, get the default Controller.
        if ($this->m_buttonsource === null) {
            $this->m_buttonsource = $this->m_node;
        }

        return $this->m_buttonsource->getFormButtons("edit", $record);
    }

    /**
     * Create template field array for the given edit field.
     *
     * @param array $fields all fields
     * @param int $index field index
     * @param string $mode mode (add/edit)
     * @param string $tab active tab
     *
     * @return array template field
     */
    function createTplField(&$fields, $index, $mode, $tab)
    {
        $field = &$fields[$index];

        // visible sections, both the active sections and the tab names (attribute that are
        // part of the anonymous section of the tab)
        $visibleSections = array_merge($this->m_node->getActiveSections($tab, $mode), $this->m_node->getTabs($mode));

        $tplfield = array();

        $classes = isset($field['class']) ? explode(" ", $field['class']) : array();
        if ($field["sections"] == "*") {
            $classes[] = "alltabs";
        } else {
            if ($field["html"] == "section") {
                // section should only have the tab section classes
                foreach ($field["tabs"] as $section) {
                    $classes[] = "section_" . str_replace('.', '_', $section);
                }
                if ($this->isSectionInitialHidden($field['name'], $fields)) {
                    $classes[] = "atkAttrRowHidden";
                }
            } else {
                if (is_array($field["sections"])) {
                    foreach ($field["sections"] as $section) {
                        $classes[] = "section_" . str_replace('.', '_', $section);
                    }
                }
            }
        }

        if (isset($field["initial_hidden"]) && $field["initial_hidden"]) {
            $classes[] = "atkAttrRowHidden";
        }

        $tplfield["class"] = implode(" ", $classes);
        $tplfield["tab"] = $tplfield["class"]; // for backwards compatibility
        // Todo fixme: initial_on_tab kan er uit, als er gewoon bij het opstarten al 1 keer showTab aangeroepen wordt (is netter dan aparte initial_on_tab check)
        // maar, let op, die showTab kan pas worden aangeroepen aan het begin.
        $tplfield["initial_on_tab"] = ($field["tabs"] == "*" || in_array($tab, $field["tabs"])) &&
            (!is_array($field["sections"]) || count(array_intersect($field['sections'], $visibleSections)) > 0);

        // ar_ stands voor 'attribrow'.
        $tplfield["rowid"] = "ar_" . ($field['id'] != '' ? $field['id'] : Tools::getUniqueID("anonymousattribrows")); // The id of the containing row
        // check for separator
        if ($field["html"] == "-" && $index > 0 && $fields[$index - 1]["html"] != "-") {
            $tplfield["type"] = "line";
            $tplfield["line"] = "<hr>";
        } /* double separator, ignore */ elseif ($field["html"] == "-") {

        } /* sections */ elseif ($field["html"] == "section") {
            $tplfield["type"] = "section";
            list($tab, $section) = explode('.', $field["name"]);
            $tplfield["section_name"] = "section_{$tab}_{$section}";
            $tplfield["line"] = $this->getSectionControl($field, $mode);
        } /* only full HTML */ elseif (isset($field["line"])) {
            $tplfield["type"] = "custom";
            $tplfield["line"] = $field["line"];
        } /* edit field */ else {
            $tplfield["type"] = "attribute";

            if ($field["attribute"]->m_ownerInstance->getNumbering()) {
                $this->_addNumbering($field, $tplfield, $index);
            }

            /* does the field have a label? */
            if ((isset($field["label"]) && $field["label"] !== "Attribute::AF_NO_LABEL") || !isset($field["label"])) {
                if (!isset($field["label"]) || empty($field["label"])) {
                    $tplfield["label"] = "";
                } else {
                    $tplfield["label"] = $field["label"];
                    if ($field["error"]) { // TODO KEES
                        $tplfield["error"] = $field["error"];
                    }
                }
            } else {
                $tplfield["label"] = "Attribute::AF_NO_LABEL";
            }

            /* obligatory indicator */
            if ($field["obligatory"]) {
                // load images
                $theme = Theme::getInstance();
                $reqimg = '<img align="top" src="' . $theme->imgPath("required_field.gif") . '" border="0"
                     alt="' . Tools::atktext("field_obligatory") . '" title="' . Tools::atktext("field_obligatory") . '">';

                $tplfield["label"];
                $tplfield["obligatory"] = $reqimg;
            }

            // Make the attribute and node names available in the template.
            $tplfield['attribute'] = $field["attribute"]->fieldName();
            $tplfield['node'] = $field["attribute"]->m_ownerInstance->atkNodeType();

            /* html source */
            $tplfield["widget"] = $field["html"];
            $editsrc = $field["html"];

            /* tooltip */
            $tooltip = $field["attribute"]->getToolTip();
            if ($tooltip) {
                $tplfield["tooltip"] = $tooltip;
                $editsrc .= $tooltip . "&nbsp;";
            }

            $tplfield['id'] = str_replace('.', '_', $this->m_node->atknodetype() . '_' . $field["id"]);

            $tplfield["full"] = $editsrc;

            $column = $field['attribute']->getColumn();
            $tplfield["column"] = $column;

            $tplfield['readonly'] = $field['attribute']->isReadonlyEdit($mode);

        }

        // allow passing of extra arbitrary data, for example if a user overloads the editArray method
        // to pass custom extra data per attribute to the template
        if (isset($field['extra'])) {
            $tplfield['extra'] = $field['extra'];
        }

        return $tplfield;
    }

    /**
     * Function returns a generic html form for editing a record.
     *
     * @param string $mode The edit mode ("add" or "edit").
     * @param array $record The record to edit.
     * @param array $forceList A key-value array used to preset certain
     *                             fields to a certain value.
     * @param array $suppressList An array of fields that will be hidden.
     * @param string $fieldprefix If set, each form element is prefixed with
     *                             the specified prefix (used in embedded
     *                             forms)
     * @param string $template The template to use for the edit form
     * @param boolean $ignoreTab Ignore the tabs an attribute should be shown on.
     *
     * @return String the edit form as a string
     */
    function editForm(
        $mode = "add",
        $record = null,
        $forceList = "",
        $suppressList = "",
        $fieldprefix = "",
        $template = "",
        $ignoreTab = false
    ) {
        $node = $this->m_node;

        /* get data, transform into form, return */
        $data = $node->editArray($mode, $record, $forceList, $suppressList, $fieldprefix, $ignoreTab);
        // Format some things for use in tpl.
        /* check for errors and display them */
        $tab = $node->getActiveTab();
        $error_title = "";
        $pk_err_attrib = array();
        $tabs = $node->getTabs($node->m_action);

        // Handle errors
        $errors = array();
        if (count($data['error']) > 0) {
            $error_title = '<b>' . Tools::atktext('error_formdataerror') . '</b>';

            foreach ($data["error"] as $error) {
                if ($error['err'] == "error_primarykey_exists") {
                    $pk_err_attrib[] = $error['attrib_name'];
                } else {
                    $type = (empty($error["node"]) ? $node->m_type : $error["node"]);

                    if (count($tabs) > 1 && $error["tab"]) {
                        $tabLink = $this->getTabLink($node, $error);
                        $error_tab = ' (' . Tools::atktext("error_tab") . ' ' . $tabLink . ' )';
                    } else {
                        $tabLink = null;
                        $error_tab = "";
                    }

                    if (is_array($error['label'])) {
                        $label = implode(', ', $error['label']);
                    } else {
                        if (!empty($error['label'])) {
                            $label = $error['label'];
                        } else {
                            if (!is_array($error['attrib_name'])) {
                                $label = $node->text($error['attrib_name']);
                            } else {
                                $label = array();
                                foreach ($error['attrib_name'] as $attrib) {
                                    $label[] = $node->text($attrib);
                                }

                                $label = implode(", ", $label);
                            }
                        }
                    }

                    /* Error messages should be rendered in templates using message, label and the link to the tab. */
                    $err = array("message" => $error['msg'], "tablink" => $tabLink, "label" => $label);

                    /**
                     * @deprecated: For backwards compatibility, we still support the msg variable as well.
                     * Although the message, tablink variables should be used instead of msg and tab.
                     */
                    $err = array_merge($err, array("msg" => $error['msg'] . $error_tab));

                    $errors[] = $err;
                }
            }
            $pk_err_msg = '';
            if (count($pk_err_attrib) > 0) { // Make primary key error message
                $pk_err_msg = '';
                for ($i = 0; $i < count($pk_err_attrib); $i++) {
                    $pk_err_msg .= Tools::atktext($pk_err_attrib[$i], $node->m_module, $node->m_type);
                    if (($i + 1) < count($pk_err_attrib)){
                        $pk_err_msg .= ", ";
                    }
                }
                $errors[] = array("label" => Tools::atktext("error_primarykey_exists"), "message" => $pk_err_msg);
            }
        }

        /* display the edit fields */
        $fields = array();
        $errorFields = array();
        $attributes = array();

        for ($i = 0, $_i = count($data["fields"]); $i < $_i; $i++) {
            $field = &$data["fields"][$i];

            $tplfield = $this->createTplField($data["fields"], $i, $mode, $tab);
            $fields[] = $tplfield; // make field available in numeric array
            $params[$field["name"]] = $tplfield; // make field available in associative array
            $attributes[$field["name"]] = $tplfield; // make field available in associative array

            if ($field['error']) {
                $errorFields[] = $field['id'];
            }
        }

        $ui = $this->getUi();
        $page = $this->getPage();
        $page->register_script(Config::getGlobal("assets_url") . "javascript/formsubmit.js");

        // register fields that contain errornous values
        $page->register_scriptcode("var atkErrorFields = " . JSON::encode($errorFields) . ";");

        if (Config::getGlobal('lose_changes_warning', true)) {
            // If we are in the save or update action the user has added a nested record, has done
            // a selection using the select handler or generated an error, in either way we assume
            // the form has been changed, so we always warn the user when leaving the page.
            $isChanged = 'false';
            if ((isset($record['atkerror']) && count($record['atkerror']) > 0) ||
                (isset($this->m_node->m_postvars['__atkunloadhelper']) && $this->m_node->m_postvars['__atkunloadhelper'])
            ) {
                $isChanged = 'true';
            }

            $unloadText = addslashes($this->m_node->text('lose_changes_warning'));
            $page->register_script(Config::getGlobal("assets_url") . "javascript/class.atkunloadhelper.js");
            $page->register_loadscript("new ATK.UnloadHelper('entryform', '{$unloadText}', {$isChanged});");
        }

        $result = "";

        foreach ($data["hide"] as $hidden) {
            $result .= $hidden;
        }

        $params["activeTab"] = $tab;
        $params["fields"] = $fields; // add all fields as a numeric array.
        $params["attributes"] = $attributes; // add all fields as an associative array

        $params["errortitle"] = $error_title;
        $params["errors"] = $errors; // Add the list of errors.
        Tools::atkdebug("Render editform - $template");
        if ($template) {
            $result .= $ui->render($template, $params);
        } else {
            $theme = Theme::getInstance();
            if ($theme->tplPath("editform_common.tpl") > "") {
                $tabTpl = $this->_getTabTpl($node, $tabs, $mode, $record);
                $params['fieldspart'] = $this->_renderTabs($fields, $tabTpl);
                $result .= $ui->render("editform_common.tpl", $params);
            } else {
                $result .= $ui->render($node->getTemplate($mode, $record, $tab), $params);
            }
        }

        return $result;
    }

    /**
     * Get the link fo a tab
     *
     * @param Node $node The node
     * @param array $error
     * @return String HTML code with link
     */
    function getTabLink(&$node, $error)
    {
        if (count($node->getTabs($node->m_action)) < 2) {
            return '';
        }
        return '<a href="javascript:void(0)" onclick="showTab(\'' . $error["tab"] . '\'); return false;">' . $this->getTabLabel($node,
            $error["tab"]) . '</a>';
    }

    /**
     * Overrideable function to create a header for edit mode.
     * Similar to the admin header functionality.
     */
    function editHeader()
    {
        return "";
    }

    /**
     * The edit dialog
     *
     * @return String The edit dialog
     */
    function partial_dialog()
    {
        return $this->renderEditDialog();
    }

    /**
     * Render add dialog.
     *
     * @param array $record
     * @return string html
     */
    function renderEditDialog($record = null)
    {
        if ($record == null) {
            $record = $this->getRecord();
        }

        $this->setRenderMode('dialog');
        $result = $this->m_node->renderActionPage("edit", $this->invoke("editPage", $record));
        return $result;
    }

    /**
     * Override the default dialog save URL.
     *
     * @param string $url dialog save URL
     */
    function setDialogSaveUrl($url)
    {
        $this->m_dialogSaveUrl = $url;
    }

    /**
     * Returns the dialog save URL.
     *
     * @return string dialog save URL
     */
    function getDialogSaveUrl()
    {
        if ($this->m_dialogSaveUrl != null) {
            return $this->m_dialogSaveUrl;
        } else {
            return Tools::partial_url($this->m_node->atkNodeType(), 'update', 'dialog');
        }
    }

    /**
     * Returns the dialog save params. These are the same params that are part of the
     * dialog save url, but they will be appended at the end of the query string to
     * override any form variables with the same name!
     */
    function getDialogSaveParams()
    {
        $parts = parse_url($this->getDialogSaveUrl());
        $query = $parts['query'];
        $params = array();
        parse_str($query, $params);
        return $params;
    }

}

