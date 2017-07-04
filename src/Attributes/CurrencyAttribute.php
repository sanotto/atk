<?php

namespace Sintattica\Atk\Attributes;

use Sintattica\Atk\Core\Tools;

/**
 * The CurrencyAttribute can be used for money values.
 *
 * @author Mark Baaijens <mark@ibuildings.nl>
 */
class CurrencyAttribute extends NumberAttribute
{
    public $m_currencysymbol;

    /**
     * Constructor.
     *
     * @param string $name Name of the attribute
     * @param int $flags Flags for this attribute
     * @param string $currencysymbol The symbol which is printed in front of the value.
     * @param int $decimals The number of decimals (default 2)
     * @param string $decimalseparator The separator which is printed for the decimals.
     * @param string $thousandsseparator The separator which is printed for the thousands.
     */
    public function __construct(
        $name,
        $flags = 0,
        $currencysymbol = '',
        $decimals = 2,
        $decimalseparator = '',
        $thousandsseparator = ''
    ) {
        parent::__construct($name, $flags, $decimals);
        $this->setAttribSize(10);

        $this->m_currencysymbol = $currencysymbol;
        $this->m_decimalseparator = ($decimalseparator != '' ? $decimalseparator : '.');
        $this->m_thousandsseparator = ($thousandsseparator != '' ? $thousandsseparator : ',');

        $this->setUseThousandsSeparator(true);
        $this->setTrailingZeros(true);
    }

    public function init()
    {
        if ($this->m_currencysymbol == '') {
            $this->m_currencysymbol = $this->getOwnerInstance()->getLanguage()->trans('currencysymbol', 'atk', '', '', '', true);
        }
    }

    /**
     * overrides the edit function to put the currencysymbol in front of the input field.
     *
     * @param array $record The record that holds the value for this attribute.
     * @param string $fieldprefix The fieldprefix to put in front of the name
     *                            of any html form element for this attribute.
     * @param string $mode The mode we're in ('add' or 'edit')
     *
     * @return string A piece of htmlcode for editing this attribute
     */
    public function edit($record, $fieldprefix, $mode)
    {
        $input = parent::edit($record, $fieldprefix, $mode);
        $currency = trim($this->getCurrencySymbolDisplay());
        $result = $input;

        $size = $this->m_size;
        if ($this->getDecimals() > 0) {
            $size += ($this->getDecimals() + 1);
        }

        if (strlen($currency)) {
            $result = '<div class="input-group" style="width:'.$size.'em;">';
            $result .= $input;
            $result .= '<div class="input-group-addon">'.$currency.'</div>';
            $result .= '</div>';
        }

        return $result;
    }

    /**
     * overrides the display function to put the currencysymbol in front of the input field.
     *
     * The regular Attribute uses PHP's nl2br() and htmlspecialchars()
     * methods to prepare a value for display, unless $mode is "cvs".
     *
     * @param array $record The record that holds the value for this attribute
     * @param string $mode The display mode ("view" for viewpages, or "list"
     *                       for displaying in recordlists, "edit" for
     *                       displaying in editscreens, "add" for displaying in
     *                       add screens. "csv" for csv files. Applications can
     *                       use additional modes.
     *
     * @return string HTML String
     */
    public function display($record, $mode)
    {
        $result = empty($this->m_currencysymbol) ? '' : $this->getCurrencySymbolDisplay().' ';
        $result .= parent::display($record, $mode);

        return $result;
    }

    /**
     * Get currency symbol display.
     *
     * @return string
     */
    public function getCurrencySymbolDisplay()
    {
        return $this->m_currencysymbol;
    }
}
