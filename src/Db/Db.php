<?php

namespace Sintattica\Atk\Db;

use Sintattica\Atk\Core\Config;
use Sintattica\Atk\Core\Tools;
use Sintattica\Atk\Db\Statement\Statement;
use Sintattica\Atk\Errors\AtkErrorException;
use Sintattica\Atk\Utils\Debugger;
use Sintattica\Atk\Utils\TmpFile;

/**
 * Abstract baseclass for ATK database drivers.
 *
 * Implements some custom functionality and defines some methods that
 * derived classes should override.
 *
 * @author Peter Verhage <peter@achievo.org>
 * @author Ivo Jansch <ivo@achievo.org>
 */
class Db
{
    /**
     * Some defines used for connection statusses, generic error messages, etc.
     */
    const DB_SUCCESS = 0;
    const DB_UNKNOWNERROR = 1;
    const DB_UNKNOWNHOST = 2;
    const DB_UNKNOWNDATABASE = 3;
    const DB_ACCESSDENIED_USER = 4;
    const DB_ACCESSDENIED_DB = 5;

    /**
     * Meta flags.
     */
    const MF_PRIMARY = 1;
    const MF_UNIQUE = 2;
    const MF_NOT_NULL = 4;
    const MF_AUTO_INCREMENT = 8;

    /*
     * The hostname/ip to connect to
     */
    public $m_host = '';

    /*
     * The name of the database/schema to use
     */
    public $m_database = '';

    /*
     * The username for the connection
     */
    public $m_user = '';

    /*
     * The password for the connection
     */
    public $m_password = '';

    /*
     * The port for the connection
     */
    public $m_port = '';

    /*
     * The character set
     */
    public $m_charset = '';

    /*
     * The collate
     */
    public $m_collate = '';

    /*
     * Force case insensitive searching and ordering
     */
    public $m_force_ci = false;

    /*
     * The mode for the connection.
     */
    public $m_mode = '';

    /*
     * The current connection name.
     */
    public $m_connection = '';

    /*
     * Contains the current record from the result set
     */
    public $m_record = [];

    /*
     * Current row number
     */
    public $m_row = 0;

    /*
     * Contains error number, in case an error occurred
     * @var int
     */
    public $m_errno = 0;

    /*
     * Contains textual error message, in case an error occurred
     */
    public $m_error = '';

    /*
     * If true, an atkerror is raised when an error occurred.
     *
     * The calling script can use this to stop execution and rollback.
     * If false, the error will be ignored and script execution
     * continues. Use this only for queries that may fail but still be valid.
     */
    public $m_haltonerror = true;

    /*
     * Driver name.
     *
     * Derived classes should add their own m_type var to the class
     * definition and put the correct name in it. (e.g. "mysql" etc.)
     */
    public $m_type = '';

    /*
     * Vendor.
     *
     * This is mainly used to retrieve things like error messages that are
     * common for a vendor (i.e., they do not differ between versions).
     */
    public $m_vendor = '';

    /*
     * number of affected rows after an update/delete/insert query
     */
    public $m_affected_rows = 0;

    /*
     * array to cache meta-information about tables
     */
    public $m_tableMeta = [];

    /**
     * The connection is stored in this variable
     * @var mixed $m_link_id
     */
    public $m_link_id;

    /**
     * The query statement is stored in this variable
     * @var mixed $m_query_id
     */
    public $m_query_id;

    /*
     * Auto free result upon next query.
     *
     * When set to true, the previous results are cleared when a new query is
     * executed. It should generally not be necessary to put this to false.
     */
    public $m_auto_free = true;

    /*
     * List of error codes that could be caused by an end-user.
     *
     * This type of errors is 'recoverable'. An example is a violation of a unique constraint.
     */
    public $m_user_error = [];

    /*
     * Internal use; error messages from language files are cached here
     */
    public $m_errorLookup = [];

    /*
     * Indentifier Quoting
     */
    protected $m_identifierQuoting = array('start' => '"', 'end' => '"', 'escape' => '"');

    protected $debugger;

    /**
     * Constructor.
     * @param Debugger $debugger
     */
    public function __construct(Debugger $debugger)
    {
        $this->debugger = $debugger;
    }

