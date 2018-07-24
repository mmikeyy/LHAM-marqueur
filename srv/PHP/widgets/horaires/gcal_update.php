<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of gcal_update
 *
 * @author michel
 */
class gcal_update {
	protected $gcal_client;
	public $result_msg = '';
	private $separateur = '';
	static $batch_size = 100;
	private $update_list;
	private $nb_entries;
	private $feed;
	private $finished = false;
	
	const batch_feed_tag = "<feed xmlns='http://www.w3.org/2005/Atom'
      xmlns:app='http://www.w3.org/2007/app'
      xmlns:batch='http://schemas.google.com/gdata/batch'
      xmlns:gCal='http://schemas.google.com/gCal/2005'
      xmlns:gd='http://schemas.google.com/g/2005'
	  xmlns:georss='http://www.georss.org/georss'
	  xmlns:gml='http://www.opengis.net/gml'
	  >";
	
	const entry_feed = "http://www.google.com/calendar/feeds/default/private/full/";
	
	private function test($res, $msg){
		if (!$res){
			throw new Exception($msg);
		}
	}
	/*
	 * appeler fonction update pour mettre à jour toutes les entrées de calendrier déjà marquées comme ayant changé
	 * et pour effacer celles qui ont été mise dans table "gcal_effacement".
	 * 
	 * la méthode établit la liste des mises à jour;
	 * et s'il y a des mises à jour, se connecte au calendrier;
	 * et soumet les modifications à fonction prepare_feed
	 * qui les traite par lots successifs de self::$batch_size entrées et forme un 
	 * 'feed' à soumettre à Google Calendar
	 * 
	 * La méthode prepare_feed met $this->finished à true quand tout a été traité.
	 * 
	 * Les tables sont mises à jour en fonction de la réponse de gcal.
	 */
	 function update(){
		
		$this->get_update_list();
		if ($this->update_list->num_rows == 0){
			$this->report('Calendriers à jour.');
			return;
		}
		
		
		
		$this->report("{$this->update_list->num_rows} changements à apporter au calendrier");
		$this->test($this->authenticate_gcal(), 
				'échec d\'authentification pour calendrier google'
				);
		
		$gdataCal = new Zend_Gdata_Calendar($this->gcal_client); 
		$no = 0;
		do{
			$no++;
			debug_print_once("preparing feed $no");
			$this->prepare_feed();
			$result = $gdataCal->POST($this->feed, 'http://www.google.com/calendar/feeds/default/private/full/batch');
			$this->process_result($result);
		} while (!$this->finished);
		//*****************************************************************************************
		//debug_print_once("----------->>>>");
		
	 }	
	 
	 function effacer_participants(){
		 $this->get_update_list_effacement_participants();
		 if ($this->update_list->num_rows == 0){
			 $this->report('aucun événement');
			 return;
		 }
		 $this->result_msg .= "{$this->update_list->num_rows} événements avec participants à effacer";
		$this->test($this->authenticate_gcal(), 
				'échec d\'authentification pour calendrier google'
				);
		
		$gdataCal = new Zend_Gdata_Calendar($this->gcal_client); 
		do{
			$this->prepare_feed($mode = 'efface_participants');
			$result = $gdataCal->POST($this->feed, 'http://www.google.com/calendar/feeds/default/private/full/batch');
			$this->process_result($result);
		} while (!$this->finished);
		//*****************************************************************************************
		//debug_print_once("----------->>>>");
		 
	 }
		
	/*
	 * 
	 */	
		
