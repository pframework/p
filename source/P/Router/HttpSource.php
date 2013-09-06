<?php
/**
 * P Framework
 * @link http://github.com/pframework
 * @license UNLICENSE http://unlicense.org/UNLICENSE
 * @copyright Public Domain
 * @author Ralph Schindler <ralph@ralphschindler.com>
 */

namespace P\Router;

class HttpSource implements SourceInterface, \ArrayAccess
{

    protected $method;
    protected $uri;
    protected $protocol;
    protected $headers = array();
    protected $normalizedHeaders = array();

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->uri = $_SERVER['REQUEST_URI'];
        $this->protocol = $_SERVER['SERVER_PROTOCOL'];

        // parse headers
        foreach ($_SERVER as $key => $value) {
            if ($value && strpos($key, 'HTTP_') === 0 && (strpos($key, 'HTTP_COOKIE') !== 0)) {
                $name = strtr(ucwords(strtolower(strtr(substr($key, 5), '_', ' '))), ' ', '-');
            } elseif ($value && strpos($key, 'CONTENT_') === 0) {
                $name = substr($key, 8); // Content-
                $name = 'Content-' . (($name == 'MD5') ? $name : ucfirst(strtolower($name)));
            } else {
                continue;
            }
            $this->headers[$name] = $value;
            $this->normalizedHeaders[strtolower($name)] = $value;
        }
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getNormalizedHeaders()
    {
        return $this->normalizedHeaders;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function getProtocol()
    {
        return $this->protocol;
    }

    public function getUri()
    {
        return $this->uri;
    }

    public function getBody()
    {
        return file_get_contents('php://input');
    }

    public function offsetExists($offset)
    {
        // headers, normalized-headers, 'method', 'protocol',
        if (isset($this->headers[$offset])) {
            return true;
        }
        if (isset($this->normalizedHeaders[$offset])) {
            return true;
        }
        switch ($offset) {
            case 'method':
            case 'protocol':
            case 'uri':
            case 'body':
                return true;
        }
        return false;
    }

    public function offsetGet($offset)
    {
        // headers, normalized-headers, 'method', 'protocol',
        if (isset($this->headers[$offset])) {
            return $this->headers[$offset];
        }
        if (isset($this->normalizedHeaders[$offset])) {
            return $this->normalizedHeaders[$offset];
        }
        switch ($offset) {
            case 'method': return $this->method;
            case 'protocol': return $this->protocol;
            case 'uri': return $this->uri;
            case 'body': return $this->getBody();
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