    /**
     * Set database sequence value.
     *
     * @param string $seqname sequence name
     * @param int $value sequence value
     *
     * @abstract
     */
    public function setSequenceValue($seqname, $value)
    {
        throw new AtkErrorException('WARNING: '.get_class($this).'.setSequenceValue NOT IMPLEMENTED!');
    }

    /**
     * Get the database driver type.
     *
     * @return string driver type
     */
    public function getType()
    {
        return $this->m_type;
    }

    /**
     * Get the current connection.
     *
     * @return mixed Connection resource id
     */
    public function link_id()
    {
        return $this->m_link_id;
    }

    /**
     * Has error?
     */
    public function hasError()
    {
        return $this->m_errno != 0;
    }

    /**
     * Determine whether an error that occurred is a recoverable (user) error
     * or a system error.
     *
     * @return string "user" or "system"
     */
    public function getErrorType()
    {
        if (in_array($this->m_errno, $this->m_user_error)) {
            return 'user';
        }

        return 'system';
    }

    /**
     * Get generic atk errorccode.
     *
     * @return int One of the ATK self::DB_* codes.
     */
    public function getAtkDbErrno()
    {
        return $this->_translateError(null);
    }

    /**
     * Get vendor-dependent database error number.
     *
     * Applications should not rely on this method, if they want to be
     * database independent.
     *
     * @return mixed Database dependent error code.
     */
    public function getDbErrno()
    {
        return $this->m_errno;
    }

    /**
     * Get vendor-dependent database error message.
     *
     * Applications should not rely on this method, if they want to be
     * database independent.
     *
     * @return string Database dependent error message.
     */
    public function getDbError()
    {
        return $this->m_error;
    }

    /**
     * Define custom user error codes.
     *
     * Error codes passed to this method will be treated as recoverable user
     * errors.
     *
     * @param mixed $errno Vendor-dependent database error code
     */
    public function setUserError($errno)
    {
        $this->debugger->addDebug(__CLASS__.'::setUserError() -> '.$errno);
        $this->m_user_error[] = $errno;
    }

    /**
     * Returns the query mode.
     *
     * @param string $query
     *
     * @return string Return r or w mode
     */
    public function getQueryMode($query)
    {
        $query = strtolower($query);

        $regexes = array('^\\s*select(?!\\s+into)', '^\\s*show');
        foreach ($regexes as $regex) {
            if (preg_match("/$regex/", $query)) {
                return 'r';
            }
        }

        return 'w';
    }

    /**
     * Looks up the error.
     *
     * @param int $errno Error number
     *
     * @return string The translation for the error
     */
    public function errorLookup($errno)
    {
        if (isset($this->m_errorLookup[$errno])) {
            return $this->m_errorLookup[$errno];
        }

        return '';
    }

    /**
     * Get localized error message (for display in the application).
     *
     * @return string Error message
     */
    public function getErrorMsg()
    {
        $errno = $this->getAtkDbErrno();
        if ($errno == self::DB_UNKNOWNERROR) {
            $errstr = $this->errorLookup($this->getDbErrno());
            if ($errstr == '') {
                $this->m_error = 'unknown_error: '.$this->getDbErrno().' ('.$this->getDbError().')';
            } else {
                $this->m_error = $errstr.($this->getErrorType() == 'system' ? ' ('.$this->getDbError().')' : '');
            }

            return $this->m_error;
        } else {
            $tmp_error = '';
            switch ($errno) {
                case self::DB_ACCESSDENIED_DB:
                    $tmp_error = sprintf('db_access_denied_database %s %s', $this->m_user, $this->m_database);
                    break;
                case self::DB_ACCESSDENIED_USER:
                    $tmp_error = sprintf('db_access_denied_user %s %s', $this->m_user, $this->m_database);
                    break;
                case self::DB_UNKNOWNDATABASE:
                    $tmp_error = sprintf('db_unknown_database %s', $this->m_database);
                    break;
                case self::DB_UNKNOWNHOST:
                    $tmp_error = sprintf('db_unknown_host %s', $this->m_host);
                    break;
            }
            $this->m_error = $tmp_error;

            return $this->m_error;
        }
    }

    /**
     * If haltonerror is set, this will raise an atkerror. If not, it will
     * place the error in atkdebug and continue.
     *
     * @param string $message
     * @throws AtkErrorException
     */
    public function halt($message = '')
    {
        if ($this->m_haltonerror) {
            throw new AtkErrorException($message);
        } else {
            $this->debugger->addWarning("DB halt: ".$message);
        }
    }

