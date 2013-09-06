<?php
/**
 * P Framework
 * @link http://github.com/pframework
 * @license UNLICENSE http://unlicense.org/UNLICENSE
 * @copyright Public Domain
 * @author Ralph Schindler <ralph@ralphschindler.com>
 */

namespace P\Router;

class CliSource implements SourceInterface, \ArrayAccess
{

    protected $arguments;

    public function __construct()
    {
        $argv = $_SERVER['argv'];
        $this->arguments = array_splice($argv, 1);
    }

    public function getArguments()
    {
        return $this->arguments;
    }

    public function offsetExists($offset)
    {
        // headers, normalized-headers, 'method', 'protocol',
        if (isset($this->arguments[$offset])) {
            return true;
        }
        return false;

    }

    public function offsetGet($offset)
    {
        if (isset($this->arguments[$offset])) {
            return $this->headers[$offset];
        }
        throw new \InvalidArgumentException('Unknown offset in ' . __CLASS__);
    }

    public function __isset($name)
    {
        return $this->offsetExists($name);
    }

    public function __get($name)
    {
        return $this->offsetGet($name);
    }

    public function offsetSet($offset, $value)
    {
        throw new \RuntimeException(__METHOD__ . ' is not supported by ' . __CLASS__);
    }

    public function offsetUnset($offset)
    {
        throw new \RuntimeException(__METHOD__ . ' is not supported by ' . __CLASS__);
    }

}
