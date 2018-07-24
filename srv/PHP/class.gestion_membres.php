<?php

class gestion_membres extends table {

	static $table_membres = 'membres';
	static $msg = false;
	
	function __construct($no_op = false, $table = 'membres'){
		parent::__construct($table);
		
		self::$table_membres = $table;
		
		$this->champs = array(
			'pseudo' =>			'string;min:4;max:15;empty_string_to_null;accept_null',
			'nom' =>			'string;min:2;max:50', 
			'prenom'=>			'string;min:2;max:50', 
			'courriel'=>		'courriel;default_empty_string;force_accept_default',
			'nouveau_courriel'=>'courriel;default_empty_string;force_accept_default',
			'tel_jour' =>		'tel;default_empty_string;force_accept_default',
			'tel_soir' =>		'tel;default_empty_string;force_accept_default',
			'cell' =>			'tel;default_empty_string;force_accept_default',
			'date_naissance'=>	'date;empty_string_to_null;accept_null',
			'sexe'=>			'regex:#^(M|F)$#i',
			'cache_courriel'=>	'unsigned;max:10',
			'cache_tel_jour'=>	'unsigned;max:10',
			'cache_tel_soir'=>	'unsigned;max:10',
			'cache_cell'=>		'unsigned;max:10',
			'cache_tel_domicile'=>'unsigned;max:10',
			'mot_passe'=>		'regex:#[a-f0-9]{32}#',
			'code_validation'=> 'unsigned;empty_string_to_null;accept_null',
			'id_domicile'=>		'unsigned;empty_string_to_null;accept_null',
			'id_hcr'=>			'regex:#^\d+$#'
			);
		$this->champ_clef = 'id';
		$this->champs_read_only[] = 'distinction';
		
		$this->expressions['age'] = 'age(%sdate_naissance)';
		
		if (!self::$msg){
			self::$msg = new msg('class_gestion_membres',$this);
		}
		
		if(!$no_op){
			self::execute_op();
		}
	}
	static function fin($index = '', $info = ''){
		self::load_msg();
		return self::$msg->fin($index,$info);

	}	
	function fn_update(){
		extract(self::check_params(
				'id;unsigned',
				'updates;array',
				'get_values;array;opt',
				'value_formats;string;opt'
		));
		if (!perm::test('inscription')){
			self::verif_admin_ou_gerant($id);
		}
		$this->update($id, $updates);
		
		if (isset ($get_values)){
			
			$champs_acceptables = array_merge(explode(',',$this->liste_champs_tous()), explode(',',$this->liste_expressions()));
			
			$get_values = array_intersect($get_values, $champs_acceptables);
	
			if (count($get_values)){
				$get_values = $this->expand_expressions($get_values);
				self::$data['values'] = self::get($id, $get_values);
				if (isset($value_formats)){
					self::apply_formats($value_formats, self::$data['values']);
				}
			}
		}
		$this->succes();
	}
	
	static function apply_formats($formats, &$values){
		if (is_string($formats)){
			$formats = explode(',',$formats);
		}
		foreach($formats as $format){
			debug_print_once("applique $format");
			switch ($format){
				case 'upper_first_nom':
					foreach($values as $ind=>&$val){
						if ($ind == 'nom' or $ind == 'prenom'){
							debug_print_once("format applique a $ind = $val");
							$val = self::formate_nom($val);
							debug_print_once("valeur formatee = $val");
						}
					}
			}
		}
	}
	
	static function load_msg(){
		if (self::$msg){
			return;
		}
		self::$msg = new msg('class_gestion_membres');
	}
	static function msg($ind = '', $info = '', $garb = null){
		self::load_msg();
		return self::$msg->get($ind);
	}