    /**
     * Returns the current query resource.
     *
     * @return mixed query resource
     */
    public function getQueryId()
    {
        return $this->m_query_id;
    }

    /**
     * Sets the current query identifier used for next_record() etc.
     *
     * @param mixed $queryId query resource
     */
    public function setQueryId($queryId)
    {
        $this->m_query_id = $queryId;
    }

    /**
     * Rests the query resource.
     *
     * NOTE: this doesn't close the query/statement!
     */
    public function resetQueryId()
    {
        $this->m_query_id = null;
    }

    /**
     * Get the current query statement resource id.
     *
     * @return resource Query statement resource id.
     */
    public function query_id()
    {
        return $this->m_query_id;
    }

    /**
     * Connect to the database.
     *
     * @return int Connection status
     * @abstract
     */
    public function connect()
    {
        if ($this->m_link_id == null) {
            $this->debugger->addDebug("db::connect -> Don't switch use current db");

            return $this->doConnect($this->m_host, $this->m_user, $this->m_password, $this->m_database, $this->m_port, $this->m_charset);
        }

        return self::DB_SUCCESS;
    }

    /**
     * Connect to the database.
     *
     * @param string $host The host to connect to
     * @param string $user The user to connect with
     * @param string $password The password to connect with
     * @param string $database The database to connect to
     * @param int $port The portnumber to use for connecting
     * @param string $charset The charset to use
     * @abstract
     *
     * @return int status
     */
    public function doConnect($host, $user, $password, $database, $port, $charset)
    {
        return self::DB_SUCCESS;
    }

    /**
     * Translate database-vendor dependent error messages into an ATK generic
     * error code.
     *
     * Derived classes should implement this method and translate their error
     * codes.
     *
     * @param mixed $errno Vendor-dependent error code.
     *
     * @return int ATK error code
     */
    public function _translateError($errno = null)
    {
        return self::DB_UNKNOWNERROR;
    }

    /**
     * Disconnect from database.
     *
     * @abstract
     */
    public function disconnect()
    {
    }

    /**
     * Commit the current transaction.
     *
     * @abstract
     */
    public function commit()
    {
    }

    /**
     * Set savepoint with the given name.
     *
     * @param string $name savepoint name
     * @abstract
     */
    public function savepoint($name)
    {
    }

    /**
     * Rollback the current transaction.
     * (If a savepoint is given to the given savepoint.).
     *
     * @param string $savepoint savepoint name
     *
     * @abstract
     */
    public function rollback($savepoint = '')
    {
    }

    /**
     * Creates a new statement for the given query.
     *
     * @see Statement
     *
     * @param string $query SQL query
     *
     * @return Statement statement
     */
    public function prepare($query)
    {
        $class = __NAMESPACE__.'\\Statement\\'.$this->m_type.'Statement';
        if (!class_exists($class)) {
            $class = __NAMESPACE__.'\\Statement\\CompatStatement';
        }

        $stmt = new $class($this, $query);

        return $stmt;
    }

    /**
     * Parse and execute a query.
     *
     * If the query is a select query, the rows can be retrieved using the
     * next_record() method.
     *
     * @param string $query The SQL query to execute
     * @param int $offset Retrieve the results starting at the specified
     *                       record number. Pass -1 or 0 to start at the first
     *                       record.
     * @param int $limit Indicates how many rows to retrieve. Pass -1 to
     *                       retrieve all rows.
     * @abstract
     *
     * @return bool
     */
    public function query($query, $offset = -1, $limit = -1)
    {
        return true;
    }

    /**
     * Retrieve the next record in the resultset.
     *
     * @return mixed An array containing the record, or 0 if there are no more
     *               records to retrieve.
     * @abstract
     */
    public function next_record()
    {
        return 0;
    }

    /**
     * Lock a table in the database.
     *
     * @param string $table The name of the table to lock.
     * @param string $mode The lock type.
     *
     * @return bool True if succesful, false if not.
     * @abstract
     */
    public function lock($table, $mode = 'write')
    {
        return 0;
    }

    /**
     * Relieve all locks.
     *
     * @return bool True if succesful, false if not.
     * @abstract
     */
    public function unlock()
    {
        return 0;
    }

