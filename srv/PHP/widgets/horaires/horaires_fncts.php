<?php

/*dev_log
 * 23 fév changé periode de temps pour répartir taches proxy à 300 minutes.
 *
 *
 *
 *
 */


class horaires_fncts extends http_json
{

    protected $task_data = array(

        'publications_sportsV2' => array(
            'proxy' => false,
            'file' => 'publ_sportsV2.php',
            'class' => 'publ_sportsV2'
        ),
        'publications_sportsV2_saison' => array(
            'proxy' => false,
            'file' => 'publ_sportsV2_saison.php',
            'class' => 'publ_sportsV2_saison'
        )
    );

    static $msg = array();
    static $msg_loaded = false;

    public $auto_cfg;
    private $index_rand_proxy = 0;
    private $liste_proxies = array();
    private $url_proxies = array();
    public $extra_log_data = '';
    static $cfg;

    function __construct($no_op = false)
    {
        //debug_print_once("construct");
        parent::__construct();

        if (!$no_op) {
            self::execute_op();
        }

    }

    static function msg($ind, $lang = '', $obj = null)
    {
        if (!self::$msg_loaded) {
            self::$msg_loaded = true;
            /**
             * @var $msg_hor array
             */
            require_once lang::include_lang('horaires.php');
            self::$msg = $msg_hor;
        }
        if (array_key_exists($ind, self::$msg)) {
            return self::$msg[$ind];
        } else {
            return $ind;
        }
    }

    function fn_get_auto_config()
    {
        self::$data['cfg'] = $this->get_auto_cfg();
        $this->succes();

    }

    function fn_set_auto_config()
    {

        if (!perm::test('admin')) {
            $this->fin('niveau_insuffisant');
        }

        /**
         * @var array $config
         */
        extract(self::check_params(
            'config;json;decode_array'
        ));

        http_json::set_source_check_params_once($config);

        $params = self::check_params(
            'importer_auto;bool',
            'frequence;unsigned;min:1',
            'temps_max;unsigned;min:2',
            'debut;time',
            'fin;time',
            'courriel_err;courriel'
        );


        file_put_contents(__DIR__ . '/horaires_auto_config.json', json_encode($params));
        $this->succes();

    }

    function fn_get_list()
    {
        self::set_data('liste', $this->get_liste_horaires());
        self::set_data('types', array_keys($this->task_data));
        $this->succes();
    }

    function fn_change_status()
    {


        if (!perm::test('admin')) {
            $this->fin('niveau_insuffisant');
        }

        /**
         * @var integer $id
         * @var integer $desactiver
         */
        extract(self::check_params(
            'id;unsigned_list',
            'desactiver;bool;bool_to_num'
        ));


        $res = db::query("

			UPDATE importation_donnees
			SET inactif = $desactiver
			WHERE id in ($id)

		",
            'acces_table', 'update importation_donnees');

        $this->succes();
    }

    function fn_source_data()
    {
        //debug_print('Lit le contenu du fichier ' . urlencode($_GET['url']));
        $url = $_GET['url'];

        self::$data['contenu'] = @file_get_contents($url);
        $this->succes();
    }

    function fn_save_config()
    {//--------------------------------------------------------+++++++++++++++++++

        //require_once('../../include/connect.php');

        if (!perm::test('admin')) {
            $this->fin('niveau_insuffisant');
        }

        debug_print_once('request' . print_r($_REQUEST, 1));

        /**
         * @var integer $id
         * @var string $config
         * @var string $cfg
         * @var string $source
         * @var string $url
         * @var string $desc
         * @var integer $ordre
         * @var string $type
         */
        $vals = self::check_params(
            'id;unsigned',
            'source;string;min:1',
            'url;string;min:1',
            'description;string;min:1',
            'ordre;num',
            'type;regex:#^(SOL_matchs|SOL_resultats|lac_st_louis|texte|lehockey_ca_matchs|lehockey_ca_result|hca_matchs_XL|hca_classement|horaires(_multigroupes)?_nsh|results_multigroupes_nsh|publications_sports|publications_sportsV2|publications_sportsV2_saison|publications_sports_cal)$#',
            'config;json;opt'
        );

        $assign = db::make_assignment($vals, ['id']);
        $id = $vals['id'];


        $res = db::dquery("

			UPDATE importation_donnees
			SET $assign
			WHERE id = $id

			",
            'acces_table', 'update importation_donnees');



        $this->succes();

    }

    function fn_sauvegarde_nouveau()
    {//----------------------------------------------------++++++++++++++++
        //require_once('../../include/connect.php');

        if (!perm::test('admin')) {
            $this->fin('niveau_insuffisant');
        }
        $err_ident = 'sauvegarde_nouveau';

        /**
        * @var string $source
        * @var string $url
        * @var string $description
        * @var integer $ordre
        * @var string $type
        */
        $vals = self::check_params(
            'source;string;min:1',
            'url;string;min:1',
            'description;string;min:1',
            'ordre;num',
            'type;regex:#^(SOL_matchs|SOL_resultats|lac_st_louis|texte|lehockey_ca_matchs|lehockey_ca_result|hca_matchs_XL|hca_classement|horaires(_multigroupes)?_nsh|results_multigroupes_nsh|publications_sports|publications_sportsV2|publications_sportsV2_saison|publications_sports_cal)$#'
        );


        $sql_desc = db::sql_str($vals['description']);
        /**
        * @var integer $nb
        */
        $res = db::query("

			SELECT count(*) as nb
			FROM importation_donnees
			WHERE description = $sql_desc

			",
            'acces_table', "$err_ident (select count)");

        if ($res->num_rows != 1) {
            $this->fin('acces_table', "$err_ident (select count / num_rows <> 1)");
        }

        extract($res->fetch_assoc());
        if ($nb != 0) {
            $this->fin('existe_deja');
        }

        $assign = db::make_assignment($vals);

        db::query("

			INSERT INTO importation_donnees
			SET  $assign

			",
            'acces_table', "$err_ident (insert into...)");

        if (db::get('affected_rows') != 1) {
            $this->fin('no_insert');
        }

        self::set_data('id', db::get('insert_id'));

        $this->succes();

    }

