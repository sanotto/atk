<?php

namespace Sintattica\Atk\Db;

use Sintattica\Atk\Utils\Debugger;

class DbFactory
{
    protected $debugger;
    protected $dbInstances = [];
    protected $dbConfig;

    /**
     * DbFactory constructor.
     * @param array $dbConfig
     * @param Debugger $debugger
     */
    public function __construct($dbConfig, Debugger $debugger)
    {
        $this->dbConfig = $dbConfig;
        $this->debugger = $debugger;
    }

    /**
     * Get a database instance (singleton)
     *
     * This method instantiates and returns the correct (vendor specific)
     * database instance, depending on the configuration.
     *
     * @static
     *
     * @param string $conn The name of the connection as defined in the
     *                      config.inc.php file (defaults to 'default')
     * @param bool $reset Reset the instance to force the creation of a new instance
     * @param string $mode The mode to connect with the database
     *
     * @return Db Instance of the database class.
     */
    public function getDb($conn = 'default', $reset = false, $mode = 'rw')
    {
        $dbInstance = array_key_exists($conn, $this->dbInstances) ? $this->dbInstances[$conn] : null;

        if ($reset || !$dbInstance || !$dbInstance->hasMode($mode)) {
            $dbInstance = $this->newDb($conn, $mode);
            $this->dbInstances[$conn] = $dbInstance;
        }

        return $dbInstance;
    }

    /**
     * Get a new database instance
     * @param $conn
     * @param string $mode
     * @return Db
     */
    public function newDb($conn = 'default', $mode = 'rw')
    {
        //TODO: check mode usage
        $driver = __NAMESPACE__.'\\'.$this->dbConfig[$conn]['driver'].'Db';
        $this->debugger->addDebug("Creating new database instance with '{$driver}' driver");

        /** @var Db $driverInstance */
        $dbInstance = new $driver($this->debugger);
        $this->initDb($dbInstance, $conn);

        return $dbInstance;
    }

    /**
     * @param Db $db
     * @param string $conn
     */
    protected function initDb(Db $db, $conn)
    {
        $config = $this->dbConfig[$conn];
        $db->m_connection = $conn;
        $db->m_mode = (isset($config['mode']) ? $config['mode'] : 'rw');
        if (isset($config['db'])) {
            $db->m_database = $config['db'];
            $db->m_user = $config['user'];
            $db->m_password = $config['password'];
            $db->m_host = $config['host'];

            if (isset($config['port'])) {
                $db->m_port = $config['port'];
            }
            if (isset($config['charset'])) {
                $db->m_charset = $config['charset'];
            }
            if (isset($config['collate'])) {
                $db->m_collate = $config['collate'];
            }
            if (isset($config['force_ci'])) {
                $db->m_force_ci = $config['force_ci'];
            }
        }
    }
}