    /**
     * Retrieve the number of rows affected by the last query.
     *
     * After calling query() to perform an update statement, this method will
     * return the number of rows that was updated.
     *
     * @return int The number of affected rows
     * @abstract
     */
    public function affected_rows()
    {
        return 0;
    }

    /**
     * Get the next sequence number of a certain sequence.
     *
     * If the sequence does not exist, it is created automatically.
     *
     * @param string $sequence The sequence name
     *
     * @return int The next sequence value
     * @abstract
     */
    public function nextid($sequence)
    {
    }

    /**
     * Return the meta data of a certain table HIE GEBLEVEN.
     *
     * depending on $full, metadata returns the following values:
     *  -full is false (default):
     *   $result[]:
     *     [0]["table"]  table name
     *     [0]["name"]   field name
     *     [0]["type"]   field type
     *     [0]["len"]    field length
     *     [0]["flags"]  field flags
     *
     *  -full is true:
     *   $result[]:
     *     ["num_fields"] number of metadata records
     *     [0]["table"]  table name
     *     [0]["name"]   field name
     *     [0]["type"]   field type
     *     [0]["len"]    field length
     *     [0]["flags"]  field flags
     *     ["meta"][field name] index of field named "field name"
     *     The last one is used, if you have a field name, but no index.
     *
     * @param string $table the table name
     * @param bool $full all meta data or not
     *
     * @return array with meta data
     */
    public function metadata($table, $full = false)
    {
        return [];
    }

    /**
     * Return the available table names.
     *
     * @return array with table names etc.
     *
     * @param bool $includeViews include views?
     */
    public function table_names($includeViews = true)
    {
        return [];
    }

    /**
     * This function checks the database for a table with
     * the provide name.
     *
     * @param string $tableName the table to find
     *
     * @return bool true if found, false if not found
     */
    public function tableExists($tableName)
    {
        return false;
    }

    /**
     * Returns the first row for the given query.
     *
     * Please note: this method does *not* add a limit to the query
     *
     * @param string $query query
     * @param bool $useLimit add limit to the query (if you have your own limit specify false!)
     *
     * @return array row
     */
    public function getRow($query, $useLimit = false)
    {
        $rows = $this->getRows($query, $useLimit ? 0 : -1, $useLimit ? 1 : -1);

        return count($rows) > 0 ? $rows[0] : null;
    }

    /**
     * Get all rows for the given query.
     *
     * NOTE:
     * This is not an efficient way to retrieve records, as this
     * will load all records into one array into memory. If you
     * retrieve a lot of records, you might hit the memory_limit
     * and your script will die.
     *
     * @param string $query query
     * @param int $offset offset
     * @param int $limit limit
     *
     * @return array rows
     */
    public function getRows($query, $offset = -1, $limit = -1)
    {
        return $this->getRowsAssoc($query, null, $offset, $limit);
    }

    /**
     * Get rows in an associative array with the given column used as key for the rows.
     *
     * NOTE:
     * This is not an efficient way to retrieve records, as this
     * will load all records into one array into memory. If you
     * retrieve a lot of records, you might hit the memory_limit
     * and your script will die.
     *
     * @param string $query query
     * @param int|string $keyColumn column index / name (default first column) to be used as key
     * @param int $offset offset
     * @param int $limit limit
     *
     * @return array rows
     */
    public function getRowsAssoc($query, $keyColumn = 0, $offset = -1, $limit = -1)
    {
        $result = [];

        $this->query($query, $offset, $limit);
        for ($i = 0; $this->next_record(); ++$i) {
            if ($keyColumn === null) {
                $key = $i;
            } else {
                if (is_numeric($keyColumn)) {
                    $key = Tools::atkArrayNvl(array_values($this->m_record), $keyColumn);
                } else {
                    $key = $this->m_record[$keyColumn];
                }
            }

            $result[$key] = $this->m_record;
        }

        return $result;
    }

