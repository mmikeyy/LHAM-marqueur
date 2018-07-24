<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of class
 *
 * @author michel
 */
use Underscore\Types\Arrays;
class cfg_yml{
	static $fname;
	static $cfg;
	static $is_dirty;
	static $abreviations = array();
    static $search_equipe = false;
    static $replace_equipe = false;

	static function load(){
		if (is_null(self::$fname)){
			self::set_default_name();
		}
		if (!is_null(self::$cfg)){
			return;
		}
		self::$cfg = sfYaml::load(self::$fname);

		self::$abreviations = array();
        $noms_equipes = self::get('noms_equipes');
        if (is_array($noms_equipes) and (array_key_exists('nom_1', $noms_equipes) or array_key_exists('NOM_1', $noms_equipes))){
            $noms_equipes['std'] = array();
            foreach($noms_equipes as $ind=>$nom){
                if (preg_match('#^nom_\d+$#i', $ind)){
                    $noms_equipes['std'][] = $nom;
                    unset($noms_equipes[$ind]);
                    self::$is_dirty = true;
                } 
            }
            self::set($noms_equipes, 'noms_equipes');
            self::save();
        }
	}
	static function create($contents = null){
		
		if (is_null($contents)){
            self::$cfg = array();
        } else {
			self::$cfg = $contents;
		}
	}
	
	static function set_default_name(){

		self::$fname = __DIR__. '/../cfg/config.yml';
	}
	
	static function clear(){
		self::$cfg= null;
		self::$fname = null;
	}
	static function reload(){
		self::$cfg = null;
		self::load();
	}
    static function loadFile($fname)
    {
        self::$cfg = null;
        self::$fname = $fname;
        self::$is_dirty = false;
        self::load();
    }
	static function set_name($name){
		if (!dirname($name)){ // si le nom contient un path
			self::$fname = __DIR__ . '/../cfg/' . $name; // positionner nom par rapport à root
		} else{
			self::$fname = $name;
		}
        
	}
	static function get(){
		self::load();
        $val = self::$cfg;
        if (func_num_args() == 0){
            return $val;
        }
        $params = array();
        if (func_num_args() == 1 and is_array(func_get_arg(0))){
            $params = func_get_arg(0);
        } else {
            for($i=0, $nb = func_num_args(); $i < $nb; $i++){
                $params[] = func_get_arg($i);
            }
        }
		foreach($params as $param){
            if (is_array($val)){
                if (array_key_exists($param, $val)){
                    $val = $val[$param];
                } else {
                    return null;
                }
            } else {
                return null;
            }
        }
        
		return $val;
	}
    static function is_set()
    {
		self::load();
        $val = self::$cfg;
        if (func_num_args() == 0){
            return false;
        }
        $params = array();
        if (func_num_args() == 1 and is_array(func_get_arg(0))){
            $params = func_get_arg(0);
        } else {
            for($i=0, $nb = func_num_args(); $i < $nb; $i++){
                $params[] = func_get_arg($i);
            }
        }
		foreach($params as $param){
            if (is_array($val)){
                if (array_key_exists($param, $val)){
                    $val = $val[$param];
                } else {
                    return false;
                }
            } else {
                return false;
            }
        }
        
		return true;
    }
	static function get_section(){
		self::load();
		return self::get(func_get_args());
	}
	static function set_section(){
		$refl = new ReflectionMethod(__CLASS__, 'set');
        $refl->invokeArgs(null, func_get_args());
	}
	static function set_save_section(){
		self::set(func_get_args());
		self::save();
	}
    static function reset($val)
    {
        self::$cfg = $val;
        self::$is_dirty = true;
    }
    /**
     * paramètres = $val, $ind1, ..., $indn => va faire self::$cfg[$ind1]...[$indn] = $val
     */
	static function set(){
		self::load();
        if (func_num_args() < 2 and !(func_num_args() == 1 and is_array(func_get_arg(0)) and count(func_get_arg(0)) > 1)){
            throw new Exception('nb de paramètres insuffisant');
        }
        
        if (func_num_args() == 1){
            $params = func_get_arg(0);
        } else {
            $params = func_get_args();
        }
        
        if (!is_array(self::$cfg)){
            self::$cfg = array();
        }
        $dest =& self::$cfg;
        
        
        
        for($i = 1, $nb = count($params), $max = $nb-1; $i < $nb; $i++){
            $ind = $params[$i];
            if (!is_array($dest)){
                $dest = array();
            }
            if (!array_key_exists($ind, $dest) or !is_array($dest[$ind]) ){
                $dest[$ind] = array();
            }
            if ($i == $max){
                $dest[$ind] = $params[0];
                break;
            }
            $dest =& $dest[$ind];
        }
		
		self::$is_dirty = true;
	}
	static function save(){
		if (!self::$is_dirty){
			return;
		}
		self::load();
		file_put_contents(self::$fname, sfYaml::dump(self::$cfg, 4));
		self::$is_dirty = false;
	}
	static function set_save(){
		
		self::set(func_get_args());
		self::save();
	}
	
	public function __call($name, $args){
		if (strpos($name, 'get_')=== 0){
			return self::get_by_name(substr($name, 4));
		} else if (strpos($name, 'set_') === 0){
			return self::set_by_name(substr($name, 4), $args[0]);
		}
		return null;
	}
	public function __get($name){
		return self::get_by_name($name);
	}

