<?php
/**
 * P Framework
 * @link http://github.com/pframework
 * @license UNLICENSE http://unlicense.org/UNLICENSE
 * @copyright Public Domain
 * @author Ralph Schindler <ralph@ralphschindler.com>
 */

namespace P\Feature;

class PHTMLView extends AbstractFeature
{
    /** @var array */
    protected $paths = array();
    protected $lastIncludePath = null;
    protected $variables = array();

    public function getCallbacks()
    {
        return array(
            array('Application.PostDispatch', array($this, 'handle'), 0)
        );
    }

    public function getServices()
    {
        return array(
            'PHTMLView' => $this
        );
    }

    public function setPaths($paths)
    {
        $paths = (array) $paths;
        $this->paths = $paths;
    }

    public function canHandle($return)
    {
        if (!is_array($return)
            || !isset($return[0])
            || !is_string($return[0])
        ) {
            return false;
        }
        if (isset($return[1])
            && !is_array($return[1])) {
            return false;
        }
        return true;
    }

    public function handle($ApplicationState, $Configuration)
    {
        $return = $ApplicationState->getResult('Application.Dispatch');

        if (!is_array($return) || !isset($return[0]) || !is_string($return[0])) {
            return;
        }

        $variables = (isset($return[1]) ? $return[1] : array());

        $script = $return[0];
        if (pathinfo($script, PATHINFO_EXTENSION) == null) {
            $script .= '.phtml';
        }

        $this->paths = $Configuration['phtml_view']['paths'];

        extract(array_merge($this->variables, $variables));
        $this->prepare();
        try {
            include $script;
        } catch (\Exception $e) {
            $this->cleanup();
            throw $e;
        }
        $this->cleanup();
    }

    public function render($script, array $variables = array())
    {
        if (pathinfo($script, PATHINFO_EXTENSION) == null) {
            $script .= '.phtml';
        }
        extract($variables);
        $this->prepare();
        try {
            include $script;
        } catch (\Exception $e) {
            $this->cleanup();
            throw $e;
        }
        $this->cleanup();
    }

    protected function prepare()
    {
        $this->lastIncludePath = get_include_path();
        set_include_path(implode(PATH_SEPARATOR, $this->paths));
    }

    protected function cleanup()
    {
        set_include_path($this->lastIncludePath);
    }

    public function setVariable($name, $value)
    {
        $this->variables[$name] = $value;
    }

    public function getVariable($name, $value)
    {
        return $this->variables[$name];
    }

    public function __invoke($Configuration)
    {
        var_dump($Configuration);
    }

}