    /**
     * Get a single value from a certain specified query.
     *
     * @param string $query query
     * @param mixed $default fallback value if the query doesn't return a result
     * @param int|string $valueColumn column index / name (default first column) to be used as value
     * @param bool $useLimit add limit to the query (if you have your own limit specify false!)
     *
     * @return mixed first value or default fallback value
     */
    public function getValue($query, $default = null, $valueColumn = 0, $useLimit = false)
    {
        $row = $this->getRow($query, $useLimit);

        if ($row == null) {
            return $default;
        } else {
            if (is_numeric($valueColumn)) {
                return Tools::atkArrayNvl(array_values($row), $valueColumn);
            } else {
                return $row[$valueColumn];
            }
        }
    }

    /**
     * Get an array with all the values in the specified column.
     *
     * NOTE:
     * This is not an efficient way to retrieve records, as this
     * will load all records into one array into memory. If you
     * retrieve a lot of records, you might hit the memory_limit
     * and your script will die.
     *
     * @param string $query query
     * @param int|string $valueColumn column index / name (default first column) to be used as value
     * @param int $offset offset
     * @param int $limit limit
     *
     * @return array with values
     */
    public function getValues($query, $valueColumn = 0, $offset = -1, $limit = -1)
    {
        return $this->getValuesAssoc($query, null, $valueColumn, $offset, $limit);
    }

    /**
     * Get rows in an associative array with the given key column used as
     * key and the given value column used as value.
     *
     * NOTE:
     * This is not an efficient way to retrieve records, as this
     * will load all records into one array into memory. If you
     * retrieve a lot of records, you might hit the memory_limit
     * and your script will die.
     *
     * @param string $query query
     * @param int|string $keyColumn column index / name (default first column) to be used as key
     * @param int|string $valueColumn column index / name (default first column) to be used as value
     * @param int $offset offset
     * @param int $limit limit
     *
     * @return array rows
     */
    public function getValuesAssoc($query, $keyColumn = 0, $valueColumn = 1, $offset = -1, $limit = -1)
    {
        $rows = $this->getRowsAssoc($query, $keyColumn, $offset, $limit);
        foreach ($rows as $key => &$value) {
            if (is_numeric($valueColumn)) {
                $value = Tools::atkArrayNvl(array_values($value), $valueColumn);
            } else {
                $value = $value[$valueColumn];
            }
        }

        return $rows;
    }

    /**
     * This function indicates what searchmodes the database supports.
     *
     * @return array with search modes
     */
    public function getSearchModes()
    {
        // exact match and substring search should be supported by any database.
        // (the LIKE function is ANSI standard SQL, and both substring and wildcard
        // searches can be implemented using LIKE)
        return array(
            'exact',
            'substring',
            'wildcard',
            'greaterthan',
            'greaterthanequal',
            'lessthan',
            'lessthanequal',
            'between',
        );
    }

    /**
     * Fetches table meta data from database.
     *
     * @param string $table
     *
     * @return array
     */
    public function tableMeta($table)
    {
        if (isset($this->m_tableMeta[$table])) {
            return $this->m_tableMeta[$table];
        }

        if (Config::getGlobal('meta_caching')) {
            $this->m_tableMeta[$table] = $this->_getTableMetaFromCache($table);
        } else {
            $this->m_tableMeta[$table] = $this->_getTableMetaFromDb($table);
        }

        return $this->m_tableMeta[$table];
    }

    /**
     * get NOW() or SYSDATE() equivalent for the current database.
     *
     * Every database has it's own implementation to get the current date
     */
    public function func_now()
    {
        return 'NOW()';
    }

    /**
     * get SUBSTRING() equivalent for the current database.
     *
     * @param string $fieldname The database fieldname
     * @param int $startat The position to start from
     * @param int $length The number of characters
     * @return string
     */
    public function func_substring($fieldname, $startat = 0, $length = 0)
    {
        return "SUBSTRING($fieldname, $startat".($length != 0 ? ", $length" : '').')';
    }

    /**
     * Get TO_CHAR() equivalent for the current database.
     * Each database driver should override this method to perform vendor
     * specific conversion.
     *
     * @param string $fieldname The field to generate the to_char for.
     * @param string $format Format specifier. The format is compatible with
     *                          php's date() function (http://www.php.net/date)
     *                          The default is what's specified by
     *                          $config_date_to_char, or "Y-m-d" if not
     *                          set in the configuration.
     *
     * @return string Piece of sql query that converts a date field to char
     *                for the current database
     */
    public function func_datetochar($fieldname, $format = '')
    {
        if ($format == '') {
            $format = Config::getGlobal('date_to_char', 'Y-m-d');
        }

        return "TO_CHAR($fieldname, '".$this->vendorDateFormat($format)."')";
    }

