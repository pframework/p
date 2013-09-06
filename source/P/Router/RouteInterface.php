<?php
/**
 * P Framework
 * @link http://github.com/pframework
 * @license UNLICENSE http://unlicense.org/UNLICENSE
 * @copyright Public Domain
 * @author Ralph Schindler <ralph@ralphschindler.com>
 */

namespace P\Router;

interface RouteInterface
{
    public function getDispatchable();
    public function match(SourceInterface $source);
    public function assemble($parameters);
}