	static private function get_by_name($name){
		$ind = self::get_indices($name);
		if (!is_array($ind)){
			return null;
		}
		//echo "\ngetting {$ind[1]} / {$ind[2]}\n";
		return self::get($ind[1], $ind[2]);
	}
	
	static private function set_by_name($name, $val){
		$ind = self::get_indices($name);
		if (!is_array($ind)){
			return null;
		}
		return self::set($ind[1], $val, $ind[2]);
	}
	
	static public function get_indices($name){
		if (($nb = substr_count($name, '_')) == 1){
			$separ = '_';
		} else if ($nb > 1 and substr_count($name, '__') == 1){
			$separ = '__';
		} else{
			return null;
		}
		
		$nb = preg_match("#^(.+)$separ(.+)$#", $name, $res);
		if ($nb == 0){
			return null;
		}
		return $res;
		
	}
	static function update_saisons_from_table(){
		$courante = -1;
		$prochaine = -1;
		$inscription = -1;
		
		$table_saison = saisons::$table_saisons;
		
		$res = db::query("
			SELECT id, statut, inscription
			FROM $table_saison
			WHERE statut in (1,2)
		");
		
		if ($res->num_rows){
			while ($row = $res->fetch_assoc()){
				//print_r($row);
				if ($row['statut'] == 1){
					$courante = $row['id'];
					if ($row['inscription']){
						$inscription = $courante;
					}
				} else if ($row['statut'] == 2){
					$prochaine = $row['id'];
					if ($row['inscription']){
						$inscription = $prochaine;
					}
				}
			}
		}
		self::set($courante, 'saisons', 'courante');
		self::set($prochaine, 'saisons', 'prochaine');
		self::set($inscription, 'saisons', 'inscription');
		self::save();
	}
	static function abreviation_equipe($nom){
        if (!is_string($nom)){
            return $nom;
        }
		if (key_exists($nom, self::$abreviations)){
			return self::$abreviations[$nom];
		}
        if (self::$search_equipe === false){
            self::$search_equipe = self::get('abreviations', 'equipe_cherche');
            self::$replace_equipe = self::get('abreviations', 'equipe_remplace');
        }
		if (is_string(self::$search_equipe) and is_string(self::$replace_equipe)){
			$abrev = preg_replace(self::$search_equipe, self::$replace_equipe, $nom);
		} else{
			$abrev = $nom;
		}
		self::$abreviations[$nom] = $abrev;
		return $abrev;
	}
    static function abreviation_equipe_(&$array, $ind = null)
    {
        $liste = array();
        if (is_null($ind)){
            foreach($array as $index=>$val){
                $liste[] = $index;
            }
        } else if (is_string($ind)){
            $liste = explode(',', $ind);
        } else if (is_array($ind)){
            $liste = $ind;
        }
        foreach($liste as $ind){
            if (array_key_exists($ind, $array)){
                $array[$ind] = self::abreviation_equipe($array[$ind]);
            }
        }
    }
    static function abreviation_equipe_array(&$array, $ind = null)
    {
        $liste = array();
        if (is_null($ind)){
            foreach($array as $index=>$val){
                $liste[] = $index;
            }
        } else if (is_string($ind)){
            $liste = explode(',', $ind);
        } else if (is_array($ind)){
            $liste = $ind;
        }
        foreach($array as &$item){
            foreach($liste as $ind){
                if (array_key_exists($ind, $item)){
                    $item[$ind] = self::abreviation_equipe($item[$ind]);
                }
            }
        }
    }
	static function contextuel_msg($context_string){
		$langue = lang::get_lang();
		$fmt = self::get('general', 'indicateur_contenu_contextuel_format');
		if (!$fmt || strpos($fmt, '%s')=== false){
			return '';
		}
		$msg = self::get('general', 'msg_contextuel_' . $langue);
		if (!$msg or strpos($msg, '%s') === false){
			return '';
		}
		return sprintf($fmt, sprintf($msg, $context_string));
	}
	static function remove_key(){
		self::load();
        if (!is_array(self::$cfg)){
            return;
        }
        $dest =& self::$cfg;
		for($i = 0, $nb = func_num_args(), $dern = $nb-1; $i < $nb; $i++){
            $ind = func_get_arg($i);
            if (!array_key_exists($ind, $dest)){
                return;
            }
            if ($i == $dern){
                unset($dest[$ind]);
                self::$is_dirty = true;
                return;
            }
            if (!is_array($dest[$ind])){
                return;
            }
            $dest =& $dest[$ind];
        }
	}
    static function mergeDefault($file)
    {
        if (!file_exists($file)){
            return false;
        }
        $default = sfYaml::load($file);
        if (is_array($default)){
            self::$cfg = array_merge($default, self::$cfg);
            return true;
        }
        return false;
    }

    static function set_($key, $val)
    {
        self::load();
        self::$cfg = Arrays::set(self::$cfg, $key, $val);
        self::$is_dirty = true;
    }
    static function get_($key)
    {
        if (unitTesting::$testing and unitTesting::is_config_set($key)){
            return unitTesting::config_val($key);
        }
        self::load();
        return Arrays::get(self::$cfg, $key);
    }

}
?>
