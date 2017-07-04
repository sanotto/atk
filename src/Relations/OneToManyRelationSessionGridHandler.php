<?php

namespace Sintattica\Atk\Relations;

use Sintattica\Atk\DataGrid\DataGrid;
use Sintattica\Atk\Session\SessionStoreFactory;

/**
 * The OTM Session Grid Handler
 * Provides the OTM grid with data from the session,
 * when the OTM is in add mode.
 */
class OneToManyRelationSessionGridHandler
{
    private $_key;
    private $sessionStoreFactory;

    /**
     * Create a OTM session grid handler.
     *
     * @param string $key
     * @param SessionStoreFactory $sessionStoreFactory
     */
    public function __construct($key, SessionStoreFactory $sessionStoreFactory)
    {
        $this->_key = $key;
        $this->sessionStoreFactory = $sessionStoreFactory;
    }

    /**
     * Select handler, returns the records for the grid.
     *
     * @param DataGrid $grid
     *
     * @return array Records for the grid
     */
    public function selectHandlerForAdd(DataGrid $grid)
    {
        $records = $this->getRecordsFromSession();

        $limit = $grid->getLimit();
        $offset = $grid->getOffset();
        $records_count = count($records);

        // If we don't need to limit the result, then we don't
        if ((int)$offset === 0 && $limit >= $records_count) {
            // We have to sort the data first, because the datagrid
            // is very sensitive with regards to it's numerical keys
            // being sequential
            sort($records);

            return $records;
        }

        // Limit the search results and return the limited results
        $ret = [];
        $records_keys = array_keys($records);
        for ($i = $offset, $j = 0; $i < $records_count && $j < $limit; $i++, $j++) {
            $ret[] = $records[$records_keys[$i]];
        }

        return $ret;
    }

    /**
     * Count handler, return the number of records there are in the session.
     *
     * @return int
     */
    public function countHandlerForAdd()
    {
        return count($this->getRecordsFromSession());
    }

    /**
     * Get all records for the current key from the session.
     *
     * @return array
     */
    private function getRecordsFromSession()
    {
        $ss = $this->sessionStoreFactory->getSessionStore($this->_key);

        return $ss->getData();
    }
}
