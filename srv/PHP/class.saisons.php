<?php

class  saisons extends http_json{
	static public $saisons = array(0=>'aucune', 1=>'courante',2=>'prochaine',3=>'inscription');
	static public $cond_statut = array(
		'courante'=>'statut=1',
		'prochaine'=>'statut=2',
		'inscription'=>'inscription',
		'aucune' =>1
	);
	static public $table_saisons = 'saisons';
	static public $en_saison = array();
	static public $data_saisons;
    static public $ref = [];
	
	function __construct($table = 'saisons'){
		parent::__construct();
		
		self::$table_saisons = $table;
		
		self::execute_op();
	}
	static function choisir_fichier_saisons($nom_table){
		self::$table_saisons = $nom_table;
	}
	
	// obtenir liste avec description et dates pour saisons courante et prochaine
	function fn_get_list(){
		$table_saisons = self::$table_saisons;
		
		$res = db::query($q = "

			SELECT *
			FROM $table_saisons
			WHERE statut in (1, 2)

		",
				'acces_table');
		//debug_print_once($q);
		self::$data['liste'] = array();

		if($res->num_rows){
			while ($row = $res->fetch_assoc()){
				self::$data['liste'][$row['statut'] == '1'?'courante':'proch'] = $row;
			}
		}

		$this->succes();
	}
	
