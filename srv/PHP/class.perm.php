<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

use Phamda\Phamda as P;

/**
 * Description of perm
 *
 * @author michel
 */
class perm
{

    static $last_msg = '';
    static $last_ref = '';

    static $result;
    static $flds = array();
    static $fld_err = false;
    static $test_result;

    static $perm_horaire_resultats = array();

    static $champs_editeurs = array( // [[nom_champ, dans_session?], [...],...]
        'perm_structure' => 0,
        'perm_ajout_document' => 0,
        'perm_admin' => 0,
        'perm_inscription' => 0,
        'perm_admin_inscription' => 0,
        'perm_convert' => 0,
        'perm_tout_contenu' => 0,
        'perm_tout_publier' => 0,
        'perm_insertion_contenu' => 0,
        'perm_communications' => 0,
        'perm_edit_classes' => 0,
        'perm_defilement' => 0,
        'perm_securite_inscription_ev' => 0,
        'perm_pratiques' => 0,
        'perm_pratiques_maitre' => 0,
        'perm_dispo_ress' => 0,
        'perm_alloc_ress' => 0,
        'id_membre' => 0,
        'inactif' => 0,
        'superhero' => 0,
        'choix_db' => 0,
        'type_usager' => 0
    );
    static $perm_resp_un_niveau = null;
    static $perm_resp_niveau = array();
    static $equipe_division = array();

    static function default_self(&$membre = 0)
    {
        if (!$membre) {
            $membre = session::get('id_visiteur');
        }
        return $membre;
    }

    static function fld($fld, $membre = 0)
    {
        self::$fld_err = false;
        if (!self::default_self($membre)) {
            return false;
        }
        if (!self::load_flds($membre)) {
            return false;
        }
        if (array_key_exists($fld, self::$flds[$membre])) {
            return self::$flds[$membre][$fld];
        }
        return false;

    }

    static function flds($membre = 0)
    {
        if (!self::load_flds($membre)) {
            return false;
        }
        return self::$flds[$membre];
    }

