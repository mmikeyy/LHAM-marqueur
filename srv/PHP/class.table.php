<?php

class table extends http_json {
	protected $table;

	private $regex_champs = '';	
	
	public $champs;
	public $liste_champs;
	public $champ_clef;
	public $champs_read_only = array();
	public $expressions = array();
	
	public $row = array();
	public $id_row = null;
	public $found = false;
	
	function __construct($table = ''){
		parent::__construct();
		
		$this->table = $table;
	}
	
	function regex_champs(){
		if (strlen($this->regex_champs)){
			return $this->regex_champs;
		}
		$regex = array();
		foreach($this->champs as $champ=>$validation){
			$regex[] = $champ;
		}
		$this->regex_champs = '#^(' . implode('|', $regex). ')$#';
		return $this->regex_champs;
	}
	function validation_str($fld, $extra = ''){
		if ($extra and substr($extra, 0, 1) != ';'){
			$extra = ";$extra";
		}
		return $fld . ';' . $this->champs[$fld] . $extra;
	}
	function liste_champs($prefixe = ''){
		if ($this->liste_champs){
			if ($prefixe == ''){
				return $this->liste_champs;
			} else{
				return $prefixe . '.' . str_replace(',', ", $prefixe.", $this->liste_champs);
			}
		}
		
		
		
		$this->liste_champs = array();
		$to_ret = array();
		if ($prefixe){
			$prefixe .= '.';
		}
		foreach($this->champs as $fld=>$valid){
			$this->liste_champs[] = $fld;
			$to_ret[] = $prefixe . $fld;
		}
		$this->liste_champs = implode(',', $this->liste_champs);
		return implode(',', $to_ret);
	}
	
	function expand_expressions($champs, $prefix = ''){
		if (is_string($champs)){
			$champs = explode(',', $champs);
		}
		if ($prefix){
			$prefix .= '.';
		}
		foreach($champs as &$champ){
			if (isset($this->expressions[$champ])){
				$champ = sprintf($this->expressions[$champ], $prefix) . ' ' . $champ;
			}
		}
		
		return implode(',', $champs);
	}
	
	function liste_champs_tous($prefixe = ''){
		$to_ret = $this->liste_champs($prefixe);
		if ($prefixe){
			$prefixe .= '.';
		}
		$ajout = array();
		foreach($this->champs_read_only as $champ){
			$ajout[] = $prefixe . $champ;
		}
		if (count($ajout)){
			$to_ret .= ',' . implode(',', $ajout);
		}
		return $to_ret;
	}
	function liste_expressions($prefixe = ''){
		if (!$prefixe){
			return implode(',', array_keys($this->expressions));
		}
		$to_ret = array();
		foreach($this->expressions as $champ){
			$to_ret[] = "$prefixe.$champ";
		}
		return implode(',', $to_ret);
	}

	function validate_updates($updates){
		$validation = array();
		$regex_champs = $this->regex_champs();
		foreach($updates as $fld=>$val){
			if (!preg_match($regex_champs, $fld)){
				$this->fin('mauvais_param', "table $this->table; champ $fld");
			}
			$validation[] = "$fld;" . $this->champs[$fld];
		}
		//debug_print_once("check params table $this->table = " . print_r($updates));
		// faire en sorte que les valeurs validées soient prises dans $update plutôt que dans $_REQUEST]
		self::set_source_check_params_once($updates); 
	
		//debug_print_once('validation = ' . print_r($validation,1));
		//debug_print_once("updates = " . print_r($updates,1));
		return self::check_params($validation);
		
	}
	function apply_updates($id, $assignations){
		if (count($assignations) == 0){
			return;
		}
		$assignment = array();
		foreach($assignations as $fld=>$val){
			$val = db::sql_str($val);
			$assignment[] = "$fld = $val";
		}
		$assignment = implode(',', $assignment);
		$res = db::query($q = "
			UPDATE $this->table
			SET $assignment
			WHERE $this->champ_clef = $id
		", 
				'acces_table', 'update ' . $this->table);
		//debug_print_once($q);
		
	}
	
	function insert($assignations){
		if (count($assignations) == 0){
			return false;
		}
		$assignment = array();
		foreach($assignations as $fld=>$val){
			$val = db::sql_str($val);
			$assignment[] = "$fld = $val";
		}
		$assignment = implode(',', $assignment);
		$res = db::query($q = "
			INSERT INTO $this->table
			SET $assignment
		", 
				'acces_table', 'update ' . $this->table);
		return db::get('insert_id');
		
	}
	
	
	function update($id, $updates){
		
		$assignations = $this->validate_updates($updates);
		if (!is_array($assignations)){
			$this->fin('assignation_non_valable');
		}
		
		if (method_exists($this, 'additional_validations')){
			$this->additional_validations($assignations);
		}
		
		$this->apply_updates($id, $assignations);

	}
	
	public function lock($val){
		$condition_lock = '';
		if (is_array($val)){
			$liste = array();
			foreach($val as $v){
				$liste[] = db::sql_str($v);
			}
			$condition_lock = 'in (' . implode(',', $liste) . ')';
		} else{
			$condition_lock = ' = ' . db::sql_str($val);
		}
		
		$res = db::query("
			SELECT count(*) nb
			FROM $this->table
			WHERE $this->champ_clef $condition_lock
		",
				'acces_table', 'verrouillage');
		extract($res->fetch_assoc());
		return $nb;
	}
	static function lock_statement($level=0){
		switch($level){
			case 1:
			case 'share':
				return 'LOCK IN SHARE MODE';
			case 2:
			case 'update':
				return 'FOR UPDATE';
			default:
				return '';
		}
	}

	static function verif_admin_ou_gerant($id, $return_bool = false){
		if (!perm::test('admin') and !perm::est_gerant_de(session::get('id_visiteur'), $id)){
			if (!$return_bool){
				self::conditional_fin('non_autorise');
			} else{
				return false;
			}
		}
		return true;
	}
	
	function get_row($id, $flds = '*', $lock = 0){
		$this->id_row = $id;
		db::sql_str_($id);
		$lock = db::lock_statement($lock);
		
		$res = db::query("
			SELECT $flds
			FROM $this->table
			WHERE $this->champ_clef = $id
			$lock
		",
				'acces_table','get theme');
		$this->found = ($res->num_rows != 0);
		
		if ($this->found){
			$this->row = $res->fetch_assoc();
		} else {
			$this->row = array();
		}
		return $this->row;
	}
	
	function select_champs($regex, $prefix = 0){
		if ($prefix){
			$prefix .= '.';
		}
		$liste = array();
		foreach($this->champs as $champ=>$spec){
			if (preg_match($regex, $champ)){
				$liste[] = "$prefix$champ";
			}
		}
		return $liste;
	}
}
?>
