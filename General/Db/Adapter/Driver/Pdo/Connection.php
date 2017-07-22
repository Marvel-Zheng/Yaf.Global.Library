<?php
/**
 *
 * @author
 * @copyright Copyright (c) Beijing Jinritemai Technology Co.,Ltd.
 */

namespace General\Db\Adapter\Driver\Pdo;

use General\Db\Adapter\Driver\ConnectionInterface;
use General\Db\Adapter\Exception;

class Connection implements ConnectionInterface
{
    /**
     * @var Pdo
     */
    protected $driver = null;

    /**
     * @var string
     */
    protected $driverName = null;

    /**
     * @var array
     */
    protected $connectionParameters = array();

    /**
     * @var \PDO
     */
    protected $resource = null;

    /**
     * @var bool
     */
    protected $inTransaction = false;

    /**
     * Constructor
     *
     * @param array|\PDO|null $connectionParameters
     * @throws Exception\InvalidArgumentException
     */
    public function __construct($connectionParameters = null)
    {
        if (is_array($connectionParameters)) {
            $this->setConnectionParameters($connectionParameters);
        } elseif ($connectionParameters instanceof \PDO) {
            $this->setResource($connectionParameters);
        } elseif (null !== $connectionParameters) {
            throw new Exception\InvalidArgumentException(
                '$connection must be an array of parameters, a PDO object or null');
        }
    }

    /**
     * Set driver
     *
     * @param Pdo $driver
     * @return $this
     */
    public function setDriver(Pdo $driver)
    {
        $this->driver = $driver;
        return $this;
    }

    /**
     * Get driver name
     *
     * @return null|string
     */
    public function getDriverName()
    {
        return $this->driverName;
    }

    /**
     * Set connection parameters
     *
     * @param array $connectionParameters
     * @return void
     */
    public function setConnectionParameters(array $connectionParameters)
    {
        $this->connectionParameters = $connectionParameters;
        if (isset($connectionParameters['dsn'])) {
            $this->driverName = substr($connectionParameters['dsn'], 0, strpos($connectionParameters['dsn'], ':'));
        } elseif (isset($connectionParameters['pdodriver'])) {
            $this->driverName = strtolower($connectionParameters['pdodriver']);
        } elseif (isset($connectionParameters['driver'])) {
            $this->driverName = strtolower(substr(str_replace(
                array('-', '_', ' '), '', $connectionParameters['driver']), 3));
        }
    }

    /**
     * Get connection parameters
     *
     * @return array
     */
    public function getConnectionParameters()
    {
        return $this->connectionParameters;
    }

    /**
     * Get current schema
     *
     * @return string
     */
    public function getCurrentSchema()
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        switch ($this->driverName) {
            case 'mysql':
                $sql = 'SELECT DATABASE()';
                break;
            case 'sqlite':
                return 'main';
            case 'pgsql':
            default:
                $sql = 'SELECT CURRENT_SCHEMA';
                break;
        }