    static function load_flds($membre = 0, $force = false)
    {

        if (!self::default_self($membre)) {
            return false;
        }

        if (!$force and array_key_exists($membre, self::$flds) and is_array(self::$flds[$membre]) and count(self::$flds[$membre])) {
            return true;
        }
        $res = db::query("
			select *
			FROM editeurs
			WHERE id_membre = $membre
		");
        if (!$res) {
            return http_json::conditional_fin('acces_table', 'lecture permissions');
        }
        if ($res->num_rows) {
            self::$flds[$membre] = $res->fetch_assoc();
            return true;
        }
        // valeurs de défaut si pas un éditeur
        self::$flds[$membre] = self::$champs_editeurs;
        return true;

    }

    static function force_load_flds($membre = 0)
    {
        return self::load_flds($membre, true);
    }

    static function admin_photo()
    {
        return session::test_set('admin_photo') and session::get('admin_photo') >= 100;
    }


    private static function err($msg = '', $ref = '')
    {
        self::$last_msg = $msg;
        self::$last_ref = $ref;
    }

    public static function test($perm, $membre = 0)
    {
        self::err(); // effacer msgs
        if (!is_null(self::$test_result)) {
            return self::$test_result;
        }

        $id_visiteur = self::default_self($membre);

        if (!$id_visiteur) {
            self::err('ouvrez_session');
            return false;
        }

        if (strpos($perm, ',') !== false) {
            $perms = explode(',', $perm);
            foreach ($perms as $p) {
                if (self::test(trim($p), $id_visiteur)) {
                    return true;
                }
            }
            return false;
        }


        $perm = self::normaliser_perm($perm);

        $flds = self::flds($id_visiteur);

        if ($flds === false) {
            return false;
        }

        if ($flds['superhero']) {
            return true;
        }
        if (array_key_exists($perm, $flds)) {

            return !$flds['inactif'] and $flds[$perm];
        }

        switch ($perm) {
            case 'officiel':
                return session::get('officiel');
            case 'gerant':
                return self::est_gerant($id_visiteur);
            case 'editeur':
                // champ id_membre sera non null pour un éditeur
                return $flds['id_membre'] ? 1 : 0;
            case 'publiciste':
                //debug_print_once("va retourner " . (session::get('id_visiteur') and session::get('publiciste')));
                return session::get('id_visiteur') and session::get('publiciste');
            case 'perm_admin_pub':
                return session::get('perm_admin_pub');
            case 'controleur_niveau':
                $id = session::get('id_visiteur');
                /**
                 * @var int $nb
                 **/
                $res = db::query("
					SELECT COUNT(*) nb
					FROM permissions_niveaux
					WHERE id_membre = $id AND controleur IS NOT NULL AND controleur > now()
				");
                if (!$res) {
                    return http_json::conditional_fin('acces_table', 'permission controleur niveau');
                }
                extract($res->fetch_assoc());
                return ($nb > 0);
            case 'perm_securite_inscription_ev':
            case 'effacer_donnees_ev_inscr':
                return session::get('perm_securite_inscription_ev');
            case 'perm_pratiques':
                return session::get('perm_pratiques');
            case 'perm_pratiques_maitre':
                return session::get('perm_pratiques_maitre');
            case 'perm_dispo_ress':
                return session::get('perm_dispo_ress');
            case 'perm_alloc_ress':
                return session::get('perm_alloc_ress');
        }

        http_json::fin('mauvaise permission', $perm);

        return false;
    }

    static function est_capitaine_de_joueur($id_joueur, $id = null)
    {
        if (is_null($id)){
            $id = session::get('id_visiteur');
        }
        if (!$id){
            return false;
        }
        /**
         * @var int $nb
         **/
        $res = db::query("
            SELECT COUNT(*) nb
            FROM role_equipe re
            JOIN joueur_equipe je USING(id_equipe)
            WHERE re.id_adulte = $id AND re.role = 0 AND je.id_joueur = $id_joueur
        ",             'acces_table', '');
        extract($res->fetch_assoc());
        return $nb > 0;
    }
    static function marqueur_match($id_match, $strict = false)
    {
        if (!$strict and perm::test('admin')){
            return true;
        }

        $id_visiteur = session::get('id_visiteur');
        if (!$id_visiteur){
            return false;
        }
        /**
         * @var $marqueur int
         * @var $div1 string
         * @var $cl1 string
         * @var $div2 string
         * @var $cl2 string
         */
        $res = db::query("
            SELECT marqueur, n1.categ div1, n1.classe cl1, n2.categ div2, n2.classe cl2
            FROM stats_matchs sm
            JOIN equipes eq1 ON sm.id_equipe1 = eq1.id_equipe
            JOIN niveaux n1 ON eq1.niveau = n1.niveau
            JOIN equipes eq2 ON sm.id_equipe2 = eq2.id_equipe
            JOIN niveaux n2 ON eq2.niveau = n2.niveau
            WHERE sm.id = $id_match
        ");

        extract($res->fetch_assoc());
        if ($marqueur == $id_visiteur){
            return true;
        }

        #debug_print_once("va tester div $div1 cl $cl1");

        if(self::perm_resultats($div1, $cl1, $id_visiteur, $strict)){
            return true;
        }
        if ($div1 != $div2 or $cl1 != $cl2){
            return self::perm_resultats($div2, $cl2, $id_visiteur, $strict);
        }
        return false;

    }


    static function is_gerant($id_membre = null) {
        if (is_null($id_membre)) {
            $id_membre = session::get('id_visiteur');
        }
        if (!$id_membre) {
            return false;
        }
        $res = db::query("
                SELECT COUNT(*) nb
                FROM roles_courants
                WHERE id_adulte = $id_membre AND role = 0
            ", 'acces_table');
        /**
         * @var int $nb
         **/
        extract($res->fetch_assoc());
        return $nb > 0;

    }

    static function is_entraineur($id_membre = null) {
        if (is_null($id_membre)) {
            $id_membre = session::get('id_visiteur');
        }
        if (!$id_membre) {
            return false;
        }
        $res = db::query("
                SELECT COUNT(*) nb
                FROM roles_courants
                WHERE id_adulte = $id_membre AND role = 1
            ", 'acces_table');
        /**
         * @var int $nb
         **/
        extract($res->fetch_assoc());
        return $nb > 0;
    }

    static function normaliser_perm($perm)
    {
        $perm = preg_replace('#^perm_#', '', $perm);
        switch ($perm) {
            case 'structure':
            case 'edit_struct':
                return 'perm_structure';
            case 'add_document':
            case 'ajout_document':
                return 'perm_ajout_document';
            case 'admin':
                return 'perm_admin';
            case 'admin_pub':
                return 'perm_admin_pub';
            case 'inscription':
                return 'perm_inscription';
            case 'admin_inscription':
                return 'perm_admin_inscription';
            case 'convert':
                return 'perm_convert';
            case 'communications':
                return 'perm_communications';

            case 'perm_securite_inscription_ev':
            case 'securite_inscription_ev':
                return 'effacer_donnees_ev_inscr';

            case 'edit_all':
            case 'tout_contenu':
                return 'perm_tout_contenu';
            case 'tout_publier':
                return 'perm_tout_publier';
            case 'insertion_contenu':
                return 'perm_insertion_contenu';
            case 'edit_classes':
                return 'perm_edit_classes';
            case 'defilement':
                return 'perm_defilement';
            case 'pratiques':
                return 'perm_pratiques';
            case 'pratiques_maitre':
                return 'perm_pratiques_maitre';
            case 'dispo_ress':
                return 'perm_dispo_ress';
            case 'alloc_ress':
                return 'perm_alloc_ress';
            default:
                return $perm;
        }
    }

    static public function test_perms()
    {
        if (!login_visiteur::logged_in()) {
            return 'ouvrez_session';
        }
        $nb_tests = func_num_args();
        for ($i = 0; $i < $nb_tests; $i++) {
            if (perm::test(func_get_arg($i))) {
                return true;
            }
        }
        return false;
    }

    static function editer_pratiques($id_equipe)
    {
        if (self::test('perm_pratiques')) {
            return true;
        }

        if (!login_visiteur::logged_in()) {
            return false;
        }

        /**
         * @var int $nb
         **/
        $res = db::query("
                SELECT COUNT(*) nb
                FROM role_equipe
                WHERE id_equipe = $id_equipe AND role <= 1
            ", 'acces_table');
        extract($res->fetch_assoc());
        if ($nb) {
            return true;
        }
        return self::resp_niveau_equipe($id_equipe);

    }

    static function editer_pratiques_division($id_division = null)
    {
        if (self::test('perm_pratiques')) {
            return true;
        }

        if (!login_visiteur::logged_in()) {
            return false;
        }
        if (!$id_division) {
            return false;
        }

        return self::resp_niveau($id_division);

    }

    /**
     *
     * @param int $id_document
     * @param int $id_contenu
     * @param array $liste_contenus_editables
     * @param int $id_editeur
     * @return boolean = permission ou non d'éditer contenu spécifié
     *
     * attention: $liste_contenus_editables est passé par référence
     */

    static function est_gerant($id)
    {
        $res = db::query("
			SELECT id_adulte
			FROM roles_courants rc
			WHERE id_adulte = $id and role = 0
			
		",
            'acces_table', 'verification role');
        self::$result = array();
        if ($res->num_rows == 0) {
            return false;
        }
        while ($row = $res->fetch_assoc()) {
            self::$result[] = $row['id_adulte'];
        }
        return (count(self::$result) > 0);

    }

    // vérifie si $teste est gérant de $id_joueur_ou_parent
    public static function est_gerant_de($teste, $id_joueur_ou_parent)
    {
        /**
         * @var int $nb
         **/
        $res = db::query("
			SELECT count(*) nb
			FROM roles_courants roles
			JOIN joueur_equipe je USING(id_equipe)
			JOIN membres joueur ON joueur.id = je.id_joueur
			LEFT JOIN rel_parent par ON joueur.id = par.id_enfant
			WHERE roles.id_adulte = $teste AND roles.role = 0 AND (joueur.id = $id_joueur_ou_parent OR par.id_parent = $id_joueur_ou_parent)
		",
            'acces_table', 'verification statut gerant');
        extract($res->fetch_assoc());
        if ($nb > 0) {
            return true;
        }
        // vérifier si la personne est gérant d'une équipe où la personne testée est un autre officiel de rang inférieur
        $res = db::query("
			SELECT COUNT(*) nb
			FROM roles_courants roles
			JOIN roles_courants roles2 USING(id_equipe)
			WHERE roles.id_adulte = $teste AND roles.role = 0 AND roles2.id_adulte = $id_joueur_ou_parent AND (roles2.role > 0 OR roles2.id_adulte = $teste) 
		",
            'acces_table', 'verification statut gérant 2');
        extract($res->fetch_assoc());
        return ($nb > 0);
    }

    public static function est_resp_niveau_de($teste, $id_joueur_ou_parent_ou_officiel)
    {
        $res = db::query("
            SELECT categ
            FROM permissions_niveaux
            WHERE id_membre = $teste AND controleur > NOW()
		", 'acces_table', '');
        $liste_categs = implode(',', db::sql_str(db::result_array_values_one($res)));

        if (!$liste_categs) {
            return false;
        }

        /**
         * @var int $nb
         **/
        $res = db::query("
            SELECT COUNT(*) nb
            FROM joueur_equipe je
            JOIN equipes_courantes e USING(id_equipe)
            JOIN niveaux n USING(niveau)
            LEFT JOIN rel_parent par ON je.id_joueur = par.id_enfant
            LEFT JOIN role_equipe re USING(id_equipe)
            WHERE (je.id_joueur = $id_joueur_ou_parent_ou_officiel 
                OR par.id_parent = $id_joueur_ou_parent_ou_officiel
                OR re.id_adulte = $id_joueur_ou_parent_ou_officiel
                    )
                AND n.categ IN ($liste_categs)
            LIMIT 1
		", 'acces_table', '');
        extract($res->fetch_assoc());
        return ($nb > 0);
    }

    public static function officiel_de($id_equipe)
    {

        //debug_print_once(print_r($S, true));
        if (!self::test('officiel')) {
            return false;
        }
        $id_visiteur = session::get('id_visiteur');
        if (!$id_visiteur) {
            return false;
        }

        if (self::resp_niveau_equipe($id_equipe)) {
            return true;
        }

        $res = db::query(("

			SELECT count(*) nb
			FROM roles_courants
			WHERE id_equipe = 0$id_equipe and id_adulte = $id_visiteur

		"));
        if (!$res) {
            return http_json::conditional_fin('acces_table', "SELECT... FROM role equipe");
        }
        $row = $res->fetch_assoc();
        return ($row['nb'] > 0);
    }

    public static function gerant_de($id_equipe)
    {
        $id_visiteur = session::get('id_visiteur');

        /**
         * @var int $nb
         **/
        $res = db::query("
			SELECT count(*) nb
			FROM role_equipe
			WHERE
				id_equipe = $id_equipe
				AND id_adulte = $id_visiteur
				AND role = 0
		");
        if (!$res) {
            return http_json::conditional_fin('acces_table', "gerant de...");
        }
        extract($res->fetch_assoc());
        return $nb;
    }

    public static function set_perm($type, $data = true, $min = 30)
    {
        session::set('permissions', $type, array(
            'ip' => $_SERVER['REMOTE_ADDR'],
            'exp' => time() + $min * 60,
            'data' => $data
        ));
    }

    public static function reset_perm($type)
    {
        session::un_set('permissions', $type);
    }

    public static function check_perm($type)
    {
        if (!session::test_set('permissions', $type)) {
            return false;
        }
        $perm = session::get('permissions', $type);
        if (!is_array($perm) or !array_key_exists('ip', $perm) or !array_key_exists('exp', $perm) or !array_key_exists('data', $perm)) {
            return false;
        }
        if ($perm['ip'] != $_SERVER['REMOTE_ADDR']) {
            return false;
        }
        if ($perm['exp'] < time()) {
            return false;
        }
        return $perm['data'];
    }

    public static function joueur_de($id_equipe)
    {
        $id_visiteur = session::get('id_visiteur');
        if (!$id_visiteur or !is_numeric($id_visiteur)) {
            return false;
        }
        /**
         * @var int $nb
         **/
        $res = db::query("
			SELECT count(*) nb
			FROM joueur_equipe
			WHERE id_joueur = $id_visiteur AND id_equipe = $id_equipe
		");
        if (!$res) {
            return http_json::conditional_fin('acces_table', 'perm joueur de');
        }
        extract($res->fetch_assoc());
        return ($nb ? true : false);
    }

    public static function membre_de($id_equipe)
    {
        $id_visiteur = session::get('id_visiteur');
        if (!$id_visiteur or !is_numeric($id_visiteur)) {
            return false;
        }

        /**
         * @var int $nb
         **/
        $res = db::query("
			SELECT count(*) nb
			FROM equipes eq
			LEFT JOIN joueur_equipe je USING(id_equipe)
			LEFT JOIN rel_parent par ON je.id_joueur = par.id_enfant
			LEFT JOIN role_equipe re USING(id_equipe)
			
			WHERE je.id_equipe = $id_equipe
				AND (
				je.id_joueur = $id_visiteur
				OR
				par.id_parent = $id_visiteur
				OR
				re.id_adulte = $id_visiteur
				)
		");
        if (!$res) {
            return http_json::conditional_fin('acces_table', 'perm membre de');
        }
        extract($res->fetch_assoc());
        return ($nb ? true : false);

    }

    public static function get_perm_horaire_resultats($id_membre = null)
    {
        if (!is_numeric($id_membre)) {
            if (!login_visiteur::logged_in()) {
                return array();
            }
            $id_membre = session::get('id_visiteur');
        }

        if (array_key_exists($id_membre, self::$perm_horaire_resultats)) {
            return self::$perm_horaire_resultats[$id_membre];
        }

        $res = db::query("
			SELECT categ, 
				classe, 
				if(horaire>= now() OR (@a := (controleur >= now())), 1, 0) perm_horaire,
				if(resultats >= now() OR @a, 1, 0) perm_resultats,
				@a perm_controleur
			FROM permissions_niveaux
			LEFT JOIN rang_niveau rn USING(categ)
			LEFT JOIN classes cl USING(classe)
			WHERE id_membre = $id_membre
			ORDER BY rn.rang, cl.ordre
		");
        if (!$res) {
            return http_json::conditional_fin('acces_table', 'get perm horaires/resultats');
        }

        return self::$perm_horaire_resultats[$id_membre] = db::result_array($res);
    }

    public static function div_cl_equipe($id_equipe)
    {
        $res = db::query("
            SELECT n.categ, n.classe
            FROM equipes e
            JOIN niveaux n USING(niveau)
            WHERE e.id_equipe = $id_equipe
        ",             'acces_table', '');
        if ($res->num_rows == 0){
            array(false, false);
        }
        /**
         * @var string $categ
         * @var string $classe
         **/
        extract($res->fetch_assoc());
        return [$categ, $classe];
    }


    public static function perm_resultats($div, $cl, $id_membre = null, $strict = false)
    {
        if (perm::test('admin') and !$strict){
            return true;
        }
        if (is_null($id_membre)){
            $id_membre = session::get('id_visiteur');
        }
        $perms = self::get_perm_horaire_resultats($id_membre);

        foreach($perms as $perm){
            #debug_print_once(print_r($perm,1));
            #debug_print_once("div $div cl $cl");
            if ($perm['categ'] == $div
                and
                (
                    is_null($perm['classe'])
                    or $perm['classe'] == $cl
                )
                and     (
                    $perm['perm_resultats']
                    or $perm['perm_controleur']
                )
            ){
                return true;
            }

        }
        #debug_print_once("--------------");
        return false;

    }
    public static function perm_resultats_eq($id_equipe, $id_membre = null)
    {
        list($div,$cl) = self::div_cl_equipe($id_equipe);
        if (!$div){
            return false;
        }
        return self::perm_resultats($div, $cl, $id_membre);
    }

    static function resp_niveau($id_division = null, $op = null)
    {
        try {

            if (is_null($id_division)) {

                if (!is_null(self::$perm_resp_un_niveau)) {
                    return self::$perm_resp_un_niveau;
                }
                $id_visiteur = session::get('id_visiteur');
                /**
                 * @var int $nb
                 **/
                $res = db::query("
                    SELECT COUNT(*) nb
                    FROM permissions_niveaux pn
                    WHERE pn.id_membre = $id_visiteur 
                    AND pn.controleur IS NOT NULL AND pn.controleur > NOW()
                ", 'acces_table', '');
                extract($res->fetch_assoc());
                return self::$perm_resp_un_niveau = ($nb > 0);
            }

            if (array_key_exists($id_division, self::$perm_resp_niveau)) {
                $perm = self::$perm_resp_niveau[$id_division];
                if (!$perm['perm']) {
                    return false;
                }
                if (is_null($op)) {
                    return $perm['perm'];
                }
                if (!array_key_exists($op, $perm)) {
                    return false;
                }
                return $perm[$op];
            }


            $id_visiteur = session::get('id_visiteur');
            if (!$id_visiteur or !is_numeric($id_visiteur)) {
                return false;
            }
            if (!preg_match('#^[1-9]\d*$#', $id_division)) {
                return false;
            }
            /**
             * @var int $nb
             * @var int $inscription
             **/
            $res = db::query("
                SELECT COUNT(*) nb, COUNT(IF(inscr_par_responsable,1,null)) inscription
                FROM permissions_niveaux pn
                JOIN rang_niveau rn USING(categ)
                WHERE pn.id_membre = $id_visiteur 
                    AND pn.controleur IS NOT NULL AND pn.controleur > NOW()
                    AND rn.id = $id_division
            ",
                'acces_table', 'permission resp_niveau');
            extract($res->fetch_assoc());


            self::$perm_resp_niveau[$id_division]['perm'] = ($nb > 0);
            if ($op == 'inscription') {
                self::$perm_resp_niveau[$id_division][$op] = ($inscription > 0);
            }

            if (is_null($op)) {
                return ($nb > 0);
            }
            if ($op == 'inscription') {
                return ($nb > 0 and $inscription > 0);
            }
            return false;
        } catch (Exception $e) {
            debug_print_once('Exception permission resp niveau ' . $e->getMessage());
            return false;
        }
    }

    static function resp_niveau_equipe($id_equipe, $op = null)
    {
        try {
            if (array_key_exists($id_equipe, self::$equipe_division)) {
                if (self::$equipe_division[$id_equipe] === false) {
                    return false;
                }
                #debug_print_once("trouvé $id_equipe ");
                return self::resp_niveau(self::$equipe_division[$id_equipe], $op);
            }

            /**
             * @var int $id_division
             * @var int $id_saison
             **/
            $res = db::query("
                SELECT e.id_saison, rn.id id_division
                FROM equipes e
                JOIN niveaux n USING(niveau)
                JOIN rang_niveau rn USING(categ)
                WHERE e.id_equipe = $id_equipe
            ", 'acces_table', '');
            if ($res->num_rows == 0) {
                #debug_print_once("pas de division pour équipe $id_equipe car pas de saison ni de div");
                self::$equipe_division[$id_equipe] = false;
                return false;
            }
            extract($res->fetch_assoc());

            $res = db::query("
                SELECT id_equipe
                FROM equipes e
                JOIN niveaux USING(niveau)
                JOIN rang_niveau rn USING(categ)
                WHERE rn.id = $id_division AND e.id_saison = $id_saison
            ", 'acces_table', '');

            if ($res->num_rows == 0) {
                #debug_print_once("pas trouvé d'équipes dans saison $id_saison ");
                self::$equipe_division[$id_equipe] = false;
                return false;
            }
            while ($row = $res->fetch_assoc()) {
                self::$equipe_division[$row['id_equipe']] = $id_division;
            }

            #debug_print_once(print_r(self::$equipe_division,1));

            return self::resp_niveau($id_division, $op);
        } catch (Exception $e) {
            debug_print_once("Erreur permission resp niveau equipe = " . $e->getMessage());
            return false;
        }

    }

//    static function ids_contenus_editables($id_document, $id_editeur = 0)
//    {
//        self::default_self($id_editeur);
//
//        if (!$id_editeur or !is_numeric($id_editeur)) {
//            return [];
//        }
//
//        $obj = new contenus_editables($id_editeur, $id_document);
//
//        return $obj->ids_perm_edit;
//    }

//    static function contenu_editable($id_contenu, $id_editeur = null) {
//        if (is_null($id_editeur)) {
//            $id_editeur = session::get('id_editeur');
//        }
//        if (!$id_editeur) {
//            return false;
//        }
//
//        $res = db::query("
//                SELECT id_
//            ", 'acces_table');
//
//    }

}

class row
{
    static $list = [];
    static $id_editeur;
    static $resp_niveaux = [];
    static $resp_div = [];
    static $roles_0 = [];
    static $roles_1 = [];
    static $div_document;
    static $cl_document;
    static $eq_document;

    static $ids_contenus = [];

    public $row;
    public $perm_edit = false;


    function __construct($row)
    {
        debug_print_once(print_r($row, 1));
        $this->row = $row;
        self::$list[$row['element_structure']] = $this;
        $this->adjust_context();

        if ($row['id_contenu'] and $this->match_context() and !in_array($row['id_contenu'], self::$ids_contenus)) {
            self::$ids_contenus[] = $row['id_contenu'];
        }
    }

    static function set_root_context($div, $cl, $eq)
    {
        list(self::$div_document, self::$cl_document, self::$eq_document) = [$div, $cl, $eq];
    }

    public function adjust_context()
    {
        if ($this->parent) {
            $this->div = $this->div ?? $this->parent->div;
            $this->cl = $this->cl ?? $this->parent->cl;
            $this->eq = $this->eq ?? $this->parent->eq;
        } else {
            $this->div = $this->div ?? self::$div_document;
            $this->cl = $this->cl ?? self::$cl_document;
            $this->eq = $this->eq ?? self::$eq_document;
        }

//        debug_print_once('contexte ajusté ' . $this->row['element_structure']. ' div ' . $this->div . '; cl:' . $this->cl . '; eq: ' . $this->eq );
    }

    function match_context()
    {
        $debug = false;
//        if ($this->row['element_structure'] == 9906){
//            $debug = true;
//        }
        if (!$this->div) {
            if ($debug) {
                debug_print_once('pas div');
            }
            return false;
        }
        if ($this->perm_controleur and in_array($this->div, self::$resp_div)) {
            if ($debug) {
                debug_print_once('controleur ok');
            }
            return true;
        } else {
            if ($this->row['element_structure'] == 9906) {
                debug_print_once('controleur PAS ok');
                debug_print_once('perm controleur = ' . ($this->perm_controleur ? 'OUI' : 'NON'));
                debug_print_once('this div = ' . $this->div);
                debug_print_once('resp div ' . print_r(self::$resp_div, 1));
            }

        }
        $context_cl = $this->context_cl;
        if (!$context_cl) {
            return false;
        }
        if ($this->perm_controleur and in_array($context_cl, self::$resp_niveaux)) {
            return true;
        }
        $context_eq = $this->context_eq;
        if (!$context_eq) {
            return false;
        }
        if ($this->perm_role_0 and in_array($context_eq, self::$roles_0)) {
            return true;
        }
        if ($this->perm_role_1 and in_array($context_eq, self::$roles_1)) {
            return true;
        }
        return false;
    }

    function __get($name)
    {
        switch ($name) {
            case 'has_parent':
                return $this->row['parent'];
            case 'parent':
                if ($this->row['parent']) {
                    return $this->list[$this->row['parent']];
                } else {
                    return null;
                }
                break;
            case 'div':
                return $this->row['contexte_division'];
            case 'cl':
                return $this->row['contexte_classe'];
            case 'eq':
                return $this->row['contexte_equipe'];
            case 'perm_controleur':
                return $this->row['perm_controleur'];
            case 'perm_role_0':
                return $this->row['perm_role_0'];
            case 'perm_role_1':
                return $this->row['perm_role_1'];
            case 'context_div':
                return $this->div;
            case 'context_cl':
                if (!$this->div or !$this->cl) return null;
                return $this->div . '-' . $this->cl;
            case 'context_eq':
                if (!$this->div or !$this->cl or !$this->eq) return null;
                return $this->div . '-' . !$this->cl . '-' . !$this->eq;

        }
        return null;
    }

    function __set($nom, $val)
    {
        switch ($nom) {
            case 'div':
                $this->row['contexte_division'] = $val;
                return;
            case 'cl':
                $this->row['contexte_classe'] = $val;
                return;
            case 'eq':
                $this->row['contexte_equipe'] = $val;
                return;

        }
    }


//    static public function reset($id_editeur)
//    {
//        self::$id_editeur = $id_editeur;
//        self::$list = [];
//        self::$roles_0 = [];
//        self::$roles_1 = [];
//        self::$resp_niveaux = [];
//        self::$resp_div = [];
//        self::$ids_contenus = [];
//
//        $res = db::query("
//                SELECT CONCAT_WS('-', eq.division, eq.classe, eq.id_nom_std) context, MAX(re.role) role
//                FROM equipes eq
//                JOIN roles_courants re USING(id_equipe)
//                WHERE re.id_adulte = $id_editeur
//                GROUP BY eq.id_equipe
//            ", 'acces_table');
//
//        if ($res->num_rows) {
//            $liste = db::result_array($res);
//            self::$roles_0 = array_map(function ($v) {
//                return $v['context'];
//            }, array_filter($liste, function ($v) {
//                return $v == '0';
//            }));
//            self::$roles_1 = array_map(function ($v) {
//                return $v['context'];
//            }, array_filter($liste, function ($v) {
//                return $v == '1';
//            }));
//        }
//
////        debug_print_once('gerant');
////        debug_print_once(print_r(self::$roles_0, 1));
////        debug_print_once('entraineur');
////        debug_print_once(print_r(self::$roles_1, 1));
//
//        $res = db::query("
//                SELECT CONCAT_WS('-', rn.id, cl.id) context, cl.id cl
//                FROM permissions_niveaux pn
//                LEFT JOIN rang_niveau rn USING(categ)
//                LEFT JOIN classes cl ON pn.classe = cl.id
//                WHERE id_membre = $id_editeur AND controleur > NOW()
//            ", 'acces_table');
//        if ($res->num_rows) {
//
//
//            $liste = db::result_array($res);
//
//
//            self::$resp_niveaux = array_map(function ($v) {
//                return $v['context'];
//            }, array_filter($liste, function ($v) {
//                return !is_null($v['cl']);
//            }));
//
//
//            self::$resp_div = array_map(function ($v) {
//                return $v['context'];
//            }, array_filter($liste, function ($v) {
//                return is_null($v['cl']);
//            }));
//        }
////        debug_print_once('resp niveaux');
////        debug_print_once('niveaux');
////        debug_print_once(print_r(self::$resp_niveaux, 1));
////        debug_print_once('divisions');
////        debug_print_once(print_r(self::$resp_div, 1));
//
//    }


}

//class contenus_editables
//{
//    public $id_document;
//    public $id_editeur;
//    public $perm_tout_contenu;
//    public $documents = [];
//    public $pas_editeur;
//    public $ids_perm_edit = [];
//
//    function __construct($id_editeur, $id_document)
//    {
//        $this->id_document = $id_document;
//        $this->id_editeur = $id_editeur;
//        $this->check_perm_tout_contenu();
//
//
//        $this->get_documents($this->id_document);
////        debug_print_once('-----------------------------');
////        debug_print_once(print_r($this->documents, 1));
//        $ids = $this->ids_documents_permis;
//        if ($ids) {
//            $sql_ids = implode(',', $ids);
//            if ($this->perm_tout_contenu) {
//                $res = db::query("
//                        SELECT s.id_contenu
//                        FROM structure2 s
//                        LEFT JOIN permissions_contenu pc ON s.id_contenu = pc.id_contenu AND pc.id_editeur = $id_editeur
//                        WHERE 
//                        s.id_document IN ($sql_ids) AND s.archive IS NULL AND s.id_editeur IS NULL AND 
//                        s.id_contenu IS NOT NULL AND (pc.id_contenu IS NULL OR pc.id_perm IS NULL OR pc.perm_edit_expire > NOW())
//                    ", 'acces_table');
//                if ($res->num_rows) {
//                    $this->ids_perm_edit = array_unique(array_merge($this->ids_perm_edit, db::result_array_values_one($res)));
//                }
//
//            } else {
//                $res = db::query("
//                        SELECT s.id_contenu
//                        FROM structure2 s
//                        LEFT JOIN permissions_contenu pc ON s.id_contenu = pc.id_contenu AND pc.id_editeur = $id_editeur
//                        WHERE 
//                        s.id_document IN ($sql_ids) AND s.archive IS NULL AND s.id_editeur IS NULL AND 
//                        s.id_contenu IS NOT NULL AND (pc.perm_edit_expire > NOW() OR not pc.interdit)
//                    ", 'acces_table');
//                if ($res->num_rows) {
//                    $this->ids_perm_edit = array_unique(array_merge($this->ids_perm_edit, db::result_array_values_one($res)));
//                }
////                    debug_print_once(print_r(db::result_array_values_one($res, 'id_contenu'), 1));
////                    debug_print_once(print_r($this->ids_perm_edit, 1));
//
//                // trouver les permissions contextuelles
//                row::reset($id_editeur);
//                foreach ($ids as $id) {
//                    row::set_root_context(...array_values($this->documents[$id]['contexte']));
//                    $res = db::query("
//                                SELECT element_structure, id_contenu, parent, perm_controleur, perm_role_0, perm_role_1,
//                                contexte_division,contexte_classe,contexte_equipe
//
//                                FROM structure2
//                                WHERE id_document = $id AND id_editeur IS NULL AND archive IS NULL
//                                ORDER BY ordre
//                            ", 'acces_table');
//                    if ($res->num_rows) {
//                        while ($row = $res->fetch_assoc()) {
//                            new row($row);
//                        }
//                    }
//
//                }
//                $this->ids_perm_edit = array_unique(array_merge($this->ids_perm_edit, row::$ids_contenus));
//            }
//
//        }
//
//
//    }
//
//
//    public function check_perm_tout_contenu()
//    {
//        /**
//         * @var int $perm_tout_contenu
//         **/
//        $res = db::query("
//                SELECT perm_tout_contenu
//                FROM editeurs
//                WHERE id_membre = $this->id_editeur
//            ", 'acces_table');
//
//        if ($res->num_rows) {
//            extract($res->fetch_assoc());
//            if ($perm_tout_contenu) {
//                $this->perm_tout_contenu = true;
//            }
//        } else {
//            $this->pas_editeur = true;
//        }
//
//    }
//
//    public function get_documents($id_document, $done = [], $div = null, $cl = null, $eq = null)
//    {
//        $res = db::query("
//                SELECT ld.id_child_document id_child, d.contexte_division `div`, d.contexte_classe cl, d.contexte_equipe eq
//                FROM layout_document ld
//                JOIN documents d USING(id_document)
//                WHERE ld.id_document = $id_document
//            ", 'acces_table');
//
//        if ($res->num_rows == 0) {
//            $res = db::query("
//                    SELECT d.id_document, 
//                    d.contexte_division `div`, 
//                    d.contexte_classe cl, 
//                    d.contexte_equipe eq,
//                    IF(pd.id_perm IS NOT NULL AND (pd.perm_edit_expire IS NULL OR pd.perm_edit_expire < NOW()), 1, 0) interdit_edit_document
//                    
//                    FROM documents d
//                    LEFT JOIN permissions_documents pd USING(id_document)
//                    WHERE d.id_document = $id_document
//                ", 'acces_table');
//
//            $doc = $res->fetch_assoc();
//            $done[] = $doc['id_document'];
//            $this->documents[$doc['id_document']] = [
//                'contexte' => [
//                    'div' => $div ?? $doc['div'],
//                    'cl' => $cl ?? $doc['cl'],
//                    'eq' => $eq ?? $doc['eq']
//                ],
//                'interdit' => $doc['interdit_edit_document'],
//                'id' => $doc['id_document']
//            ];
//
//        } else {
//            while ($row = $res->fetch_assoc()) {
//                $done[] = $row['id_child'];
//                $this->get_documents($row['id_child'], $done, $row['div'], $row['cl'], $row['eq']);
//            }
//        }
//
//    }
//
//    public function __get($name)
//    {
//        switch ($name) {
//            case 'ids_documents':
//                return array_keys($this->documents);
//            case 'ids_documents_permis':
//                return array_filter($this->ids_documents, function ($v) {
//                    return !$this->documents[$v]['interdit'];
//                });
//        }
//        return null;
//    }
//
//    public function list_tout_contenu()
//    {
//
//    }
//
//
//}
