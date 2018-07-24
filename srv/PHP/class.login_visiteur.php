<?php

use Phamda\Phamda as P;

class login_visiteur extends http_json{

    static public $pseudo;
    static private $mdp;
    static private $refresh_interval = 200;
    static public $msg = '';

    const MD5_REGEX = '#^[a-f0-9]{32}$#i';
    static public $valid_pw = 'regex:#^(\d\d\d){64,}$#';

    function __construct(){
        parent::__construct();
        $this->trim_all_data();

        self::$msg = new msg('class_login_visiteur', $this);

        $this->execute_op();

    }
    static function fin($index, $info = ''){
        return self::$msg->fin($index,$info);

    }
    /*
     * vérifier que param http 'code' est un entier supérieur ou égal à 100000
     */

    function valider_code(){
        /**
         * @var $code int
         */
        extract(self::check_params(
            'code;unsigned;min:100000'
        ));
        return $code;
        /*
        if (!$this->test_int('code', 100000)){
            $this->fin('code_validation_visiteur_invalide');
        }
        return self::$val;
         *
         */
    }
    /*
     * vérifier que param http 'email' est un courriel valide
     */
    function valider_email(){

        /**
         * @var $email string
         */
        extract(self::check_params(
            'email;courriel'
        ));
        return $email;

    }
    /*
     * simplement valider que code de validation et courriel fournis existent dans un enregistrement de la db
     */
    function fn_valider(){

        /**
         * @var int $code
         * @var string $courriel
         **/
        extract(self::check_params(
            'courriel;courriel;sql',
            'code;unsigned;min:100000'
        ));

        /**
         * @var string $nom
         **/
        $res = db::query(("
			SELECT concat(prenom, ' ', nom) nom
				FROM membres
				WHERE code_validation = $code and courriel = $courriel AND NOT code_pour_nouveau_courriel
				"),
            'acces_table');


        if ($res->num_rows == 0){
            $this->fin('mauvais_code');
        }
        extract($res->fetch_assoc());
        self::$data['nom'] = $nom;
        $this->succes();

    }

    function fn_changer_mdp(){
        $md5 = self::MD5_REGEX;

        /**
         * @var int $id
         * @var string $mdp
         * @var string $nouv_mdp
         **/
        extract(self::check_params(
            'id;unsigned',
            "mdp;regex:$md5",
            "nouv_mdp;regex:$md5"

        ));


        /**
         * @var int $nb
         **/
        $res = db::query("
			SELECT count(*) nb
			FROM membres
			WHERE id = $id AND mot_passe = '$mdp'
		",
            'acces_table', 'verif mdp');

        extract($res->fetch_assoc());
        if ($nb != 1){
            $this->fin('mauvais_mdp');
        }

        $res = db::query("
			UPDATE membres
			SET mot_passe = '$nouv_mdp',
			doit_changer = 0
			WHERE id = $id
		",
            'acces_table', 'sauvegarde nouveau mdp');
        $this->succes();
    }

    /**
     * Changer mdp pour un usager identifié uniquement par courriel et code de validation
     * (utilisé à la fin de la procédure de récupération de mdp perdu)
     */
    function fn_changer_mdp2(){
        /**
         * @var string $courriel
         * @var int $code
         * @var string $mdp
         * @var string $pseudo
         **/
        extract(self::check_params(
            'courriel;courriel;sql',
            'code;unsigned;min:100000',
            'mdp;regex:' . self::MD5_REGEX,
            'pseudo;string;min:5;sql'
        ));


        /**
         * @var int $id
         **/
        $res = db::query("
			SELECT id
			FROM membres
			WHERE courriel = $courriel
				AND code_validation = $code AND NOT code_pour_nouveau_courriel
			FOR UPDATE
		",
            'acces_table', 'select pseudo');
        if ($res->num_rows > 1){
            $this->fin('plus_d_un_changement');
        } else if ($res->num_rows == 0){
            $this->fin('information_plus_valide');
        }
        extract($res->fetch_assoc());

        /**
         * @var int $id_pseudo
         **/
        $res = db::query("
			SELECT id id_pseudo
			FROM membres
			WHERE pseudo = $pseudo
			FOR UPDATE
		",
            'acces_table','recherche pseudo');

        if ($res->num_rows != 0){
            if ($res->num_rows == 1){
                extract($res->fetch_assoc());
                if ($id_pseudo != $id){
                    $this->fin('nom_usager_non_dispo');
                }
            } else if ($res->num_rows > 1){
                $this->fin('nom_usager_non_dispo');
            }
        }

        $res = db::query("
			UPDATE membres
			SET mot_passe = '$mdp',
				code_validation = null,
				pseudo = $pseudo
			WHERE id = $id
		",
            'acces_table', 'update membres');
        $this->succes();
    }
    /**
     * changer ID et PSEUDO pour un usager identifié par courriel et code de validation
     */
    function fn_changer_mdp3(){
        /**
         * @var $courriel string
         * @var $code int
         * @var $pseudo string
         * @var $mdp string
         */
        extract(self::check_params(
            'courriel;courriel;sql',
            'code;unsigned;min:100000',
            'pseudo;string;min:4;max:15;sql',
            'mdp;regex:' . self::MD5_REGEX
        ));
        $this->accepte_code($pseudo, $mdp, $code, $courriel);
        if (self::login($pseudo, $mdp)){
            $this->succes();
        } else {
            $this->fin('');
        }

    }

    function fn_changer_lang(){
        lang::switch_lang();
        self::$data['lang'] = lang::lang();
        $this->succes();
    }

    /*
     * une adresse étant fournie comme param http, vérifier qu'elle est valable et qu'elle est présente dans db.
     *
     * si oui, générer nouveau code de validation et l,envoyer
     */
    function fn_envoyer_courriel(){

        /**
         * @var string $courriel
         **/
        extract(self::check_params(
            'courriel;courriel'
        ));

        $envois = new gestion_envoi_codes_validation($courriel);

        $envois->ajout_possible();

        $gc = new gestion_courriels($courriel);

        // donner un nouveau code de validation à tous ceux qui ont cette adresse de courriel
        $liste = $gc->assigner_codes_pour_courriel();

        if (!$liste){
            self::$data['adresse_introuvable'] = 1;
            self::$data['courriel_info'] = cfg_yml::get('courriels','info');
            $this->fin();
        }
        // régler destinataire pour courriel
        $envois->ajouter_destinataire($courriel);
        // régler le contenu du courriel (voici code pour prouver que l'adresse est à vous)
        $envois->msg_valider_adr_existante($liste);

        // noter l'envoi d'un courriel à cette adresse
        $envois->ajout_envoi();

        $envois->send();

        $this->succes();



    }

    // envoyer courriel pour prouver propriété de l'adresse avant de donner le même courriel à un parent
    function fn_envoyer_courriel_pour_transfert(){


        /**
         * @var int $id
         * @var string $courriel
         **/
        extract(self::check_params(
            'courriel;courriel',
            'id;unsigned' // id de l'enfant dont l'adresse est fournie
        ));

        $envois = new gestion_envoi_codes_validation($courriel, 'transfert');

        $gc = new gestion_courriels($courriel);
        $gc->set_id_membre($id);

        if (!$gc->verifier_appartenance_courriel()){
            $this->fin('mauvaise_adresse');
        }
        $envois->ajout_possible();

        $gc->assigner_code_a_membre();


        $envois->ajouter_destinataire($id);
        $envois->msg_transfert_adresse($id);

        $envois->send();

        $this->succes();


    }

    /*
     * vérifier si code de validation ET courriel fournis.
     *
     * accepter code de validation pour courriel fourni. en cas d'erreur, finir
     */
    function accepte_code($pseudo, $mdp, $code, $email){


        // vérifier qu'il n'y a qu'un enregistrement avec email et code_validation donnés
        $res = db::query(("

			SELECT id
			FROM membres
			WHERE courriel = $email and code_validation = $code AND NOT code_pour_nouveau_courriel
			FOR UPDATE

		"),
            'acces_table', "verif courriels");


        if ($res->num_rows != 1){
            $this->fin('code_validation_visiteur_invalide');
        }
        extract($res->fetch_assoc());

        // vérifier si le pseudo est dispo

        /**
         * @var int $id
         **/

        $res = db::query("

			SELECT id
			FROM membres
			WHERE pseudo = $pseudo
			AND id <> $id

		",
            'acces_table', "verification doublon pseudo");
        if ($res->num_rows){
            $this->fin('pseudo_non_dispo');
        }
        $mdp = db::sql_str($mdp);
        $res = db::query("

			UPDATE membres
			SET pseudo = $pseudo, mot_passe = $mdp
			WHERE id = $id

		",
            'acces_table', "écriture nouvelles valeurs pseudo et mot de passe");

        $res = db::query("

			UPDATE membres
			SET code_validation = null
			where courriel = $email

		",
            'acces_table', "remise à zéro des codes de validation");


        if (self::$testing){
            return true;
        }

        // une fois visiteur reconnu, compléter le login

        db::commit() or $this->fin('chg_impossible');
    }
    static function record_mdp_use($id_membre, $mdp)
    {
        $sha1_mdp = db::sql_str(sha1($mdp));
        $res = db::query("
                DELETE FROM mdp_utilises
                WHERE date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            ", 'acces_table');
        /**
         * @var int $nb
         **/
        $res = db::query("
                SELECT COUNT(*) nb
                FROM mdp_utilises
                WHERE mdp = $sha1_mdp AND id_membre = $id_membre
            ", 'acces_table');
        extract($res->fetch_assoc());
        if ($nb) {
            return false;
        }
        $res = db::query("
                INSERT IGNORE INTO mdp_utilises
                SET id_membre = $id_membre,
                mdp = $sha1_mdp,
                date = CURDATE()
            ", 'acces_table');

        return true;
    }
    function fn_login(){

        /**
         * @var int $pseudo
         * @var string $mdp
         * @var string $code
         * @var string $email
         **/
        extract(self::check_params(
            'id;string;min:4;max:15;sql;rename:pseudo',
            'mdp;' . self::$valid_pw,
            'code;unsigned;min:100000;opt',
            'email;courriel;sql;opt'
        ));


        if(isset($code) and isset($email)){
            $this->accepte_code($pseudo, $mdp, $code, $email);
        }

        self::logout(); // nécessaire pour nettoyer la session avant d'y inscrire des données
        // ... car inscription partielle pour visiteurs non éditeurs pourraient laisser des infos
        // concernant le dernier éditeur loggé sinon

        //compléter le login

        if (self::login($pseudo, $mdp)){
            self::$data['token'] = session_id();
            $this->succes();
        }

        $this->fin('err_login_pas_reconnu');

    }

    static function update_all_mdp($id = 0)
    {


        $res = db::query("
                SELECT id, mot_passe
                FROM membres
                WHERE LENGTH(mot_passe) = 32
                ORDER BY IF(id = $id, 0, 1)
                LIMIT 50
                FOR UPDATE
            ", 'acces_table');

        $liste = db::result_array($res);
        if ($res->num_rows == 0) {
            return true;
        }

        $hash = new \Phpass\Hash();
        $salt = cfg_yml::get_('general.salt');
        if (!$salt) {
            return false;
        }
        $values = array_map(function ($row) use ($hash, $salt) {
            list($id, $pw) = array_values($row);
            $pw = db::sql_str($hash->hashPassword(sha1($salt . $pw)));

            return "$id, $pw";
        }, $liste);

        $sql_values = implode('),(', $values);

        $res = db::query("
                CREATE TEMPORARY TABLE mots_passe
                (
                  id BIGINT UNSIGNED,
                  pw CHAR(60),
                  INDEX (id)
                )
                ENGINE=MEMORY
                
            ", 'acces_table');
        $res = db::query("
                TRUNCATE mots_passe
            ", 'acces_table');

        $res = db::query("
                INSERT INTO mots_passe
                (id, pw)
                VALUES 
                ( $sql_values )
            ", 'acces_table');

        $res = db::query("
                UPDATE membres m
                JOIN mots_passe mdp USING(id)
                SET m.mot_passe = mdp.pw
            ", 'acces_table');

        return true;
    }

    static function login($pseudo, $mdp){
        //debug_print_once("loginnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnn");
        $res = db::query("

			SELECT m.id,
				concat(m.prenom, ' ', m.nom, if(m.distinction,concat(' (', m.distinction, ')'),'')) as nom,
				count(if(re.role is null AND pn.id IS NULL, null, 1)) officiel,
                COUNT(pn_resultats.id) or m.marqueur is_marqueur,
                m.arbitre arbitre,
                COUNT(pn_resultats_actif.id) perm_resultats,
                COUNT(pn_horaire.id) perm_horaire,
                COUNT(pn.id) perm_controleur,
				ifnull(ed.id_membre,0) id_editeur,
				ed.perm_admin,
				
				ed.superhero,

				ifnull(ed.docum_usager, '-') docum_usager,
				age(m.date_naissance) age,
				m.doit_changer,
				# m.perm_admin_pub,
				m.mot_passe
			FROM membres m
			LEFT JOIN roles_courants re ON m.id = re.id_adulte
            LEFT JOIN permissions_niveaux pn ON m.id = pn.id_membre and pn.controleur > NOW()
            LEFT JOIN permissions_niveaux pn_horaire ON m.id = pn_horaire.id_membre and pn_horaire.horaire > NOW()
            LEFT JOIN permissions_niveaux pn_resultats_actif ON m.id = pn_resultats_actif.id_membre and pn_resultats_actif.resultats > NOW()
            LEFT JOIN permissions_niveaux pn_resultats ON m.id = pn_resultats.id_membre and pn_resultats.resultats > NOW()
			LEFT JOIN editeurs ed on m.id = ed.id_membre
			LEFT JOIN publ_rel_client_publicitaire pub_rel ON m.id = pub_rel.id_publicitaire
			WHERE pseudo = $pseudo
			group by id

		",
            'acces_table', "select id from membres " );

        if ($res->num_rows){

            include lang::include_lang('login_visiteurs.php');

            $row = $res->fetch_assoc();

            A::setBooleanEach($row, ['officiel', 'is_marqueur', 'arbitre', 'perm_resultats', 'perm_horaire', 'perm_controleur', 'perm_admin', 'superhero', 'doit_changer']);

            if (!self::record_mdp_use($row['id'], $mdp)) {
                debug_print_once('already used');
                return false;
            }

            $hash = new \Phpass\Hash();

            $unscrambled_pw = self::unscramble_pw($mdp);
            if (!$unscrambled_pw) {
                debug_print_once('fail unscramble');
                return false;
            }

            if (strlen($row['mot_passe']) == 32) { // charger le mdp qui devrait avoir été changé
                if (!self::update_all_mdp()) {
                    debug_print_once('fail update all mdp');
                    return false;
                }
                /**
                 * @var string $mot_passe
                 **/
                $res = db::dquery("
                        SELECT mot_passe
                        FROM membres
                        WHERE id = {$row['id']}
                    ", 'acces_table');

                extract($res->fetch_assoc());
                $row['mot_passe'] = $mot_passe;

            }

            debug_print_once("about to check $unscrambled_pw against {$row['mot_passe']}");
            if (!$hash->checkPassword($unscrambled_pw, $row['mot_passe'])) {
                debug_print_once("failed checkPassword $unscrambled_pw");
                debug_print_once("vs stored password " . $row['mot_passe']);
                return false;
            }

            $row['nom'] = gestion_membres::formate_nom($row['nom']);
            session::set('pseudo', $pseudo);
            //session::set('mdp', $mdp);


            self::$data['nom'] =		session::set('nom_visiteur',	$row['nom']);
            self::$data['id'] =			session::set('id_visiteur',		$row['id']);
//            self::$data['officiel'] =	session::set('officiel',		!!$row['officiel']);
            self::$data['perm_resultats'] = session::set('perm_resultats',		$row['perm_resultats'] or $row['superhero'] or $row['perm_admin']);
            self::$data['perm_horaire'] = session::set('perm_horaire',		$row['perm_horaire'] or $row['superhero'] or $row['perm_admin']);
            self::$data['perm_controleur'] = session::set('perm_controleur',		$row['perm_controleur'] or $row['superhero'] or $row['perm_admin']);
            self::$data['is_marqueur'] = session::set('is_marqueur',$row['is_marqueur'] or $row['superhero'] or $row['perm_admin'] );
            self::$data['is_arbitre'] = session::set('is_arbitre',		$row['arbitre'] == '1');

            #debug_print_once('row login = ' . print_r($row, 1));

            $description_login = array();

            if ($row['id_editeur']){
                session::set('id_editeur', $row['id_editeur']);
                session::set('id_msg',					$row['nom']); // attention... utilisé dand transferts de données entre secitons de contenu
                session::set('perm_structure',			$row['perm_structure'] or $row['superhero']);
                session::set('perm_admin',				$row['perm_admin'] or $row['superhero']);
//                session::set('perm_inscription',		$row['perm_inscription'] or $row['superhero']);
//                session::set('perm_admin_inscription',	$row['perm_admin_inscription'] or $row['superhero']);
//                session::set('perm_convert',			$row['perm_convert'] or $row['superhero'] or $row['perm_admin']);
//                session::set('perm_communications',		$row['perm_communications'] or $row['superhero'] or $row['perm_admin']);
//                session::set('perm_insertion_contenu',	$row['perm_insertion_contenu'] or $row['superhero'] or $row['perm_admin']);
//                session::set('perm_edit_classes',	$row['perm_edit_classes'] or $row['superhero'] or $row['perm_admin']);
//                session::set('perm_defilement',		$row['perm_defilement'] or $row['superhero'] or $row['perm_admin']);
//                session::set('choix_db',		(integer) $row['choix_db']);
                session::set('superhero',		!!$row['superhero']);
//                session::set('type_usager',		$row['type_usager']);
//                session::set('docum_usager',	$row['docum_usager']);
//                session::set('perm_admin_pub',	$row['perm_admin_pub'] or $row['superhero']);



                //self::$data['prenom'] =			$row['prenom'];
                self::$data['nom'] =			$row['nom'];
//                self::$data['perm_structure'] = session::get('perm_structure');
                self::$data['perm_admin'] =		session::get('perm_admin');
//                self::$data['perm_inscription'] =		session::get('perm_inscription');
//                self::$data['perm_admin_inscription'] = session::get('perm_admin_inscription');
//                self::$data['perm_convert'] =	session::get('perm_convert');
//                self::$data['perm_communications'] =	session::get('perm_communications');
//                self::$data['perm_insertion_contenu'] =	session::get('perm_insertion_contenu');
//                self::$data['perm_edit_classes'] =	session::get('perm_edit_classes');
//                self::$data['perm_defilement'] =	session::get('perm_defilement');
                self::$data['superhero']=		!!$row['superhero'];
//                self::$data['type_usager'] =	session::get('type_usager');
//                self::$data['docum_usager'] =	session::get('docum_usager');
//                self::$data['choix_db'] =		(integer) $row['choix_db'];
//                self::$data['doit_changer'] =	(integer) $row['doit_changer'];
//                self::$data['perm_admin_pub'] = (integer) session::get('perm_admin_pub');


//                if (session::get('perm_admin') or session::get('type_usager')){
//                    session::set('CKFinder_UserRole', 'admin');
//                } else{
//                    session::set('CKFinder_UserRole', 'editeur');
//                }

                self::$pseudo = $pseudo;
                self::$mdp =  $row['mot_passe'];
                $description_login[] = 'editeur';
                if ($row['perm_admin']){
                    $description_login[] = 'admin';
                }
//                if ($row['perm_inscription']){
//                    $description_login[] = 'inscription';
//                }
//
            }
//            if ($row['publiciste']){
//                $description_login[] = 'publiciste';
//            }
            if ($row['officiel']){
                $description_login[] = 'officiel';
            }
            $description_login[] = '[' . $_SERVER['REMOTE_ADDR'] . ']';

            $description_login = implode(', ', $description_login);

//            self::$data['perm_horaire_resultats'] = perm::get_perm_horaire_resultats($row['id']);
//            session::set('perm_horaire_resultats', self::$data['perm_horaire_resultats']);
            session::set('login_refreshed', time());

            event_log::add('login', 'login', $description_login);

            return true;
        }
        event_log::add('login', 'echec', "pseudo = $pseudo ; [{$_SERVER['REMOTE_ADDR']}]");
        return false;
    }

    function fn_logout(){

        self::logout();

        self::succes();

    }


    static function logout(){
        //debug_print_once("logout");
        /*		ob_start();
                debug_print_backtrace();
                $a = ob_get_contents();
                ob_end_clean();
                file_put_contents(dirname(__FILE__) . '/../logs/debug/debug_info_all_users.txt', $a, FILE_APPEND);
                */
        if (session::get('id_visiteur')){
            event_log::add('login', 'log out', 'Nom = ' . session::get('nom_visiteur'));
        }
        session::un_set('id_visiteur');
        session::un_set('nom_visiteur');
        session::un_set('statut');
        session::un_set('officiel');
//        session::un_set('admin_photo');
//        session::un_set('docum_usager');

        session::set('publiciste', 0);
        session::set('id_msg', '');
        session::set('id_editeur', 0);
//        session::set('choix_db', 0);
        session::set('superhero', 0);
//        session::set('perm_structure', 0);
        session::set('perm_admin', 0);
//        session::set('perm_inscription', 0);
//        session::set('perm_admin_inscription', 0);
//        session::set('perm_insertion_contenu', 0);
//        session::set('perm_edit_classes', 0);
//        session::set('perm_defilement', 0);
//        session::set('perm_convert', 0);
//        session::set('perm_communications', 0);
//        session::set('perm_admin_pub',0);
//        session::un_set('code_formulaire_benevole');
//        session::un_set('id_formulaire_benevole');
        session::un_set('id_annonceur');
        session::un_set('nom_annonceur');
        session::un_set('mdp');
        session::un_set('pseudo');

        session::set('perm_resultats',0);
        session::set('perm_horaire',0);
        session::set('perm_controleur',0);
        session::set('is_marqueur',0);
        session::set('is_arbitre',0);


        session::un_set('CKFinder_UserRole');
        self::$pseudo = 'toto';

    }

    static function clean_up(){


        if (!session::get('id_visiteur') or !session::get('nom_visiteur')){
            self::logout();
            return;
        }

        if (!session::get('id_editeur')){
            foreach(array(
                        array('id_msg', ''),
                        array('id_editeur', 0),
//                        array('choix_db', 0),
                        array('superhero', 0),
                        array('perm_structure', 0),
                        array('perm_admin', 0),
//                        array('perm_admin_pub', 0),
//                        array('perm_inscription', 0),
//                        array('perm_admin_inscription', 0),
//                        array('perm_insertion_contenu',0),
//                        array('perm_edit_classes',0),
//                        array('perm_defilement',0),
//                        array('perm_convert', 0),
//                        array('perm_communications', 0),
//                        array('code_formulaire_benevole', null),
//                        array('id_formulaire_benevole', null),
//                        array('tout_montrer', null),
                        array('mdp', null)
                    ) as $param){
                if (!is_null($param[1])){
                    session::set($param[0], $param[1]);
                } else{
                    session::un_set($param[0]);
                }
            }
        }

    }
    static function logged_in(){
        if (!session::test_set('id_visiteur')){
            return false;
        }
        $id = session::get('id_visiteur');
        if (!is_numeric($id) or !$id){
            return false;
        }
        return true;
    }

    static function testMD5($stored_val, $md5, $salt){
        $salted_md5 = md5("$salt$stored_val$salt");
        $salted_md5 = substr(substr($salted_md5, -((10*($salt/10-floor($salt/10))))) . $salted_md5, 0, strlen($salted_md5));
        debug_print_once("Compare $stored_val ; $md5; $salt result = $salted_md5" );
        return $md5 == $salted_md5;
    }

    function fn_test_pub()
    {
        /**
         * @var int $mode
         **/
        extract(self::check_params(
            'mode;unsigned;max:1'
        ));
        if (!perm::test('admin_pub,publiciste')){
            session::set('pub_test', false);
        } else {
            session::set('pub_test', $mode?true:false);
        }
        self::$data['LK'] = ['pub_test'=> $mode?true:false];
        $this->succes();
    }
    static function test_self($id)
    {
        return $id == session::get('id_visiteur');
    }


    static function scramble($pw)
    {

        $session = session::id();
//    echo "\n" . $session . date('Ymd') . chr(strlen($session) + 8) . "\n";

        $str = '';
        $f = P::pipe(
            'md5'
            , 'str_split'
            , P::prepend(str_split(cfg_yml::get('general', 'salt')))
            , 'flatten'
            , P::implode('')
            , 'sha1'
            , 'str_split'

            , P::append(str_split($session . date('Ymd') . chr(strlen($session) + 8)))
            , 'flatten'
            , P::map(function ($v) {
            return ord($v);
        })
            , P::prepend(P::times(function () {
            return mt_rand(0, 255);
        }, 16))
            , 'flatten'
            , P::map(function ($v, $ind, $list) {
            if ($ind < 16) {
                return sprintf("%'03u", $v);
            } else {
                $rang = $ind % 16;
                $pair = !($rang % 2);
                $mask = $list[$rang];
                $a = sprintf("%'03u", (int)($pair ? $mask << 1 & 255 ^ $v : (($mask >> 1)) ^ $v));

//            echo "\n scr  $v  avec $mask =>" . $a;

                return $a;
            }

        })
            , P::implode('')
        );

        $res = $f($pw);
        return $res;
    }


    static function unscramble_pw($pw)
    {
        if (is_string($pw)) {
            $f = P::pipe(
                'str_split'
                , P::reduce(
                function ($res, $v, $i) {
                    if (!($i % 3)) {
                        $res[] = (int)$v;
                    } else {
                        $res[] = 10 * array_pop($res) + (int)$v;
                    }
                    return $res;
                },
                []
            )
            );
            $pw = $f($pw);
        }
        $shift_left = true;
        $pw_part = array_slice($pw, 16);
        $key_source = array_slice($pw, 0, 16);

        $password = '';

        $ind = 0;
        while (count($pw_part)) {
            $char = array_shift($pw_part);
            $key = array_shift($key_source);
            $key_source[] = $key;

            if ($shift_left) {
                $key = ($key << 1) & 255;
            } else {
                $key = ($key >> 1);
            }

            $shift_left = !$shift_left;
            $decoded = chr($char ^ $key);
            $ind++;
            $password .= $decoded;
        }

        $nb_added = ord(substr($password, -1, 1)) + 1;
        $date = substr($password, -9, 8);
        $session_id = substr($password, -$nb_added, $nb_added - 9);
        $password = substr($password, 0, strlen($password) - $nb_added);

        if ($date !== date('Ymd') or $session_id !== session::id()) {
            debug_print_once("$date <> " . date('Ymd') . " ou $session_id <> " . session_id());
            if (unitTesting::$testing) {
                if ($date !== date('Ymd')) {
                    throw new Exception('date incorrecte ' . $date);
                } else {
                    throw new Exception('session id incorrect (' . session::id() . ' vs ' . $session_id . ')');
                }
            }
            return false;
        }

        return $password;
    }
}


