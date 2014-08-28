<?php
/**
 * This file is part of the ATK distribution on GitHub.
 * Detailed copyright and licensing information can be found
 * in the doc/COPYRIGHT and doc/LICENSE files which should be
 * included in the distribution.
 *
 * @package atk
 * @subpackage themes
 *
 * @copyright (c)2000-2004 Ibuildings.nl BV
 * @license http://www.achievo.org/atk/licensing ATK Open Source License
 *
 * @version $Revision: 6021 $
 * $Id$
 */
/**
 * Theme definition
 */

$theme["recordlist_orientation"] = 'right';
$theme['modern_menu_compat'] = true;
$theme['frame_top_height'] = 149;
$theme['frame_menu_width'] = 210;
$theme['dialog_theme_name'] = 'atkdialog';
$theme['dialog_theme_load'] = false;

$theme["doctype"] = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN"' . "\n" . '"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">';
$theme['compatible_menus'] = array('atk.menu.atkDropdownMenu');
$theme["highlight"] = "";
$theme["select"] = "";
$theme['useframes'] = false;
$theme['dispatcher'] = 'index.php';
$theme['menufile'] = 'index.php';
$theme['menu_params'] = "class='menulink'";
$theme["tabtype"] = "dhtml";

$atkPage = atkPage::getInstance();