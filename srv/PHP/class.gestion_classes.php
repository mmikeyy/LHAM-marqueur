<?php

class gestion_classes extends http_json{
	
	static $liste = array();
	static $liste_par_classe = array();
	static $liste_ordonnee = array();
	static $liste_loaded;
	
	function __construct(){
		parent::__construct();
		
		$this->execute_op();
	}
	
	
	function fn_get_liste_classes(){
		
		$res = db::query("

			SELECT cl.*, ifnull(cl.id_groupe_classe, '-') id_groupe_classe
			FROM classes cl
			LEFT JOIN classes_groupes gr USING (id_groupe_classe)
			WHERE 1
			ORDER BY gr.ordre, ordre

		",
				'acces_table', 'obtention liste classes');
		
		self::$data['liste'] = array();
		if ($res->num_rows){
			while ($row = $res->fetch_assoc()){
				self::$data['liste'][] = $row;
			}
		}

		$res = db::query("

			SELECT *
			FROM classes_groupes
			WHERE 1
			ORDER BY ordre

		",
				'acces_table', "");
		self::$data['groupes'] = array();
		if ($res->num_rows) {
			while ($row = $res->fetch_assoc()) {
				self::$data['groupes'][] = $row;
			}
		}
		$this->succes();
	}
	function fn_sauvegarde_classes(){
		if (!perm::test('admin')){
			$this->fin('non_autorise');
		}
		
		extract(self::check_params(
				'vals;array'
		));
		
		$res = db::query("

			SELECT classe
			FROM classes
			WHERE 1
			FOR UPDATE

		",
				'acces_table', "verrouillage classes");
		$classes = array();
		if ($res->num_rows){
			while ($row = $res->fetch_assoc()){
				$classes[] = $row['classe'];
			}
		}
		$res = db::query("

			SELECT id_groupe_classe id
			FROM classes_groupes
			WHERE 1
			LOCK IN SHARE MODE

		",
				'acces_table', "verrouillage groupes");
		$groupes = array('-');

		if ($res->num_rows){
			while ($row = $res->fetch_assoc()){
				$groupes[] = $row['id'];
			}
		}

		// vérifier que les groupes dans les données sauvegardées existent
		$valeurs = array();
		$liste_classes_sauvees = array();
		$ordre = 0;
		foreach($vals as $gr=>$val){
			if (!in_array($gr, $groupes)){
				$groupes_inexistants[] = $gr;
				if (!is_array($val)){
					fin('mauvais_param', 'vals');
				}
			}
			$groupe = (is_numeric($gr)?$gr:'null');
			foreach($val as &$classe){
				$classe = db::sql_str($classe);
				$liste_classes_sauvees[] = $classe;
				$ord = $ordre++;
				$valeurs[] = "($ord, $classe, $groupe)";
			}
		}
		unset($classe);
		if (count($groupes_inexistants)){
			fin('groupes_inexistants', implode(', ', $groupes_inexistants));
		}

		if (count($liste_classes_sauvees)){
			// effacer les classes disparues

			$liste_classes_sauvees = implode(',', $liste_classes_sauvees);
		debug_print_once("Liste classes sauvees = $liste_classes_sauvees");
			$res = db::query("

				DELETE FROM classes
				WHERE classe NOT IN ($liste_classes_sauvees)

			",
					'acces_table', "effacement classes plus utilisées");
			self::$data['effaces'] = db::get('affected_rows');
			//insérer valeurs
			$valeurs = implode(', ', $valeurs);
			$res = db::query("

				INSERT INTO classes
				(ordre, classe, id_groupe_classe)
				VALUES $valeurs
				ON DUPLICATE KEY UPDATE ordre = VALUES(ordre), classe = VALUES(classe), id_groupe_classe = VALUES(id_groupe_classe)

			",
					'acces_table', "");
			self::$data['modif'] = db::get('affected_rows');
		} else{
			$res = db::query("

				DELETE
				FROM classes
				WHERE 1

				",
					'acces_table', "effacement tout");
			self::$data['effaces'] = db::get('affected_rows');
		}

		$this->succes();
	}
	
	static function get_selection($val = 'id', $text = 'classe'){
		
		if ($val != 'id' and !in_array($val, tables::get_columns('classes'))){
			$val = 'id';
		}
		if ($text != 'classe' and !in_array($text, tables::get_columns('classes'))){
			$val = 'classe';
		}
		
		if ($val == 'id'){
			$val = "concat(id, '_')";
		}
		
		$res = db::query("
			SELECT $val val, $text text
			FROM classes
			ORDER BY ordre
		",
				'acces_table','get selection (classes)');
		$to_ret = array();
		if ($res->num_rows){
			while ($row = $res->fetch_assoc()){
				$to_ret[$row['val']] = $row['text'];
			}
		}
		return $to_ret;
	}
	static function get_desc($id){
		self::load_classes();
		
		if (array_key_exists($id, self::$liste)){
			return self::$liste[$id];
		} 
		return '?';
	}
	static function get_id($classe){
		self::load_classes();
		if (!array_key_exists($classe, self::$liste_par_classe)){
			return null;
		}
		return self::$liste_par_classe[$classe];
	}
	static function load_classes(){
		
		if (self::$liste_loaded){
			return;
		}
		self::$liste_loaded = true;
		
		$res = db::query("
			SELECT id, classe
			FROM classes
			WHERE 1
			ORDER BY ordre
		",
				'acces_table','load classes');
		if ($res->num_rows == 0){
			return;
		}
		while ($row = $res->fetch_assoc()){
			self::$liste_ordonnee[] = $row;
			self::$liste[$row['id']] = $row['classe'];
			self::$liste_par_classe[$row['classe']] = $row['id'];
		}
	}
	static function is_valid_id($id){
	    self::load_classes();
        return array_key_exists($id, self::$liste);
    }
	static function lock($id, $lock_level = 1){
		if (is_array($id)){
			$cond = 'id in ('. implode($id) . ')';
		} else {
			$cond = 'id = ' . $id;
		}
		$lock = db::lock_statement($lock_level);
		$res = db::query("
			SELECT id
			FROM classes
			WHERE $cond
			$lock
		",
				'acces_table','verrouillage classes');
		return $res->num_rows;
	}

	static function options_classes($categ){
		$res = db::query("
			SELECT niv.classe
			FROM niveaux niv
			JOIN classes USING(classe)
			WHERE niv.categ = $categ
			ORDER BY classes.ordre
		");
		if (!$res){
			return http_json::conditional_fin('acces_table','options_classes');
		}
		return db::select_options($res, 'classe','classe');
	}
}
?>
