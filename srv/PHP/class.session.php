<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of session
 *
 * @author michel
 */
class session {
	static private $val;
	static private $session;
	static private $array;
	static private $target_session = true;
	static private $ptr;
	static private $not_set;
	static private $connected = false;

	static function connect($force = false){
		if (!$force and self::$connected and is_array(self::$session)){
			return;
		}
		self::$connected = true;
		
		if (self::$target_session){
			self::$session =& $_SESSION;
		} else{
			self::$session =& self::$array;
		}
		if (!is_array(self::$session)){
			self::$session = array();
		}
	}
	static function target_session($session = true){
		self::$target_session = $session;
		self::connect(true);
	}
	
	static function clear(){
		self::connect();
		self::$session = array();
	}
	
	static function get(){
		self::connect();
		
		if (func_num_args() == 0){
			return self::$session;
		}
		$args = func_get_args();
		
		$args = self::list_args($args);
		
		
		$val =& self::$session;
		self::$not_set = false;
		foreach($args as $key){
			if (!isset($val[$key])){
				self::$not_set = true;
				return null;
			}
			$next =& $val[$key];
			$val =& $next;
		}
		self::$val = $val;
		return ($val);
	}
	static function get_bool_str(){
		$args = func_get_args();
		$val = self::get($args);
		return ($val?'true':'false');
	}
	static function not_set(){
		return self::$not_set;
	}

	static function get_or_default(){
		self::connect();
		$arguments = func_get_args();
		$args = self::list_args($arguments);
		
		if (count($args) < 2){
			return null;
		}
		$default = array_pop($args);
		$val = self::get($args);
		if (self::$not_set){
			return $default;
		} else{
			return $val;
		}
	}
	static function list_args($fn_args){
		$args = array();
		foreach($fn_args as $arg){
			if (is_array($arg)){
				$args = array_merge($args, self::list_args($arg));
			} else {
				$args[] = $arg;
			}
		}
		return $args;
		
	}
	
	static function set(){
		self::connect();
		if (($nb_args = func_num_args()) < 2){
			return null;
		}
		switch($nb_args){
			case 2:
				return self::$session[func_get_arg(0)] = func_get_arg(1);
			case 3:
				return self::$session[func_get_arg(0)][func_get_arg(1)] = func_get_arg(2);
			case 4:
				return self::$session[func_get_arg(0)][func_get_arg(1)][func_get_arg(2)] = func_get_arg(3);
			case 5:
				return self::$session[func_get_arg(0)][func_get_arg(1)][func_get_arg(2)][func_get_arg(3)] = func_get_arg(4);
			default:
				$args = func_get_args();
				$special_array = new array_fncts(self::$session);
				self::$session = $special_array->set($args);
		}
        return null;
	}
	static function un_set(){
		self::connect();
		if (($nb_args = func_num_args()) == 0){
			return;
		}
		switch($nb_args){
			case 1:
				unset(self::$session[func_get_arg(0)]);
				return;
			case 2:
				unset(self::$session[func_get_arg(0)][func_get_arg(1)]);
				return;
			case 3:
				unset(self::$session[func_get_arg(0)][func_get_arg(1)][func_get_arg(2)]);
				return;
			case 4:
				unset(self::$session[func_get_arg(0)][func_get_arg(1)][func_get_arg(2)][func_get_arg(3)]);
				return;
		}
		
	}
	static function test_set(){
		self::connect();
		if (($nb_args = func_num_args()) == 0){
			return null;
		}
		if (!is_array(self::$session)){
			return false;
		}
		switch($nb_args){
			case 1:
				return isset(self::$session[func_get_arg(0)]);
			case 2:
				return isset(self::$session[func_get_arg(0)][func_get_arg(1)]);
			case 3:
				return isset(self::$session[func_get_arg(0)][func_get_arg(1)][func_get_arg(2)]);
			case 4:
				return isset(self::$session[func_get_arg(0)][func_get_arg(1)][func_get_arg(2)][func_get_arg(3)]);
		}
		return false;
	}

    static function id(){
        if (unitTesting::$testing and unitTesting::$sessionId){
            return unitTesting::$sessionId;
        }
        return session_id();
    }
	

}

?>
