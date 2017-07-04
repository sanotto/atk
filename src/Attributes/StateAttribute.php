<?php

namespace Sintattica\Atk\Attributes;

/**
 * The StateAttribute class represents an attribute to handle United States in a listbox.
 * It extends ListAttribute.
 *
 * @author Kevin Lwinmoe <kevin.lwinmoe@gmail.com>
 */
class StateAttribute extends ListAttribute
{
    public $m_state = [];
    public $m_states = [];
    public $m_usa_states = array(
        'AL',
        'AK',
        'AS',
        'AZ',
        'AR',
        'CA',
        'CO',
        'CT',
        'DE',
        'DC',
        'FM',
        'FL',
        'GA',
        'GU',
        'HI',
        'ID',
        'IL',
        'IN',
        'IA',
        'KS',
        'KY',
        'LA',
        'ME',
        'MH',
        'MD',
        'MA',
        'MI',
        'MN',
        'MS',
        'MO',
        'MT',
        'NE',
        'NV',
        'NH',
        'NJ',
        'NM',
        'NY',
        'NC',
        'ND',
        'MP',
        'OH',
        'OK',
        'OR',
        'PW',
        'PA',
        'PR',
        'RI',
        'SC',
        'SD',
        'TN',
        'TX',
        'UT',
        'VT',
        'VI',
        'VA',
        'WA',
        'WV',
        'WI',
        'WY',
    );
    public $m_defaulttocurrent = true;

    /**
     * Constructor
     * <b>Example:</b>
     * $this->add(new StateAttribute("state_abbrev", self::AF_OBLIGATORY | self::AF_SEARCHABLE));
     * state_abbrev is the database attribute that holds state abbrevation data as AK,CA,NY
     * It will display the full state name.
     *
     * @param string $name Name of the attribute
     * @param int $flags Flags for the attribute
     * @param string $switch Choose usa for USA states
     * @param bool $defaulttocurrent Set the default selected state to the
     *                                 current state based on the atk language
     */
    public function __construct($name, $flags = 0, $switch = 'usa', $defaulttocurrent = true)
    {
        $flags = $flags | self::AF_NO_TRANSLATION;
        $this->fillStateArray();
        $valueArray = $this->getStateValueArray($switch);
        $optionsArray = $this->getStateOptionArray($switch);

        $this->m_defaulttocurrent = $defaulttocurrent;
        parent::__construct($name, $flags, $optionsArray, $valueArray);
    }

