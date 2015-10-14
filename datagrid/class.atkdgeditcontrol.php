<?php
/**
 * This file is part of the ATK distribution on GitHub.
 * Detailed copyright and licensing information can be found
 * in the doc/COPYRIGHT and doc/LICENSE files which should be
 * included in the distribution.
 *
 * @package atk
 * @subpackage utils
 *
 * @copyright (c) 2000-2007 Ibuildings.nl BV
 * 
 * @license http://www.achievo.org/atk/licensing ATK Open Source License
 */
atkTools::atkimport('atk.datagrid.atkdgcomponent');

/**
 * The data grid no records found message. Can be used to render a 
 * simple message underneath the grid stating there are no records 
 * found in the database.
 *
 * @author Peter C. Verhage <peter@achievo.org>
 * @package atk
 * @subpackage datagrid
 */
class Atk_DGEditControl extends Atk_DGComponent
{

    /**
     * Renders the no records found message for the given data grid.
     *
     * @param atkDataGrid $grid the data grid
     * @return string rendered HTML
     */
    public function render()
    {
        if (count($this->getGrid()->getRecords()) == 0 ||
            count($this->getNode()->m_editableListAttributes) == 0) {
            return null;
        }

        if ($this->getGrid()->getPostvar('atkgridedit', false)) {
            $call = $this->getGrid()->getUpdateCall(array('atkgridedit' => 0));
            return '<a href="javascript:void(0)" onclick="' . htmlentities($call) . '">' . $this->getGrid()->text('cancel_edit') . '</a>';
        } else {
            $call = $this->getGrid()->getUpdateCall(array('atkgridedit' => 1));
            return '<a href="javascript:void(0)" onclick="' . htmlentities($call) . '">' . $this->getGrid()->text('edit') . '</a>';
        }
    }

}
