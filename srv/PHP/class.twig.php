<?php
//require_once dirname(__FILE__) . '/Twig/Autoloader.php';
Twig_Autoloader::register();

class twig
{
    static public $loader;
    static public $twig;
    static public $currentTemplate;
    static function init()
    {
        if (is_null(self::$loader)){
            self::$loader = new Twig_Loader_Filesystem(__DIR__ . '/Twig_templates');
            self::$twig = new Twig_Environment(self::$loader, array(
                'cache' => __DIR__ . '/Twig_cache',
                'auto_reload' => true
                ));
        }
    }
    
    static function loadTemplate($name)
    {
        self::init();
        return self::$currentTemplate = self::$twig->loadTemplate($name);
    }
    
    static function render($name, $params = null)
    {
        if (is_null($params) and is_array($name) and !is_null(self::$currentTemplate)){
            return self::render(self::$currentTemplate, $name);
        }
        if (is_null($params)){
            $params = array();
        }
        self::init();
        return self::$twig->render($name, $params);
    }
    static function display($name, $params){
        echo self::render($name, $params);
    }
}
?>
