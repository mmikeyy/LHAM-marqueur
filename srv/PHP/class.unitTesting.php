<?php

/**
 * Created by PhpStorm.
 * User: micra_000
 * Date: 2016-02-07
 * Time: 17:14
 * @codeCoverageIgnore
 */
class unitTesting
{
    static $testing = false;
    static $no_exceptions = false;
    static $last_result;
    static $intercept_tag;
    static $intercept_val;
    static $bypass_courriel_adr_test_mode;
    static $force_courriel_send;
    static $config_vals = [];
    static $sessionId;

    static $now;
    
    static $tags = [];

    static function succes($res = true)
    {

        if (self::$no_exceptions){
            return $res;
        }
        throw new testSucces($res);
    }
    static function echec($res = false)
    {
        if (self::$no_exceptions){
            return $res;
        }
        throw new testEchec($res);
    }

    static function set_intercept($tag = null)
    {
        self::$intercept_tag = $tag;
        self::set_intercept_val();
    }
    static function set_intercept_val($val = null)
    {
        self::$intercept_val = $val;
    }
    static function get_intercept()
    {
        return self::$intercept_val;
    }
    static function getNow($new = false)
    {
        if ($new or !self::$now){
            self::$now = date('Y-m-d H:i:s');
        }
        return self::$now;
    }
    static function getSQLNow($new = false)
    {
        if (!self::$testing){
            return 'NOW()';
        }
        return db::sql_str(self::getNow($new));
    }

    static function getTime($diff = '00:00:00')
    {
        /**
         * @var string $t
         **/
        $res = db::query("
                SELECT ADDTIME(now(), '$diff') t
            ", 'acces_table');
        extract($res->fetch_assoc());
        return $t;

    }
    static function is_config_set($key)
    {
        return array_key_exists($key, self::$config_vals);
    }
    static function config_val($key)
    {
        return self::$config_vals[$key];
    }
    static function set_config_val($key, $val = null)
    {
        if (is_null($val) and is_array($key)){
            self::$config_vals = array_merge(self::$config_vals, $key);
        } else {
            self::$config_vals[$key] = $val;
        }

    }
    static function get_config_val($key)
    {
        return (self::is_config_set($key) ? self::config_val($key) : cfg_yml::get_($key));
    }
    static function unset_config_val($key)
    {
        if (array_key_exists($key, self::$config_vals)){
            unset(self::$config_vals[$key]);
        }
    }
    static function reset_config_vals()
    {
        self::$config_vals = [];
    }
    
    static function reset_tags($val = [])
    {
        if (!is_array($val) and $val){
            $val = [$val];
        }
        self::$tags = $val;
    }
    static function is_tag_set($val)
    {
        return in_array($val, self::$tags);
    }
    static function set_tag($val)
    {
        if (!self::is_tag_set($val)){
            self::$tags[] = $val;
        }
    }
    static function unset_tag($val)
    {
        if (!is_array($val)){
            $val = [$val];
        }
        self::$tags = array_diff(self::$tags, $val);
    }
    
    static function setSessionId($id = null){
        if (is_null($id)){
            self::$sessionId = session_id();
        } else {
            self::$sessionId = $id;
        }
    }
    static function getSessionId(){
        if (is_null(self::$sessionId)){
            self::setSessionId();
        }
        return self::$sessionId;
    }
    
}