<?php
/**
 *
 * @author
 * @copyright Copyright (c) Beijing Jinritemai Technology Co.,Ltd.
 */

namespace General\Db\Row;

use Traversable;

class RowLoader
{
    const NS_SEPARATOR = '\\';

    const NS_ROOT = '\\';

    protected $normalizeNameHandler = null;

    protected $paths = array();

    /**
     * Register a path of Row
     *
     * @param $path string|array|Traversable
     * @param $namespace string
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function registerPath($path, $namespace = self::NS_ROOT)
    {
        if (is_array($path) || $path instanceof Traversable) {
            foreach ($path as $p => $ns) {
                if (is_string($p)) {
                    $this->registerPath($p, $ns ? : $namespace);
                } else {
                    $this->registerPath($ns, $namespace);
                }
            }
            return $this;
        }
        $path = self::normalizePath($path);
        if (!is_dir($path)) {
            throw new Exception\InvalidArgumentException(sprintf('"%s" is not a directory', $path));
        }
        $this->paths[$path] = rtrim($namespace, self::NS_SEPARATOR) . self::NS_SEPARATOR;

        return $this;
    }

    /**
     * register name handler
     *
     * @param callable $handler
     * @return $this
     */
    public function registerNormalizeNameHandler(callable $handler)
    {
        $this->normalizeNameHandler = $handler;
        return $this;
    }
    /**
     * Get Row instance
     *
     * @param string $name
     * @return null|AbstractRow
     */
    public function get($name)
    {
        if (is_callable($this->normalizeNameHandler)) {
            $nameHandler = $this->normalizeNameHandler;
            $name = $nameHandler($name);
        } else {
            $name = static::normalizeName($name);
        }

        if (!($class = $this->getClassFromPath($name))) {
            return null;
        }

        return new $class;
    }

    protected function getClassFromPath($name)
    {
        foreach ($this->paths as $path => $namespace) {
            $filename = static::transformClassNameToFilename($name, $path);
            $class = $namespace . $name;
            if (is_file($filename)) {
                include_once $filename;
                if (class_exists($class, false)
                    && is_subclass_of($class, __NAMESPACE__ . '\\AbstractRow'))
                {
                    return $class;
                }
            }
        }

        return null;
    }

    protected static function normalizeName($name)
    {
        $name = str_replace(array('.', '-', '_'), ' ', $name);
        $name = str_replace(' ', '', ucwords($name));
        return $name . 'Row';
    }

    protected static function transformClassNameToFilename($class, $path)
    {
        return $path . str_replace('\\', '/', $class) . '.php';
    }

    protected static function normalizePath($path)
    {
        $path = str_replace('\\', '/', $path);
        if ($path[strlen($path) - 1] != '/') {
            $path .= '/';
        }
        return $path;
    }
}