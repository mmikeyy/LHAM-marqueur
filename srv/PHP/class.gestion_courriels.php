<?php


class gestion_courriels {

	public $id_membre;
	
	public $courriel;
	public $sql_courriel;
	
	public $code;
	
	public $code_pour_nouveau_courriel = 0;
	
	static $msg;
	public $limite_verif = 4;
	public $last_msg = '';
	public $last_msg_index = '';
	
	private $table = 'membres';
	
	function __construct($val){
		if (is_numeric($val)){
			$this->id_membre = $val;
		} else {
			$this->set_courriel($val);
			
		}
		
	}
	function set_table($table){
		$this->table = $table;
	}
	
	function code_est_pour_nouv_courriel($mode = 1){
		$this->code_pour_nouveau_courriel = $mode;
		return $this;
	}
	
	function set_courriel($courriel){
		if ($this->courriel == $courriel){
			return $this;
		}
		$this->courriel = $courriel;
		$this->sql_courriel = db::sql_str($courriel);
		return $this;
	}
	function set_code($code){
		$this->code = $code;
		return $this;
	}
	function set_id_membre($id){
		$this->id_membre = $id;
		return $this;
	}
	
	function init_msg(){
		if (self::$msg){
			return;
		}
		self::$msg = new msg('class_gestion_courriels', $this);
	}
	function fin($msg, $info){
		$this->init_msg();
		return self::$msg->fin($msg, $info);
	}
	function dbfin($info = ''){
		return http_json::conditional_fin('acces_table',$info);
	}
		