    /**
     * Returns a piece of html code that can be used in a form to edit this
     * attribute's value.
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
        if ($this->m_defaulttocurrent && !$record[$this->fieldName()]) {
            $record[$this->fieldName()] = strtoupper($this->getOwnerInstance()->getLanguage()->getLanguageCode());
        }

        return parent::edit($record, $fieldprefix, $mode);
    }

    /**
     * Fill the state array.
     */
    public function fillStateArray()
    {
        $this->m_state['AL']['en'] = 'ALABAMA';
        $this->m_state['AK']['en'] = 'ALASKA';
        $this->m_state['AS']['en'] = 'AMERICAN SAMOA';
        $this->m_state['AZ']['en'] = 'ARIZONA';
        $this->m_state['AR']['en'] = 'ARKANSAS';
        $this->m_state['CA']['en'] = 'CALIFORNIA';
        $this->m_state['CO']['en'] = 'COLORADO';
        $this->m_state['CT']['en'] = 'CONNECTICUT';
        $this->m_state['DE']['en'] = 'DELAWARE';
        $this->m_state['DC']['en'] = 'DISTRICT OF COLUMBIA';
        $this->m_state['FM']['en'] = 'FEDERATED STATES OF MICRONESIA';
        $this->m_state['FL']['en'] = 'FLORIDA';
        $this->m_state['GA']['en'] = 'GEORGIA';
        $this->m_state['GU']['en'] = 'GUAM';
        $this->m_state['HI']['en'] = 'HAWAII';
        $this->m_state['ID']['en'] = 'IDAHO';
        $this->m_state['IL']['en'] = 'ILLINOIS';
        $this->m_state['IN']['en'] = 'INDIANA';
        $this->m_state['IA']['en'] = 'IOWA';
        $this->m_state['KS']['en'] = 'KANSAS';
        $this->m_state['KY']['en'] = 'KENTUCKY';
        $this->m_state['LA']['en'] = 'LOUISIANA';
        $this->m_state['ME']['en'] = 'MAINE';
        $this->m_state['MH']['en'] = 'MARSHALL ISLANDS';
        $this->m_state['MD']['en'] = 'MARYLAND';
        $this->m_state['MA']['en'] = 'MASSACHUSETTS';
        $this->m_state['MI']['en'] = 'MICHIGAN';
        $this->m_state['MN']['en'] = 'MINNESOTA';
        $this->m_state['MS']['en'] = 'MISSISSIPPI';
        $this->m_state['MO']['en'] = 'MISSOURI';
        $this->m_state['MT']['en'] = 'MONTANA';
        $this->m_state['NE']['en'] = 'NEBRASKA';
        $this->m_state['NV']['en'] = 'NEVADA';
        $this->m_state['NH']['en'] = 'NEW HAMPSHIRE';
        $this->m_state['NJ']['en'] = 'NEW JERSEY';
        $this->m_state['NM']['en'] = 'NEW MEXICO';
        $this->m_state['NY']['en'] = 'NEW YORK';
        $this->m_state['NC']['en'] = 'NORTH CAROLINA';
        $this->m_state['ND']['en'] = 'NORTH DAKOTA';
        $this->m_state['MP']['en'] = 'NORTHERN MARIANA ISLANDS';
        $this->m_state['OH']['en'] = 'OHIO';
        $this->m_state['OK']['en'] = 'OKLAHOMA';
        $this->m_state['OR']['en'] = 'OREGON';
        $this->m_state['PW']['en'] = 'PALAU';
        $this->m_state['PA']['en'] = 'PENNSYLVANIA';
        $this->m_state['PR']['en'] = 'PUERTO RICO';
        $this->m_state['RI']['en'] = 'RHODE ISLAND';
        $this->m_state['SC']['en'] = 'SOUTH CAROLINA';
        $this->m_state['SD']['en'] = 'SOUTH DAKOTA';
        $this->m_state['TN']['en'] = 'TENNESSEE';
        $this->m_state['TX']['en'] = 'TEXAS';
        $this->m_state['UT']['en'] = 'UTAH';
        $this->m_state['VT']['en'] = 'VERMONT';
        $this->m_state['VI']['en'] = 'VIRGIN ISLANDS';
        $this->m_state['VA']['en'] = 'VIRGINIA';
        $this->m_state['WA']['en'] = 'WASHINGTON';
        $this->m_state['WV']['en'] = 'WEST VIRGINIA';
        $this->m_state['WI']['en'] = 'WISCONSIN';
        $this->m_state['WY']['en'] = 'WYOMING';
    }

    /**
     * Get the state value array.
     *
     * @param string $switch
     *
     * @return array with state values
     */
    public function getStateValueArray($switch)
    {
        if ($switch == 'usa') {
            return $this->m_usa_states;
        } else {
            $tmp_array = [];
            foreach ($this->m_state as $iso => $value) {
                $tmp_array[] = $iso;
            }

            return $tmp_array;
        }
    }

    /**
     * Get the state option array.
     *
     * @param string $switch
     *
     * @return array with state options
     */
    public function getStateOptionArray($switch)
    {
        $tmp_array = [];
        if ($switch == 'usa') {
            foreach ($this->m_usa_states as $iso) {
                $tmp_array[] = $this->getStateOption($iso);
            }
        } else {
            foreach ($this->m_state as $iso => $value) {
                $tmp_array[] = $value;
            }
        }

        return $tmp_array;
    }

    /**
     * Get the state option.
     *
     * @param string $iso_code
     *
     * @return string The state option
     */
    public function getStateOption($iso_code)
    {
        return $this->m_state[$iso_code]['en'];
    }
}
