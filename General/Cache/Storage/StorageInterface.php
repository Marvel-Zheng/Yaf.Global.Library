<?php
/**
 *
 * @author
 * @copyright Copyright (c) 2013 Beijing Jinritemai Technology Co.,Ltd.
 */

namespace General\Cache\Storage;


interface StorageInterface
{
    /**
     * Set options.
     *
     * @param array|\Traversable $options
     * @return $this
     */
    public function setOptions($options);

    /**
     * @param string $key
     * @return mixed
     */
    public function get($key);

    /**
     * @param $key
     * @param null $value
     * @param null $ttl
     * @return mixed
     */
    public function set($key, $value = null, $ttl = null);

    /**
     * @param string $key
     * @return bool
     */
    public function has($key);

    /**
     * @param string $key
     * @return bool
     */
    public function remove($key);

    /**
     * @param string $key
     * @return bool
     */
    public function touch($key);

    /**
     * @param string $key
     * @param int $value
     * @return int|bool The new value on success, false on failure
     */
    public function increment($key, $value);

    /**
     * @param string $key
     * @param int $value
     * @return int|bool The new value on success, false on failure
     */
    public function decrement($key, $value);

}