	function verifier_code(){
		
	
		$res = db::query("
			SELECT code_validation, code_pour_nouveau_courriel, nb_verif_code, if(date_add(date_code_validation, interval 1 day) > now(),0,1) code_perime, nouveau_courriel
			FROM $this->table
			WHERE id = $this->id_membre
			FOR UPDATE
		") or $this->dbfin('verifier code');


		$res->num_rows  or $this->fin('introuvable');
		extract($res->fetch_assoc());
		debug_print_once("class = $this->code_pour_nouveau_courriel; var = $code_pour_nouveau_courriel");
		if ($this->code_pour_nouveau_courriel == $code_pour_nouveau_courriel){
			debug_print_once("sont égaux");
		} else {
			debug_print_once("pas égaux");
		}
		if ('1' == 1){
			debug_print_once("1=1");
		}
		($this->code_pour_nouveau_courriel == $code_pour_nouveau_courriel) or $this->fin('mauvais_type_code');
		debug_print_once("type code ok");
		if ($code_pour_nouveau_courriel){
			$this->courriel == $nouveau_courriel or $this->fin('adresse_invalide');
		}

		($nb_verif_code <  $this->limite_verif) or $this->fin('trop_de_tentatives');

		!$code_perime or $this->fin('code_perime');

		if ($code_validation != $this->code){
			$this->incremente_tentatives();
			$this->fin('mauvais_code');
		}

		return true;
		
	}
	function incremente_tentatives(){
		$res = db::query("
			UPDATE $this->table
			SET nb_verif_code = nb_verif_code + 1
			WHERE id = $this->id_membre
		") or $this->dbfin('incremente tentatives');
		return true;
	}
	
	function id_pour_code_courriel(){
		$res = db::query($q = "
			SELECT id, if(date_code_validation < date_sub(now(), interval 1 day) or date_code_validation is null, 1, 0) code_perime
			FROM $this->table
			WHERE code_validation = $this->code 
				AND courriel = $this->sql_courriel
				AND code_pour_nouveau_courriel = $this->code_pour_nouveau_courriel
		") or $this->dbfin('recherche id pour code courriel');
		
		if ($res->num_rows == 0){
			$this->fin('introuvable');
		} else if ($res->num_rows == 1){
			extract($res->fetch_assoc());
			if ($code_perime){
				http_json::$data['perime'] = 1;
				$this->fin('code_perime');
			}
			return $id;
		}
		while ($row = $res->fetch_assoc()){
			$liste[] = $row['id'];
			if ($row['code_perime']){
				http_json::$data['perime'] = 1;
				$this->fin('code_perime');
			}
		}
		return $liste;
		
	}
	function assigner_courriel(){
		$res = db::query("
			UPDATE $this->table
			SET courriel = $this->sql_courriel,
			code_validation = null
			WHERE id = $this->id_membre
		") or $this->dbfin('assigner courriel');
		
		return true;
	}
	function enlever_courriel_aux_autres(){
		$res = db::query("
			UPDATE $this->table
			SET courriel = '',
			code_validation = null
			WHERE id <> $this->id_membre AND courriel = $this->sql_courriel
		") or $this->dbfin('enlever courriel aux autres');
		
		return true;
		
	}
	
	function assigner_codes_pour_courriel(){
		
		$this->courriel or $this->fin('adresse_manque');

		$res = db::query("
			SELECT id
			FROM $this->table
			WHERE courriel = $this->sql_courriel
			FOR UPDATE
		") or $this->dbfin('verrouillage');

		$res->num_rows  or $this->fin('adresse_introuvable', $this->courriel);

		while ($row = $res->fetch_assoc()){
			$liste[] = $row['id'];
		}
		$liste_ = implode(',',$liste);

		$res = db::query("
			UPDATE $this->table
			SET code_validation = 100000 + 9999999*rand(),
				code_pour_nouveau_courriel = 0,
				nb_verif_code = 0,
				date_code_validation = now()
			WHERE id in ($liste_)
		") or $this->dbfin('ecriture nouveaux codes');


		return $liste;
	}
	function assigner_code_a_membre($code = 0){
		if ($code == 0){
			$code = $this->make_code();
		}
		if (!is_numeric($this->id_membre)){
			$this->fin('id_membre_manque');
		}
		
		$res = db::query(debug_print_once("
			UPDATE $this->table
			SET code_validation = $code,
			date_code_validation = now(),
			nb_verif_code = 0,
			code_pour_nouveau_courriel = $this->code_pour_nouveau_courriel
			WHERE id = $this->id_membre
			LIMIT 1
		")) or $this->dbfin('assignation code');
		return $code;
	}
	function make_code(){
		$this->code = mt_rand(100000,9999999);
		return $this->code;
	}
	function verifier_appartenance_courriel($id = 0, $lock = 0){
		$lock = db::lock_statement($lock);
		if (!$id){
			$id = $this->id_membre;
		}
		
		$champ_courriel = ($this->code_pour_nouveau_courriel?'nouveau_courriel':'courriel');
		
		$res = db::query(debug_print_once("
			SELECT count(*) nb
			FROM $this->table
			WHERE id = $id AND $champ_courriel = $this->sql_courriel
			$lock
		") )or $this->dbfin('verification appartenance courriel');
		extract($res->fetch_assoc());
		return $nb;
	}
	

	function accepte_nouveau_courriel_fn($liste){
		if (is_array($liste)){
			$cond = 'id in (' . implode(',', $liste) . ')';
		} else {
			$cond = "id = $liste";
		}
		
		db::query("
			UPDATE $this->table
			SET courriel = nouveau_courriel,
				nouveau_courriel = '',
				code_validation = null,
				date_code_validation = null,
				nb_verif_code = 0
			WHERE $cond AND nouveau_courriel = $this->sql_courriel
		") or $this->fin_db('acceptation nouveau courriel');
		return db::get('affected_rows');
	}
	function accepte_nouveau_courriel(){
		return $this->accepte_nouveau_courriel_fn($this->id_membre);
	}
	function accepte_nouveau_courriel_famille(){
		return $this->accepte_nouveau_courriel_fn(gestion_famille::conjoints_et_enfants($this->id_membre));
	}
}