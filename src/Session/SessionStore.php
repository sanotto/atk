<?php

namespace Sintattica\Atk\Session;

use Sintattica\Atk\Core\Tools;
use Sintattica\Atk\Utils\Debugger;

/**
 * Session storage, given a key (or a key in the session)
 * stores records to the current session.
 */
class SessionStore
{

    /** @var Debugger $debugger */
    protected $debugger;
    /** @var SessionManager $sessionManager */
    protected $sessionManager;
    /**
     * Key to use.
     *
     * @var mixed
     */
    private $_key;

    /**
     * Create a new sessionstore.
     *
     * @param mixed $key Key to use
     * @param bool $reset Reset data
     * @param Debugger $debugger
     * @param SessionManager $sessionManager
     */
    public function __construct($key, $reset = false, Debugger $debugger, SessionManager $sessionManager)
    {
        $this->_key = $key;
        $this->debugger = $debugger;
        $this->sessionManager = $sessionManager;

        if ($reset) {
            $this->setData(array());
        }
    }

    /**
     * Get the key for the current sessionstore.
     *
     * @return mixed Key
     */
    public function getKey()
    {
        return $this->_key;
    }

    /**
     * Add a row to the current sessionstore.
     *
     * Also sets the primary key field to a fake negative id
     *
     * @param array $row Row to store in the session
     * @param string $primary_key_field Primary key field to use and set with the row key
     *
     * @return mixed Primary key for the added record, or false if we don't have a session
     */
    public function addDataRow($row, $primary_key_field)
    {
        $this->addVarDumpDebug($row,
            __CLASS__.'->'.__METHOD__.": Adding a new row to session store with primary key field '$primary_key_field' and key: ".$this->getKey());
        $data = $this->getData();
        if ($data === false) {
            return false;
        }

        $primary_key = -1 * count($data);
        $row[$primary_key_field] = $primary_key;
        $data[] = $row;

        $this->setData($data);

        return $primary_key;
    }

    /**
     * Get a data row from the session for an ATK/SQL selector.
     *
     * @param string $selector
     *
     * @return mixed Row or false if there is nothing
     */
    public function getDataRowForSelector($selector)
    {
        $this->addVarDumpDebug($selector, __CLASS__.'->'.__METHOD__.': Getting row from session store with key: '.$this->getKey());
        $data = $this->getData();
        if (!$data) {
            return false;
        }

        $row_key = $this->getRowKeyFromSelector($selector);
        if (!$this->isValidRowKey($row_key, $data)) {
            return false;
        }

        return $data[$row_key];
    }

    /**
     * Update (set) a row in the session for an ATK/SQL selector.
     *
     * @param string $selector ATK/SQL selector
     * @param array $row New row
     *
     * @return mixed Updated row or false if updating failed
     */
    public function updateDataRowForSelector($selector, $row)
    {
        Tools::atk_var_dump($row, __CLASS__.'->'.__METHOD__.': Updating row in session store with key: '.$this->getKey()." and selector: $selector");
        $data = $this->getData();
        if (!$data) {
            return false;
        }

        $row_key = $this->getRowKeyFromSelector($selector);
        if (!$this->isValidRowKey($row_key, $data)) {
            return false;
        }

        $data[$row_key] = $row;

        $this->setData($data);

        return $row;
    }

    /**
     * Delete a row in the session for a given ATK/SQL selector.
     *
     * @param string $selector ATK/SQL selector
     *
     * @return bool Wether the deleting succeeded
     */
    public function deleteDataRowForSelector($selector)
    {
        $this->addVarDumpDebug($selector, __CLASS__.'->'.__METHOD__.': Deleting row from session store with key: '.$this->getKey());
        $data = $this->getData();
        if (!$data) {
            return false;
        }

        $row_key = $this->getRowKeyFromSelector($selector);
        if (!$this->isValidRowKey($row_key, $data)) {
            return false;
        }

        unset($data[$row_key]);

        $this->setData($data);

        return true;
    }

    /**
     * Get all the data in the session for the current key.
     *
     * @return mixed Data in array form or false if we don't have a key or session
     */
    public function getData()
    {
        if (!$this->_key) {
            return false;
        }

        $data = $this->sessionManager->globalStackVar($this->_key);
        if (!is_array($data)) {
            $data = [];
        }

        return $data;
    }

    /**
     * Set ALL data in the session for the current key.
     *
     * @param array $data Data to set
     *
     * @return mixed Data that was set or false if we don't have a key or session
     */
    public function setData($data)
    {
        if (!$this->_key) {
            return false;
        }

        $this->sessionManager->globalStackVar($this->_key, $data);

        return $data;
    }

    protected function addVarDumpDebug($a, $d)
    {
        ob_start();
        var_dump($a);
        $data = ob_get_contents();
        $this->debugger->addDebug('vardump: '.($d != '' ? $d.' = ' : '').'<pre>'.$data.'</pre>');
        ob_end_clean();
    }

    /**
     * Get rowkey from an ATK/SQL selector.
     *
     * We sneak rowkeys in the selectors as negative ids.
     *
     * @param string $selector
     *
     * @return mixed Key in negative int form or false if we failed to get the key
     */
    private function getRowKeyFromSelector($selector)
    {
        $selector = Tools::decodeKeyValuePair($selector);
        $selector_values = array_values($selector);

        if (count($selector_values) === 1 && is_numeric($selector_values[0]) && $selector_values[0] <= 0) {
            return -1 * $selector_values[0];
        }

        return false;
    }

    /**
     * Check if the given row key is valid.
     *
     * @param int $rowKey Row key
     * @param array $data Data array
     *
     * @return bool
     */
    private function isValidRowKey($rowKey, $data)
    {
        if ($rowKey === false) {
            $this->debugger->addWarning(__CLASS__.'->'.__METHOD__.': No row key selector found');

            return false;
        } elseif (!array_key_exists($rowKey, $data)) {
            $this->debugger->addWarning(__CLASS__.'->'.__METHOD__.': Row key not found in the data');

            return false;
        }

        return true;
    }
}
