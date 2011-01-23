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
    
    //対象が文字列の場合は出力、それ以外の場合は取得
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

    public function clear($key)
    {
        if (isset($this->data->$key)) {
            unset($this->data->$key);
        }
    }

    //引数が１つの場合は、メッソド名に対応した配列から引数をキーにした値を出力
    //引数が２つの場合は、１つめの引数に対応したキーの値と２つめの引数の値を比較して
    //同じ値がある場合は、メソッド名を出力
    public function __call($methodName,$args)
    {

        switch (count($args)) {
            case 1:
                $option = $this->data->$methodName;
                echo $option[$args[0]];
                break;
            case 2:    
                if (isset($this->data->$args[0])) {
                    if (is_array($this->data->$args[0])) {
                        if (in_array($args[1],$this->data->$args[0])) {
                            echo $methodName;
                        }
                    } else {
                        if ($this->data->$args[0] == $args[1]) {
                            echo $methodName;
                        }
                    }
                }
                break;
            default:
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