    function fn_sample_text_data()
    {
        //require_once('../../include/connect.php');

        if (!perm::test('admin')) {
            $this->fin('niveau_insuffisant');
        }

        /**
        * @var array $config
        * @var string $source
        * @var string $url
        * @var string $desc
        * @var integer $ordre
        * @var string $type
        */

        // on n'a pas besoin de toutes ces données, mais on les exige quand même pour faire comme partout ailleurs
        extract($params = self::check_params(
            'config;json;decode_array',
            'source;string;min:1',
            'url;string;min:1',
            'desc;string;min:1',
            'ordre;num',
            'type;regex:#^(SOL_resultats|lac_st_louis|texte|lehockey_ca_matchs|lehockey_ca_result|SOL_matchs|horaires(_multigroupes)?_nsh|results_multigroupes_nsh|publications_sports|publications_sportsV2|publications_sportsV2_saison|publications_sports_cal)$#'
        ));

        if (!array_key_exists($type, $this->task_data)) {
            $this->fin('type inconnu', $type);
        }

        require_once($this->task_data[$type]['file']);

        $cl = $this->task_data[$type]['class'];
        $donnees = new $cl($params);

		$res = $donnees->import_sample();
		if ($donnees->result_code == 'ok') {
            self::$data['lignes'] = $res;
        } else {
            $this->fin($donnees->err_msg, $donnees->err_detail);
        }
		$this->succes();
	}

    function fn_importer()
    {
        $this->fn_importer_auto(false);
    }