	function process_result($result){
		
		global $mysqli;
		// maintenant parcourir le xml reçu en réponse pour mettre db à jour selon les changements faits au calendrier
		
		
		//debug_print_once( 'Résultat ============= ' . $result);
		
		$xml = Zend_Http_Response::extractBody($result);
		
		$xmlDoc = DOMDocument::loadXML($xml);
		
		$entries = $xmlDoc->getElementsByTagName ('entry');
		$rang = 0;
		$this->create_temp_table();
		$update_data = array();
		$flds = array('operation','ref','gcal_event','status_code', 'deleted');
		foreach($entries as $entry){
			++$rang;
			$entry = new entry($entry, $this);
			$operation = $entry->get_operation();
			if (!preg_match('#^(insert|update|delete)$#', $operation)){
				$this->report('Opération invalide = ' . $operation);
				continue;
			}
			$update_data[] = $entry->get_fields($flds);
		}
		//debug_print_once("UPDATE DATA ");
		//debug_print_once(print_r($update_data, true));
		$update_data = implode(',', $update_data);
		$field_list = implode(',', $flds);
		$this->test($mysqli->query(debug_print_once("

			INSERT INTO update_status
			($field_list)
			VALUES
			$update_data

		")), 'Erreur peuplement table temporaire update_data');
		
		/*
		 *	200 OK			No error.
			201 CREATED		Creation of a resource was successful.
			304 NOT MODIFIED	The resource hasn't changed since the time specified in the request's If-Modified-Since header.
			400 BAD REQUEST	Invalid request URI or header, or unsupported nonstandard parameter.
			401 UNAUTHORIZED	Authorization required.
			403 FORBIDDEN	Unsupported standard parameter, or authentication or authorization failed.
			404 NOT FOUND	Resource (such as a feed or entry) not found.
			409 CONFLICT	Specified version number doesn't match resource's latest version number.
			410 GONE		Requested change history is no longer available on the server. Refer to service-specific documentation for more details.
			500 INTERNAL SERVER ERROR	Internal error. This is the default code that is used for all unrecognized server errors.
		 * http://code.google.com/apis/gdata/docs/2.0/reference.html#HTTPStatusCodes
		 * 
		 * */
		// mettre à 0 bit de mise à jour de calendrier et à null le id d'entrée dans EHL pour les entrées effacées du calendrier
		// enregistrer changement ssi resultat = OK ou si entrée déjà effacée (non trouvée)
		$this->test($res = db::query(("

				UPDATE ehl e
				JOIN update_status us USING(gcal_event)
				SET 
					e.gcal_event =			null,
					e.gcal_a_sauvegarder =	0,
					e.gcal_status =			us.status_code,
					e.gcal_update_date =	now()
					
				WHERE us.operation = 'delete'
					AND us.status_code in (200,404)

			")),
				'prise en compte d\'entrées de calendrier effacées'
				);
		$this->report(db::get('affected_rows') . ' entrées calendrier retirées');
		
		// noter mise à jour des entrées inscrites au calendrier sans erreur
		$this->test($res = db::query("

			UPDATE ehl e
			JOIN update_status us ON us.ref = e.id_match
			SET
				e.gcal_event =			us.gcal_event,
				e.gcal_status =			us.status_code,
				e.gcal_a_sauvegarder =	0,
				e.gcal_update_date =	now()
				
			WHERE
			us.operation in ('update', 'insert')
			AND us.status_code < 400


		"), 
			'mise à jour de ehl pour update et insert'
			);
		$this->report(db::get('affected_rows') . ' entrées calendrier mises à jour');
		
		// ménage au cas où des entrées de calendrier incrites dans EHL seraient introuvables dans le calendrier...
		// en effaçant le id de l'entrée dans ehl, on s'assure qu'à la prochaine mise à jour, une nouvelle entrée
		// sera créée dans le calendrier pour éviter de tenter à nouveau sans succès d'éditer une entrée introuvable
		$this->test(
			$res = db::query("

				UPDATE ehl e
				JOIN update_status us ON us.ref = e.id_match
				SET 
					e.gcal_event =		null,
					e.gcal_status =		us.status_code,
					e.gcal_update_date = now()
					
				WHERE us.status_code = 404

			"), 'effacement id d\'entrées ehl introuvables dans calendrier'
						);
		if (db::get('affected_rows')){
			$this->report(db::get('affected_rows') . ' entrées ehl introuvables dans calendrier');
		}
		
		$this->test(
				$res = db::query("
					DELETE ge 
					FROM gcal_effacement ge
					JOIN update_status us USING(gcal_event)
					WHERE us.deleted
				"),
				'Erreur mise à jour liste d\'effacement'
				);
		
	}
	
	/*
	 * préalable: la liste des mises à jour a été stockée dans $this->update_list (résultat de $mysqli->query);
	 * 
	 * Note: le pointeur interne de $this->update_list peut être ailleur qu'au début si on prépare un 'feed' pour
	 * un lot qui n'est pas le premier.
	 * 
	 * met nombre d'entrées dans $this->nb_entries
	 * met le feed en xml dans $this->feed
	 * retourne: rien;
	 * 
	 */
	
	function prepare_feed($mode = ''){
		$nb_entries = 0;

		$entries = self::batch_feed_tag;
		
		while ($row = $this->update_list->fetch_assoc()){
			//debug_print_once('ROW = ' . print_r($row, true));
			$entry = new gcal_entry($row, $mode);
			$entries .= $entry->xml_entry();
			//debug_print_once(print_r($entry, true));
			//debug_print_once(' ENTRY = ' . $entry->xml_entry());
			if (++$nb_entries > self::$batch_size){
				break;
			}
		}
		if (!$row){
			$this->finished = true;
		}
		$entries .= '</feed>';
		$this->nb_entries = $nb_entries;
		$this->feed = $entries;
	}
	
	function get_update_list_effacement_participants(){
		$tz = cfg_yml::get('calendrier_matchs', 'timezone');
		$duree_match = cfg_yml::get('calendrier_matchs', 'duree_match');
		if (!is_numeric($duree_match)){
			$duree_match = 60;
		} else if ($duree_match > 120){
			$duree_match = 120;
		} else if ($duree_match < 30){
			$duree_match = 30;
		}
		// établir liste des données à mettre dans calendrier (et des entrées à effacer)
		$this->test($this->update_list = db::query(("

			SELECT  ifnull(abrev_gr.abrev,e.groupe) groupe,
					concat(e.date, 'T', e.debut, '$tz') debut,
					concat(date(@a := date_add(addtime(e.date, e.debut), interval $duree_match minute)), 'T', time(@a), '$tz') fin,	
					ifnull(abrev1.abrev, equipe1) equipe1, 
					ifnull(courriel1.adresse, '') courriel1,
					ifnull(abrev2.abrev, equipe2) equipe2,
					ifnull(courriel2.adresse, '') courriel2,
					ifnull(courriel_groupe.adresse, '') courriel_groupe,
					e.gcal_event,
					e.id_match ref,
					e.ref no_match,
					ifnull(lieux.lieu_propre, e.lieu) lieu,
					ifnull(lieux.adresse, e.lieu) adresse,
					e.a_replanifier,
					lieux.latitude,
					lieux.longitude,
					lieux.tel
			FROM ehl e
			LEFT JOIN gcal_abreviations abrev1 ON e.equipe1 = abrev1.nom
			LEFT JOIN gcal_abreviations abrev2 ON e.equipe2 = abrev2.nom
			LEFT JOIN gcal_abrev_groupes abrev_gr ON e.groupe = abrev_gr.abrev
			LEFT JOIN widget_calendars courriel1 ON e.groupe = courriel1.groupe AND e.equipe1 = courriel1.nom_equipe
			LEFT JOIN widget_calendars courriel2 ON e.groupe = courriel2.groupe AND e.equipe2 = courriel2.nom_equipe
			LEFT JOIN widget_calendars courriel_groupe ON e.groupe = courriel_groupe.groupe AND courriel_groupe.nom_equipe is null
			LEFT JOIN gcal_lieux lieux ON e.lieu = lieux.lieu_original
			WHERE e.gcal_event
			
				

		")),
				'ERREUR: établissement liste de changements à calendrier'
				);
		
			
	}
	
	
	/*
	 * appeler au début pour établir liste des mises à jour à effectuer.
	 * met liste (résultat de requête) dans $this->update_list
	 */
	
	function get_update_list(){
		
		$tz = cfg_yml::get('calendrier_matchs', 'timezone');
		
		// établir liste des données à mettre dans calendrier (et des entrées à effacer)
		$this->test($this->update_list = db::query(("

			SELECT  ifnull(abrev_gr.abrev,e.groupe) groupe,
					concat(e.date, 'T', e.debut, '$tz') debut,
					concat(date(@a := date_add(addtime(e.date, e.debut), interval 60 minute)), 'T', time(@a), '$tz') fin,	
					ifnull(abrev1.abrev, equipe1) equipe1, 
					ifnull(courriel1.adresse, '') courriel1,
					ifnull(abrev2.abrev, equipe2) equipe2,
					ifnull(courriel2.adresse, '') courriel2,
					ifnull(courriel_groupe.adresse, '') courriel_groupe,
					e.gcal_event,
					e.id_match ref,
					e.ref no_match,
					ifnull(lieux.lieu_propre, e.lieu) lieu,
					ifnull(lieux.adresse, e.lieu) adresse,
					e.a_replanifier,
					lieux.latitude,
					lieux.longitude,
					lieux.tel
			FROM ehl e
			LEFT JOIN gcal_abreviations abrev1 ON e.equipe1 = abrev1.nom
			LEFT JOIN gcal_abreviations abrev2 ON e.equipe2 = abrev2.nom
			LEFT JOIN gcal_abrev_groupes abrev_gr ON e.groupe = abrev_gr.abrev
			LEFT JOIN widget_calendars courriel1 ON e.groupe = courriel1.groupe AND e.equipe1 = courriel1.nom_equipe AND courriel1.importer_auto
			LEFT JOIN widget_calendars courriel2 ON e.groupe = courriel2.groupe AND e.equipe2 = courriel2.nom_equipe AND courriel2.importer_auto
			LEFT JOIN widget_calendars courriel_groupe ON e.groupe = courriel_groupe.groupe AND courriel_groupe.nom_equipe is null 
			LEFT JOIN gcal_lieux lieux ON e.lieu = lieux.lieu_original
			WHERE e.gcal_a_sauvegarder
			UNION
				SELECT null groupe,
					null debut,
					null fin,
					null equipe1,
					null courriel1,
					null equipe2,
					null courriel2,
					null courriel_groupe,
				
					gcal_event,
				
					null ref,
					null no_match,
					null lieu,
					null adresse,
					null a_replanifier,
					null latitude,
					null longitude,
					null tel
				FROM gcal_effacement
				WHERE 1
				ORDER BY debut
				

		")),
				'ERREUR: établissement liste de changements à calendrier'
				);
		
	}
	function create_temp_table(){
		
		$res = db::query("
			CREATE TEMPORARY TABLE IF NOT EXISTS update_status
						(	
							operation SET('update', 'insert', 'delete'),
							ref bigint NULL,
							gcal_event varchar(250) NULL,
							status_code int,
							deleted tinyint,
							index (ref),
							index (gcal_event),
							index (operation)
					
						)
					ENGINE=MEMORY

		");
		db::query('truncate update_status');
	}
	
	function report($msg){
		$this->result_msg .= $this->separateur . $msg;
		$this->separateur = '; ';
	}
	
	function authenticate_gcal(){
		if (is_object($this->gcal_client)){
			return true;
		}
		$courriel = cfg_yml::get('calendrier_matchs', 'courriel');
		$mdp = cfg_yml::get('calendrier_matchs', 'mdp');
		debug_print_once("Tentative d'authentification avec $courriel et $mdp");
		try{
			$this->gcal_client = Zend_Gdata_ClientLogin::getHttpClient($courriel, $mdp, Zend_Gdata_Calendar::AUTH_SERVICE_NAME);
		} catch(Zend_Gdata_App_Exception  $e){
			debug_print_once("échec d'authentification");
			return false;
		}
		return (is_object($this->gcal_client));
	}
}


// classe 'read only' pour lecture d'entry extraite de la réponse HTTP reçue après mise à jour de calendrier
class entry{
	public $node;
	public $parent_obj;
	protected $extended_properties;
	protected $status_code;
	protected $operation;
	protected $id;
	protected $id_determined;
	protected $reason = '';
	const entry_feed = "http://www.google.com/calendar/feeds/default/private/full/";
	
	function __construct($node, $parent_obj){
		$this->node = $node;
		$this->parent_obj = $parent_obj;
	}
	function report($msg){
		$this->parent_obj->report($msg);
	}
	function get_gcal_event(){
		return $this->get_id();
	}
	function get_id(){
		if ($this->id_determined){
			return $this->id;
		}
		//debug_print_once("======================eille caaaaaaaaaaaaaaalisse==========================================");
		$id = $this->node->getElementsByTagNameNS ('http://www.w3.org/2005/Atom', 'id');
			
		if ($id->length == 0){
			return $this->set_id(null);
			$this->report("entrée sans id");
			log_event('google_cal', 'update', 'id manque: ' . print_r($this->node, true));
		} 
		$id = $id->item(0)->nodeValue;
		$nb= preg_match('#' . self::entry_feed .  '([^/]+)#', $id, $a);
		//debug_print_once("id = $id");
		//debug_print_once("resultat de recherche d'id = " . print_r($a, true));
		$id = $a[1];
		return $this->set_id($id);
	}
	function set_id($id){
		$this->id_determined = true;
		$this->id = $id;
		return $id;
	}
	function get_extended_properties(){
		if (is_array($this->extended_properties)){
			return $this->extended_properties;
		}
		$ep = $this->node->getElementsByTagNameNS ('http://schemas.google.com/g/2005', 'extendedProperty');
		$this->extended_properties = array();
		foreach($ep as $prop){
			$this->extended_properties[$prop->getAttribute('name')] = $prop->getAttribute('value');
		}
		return $this->extended_properties;
	}
	function get_ref(){
		$p = $this->get_extended_properties();
		if (!isset($p['ref'])){
			return null;
		}
		return $p['ref'];
	}
	function get_status_code(){
		if (is_numeric($this->status_code)){
			return $this->status_code;
		}
		$status_code = $this->node->getElementsByTagnameNS('http://schemas.google.com/gdata/batch', 'status');
		if ($status_code->length == 0){
			$this->status_code = 0;
			return 0;
		}
		$status_code = $status_code->item(0);
		$this->reason = $status_code->getAttribute('reason');
		$status_code = $status_code->getAttribute('code');
		
		$this->status_code = $status_code;
		return $status_code;
	}
	function get_reason(){
		$this->get_status_code();
		return $this->reason;
	}
	function get_operation(){
		if (is_numeric($this->operation)){
			return $this->operation;
		}
		$operation = $this->node->getElementsByTagnameNS('http://schemas.google.com/gdata/batch', 'operation');
		$operation = $operation->item(0);
		$operation = $operation->getAttribute('type');
		$this->operation = $operation;
		return $operation;
	}
	function get_deleted(){
		$status = $this->get_status_code();
		
		if ($this->get_operation() != 'delete'){
			return 0;
		}
		if ($status == 200 or $status == 404){
			return 1;
		}
		if ($status== 403 and $this->get_reason() == 'Cannot delete a cancelled event'){
			return 1;
		}
		return 0;
	}
	function get_field($field){
		$fn = "get_$field";
		return $this->$fn();
	}
	function get_fields($fields){
		foreach($fields as $field){
			$valeurs[] = db::sql_str($this->get_field($field));
		}
		return '(' . implode(',', $valeurs) . ')';
	}
}
?>
