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
 * The data grid summary. Can be used to render a 
 * summary for an ATK data grid.
 *
 * @author Peter C. Verhage <peter@achievo.org>
 * @package atk
 * @subpackage datagrid
 */
class Atk_DGSummary extends Atk_DGComponent
{

    /**
     * Renders the summary for the given data grid.
     *
     * @return string rendered HTML
     */
    public function render()
    {
        $grid = $this->getGrid();

        $limit = $grid->getLimit();
        $count = $grid->getCount();

        if ($count == 0) {
            return null;
        }

        if ($limit == -1) {
            $limit = $count;
        }

        $start = $grid->getOffset();
        $end = min($start + $limit, $count);
        $page = floor(($start / $limit) + 1);
        $pages = ceil($count / $limit);

        $string = $grid->text('datagrid_summary');

        $params = array(
            'start' => $start + 1,
            'end' => $end,
            'count' => $count,
            'limit' => $limit,
            'page' => $page,
            'pages' => $pages
        );

        atkTools::atkimport("atk.utils.atkstringparser");
        $parser = new Atk_StringParser($string);
        $result = $parser->parse($params);

        return '<span class="dgridsummary">'.$result.'</span>';
    }

}

