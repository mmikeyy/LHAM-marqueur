<?php
use Underscore\Types\Arrays;
/**
 * Created by PhpStorm.
 * User: micra
 * Date: 2016-11-05
 * Time: 20:21
 */
class twitter extends widget
{
    /**
     * @var Arrays $cfg
     **/
    static $cfg;
    static $defaut;

    function __construct()
    {
        parent::__construct();
    }

    static function load_cfg()
    {
        if (self::$cfg){
            return;
        }
        self::$cfg = Arrays::from(Spyc::YAMLLoad(__DIR__ . '/widgets.yml'));
        $defaut = self::$cfg->get('defaut');
        if ($defaut){
            self::$defaut = self::$cfg->get($defaut);
        }


    }

    function render($row)
    {
        self::load_cfg();
        $params = $row['contexte_widget_params'];
        $html = '';
        if ($params and $params->tag){
            $tag = $params->tag;
            if (self::$cfg->$tag) {

                $html = self::$cfg->obtain()[$tag]['html'];
                ;

                if ($params->chromes and is_array($params->chromes) and count($params->chromes)) {
                    $chromes = implode(' ', $params->chromes);

                }
            }
        }






        return $html;

    }
}