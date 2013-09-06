<?php
/**
 * P Framework
 * @link http://github.com/pframework
 * @license UNLICENSE http://unlicense.org/UNLICENSE
 * @copyright Public Domain
 * @author Ralph Schindler <ralph@ralphschindler.com>
 */

namespace P\Feature;

use P\Application;

class BasicErrorHandler extends AbstractFeature
{
    public function getServices()
    {
        return ['ErrorHandler' => $this];
    }
    
    public function getCallbacks()
    {
        return array(
            array('Application.Error', array($this, 'handle'))
        );
    }

    public function handle($ApplicationState)
    {
        $params = $ApplicationState->getScopeParameters();
        switch ($params['type']) {
            case Application::ERROR_UNROUTABLE:
            case Application::ERROR_UNDISPATCHABLE:
                if (php_sapi_name() != 'cli') {
                    header('HTTP/1.0 404 Not Found');
                    echo 'Not Found.' . PHP_EOL;
                } else {
                    echo 'Unknown command.' . PHP_EOL;
                }
                break;
            case Application::ERROR_EXCEPTION:
                if (php_sapi_name() != 'cli') {
                    header('HTTP/1.0 500 Application Error');
                }
                echo 'An application error has occrued: '
                    . $exception->getMessage() . "\n" . $exception->getTraceAsString() . PHP_EOL;
                break;
        }

        exit(-1);
    }

}
