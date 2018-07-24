<?php

//debug_print_once("loading class horaires_importation"); 
class horaires_importation {
	private $debug_allowed = false;
	
	protected $detail = '';
	protected $h;
	protected $err_msg;
	public $signature_version;
	public $log_data = '';
	public $log;
	public $log_prepend = '';
	static $config_horaires;
	
	// true si les données fournies sont des données provenant d'une source pour plusieurs groupes.
	// dans ce cas, on fera la maj de ehl selon le groupe et le no de match (ref) seulement
	protected $resultats_multi_groupes = false;

	function __construct(){
		

		
		$this->h = new http();
		
	
		
		
		$this->query_err("

			CREATE TEMPORARY TABLE IF NOT EXISTS match_data
						(	
							date date,
							debut time,
							equipe1 varchar(35),
							equipe2 varchar(35),
							lieu varchar(40),
							id_lieu_source smallint unsigned DEFAULT NULL,
							groupe varchar(30),
							ref bigint unsigned,
							display_ref bigint unsigned,
							pts1 tinyint,
							pts2 tinyint,
							fj1 tinyint,
							fj2 tinyint,
							marque tinyint,
							a_replanifier tinyint,
							`div` int unsigned,
							cl int unsigned,
							eq1 int unsigned,
							eq2 int unsigned,

					INDEX (ref),
					INDEX (date)
						)
					ENGINE=MEMORY

		",
				'erreur création table temporaire');
		
		$res = db::query("

		create table IF NOT EXISTS test_match like match_data

	");
		
	}
	function set_log($log, $prepend = ''){
		$this->log = $log;
		$this->log_prepend = $prepend;
	}
	function log($msg, $separ = '; '){
		if ($this->log){
			$this->log->log($this->log_prepend . $msg);
		} 
		$this->log_data .= $separ . $msg;
		
	}
	function __get($name){
		if ($name == 'affected_rows'){
			return db::get('affected_rows');
		}
		
	}
	function dquery_err($query, $msg, $detail = '', $debug = true){
		return $this->query_err($query, $msg, $detail, $this->debug_allowed and $debug);
	}
	function query_err($query, $msg, $detail = '', $debug = false){
		if ($debug){
			$res = db::dquery($query);
		} else {
			$res = db::query($query);
		}
		
		if (!$res){
			$this->err_msg = $msg;
			$this->detail = $detail;
			$this->log("Erreur: ".$this->err_msg . (($this->err_detail . d())?"\nDetail: ". $this->err_detail . d():''));
			throw new Exception ($msg);
			
		}
		return $res;
	}
	function err($res, $msg, $detail=''){
		if (!$res){
			$this->err_msg = $msg;
			$this->detail = $detail;
			$this->log("Erreur: ".$this->err_msg . (($this->err_detail . d())?"\nDetail: ". $this->err_detail . d():''));
			throw new Exception ($msg);
		}
	}
	

	function importer_ehl($fields, $data, $params = ''){
  
		$source = db::sql_str($this->source);
		$source_groupe = ' source ' . $this->source . '; groupe ' . $this->cfg->groupe;
		
		
		 if(preg_match('#\bvider_horaire_futur\b#', $params)){
			 
			 if (!($groupe = $this->cfg->groupe)){
				 $this->log('Groupe non spécifié');
				 return false;
			 }
			 db::sql_str_($groupe);
			 $today = date::today();

             /**
             * @var integer $nb
             */
			 // n'accepter de vider que si 2 matchs ou moins restent à jouer
			 $res = db::query("
				 SELECT count(*) nb
				 FROM ehl
				 WHERE groupe = $groupe
					 AND source = $source
					 AND date >= '$today'
			",
				'acces_table','décompte matchs à effacer');
			 extract($res->fetch_assoc());
			 
			 if ($nb > 2){
				 $this->log("Tentative d\'effacement de $nb matchs avortée (source $this->source ({$this->cfg->groupe}) ");
				 return false;
			 }
			 
			 db::query("
				 INSERT INTO gcal_effacement
				 (gcal_event)
				 SELECT gcal_event
				 FROM ehl
				 WHERE groupe = $groupe
					 AND source = $source
					 AND date >= '$today'
					
			",
				'acces_table','insertion evenements calendrier a effacer' . $source_groupe);
			 if (db::get('affected_rows')){
				$this->log('Effacement de ' . db::get('affected_rows') . ' (tous) événements de calendrier futurs' . $source_groupe);
			 }
			 
			 db::query("
				 DELETE FROM ehl
				 WHERE groupe = $groupe
					 AND source = $source
					 AND date >= '$today'
			",
				'acces_table','effacement événements futurs' . $source_groupe);
			 
			 $this->log('Effacement de ' . db::get('affected_rows') . ' (tous) événements futurs de table EHL' . $source_groupe);
			 return true;
		 }
		
		
		
		// mode horaire remplace les matchs futurs avec le contenu 
		$mode_horaire = preg_match('#horaire#', $params);
		

		if (!is_array($fields) or count($fields) == 0){
			$this->log('aucune donnée');
			return true;
		}
		
		/*
		 * $fields: array de champs incluant source, groupe, ref
		 * $data: données dans même ordre que les champs
		 */
		//debug_print_once("fields = " . print_r($fields, true));
		//debug_print_once("data = " . print_r($data, true));

		
		
		//debug_print_once('Fields = ' . print_r($fields,true));
		//debug_print_once("Data = " . print_r($data,true));
		
		
		$data_field_list = array_diff($fields, array('groupe', 'source'));
        $assignment = [];

		foreach($data_field_list as $field){
			if ($field == 'ref') continue;
			if (in_array($field, array('date', 'debut'))){
				$assignment[] = "e.$field = if(m.a_replanifier, e.$field, m.$field)";
			} else{
				$assignment[] = "e.$field = m.$field\n";
			}
		}

		$assignment = implode(',', $assignment);
		$liste_fields = implode("\n,", $this->ticked($fields));
		$data_field_list = implode("\n,", $this->ticked($data_field_list));


		$this->query_err('

			truncate match_data

			',
				'erreur (truncate match_data)'
				);

		// importer données dans table temporaire
		//debug_print_once(print_r($data,true));
		$this->dquery_err("

			INSERT INTO match_data
			($liste_fields)
			VALUES
				$data

			",
				'insertion dans match_data'
				);
		$affected = db::get('affected_rows');
		$this->log("Vérifié $affected lignes; ");

		$debut_saison = db::sql_str(saisons::get_fld('debut'));

		$this->dquery_err("
			DELETE FROM match_data
			WHERE date < $debut_saison AND NOT a_replanifier
		", 
				'effacement matchs avant début saison');
		$affected = db::get('affected_rows');
		if ($affected){
			$this->log("Effacé $affected lignes datant d'avant le début de la saison ($debut_saison).");
		}
        
        /*

		db::query('

		truncate test_match

		');
		db::query('

		insert into test_match select * from match_data where 1

	');
         * 
         */
		// verrouiller ehl pour source et groupe
		//debug_print_once("Source = $source; groupe = $groupe");
		//debug_print_once(print_r($liste_fields, 1));
		//debug_print_once(print_r($data, 1));

		if ($this->resultats_multi_groupes){
			// si seulement mise à jour de résultats de matchs, alors pas besoin de mettre à jour calendriers google...
			$this->dquery_err("

				UPDATE ehl e
				JOIN match_data m USING(ref)
				SET 
					$assignment
				WHERE e.source = $source

				",
					'erreur update lignes ehl multi-groupes'
					);
			return true;
		}

		
		// 
		// si on se rend ici, il doit y avoir un groupe défini pour les données
		//
		$groupe = db::sql_str($this->cfg->groupe);
		if (empty($groupe)){
			$this->log('Opération avortée: tentative de traitement de données sans groupe!');
			return null;
		}

		// si horaires inclus pour le futur, composer les clauses permettant de déterminer
		// si les calendriers google devront être mis à jour
		$date_limite_calendrier = new DateTime();
		
		// ATTENTION dans cfg, mettre jours_futur = nombre de jours futurs à mettre dans calendrier;
		// 
		// sinon omettre cette valeur ou la régler à une valeur non numérique  ou non entière pour
		// régler les calendriers jusqu'à la date de la fin de la saison
		
		$jours_futur = cfg_yml::get('calendrier_matchs', 'jours_futur');
		if (!is_string($jours_futur) or !preg_match('#^\d+$#', $jours_futur)){
			$date_limite_calendrier = saisons::get_fld('fin');
		} else {
			$date_limite_calendrier->modify('+' . cfg_yml::get('calendrier_matchs', 'jours_futur') . ' days');
			$date_limite_calendrier = $date_limite_calendrier->format('Y-m-d');
		}
		
		
		$identification_nom = db::sql_str('%' . cfg_yml::get('noms_equipes', 'mot_clef_recherche') . '%');
		
		$changement = array();
		foreach(array('date', 'debut', 'lieu', 'equipe1', 'equipe2', 'a_replanifier') as $field){
			if (in_array($field, $fields)){
				$changement[] = "ifnull(e.$field,'')<>ifnull(m.$field,'')";
			}
		}
		$changement = implode(' OR ', $changement);
		$update_gcal = "
			e.gcal_a_sauvegarder =  
			e.date BETWEEN curdate() AND '$date_limite_calendrier'
			AND 
			(
				ifnull(m.equipe1,'') LIKE $identification_nom 
				OR
				ifnull(m.equipe2, '') LIKE $identification_nom
			)
			AND
			(
				e.gcal_a_sauvegarder 
				OR
				gcal_event IS NULL 
				OR
				$changement
			)
		";
					

		



		// verrouiller matchs de source/groupe qui sont susceptibles de changer
		$this->dquery_err("

			SELECT id_match
			FROM ehl
			WHERE source=$source AND groupe = $groupe
			FOR UPDATE

			",
				'verrouillage ehl'
				);


		
		if ($mode_horaire){
			// effacer de ehl lignes absentes de match_data après aujourd'hui
			
			// d'abord retenir la liste des entrées de calendrier qui devront être effacées
			// comprenant entrées de chaque équipe + entrées du groupe
			// inclure les matchs pour lesquels les équipes ont changé (ne devrait pas se produire...)
			// logique:
			// 1) on retient toutes les entrées calendrier des enregistrements qui vont être effacés de ehl
			//		c-a-d ceux pour lesquels m.ref est null
			// 2) on vérifie pour chaque match si equipe1 ou equipe2 a changé
			//		si oui, on note le id_match du match à la position de l'équipe (cet enreg. ne sera pas effacé)
			//		en procédant ainsi, on peut facilement effacer les entrées calendrier plus tard
			// à noter que les changements d'équipe n'affectent pas les entrées calendrier du groupe
			$date_effacement = new DateTime();
			$date_effacement->modify('-' . cfg_yml::get('calendrier_matchs', 'jours_passe') . ' days');
			$date_effacement = $date_effacement->format('Y-m-d');
			
			// marquer comme étant à sauvegarder les matchs qui ne sont pas au calendrier
            /* NOOOONNNN!! ne pas faire ça. Ca force l'inclusion dans les calendriers des matchs de toutes les équipes, même ceux
             * n'impliquant aucune équipe de la ligue
             * 
			$this->dquery_err("
				UPDATE ehl
				SET gcal_a_sauvegarder = 1
				WHERE gcal_event IS NULL
					AND date >= '$date_effacement'
					AND date <= '$date_limite_calendrier'
				",
					'marquer à sauvegarder dans intervalle de dates');
			*/
            // ajouter à liste d'effacement d'événements gcal les gcal des matchs qui vont disparaître du calendrier
            // PLUS les matchs trop vieux ou trop loin dans le futur
			$this->dquery_err("

				INSERT IGNORE INTO gcal_effacement
				(gcal_event)
				
				SELECT e.gcal_event
				FROM ehl e
				LEFT JOIN match_data m USING(ref)
				WHERE 
					e.gcal_event is not null 
					AND 
					( e.date > NOW() 
					AND e.source = $source 
					AND e.groupe = $groupe
					AND m.ref is null
					OR
					e.date < '$date_effacement'
					OR e.date > '$date_limite_calendrier'
					)
					

				",
					'retenir entrées calendrier google à retirer'
					);
			
			// procéder à l'effacement des enregistrements futurs retirés de l'horaire
			$this->dquery_err("

				DELETE e
				FROM ehl e
				LEFT JOIN match_data m USING(ref)
				WHERE e.date > now() and e.source = $source and e.groupe = $groupe and m.ref is null

				",
					'erreur effacement lignes ehl'
					);
			
			$this->log("$this->affected_rows effacées car retirées de l'horaire; ");
			
			
		}
		
		// mettre à jour dans ehl les données des matchs qui sont potentiellement modifiées dans matchdata
		// noter nécessité de mise à jour du calendrier si certains champs ont changé (voir $update_gcal)
		// marquer dans matchdata les données ainsi traitées pour pouvoir ensuite les éliminer de matchdata
		// de façon à n'avoir ensuite que les nouvelles données
		
		$this->dquery_err("

			UPDATE ehl e
			JOIN match_data m USING(ref)
			LEFT JOIN widget_calendars adr1 ON e.groupe = adr1.groupe AND e.equipe1 = adr1.nom_equipe
			LEFT JOIN widget_calendars adr2 ON e.groupe = adr2.groupe AND e.equipe2 = adr2.nom_equipe
			SET 
				$update_gcal,
				$assignment,
				m.marque = 1
			WHERE e.source = $source AND e.groupe = $groupe

			",
				'erreur update lignes ehl'
				);
                
		// mettre à jour les matchs présents dans ehl et dans match_data
		// ne mettre à jour que les champs envoyés

		// effacer les données de match_data qu'on vient de traiter
		$this->dquery_err("

			DELETE 
			FROM match_data
			WHERE marque

			",
				'effacement données transférées'
				);
		$this->log("$this->affected_rows matchs existants mis à jour si changés; ");

		// mise à jour ehl pour les matchs futurs, avec indicateur de mise au calendrier seulement lorsque pas trop lointains
		$this->dquery_err("

			INSERT IGNORE INTO ehl
			(source, groupe, $data_field_list, gcal_a_sauvegarder)
			SELECT $source, $groupe,$data_field_list, if(date <= '$date_limite_calendrier' AND  concat(ifnull(equipe1,''),ifnull(equipe2,'')) LIKE $identification_nom, 1, 0) FROM match_data WHERE 1

			",
				'insertion / remplacement ehl matchs à venir'
				);
		$this->log("$this->affected_rows nouvelles lignes; ");
		
		// ajouter à liste d'entrées de calendrier à effacer celles qui sont au-delà de la date limite dans le futur
		// ne sert que si la date a changé en étant devancée 
		$this->dquery_err("
				INSERT IGNORE INTO gcal_effacement
				(gcal_event)
				
				SELECT e.gcal_event
				FROM ehl e
				WHERE e.gcal_event is not null and date > '$date_limite_calendrier'
			
			",
				"liste d\'événements futurs à effacer du calendrier au-delà de $date_limite_calendrier; "
				);
		$this->dquery_err("
				UPDATE ehl
				SET gcal_a_sauvegarder = 0,
					gcal_event = null
				WHERE date > '$date_limite_calendrier'
			",
				"enlever indicateur de maj de matchs au-delà de date limite $date_limite_calendrier"
				);
        return true;

	}
	

	static function extract_data($data){
		if (is_string($data)){
			$data2 = unserialize($data);
			if ($data2 === false){
				return $data;
			} else{
				$data = $data2;
			}
		}
		if (is_array($data) and array_key_exists('data', $data)){
			return $data['data'];
		}
		return $data;
	}

	static function get_config_horaires($var, $section = 'general'){
		self::load_config_horaires();
		if (!self::$config_horaires or !isset(self::$config_horaires[$section][$var])){
			return null;
		}
		return self::$config_horaires[$section][$var];
	}
	static function set_config_horaires($var){
		self::$config_horaires = $var;
	}
	static function load_config_horaires(){
		if (!is_null(self::$config_horaires)){
			return;
		}
		self::$config_horaires = parse_ini_file(dirname('__FILE__'). '/config_horaires.ini', true);
	}
	
	
	static function fix_html($html){
		$html = iconv('UTF-8', 'UTF-8//IGNORE', $html);
		return preg_replace('#(<head[^>]*>)#m', '$1<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>', $html);
	}
	function create_dir($fname = null){
		if (is_null($fname)){
			$fname = $this->filename;
		}
		if (is_null($fname)){
			return false;
		}
		$path_info = pathinfo($fname);
		if (!file_exists($path_info['dirname'])){
			return mkdir($path_info['dirname'], 0755,true);
		}
		return true;
	}

	function ticked($list) {
        return array_map(function($f){return "`$f`";}, $list);
    }
}


?>
