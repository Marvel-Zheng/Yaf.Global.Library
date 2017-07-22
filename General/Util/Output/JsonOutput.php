<?php
/**
 *
 * @author
 * @copyright Copyright (c) Beijing Jinritemai Technology Co.,Ltd.
 */

namespace General\Util\Output;

use General\Util\Sender\Http as HttpSender;
use General\Util\Sender\SenderInterface;
use Traversable;

class JsonOutput implements OutputInterface
{
    /**
     * @var array
     */
    protected $variables = array();

    /**
     * @var string
     */
    protected $callback = null;


    /**
     * @param null $variables
     */
    public function __construct($variables = null)
    {
        if ($variables) {
            $this->setVariables($variables, true);
        }
    }

    /**
     * @param array|Traversable $variables
     * @param bool $overwrite
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function setVariables($variables, $overwrite = false)
    {
        if (!is_array($variables) && !$variables instanceof Traversable) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s: expects an array, or Traversable argument; received "%s"', __METHOD__,
                (is_object($variables) ? get_class($variables) : gettype($variables))
            ));
        }

        if ($overwrite) {
            if ($variables instanceof Traversable) {
                if (method_exists($variables, 'toArray')) {
                    /** @noinspection PhpUndefinedMethodInspection */
                    $variables = $variables->toArray();
                } else {
                    $temp = array();
                    foreach ($variables as $key => $val) {
                        $temp[$key] = $val;
                    }
                    $variables = $temp;
                }
            }

            $this->variables = $variables;
        } else {
            foreach ($variables as $key => $value) {
                $this->setVariable($key, $value);
            }
        }

        return $this;
    }

    /**
     * Property overloading: set variable value
     *
     * @param  string $name
     * @param  mixed $value
     * @return void
     */
    public function __set($name, $value)
    {
        $this->setVariable($name, $value);
    }

    /**
     * Property overloading: get variable value
     *
     * @param  string $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->getVariable($name);
    }

    /**
     * Property overloading: do we have the requested variable value?
     *
     * @param  string $name
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->variables[$name]);
    }

    /**
     * Property overloading: unset the requested variable
     *
     * @param  string $name
     */
    public function __unset($name)
    {
        unset($this->variables[$name]);
    }

    /**
     * Get a single variable
     *
     * @param  string $name
     * @param  mixed|null $default (optional) default value if the variable is not present.
     * @return mixed
     */
    public function getVariable($name, $default = null)
    {
        $name = (string)$name;
        if (array_key_exists($name, $this->variables)) {
            return $this->variables[$name];
        }

        return $default;
    }

    /**
     * Set a variable
     *
     * @param  string $name
     * @param  mixed $value
     * @return $this
     */
    public function setVariable($name, $value)
    {
        $this->variables[(string)$name] = $value;
        return $this;
    }

    /**
     * Set jsonp callback
     *
     * @param string $callback
     * @return $this
     */
    public function setCallback($callback)
    {
        $this->callback = $callback;
    }

    /**
     * Output the content
     */
    public function __invoke(SenderInterface $sender)
    {
        $json = json_encode($this->variables);
        $contentType = 'application/json';
        if (null !== $this->callback) {
            $json = $this->callback . '(' . $json . ');';
            $contentType = 'application/x-javascript';
        }
        if ($sender instanceof HttpSender) {
            $headers = $sender->getHeaders();
            $headers->addHeaderLine('Content-Type', $contentType);
        }
        $sender->setContent($json);
        $sender->send();
    }
}