        $result = $this->resource->query($sql);
        if ($result instanceof \PDOStatement) {
            return $result->fetchColumn();
        }
        return false;
    }

    /**
     * Set resource
     *
     * @param  \PDO $resource
     * @return $this
     */
    public function setResource(\PDO $resource)
    {
        $this->resource = $resource;
        $this->driverName = strtolower($this->resource->getAttribute(\PDO::ATTR_DRIVER_NAME));
        return $this;
    }

    /**
     * Get resource
     *
     * @return \PDO
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * Connect
     *
     * @return $this
     * @throws Exception\InvalidConnectionParametersException
     * @throws Exception\RuntimeException
     */
    public function connect()
    {
        if ($this->resource) {
            return $this;
        }

        $dsn = $username = $password = $hostname = $database = null;
        $options = array();
        foreach ($this->connectionParameters as $key => $value) {
            switch (strtolower($key)) {
                case 'dsn':
                    $dsn = $value;
                    break;
                case 'driver':
                    $value = strtolower($value);
                    if (strpos($value, 'pdo') === 0) {
                        $pdoDriver = strtolower(substr(str_replace(array('-', '_', ' '), '', $value), 3));
                    }
                    break;
                case 'pdodriver':
                    $pdoDriver = (string)$value;
                    break;
                case 'user':
                case 'username':
                    $username = (string)$value;
                    break;
                case 'pass':
                case 'password':
                    $password = (string)$value;
                    break;
                case 'host':
                case 'hostname':
                    $hostname = (string)$value;
                    break;
                case 'port':
                    $port = (int)$value;
                    break;
                case 'database':
                case 'dbname':
                    $database = (string)$value;
                    break;
                case 'charset':
                    $charset = (string)$value;
                    break;
                case 'driver_options':
                case 'options':
                    $value = (array)$value;
                    $options = array_diff_key($options, $value) + $value;
                    break;
                default:
                    $options[$key] = $value;
                    break;
            }
        }

        if (!isset($dsn) && isset($pdoDriver)) {
            $dsn = array();
            switch ($pdoDriver) {
                case 'sqlite':
                    $dsn[] = $database;
                    break;
                default:
                    if (isset($database)) {
                        $dsn[] = "dbname={$database}";
                    }
                    if (isset($hostname)) {
                        $dsn[] = "host={$hostname}";
                    }
                    if (isset($port)) {
                        $dsn[] = "port={$port}";
                    }
                    if (isset($charset)) {
                        $dsn[] = "charset={$charset}";
                    }
                    break;
            }
            $dsn = $pdoDriver . ':' . implode(';', $dsn);
        } elseif (!isset($dsn)) {
            throw new Exception\InvalidConnectionParametersException(
                'A dsn was not provided or could not be constructed from your parameters',
                $this->connectionParameters
            );
        }

        $retryConnectTimes = 3;
        $connectTimes      = 1;
        while ($retryConnectTimes >= $connectTimes) {
            try {
                $this->resource = new \PDO($dsn, $username, $password, $options);
                $this->resource->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $this->resource->setAttribute(\PDO::ATTR_TIMEOUT, 10);
                $this->driverName = strtolower($this->resource->getAttribute(\PDO::ATTR_DRIVER_NAME));
                return $this;
            } catch (\PDOException $e) {
                $message = 'Database Connect Error: ' . $e->getMessage() . "\n" .
                    'driver: ' . $pdoDriver . "\n" .
                    'host: '   . $hostname . "\n" .
                    'port: '   . $port . "\n" .
                    'retryTimes: ' . $connectTimes . "\n";
                error_log($message);

                if ($connectTimes == $retryConnectTimes) {
                    throw new Exception\RuntimeException('Connect Error: ' . $e->getMessage(), $e->getCode(), $e);
                }
            }

            $connectTimes++;
        }
    }

    /**
     * Is connected
     *
     * @return bool
     */
    public function isConnected()
    {
        if ($this->resource instanceof \PDO) {
            // 检测连接是否有效
            try {
                $this->resource->query("select now()");
            } catch(\PDOException $e) {
                if (strpos(strtolower($e->getMessage()), "gone away") !== false) {
                    $this->resource = null;
                    return false;
                }
            }

            return true;
        } else {
            return false;
        }
    }

    /**
     * Disconnect
     *
     * @return $this
     */
    public function disconnect()
    {
        if ($this->isConnected()) {
            $this->resource = null;
        }
        return $this;
    }

    /**
     * Begin transaction
     *
     * @return $this
     */
    public function beginTransaction()
    {
        if (!$this->isConnected()) {
            $this->connect();
        }
        $this->resource->beginTransaction();
        $this->inTransaction = true;
        return $this;
    }

    /**
     * Commit
     *
     * @return $this
     */
    public function commit()
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $this->resource->commit();
        $this->inTransaction = false;
        return $this;
    }

    /**
     * Rollback
     *
     * @return $this
     * @throws Exception\RuntimeException
     */
    public function rollback()
    {
        if (!$this->isConnected()) {
            throw new Exception\RuntimeException('Must be connected before you can rollback');
        }

        if (!$this->inTransaction) {
            throw new Exception\RuntimeException('Must call beginTransaction() before you can rollback');
        }

        $this->resource->rollBack();
        return $this;
    }

    /**
     * Execute
     *
     * @param string $sql
     * @return Result
     * @throws Exception\InvalidQueryException
     */
    public function execute($sql)
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $resultResource = $this->resource->query($sql);

        if ($resultResource === false) {
            $errorInfo = $this->resource->errorInfo();
            throw new Exception\InvalidQueryException($errorInfo[2]);
        }

        $result = $this->driver->createResult($resultResource, $sql);
        return $result;
    }

    /**
     * Prepare
     *
     * @param string $sql
     * @return Statement
     */
    public function prepare($sql)
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $statement = $this->driver->createStatement($sql);
        return $statement;
    }

    /**
     * Get last generated id
     *
     * @param string $name
     * @return int|null|bool
     */
    public function getLastGeneratedValue($name = null)
    {
        if ($name === null && $this->driverName == 'pgsql') {
            return null;
        }

        try {
            return $this->resource->lastInsertId($name);
        } catch (\Exception $e) {
            // do nothing
        }
        return false;
    }

}