    /**
     * Get CONCAT() equivalent for the current database.
     *
     * @param array $fields
     *
     * @return string
     */
    public function func_concat($fields)
    {
        if (count($fields) == 0 or !is_array($fields)) {
            return '';
        } elseif (count($fields) == 1) {
            return $fields[0];
        }

        return 'CONCAT('.implode(',', $fields).')';
    }

    /**
     * Get CONCAT_WS() equivalent for the current database.
     *
     * @param array $fields
     * @param string $separator
     * @param bool $remove_all_spaces remove all spaces in result (atkAggrecatedColumns searches for string without spaces)
     *
     * @return string $query_part
     */
    public function func_concat_ws($fields, $separator, $remove_all_spaces = false)
    {
        if (count($fields) == 0 or !is_array($fields)) {
            return '';
        } elseif (count($fields) == 1) {
            return $fields[0];
        }

        if ($remove_all_spaces) {
            return "REPLACE ( CONCAT_WS('$separator', ".implode(',', $fields)."), ' ', '') ";
        } else {
            return "CONCAT_WS('$separator', ".implode(',', $fields).')';
        }
    }

    /**
     * Convert a php date() format specifier to a vendor specific format
     * specifier.
     * The default implementation returns the format as used by many
     * database vendors ('YYYYMMDD HH24:MI'). Databases that use different
     * formatting, should override this method.
     *
     * Note that currently, only the common specifiers Y, m, d, H, h, i and
     * s are supported.
     *
     * @param string $format Format specifier. The format is compatible with
     *                       php's date() function (http://www.php.net/date)
     *
     * @return string Vendor specific format specifier.
     */
    public function vendorDateFormat($format)
    {
        $php_fmt = array('Y', 'm', 'd', 'H', 'h', 'i', 's');
        $db_fmt = array('YYYY', 'MM', 'DD', 'HH24', 'HH12', 'MI', 'SS');

        return str_replace($php_fmt, $db_fmt, $format);
    }

    /**
     * Get TO_CHAR() equivalent for the current database.
     *
     * TODO/FIXME: add format parameter. Current format is always yyyy-mm-dd hh:mi.
     *
     * @param string $fieldname The field to generate the to_char for.
     *
     * @return string Piece of sql query that converts a datetime field to char
     *                for the current database
     */
    public function func_datetimetochar($fieldname)
    {
        return "TO_CHAR($fieldname, 'YYYY-MM-DD hh:mi')";
    }

    /**
     * Returns the maximum length an identifier (tablename, columnname, etc) may have.
     *
     * @return int The maximum identifier length
     */
    public function maxIdentifierLength()
    {
        return 64;
    }

    /**
     * escapes quotes for use in SQL: ' -> '' (and sometimes % -> %%).
     *
     * @param string $string The string to escape
     * @param bool $wildcard Use wildcards?
     *
     * @return string The escaped SQL string
     */
    public function escapeSQL($string, $wildcard = false)
    {
        $result = str_replace("'", "''", $string);
        $result = str_replace('\\', '\\\\', $result);
        if ($wildcard == true) {
            $result = str_replace('%', '%%', $result);
        }

        return $result;
    }

    /**
     * Create an Query object for constructing queries.
     *
     * @return Query Query class.
     */
    public function createQuery()
    {
        $class = __NAMESPACE__.'\\'.$this->m_type.'Query';
        $query = new $class();
        $query->m_db = $this;

        return $query;
    }

    /**
     * Enable/disable all foreign key constraints.
     *
     * @param bool $enable enable/disable foreign keys?
     * @throws AtkErrorException
     */
    public function toggleForeignKeys($enable)
    {
        $this->debugger->addDebug('WARNING: '.get_class($this).'::toggleForeignKeys not implemented!');
    }

    /**
     * Empty all database tables.
     */
    public function deleteAll()
    {
        $tables = $this->table_names(false);
        $count = count($tables);

        do {
            $prevCount = $count;
            $count = 0;

            foreach ($tables as $table) {
                $query = $this->createQuery();
                $query->addTable($table['table_name']);
                if (!$query->executeDelete()) {
                    ++$count;
                }
            }
        } while ($count < $prevCount && $count > 0);

        if ($count > 0) {
            throw new AtkErrorException(__CLASS__.'::deleteAll failed, probably because of circular dependencies');
        }
    }