    function fn_importer_auto($auto = true)
    {
        //require_once include_lang('horaires.php');
        //debug_print_once("importer auto");


        /**
        * @var string $mode
        */
        extract(self::check_params(
            'mode;regex:#^(json|text)$#;default:json'
        ));


        $config_auto = $this->get_auto_cfg(false);

        if ($auto) {
            $mode = 'text';
        }
        debug_print_once(print_r($config_auto, 1));

        if ($auto and !$config_auto->importer_auto) {
            debug_print_once('pas auto');
            $this->succes();
        }
        debug_print_once('mode auto');
        //debug_print_once("va traiter proxy");
        //$this->traiter_proxy();
        //debug_print_once("fini traiter proxy");
        //db::commit();
        //$this->succes();


        $log_data = '';

        if ($auto) {
            $log_data = "Importation auto";


            $minutes_debut = date_parse($config_auto->debut);
            $minutes_debut = $minutes_debut['hour'] * 60 + $minutes_debut['minute'];

            $minutes_fin = date_parse($config_auto->fin);
            $minutes_fin = $minutes_fin['hour'] * 60 + $minutes_fin['minute'];

            $minutes_actuelles = date('G') * 60 + date('i');


            if (!($minutes_debut < $minutes_fin and $minutes_actuelles >= $minutes_debut and $minutes_actuelles <= $minutes_fin
                OR
                $minutes_debut > $minutes_fin and ($minutes_actuelles < $minutes_fin or $minutes_actuelles > $minutes_debut)
            )
            ) {
                $heure_maintenant = date('H:i');
                $this->finir_($mode, "Pas de vérification à cette heure ($heure_maintenant compar = $minutes_actuelles vs [$minutes_debut -> $minutes_fin]). Prochaine à $config_auto->debut");
            }


            $arrete_apres = date('U') + $config_auto->temps_max;

            if (!is_numeric($config_auto->frequence)) {
                $config_auto->frequence = 60;
            }
            $where_clause = "WHERE ((@temp := timestampdiff(MINUTE, last_update, NOW())) > {$config_auto->frequence} OR @temp is null)";
        } else {

            $where_clause = 'WHERE id_importation in (select min(id_importation) from importation_donnees where not inactif)';
            $arrete_apres = date('U') + 25;
        }

        $log = new log_importation();
        $log->ouvrir_sinon_creer_cond('erreurs = -1');

        if ($log->id() == -1) {
            $log->set('no_essai', -1);
            $log->ecrire();
        } else {
            $log->increment_no_essai();
        }
        $log->enreg_debut();
        $id_importation = $log->id();

        /*
        // trouver le dernier enregistrement créé dans lequel il n'y a pas eu de résultats à mettre
        $res = db::query("

            SELECT id_importation from importation_donnees_date
            WHERE erreurs < 0
            FOR UPDATE

        ") or $this->finir_($mode,'acces_table', 'horaires.php / select where erreur < 0');

        if ($res->num_rows){
            extract($res->fetch_assoc());
            $res = db::query("

                UPDATE importation_donnees_date
                SET date_import = now(), no_essai=no_essai+1
                WHERE id_importation = $id_importation

            ") or $this->finir_($mode,'acces_table', 'update importation... no_essai=no_essai+1');
        } else {

            $res = db::query("

                INSERT INTO importation_donnees_date
                SET date_import = NOW(), erreurs = -1

                ") or $this->finir_($mode,'acces_table', 'insert into importation_donn...');

            $id_importation = db::get('insert_id');
        }
        */
        $log->ecrire();
        db::commit();


        $import_list = db::dquery("

			SELECT i.id, i.url, i.type, i.config, i.tag_mise_a_jour, i.source, i.id_importation, i.description, floor(i.ordre) as lot
			FROM importation_donnees i
			LEFT JOIN importation_sources s USING(source)
			$where_clause AND NOT inactif AND (s.proxy IS NULL OR s.proxy = 0)
			ORDER BY ordre
			FOR UPDATE

			") or $this->finir_($mode, 'acces_table', "class horaires / import_data / select id...");


        $erreurs = 0;
        $changements = 0;
        $lot_courant = null;
        $incomplet = false;
        /*
                if ($import_list->num_rows == 0){
                    if ($mode == 'json'){
                        $this->succes();
                    } else{
                        $this->finir_($mode, 'OK', "rien à faire");
                    }
                }
        */
        if ($import_list->num_rows) {

            while ($row = $import_list->fetch_assoc()) {
                //debug_print_once('importation = ' . print_r($row,1));
                if ($lot_courant != $row['lot']) {
                    if ($lot_courant != null) {
                        $res = db::query("

                            UPDATE importation_donnees
                            SET last_update = now()
                            WHERE floor(ordre) = $lot_courant

                        ") or $this->finir_($mode, 'acces_table', " update .. where floor(ordre) = $lot_courant");
                    }
                    //debug_push(1);
                    //debug_print('Changement de lot à heure: ' . date('U'));
                    if (date('U') > $arrete_apres) {
                        //debug_print("> $arrete_apres ==> break");
                        //debug_pop();
                        $incomplet = true;
                        break;
                    }
                    //debug_pop();
                    $lot_courant = $row['lot'];
                }

                $params = $row;

                //debug_print('import = ' . print_r($row, true));

                $log->log("========================================");
                $log->log(sprintf("%sTraitement #{$row['id']} ->{$row['description']}", date('H:i:s')));

                if (!array_key_exists($params['type'], $this->task_data)) {
                    $log->log("Type inconnu = {$params['type']}");
                    //debug_print ($log_data . "\ncontinue");
                    ++$erreurs;
                    continue;

                }
                /*
                if (!class_exists($this->task_data[$params['type']]['class'])){
                    require_once($this->task_data[$params['type']]['file']);
                }
                $donnees = new $this->task_data[$params['type']]['class']($params);
                */
                $donnees = $this->update_obj($params);

                if ($donnees) {
                    //debug_print_once('$donnees->import_if_changed();');
                    $donnees->import_if_changed();
                    $log->log($donnees->log_data);
                    $log->log("Result_code = " . $donnees->result_code);
                } else {
                    $log->log("Type non reconnu; classe introuvable");
                }


                if (!$donnees or $donnees->result_code == 'erreur') {
                    $erreurs++;
                    db::rollback();
                } else {
                    db::commit();
                    if ($donnees->result_code != 'aucun_changement') {
                        $changements++;
                    }
                }
                $res = db::query(sprintf("

                    UPDATE importation_donnees
                    SET id_importation =	$id_importation,
                        last_update =		NOW(),
                        succes =			%u,
                        result_code =		%s
                    WHERE id = %u

                ",
                    (!$donnees or $donnees->result_code == 'erreur') ? 0 : 1,
                    $donnees ? db::sql_str($donnees->result_code) : 'classe non reconnue',
                    $row['id']
                )) or $this->finir_($mode, 'acces_table', 'update importation_donnees');

            }
            // les infos du dernier lot doivent être enregistrées
            if ($lot_courant != null) {
                $res = db::query("

                    UPDATE importation_donnees
                    SET last_update = now()
                    WHERE floor(ordre) = $lot_courant

                ") or $this->finir_($mode, 'acces_table', " update .. where floor(ordre) = $lot_courant");
            }


            if ($incomplet) {
                $log->log("Suivants remis à plus tard (limite de temps atteinte: {$config_auto->temps_max} s)");
                do {
                    $log->log("{$row['description']}");
                } while ($row = $import_list->fetch_assoc());
            }
            if ($auto and $erreurs and $config_auto->courriel_err) {

                $misc_data = @file_get_contents('./misc_data.json');
                if ($misc_data) {
                    $misc_data = json_decode($misc_data);
                    if (!is_object($misc_data)) {
                        $misc_data = null;
                    }
                }
                $last = $misc_data->last_mail_time;
                if (is_numeric($last) and (date('U') - $last) / 3600 > 6) {
                    if (mail($config_auto->courriel_err, 'Erreur importation ', wordwrap($log->get('log'), 70))) {
                        $misc_data->last_mail_time = date('U');
                        file_put_contents('./misc_data.json');
                    }
                }


            }
        }
        // traiter les importations de données à travers proxy
        $this->traiter_proxy();


        $log->enreg_fin();
        $log->set('erreurs', $erreurs);
        $log->ecrire();

        /*
        $res = db::query("

            UPDATE importation_donnees_date
            SET date_fini = NOW(),
            log = $log_data,
            erreurs = $erreurs
            WHERE id_importation = $id_importation

            ") or
                $this->finir_($mode, 'acces_table', 'update importation_donn...');
                */

        db::commit();
        $log_cal = new log_importation();
        $log_cal->enreg_debut();
        $log_cal->log('Mise à jour calendrier google');
        $log_cal->ecrire();
        /*
        $res = db::query("

            INSERT INTO importation_donnees_date
            SET date_import = now(), log = 'Mise à jour calendrier google; '


        ") or
                $this->finir_($mode,'acces_table', "");
         *
         */
        //$id_maj_calendrier = db::get('insert_id');

        $id_maj_calendrier = $log_cal->id();

//        try {
//            $update_calendrier = new gcal_update();
//            $update_calendrier->update();
//            $result = $update_calendrier->result_msg;
//            //debug_print_once("résultat update calendrier = " . $update_calendrier->result_msg);
//        } catch (Exception $err) {
//            //debug_print_once("erreur maj calendrier -> " . $err->getMessage());
//            $result = $err->getMessage();
//        }

        /*
        $result = db::sql_str($result);


        $res = db::query("

            UPDATE importation_donnees_date
            SET log = concat(log ,$result), date_fini = now()
            WHERE id_importation = $id_maj_calendrier

            ") or
                $this->finir_($mode, 'acces_table', 'update importation_donnes. maj calendreir');

         *
         */
//        $log_cal->log($result);
//        $log_cal->ecrire();

        if ($mode == 'json') {
            $this->succes();
        } else {
            db::commit();
            $this->finir_($mode, 'OK', "nombre d\'erreurs = $erreurs; nombre de changements = $changements");
        }


    }

    function fn_compter_matchs_calendrier()
    {
        $res = db::query("
			SELECT count(*) nb, min(date) premier, max(date) dernier
			FROM ehl
			WHERE gcal_event IS NOT NULL
		",
            'acces_table', 'compte matches');
        self::$data = $res->fetch_assoc();
        $this->succes();
    }

    function fn_effacer_tous_matchs_calendrier()
    {
        if (!perm::test('admin')) {
            $this->fin('non_autorise');
        }

        $res = db::query("
			INSERT IGNORE INTO gcal_effacement
			(gcal_event)
			SELECT gcal_event
			FROM ehl e
			WHERE e.gcal_event IS NOT NULL
		",
            'acces_table');
        $res = db::query("
			UPDATE ehl
			SET gcal_event = NULL,
				gcal_a_sauvegarder = 0,
				gcal_update_date = now(),
				gcal_status = 0
			WHERE gcal_event IS NOT NULL
		",
            'acces_table', 'update ehl');
        try {
            $update = new gcal_update();
            $update->update();
        } catch (Exception $err) {
            $this->fin($err->getMessage());
        }
        $this->succes();
    }

    function fn_effacer_tous_participants_calendrier()
    {
        if (!perm::test('admin')) {
            $this->fin('non_autorise');
        }
        try {
            $update = new gcal_update();
            $update->effacer_participants();
        } catch (Exception $err) {
            $this->fin($err->getMessage());
        }
        $this->succes();
    }

    function update_signature_version(&$data)
    {
        if ($data['type'] == 'hca_matchs_XL') {
            $obj = new lehockey_ca_matchs_XL($data);
            if (file_exists($obj->filename) and date('Y-m-d', filemtime($obj->filename)) < date('Y-m-d')) {
                $contenu = unserialize(file_get_contents($obj->filename));
                if (!is_array($contenu)) {
                    $data['signature_version'] = '';
                    return;
                }
                $contenu = serialize(XLS_hockey_ca::purge_older($contenu));
                $data['signature_version'] = sha1($contenu);
                file_put_contents($obj->filename, $contenu);
            }
        }

    }

    function traiter_proxy()
    {
        $this->assigner_taches_proxy();


        db::commit();

        // obtenir les infos des proxies 1 heure après celle à laquelle ils doivent l'avoir obtenue
        // délai permet au proxy d'introduire un délai aléatoire
        $res = db::query("
			SELECT i.*, p.url_task url, id.signature_version, id.type, id.config cfg
			FROM importation_proxy_taches i
			JOIN importation_proxy p USING(id_proxy)
			JOIN importation_donnees id ON i.id_importation = id.id
			WHERE date(date_du) = curdate()
			AND date_du < subdate(NOW(), INTERVAL 30 MINUTE)
			AND statut = 'en_attente'
			ORDER BY id_proxy
		",
            'acces_table', '');
        if ($res->num_rows == 0) {
            return false;
        }
        $import_proxy = db::result_array($res);
        $taches = array();
        foreach ($import_proxy as $import) {
            $this->update_signature_version($import);

            $taches[$import['id_proxy']]['data'][$import['id_tache']] = $import['signature_version'];
            $taches[$import['id_proxy']]['url'] = $import['url'];
        }

        // obtenir les données des proxies en fournissant à chacun la liste de tâches pour lesquels on demande les données

        $erreurs_prog = array();
        $inchanges = array();
        $this->log_proxy = new log_importation();
        $this->log_proxy->log('Fonction traiter_proxy');
        foreach ($taches as $id_proxy => $data_proxy) {
            $this->log_proxy->log('get data de proxy no ' . $id_proxy);

            $data = http_post::get_response_(

                $data_proxy['url'],

                array(
                    'op' => 'get_data',
                    'taches' => $data_proxy['data'],
                    'origine' => cfg_yml::get('general', 'code_client') . ($GLOBALS['is_local'] ? '_local' : '_remote')
                )
            );

            $data = json_decode($data, true);

            //debug_print_once(print_r($data,1));


            if (!$data['result']) {
                $this->log_proxy->log('Réponse = erreur...');
                $erreurs_prog[] = $id_proxy;
                continue;
            }
            $this->log_proxy->log('Réponse = ok');
            foreach ($data['contenus'] as $id => $contenu) {


                //debug_print_once("mise à jour tache $id signature = '{$contenu['signature_version']}'; statut = {$contenu['statut']}");
                $statut_proxy = db::sql_str($contenu['statut']);
                $msg_proxy = db::sql_str($contenu['msg']);

                if (in_array($contenu['statut'], array('en_attente', 'complete', 'inchange', 'erreur'))) {
                    $set_nouveau_statut = "ipt.statut = '{$contenu['statut']}',";
                } else {
                    $set_nouveau_statut = '';
                }
                $succes_importation = false;
                $update_signature = '';
                if ($contenu['statut'] == 'complete') {
                    $this->log_proxy->log('Importation proxy tâche no ' . $id);


                    $succes_importation = $this->importer_de_proxy($id, $contenu);
                    if ($succes_importation) {
                        $update_signature = 'i.signature_version = ' . db::sql_str($contenu['signature_version']) . ',';
                        $this->log_proxy->log('Importation réussie');
                    } else {
                        $this->log_proxy->log('Échec d\'importation');
                        $set_nouveau_statut = 'ipt.statut = \'erreur\',';
                        db::rollback();
                    }

                } else {
                    $this->log_proxy->log("Statut de tâche no $id sur proxy = {$contenu['statut']}");
                }

                $res = db::query("
					UPDATE importation_proxy_taches ipt
					JOIN importation_donnees i ON i.id = ipt.id_importation
					set ipt.statut_proxy = $statut_proxy,
						$set_nouveau_statut
						$update_signature
						ipt.date_statut_proxy = NOW(),
						ipt.msg_proxy = $msg_proxy
					WHERE ipt.id_tache = $id

				",
                    'acces_table', 'mise à jour statut proxy');
                db::commit();
            }


        }
        if (count($erreurs_prog)) {
            $this->log_proxy->log('Réponse du proxy = erreur pour tâches ' . implode(',', $erreurs_prog));
        }
        $this->log_proxy->ecrire(true, true);
    }

    function update_obj($params)
    {
        //debug_print_once("update obj");
        if (!array_key_exists('type', $params) or !array_key_exists($params['type'], $this->task_data)) {
            $this->log_proxy->log('Ces paramètres ne permettent pas d\'identifier une classe: ' . json_encode($params));
            //debug_print_once("erreur 1");
            return false;
        }
        if (!class_exists($this->task_data[$params['type']]['class'])) {
            //debug_print_once("inclut {$this->task_data[$params['type']]['file']}");
            @include $this->task_data[$params['type']]['file'];
            if (!class_exists($this->task_data[$params['type']]['class'])) {
                $this->log_proxy->log('Classe introuvable: ' . $this->task_data[$params['type']]['class']);
                //debug_print_once("erreur 2");
                return false;
            }
        }
        //debug_print_once("nouv classe");
        $cl = $this->task_data[$params['type']]['class'];
        return new $cl($params);

	}

    function importer_de_proxy($id, $data)
    {
        // trouver les données sur la  tâche pour savoir quoi faire avec les données
        $res = db::query("
			SELECT i_d.*
			FROM importation_donnees i_d
			JOIN importation_proxy_taches ipt ON ipt.id_importation = i_d.id
			WHERE ipt.id_tache = $id
		",
            'acces_table', "trouver données tache $id");
        if ($res->num_rows != 1) {
            $this->log_proxy->log('Tàche d\'importation par proxy introuvable (no ' . $id . ')');
            return false;
        }
        $params = $res->fetch_assoc();
        //debug_print_once("params tache proxy = " . print_r($params,1));
        $obj = $this->update_obj($params);
        //debug_print_once("type obj = " . get_class($obj));
        if (!$obj) {
            $this->log_proxy->log('objet introuvable pour tache no ' . $id);
            return false;
        }

        if (!method_exists($obj, 'importer_donnees_proxy')) {
            $this->log_proxy->log('Méthode "importer_donnees_proxy" introuvable dans classe ' . get_class($obj) . ' pour tâche no ' . $id);
            return false;
        }
        if (method_exists($obj, 'set_log')) {
            //	debug_print_once("trouvé fnct set_log");
            $obj->set_log($this->log_proxy);
        }
        //debug_print_once("procede à importation donnees proxy");

        $succes = $obj->importer_donnees_proxy($data['contenu']);

        return $succes and ($obj->save_to_cache($data['contenu']) !== false);

    }

    static function get_cfg($force = false)
    {
        if (!$force and self::$cfg) {
            return self::$cfg;
        }
        self::$cfg = parse_ini_file(dirname(__FILE__) . '/config_horaires.ini');
        if (!is_array(self::$cfg)) {
            self::$cfg = array();
        }
        return self::$cfg;
    }

    function assigner_taches_proxy()
    {

        $log = new log_importation();
        $log->log('Assignation de tâches à proxy');
        //debug_print_once("assignation proxy");
        // effacer les tâches plus vieilles que 3 jours

        $res = db::query("
			DELETE FROM importation_proxy_taches
			WHERE date_cree < SUBDATE(curdate(), INTERVAL 3 DAY)
		",
            'acces_table', 'effacement vieilles taches proxy');


        //vérifier si des types de données ont été définies comme devant être importées par proxy
        $res = db::query("
			SELECT i.id, ipt.id_tache, ipt.statut
			FROM importation_donnees i
			JOIN importation_sources s USING(source)
			LEFT JOIN importation_proxy_taches ipt ON ipt.id_importation = i.id AND ipt.date_cree = CURDATE()
			WHERE s.proxy AND NOT inactif
		",
            'acces_table', '');
        $liste_taches_et_statut = db::result_array($res);

        // si aucune tâche, rien à faire
        if (count($liste_taches_et_statut) == 0) {
            $log->log('aucune tâche pour proxy');
            $log->ecrire(true);
            return false;
        }

        // trouver tâches a_soumettre => on va les réassigner à un proxy pour leur trouver une nouvelle heure
        // d'exécution et possiblement un autre proxy
        $taches_a_soumettre = array();
        $liste_taches = array();

        foreach ($liste_taches_et_statut as $val) {
            if ($val['statut'] == 'a_soumettre') {
                $taches_a_soumettre[] = $val['id_tache'];
                $liste_taches[] = $val['id'];
            } else if (is_null($val['statut'])) {
                $liste_taches[] = $val['id'];
            }
        }
        if (count($taches_a_soumettre)) {
            $log->log('Effacement de ' . count($taches_a_soumettre) . ' tâches à resoumettre');

            $taches_a_soumettre = implode(',', $taches_a_soumettre);

            $res = db::query("
				DELETE from importation_proxy_taches
				WHERE id_tache IN ($taches_a_soumettre)
			",
                'acces_table', 'effacement taches à resoumettre');
        }
        if (!count($liste_taches)) {
            $log->log('Aucune tâche pour proxy2');
            $log->ecrire(true);
            return false;
        }

        // mettre tâches dans ordre aléatoire
        shuffle($liste_taches);

        // obtenir la liste des proxies actifs
        $res = db::query("
			SELECT id_proxy, url_task
			FROM importation_proxy
			WHERE NOT inactif
		",
            'acces_table', '');
        if ($res->num_rows == 0) {
            $log->log('Aucun proxy disponible');
            $log->ecrire(true);
            return false;
        }
        while ($row = $res->fetch_assoc()) {
            $this->liste_proxies[] = $row['id_proxy'];
            $this->url_proxies[$row['id_proxy']] = $row['url_task'];
        }


        // mettre liste de proxies en ordre aléatoire
        shuffle($this->liste_proxies);

        if (!$this->auto_cfg) {
            $this->get_auto_cfg();
        }

        $taches_proxy = array();

        // vérifier si la date de début de l'importation auto est réglée
        preg_match('#^(\d{1,2})(?:(\d{0,2}))?#', $this->auto_cfg['debut'], $a);

        // sinon, la régler à 7h
        if (!array_key_exists(1, $a) or !is_numeric($a[1]) or $a[1] < 0 or $a[1] > 23) {
            $a[1] = 7;
        }

        if (!array_key_exists(2, $a) or !is_numeric($a[2]) or $a[2] < 0 or $a[2] > 59) {
            $a[2] = 0;
        }

        $begin_time = new DateTime();
        $begin_time->setTime($a[1], $a[2]);

        //debug_print_once("Date de début initiale = " . $begin_time->format('Y-m-d H:i:s'));

        // si l'heure de démarrage est passée, démarrer maintenant
        if ($begin_time->format('U') < date('U')) {
            $begin_time = new DateTime();
            //debug_print_once("vu que heure passée, début repoussé à " . $begin_time->format('Y-m-d H:i:s'));
        }

        // tenir compte du nombre max d'heures pendant lesquelles il est accepté de communiquer avec proxies
        $max_time_stamp = $begin_time->format('U') + $this->auto_cfg['temps_total_proxy'] * 60;

        $task_time = $begin_time;
        $debut = 1;
        $index_proxy = 0;

        foreach ($liste_taches as $id) {
            // la 1ère tâche sera exécutée à l'heure initiale + 0 à 2 intervalles; suivantes 1 à 3 intervalles plus tard
            $task_time->modify($mod = '+' . ((mt_rand(1, 3) - $debut) * $this->auto_cfg['delai_proxy']) . ' minutes');
            //debug_print_once("date tache modifiée de $mod => " . $task_time->format('Y-m-d H:i:s'));
            if ($task_time->format('U') > $max_time_stamp) {
                $task_time->modify('-' . $this->auto_cfg['temps_total_proxy'] . ' minutes');
                //debug_print_once("limite dépassée alors reporté à " . $task_time->format('Y-m-d H:i:s'));
            }
            $debut = 0;
            $taches_proxy[] = array('id' => $id, 'time' => $task_time->format('Y-m-d H:i:s'), 'proxy' => $this->liste_proxies[$index_proxy++]);
            if ($index_proxy >= count($this->liste_proxies)) {
                $index_proxy = 0;
            }
        }
        if (!count($taches_proxy)) {
            $log->log('Aucune tâche');
            $log->ecrire(true);
            return false;
        }

        $values = array();

        foreach ($taches_proxy as $vals) {
            $values[] = "'a_soumettre',curdate(),'{$vals['time']}', {$vals['id']}, {$vals['proxy']}, NOW()";
        }
        $values = implode($values, '),(');

        $res = db::query("
			INSERT IGNORE INTO importation_proxy_taches
			(statut, date_cree, date_du, id_importation, id_proxy, date_statut)
			VALUES
			($values)
		",
            'acces_table', 'insertion taches proxy');

        $res = db::query("
			SELECT t.id_proxy, t.id_tache, t.date_du, i.url, i.config, i.source, i.type, i.description, i.id
			FROM importation_proxy_taches t
			JOIN importation_donnees i ON t.id_importation = i.id
			WHERE statut = 'a_soumettre'
		",
            'acces_table', '');
        if ($res->num_rows == 0) {
            $log->log('Rien trouvé à soumettre, après pourtant avoir inséré des tâches...');
            $log->ecrire(true);
            return false;
        }
        $taches = db::result_array_group($res, 'id_proxy');

        $post = new http_post();
        foreach ($taches as $id_proxy => $taches_proxy) {
            $liste_id_taches = array();
            $taches_erreur_cfg = array();
            foreach ($taches_proxy as $ind => $vals) {
                if (!is_array(json_decode($vals['config'], true))) {
                    $taches_erreur_cfg[] = $vals['id_tache'];
                    $log->log("ATTENTION tâche #{$vals['id_tache']}({$vals['description']}) non ou mal configurée");
                    unset($taches_proxy[$ind]);
                    continue;
                }

                $liste_id_taches[] = $vals['id_tache'];
            }
            if (count($taches_erreur_cfg)) {
                $taches_erreur_cfg = implode(',', $taches_erreur_cfg);
                $res = db::query("
					UPDATE importation_proxy_taches
					SET statut = 'erreur',
					date_statut = NOW()
					WHERE id_tache IN ($taches_erreur_cfg)
				",
                    'acces_table', "maj statut_proxy '' pour taches mal cfg $taches_erreur_cfg");

            }
            if (count($liste_id_taches) == 0) {
                continue;
            }

            $liste_id_taches = implode(',', $liste_id_taches);
            $post->set_url($this->url_proxies[$id_proxy]);
            $post->set_data($request = array(
                'op' => 'add_tasks',
                'tasks' => $taches_proxy,
                'origine' => cfg_yml::get('general', 'code_client') . ($GLOBALS['is_local'] ? '_local' : '_remote'),
                'ini' => self::get_cfg()
            ));
            debug_print_once("Request = " . print_r($request, 1));
            $a = $post->get_response();
            debug_print_once("aaaaaaaaaaaaaaa = " . print_r($a, 1));
            $res = json_decode($a, true);

            if (!is_array($res) or !array_key_exists('result', $res)) {
                $res = array('result' => 0);
            }

            if (array_key_exists('msg', $res) and $res['msg']) {
                $msg = $res['msg'];
                if (array_key_exists('ref', $res) and $res['ref']) {
                    $msg .= " ({$res['ref']})";
                }
                $log->log($msg);
            }


            $statut = ($res['result'] ? 'en_attente' : 'erreur');

            $res = db::query("
				UPDATE importation_proxy_taches
				SET statut = '$statut',
				date_statut = NOW()
				WHERE id_tache IN ($liste_id_taches)
			",
                'acces_table', "maj statut_proxy '$statut' pour taches soumises $liste_id_taches");

            $log->log("Changé statut à $statut pour tâches $liste_id_taches (proxy $id_proxy)");
        }


        $log->ecrire(true);

        return true;
    }
    // reçoit un id_tache (correspondant à une tâche à exécuter par proxy dans importation_proxy_taches)
    // reçoit aussi id_proxy pour vérification seulement
    // retourne objet pour cette tâche
    function obj_tache($id_tache, $id_proxy = null)
    {
        if (!is_numeric($id_proxy)) {
            $cond_proxy = '1';
        } else {
            $cond_proxy = "id_proxy = $id_proxy";
        }
        if (!is_numeric($id_tache)) {
            return false;
        }

        $res = db::query("
			SELECT id.type, id.config
			FROM importation_proxy_taches t
			JOIN importation_donnees id ON id.id = t.id_importation
			WHERE id_tache = $id_tache AND $cond_proxy
		",
            'acces_table', 'recherche type pour données reçues de proxy');
        if ($res->num_rows == 0) {
            $this->extra_log_data .= "type de données introuvable pour tache $id_tache";
            return false;
        }
        $params = $res->fetch_assoc();
        if (!($obj = $this->obj_type($params))) {
            return false;
        }


    }

    function obj_type($params)
    {
        $type = $params['type'];
        if (!array_key_exists($type, $this->task_data)) {
            $this->extra_log_data .= "type de données $type non défini (tâche )";
            return false;
        }
        if (!$this->task_data[$type]['proxy']) {
            $this->extra_log_data .= "type de données $type non défini comme à obtenir de proxy (tâche )";
            return false;
        }
        require_once $this->task_data[$type]['file'];

        $cl = $this->task_data[$type]['class'];
        return new $cl($params);

	}

    function fn_efface_source()
    {
        //require_once('../../include/connect.php');
        if (!perm::test('admin')) {
            $this->fin('niveau_insuffisant');
        }

        /**
        * @var integer $id
        */
        extract(self::check_params(
            'id;unsigned_list'
        ));

        db::query("

			DELETE FROM importation_donnees
			WHERE id in($id)


			",
            'acces_table', 'delete...');
        $this->succes();

    }

    function fn_get_groups()
    {
        //require_once('../../include/connect.php');
        $res = db::query("

			SELECT DISTINCT groupe
			FROM ehl
			ORDER BY groupe

			",
            'acces_table', 'horaires.php get_groups');
        self::$data['liste'] = array();
        if ($res->num_rows) {
            while ($row = $res->fetch_assoc())
                self::$data['liste'][] = $row['groupe'];
        }
        $this->succes();
    }

    function fn_get_results()
    {

        extract(self::check_params(
            'nb;unsigned',
            'recent;unsigned;opt;accept_null',
            'old;unsigned;opt;accept_null'
        ));


        if (isset($recent) and !is_null($recent)) {
            $where_id = "WHERE id_importation >= $recent";
            $limit = '';
        } else {
            $where_id = 'WHERE 1';
            $limit = "limit 20";
        }
        //require 'connect.php';
        $res = db::query("

		SELECT id_importation as id, date_import, date_fini, no_essai, log
		FROM importation_donnees_date
		$where_id
		ORDER BY id_importation DESC
		$limit

		",
            'acces_table', 'horaires.php / select id_importation ... recent' . d());


        self::$data['recent_results'] = $this->list_data($res, $min, $max, true); // true pour marquer le premier d'un div
        self::$data['recent_id'] = $max;
        self::$data['old_id'] = $min;

        if (isset($old) and !is_null($old)) {
            $where_id = "WHERE id_importation < $old";

            $res = db::query("

				SELECT id_importation as id, date_import, date_fini, no_essai, log
				FROM importation_donnees_date
				$where_id
				ORDER BY id_importation DESC
				limit 20

			",
                'acces_table', 'horaires.php / select id_importation ... old');
            if ($res->num_rows == 0) {
                self::$data['old_id'] = $old;
                self::$data['old_results'] = '';
            } else {
                self::$data['old_results'] = $this->list_data($res, $min, $max);
                self::$data['old_id'] = min($min, self::$data['old_id']);
                if (is_null(self::$data['old_id'])) {
                    self::$data['old_id'] = $min;
                }
            }
        }


        $this->succes();
    }

    /**
     * @param mysqli_result $res
     * @param $min
     * @param $max
     * @param bool $mark_1st
     * @return mixed|string
     */
    function list_data($res, &$min, &$max, $mark_1st = false)
    {
        if ($res->num_rows == 0) {
            return '';
        }
        $to_ret = '';
        $first = true;

        $commence = self::msg('commence_le');
        $fini = self::msg('fini_le');
        $no_essai = self::msg('no_essai');

        while ($row = $res->fetch_assoc()) {
            if ($first) {
                $max = $row['id']; // le premier est tjrs le plus grand puisque ordre DESC
            }
            $min = $row['id'];
            if ($first and $mark_1st) {
                $to_ret .= '<div class="first">';
            }


            $to_ret .= "\n<span style='font-weight:bold'>#{$row['id']}- $commence {$row['date_import']}; $fini {$row['date_fini']}; $no_essai: {$row['no_essai']}</span>";
            $to_ret .= "\n" . $row['log'];
            if ($first and $mark_1st) {
                $to_ret .= '</div>';
            }
            $to_ret .= "<div class='nav'><span class='prev' style='color:blue;cursor:pointer'>" . str_repeat('&lt;', 30) . '</span><span class="next" style="color:blue;cursor:pointer">' . str_repeat('&gt;', 30) . "</span></div>";
            if ($first) {
                $first = false;
            }
        }
        return str_replace("\n", '<br/>', $to_ret);

    }

    function get_auto_cfg($array = true)
    {

        $default = array(
            'importer_auto' => false,
            'frequence' => 60,
            'temps_max' => 5,
            'debut' => '7:00',
            'fin' => '23:00',
            'courriel_err' => cfg_yml::get('general', 'adresse_courriel_test'),
            'delai_proxy' => 10, // délai entre deux tâches proxy
            'temps_total_proxy' => 300  // temps (minutes) pour faire exécuter toutes les tâches par proxies
        );

        $f_contents = @file_get_contents(__DIR__ . '/horaires_auto_config.json');

        $this->auto_cfg = json_decode($f_contents, true);

        if (!is_array($this->auto_cfg)) {
            $this->auto_cfg = array();
        }
        $this->auto_cfg = array_merge($default, $this->auto_cfg);

        if ($array) {
            return $this->auto_cfg;
        }

        $cfg = json_decode($f_contents, false);

        if (!is_object($cfg)) {
            $cfg = new stdClass();
        }

        foreach ($default as $param => $val) {
            if (!isset($cfg->$param)) {
                $cfg->$param = $val;
            }
        }

        return $cfg;
    }

    function finir_($mode, $msg, $detail = '')
    { // mode = text ou json

        if ($mode == 'text') {
            header('Content-type:text/html; charset=utf-8');
            die("\n" . date("H:i:s : ") . "  $msg ($detail)");

        } else {
            $this->fin($msg, $detail);
        }
    }

    function get_liste_horaires(){
        $liste = [];

        //require_once(ROOT . 'include/connect.php');

        $res = db::query("

			SELECT id, description, source, config, type, url, ordre, ifnull(inactif, 0) as inactif
			FROM importation_donnees
			ORDER BY ordre, description

			") or fin('acces_table', 'classe horaires_ / get_liste_horaires');

        $liste['horaires'] = db::result_array($res);

        A::setBooleanEach($liste['horaires'], ['inactif']);
        A::setIntEach($liste['horaires'], ['ordre']);

        $res = db::query("

			SELECT DISTINCT trim(source) as source
			FROM importation_donnees
			HAVING source <> ''
			ORDER BY source

			") or fin('acces_table', 'classe horaires_ / select source');

        $liste['sources'] = db::result_array_one_value($res, 'source');
        return $liste;
    }
}


?>