	function fn_get_list_courante_et_avant(){
		$table_saisons = self::$table_saisons;
		
		$res = db::query("
			SELECT *
			FROM $table_saisons
			WHERE statut <= 1
			ORDER BY debut DESC
		", 'acces_table', 'liste_saisons');
		self::$data['liste'] = array();
		if ($res->num_rows){
			while ($row = $res->fetch_assoc()){
				self::$data['liste'][] = $row;
			}
		}
		$this->succes();
	}
	
	/**
	 * stocker le numéro de saison auquel s'applique le tableau des âges
	 */
	function fn_set_saison_tableau(){
		$table_saisons = self::$table_saisons;
		
		extract(self::check_params(
				'id_saison;unsigned'
		));
		
		
		$res = db::query("

			SELECT id saison_tableau, nom_saison nom_saison_tableau
			FROM $table_saisons
			WHERE id = $id_saison 

		",
				'acces_table');

		if ($res->num_rows == 0){
			$this->fin('introuvable');
		}
		self::$data = $res->fetch_assoc();

	
		//cfg::set_save('saison_tableau_ages', $id_saison, 'general');
		cfg_yml::set_save($id_saison, 'general', 'saison_tableau_ages');
		
		$this->succes();
	}
    function fn_get_liste_toutes_saisons(){
        $res = db::query("
            SELECT id, nom_saison
            FROM saisons
            ORDER BY debut DESC
		",
			'acces_table','');
        self::$data['liste'] = db::result_array($res);
        $this->succes();
    }

	
	/**
	 *
	 * @return  int = no de saison courante depuis fichier ini
	 */
	static function courante(){
		self::load_data_saisons();
        if (array_key_exists(1, self::$data_saisons)){
            return self::$data_saisons[1]['id'];
        }
        return 0;
	}
	static function courante_lock($saison = null){
		if (is_null($saison)){
			$saison = self::get('courante');
		} else{
			$saison = self::courante($saison);
			if ($saison === false){
				return false;
			}
		}
			
		if (self::lock($saison)){
			return $saison;
		}
		return false;
	}
	/**
	 *
	 * @return  int = no de saison prochaine depuis fichier ini
	 */
	static function prochaine($saison = null){
        self::load_data_saisons();
        if (array_key_exists(2, self::$data_saisons)){
            return self::$data_saisons[2]['id'];
        }
        return 0;
	}
	static function prochaine_lock($saison = null){
		if (is_null($saison)){
			$saison = self::get('prochaine');
		} else {
			$saison = self::prochaine($saison);
			if ($saison === false){
				return false;
			}
		}
		if (self::lock($saison)){
			return $saison;
		}
		return false;
	}
	/**
	 *
	 * @return  int = no de saison d'inscription depuis fichier ini
	 */
	static function inscription($saison = null){
		if (is_null($saison)){
			return self::get('inscription');
		} else{
			return self::set('inscription', $saison);
		}
	}
	static function inscription_lock($saison = null){
		if (is_null($saison)){
			$saison = self::get('inscription');
		} else {
			$saison = self::inscription($saison);
			if ($saison === false){
				return false;
			}
		}
		if (self::lock($saison)){
			return $saison;
		}
		return false;
	}
	
	static private function lock($saison){
		$table_saisons = self::$table_saisons;
		
		if (!is_numeric($saison)){
			return false;
		}
		
		$res = db::query("
			SELECT id
			FROM $table_saisons
			WHERE id = $saisons
			FOR UPDATE
			");
		if (!$res or $res->num_rows == 0){
			return false;
		}
		return true;
	}
	
	static function get($laquelle){

        self::load_data_saisons();
        switch($laquelle){
            case '1':
            case 'courante':
                if (array_key_exists(1, self::$data_saisons)){
                    return self::$data_saisons[1]['id'];
                } else {
                    return null;
                }
                break;
            case '2':
            case 'prochaine':
                if (array_key_exists(2, self::$data_saisons)){
                    return self::$data_saisons[2]['id'];
                } else {
                    return null;
                }
                break;
            case '3':
            case 'inscription':
            if (array_key_exists(3, self::$data_saisons)){
                return self::$data_saisons[3]['id'];
            } else {
                return null;
            }
            break;

        }
        return null;
		

	}
	
	static function set($statut, $id){
		$table_saisons = self::$table_saisons;
		
		$statut = self::to_integer_type($statut);
		if ($statut === false){
			return false;
		}
		if ($statut == 1 or $statut == 2){
			db::query("
				UPDATE $table_saisons
				SET statut = 0
				WHERE statut = $statut
			", 
					'acces_table', "update saison set statut 0 where statut = $statut");
			
			db::query("
				UPDATE $table_saisons
				SET statut = $statut
				WHERE id = $id
				", 
					'acces_table', "update $table_saisons set statut = $statut");
			
			// s'assurer que la saison d'inscription n'est pas une saison autre que courante ou prochaine
			$res = db::query("
				UPDATE $table_saisons
				SET inscription = 0
				WHERE statut = 0
				
			");
			
			if (!db::commit()){
				return false;
			}
			cfg_yml::update_saisons_from_table();
			return true;
		} else if ($statut == 3){
			db::query("
				UPDATE $table_saisons
				SET inscription = 0
				WHERE inscription
			",
					'acces_table', 'inscription à zéro');
			db::query("
				UPDATE $table_saisons
				SET inscription = 1
				WHERE id = $id AND statut <> 0
				",
					'acces_table', 'inscription à 1');
			if (!db::commit()){
				return false;
			}
			cfg_yml::update_saisons_from_table();
			return true;
		}
		return false;
	}
	
	static private function to_integer_type($laquelle){
		if (is_numeric($laquelle)){
			return $laquelle;
		}
		return array_search($laquelle, self::$table_saisons);
	}
	
	static function regle_statut($courante, $prochaine, $inscription){
		
		$table_saisons = self::$table_saisons;
		
		$res = db::query("

			SELECT id
			FROM $table_saisons
			ORDER BY debut DESC
			LIMIT 2
			LOCK IN SHARE MODE

		", 
				'acces_table');
		
		// si aucune saison dans la table, alors aucune saison ne peut être spécifiée avec un id > 0
		if ($res->num_rows == 0){
			if ($courante != -1 or $prochaine != -1 or $inscription != -1){
				return 'saison_invalide';
			}
		}
		// mettre en array les 2 saisons les plus récentes
		while ($row = $res->fetch_assoc()){
			$s[] = $row['id'];
		}
		foreach(array($courante, $prochaine, $inscription) as $saison){
			// si un id de saison fourni en paramètre, il doit correspondre à une des deux saisons les plus récentes
			if($saison != -1 and !in_array($saison, $s)){
				return array('saison_invalide', $saison);
			}
		}
		// si spécifiée, saison d'inscription doit être prochaine ou courante
		if ($inscription != -1 and $inscription != $courante and $inscription != $prochaine){
			return 'inscription_proch_ou_cour';
		}
		
		
		// si courante et prochaine spécifiées, alors prochaine doit être postérieure à courante
		if ($prochaine != -1 and $courante != -1){
			// si prochaine et courante spécifiées, les deux doivent différer
			if ($prochaine == $courante){
				return 'err_meme_prochaine_et_courante';
			}
			
			if (array_search($prochaine, $s) >= array_search($courante, $s)){
				return 'prochaine_apres_cour';
			}
		}
		$res = db::query("

			UPDATE $table_saisons
			SET statut =
				CASE id
					WHEN $courante THEN 1
					WHEN $prochaine THEN 2
					ELSE 0
				END,
				inscription = if(id=$inscription, 1, 0)
			WHERE 1

		");
		//debug_print_once(db::$last_query);
		if (!$res){
			return array('acces_table', "mise à jour table saisons ");
		}
		
		if (!db::commit()){
			return array('acces_table', 'commit');
		}
		cfg_yml::update_saisons_from_table();
		
		
		
		return true;

	}
	
	static function get_nom($id, $lock = 0){
		$table = self::$table_saisons;
		
		if (!is_numeric($id)){
			if (isset(self::$cond_statut[$id]) and $id != 'aucune'){
				$cond = self::$cond_statut[$id];
			} else {
				return false;
			}
		} else {
			$cond = "id = $id";
		}
		$lock = self::lock_statement($lock);
		
		$res = db::query("
			SELECT nom_saison
			FROM $table
			WHERE $cond
			$lock
		");
		if (!$res){
			return http_json::conditional_fin('acces_table','nom_saison');
		}
		if ($res->num_rows){
			$row = $res->fetch_assoc();
			return $row['nom_saison'];
		}
		return false;
	}
	static function get_data_plus_recentes($nb = 1, $prefixe = ''){
		$table = self::$table_saisons;
		$res = db::query($q = "
			SELECT *
			FROM(
				SELECT *
				FROM $table
				WHERE 1
				ORDER BY debut DESC
				limit $nb
				) a
			ORDER BY debut
			");
		//debug_print_once($q);
		if (!$res){
			return http_json::conditional_fin('acces_table', "liste saisons" );
		}
		$to_ret = array();
		if ($res->num_rows){
			while($row = $res->fetch_assoc()){
				$to_ret["$prefixe{$row['id']}"] = $row;
			}
		}
		return $to_ret;
			
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
	
	// la dernière est la courante si courante <> prochaine et prochaine définie
	//	ou la plus récente avant la courante s'il n'y a pas de prochaine définie
	static function get_derniere(){
		$res = db::query("
			SELECT id, statut, inscription
			FROM saisons
			ORDER BY statut DESC, fin DESC
			LIMIT 2
		");
		if (!$res){
			return http_json::conditional_fin('acces_table','get_derniere');
		}
		if ($res->num_rows == 0){
			return null;
		}
		while ($row = $res->fetch_assoc()){
			$saisons[] = $row;
		}
		debug_print_once(print_r($saisons, 1));
		if ($saisons[0]['statut'] == 2 and isset($saisons[1]) and $saisons[1]['statut'] == 1){
			return $saisons[1]['id'];
		} else if ($saisons[0]['statut'] == 1 and isset($saisons[1])){
			return $saisons[1]['id'];
		} else return null;
	}
	static function get_avant($laquelle, $nb = 0){
		$saison = self::get($laquelle);
		if ($saison == -1){
			return -1;
		}
		if (!$nb){
			$nb = 1;
		}
		$debut = db::sql_str(self::get_fld_for_id('debut', $saison));
		$res = db::query("
			SELECT id, nom_saison
			FROM saisons
			WHERE debut < $debut
			ORDER BY debut DESC
			LIMIT $nb
		",
			'acces_table','recherche saison avant');
		if ($res->num_rows == 0){
			return -1;
		}
		extract($res->fetch_assoc());
		return $id;
		
	}
	static function no_statut($laquelle = 'courante'){
		switch ($laquelle){
			case 'courante':
			case 1:
				return 1;
			case 'prochaine':
			case 2:
				return 2;
			case 'inscription':
			case 3:
				return 3;
			case 'aucune':
			case 0:
				return 0;
			default:
				return false;
		}
	}
	static function en_saison($laquelle = 'courante'){
		$laquelle = self::no_statut($laquelle);
		if (!$laquelle){
			return true;
		}
		if (array_key_exists($laquelle, self::$en_saison)){
			return self::$en_saison[$laquelle];
		}
		$res = db::query("
			SELECT COUNT(*) nb
			FROM saisons
			WHERE statut = $laquelle
				AND curdate() >= debut AND curdate() <= fin
		");
		if (!$res){
			return http_json::conditional_fin('acces_table','en saison');
		}
		extract($res->fetch_assoc());
		$to_ret = ($nb?true:false);
		self::$en_saison[$laquelle] = $to_ret;
		return $to_ret;
	}
    static function en_saison_par_id($id_saison){
        $res = db::query("
            SELECT count(*) nb
            FROM saisons
            WHERE id = $id_saison AND curdate() >= debut AND curdate() <= fin
		",
			'acces_table','');
        extract($res->fetch_assoc());
        return ($nb > 0);
    }
	function fn_get_mois_saison(){
		$res = db::query("
			SELECT year(debut) annee_debut, month(debut) mois_debut, year(fin) annee_fin, month(fin) mois_fin
			FROM saisons
			WHERE statut = 1
		",
				'acces_table','dates saison courante');
		if ($res->num_rows == 0){
			$this->fin('saison_introuvable');
		}
		extract($res->fetch_assoc());
		$mois = $mois_debut;
		$choix = array();
		for($annee=$annee_debut; $annee <= $annee_fin; $annee++){
			$dernier_mois = ($annee == $annee_fin?$mois_fin:12);
			while ($mois <= $dernier_mois){
				$choix[] = sprintf('%d - %02d', $annee, $mois++);
			}
			$mois = 1;
		}
		if (count($choix) == 0){
			$this->fin('saison_mal_definie');
		}
		self::$data['liste'] = $choix;
		$this->succes();
		
	}
	
	static function load_data_saisons(){
		if (self::$data_saisons){
			return;
		}
		self::$data_saisons = array();
		$res = db::query("
			SELECT *
			FROM saisons
			WHERE statut OR inscription
			
		");
		if (!$res){
			return http_json::conditional_fin('acces_table','data_saisons');
		}
		if ($res->num_rows == 0){
			return;
		}
		
		while ($row = $res->fetch_assoc()){
			self::$data_saisons[$row['statut']] = $row;
			if ($row['inscription']){
				self::$data_saisons[3] = $row;
			}
			self::$ref[$row['id']] = $row;
		}
	}
	static function get_fld($fld, $laquelle = 'courante'){
		
		$laquelle = self::no_statut($laquelle);
		self::load_data_saisons();
		
		if (!(self::$data_saisons[$laquelle][$fld])){
			return null;
		}
		return self::$data_saisons[$laquelle][$fld];
	}
	static function get_fld_for_id($fld, $id_saison){
		$res = db::query("
			SELECT $fld
			FROM saisons
			WHERE id = $id_saison
		",
			'acces_table','get fld by id (saisons)');
		if ($res->num_rows == 0){
			return null;
		}
		$row = $res->fetch_assoc();
		return $row[$fld];
		
	}

	static function existe($id){
	    self::load_data_saisons();
        return array_key_exists($id, self::$ref);
    }
}
?>
