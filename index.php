<?php

//共通クラスファイルの場所
define('BASE_LIB_DIR','../lib');

//アプリケーションファイルの場所
define('BASE_APP_DIR','../app');

//デフォルトのモジュール名
define('DEFAULT_MODULE','Default');

//デフォルトのアクション名
define('DEFAULT_ACTION','Index');

//共通クラスのオートローダー
function __autoload($className)
{
    require_once BASE_LIB_DIR . '/' . str_replace('_','/',$className) . '.php';
}


try {

    $controller = Controller::getInstance();
    $controller->forward();

} catch (Exception $e) {
    echo $e->getMessage();
    exit;
}


abstract class Action
{
    abstract public function execute();

    public function view($module,$action)
    {
        $parameter = Parameter::getInstance();
        include BASE_APP_DIR . '/modules/' . $module . '/templates/' . $action . '.tpl';
    }
}

abstract class Component
{
    abstract public function execute();

    public function view($module,$action)
    {
        $parameter = Parameter::getInstance();
        include BASE_APP_DIR . '/components/' . $module . '/templates/' . $action . '.tpl';
    }
}

abstract class Filter
{
    abstract public function execute();
}




class Parameter
{
    static private $parameter;
    private $data;
    private function __construct()
    {
        $this->data = new stdClass;
        foreach ($_GET as $key => $value) {
            $this->data->$key = $value;
        }
        foreach ($_POST as $key => $value) {
            $this->data->$key = $value;
        }
    }

    static public function getInstance()
    {
        if (!isset(self::$parameter)) {
            self::$parameter = new Parameter;
        }
        return self::$parameter;
    }

    public function __get($key)
    {
        if (!isset($this->data->$key)) {
            return '';
        }
        if (is_string($this->data->$key)) {
            echo htmlspecialchars($this->data->$key);
        } 
        return $this->data->$key;
    }

    public function __set($key,$value)
    {
        $this->data->$key = $value;
    }

    public function get($key)
    {   
        if (isset($this->data->$key)) {
            return $this->data->$key;
        } else {
            return false;
        }
    }

    public function set($key,$value)
    {   
        $this->data->$key = $value;
    }
 
    public function exist($key)
    {
        if (isset($this->data->$key)) {
            return true;
        } else {
            return false;
        }
    }

   
    public function __call($methodName,$args)
    {
        if (isset($this->data->$args[0]) && $this->data->$args[0] == $args[1]) {
            echo $methodName;
        }
    }


}


class Controller
{
    static private $controller;

    private $module;
    private $action;

    private $globalFilterPath = NULL;
    private $filters = array();


    private function __construct()
    {
        $parameter = Parameter::getInstance();
        if ($parameter->get('MO') == '') {
            $this->module = DEFAULT_MODULE;
        } else {
            $this->module = $parameter->get('MO');
        }
        if ($parameter->get('AC') == '') {
            $this->action = DEFAULT_ACTION;
        } else {
            $this->action = $parameter->get('AC');
        }

    }

    public function getCurrentModule()
    {
        return $this->module;
    }

    public function getCurrentAction()
    {
        return $this->action;
    }


    static public function getInstance()
    {
        if (!isset(self::$controller)) {
            self::$controller = new Controller;
        }
        return self::$controller;
    }

    public function forward($module = '',$action = '')
    {

        if ($module != '') {
            $this->module = $module;
        }
        if ($action != '') {
            $this->action = $action;
        }

        $className = 'Action_' . $this->action;

        $path = BASE_APP_DIR . '/modules/' . $this->module . '/actions/' . $this->action . '.php';
        if (file_exists($path)) {
            require_once $path;
        } else {
            throw new Exception('no file exists');
        }

        //全体のフィルター
        if (!isset($this->globalFilterPath)) {
            $this->globalFilterPath = BASE_APP_DIR . '/filters/Global.php';
            if (file_exists($this->globalFilterPath)) {
                require_once $this->globalFilterPath;
                $filterInstance = new Filter_Global;
                $filterInstance->execute();
            }
        }

        //モジュールごとのフィルター
        if (!isset($this->filters[$this->module])) {
            $this->filters[$this->module] = BASE_APP_DIR . '/filters/' . $this->module . '.php';
            if (file_exists($this->filters[$this->module])) {
                require_once $this->filters[$this->module];
                $filterClassName = "Filter_" . $this->module;
                $filterInstance = new $filterClassName;
                $filterInstance->execute();
            }
        }


        $instance = new $className;
        $instance->execute();
        $instance->view($this->module,$this->action);

    }

    public function component($module,$action)
    {
        $className = 'Component_' . $action;
        $path = BASE_APP_DIR . '/components/' . $module . '/actions/' . $action . '.php';
        require_once $path;

        $instance = new $className;

        $instance->execute();
        ob_start();
        $instance->view($module,$action);
        $body = ob_get_contents();
        ob_end_clean();
        return $body;
    }

}