    /**
     * Drop all database tables.
     */
    public function dropAll()
    {
        $tables = $this->table_names();
        foreach ($tables as $table) {
            $this->query('DROP TABLE '.$table['table_name']);
        }
    }

    /**
     * Clones the database structure of the given database
     * to this database. This also means the complete database
     * is emptied beforehand.
     *
     * @param Db $otherDb other database instance
     */
    public function cloneAll($otherDb)
    {
        $this->dropAll();
        $tables = $otherDb->table_names();
        foreach ($tables as $table) {
            $ddl = $this->createDdl();
            $metadata = $otherDb->metadata($table['table_name']);
            $ddl->loadMetaData($metadata);
            $query = $ddl->buildCreate();
            $this->query($query);
        }
    }

    /**
     * Create an atkDdl object for constructing ddl queries.
     *
     * @return Ddl Ddl object
     */
    public function createDdl()
    {
        $ddl = Ddl::create($this->m_type);
        $ddl->m_db = $this;

        return $ddl;
    }

    /**
     * Check if the current instance has the given mode.
     *
     * @param string $mode The mode we want to check
     *
     * @return bool True or False
     */
    public function hasMode($mode)
    {
        if (strpos($this->m_mode, $mode) !== false) {
            return true;
        }

        return false;
    }

    /**
     * Halt on error?
     *
     * @return bool halt on error?
     */
    public function getHaltOnError()
    {
        return $this->m_haltonerror;
    }

    /**
     * Halt on error or not?
     *
     * @param bool $state
     */
    public function setHaltOnError($state = true)
    {
        $this->m_haltonerror = $state;
    }

    /**
     * Check if current db is present and acceptable for current user.
     *
     * @return mixed
     */
    public function getDbStatus()
    {
        // We don't want the db class to display error messages, because
        // we handle the error ourselves.
        $curhaltval = $this->m_haltonerror;
        $this->m_haltonerror = false;

        $res = $this->connect();

        if ($res === self::DB_SUCCESS && (strpos($this->m_type, 'mysql') === 0)) {
            // This can't be trusted. Mysql returns self::DB_SUCCESS even
            // if the user doesn't have access to the database. We only get an
            // error for this after we performed the first query.
            $this->table_names();  // this triggers a query
            $res = $this->_translateError($this->getDbErrno());
        }

        $this->m_haltonerror = $curhaltval;

        return $res;
    }

    /**
     * Quote Indentifier.
     *
     * @param string $str
     *
     * @return string
     */
    public function quoteIdentifier($str)
    {
        $str = str_replace($this->m_identifierQuoting['end'], $this->m_identifierQuoting['escape'].$this->m_identifierQuoting['end'], $str);

        return $this->m_identifierQuoting['start'].$str.$this->m_identifierQuoting['end'];
    }

    /**
     * Returns the last inserted auto increment value.
     *
     * @return int auto increment value of latest insert query
     */
    public function getInsertId()
    {
        return null;
    }

    public function getForceCaseInsensitive()
    {
        return $this->m_force_ci;
    }

    public function getDebugger()
    {
        return $this->debugger;
    }

    /**
     * Returns the tablemetadata directly from db.
     *
     * @param string $table
     *
     * @return array
     */
    protected function _getTableMetaFromDb($table)
    {
        $meta = $this->metadata($table, false);

        $result = [];
        for ($i = 0, $_i = count($meta); $i < $_i; ++$i) {
            $meta[$i]['num'] = $i;
            $result[$meta[$i]['name']] = $meta[$i];
        }

        return $result;
    }

    /**
     * If cached it'll return the table metadata
     * from cache.
     *
     * @param string $table
     *
     * @return array
     */
    private function _getTableMetaFromCache($table)
    {
        $tmpfile = new TmpFile('tablemeta/'.$this->m_connection.'/'.$table.'.php');

        $tablemeta = [];
        if ($tmpfile->exists()) {
            include $tmpfile->getPath();
        } else {
            $tablemeta = $this->_getTableMetaFromDb($table);
            $tmpfile->writeAsPhp('tablemeta', $tablemeta);
        }

        return $tablemeta;
    }
}
