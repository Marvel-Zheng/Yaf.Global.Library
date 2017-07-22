<?php
/**
 *
 *
 * @copyright Copyright (c) Beijing Jinritemai Technology Co.,Ltd.
 */

namespace General\Db\Adapter\Metadata;

use General\Cache\CachePool;
use General\Db\Adapter\AdapterInterface;
use General\Cache\Storage\StorageInterface as CacheStorage;
use General\Db\Adapter\Exception;

abstract class AbstractMetadata implements MetadataInterface
{

    const DEFAULT_SCHEMA = '__DEFAULT_SCHEMA__';

    /**
     * @var AdapterInterface
     */
    protected $adapter = null;

    /**
     * @var string
     */
    protected $defaultSchema = null;

    /**
     * @var array
     */
    protected $data = array();

    /**
     * @var CacheStorage
     */
    protected $cacher;

    /**
     * Constructor
     *
     * @param AdapterInterface $adapter
     */
    public function __construct(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
        try {
            $this->cacher = CachePool::get('MetadataCacher');
            if ($this->cacher instanceof \General\Cache\Storage\StorageInterface) {
                $this->cacher->getResource();
            }
        } catch (\Exception $e) {
            $this->cacher = null;
            $message = 'MetaDataCacher Connect Error: ' . $e->getMessage() . "\n";
            error_log($message);
        }
        $this->defaultSchema = ($adapter->getDriver()->getConnection()->getCurrentSchema()) ? : self::DEFAULT_SCHEMA;
    }

    /**
     * Get columns
     *
     * @param  string $table
     * @param  string $schema
     * @return array
     */
    public function getColumns($table, $schema = null)
    {
        if ($schema == null) {
            $schema = $this->defaultSchema;
        }

        $key = static::normalizeKey($table, $schema);
        if ($this->hasData($key)) {
            return $this->getData($key);
        } else {
            $data = $this->load($table, $schema);
            $this->setData($key, $data);
            return $data;
        }
    }

    /**
     * @param $table
     * @param string $schema
     * @return array
     */
    public function getPrimarys($table, $schema = null)
    {
        $columns = $this->getColumns($table, $schema);
        $primary = array();
        foreach ($columns as $name => $def) {
            if ($def['PRIMARY']) {
                $primary[] = $name;
            }
        }
        return $primary;
    }

    abstract protected function load($table, $schema);

    protected function getData($key)
    {
        if ($this->cacher) {
            return $this->cacher->get($key);
        } else {
            return $this->data[$key];
        }
    }

    protected function hasData($key)
    {
        if ($this->cacher) {
            return $this->cacher->has($key);
        } else {
            return isset($this->data[$key]);
        }
    }

    protected function setData($key, $data)
    {
        if ($this->cacher) {
            $this->cacher->set($key, $data);
        } else {
            $this->data[$key] = $data;
        }
    }

    protected static function normalizeKey($table, $schema)
    {
        return sprintf("%s.%s", $schema, $table);
    }
}
