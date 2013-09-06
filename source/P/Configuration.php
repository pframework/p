<?php
/**
 * P Framework
 * @link http://github.com/pframework
 * @license UNLICENSE http://unlicense.org/UNLICENSE
 * @copyright Public Domain
 * @author Ralph Schindler <ralph@ralphschindler.com>
 */

namespace P;

class Configuration implements \ArrayAccess
{

    protected $files = array();

    protected $data;

    public function __construct(array $data = array())
    {
        $this->data = $data;
    }

    public function addFilesWithGlob($glob, $useFilenameAsKey = true)
    {
        // @todo
    }

    public function processFiles()
    {
        // @todo
    }

    public function merge($data, $replace = true)
    {
        // @todo
    }

    public function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet($offset)
    {
        if (!isset($this->data[$offset])) {
            throw new \Exception('Offset in configuration not available');
        }
        return $this->data[$offset];
    }

    public function offsetSet($offset, $value)
    {
        throw new \Exception('Configuration must be merged in');
    }

    public function offsetUnset($offset)
    {
        throw new \Exception('Configuration changes must be merged in.');
    }
}