	function fn_liste_selection_membres(){
		extract(self::check_params(
				'jeunes_exclus;bool;bool_to_num',
				'nom_commence;string'
		));
		$condition_age = '';
		$condition_nom = '';
		if ($jeunes_exclus){
			$condition_age = '(m.date_naissance IS NULL OR date_add(m.date_naissance, INTERVAL 18 YEAR) < now())';
		} else {
			$condition_age = 1;
		}
		if ($nom_commence){
			$condition_nom = "m.nom like '$nom_commence%'";
		} else {
			$condition_nom = 1;
		}
		$nom = self::nom_prenom_expr('m');
		$m2_nom = self::prenom_nom_expr('m2');
		$m3_nom = self::prenom_nom_expr('m3');
		
		$res = db::query("
			SELECT
				m.id,
				$nom nom, 
				IF( m.date_naissance IS NULL ,  '?', age(m.date_naissance) ) age,
				group_concat($m2_nom separator '; ') parent_de,
				group_concat($m3_nom separator '; ') enfant_de
				FROM $this->table m
				LEFT JOIN editeurs e ON m.id = e.id_membre
				LEFT JOIN rel_parent p ON p.id_parent = m.id
				LEFT JOIN $this->table m2 ON m2.id = p.id_enfant
				LEFT JOIN rel_parent enfant ON enfant.id_enfant = m.id
				LEFT JOIN $this->table m3 ON enfant.id_parent = m3.id
				WHERE
				$condition_nom AND $condition_age
				GROUP BY m.id
				order by nom
				
		");
		self::$data['liste'] = array();
		if ($res->num_rows){
			while ($row = $res->fetch_assoc()){
				self::$data['liste'][] = $row;
			}
		}
		$this->succes();
	}
	
	function fn_get_liste_membres(){
		if (!perm::test('admin,inscription,admin_inscription')){
			$this->fin('non_autorise');
		}
		
		extract(self::check_params(
				'ages;regex:#^\d\d?-\d\d?$#',
				'nom;string',
				'saison;unsigned',
				'parents;regex:#^(oui|non|tous)$#',
				'enfants;regex:#^(oui|non|tous)$#',
				'joueurs;regex:#^(oui|non|tous)$#',
				'roles;regex:#^(0|1|2|tous)$#',
				'page;unsigned;min:1;opt'
		));
		
		$longueur_page = 250;
		if (!isset($page)){
			$page = 0;
		}
		$debut = ($page-1) * $longueur_page;
		
		$where = '1';
		
		if (strlen($nom)){
			$where .= ' and m.nom like ' . db::sql_str($nom . '%');
		}
		
		if ($parents != 'tous'){
			if ($parents == 'oui'){
				$where .= ' and m_enfants.id is not null';
			} else{
				$where .= ' and m_enfants.id is null';
			}
		}
		
		if ($enfants != 'tous'){
			if ($enfants == 'oui'){
				$where .= ' and m_parents.id is not null';
			} else {
				$where .= ' and m_parents.id is null';
			}
		}
		
		if ($joueurs != 'tous'){
			if ($joueurs == 'oui'){
				$where .= ' and joueur_de.id_joueur is not null';
			} else {
				$where .= ' and joueur_de.id_joueur is null';
			}
		}
		
		if ($roles != 'tous'){
			$where .= " and role.role = $roles";
		}
		
		preg_match('#^(\d+)-(\d+)$#', $ages, $interval);
		if ($interval[1] >= 3 or $interval[2] < 90){
			$where .= " and (m.date_naissance is null or" ;
				$where2 = array();
			if ($interval[1] >= 3){
				$where2[] .= " adddate(m.date_naissance, INTERVAL $interval[1] YEAR) <= curdate()";
			}
			if ($interval[2] < 90){
				$where2[] = " adddate(m.date_naissance, INTERVAL $interval[2] YEAR) >= curdate()";
			}
			$where .= implode(' AND ', $where2) . ')';
		}
		
		self::$data = array(
			'liste'=>array(),
			'noms_equipes'=>array(),
			'page'=>1,
			'nb_pages'=>0,
			'nb_total'=>0
		);
		
		$equipes_saison = equipes::liste_equipes_saison($saison);
		if (count($equipes_saison) == 0){
			$this->succes();
		}
		$liste_equipes = implode(',', $equipes_saison);
		
		$res = db::query("
			SELECT e.id_equipe, concat(n.categ, '-', n.classe, ' ', e.nom) nom
			FROM equipes e
			JOIN niveaux n USING(niveau)
			WHERE e.id_equipe in ($liste_equipes)
		",
				'acces_table','liste de noms');
		if ($res->num_rows == 0){
			$this->succes();
		}
		while ($row = $res->fetch_assoc()){			
			self::$data['noms_equipes'][$row['id_equipe']] = cfg_yml::abreviation_equipe($row['nom']);
		}
		//concat(m.nom, ', ', m.prenom, if(m.distinction, concat(' #', m.distinction), '')) nom,
		$res = db::query($q = "
			SELECT SQL_CALC_FOUND_ROWS
				m.id,
				m.nom,
				m.prenom,
				m.distinction,
				ifnull(age(m.date_naissance), '?') age,
				
				GROUP_CONCAT(DISTINCT
					IF(isnull(m_enfants.id), NULL,CONCAT_WS(',',
						m_enfants.id,
						REPLACE(REPLACE(m_enfants.nom, ',', '_'), ';','_'),
						REPLACE(REPLACE(m_enfants.prenom, ',', '_'), ';','_'),
						ifnull(m_enfants.distinction, ''),
						ifnull(age(m_enfants.date_naissance), '?'),
						ifnull(je.id_equipe,'')
					)) ORDER BY m_enfants.nom, m_enfants.prenom SEPARATOR ';'
				) enfants,
				
				GROUP_CONCAT(DISTINCT
					IF(isnull(m_parents.id), NULL, CONCAT_WS(',',
						m_parents.id,
						REPLACE(REPLACE(m_parents.nom, ',', '_'), ';','_'),
						REPLACE(REPLACE(m_parents.prenom, ',', '_'), ';','_'),
						ifnull(m_parents.distinction,''),
						ifnull(age(m_parents.date_naissance), '?')
					))  ORDER BY m_parents.nom, m_parents.prenom SEPARATOR ';'
				) parents,
				
				GROUP_CONCAT(DISTINCT
					IF(isnull(role.role), NULL, CONCAT_WS(',',
						role.role,
						role.id_equipe_role
					)) SEPARATOR ';'
				) roles,
				
				GROUP_CONCAT(DISTINCT
					IF(isnull(joueur_de.id_equipe_joueur), NULL, 
						joueur_de.id_equipe_joueur
					) SEPARATOR ';'
				) joueur
				
			FROM $this->table m
			LEFT JOIN rel_parent par_de ON m.id = par_de.id_parent
			LEFT JOIN $this->table m_enfants ON par_de.id_enfant = m_enfants.id
			LEFT JOIN joueur_equipe je ON m_enfants.id = je.id_joueur and id_equipe IN ($liste_equipes)
			

			LEFT JOIN rel_parent enf_de ON m.id = enf_de.id_enfant
			LEFT JOIN $this->table m_parents ON enf_de.id_parent = m_parents.id
			
			LEFT JOIN (
				SELECT role, id_adulte,id_equipe id_equipe_role
				FROM role_equipe 
				WHERE id_equipe in ($liste_equipes)
				) role ON role.id_adulte = m.id
			LEFT JOIN (
				SELECT id_joueur, id_equipe id_equipe_joueur
				FROM joueur_equipe 
				WHERE id_equipe in ($liste_equipes)
				) joueur_de ON joueur_de.id_joueur = m.id
			LEFT JOIN inscriptions i ON i.saison = $saison and i.id_joueur = m.id
			LEFT JOIN rel_parent par_inscr ON par_inscr.id_parent = m.id
			LEFT JOIN inscriptions i2 ON par_inscr.id_enfant = i2.id_joueur AND i2.saison = $saison
			WHERE  $where
					
					AND (role.role 
					OR joueur_de.id_joueur 
					OR i.id_joueur = m.id 
					OR i2.id_joueur IS NOT NULL
					OR m.id in (
						SELECT id_parent
						FROM rel_parent
						JOIN joueur_equipe je ON rel_parent.id_enfant = je.id_joueur
						WHERE je.id_equipe in ($liste_equipes)
						)
				)
			GROUP BY m.id
			ORDER BY m.nom, m.prenom, m.distinction
			LIMIT $debut, $longueur_page
			
		",
				'acces_table');
		//debug_print_once("$q");
		$abreviations = array();
		
		$d =& self::$data['liste'];
		
		
		
		if ($res->num_rows){
			
			while ($row = $res->fetch_assoc()){
				$membre = $row;
				$membre['nom'] = self::formate_nom($membre['nom']);
				$membre['prenom'] = self::formate_nom($membre['prenom']);
				$this->split($membre['enfants'], array('id', 'nom', 'prenom', 'distinction', 'age', 'id_equipe'));
				$this->split($membre['parents'], array('id', 'nom', 'prenom', 'distinction', 'age'));
				$this->split($membre['roles'], array('role', 'id'));
				$this->split($membre['joueur'], array('id'));
		
			
				foreach($membre['joueur'] as &$vals){
					$vals['role'] = 'joueur';
					$membre['roles'][] = $vals;
				}
				unset($vals);
				
				unset($membre['joueur']);
				$d[] = $membre;

			}
		}
		
		$res = db::query("
			SELECT FOUND_ROWS() nb
		",
				'acces_table','décompte found_rows');
		extract($res->fetch_assoc());
		self::$data['nb_total'] = (integer) $nb;
		self::$data['page'] = (integer) $page;
		self::$data['nb_pages'] = ceil($nb / $longueur_page);
		
		$this->succes();
	}
	
	function fn_details_membre(){
		if (!login_visiteur::logged_in()){
			$this->fin('non_autorise');
		}
		extract(self::check_params(
				'id;unsigned'
		));
		
		if (!perm::test('admin,inscription') and !perm::resp_niveau()){
			if (!perm::est_gerant_de(session::get('id_visiteur'), $id)){
				$this->fin('non_autorise');
			}
		}
		
		$details = $this->details_membre($id);
		$details['nom'] = self::formate_nom($details['nom']);
		
		unset($val);
		if (!$details){
			self::$data['introuvable'] = 1;
		} else {
			self::$data['details'] = $details;
			$res = db::query("
				SELECT concat(prenom, ' ', nom) nom
				FROM membres m
				JOIN rel_parent rel ON m.id = rel.id_enfant
				WHERE rel.id_parent = $id
				ORDER BY nom, prenom
			",
					'acces_table','recherche enfants');
			self::$data['enfants'] = db::result_array_values_one($res);
			foreach(self::$data['enfants'] as &$val){
				$val = self::formate_nom($val);
			}
			unset($val);
		}
		$this->succes();
		
	}
	function fn_details_enfant(){
		if (!login_visiteur::logged_in()){
			$this->fin('non_autorise');
		}
		extract(self::check_params(
				'id;unsigned',
				'id_membre;unsigned' // id du parent sur la ligne de tableau duquel on se trouve
		));
		
		if (!perm::test('admin,inscription')){
			if (!perm::est_gerant_de(session::get('id_visiteur'), $id)){
				$this->fin('non_autorise');
			}
		}
		
		$details = $this->details_membre($id);
		if (!$details){
			self::$data['introuvable'] = 1;
			$this->succes();
		}
		self::$data['details'] = $details;
		
		// trouver les autres parents de l'enfant s'il y en a
		
		$res = db::query("
			SELECT id_parent
			FROM rel_parent par
			WHERE id_enfant = $id and id_parent <> $id_membre
			LOCK IN SHARE MODE
		",
				'acces_table','recherche autre parent');
		self::$data['details']['nb_autres_parents'] = $res->num_rows;
		
		if ($res->num_rows){
			while ($row = $res->fetch_assoc()){
				$liste[] = $row['id_parent'];
			}
			self::$data['details']['autres_parents'] = $this->details_membre($liste);
		}
		$this->succes();
		
	}
	
	/**
	 * trouver détails à afficher pour le parent d'un enfant dans le tableau des membres
	 * 
	 * ON retournera:
	 * 1) les autres enfants de ce parent (autres que celui de la ligne sur laquelle on se trouve)
	 * 2) pour chaque enfant, son age courant et l'équipe à laquelle il appartenait pendant la saison spécifiée
	 * 
	 */
	function fn_details_parent(){
		if (!login_visiteur::logged_in()){
			$this->fin('non_autorise');
		}
		extract(self::check_params(
				'id;unsigned',
				'id_membre;unsigned', // id de l'enfant sur la ligne de tableau duquel on se trouve
				'saison;unsigned'
		));
		
		if (!perm::test('admin,inscription')){
			if (!perm::est_gerant_de(session::get('id_visiteur'), $id)){
				$this->fin('non_autorise');
			}
		}
		$details = $this->details_membre($id);
		if (!$details){
			self::$data['introuvable'] = 1;
			$this->succes();
		}
		self::$data['details'] = $details;
		
		// trouver les autres enfants du parent s'il y en a
		$nom = self::prenom_nom_expr('m');
		$res = db::query("
			SELECT 
				m.id, 
				m.id_hcr,
				ifnull(age(m.date_naissance),'?') age, 
				$nom nom, 
				eq.niveau, 
				eq.nom nom_equipe
			FROM $this->table m
			LEFT JOIN joueur_equipe je ON m.id = je.id_joueur
			LEFT JOIN equipes eq ON eq.id_equipe = je.id_equipe and eq.id_saison = $saison
			WHERE m.id in (
				SELECT par.id_enfant id
				FROM rel_parent par
				JOIN $this->table m ON m.id = par.id_enfant
				WHERE id_parent = $id and id_enfant <> $id_membre
				)
			ORDER BY m.date_naissance
		",
				'acces_table','recherche autres enfants');
		self::$data['details']['nb_autres_enfants'] = $res->num_rows;
		
		if ($res->num_rows){
			while ($row = $res->fetch_assoc()){
				self::normalise_details($row);
				$liste[] = $row;
			}
			self::$data['details']['autres_enfants'] = $liste;
		}
		$this->succes();
		
		
	}
	
	function fn_details_roles(){
		if (!login_visiteur::logged_in()){
			$this->fin('non_autorise');
		}
		extract(self::check_params(
				'id;unsigned',	// id de l'équipe
				'id_membre;unsigned' // id de l'enfant sur la ligne de tableau duquel on se trouve
				
		));
		if (!perm::test('admin,inscription')){
			if (!perm::est_gerant_de(session::get('id_visiteur'), $id_membre)){
				$this->fin('non_autorise');
			}
		}
		
		self::$data['details'] = $this->details_role($id);
		$this->succes();
	}
	
	function details_role($id){
		$to_ret = array();
		$nom = self::nom_prenom_expr('m');
		
		$res = db::query("
			SELECT $nom nom,
				re.role
			FROM $this->table m
			JOIN role_equipe re ON re.id_adulte = m.id
			WHERE re.id_equipe = $id
			ORDER BY re.role
		",
				'acces_table','liste officiels');
		if ($res->num_rows){
			while ($row = $res->fetch_assoc()){
				self::normalise_details($row);
				$to_ret['officiels'][] = $row;
			}
		}
		$saison = saisons::get('courante');
		$res = db::query("
			SELECT $nom nom,
			d.position,
			if(position=2,1,0) ordre
			FROM $this->table m
			JOIN joueur_equipe je ON m.id = je.id_joueur
			JOIN dossier_joueur d USING(id_joueur)
			WHERE je.id_equipe = $id AND d.saison = $saison
			ORDER BY ordre, nom
		");
		$to_ret['joueurs'] = array();
		if ($res->num_rows){
			while ($row = $res->fetch_assoc()){
				self::normalise_details($row);
				$to_ret['joueurs'][] = $row;
			}
		}
		
		$to_ret['nom_equipe'] = equipes::get_nom_complet($id,true);
		
		
		
		return $to_ret;
	}
	
	function details_frere($id){
		
		if (is_array($id)){
			$array = true;
			$where = ' where m.id in (' . implode(',', $id) . ')';
		} else{
			$array = false;
			$where = " where m.id = $id";
		}
		
		$nom = self::nom_prenom_expr('m');
		$res = db::query("
			SELECT 
				m.id,
				id_hcr,
				$nom nom,
				courriel, 
				ifnull(age(m.date_naissance), '?') age,
				eq.niveau,
				eq.nom_equipe
			FROM $this->table m
			LEFT JOIN joueur_equipe_courant jec USING(id_joueur)
			LEFT JOIN equipes eq ON jec.id_equipe = eq.id_equipe
			$where
		",
				'acces_table', 'recherche details membre');
		
		if ($res->num_rows == 0){
			return false;
		} else {
			if (!$array){
				$row = $res->fetch_assoc();
				self::normalise_details($row);
				return $row;
			} else {
				$details = array();
				while ($row = $res->fetch_assoc()){
					self::normalise_details($row);
					$details[] = $row;
				}
				return $details;
			}
		}		
	}
	
	static function normalise_details(&$row){
		if (isset($row['nom'])){
			$row['nom'] = self::formate_nom($row['nom']);
		}
		if (isset($row['niveau']) and isset($row['nom_equipe'])){
			$row['nom_equipe'] = $row['niveau'] . '-' . cfg_yml::abreviation_equipe($row['nom_equipe']);
			unset($row['niveau']);
		}
	}
	
	function details_membre($id){
		
		if (is_array($id)){
			$array = true;
			$where = ' where m.id in (' . implode(',', $id) . ')';
		} else{
			$array = false;
			$where = " where m.id = $id";
		}
		$nom = self::nom_prenom_expr('m');
		$res = db::query("
			SELECT 
				m.id,
				id_hcr,
				$nom nom,
				concat(prenom, ' ', nom) prenom_nom,
				m.courriel, 
				m.tel_jour,
				m.cell,
				d.adr1,
				d.adr2,
				d.ville,
				d.code_postal,
				ifnull(d.tel,m.tel_soir) tel_domicile,
				age(date_naissance) age
			FROM $this->table m
			LEFT JOIN domiciles d ON m.id_domicile = d.id
			$where
		",
				'acces_table', 'recherche details membre');
		
		if ($res->num_rows == 0){
			return false;
		} else {
			if (!$array){
				$row = $res->fetch_assoc();
				$row['nom'] = self::formate_nom($row['nom']);
				return $row;
			} else {
				$details = array();
				while ($row = $res->fetch_assoc()){
					$details[] = $row;
				}
				return $details;
			}
		}		
	}
	
	function split(&$array, $champs){
		if (is_null($array)){
			$array = array();
			return;
		}
		$liste = explode(';', $array);
		
		foreach($liste as &$item){
			$ligne_detail = array();
			$data = explode(',', $item);
			foreach($champs as $ind=>$champ){
				if ($champ == 'nom' or $champ == 'prenom'){
					$ligne_detail[$champ] = self::formate_nom($data[$ind]);
				} else if ($champ == 'nom_equipe'){
					$ligne_detail[$champ] = cfg_yml::abreviation_equipe($data[$ind]);
				} else {
					$ligne_detail[$champ] = $data[$ind];
				}
			}
			$item = $ligne_detail;
		}
		//if (count($liste)){
			$array = $liste;
	//	} else {
	//		return null;
	//	}
	}
	
	static function formate_nom($nom){
		$nom = str_replace('-', '- ', utf8_decode($nom));
		$nom = ucwords(strtolower($nom));
		return utf8_encode(str_replace('- ', '-', $nom));
	}
	
	/**
	 *	Fonction qui prend priorité sur fnt du même nom dans parent. Raison est que 
	 *  nom et prénom doivent être traités séparément
	 * 
	 * @param int $id  id du membre dont les données sont à mettre à jour
	 * @param array $updates   array de couples [fld => val]
	 * @return bool selon succès ou échec 
	 */
	function update($id, $updates){
		
		if (count($updates) == 0){
            return null;
		}
		// obtenir couples [fld=>val] si les valeurs sont valides
		$assignations = $this->validate_updates($updates); 
		
		if (!is_array($assignations) or count($assignations) == 0){
			return false;
		}

		// traiter les nom et prénom séparément à cause de la possibilité d'introduire des doublons
		if (key_exists('nom', $assignations) and key_exists('prenom', $assignations)){
			self::$data['distinction'] = $this->changer_nom_prenom($id, $updates['nom'], $updates['prenom']);
			unset($assignations['nom']);
			unset($assignations['prenom']);
		} else if (key_exists('nom', $assignations)){
			self::$data['distinction'] = $this->changer_nom($id, $updates['nom']);
			unset($assignations['nom']);
		} else if (key_exists('nom', $assignations)){
			self::$data['distinction'] = $this->changer_prenom($id, $updates['prenom']);
			unset($assignations['prenom']);
		}
		if (key_exists('pseudo',$assignations)){
			if (!self::reserve_pseudo($updates['pseudo'])){
				$this->fin('pseudo_existe');
			}
		}
		if (key_exists('date_naissance', $assignations)){
			$date_max = new DateTime($assignations['date_naissance']);
			$date_max->modify('+ 16 years');
			$date_min = new DateTime($assignations['date_naissance']);
			$date_min->modify('-16 years');
			
			$date_max = $date_max->format('Y-m-d');
			$date_min = $date_min->format('Y-m-d');
			
			$nom_expr_par = self::prenom_nom_expr('parents');
			$nom_expr_enf = self::prenom_nom_expr('enfants');
			$res = db::query("
					SELECT $nom_expr_par nom, parents.date_naissance
					FROM $this->table parents
					JOIN rel_parent r_par ON parents.id = r_par.id_parent
					WHERE r_par.id_enfant = $id 
						AND parents.date_naissance is not null
						AND parents.date_naissance > '$date_min'
					
					UNION
					
					SELECT $nom_expr_enf nom, enfants.date_naissance
					FROM $this->table enfants
					JOIN rel_parent r_enf ON enfants.id = r_enf.id_enfant
					WHERE r_enf.id_parent = $id 
						AND enfants.date_naissance is not null
						AND enfants.date_naissance < '$date_max'
					
			",
					'acces_table','verification age');
			if ($res->num_rows){
				while ($row = $res->fetch_assoc()){
					$liste[] = self::formate_nom($row['nom']) . ' - ' . $row['date_naissance'];
				}
				$liste = implode('; ', $liste);
				$this->fin('probleme_age_generations', $liste);
			}
		}

		// s'il reste des valeurs non encore assignées une fois nom et prénom traités, les traiter

		$this->apply_updates($id, $assignations);
			
		return true;

	}
	function verifier_admin(){
		if (!perm::test('admin')){
			$this->fin('non_autorise');
		}
	}
	function fn_changer_mdp(){
		$this->verifier_admin();
		
		extract(self::check_params(
				'id;unsigned',
				'mdp;regex:#^[a-f0-9]{32}$#'
		));
		
		
		$this->apply_updates($id, array('mot_passe'=>$mdp));
		$this->succes();
	}
	function fn_changer_pseudo_mdp(){
		extract(self::check_params(
			'id;unsigned',
			'updates;array'
		));
		if (!perm::check_perm('chg_pseudo_mdp')){
			$this->fin('delai_perm_expire');
		}
		
		$this->update($id, $updates);
		perm::reset_perm('chg_pseudo_mdp');
		$this->succes();
		
	}
    
    function valider_data_nouveau_membre($data_membre)
    {
		self::set_source_check_params_once($data_membre);
		$data_membre = self::check_params(
				$this->validation_str('nom'),
				$this->validation_str('prenom'),
				$this->validation_str('courriel'),
				$this->validation_str('date_naissance', 'opt'),
				$this->validation_str('tel_jour', 'opt;default_empty'),
				$this->validation_str('tel_soir', 'opt;default_empty'),
				$this->validation_str('cell', 'opt;default_empty'),
				$this->validation_str('pseudo', 'opt;default_empty') ,
				$this->validation_str('mdp', 'opt;default_empty')
		);
        return $data_membre;
        
    }
    function valider_data_domicile($data_domicile)
    {
        $dom = new gestion_domiciles(true);
        self::set_source_check_params_once($data_domicile);
        $data_domicile = self::check_params(
                $dom->validation_str('adr1'),
                $dom->validation_str('adr2', 'opt'),
                $dom->validation_str('ville'),
                $dom->validation_str('code_postal'),
                $dom->validation_str('tel', 'opt')

                );
        return array($dom, $data_domicile);

    }
    
	
	/**
	 * crée un nouveau membre, avec optionnellement statut d'éditeur;
	 * 
	 * si statut d'éditeur, retourner toutes les valeurs normalement utilisées en édition d'éditeurs;
	 * 
	 * si simple membre, retourner id et distinction dans array nommée 'vals'
	 */
	
	
	
	function fn_nouveau_membre(){
		$this->verifier_admin();
		
		extract(self::check_params(
			'data_membre;array'
		));
        
        $data_membre = $this->valider_data_nouveau_membre($data_membre);
        /*
		self::set_source_check_params_once($data_membre);
		$data_membre = self::check_params(
				$this->validation_str('nom'),
				$this->validation_str('prenom'),
				$this->validation_str('courriel'),
				$this->validation_str('date_naissance', 'opt'),
				$this->validation_str('tel_jour', 'opt;default_empty'),
				$this->validation_str('tel_soir', 'opt;default_empty'),
				$this->validation_str('cell', 'opt;default_empty'),
				$this->validation_str('pseudo', 'opt;default_empty') ,
				$this->validation_str('mdp', 'opt;default_empty')
		);
		*/
        
		// id_domicile = -1 pour nouveau domicile, id_domicile pour existant
		if (!is_null($_REQUEST['domicile']) and is_numeric($_REQUEST['domicile']['id_domicile'])){
			$id_domicile = $_REQUEST['domicile']['id_domicile'];
			
			if ($id_domicile < 0){
                list($dom, $data_domicile) = $this->valider_data_domicile($_REQUEST['domicile']);
                /*
				$dom = new gestion_domiciles(true);
				self::set_source_check_params_once($_REQUEST['domicile']);
				$data_domicile = self::check_params(
						$dom->validation_str('adr1'),
						$dom->validation_str('adr2', 'opt'),
						$dom->validation_str('ville'),
						$dom->validation_str('code_postal'),
						$dom->validation_str('tel', 'opt')
						
						);
                 * 
                 */
				$id_domicile = $dom->creer_domicile($data_domicile);
			} else if ($id_domicile > 0){
				if (!isset($_REQUEST['domicile']['id_autres_membres'])){
					$this->fin('param_manquant', 'id_autres_membres');
				}
				if (!preg_match('#((^|,)\d+($|,))+#', $id_autres_membres = $_REQUEST['domicile']['id_autres_membres'])){
					$this->fin('mauvais_param', "id_autres_membres = $id_autres_membres");
				}
				$res = db::query("
					SELECT DISTINCT id_domicile dom
					FROM membres m
					JOIN domiciles d ON m.id_domicile = d.id
					WHERE m.id in ($id_autres_membres)
					LOCK IN SHARE MODE
				",
						'acces_table','verification domicilies meme adresse');
				if ($res->num_rows != 1){
					$this->fin('domicile_change');
				}
				extract($res->fetch_assoc());
				if ($dom != $id_domicile){
					$this->fin('domicile_change');
				}
				
			}
			if ($id_domicile){
				$data_membre['id_domicile'] = $id_domicile;
			}
		} 
		
		
		
		
		extract(self::check_params(
				'nouv_editeur;bool;bool_to_num;opt;default_empty',
				'nouv_publiciste;bool;bool_to_num;opt;default_empty',
				'id_annonceur;unsigned;opt;default_empty'
		));
		
		if ($nouv_publiciste and !$id_annonceur ){
			$this->fin('manque_annonceur');
		}
		
		if ($id_annonceur){
			$res = db::query("
				SELECT id_annonceur
				FROM pub_annonceurs
				WHERE id_annonceur = $id_annonceur
				LOCK IN SHARE MODE
			",
					'acces_table','verrouillage annonceur');
			if ($res->num_rows == 0){
				$this->fin('introuvable', 'annonceur');
			}
		}
		
		// créer le nouveau membre
		
		if (isset($data_membre['pseudo']) and !$data_membre['pseudo']){
			$data_membre['mot_passe'] = '';
		}
		
		
		$distinction = self::reserve_distinction($nom, $prenom);
		
		if (!self::reserve_pseudo($data_membre['pseudo'])){
			$this->fin('pseudo_existe');
		}
		$data_membre['distinction'] = $distinction;
		
		$id = $this->insert($data_membre);
		self::$data['id'] = $id;
		$nom = self::prenom_nom_expr();
		self::$data['nom'] = gestion_membres::get_one($id, "$nom nom");
		
		// si éditeur ou publiciste, faire le nécessaire
		if ($nouv_editeur){
			$gest_editeur = new gestion_editeurs(true);
			$gest_editeur->insert_editeur($id);
		} else if ($nouv_publiciste){
			$res = db::query("
				INSERT IGNORE INTO pub_rel_publiciste_annonceur
				SET id_publiciste = $id
					id_annonceur = $id_annonceur
			",
					'acces_table','insertion relation avec annonceur');
		}
		
		// si des relations familiales ont été définies, en tenir compte
		if (!isset($_REQUEST['relations']) or !$_REQUEST['relations']){
			$this->succes();
		}
		
		if (isset($_REQUEST['relations']['parent_de'])){
			$parent_de = $_REQUEST['relations']['parent_de'];
			if (!is_array($parent_de)){
				$this->fin('mauvais_param', 'parent_de <> array');
			}
			if (!preg_match('#((^|,)\d+($|,))+#', $c_parent_de = implode(',', $parent_de))){
				$this->fin('mauvais_param','parent_de <> array de int');
			}
			gestion_famille::lier_enfants($id, $parent_de);
			
		} else if (isset($_REQUEST['relations']['enfant_de'])){
			$enfant_de = $_REQUEST['relations']['enfant_de'];
			if (!is_array($enfant_de)){
				$this->fin('mauvais_param', 'enfant_de <> array');
			}
			if (!preg_match('#((^|,)\d+($|,))+#', implode(',', $enfant_de))){
				$this->fin('mauvais_param','enfant_de <> array de int');
			}
			gestion_famille::lier_parents($id, $enfant_de);
			
		}
		
		
		
		$this->succes();
		
	}
    
    function fn_nouveau_membre2()
    {
        extract(self::check_params(
                'vals;array'
		));
        $data_membre = $this->valider_data_nouveau_membre($vals['membre']);
        if (!array_key_exists('domicile', $vals)){
            $this->fin('manque_domicile');
        }
        self::set_source_check_params_once($vals);
        extract(self::check_params(
                'domicile;array',
                'enfants;array_unsigned;opt',
                'parents;array;opt'
		));
        
        if (isset($parents) and isset($enfants)){
            $this->fin('err_enfants_et_parents');
        }
        
        self::set_source_check_params_once($domicile);
        extract(self::check_params(
                'id_domicile;int;min:-1',
                'data;array;opt;rename:data_domicile'
		));
        
        if ($id_domicile == -1){
            if (!isset($data_domicile) or !is_array($data_domicile)){
                $this->fin('manque_donnees_nouvau_dom');
            }
            list($dom, $data_domicile) = $this->valider_data_domicile($data_domicile);
            $id_domicile = $dom->creer_domicile($data_domicile);
        } else {
            if (!gestion_domiciles::lock_domiciles($id_domicile)){
                $this->fin('domicile_introuvable');
            }
            
        }
        $data_membre['id_domicile'] = $id_domicile;
        
        $id_membre = $this->validate_insert_member($data_membre);
        
        
        if (isset($enfants)){
            $enfants = array_unique($enfants);
            $nb = self::lock_membres($enfants);
            if ($nb != count($enfants)){
                $this->fin('certains_enfants_introuv');
            }
            gestion_famille::lier_enfants($id_membre, $enfants);
        } else if (isset($parents)){
            $parent_ids = array();
            $parents_existants = array();
            foreach($parents as $parent){
               if($parent['id_parent'] == -1) {
                   if (!array_key_exists('data', $parent)){
                       $this->fin('donnees_nouveau_parent_manquent');
                   }
                   $parent_ids[] = $this->validate_insert_member($parent['data']);
               } else {
                   $parent_ids[] = $parent['id_parent'];
                   $parents_existants[] = $parent['id_parent'];
               }
            }
            if (count($parents_existants)){
                if (self::lock_membres($parents_existants) != count($parents_existants)){
                    $this->fin('certains_parents_introuv');
                }
            }
            gestion_famille::lier_parents($id_membre, $parent_ids);
        }
        
        $this->succes();
        
    }
    function validate_insert_member($data)
    {
        $data = $this->valider_data_nouveau_membre($data);
        $data['distinction'] = self::reserve_distinction($data['nom'], $data['prenom']);
        return $this->insert($data);
    }
    
	function insert($data){
		$assignment = array();
		foreach($data as $fld=>$val){
			$assignment[] = "$fld = " . db::sql_str($val);
		}
		$assignment = implode(',', $assignment);
		
		$res = db::query("
			INSERT INTO $this->table
			SET $assignment
		",
				'acces_table', 'insertion');
		return db::get('insert_id');
	}
	
	/**
	 *
	 * @param unsigned $id
	 * @param SQL string $nouv_nom
	 * @return int ou null = distinction 
	 */
	function changer_nom($id, $nouv_nom){
		list($nom, $prenom, $distinction) = self::get_nom_prenom($id, true); // obtenir nom, prenom et distinction dans cet ordre
		
		if ($nouv_nom == $nom){
			return $distinction;
		}
		$a = $this->changer_nom_prenom($id, $nouv_nom, $prenom);
		return $a;
	}
	
	/**
	 *
	 * @param unsigned $id
	 * @param SQL string $nouv_prenom
	 * @return int ou null = distinction 
	 */
	function changer_prenom($id, $nouv_prenom){
		list($nom, $prenom, $distinction) = self::get_nom_prenom($id, true);
		
		if ($nouv_prenom == $prenom){
			return $distinction;
		}
		return $this->changer_nom_prenom($id, $nom, $nouv_prenom);
	}
	
	/**
	 *
	 * @param unsigned $id
	 * @param string $nouv_nom
	 * @param string $nouv_prenom
	 * @return int ou null = distinction 
	 */
	function changer_nom_prenom($id, $nouv_nom, $nouv_prenom){
		
		$res = db::query("
			SELECT nom, prenom, distinction
			FROM $this->table
			WHERE id = $id
			FOR UPDATE
		");
		if ($res->num_rows == 0){
			$this->fin('introuvable');
		}
		extract($res->fetch_assoc());
		
		if ($nom == $nouv_nom and $prenom == $nouv_prenom){
			return $distinction;
		}
		
		$sql_nouv_nom = db::sql_str($nouv_nom);
		$sql_nouv_prenom = db::sql_str($nouv_prenom);
		
		$distinction = self::reserve_distinction($nouv_nom, $nouv_prenom);
		$sql_distinction = db::sql_str($distinction);
		
		$res = db::query("
			UPDATE $this->table
			SET prenom = $sql_nouv_prenom,
				nom = $sql_nouv_nom,
				distinction = $sql_distinction
			WHERE id = $id
		",
				'echec_maj_membres', 'nom unique');
			
		return $distinction;
	}
	
	static function get_nom_prenom($id, $sql = false){
		$membres = self::$table_membres;
		
		$res = db::query("
			SELECT nom, prenom, distinction
			FROM $membres
			WHERE id = $id
			FOR UPDATE
		");
		if (!$res or $res->num_rows == 0){
			http_json::conditional_fin('introuvable', 'get_nom_prenom');
		}
		if (!$sql){
			return $res->fetch_array();
		} else {
			return db::sql_str($res->fetch_array());
			
		}
	}
	static function get_nom_prenom_formate($id, $sql = false){
		$n = self::get_nom_prenom($id);
		$d = $n['distinction']?" ({$n['distinction']})":'';
		$to_ret = "{$n['nom']}$d {$n['prenom']}";
		if ($sql){
			return db::sql_str($to_ret);
		}
		return $to_ret;
	}
	static function get_prenom_nom_formate($id, $sql = false){
		$n = self::get_nom_prenom($id);
		$d = $n['distinction']?" ({$n['distinction']})":'';
		$to_ret = self::formate_nom("{$n['prenom']} {$n['nom']}$d");
		if ($sql){
			return db::sql_str($to_ret);
		}
		return $to_ret;
	}
	static function get_liste_noms($liste, $avec_id = false){
		$membres = self::$table_membres;
		if (is_array($liste)){
			if (count($liste) == 0){
				return array();
			}
			$where = ' id in (' . implode(',', $liste) . ')';
		} else if (is_numeric($liste)){
			$where = " id = $liste";
		} else if (is_string($liste)){
			$where = $liste;
		} else {
			return array();
		}
		$res = db::query($q = "
			SELECT concat(prenom,' ',nom, if(distinction,concat(' (',distinction,')'), '')) nom, id
			FROM $membres
			WHERE $where
			
		");
		//debug_print_once($q);
		if (!$res){
			return http_json::conditional_fin('acces_table','get liste noms');
		}
		if ($res->num_rows == 0){
			return array();
		}
		$to_ret = array();
		if ($avec_id){
			while ($row = $res->fetch_assoc()){
				$to_ret[$row['id']] = $row['nom'];
			}
		} else {
			while ($row = $res->fetch_assoc()){
				$to_ret[] = $row['nom'];
			}
		}
		return $to_ret;
	}
	
	/**
	 * fournit la distinction pour un id donné
	 * 
	 * @param unsigned $id
	 * @return str
	 */
	static function get_distinction($id){
		$membres = self::$table_membres;
		
		$res = db::query("
			select distinction
			FROM $membres
			WHERE id = $id
		");
		if(!$res){
			return http_json::conditional_fin('acces_table', 'recherche_distinction');
		}
		
		if ($res->num_rows == 0){
			return false;
		}
		extract($res->fetch_assoc());
		return $distinction;
	}
	
	
	static function reserve_distinction($nom, $prenom){
		$membres = self::$table_membres;
		
		$sql_nom = db::sql_str($nom);
		$sql_prenom = db::sql_str($prenom);

		/**
		 * @var int $nb
		 * @var int $distinction
		 **/
		$res = db::query($q = "
			SELECT max(ifnull(distinction, 0) + 1) distinction, count(*) nb
			FROM $membres
			WHERE nom = $sql_nom AND prenom = $sql_prenom
			FOR UPDATE
		");
		if (!$res){
			return http_json::conditional_fin('acces_table', 'select max distinction');
		}
		extract($res->fetch_assoc());
		if ($nb == 0){
			return null;
		}
		return $distinction;
	}
	static function reserve_pseudo($pseudo){
		$membres = self::$table_membres;
		
		$sql_pseudo = db::sql_str($pseudo);
		
		$res = db::query("
			SELECT count(*) nb
			FROM $membres
			WHERE pseudo = $sql_pseudo
			FOR UPDATE
		");
		if (!$res){
			return http_json::conditional_fin('acces_table', "reserve pseudo $pseudo");
		}
		extract($res->fetch_assoc());
		return $nb?false:true;
	}
	
	function fn_get_adresses_famille(){
		extract(self::check_params(
				'id;unsigned'
		));
		if (!perm::test('admin,inscription') and !perm::est_gerant_de(session::get('id_visiteur'), $id)){
			$this->fin('non_autorise');
		}
		
		// trouver le id et la relation pour tous les parents et enfants
		
		$champs_domicile = "
				d.id,
				d.adr1,
				d.adr2,
				d.ville,
				d.code_postal,
				d.tel	
		";
		
		$enfants = self::liste_enfants($id);
		$parents = self::liste_parents($id);
		$membre = array($id);
		$freres = array_diff(self::liste_enfants($parents), $membre);
		$conjoints = array_diff(self::liste_parents($enfants), $membre);
	
		$res = db::query("
			CREATE TEMPORARY TABLE parentee
			(	id		bigint,
				lien	varchar(15),
				UNIQUE	(id)
			)
			ENGINE=MEMORY
		",
				'acces_table','creation tb temporaire');

		$values = array();
		$famille = array();
		foreach(array('parent'=>$parents, 'membre'=>$membre, 'conjoint'=>$conjoints,'enfant'=>$enfants, 'frere'=>$freres) as $tag=>$vals){
			foreach($vals as $val){
				$values[] = "($val, '$tag')";
				$famille[] = $val;
			}
		}
		$famille = array_unique($famille);

		$values = implode(',', $values);

		$res = db::query("
			INSERT IGNORE INTO parentee
			(id, lien)
			VALUES
			$values
		",
				'acces_table','insertion dans tb temporaire');


		// trouver les autres personnes qui habitent dans les domiciles trouvés
		$famille = implode(',', $famille);
		$res = db::query("
			INSERT IGNORE INTO parentee
			(id, lien)
			SELECT DISTINCT m.id, 'autre'
			FROM $this->table m
			WHERE m.id_domicile IN
				(
					SELECT id_domicile
					FROM $this->table
					WHERE id IN ($famille)
				) AND m.id NOT IN ($famille)
		");
		
		$res = db::query("
			SELECT DISTINCT
			$champs_domicile ,
			GROUP_CONCAT(DISTINCT
				concat_ws(',',
					m.id,
					replace(replace(concat(m.prenom, ' ', m.nom), ';','_'), ',','_'),
					p.lien
				)
			SEPARATOR ';'
			) membres
			FROM parentee p
			JOIN $this->table m USING(id)
			LEFT JOIN domiciles d ON m.id_domicile = d.id
			GROUP BY d.id
				
		",
				'acces_table','liste par domicile');
		
	
		self::$data['liste'] = array();
		
		$dest =& self::$data['liste'];
		
		if ($res->num_rows){
			while ($row = $res->fetch_assoc()){
				$this->split($row['membres'], array('id', 'nom', 'rel'));
				$dest[] = $row;
			}
		}
		$this->succes();
		
	}
	
	static function famille_complete($id){
		$membres = self::$table_membres;
		$res = db::query("
			select DISTINCT id
			FROM (
				SELECT m.id
				FROM $membres m
				JOIN rel_parent par ON par.id_parent = m.id
				WHERE par.id_enfant = $id
				
				UNION
				
				
				)
			FROM $membres m
			LEFT JOIN rel_parent par ON par.id_parent = m.id
			
		");
	}
	static function liste_parents($id){
		$membres = self::$table_membres;
		
		
		if (is_array($id)){
			if (count($id) == 0){
				return array();
			}
			$where = 'in (' . implode(',', $id) . ')';
		} else {
			if (is_null($id)){
				return array();
			}
			$where = "= $id";
		}
		
		$res = db::query("
			SELECT DISTINCT id_parent id
			FROM rel_parent
			WHERE id_enfant $where
		");
		if (!$res){
			return http_json::conditional_fin('acces_table','liste_parents');
		}
		$to_ret = array();
		if ($res->num_rows){
			while ($row = $res->fetch_assoc()){
				$to_ret[] = $row['id'];
			}
		}
		return $to_ret;
	}
	static function liste_parents_noms($id, $avec_id = false){
		$membres = self::$table_membres;
		
		
		if (is_array($id)){
			if (count($id) == 0){
				return array();
			}
			$where = 'in (' . implode(',', $id) . ')';
		} else {
			if (is_null($id)){
				return array();
			}
			$where = "= $id";
		}
		
		if ($avec_id){
			$id_parent = 'm.id,';
		} else {
			$id_parent = '';
		}
		
		$nom = self::prenom_nom_expr('m');
		
		$res = db::query("
			SELECT DISTINCT $id_parent $nom nom
			FROM rel_parent par
			JOIN membres m ON par.id_parent = m.id
			WHERE par.id_enfant $where
		");
		if (!$res){
			return http_json::conditional_fin('acces_table','liste_parents');
		}
		if ($avec_id){
			return db::result_array($res);
		}
		return db::result_array_values_one($res);
	}
	
	static function liste_enfants($id){
		$membres = self::$table_membres;
		
		if (is_array($id)){
			if (count($id) == 0){
				return array();
			}
			$where = 'in (' . implode(',', $id) . ')';
		} else {
			if (is_null($id)){
				return array();
			}
			$where = "= $id";
		}
		
		$res = db::query("
			SELECT DISTINCT id_enfant id
			FROM rel_parent
			WHERE id_parent $where
		");
		if (!$res){
			return http_json::conditional_fin('acces_table','liste_enfants');
		}
		$to_ret = array();
		if ($res->num_rows){
			while ($row = $res->fetch_assoc()){
				$to_ret[] = $row['id'];
			}
		}
		return $to_ret;
		
		
	}
	
	static function liste_enfants_noms($id, $avec_id = false){
		$membres = self::$table_membres;
		
		if (is_array($id)){
			if (count($id) == 0){
				return array();
			}
			$where = 'in (' . implode(',', $id) . ')';
		} else {
			if (is_null($id)){
				return array();
			}
			$where = "= $id";
		}
		$nom = self::prenom_nom_expr('m');
		
		if ($avec_id){
			$id_enfant = 'm.id,';
		} else {
			$id_enfant = '';
		}
		
		$res = db::query("
			SELECT DISTINCT $id_enfant $nom nom
			FROM rel_parent par
			JOIN membres m ON m.id = par.id_enfant
			WHERE id_parent $where
		");
		if (!$res){
			return http_json::conditional_fin('acces_table','liste_enfants');
		}
		if ($avec_id){
			return db::result_array($res);
		}
		return db::result_array_values_one($res);
		
	}
	
	static function change_domicile($liste, $id_domicile){
		if (!is_array($liste)){
			$liste = array($liste);
		}
		if (count($liste) == 0){
			return true;
		}
		$membres = self::$table_membres;
		
		$anciens_domiciles = self::liste_domiciles($liste);
		
		$liste = implode(',', $liste);
		$res = db::query($q = "
			UPDATE $membres
			SET id_domicile = $id_domicile
			WHERE id IN ($liste)
		");
		if (!$res){
			return http_json::conditional_fin('acces_table', 'change_domicile');
		}
		
		gestion_domiciles::efface_si_inutilise($anciens_domiciles);
		
		return true;
	}
	
	static function liste_domiciles($liste_membres){
		if (is_array($liste_membres)){
			if (!count($liste_membres)){
				return array();
			}
			$liste_membres = implode(',', $liste_membres);
		} else{
			if (is_null($liste_membres)){
				return array();
			}
		}
		$membres = self::$table_membres;
		
		$res = db::query($q = "
			SELECT DISTINCT m.id_domicile
			FROM $membres m
			WHERE id in ($liste_membres) and m.id_domicile is not null
		");
		if (!$res){
			return http_json::conditional_fin('acces_table','liste domiciles');
		}
		$to_ret = array();
		if($res->num_rows){
			while ($row = $res->fetch_assoc()){
				$to_ret[] = $row['id_domicile'];
			}
		}
		return $to_ret;
	}
	
	static function get_one($id, $champs, $lock = 0){
		$val = self::get($id, $champs, $lock);
		foreach($val as $v){
			return $v;
		}
        return null;
	}
	
	static function get($id, $champs,$lock = 0){
		$membres = self::$table_membres;
		$lock = self::lock_statement($lock);
		$array_result = true;
		if (is_array($champs)){
			$champs = implode(',', $champs);
		}
		
		if (is_array($id)){
			if (count($id) == 0){
				return array();
			}
			$id_cond = 'id IN (' . implode(',', $id) . ')';
			
		} else if (preg_match('#^\d+$#', $id)) {
			$id_cond = "id = $id";
			$array_result = false;
		} else if (preg_match('#(^|,) *\d *$')){
			
			$id_cond = "id IN ($id)";
		} else{
			return array();
		}
		
		$res = db::query($q = "
			SELECT $champs
			FROM $membres
			WHERE $id_cond
			$lock
		");
		//debug_print_once("$q");
		if (!$res){
			return http_json::conditional_fin('acces_table', "get $champs from membre");
		}
		if ($res->num_rows == 0){
			return array();
		}
		if (!$array_result){
			$row = $res->fetch_assoc();
			//debug_print_once(print_r($row,1));
			return $row;
		} else {
			$to_ret = array();
			while ($row = $res->fetch_assoc()){
				$to_ret[] = $row;
			}
			return $to_ret;
		}
	}
	
	static function lock_membres($liste, $lock = 1){
		if (is_array($liste)){
			if (count($liste) == 0){
                return null;
			}
			$liste = 'id in (' . implode(',', $liste) . ')';
		} else {
			$liste = " id = $liste";
		}
		$lock = self::lock_statement($lock);
		$membres = self::$table_membres;
		$res = db::query("
			SELECT id
			FROM $membres
			WHERE $liste
			$lock
		");
		if (!$res){
			return http_json::conditional_fin('acces_table','verrouillage membre');
		}
		return $res->num_rows;
		
	}
	static function lock_rel_parent($liste, $lock = 1){
		$lock = db::lock_statement($lock);
		$liste_par = self::liste_cond('id_parent', $liste);
		
		if ($liste_par === false){
            return null;
		}
		$liste_enf = preg_replace('#id_parent#', 'id_enfant', $liste_par);
		
		$res = db::query("
			select id
			FROM rel_parent
			WHERE ($liste_par) OR ($liste_enf)
			$lock
		");
		if (!$res){
			return http_json::conditional_fin('acces_table','verrouillage relation parent-enfant');
		}
        return null;
	}
	
	static function liste_cond($fld, $liste){
		if (is_array($liste)){
			if (count($liste) == 0){
				return false;
			}
			$liste = "$fld in (" . implode(',', $liste) . ')';
		} else {
			$liste = " $fld = $liste";
		}
		return $liste;
	}

	function fn_effacer_domicile(){
		extract(self::check_params(
				'id_membre;unsigned',
				'id_domicile;unsigned'
		));
		if (!perm::test('inscription')){
			self::verif_admin_ou_gerant($id_membre);
		}
		
		$domicile_courant = self::get_one($id_membre, 'id_domicile', 'update');
		if ($domicile_courant != $id_domicile){
			$this->fin('anciens_domiciles_different');
		}
		
		$res = db::query("
			UPDATE $this->table m
			SET id_domicile = null
			WHERE id = $id_membre
		",
				'acces_table','membre sans domicile');
		gestion_domiciles::efface_si_inutilise($id_domicile);
		$this->succes();
	}
	function fn_get_info_base(){
		
		extract(self::check_params(
				'id_membre;unsigned'
		));
		if (!perm::test('inscription')){
			$this->verif_admin_ou_gerant($id_membre);
		}
		
		$res = db::query("
			SELECT nom, prenom, distinction, date_naissance,id_hcr, age(date_naissance) age, sexe
			FROM $this->table
			WHERE id = $id_membre
		",
				'acces_table','get_info_base');
		if ($res->num_rows == 0){
			$this->fin('introuvable');
		}
		self::$data['data'] = $res->fetch_assoc();
		$this->succes();
	}
	function fn_get_info_contact(){
		extract(self::check_params(
				'id_membre;unsigned'
		));
		if (!perm::test('inscription')){
			self::verif_admin_ou_gerant($id_membre);
		}
		$res = db::query("
			SELECT 
				concat(m.prenom, ' ', m.nom) nom,
				m.courriel,m.cache_courriel,
				m.tel_jour,m.cache_tel_jour,
				m.tel_soir,m.cache_tel_soir,
				m.cell,m.cache_cell,
				d.tel tel_domicile, m.cache_tel_domicile,
				m.id_domicile
				
			FROM $this->table m
			LEFT JOIN domiciles d ON m.id_domicile = d.id
			WHERE m.id = $id_membre 
		",
				'acces_table','lecture info contact');
		if ($res->num_rows){
			$row = $res->fetch_assoc();
			$row['nom'] = self::formate_nom($row['nom']);
		} else{
			$row = array();
		}
		
		
		self::$data['values'] = $row;
		$this->succes();
	}
	function fn_update_contacts(){
        /**
         * @var $id_membre int
         * @var $id_domicile int
         * @var $updates array
         */
        extract(self::check_params(
				'id_membre;unsigned',
				'id_domicile;regex:#^\d*$#',
				'updates;array'
		));
		if ($id_domicile and array_key_exists('tel_domicile', $updates)){
			$update_domicile['tel_domicile'] = $updates['tel_domiciles'];
			unset($updates['tel_domiciles']);
			$domicile_courant = self::get_one($id_membre, 'id_domicile', 'share');
			if ($domicile_courant != $id_domicile){
				$this->fin('change_domicile');
			}
			self::change_domicile($id_membre, $id_domicile);
		}
		if (count($updates)){
			$this->update($id_membre, $updates);
		}
		self::enregistre_chg_info_contact($id_membre);
		
		$this->succes();
	}
	static function enregistre_chg_info_contact($id_membre){
		if (!is_numeric($id_membre)){
			return false;
		}
		$membres = self::$table_membres;
		$nom = session::get('nom');
		if (!$nom){
			return false;
		}
		
		$nom = db::sql_str($nom);
		
		$res = db::query("
			UPDATE $membres m
			SET m.editeur_info_contact = $nom
			m.date_info_contact = now()
			WHERE id = $id_membre
		");
		if (!$res){
			return http_json::conditional_fin('acces_table','enregistre chg info contact');
		}
		return true;
	}
	function fn_get_code(){
		extract(self::check_params(
				'id_membre;unsigned'
		));
		$res = db::query("
			SELECT pseudo
			FROM membres
			WHERE id = $id_membre
		",
				'acces_table','select pseudo');
		extract($res->fetch_assoc());
		
		self::$data['pseudo'] = $pseudo;
		

		$this->succes();
	}
	static function get_details($id){
		$membres = self::$table_membres;
		$res = db::query($q = "
			SELECT 
				concat(m.prenom, ' ', m.nom, if(distinction,concat(' (', m.distinction,')'),'')) nom,
				d.adr1,
				d.adr2,
				d.ville,
				d.code_postal
			FROM $membres m
			LEFT JOIN domiciles d ON m.id_domicile = d.id
			WHERE m.id = $id
				
		");
		if (!$res){
			return http_json::conditional_fin('acces_table','get_details_membre');
		}
		if (!$res->num_rows){
			return array();
		}
		$row = $res->fetch_assoc();
		self::normalise_details($row);
		return $row;
	}
	static function prenom_nom_expr($prefix = ''){
		if ($prefix){
			$prefix .= '.';
		}
		return "concat({$prefix}prenom, ' ', {$prefix}nom, if({$prefix}distinction,concat(' (', {$prefix}distinction, ')'), ''))";
	}
	static function nom_prenom_expr($prefix = ''){
		if ($prefix){
			$prefix .= '.';
		}
		return "concat({$prefix}nom, ', ', {$prefix}prenom, if({$prefix}distinction,concat(' (', {$prefix}distinction, ')'), ''))";
	}
	function fn_retirer_membre(){
		extract(self::check_params(
				'id;unsigned'
		));
		self::verif_et_effacer_membre($id);
		$this->succes();
	}
	static function verif_et_effacer_membre($id, $verifier_seulement = false){
		if (self::lock_membres($id,'update') == 0){
			return http_json::conditional_fin('introuvable');
		}
		if (self::get_one($id, 'id_hcr')){
			return http_json::conditional_fin('effacement_hcr_impossible');
		}
		
		if (count(gestion_famille::ont_un_seul_parent(self::liste_enfants($id)))){
			return http_json::conditional_fin('effacement_cree_orphelin');
		}
		$res = db::query("
			SELECT count(*) nb
			FROM role_equipe
			WHERE id_adulte = $id
		");
		if (!$res){
			return http_json::conditional_fin('acces_table','décompte rôles');
		}
		extract($res->fetch_assoc());
		if ($nb){
			return http_json::conditional_fin('echec_effacement_role');
		}
		if ($verifier_seulement){
			return true;
		}
		return self::effacer_membre($id);
		
	}
	static function effacer_membre($id){
		$table = self::$table_membres;
		$res = db::query("
			DELETE FROM $table
			WHERE id = $id
		");
		if (!$res){
			return http_json::conditional_fin('acces_table','effacement');
		}
		return true;
	}
	function fn_verifier_majorite_joueur(){
		extract(self::check_params(
				'id;unsigned'
		));
		
		if (!login_visiteur::logged_in()){
			$this->fin('non_autorise');
		}
		if ($id == 0){
			$id = session::get('id_visiteur');
		}
		$age = self::get_one($id, 'age(date_naissance) age');
		if (is_null(self::get_one($id, 'id_hcr'))){
			$this->fin('pas_joueur');
		}
		if (is_null($age)){
			$this->fin('age_inconnu');
		}
		if ($age < 18){
			$this->fin('pas_majeur');
		}
		$parents = gestion_famille::get_id_parents($id);
		if (count($parents)){
			$liens_parente_autres = gestion_famille::decompte_liens_familiaux($parents, $id);
			debug_print_once('liens familiaux = ' . print_r($liens_parente_autres,1));
			$parents_a_effacer = array();
			foreach($liens_parente_autres as $val){
				if ($val['nb'] == 0 and !is_null(self::get_one($val['id'], 'id_hcr'))){
					$parents_a_effacer[] = $val[id];
				}
			}
			// trouver si le parent est inscrit comme bénévole ou autrement
			if (count($parents_a_effacer)){
				$liste_parents = implode(',', $parents_a_effacer);
				$res = db::query("
					SELECT m.id
					FROM $this->table
					LEFT JOIN inscriptions i ON m.id = i.id_joueur
					LEFT JOIN inscription_benevoles b ON m.id = b.id_adulte
					WHERE i.id_joueur is not null or b.id_adulte is not null
					AND m.id IN ($liste_parents)
		",
						'acces_table','verification inscription parents');
				if($res->num_rows){
					while ($row = $res->fetch_assoc()){
						$liste_inscrits[] = $row['id'];
					}
					$parents_a_effacer = array_diff($parents_a_effacer, $liste_inscrits);
				}
				if (count($parents_a_effacer)){
					self::$data['parents_a_effacer'] = self::get_liste_noms($parents_a_effacer);
				}
			}
			
			
		}
		$this->succes();
	}
	static function courriel_utilise($courriel, $sql = 0){
		$membres = self::$table_membres;
		if (!$sql){
			$courriel = db::sql_str($courriel);
		}
		$res = db::query("
			SELECT COUNT(*) nb
			FROM $membres
			WHERE courriel = $courriel
			LOCK IN SHARE MODE
		");
		if (!$res){
			return http_json::conditional_fin('acces_table', 'verification utilisation courriel');
		}
		extract($res->fetch_assoc());
		return $nb?true:false;
	}

	static function peut_valider_courriel($id){
		$sujet = session::get('id_visiteur');
		return gestion_famille::conjoint_ou_enfant($sujet, $id);
	}
	
	function fn_valider_nouveau_courriel(){
		extract(self::check_params(
				'id;unsigned',
				'courriel;courriel',
				'code_validation;unsigned'
		));
		if (!login_visiteur::logged_in()){
			$this->fin('ouvrez_session');
		}
		$membre = session::get('id_visiteur');
		self::peut_valider_courriel($id) or $this->fin('pas_conjoint_ou_enfant');
		
		
		$verif_courriel = new gestion_courriels($id);
		$verif_courriel
			->set_courriel($courriel)
			->set_code($code_validation)
			->code_est_pour_nouv_courriel()
			->verifier_code();
		
		$nb_autres = $verif_courriel->accepte_nouveau_courriel_famille() - 1;
		
	
		self::$data['nb_autres'] = $nb_autres;
		$this->succes();
	}
	
	// pour valider un nouveau courriel
	function fn_envoyer_code_validation_nouveau_courriel(){
		extract(self::check_params(
				'id;unsigned',
				'courriel;courriel'
		));
		if (!self::peut_valider_courriel($id)){
			$this->fin('pas_conjoint_ou_enfant');
		}
		
		$envois = new gestion_envoi_codes_validation($courriel, 'nouveau_courriel');
		
		$envois->ajout_possible($id);
		
		$gest_courriel = new gestion_courriels($id);
		$gest_courriel->set_courriel($courriel)
				->code_est_pour_nouv_courriel()
				;
		
		if (!$gest_courriel->verifier_appartenance_courriel()){
			$this->fin('courriel_pas_a_vous');
		}
		$code = $gest_courriel->assigner_code_a_membre();
		$envois->msg_valider_nouvelle_adresse($id, $code)
				->ajout_envoi()
				->ajouter_destinataire($id, 1)
				->send();
		
		
		$this->succes();
	}
	function fn_get_liste_noms(){
		extract(self::check_params(
			'type;regex:#^(benevoles|tous|age)$#',
			'age;unsigned;opt'
		));
		if (!perm::test('admin')){
			$this->fin('non_autorise');
		}
		
		if ($type == 'age' and !isset($age)){
			$this->fin('manque_param', 'age');
		}
		
		self::$data['liste'] = array();
		
		if ($type == 'benevoles'){
			$res = db::query("
				SELECT *
				FROM benevoles
				WHERE 1
			",
					'acces_table','recherche benevoles');
		} else {

			if ($type == 'tous'){
				$cond = '1';
			} else {
				$cond = "date_naissance is null or age(date_naissance) >= $age";
			}

			$nom = self::nom_prenom_expr('m');
			$res = db::query("
				SELECT id, $nom nom, if(b.id is null, 0, 1) benevole
				FROM membres m
				LEFT JOIN benevoles b USING(id)
				WHERE $cond
				ORDER BY m.nom, m.prenom
			",
					'acces_table',"recherche membres par type $type");
		}
		
		if ($res->num_rows){
			while ($row = $res->fetch_assoc()){
				$row['nom'] = ucwords($row['nom']);
				self::$data['liste'][] = $row;
			}
		}
		$this->succes();
		
	}
	function fn_get_noms_specs(){
		if (!perm::test('admin,inscription') and !perm::test('officiel') and !perm::resp_niveau()){
			$this->fin('non_autorise');
		}
		extract(self::check_params(
				'debut;string;opt;default_empty',
				'exclure_id;array_unsigned;opt;default_empty',
				'age_min;unsigned;opt;default_empty',
				'mode_choix;regex:#^(tous|saison)$#'
		));
		
		$nom = self::nom_prenom_expr('m');
		
		if ($debut){
			$debut = 'AND m.nom LIKE ' . db::sql_str($debut . '%');
		} else {
			$debut = '';
		}
		
		if (count($exclure_id)){
			$exclure_id = 'AND m.id NOT IN (' . implode(',', $exclure_id) . ')';
		} else {
			$exclure_id = '';
		}
		
		if ($age_min){
			$age_min = "HAVING m.age >= $age_min";
		} else{
			$age_min = '';
		}
		
		if ($mode_choix == 'saison'){
			$saison = saisons::get('courante');
			
			$join = "
				JOIN (
					SELECT id_joueur id
					FROM inscriptions
					WHERE saison = $saison
			
					UNION DISTINCT
			
					SELECT DISTINCT id_parent id
					FROM rel_parent par
					JOIN inscriptions i ON par.id_enfant = i.id_joueur
					WHERE i.saison = $saison
			
					UNION DISTINCT
			
					SELECT id_membre id
					FROM inscription_benevoles
					WHERE saison = $saison
				) a USING(id)
				";
		} else {
			$join = '';
		}
		
		
		$res = db::query("
			SELECT m.id, $nom nom, age(m.date_naissance) age, concat(m.prenom, ' ', m.nom) prenom_nom
			FROM membres m
			$join
			WHERE 1 
				$debut
				$exclure_id
				$age_min
				ORDER BY nom, prenom
		",
				'acces_table','liste de membres');
		
		self::$data['liste'] = db::result_array($res);
		foreach(self::$data['liste'] as &$val){
			$val['nom'] = self::formate_nom($val['nom']);
			$val['prenom_nom'] =self::formate_nom($val['prenom_nom']);
		}
		$this->succes();
		
	}
	function fn_exclure_benevole(){
		if (!perm::test('admin')){
			fin('non_autorise');
		}
		extract(self::check_params(
			'id;unsigned'
		));
		$res = db::query("
			SELECT count(ib.id_membre) nb_inscr, count(re.id_adulte) nb_roles, m.benevole
			FROM membres m
			LEFT JOIN inscription_benevoles ib on m.id = ib.id_membre
			LEFT JOIN role_equipe re ON re.id_adulte = m.id
			WHERE m.id = $id
			FOR UPDATE
				
		",
				'acces_table','verifier statut benevole');
		extract($res->fetch_assoc());
		if (!($nb_roles or $nb_inscr)){
			$new_val = '0';
		} else {
			$new_val = 'null';
		}
		$res = db::query("
			UPDATE membres
			SET benevole = $new_val
			WHERE id = $id
		",
				'acces_table','maj statut benevole');
		$this->succes();
	}
	function fn_inclure_benevole(){
		if (!perm::test('admin')){
			fin('non_autorise');
		}
		extract(self::check_params(
			'id;unsigned'
		));
		$res = db::query("
			SELECT count(ib.id_membre) nb_inscr, count(re.id_adulte) nb_roles, m.benevole
			FROM membres m
			LEFT JOIN inscription_benevoles ib on m.id = ib.id_membre
			LEFT JOIN role_equipe re ON re.id_adulte = m.id
			WHERE m.id = $id
			FOR UPDATE
				
		",
				'acces_table','verifier statut benevole');
		extract($res->fetch_assoc());
		if ($nb_roles or $nb_inscr){
			$new_val = '0';
		} else {
			$new_val = '1';
		}
		$res = db::query("
			UPDATE membres
			SET benevole = $new_val
			WHERE id = $id
		",
				'acces_table','maj statut benevole');
		$this->succes();
		
	}

	function fn_get_liste_noms_adr(){
		extract(self::check_params(
			'commence;string;min(1)'
		));
		
		$like_expr = db::sql_str($commence . '%');
		$nom = self::nom_prenom_expr('m');
		
		$res = db::query("
			SELECT $nom nom, CONCAT_WS('\n', d.adr1, d.ville, d.code_postal) adr, m.id, m.id_domicile
			FROM membres m
			JOIN domiciles d ON m.id_domicile = d.id
			WHERE nom LIKE $like_expr
			ORDER BY nom, prenom
		",
				'acces_table','liste noms');
		if ($res->num_rows){
			while ($row = $res->fetch_assoc()){
				self::$data['liste'][] = $row;
			}
		} else {
			self::$data['liste'] = array();
		}
		$this->succes();
	}
	function fn_verifier_officiel(){
		if (!session::get('id_visiteur')){
			$this->fin('ouvrez_session');
		}
		extract(self::check_params(
				'struct_elem;unsigned',
				'data;array'
		));
		http_json::set_source_check_params_once($data);
		$contexte = self::check_params(
				'contexte_division;unsigned',
				'contexte_classe;unsigned',
				'contexte_equipe;string;min(2)'
		);
		$contexte['element_structure'] = $struct_elem;
		
		$contexte = new contexte($contexte);
		if (!($id_equipe = $contexte->get_equipe())){
			$this->fin('equipe_introuvable');
		}
		if (!perm::officiel_de($id_equipe) and !perm::test('superhero') and !perm::test('admin')){
			$this->fin('pas_officiel_equipe');
		}
		self::$data['id_equipe'] = $id_equipe;
		self::$data['nom_equipe'] = equipes::get_nom_complet($id_equipe);
		$this->succes();
		
	}
    function fn_verifier_responsable_niveau()
    {
		if (!($id_membre = session::get('id_visiteur'))){
			$this->fin('ouvrez_session');
		}
		extract(self::check_params(
				'struct_elem;unsigned',
				'data;array'
		));
		http_json::set_source_check_params_once($data);
		extract(self::check_params(
				'contexte_division;unsigned;rename:division'
		));
		$res = db::query("
            SELECT count(*) nb, rn.description, rn.categ
            FROM permissions_niveaux pn
            JOIN rang_niveau rn USING(categ)
            WHERE rn.id = $division 
                AND pn.classe IS NULL
                AND pn.id_membre = $id_membre
                AND pn.controleur IS NOT NULL
                AND pn.controleur > NOW()
		",
			'acces_table','');
        extract($res->fetch_assoc());
        if ($nb != 1){
            $this->fin('pas_resp_niveau');
        }
        self::$data['id_division'] = $division;
        self::$data['nom_division'] = $description;
        self::$data['categ'] = $categ;
        
        // fournir un choix de saisons
        $nom = saisons::get_nom($saison = saisons::courante());
        self::$data['saison_courante'] = array('id' => $saison, 'nom'=> ($nom? $nom : '?'), 'debut' => saisons::get_fld('debut'), 'fin' => saisons::get_fld('fin'));
        
        if (($saison = saisons::prochaine())){
            $nom = saisons::get_nom($saison);
            self::$data['saison_prochaine'] = array('id' => $saison, 'nom'=> $nom ? $nom : '?', 'debut'=>saisons::get_fld('debut', 'prochaine'), 'fin'=>saisons::get_fld('fin', 'prochaine'));
        }
        
        db::sql_str_($categ);
        $res = db::query("
            SELECT count(*) nb
            FROM niveaux
            WHERE categ = $categ AND horaires_manuels
		", 			'acces_table', '');
        extract($res->fetch_assoc());
        if ($nb){
            self::$data['horaires_manuels'] = '1';
        }
        $this->succes();
    }
	static function formate_ce_nom(&$array, $ind = 'nom'){
		$array[$ind] = self::formate_nom($array[$ind]);
	}
	
	function fn_choix_de_membres(){
		extract(self::check_params(
				'code_postal;code_postal;accept_empty_string',
				'nom;string',
				'age_minimum;int;opt'
		));
		$cond = array();
		if (strlen($nom)){
			$cond[] = 'm.nom = ' . db::sql_str($nom);
		}
		if (strlen($code_postal)){
			$cond[] = 'd.code_postal = ' . db::sql_str($code_postal);
		}
		
		if (count($cond) == 0){
			self::$data['liste'] = array();
			$this->succes();
		}
		if ($age_minimum){
			$age_cond = "HAVING age IS NULL OR age >= $age_minimum";
		} else {
			$age_cond = '';
		}
		$cond = implode(' OR ', $cond);
		
		$res = db::dquery("
			SELECT 
				m.id,
				concat(m.nom, ', ', m.prenom) nom,
				d.adr1,
				d.adr2,
				d.ville,
				d.code_postal,
				ifnull(GROUP_CONCAT(concat(e.prenom, ' ', e.nom) separator '; '), '') enfants,
				ifnull(GROUP_CONCAT(DISTINCT replace(concat_ws('<br/>', d_e.adr1,d_e.ville,d_e.code_postal), ';',' ') separator ';'), '') adr_enfants,
				age(m.date_naissance) age
			FROM membres m
			LEFT JOIN domiciles d ON m.id_domicile = d.id
			LEFT JOIN rel_parent par ON par.id_parent = m.id
			LEFT JOIN membres e ON par.id_enfant = e.id
			LEFT JOIN domiciles d_e ON e.id_domicile = d_e.id
			WHERE $cond
			GROUP BY m.id
			$age_cond
			ORDER BY m.nom, m.prenom
		",
				'acces_table','parents');
		self::$data['liste'] = db::result_array($res);
		$this->succes();
	}
	function fn_gerant_edite_details_lire(){
		extract(self::check_params(
				'id;unsigned'
		));
		
		if (!perm::test('admin') and !perm::est_gerant_de(session::get('id_visiteur'),$id) and !perm::est_resp_niveau_de(session::get('id_visiteur'), $id)){
			$this->fin('reserve_gerant');
		}
		$nom = self::prenom_nom_expr();
		self::$data['vals'] = self::get($id, "$nom nom,courriel,tel_jour,tel_soir,cell");
		$this->succes();
	}
	function fn_gerant_edite_details_ecrire(){
		extract(self::check_params(
				'id;unsigned',
				'vals;array'
		));
		
		if (!perm::test('admin') and !perm::est_gerant_de(session::get('id_visiteur'), $id) and !perm::est_resp_niveau_de(session::get('id_visiteur'), $id)){
			$this->fin('reserve_gerant');
		}
		$vals = array_extract($vals, 'courriel,tel_jour,tel_soir,cell');
		$this->validate_updates($vals);
		$this->apply_updates($id,$vals);
		
		$this->succes();
	}
	static function get_domiciles($id){
		if (is_array($id)){
			$id_cond = 'id IN (' . implode(',', $id) . ')';
		} else {
			$id_cond = "id = $id";
		}
		$res = db::query("
			SELECT DISTINCT id_domicile
			FROM membres
			WHERE $id_cond and id_domicile IS NOT NULL
		");
		if (!$res){
			return http_json::conditional_fin('acces_table','gestion membres / get_domiciles');
		}
		return db::result_array_values_one($res);
	}
    function fn_liste_membres_division()
    {
        extract(self::check_params(
			'id_division;unsigned',
            'id_saison;unsigned;min:1'
		));
        if ($id_division < 1){
            $this->fin('division_inconnue');
        }
        
        if (!perm::test('admin') and !perm::resp_niveau($id_division)){
            $this->fin('non_autorise');
        }
        $res = db::query("
            SELECT naissance_min, naissance_max
            FROM tableaux_ages
            WHERE id_division = $id_division and saison = $id_saison
		",
			'acces_table','recherche naissance min et max');
        if ($res->num_rows == 0){
            $this->fin('division_inconnue');
        }
        extract($res->fetch_assoc());
        self::$data['naissance_min'] = $naissance_min;
        self::$data['naissance_max'] = $naissance_max;
        
        if (!preg_match('#^\d{4}-\d\d-\d\d$#', $naissance_min) or !preg_match('#^\d{4}-\d\d-\d\d$#', $naissance_max) or $naissance_min > $naissance_max){
            $this->fin('corriger_tableau_ages');
        }
        
        
        
        // retourner les joueurs qui sont dans le fichier d'inscription OU dans une équipe pour la saison donnée
        
        $res = db::query("
            SELECT DISTINCT m.id, 
                m.nom, 
                m.prenom,
                m.distinction, 
                m.date_naissance, 
                age(m.date_naissance) age, 
                m.courriel, 
                if(i.id IS NOT NULL, 1, 0) inscrit, 
                ifnull(e.id_equipe, -1) id_equipe, 
                CONCAT(e.categ, ' ', e.classe, ' ', e.nom) nom_equipe
            FROM membres m
            LEFT JOIN inscriptions i ON m.id = i.id_joueur AND i.saison = $id_saison
            LEFT JOIN (
                SELECT je.id_joueur, e.id_equipe, e.nom, e.niveau, n.categ categ, n.classe
                FROM joueur_equipe je
                JOIN equipes e USING(id_equipe)
                JOIN niveaux n USING(niveau)
                WHERE e.id_saison = $id_saison
            )  e ON m.id = e.id_joueur
            WHERE m.date_naissance BETWEEN '$naissance_min' AND '$naissance_max'
            ORDER BY m.nom, m.prenom, m.distinction
		",
			'acces_table','');
        self::$data['liste'] = db::result_array($res);
        foreach(self::$data['liste'] as &$val){
            $val['nom_equipe'] = cfg_yml::abreviation_equipe($val['nom_equipe']);
        }
        $this->succes();
    }
    
     function fn_liste_membres_division_inscrits()
    {
        extract(self::check_params(
			'id_division;unsigned',
            'id_saison;unsigned;min:1'
		));
        if ($id_division < 1){
            $this->fin('division_inconnue');
        }
        
        if (!perm::test('admin') and !perm::resp_niveau($id_division)){
            $this->fin('non_autorise');
        }
        
        // retourner les joueurs qui sont dans le fichier d'inscription OU dans une équipe pour la saison donnée
        $nom = self::nom_prenom_expr('m');
        $res = db::query("
            SELECT DISTINCT m.id, 
                $nom nom, 
                age(m.date_naissance) age, 
                m.courriel, 
                ifnull(e.id_equipe, -1) id_equipe, 
                CONCAT(e.categ, ' ', e.classe, ' ', e.nom) nom_equipe,
                ifnull(dj.no_chandail, 0) no_chandail,
                dj.position,
                dj.nb_parties
            FROM membres m
            LEFT JOIN inscriptions i ON m.id = i.id_joueur AND i.saison = $id_saison
            LEFT JOIN dossier_joueur dj ON dj.id_joueur = m.id AND dj.saison = $id_saison
            LEFT JOIN (
                SELECT je.id_joueur, e.id_equipe, e.nom, e.niveau, n.categ categ, n.classe
                FROM joueur_equipe je
                JOIN equipes e USING(id_equipe)
                JOIN niveaux n USING(niveau)
                WHERE e.id_saison = $id_saison
            )  e ON m.id = e.id_joueur
            WHERE i.id IS NOT NULL OR dj.id IS NOT NULL
            ORDER BY nom
		",
			'acces_table','');
        self::$data['liste'] = db::result_array($res);
        foreach(self::$data['liste'] as &$val){
            $val['nom_equipe'] = cfg_yml::abreviation_equipe($val['nom_equipe']);
        }
        $this->succes();
    }
    
    
    /**
     * appeler pour obtenir une liste de membres avec courriel donné,
     * avec les parents de chacun à gauche et les enfants à droite
     * - dont l'âge est inconnu ou les rend admissibles pour la division donnée
     * - avec les membres admissibles surlignés
     */
    
    
    function fn_membres_avec_courriel()
    {
        $this->is_admin_inscr_resp_niveau();
        
        extract(self::check_params(
                'courriel;courriel;sql',
                'id_division;unsigned',
                'id_saison;unsigned'
		));
        $this->liste_membres_lies($id_saison, $id_division, $courriel, 'courriel');
        return;
        
    }
    function fn_membres_par_nom()
    {
        $this->is_admin_inscr_resp_niveau();
        extract(self::check_params(
                'nom;string;min:2',
                'id_division;unsigned',
                'id_saison;unsigned'
		));
        $nom = db::sql_str($nom . '%');
        $this->liste_membres_lies($id_saison, $id_division, $nom, 'debut_nom');
        return;
        
        
        
    }
    /**
     * 
     * @param int $id_saison
     * @param int $id_division
     * @param string $donnee
     * @param string $type_donnee = 'courriel' | 'debut_nom'
     */
    function liste_membres_lies($id_saison, $id_division, $donnee, $type_donnee)
    {
         if ($id_saison != saisons::courante() and $id_saison != saisons::prochaine()){
            $this->fin('saison_pas_courante_ni_prochaine');
        }
        if (gestion_divisions::lock($id_division) == 0){
            $this->fin('division_inconnue');
        }
        $res = db::query("
            SELECT COUNT(*) nb, naissance_min, naissance_max
            FROM tableaux_ages
            WHERE saison = $id_saison AND id_division = $id_division
		", 			'acces_table', '');
        extract($res->fetch_assoc());
        if ($nb != 1){
            $this->fin('corriger_tableau_ages');
        }
        
        $nom = self::nom_prenom_expr('m');
        
        if ($type_donnee == 'courriel'){
            $where = "m.courriel = $donnee";
        } else {
            $where = "m.nom LIKE $donnee";
        }
        
        $res = db::dquery("
            SELECT m.id, GROUP_CONCAT(par.id_enfant SEPARATOR ',') enfants, GROUP_CONCAT(enf.id_parent SEPARATOR ',') parents 
            FROM membres m
            LEFT JOIN rel_parent par ON par.id_parent = m.id
            LEFT JOIN rel_parent enf ON enf.id_enfant = m.id
            WHERE $where
            GROUP BY m.id
            ORDER BY date_naissance
            
		", 			'acces_table', '');
        
        $liste = db::result_array($res);
        $tous_ids = array();
        foreach($liste as &$vals){
            if (!is_null($vals['enfants'])){
                $vals['enfants'] = explode(',', $vals['enfants']);
            } else {
                $vals['enfants'] = array();
            }
            if (!is_null($vals['parents'])){
                $vals['parents'] = explode(',', $vals['parents']);
            } else {
                $vals['parents'] = array();
            }
            
            $tous_ids = array_merge($tous_ids, array($vals['id']), $vals['enfants'], $vals['parents']);
        }
       $tous_ids[] = -1;
       $tous_ids = implode(',', array_unique($tous_ids));
        $min = $naissance_min;
        $max = $naissance_max;
        db::sql_str_($naissance_min);
        db::sql_str_($naissance_max);
        $res = db::dquery("
            SELECT DISTINCT m.id, 
                    $nom nom, 
                    age(date_naissance) age, 
                    IF(((m.date_naissance BETWEEN $naissance_min AND $naissance_max) OR m.date_naissance IS NULL) AND i.id IS NULL,1,0) selectable,
                    IF(m.date_naissance < $naissance_min,1,0) trop_vieux,
                    IF(m.date_naissance > $naissance_max, 1, 0) trop_jeune,
                    courriel,
                    IF(i.id IS NULL, 0, 1) inscrit
            FROM membres m
            LEFT JOIN inscriptions i ON i.id_joueur = m.id and i.saison = $id_saison
            WHERE m.id IN ($tous_ids)
		", 			'acces_table', '');
        $ref = db::result_array($res, 'id');
        
        self::load_msg();
        
        self::$data['table'] = twig::render('liste_candidats_par_courriel.html.twig', array(
            'liste_relations' => $liste,
            'ref' => $ref,
            'msg' => self::$msg->get_msg(),
            'type_liste' => $type_donnee,
        ));
        self::$data['naissance_min'] = $min;
        self::$data['naissance_max'] = $max;
        
        $this->succes();
    }
    function fn_get_set_data_membre()
    {
        extract(self::check_params(
                'id_membre;unsigned',
                'updates;array;opt'
		));
        if (!login_visiteur::logged_in()){
            $this->fin('ouvrir_session');
        }
        $id_visiteur = session::get('id_visiteur');
        
        do {
            // admin et resp inscription peut éditer sans autre vérification
            if (perm::test('admin') or perm::test('inscription') or perm::test('admin_inscription')){
                self::$data['info_perm'] = self::$msg->get(perm::test('admin')?'vous_etes_admin':'avez_acces_inscriptions');
            } else {
                $liste_categ = gestion_divisions::liste_resp_categ();
                if (count($liste_categ) == 0){
                    $this->fin('non_autorise');
                }
                self::$data['info_perm'] = self::$msg->get('etes_resp_division'). ' (' . implode('; ', $liste_categ) . ')';
                $is_resp_division = true;
                // si simplement resp niveau, ne peut éditer naissance de qqn avec HCR et date de naissance
                extract(self::get($id_membre, 'id_hcr,date_naissance'));
                if ($date_naissance){
                    $saison = saisons::inscription();
                    if ($saison < 0){
                        $saison = saisons::courante();
                    }
                    if ($saison < 0){
                        $this->fin('saison_courante_ou_inscr_manque');
                    }
                    $categs = implode(',', db::sql_str($liste_categ));
                    $sql_naissance = db::sql_str($date_naissance);
                    $res = db::dquery("
                        SELECT COUNT(*) nb
                        FROM tableaux_ages ta
                        JOIN rang_niveau rn ON ta.id_division = rn.id
                        WHERE rn.categ in ($categs) 
                            AND $sql_naissance BETWEEN ta.naissance_min AND ta.naissance_max
                            AND ta.saison = $saison
                    ", 			'acces_table', '');
                    extract($res->fetch_assoc());
                    if ($nb == 0){
                        $this->fin('hors_categ_age_resp_div');
                    }
                }
                if ($id_hcr and $date_naissance){
                    if (!isset($updates)){
                        self::$data['disabled'] = array('date_naissance');
                        self::$data['info'][] = self::$msg->get('naissance_non_modifiable_si_hcr');
                    } else {
                        if (array_key_exists('date_naissance', $updates)){
                            unset($updates['date_naissance']);
                        }
                    }
                }
                if (!$date_naissance){
                    self::$data['warnings'][] = self::$msg->get('attention_a_date');
                }
                
            }
            if (!isset($updates)){
                break;
            }
            //**** rendu ici, il y a des maj à faire et le visiteur est autorisé
            
            // vérifier si la mise à jour de la date de naissance est ok
            // ok si est nulle;
            // sinon refusée si membre lié à une équipe dont la division n'accepte pas l'âge proposé (surclassement toléré pour 1 an)
            
            if (array_key_exists('date_naissance', $updates) and !$updates['date_naissance']){
                unset($updates['date_naissance']);
            }
            if (array_key_exists('date_naissance', $updates)){
                
                $nouvelle_naissance = $updates['date_naissance'];
                
                $saison_inscr = saisons::inscription();
                $saison_cour = saisons::courante();

                if (!$sql_naissance){
                    $sql_naissance = db::sql_str($nouvelle_naissance);
                }

                if ($saison_inscr > 0 or $saison_cour > 0){
                    $cond = 'e.id_saison = %1$s AND ta.saison = %1$s';
                    if ($saison_inscr > 0){
                        $cond_saison = sprintf($cond, $saison_inscr);
                    } else {
                        $cond_saison = 'false';
                    }
                    if ($saison_cour > 0 and $saison_cour != $saison_inscr){
                        $cond_saison .= ' OR ' . sprintf($cond, $saison_cour);
                    }
                    $res = db::dquery("
                        SELECT DISTINCT concat(e.niveau, '-', e.nom, ' (', s.nom_saison, ')') eq
                        FROM joueur_equipe je
                        JOIN equipes e USING(id_equipe)
                        JOIN saisons s ON e.id_saison = s.id
                        JOIN niveaux n USING(niveau)
                        JOIN rang_niveau rn ON n.categ = rn.categ
                        JOIN tableaux_ages ta ON rn.id = ta.id_division
                        WHERE ($cond_saison)
                                AND $sql_naissance NOT BETWEEN ADDDATE(ta.naissance_min, INTERVAL -1 year) AND ta.naissance_max
                                and je.id_joueur = $id_membre
                    ", 			'acces_table', '');

                    if ($res->num_rows){
                        $this->fin(self::msg('age_incompatible_avec_inscr'), implode(';', db::result_array_values_one($res)));
                    }
                    // vérifier que le visiteur peut gérer un joueur de l'âge donné
                    // s'il est seulement resp de niveau, l'âge doit appartenir à son niveau
                    if (!$categs) {
                        $categs = implode(',', db::sql_str($liste_categ));
                    }
                    if ($saison_inscr > 1){
                        $saison = $saison_inscr;
                    } else {
                        $saison = $saison_cour;
                    }
                    if ($is_resp_division){
                        $res = db::dquery("
                            SELECT count(*) nb
                            FROM tableaux_ages ta
                            JOIN rang_niveau rn ON ta.id_division = rn.id
                            WHERE $sql_naissance BETWEEN ta.naissance_min AND ta.naissance_max
                                AND ta.saison = $saison
                                AND rn.categ IN ($categs)
                                    
                        ", 			'acces_table', '');
                        extract($res->fetch_assoc());
                        if ($nb == 0){
                            $this->fin('hors_categ_age_resp_div');
                        }
                    }
                } else {
                    if ($is_resp_division){
                        $this->fin('saison_courante_ou_inscr_manque');
                    }
                    
                }
            }
            $this->update($id_membre, $updates);
            $nom = self::nom_prenom_expr('m');
            $res = db::query("
                SELECT $nom nom, age(date_naissance) age, courriel, date_naissance
                FROM membres m
                WHERE m.id = $id_membre
            ", 			'acces_table', '');
            if ($res->num_rows == 0){
                $this->fin('introuvable');
            }
            self::$data['html'] = twig::render('liste_occupants_domiciles_par_nom.html.twig', array(
                'id'        =>  $id_membre,
                'data'      =>  $res->fetch_assoc(),
                'un_seul_membre' => 1
                ));
            $this->succes();
        } while (false);
        
        self::$data['vals'] = self::get($id_membre, 'prenom,nom,date_naissance,courriel,cell,tel_soir,tel_jour');
        $this->succes();
    }
    function is_admin_inscr_resp_niveau(){
        if (!perm::test('admin') and !perm::test('inscription') and !perm::test('admin_inscription')){
            $liste_categs = gestion_divisions::liste_resp_categ();
            if (count($liste_categs) == 0){
                $this->fin('fonction_reservee_admin_inscr_resp');
            }
        }
        return true;
    }
    function fn_selection_membres()
    {
        extract(self::check_params(
                'selection;array',
                'nb_par_page;unsigned;opt;default:50',
                'debut;unsigned;opt;default:0',
                'pagination;bool;opt;default:1',
                'multi_select;bool;opt;default:1'
		));
        self::set_source_check_params_once($selection);
        extract(self::check_params(
                'age_connu;unsigned;max:2',
                'age_min;bool',
                'age_min_val;unsigned;max:99',
                'age_max;bool',
                'age_max_val;unsigned;max:99',
                'avec_enfants;unsigned;max:2',
                'avec_parents;unsigned;max:2',
                'filtrer_genre;unsigned;max:2',
                'filtrer_nom;bool',
                'nom_commence;string'
		));
        
        if ($pagination){
            $sql_calc = 'SQL_CALC_FOUND_ROWS';
        } else{
            $sql_calc = '';
        }
        
        $nom = self::nom_prenom_expr('m');
        $select = "SELECT $sql_calc DISTINCT m.id, $nom nom, age(date_naissance) age, courriel, count(enfants.id) nb_enfants, count(parents.id) nb_parents";
       // , count(parents.id) nb_parents";
        $from = array('FROM membres m');
        $from[] = 'LEFT JOIN rel_parent enfants ON m.id = enfants.id_parent';
        $from[] = 'LEFT JOIN rel_parent parents ON m.id = parents.id_enfant';
        
        $cond = array(1);
        if ($age_connu == 1){
            $cond_age_connu = 'm.date_naissance IS NOT NULL';
        } else if ($age_connu == 0){
            $cond_age_connu = 'm.date_naissance IS NULL';
        } else {
            $cond_age_connu = 1;
        }
        if ($age_min and $age_max){
            $condition_age = "age(m.date_naissance) BETWEEN $age_min_val AND $age_max_val";
        } else if ($age_min){
            $condition_age = "age(m.date_naissance) >= $age_min_val";
        }else if ($age_max){
            $condition_age = "age(m.date_naissance) <= $age_max_val";
        } else {
            $condition_age = 1;
        }
        if ($condition_age == 1){ // pas de restriction d'âge
            $condition_age = $cond_age_connu;
        } else {
            if ($age_connu == 0 or $age_connu == 2){
                $condition_age = "(m.date_naissance IS NULL OR $condition_age)";
            } 
        }
        $cond[] = $condition_age;
        
        if ($avec_enfants == 0){
            $cond[] = 'enfants.id_parent IS NULL';
        } else if ($avec_enfants == 1){
            $cond[] = 'enfants.id_parent IS NOT NULL';
        }
        
        if ($avec_parents == 0){
            $cond[] = 'parents.id_enfant IS NULL';
        } else if ($avec_parents == 1){
            $cond[] = 'parents.id_enfant IS NOT NULL';
        }
        
        if ($filtrer_genre == 1){
            $cond[] = "sexe = 'M'";
        } else if ($filtrer_genre == 0){
            $cond[] = "sexe = 'F'";
        }
        if ($filtrer_nom and $nom_commence){
            $nom_commence .= '%';
            db::sql_str_($nom_commence);
            $cond[] = "nom LIKE $nom_commence";
        }
        
        $cond = implode("\n AND ", $cond);
        $from = implode(" \n", $from);
        
        $query = "$select $from WHERE $cond
            GROUP BY m.id
            ORDER BY nom, prenom
            
        ";
        if ($pagination){
            $query .= " LIMIT $debut, $nb_par_page";
        }
        $res = db::dquery($query, 			'acces_table', '');
        $data = db::result_array($res);
        if ($pagination){
            $res = db::query("
                SELECT FOUND_ROWS() nb_rows
            ", 			'acces_table', '');
            extract($res->fetch_assoc());
        } else {
            $nb_rows = 0;
        }
        
        self::$data['html'] = twig::render('liste_selection_membres.html.twig', array(
            'data' => $data,
            'debut' => $debut,
            'nb_par_page' => $nb_par_page,
            'msg' => self::$msg->get_msg(),
            'pagination' => $pagination,
            'nb_total' => $nb_rows,
            'debug' => 0,
            'multiselect' => $multi_select
        ));
        $this->succes();
    }
    
    function fn_chercher_freres()
    {
        $this->is_admin_inscr_resp_niveau();
        extract(self::check_params(
                'naissance_membre;date;sql',
                'id_membre_famille;unsigned'
		));
        $nom = self::nom_prenom_expr('m_enf');
        $nom_par = self::nom_prenom_expr('m_par');
        // trouver tous les enfants des parents du membre (enfant) fourni
        
        $res = db::dquery("
            SELECT  m_enf.id, $nom nom, 
                age(m_enf.date_naissance) age, 
                IF(ADDDATE(m_enf.date_naissance, INTERVAL -16 YEAR) >= $naissance_membre, 1, 0) age_ok, 
                count(*) nb_parents,
                par.id_parent id_parent,
                GROUP_CONCAT($nom_par SEPARATOR '\n') nom_parent
                
                FROM membres m_enf
                JOIN rel_parent par ON m_enf.id = par.id_enfant
                JOIN membres m_par ON par.id_parent = m_par.id
                WHERE par.id_parent IN 
                (SELECT
                    id_parent FROM rel_parent rp
                    WHERE rp.id_enfant = $id_membre_famille
                    )
                GROUP BY m_enf.id
                ORDER BY m_enf.date_naissance
		", 			'acces_table', '');
        
        if ($res->num_rows){
            self::$data['table'] = twig::render('liste_enfants_de_nouveau_membre.html.twig', array(
                'liste' => db::result_array($res),
                'msg' => self::$msg->get_msg()
                    
            ));
            $this->succes();
        }
        // trouver les enfants du membre fourni (s'il s'agit d'un parent)
        $res = db::dquery("
            SELECT  m_enf.id, $nom nom, 
                    age(m_enf.date_naissance) age, 
                    IF(ADDDATE(m_enf.date_naissance, INTERVAL -16 YEAR) >= $naissance_membre, 1, 0) age_ok, 
                    count(*) nb_parents,
                    par.id_parent id_parent,
                    GROUP_CONCAT($nom_par SEPARATOR '\n') nom_parent
                FROM membres m_enf
                JOIN rel_parent par ON par.id_enfant = m_enf.id
                JOIN rel_parent par2 ON m_enf.id = par2.id_enfant
                JOIN membres m_par ON par.id_parent = m_par.id
                WHERE par.id_parent = $id_membre_famille
                GROUP BY m_enf.id
                ORDER BY m_enf.date_naissance
		", 			'acces_table', '');
        if ($res->num_rows){
            self::$data['table'] = twig::render('liste_enfants_de_nouveau_membre.html.twig', array(
                'liste' => db::result_array($res),
                'msg' => self::$msg->get_msg()
                    
            ));
            $this->succes();
        }
        
        
        $this->fin('membre_ni_parent_ni_enfant');
        
    }
    function fn_get_liste_avec_mdp()
    {
        if (!perm::test('admin')){
            $this->fin('non_autorise');
        }
        $res = db::query("
                SELECT CONCAT(nom, ', ', prenom) label, id value
                FROM $this->table
                WHERE mot_passe <> ''
                ORDER BY nom, prenom
            ", 'acces_table');
        self::$data['liste'] = db::result_array($res);
        $this->succes();
    }
    static function existe($id, $lock = false) {
        $lock_clause = 'LOCK IN SHARE MODE';
        if($lock) {
            $lock_clause = 'FOR UPDATE';
        }
        $res = db::query("
                SELECT id
                FROM membres
                WHERE id = $id
                $lock_clause
            ", 'acces_table');
        return !!$res->num_rows;

    }
    function fn_creation_par_marqueur()
    {
        # permis pour admin, ou par visiteur s'il est marqueur du match dans la demi-heure qui vient ou les 12 dernières heures
        /**
         * @var string $nom
         * @var string $prenom
         * @var string $sexe
         * @var string $courriel
         * @var string $cell
         * @var int $id_match
         * @var string $no_chandail
         * @var string $date_naissance
         */
        extract(self::check_params(
            'nom;string;min:2;max:50;sql',
            'prenom;string;min:2;max:50;sql',
            'sexe;regex:#^(M|F)$#i;sql',
            'courriel;courriel;opt;sql',
            'cell;tel;opt;sql',
            'id_match;unsigned',
            'no_chandail;unsigned;max:999;opt',
            'date_naissance;date;opt;sql'
        ));
        /**
         * @var $ok int
         * @var $locked int
         * @var $a_replanifier int
         * @var $id_equipe1 int
         * @var $id_equipe2 int
         * @var $division int
         */
        $res = db::query("
            SELECT IF(TIME_TO_SEC(TIMEDIFF(NOW(), CONCAT(sm.date, ' ', sm.debut)))/3600 BETWEEN -0.5 and 24*70, 1, 0) ok,
            sm.locked,
            sm.a_replanifier,
            sm.id_equipe1,
            sm.id_equipe2,
            e.division
            from stats_matchs sm
            LEFT JOIN equipes e ON sm.id_equipe1 = e.id_equipe
            LOCK IN SHARE MODE
        ", 			'acces_table', '');
        extract($res->fetch_assoc());
        if ($res->num_rows == 0){
            $this->fin('match_introuvable');
        }
        if (!$id_equipe1 or !$id_equipe2){
            $this->fin('match_doit_avoir_2_eq');
        }
        if ($a_replanifier){
            $this->fin('match_a_replanifier');
        }
        if (!$division){
            $this->fin('ne_peut_identifier_division');
        }

        $cree_par_marqueur = 0;

        if (!perm::test('admin')){
            if (!perm::marqueur_match($id_match)){
                $this->non_autorise();
            }

            if ($locked){
                $this->fin('verrouille');
            }
            if (!$ok){
                $this->fin('hors_delai_edition');
            }
            $cree_par_marqueur = 1;
        }

        $saison = saisons::courante();

        $res = db::query("
            SELECT *
            FROM membres m
            WHERE m.nom = $nom and m.prenom = $prenom
            FOR UPDATE
		", 			'acces_table', '');
        if($res->num_rows){
            $this->fin('nom_prenom_pris');
        }
        $assign = [
            'nom' => $nom,
            'prenom' => $prenom,
            'sexe' => strtoupper($sexe),
            'cree_par_id' => session::get('id_visiteur'),
            'cree_par' => db::sql_str(session::get('nom_visiteur')),
            'cree_par_marqueur' => $cree_par_marqueur,
            'cree_pour_match' => $id_match
        ];
        if (isset($courriel) and $courriel){
            $assign['courriel'] = $courriel;
        }
        if (isset($cell) and $cell){
            $assign['cell'] = $cell;
        }
        if (isset($date_naissance) and $date_naissance) {
            $assign['date_naissance'] = $date_naissance;
        }

        $assignment = [];
        foreach($assign as $fld => $val){
            $assignment[] = "$fld = $val";
        }
        $assignment = implode(',', $assignment);

        $res = db::query("
            INSERT INTO membres
            SET $assignment
		", 			'acces_table', '');
        self::$data['id_membre'] = $id_membre = db::get('insert_id');

        $assign_chandail = '';
        if ($no_chandail){
            $assign_chandail = ", no_chandail = $no_chandail";
        }
        $res = db::query("
            INSERT INTO dossier_joueur
            SET
                id_joueur = $id_membre,
                id_division = $division,
                substitut = 1,
                saison = $saison
                $assign_chandail
		", 			'acces_table', '');


        $this->succes();
    }

}
