<?php

use Phamda\Phamda as P;

class marqueur extends http_json
{

    public $sections = array(

        'mes_matchs' => array(),
        'info_marqueur' => null
    );
    static $erreur = '';

    public $match_perm = false; # true si une permission est basée sur la présence d'un membre comme marqueur dans stats_matchs seulement

    public $fields_marqueur = "m.id,
                CONCAT(m.nom, ', ', m.prenom) nom,
                m.courriel,
                m.tel_jour,
                m.tel_soir,
                m.cell,
                m.adr1,
                m.ville,
                m.code_postal
                ";

    static $xhr = false;


    function __construct($no_op = false)
    {
        parent::__construct();

        self::set_default_msgs('class_marqueur');


        if (!$no_op) {
            self::$xhr = true;
            self::execute_op();
        }
    }

    static function fin($msg = null)
    {

        if (self::$xhr) {
            parent::fin($msg);
        }

        self::$erreur = $msg;
        return false;
    }

    function fn_info_membre_marqueur()
    {
        /**
         * @var $id_membre int
         * @var $id_match int
         */
        extract(self::check_params(
            'id_membre;unsigned',
            'id_match;unsigned'
        ));

        $saison = saisons::courante();

        $res = db::query("
            SELECT
                sm.id id_match,
                CONCAT(e.nom, ' [', cl.classe, ']') nom_equipe,
                CONCAT(sm.date, ' ', sm.debut) date,
                IFNULL(gcl.lieu_propre, sm.lieu) lieu
            FROM match_joueurs mj
            JOIN stats_matchs sm ON sm.id = mj.id_match
            JOIN equipes e ON mj.id_equipe = e.id_equipe
            JOIN classes cl ON e.classe = cl.id
            LEFT JOIN gcal_lieux gcl ON sm.lieu = gcl.id
            WHERE DATEDIFF(NOW(), sm.date) < 30 AND mj.id_joueur = $id_membre AND mj.id_match <> $id_match
            ORDER BY sm.date DESC
		", ACCES_TABLE, '');
        self::result_to($res, 'matchs_recents');


        $res = db::query("
                SELECT COUNT(mj.id) nb, s.nom_saison
                FROM match_joueurs mj
                JOIN stats_matchs m ON mj.id_match = m.id
                JOIN saisons s ON s.id = m.saison
                WHERE mj.id_joueur = $id_membre AND mj.id_match <> $id_match
                ORDER BY s.debut DESC 
            ", ACCES_TABLE);
        self::result_to($res, 'compte_matchs_saisons');

        A::setIntEach(self::$data['compte_matchs_saisons'], ['nb']);


        $this->succes();
    }


    function fn_get_substituts()
    {
        /**
         * @var int $lettre
         **/
        extract(self::check_params(
            'lettre;string;min:1;max:1'
        ));

        $id_saison = saisons::get('courante');
        if (!$id_saison) {
            $this->fin('Pas de saison courante');
        }

        $res = db::query("
			SELECT DISTINCT
			    m.id,
			    concat(m.nom, ', ', m.prenom) nom,
			    IF(dj.position = 2, 1, 0) gardien,
			    LOWER(m.nom) nom_famille,
			    IFNULL(dj.no_chandail, '?') no_chandail,
			    m.date_naissance,
			    m.courriel,
			    m.cell
			    
			FROM membres m
            JOIN dossier_joueur dj ON dj.id_joueur = m.id AND dj.saison = $id_saison
			WHERE m.nom LIKE '$lettre%'
            AND NOT m.non_joueur
            ORDER BY m.nom, m.prenom
		");

        $liste = db::result_array($res);

        A::setBooleanEach($liste, ['gardien']);

        self::set_data('liste', $liste);
        $this->succes();
    }

    /**
     *
     * si id_marqueur est nul, alors id_marqueur = session::id_marqueur si visiteur est admin
     *
     * @param type $id_marqueur
     * @return types
     */
    function get_id_marqueur($id_marqueur = null)
    {
        $is_admin = perm::test('admin');
        $id_visiteur = session::get('id_visiteur');

        $session_marqueur = session::get('id_marqueur');

        if (is_null($id_marqueur)) {
            if ($is_admin and $session_marqueur and $session_marqueur != $id_visiteur) {
                if (!$this->is_marqueur($session_marqueur)) {
                    if (self::$xhr) {
                        $this->non_autorise();
                    }
                    return false;
                }
                return $session_marqueur;
            }
            if ($is_admin or $this->is_marqueur($id_visiteur)) {
                return $id_visiteur;
            }
            if ($this->xhr) {
                $this->non_autorise();
            }
            return false;

        }

        if (!$this->is_marqueur($id_marqueur)) {
            $this->non_autorise();
        }
        return $id_marqueur;


    }

    function fn_update()
    {
        /**
         * @var $info array
         * @var $id_marqueur int
         */
        extract(self::check_params(
            'info;array;min:1',
            'id_marqueur;unsigned;opt;default_null'
        ));

        $values = $this->update($info);


        # on ne devrait jamais avoir $values = false, mais laissé par sécurité au cas...
        if (!is_array($values)) {
            $this->fin('erreur');
        }

        self::$data = array_merge(self::$data, $values);

        self::$data['std_punitions'] = self::get_std_punitions();
        $this->succes();

    }

    static function get_std_punitions()
    {

        $res = db::query("
            SELECT description
            FROM std_punitions
            ORDER BY ordre
		", ACCES_TABLE, '');

        return db::result_array_one_value($res, 'description');
    }

    function update($info)
    {
        if (!login_visiteur::logged_in()) {
            return $this->fin('ouvrez_session');
        }
        $id_marqueur = $this->get_id_marqueur();


        return $this->get_update_data($info, $id_marqueur);

    }

    function get_update_data($info, $id_marqueur = null)
    {
        $to_ret = [];
        foreach ($info as $section) {
            switch ($section) {
                case 'mes_matchs':
                    $to_ret['mes_matchs'] = $this->get_mes_matchs($id_marqueur);
                    if (!is_array($to_ret['mes_matchs'])) {
                        return false;
                    }
                case 'info_marqueur':
                    if (!$id_marqueur or $id_marqueur == session::get('id_visiteur')) {
                        continue;
                    }
                    $to_ret['data_marqueur'] = $this->get_info_marqueur($id_marqueur);
                    if (!is_array($to_ret['data_marqueur'])) {
                        return false;
                    }
            }
        }
        return $to_ret;
    }

    function get_update_tout($id_marqueur = null)
    {
        if (is_null($id_marqueur)) {
            if (perm::test('admin') and session::get('id_marqueur')) {
                $id_marqueur = session::get('id_marqueur');
            } else if (session::get('is_marqueur')) {
                $id_marqueur = session::get('id_visiteur');
            }
        }
        if (!perm::test('admin') and (!preg_match('#^\d+$#', $id_marqueur) or !$id_marqueur)) {
            return $this->sections;
        }

        return $this->get_update_data(array_keys($this->sections), $id_marqueur);
    }

    function fn_update_un_match()
    {
        /**
         * @var $id_match int
         */
        extract(self::check_params(
            'id_match;unsigned'
        ));
        $id_marqueur = $this->get_id_marqueur();
        //$liste = $this->get_mes_matchs($id_marqueur, $id_match);
        //self::$data['data'] = (count($liste) ? $liste[0] : array());
        self::$data['data'] = $this->get_un_match($id_marqueur, $id_match);
        $this->succes();
    }

//    function get_un_match($id_marqueur, $id_match)
//    {
//        $liste = $this->get_mes_matchs($id_marqueur, $id_match);
//        return count($liste) ? $liste[0] : [];
//    }

//    function get_mes_matchs($id_marqueur, $id_match = null)
//    {
//        if (!is_null($id_match)) {
//            $where_match = "sm.id = $id_match";
//        } else {
//            $where_match = '1';
//        }
//
//        if (is_null($id_marqueur) or perm::test('admin')) {
//            $where_marqueur = "1";
//        } else {
//            $where_marqueur = "sm.marqueur = $id_marqueur";
//        }
//
//
//        $saison = saisons::courante();
//
//        $res = db::query("
//            SELECT
//                sm.id,
//                IF(CONCAT(sm.date, ' ', sm.debut) > NOW(), 1, 0) futur,
//                CONCAT(sm.date, ' ', SUBSTRING(sm.debut,1,5)) date,
//                IFNULL(gcl.lieu_propre, sm.lieu) lieu,
//                gcl.id id_lieu,
//                sm.id_equipe1,
//                sm.id_equipe2,
//                IF(eq1.division = eq2.division, rn1.categ, '') division,
//                IF(eq1.classe = eq2.classe, cl1.classe, '') classe,
//                IF(eq1.division <> eq2.division, CONCAT(eq1.nom, ' [', rn1.categ, '-', cl1.classe, ']'), IF(eq1.classe=eq2.classe, eq1.nom, CONCAT(eq1.nom, ' [', cl1.classe, ']'))) nom_equipe1,
//                IF(eq1.division <> eq2.division, CONCAT(eq2.nom, ' [', rn2.categ, '-', cl2.classe, ']'), IF(eq1.classe=eq2.classe, eq2.nom, CONCAT(eq2.nom, ' [', cl2.classe, ']'))) nom_equipe2,
//                sm.pts1,
//                sm.pts2,
//                sm.sj_ok1,
//                sm.sj_ok2,
//                sm.locked,
//                sm.forfait1,
//                sm.forfait2
//            FROM stats_matchs sm
//            LEFT JOIN gcal_lieux gcl ON gcl.id = sm.lieu
//            LEFT JOIN equipes eq1 ON sm.id_equipe1 = eq1.id_equipe
//            LEFT JOIN classes cl1 ON eq1.classe = cl1.id
//            LEFT JOIN rang_niveau rn1 ON rn1.id = eq1.division
//            LEFT JOIN equipes eq2 ON sm.id_equipe2 = eq2.id_equipe
//            LEFT JOIN classes cl2 ON eq2.classe = cl2.id
//            LEFT JOIN rang_niveau rn2 ON rn2.id = eq2.division
//            WHERE
//            TIME_TO_SEC(TIMEDIFF(CONCAT(sm.date, ' ', sm.debut), NOW()))/3600 < 4
//            AND sm.id_equipe1 AND sm.id_equipe2
//            AND $where_marqueur
//                AND ($where_match)
//                AND sm.saison = $saison
//                AND NOT sm.locked
//            ORDER BY date DESC
//
//		");
//        if (!$res) {
//            return $this->fin(ACCES_TABLE);
//        }
//        return db::result_array($res);
//    }

    function is_marqueur_match($id_match)
    {
        /**
         * @var $marqueur int
         */
        $res = db::query("
            SELECT marqueur
            FROM stats_matchs
            WHERE id = $id_match
		", ACCES_TABLE, '');
        if ($res->num_rows == 0) {
            return false;
        }
        extract($res->fetch_assoc());

        return session::get('id_visiteur') == $marqueur;
    }

    # retourne true si marqueur selon statut membre ou droits conférés
    # retourne true + $this->match_perm réglé à true si pas marqueur mais l'a été pour des matchs dans stats_matchs
    function is_marqueur($id_marqueur = null)
    {
        if (is_null($id_marqueur)) {
            $id_marqueur = session::get('id_visiteur');
        }
        if (!$id_marqueur) {
            $this->non_autorise();
        }
        /**
         * @var $nb int
         */
        $res = db::query("
            SELECT COUNT(m.id) nb
            FROM membres m
            LEFT JOIN permissions_niveaux pn ON m.id = pn.id_membre
            WHERE pn.id_membre = $id_marqueur
                AND pn.resultats IS NOT NULL
                OR m.marqueur
		", ACCES_TABLE, '');
        extract($res->fetch_assoc());
        if ($nb) {
            return true;
        }
        $res = db::query("
            SELECT COUNT(*) nb
            FROM stats_matchs
            WHERE marqueur = $id_marqueur
		", ACCES_TABLE, '');
        extract($res->fetch_assoc());
        if ($nb) {
            $this->match_perm = true;
            return true;
        }
        return false;
    }

    function get_info_marqueur($id_marqueur)
    {
        $res = db::query("
            SELECT $this->fields_marqueur
            FROM membres m
            WHERE m.id = $id_marqueur
		");
        if (!$res) {
            $this->fin(ACCES_TABLE);
        }
        if ($res->num_rows == 0) {
            return null;
        }
        return $res->fetch_assoc();
    }

    function fn_get_choix_marqueurs()
    {
        if (!perm::test('admin')) {
            $this->non_autorise();
        }
        $res = db::query("
            SELECT $this->fields_marqueur
            FROM membres m
            JOIN permissions_niveaux pn ON pn.id_membre = m.id
            WHERE pn.controleur IS NOT NULL or pn.resultats IS NOT NULL
            ORDER BY nom, prenom
            
		", ACCES_TABLE, '');
        self::$data['liste'] = db::result_array($res);
        $this->succes();
    }


    # fnct pour superhero vise à se faire passer pour un marqueur pour tester...
    function fn_choix_marqueur()
    {
        /**
         * @var $id int
         */

        extract(self::check_params(
            'id;unsigned'
        ));
        if ($id == 0) {
            session::un_set('id_marqueur');
            self::$data['data_marqueur'] = null;
            $this->succes();
        }
        /**
         * @var $nb int
         */

        $res = db::query("
            SELECT COUNT(*) nb
            FROM permissions_niveaux
            WHERE id_membre = $id AND controleur IS NOT NULL OR resultats IS NOT NULL
            
		", ACCES_TABLE, '');
        extract($res->fetch_assoc());
        if (!$nb) {
            $this->fin('non_membre_ou_non_marqueur');
        }
        session::set('id_marqueur', $id);
        $this->succes();
    }

    function fn_effacer_resultats_stats() # attention dupliqué dans horaires_manuels
    {
        /**
         * @var $ref int
         * @var boolean $stats_seulem
         */
        extract(self::check_params(
            'ref;unsigned',
            'stats_seulem;bool;opt;default_empty'

        ));

        $match = new record_stats_match($ref);

        if (!$match->load(null, 2)) {
            $this->fin('introuvable');
        }

        if (!$match->is_editable_marqueur(true) and !perm::test('admin')) {
            $this->non_autorise();
        }


        $res = db::query("
			DELETE stats_joueurs
			FROM stats_joueurs
			JOIN stats_matchs m ON stats_joueurs.id_match = m.id
			WHERE  m.id = $ref
		",
            ACCES_TABLE, ' delete stats');
        $assignments = [
            'forfait1' => 0,
            'forfait2' => 0,
            'sj_ok1' => 0,
            'sj_ok2' => 0,
        ];
        if (!$stats_seulem) {
            $assignments['pts1'] = null;
            $assignments['pts2'] = null;
        }

        $assign = db::make_assignment($assignments);

        $res = db::query("
			UPDATE stats_matchs
			SET $assign
			WHERE id = $ref
		",
            ACCES_TABLE, 'update stats_matchs');

        cache::suppress('stats_joueurs.%');
        cache::suppress('classement.%');
        self::$data['data_match'] = gestion_matchs::get_un_match($ref);

        $this->succes();

    }


    function fn_retirer_forfait()
    {
        /**
         * @var $id_match int
         */
        extract(self::check_params(
            'id_match;unsigned'
        ));
        $record = new record_stats_match($id_match, 2);
        $record->load();
        if (!$record->is_found) {
            $this->fin('introuvable');
        }
        if (!$record->is_editable_marqueur(true)) {
            $this->non_autorise();
        }
        $record->update(['forfait1' => 0, 'forfait2' => '0']);
        self::$data['data_match'] = gestion_matchs::get_un_match($id_match);
        $this->succes();
    }



    # vérifier si les conditions sont réunies pour inscrire une victoire par forfait,
    # inscrire cette victoire si conditions réunies
    # rapporter erreur autrement ou si proceder <> 1
    function fn_verifier_forfait()
    {
        /**
         * @var $id_match int
         * @var $proceder int
         */

        extract(self::check_params(
            'id_match;unsigned',
            'proceder;unsigned;max:1'
        ));

        if (!perm::marqueur_match($id_match)) {
            $this->non_autorise();
        }

        $record = new record_stats_match();
        $record->load($id_match, $proceder ? 2 : 0);

        if (!$record->is_found) {
            $this->fin('introuvable');
        }

        $this->verifier_forfait($record, $proceder);


        if (!$proceder or count(self::$data['erreurs'])) {
            if (count(self::$data['erreurs'])) {
                $fn = function ($msg) {
                    return self::msg($msg);
                };
//                debug_print_once('erreurs'.  print_r(self::$data['erreurs'], 1));
                self::$data['erreurs'] = array_map(
                    function ($msg) use ($fn) {
                        return $fn($msg);
                    },
                    self::$data['erreurs']
                );
//                debug_print_once('erreurs2'.  print_r(self::$data['erreurs'], 1));
            }
            $this->succes();
        }

        $record->update($record->assignment_);


        self::$data['data_match'] = gestion_matchs::get_un_match($id_match);
//        self::$data['data_match'] = $this->get_un_match(null, $id_match);
        $this->succes();
    }

    function fn_inserer_fins_periodes()
    {

        /**
         * @var $id_match int
         * @var $duree  string
         */
        extract(self::check_params(
            'id_match;unsigned',
            'duree;regex:#^\d\d?(:\d\d)?$#;opt;default_null'
        ));
        if (!perm::marqueur_match($id_match)) {
            $this->non_autorise();
        }

        if (is_null($duree)) {
            $duree = cfg_yml::get('matchs', 'duree_periodes');

            $duree .= sprintf('%02u', $duree) . ':00';
        } else {
            if (strpos($duree, ':') === false) {
                $duree .= ':00';
            }
            if (strlen($duree == 4)) {
                $duree = '0' . $duree;
            }
        }

        self::$data = array_merge(self::$data, $this->inserer_fins_periodes($id_match, $duree, true));

        $this->succes();

    }

    /**
     * @param $id_match
     * @param $chrono_std_fin_per
     * @param bool $inconditionnel : si true, fin de période insérée même si précède le plus récent événement trouvé
     * @return array
     *
     */
    function inserer_fins_periodes($id_match, $chrono_std_fin_per, $inconditionnel = false)
    {
        $res_obj = new result_insertion_fins_periodes();

        $res = db::query("
                SELECT periode,
                  MAX(chrono) chrono,
                  COUNT(IF(type_enreg = 'fin_periode',1,NULL)) nb_fins_periodes,
                  MAX(IF(type_enreg = 'fin_periode', chrono, NULL)) chrono_fin_periode
                FROM match_feuille
                WHERE id_match = $id_match
                GROUP BY periode
                ORDER BY periode
                FOR UPDATE
            ", ACCES_TABLE);
        if ($res->num_rows == 0) {
            return $res_obj->result();
        }
        $liste = db::result_array($res, 'periode');
        $max_periode = 1;
        foreach ($liste as $no_periode => $data_periode) {
            $max_periode = max($max_periode, $no_periode);
        }


        for ($no_periode = 1; $no_periode <= $max_periode; $no_periode++) {
            $insertion = [$id_match, $no_periode, db::sql_str($chrono_std_fin_per), "'fin_periode'"];

            # si rien dans  la période, insérer une fin de période
            if (!array_key_exists($no_periode, $liste)) {
                $res_obj->add_insertion($insertion);
                continue;
            }
            # si qqch dans la période mais pas de fin de période, alors vérifier que
            # le plus récent événement enregistré ne dépasse pas la fin de la période
            # si c'est le cas, ajouter une fin de période, sinon erreur
            $data_periode = $liste[$no_periode];
            if ($data_periode['nb_fins_periodes'] == 0) {
                if ($data_periode['chrono'] > $chrono_std_fin_per) {
                    $msg = sprintf(self::msg('evenement_apres_fin_periode'), $no_periode, $data_periode['chrono']);
                    if (!$inconditionnel) {
                        $res_obj->add_erreur($msg);
                        continue;
                    }
                    $res_obj->add_msg($msg);
                }
                $res_obj->add_insertion($insertion);
                continue;
            }

            # si jamais plus d'une fin de période pour une période
            if ($data_periode['nb_fins_periodes'] > 1) {
                /**
                 * @var $ids int
                 */
                $res = db::query("
                        SELECT id
                        FROM match_feuille
                        WHERE id_match = $id_match AND periode = $no_periode AND type_enreg = 'fin_periode'

                        ORDER BY chrono DESC
                        LIMIT 1
                    ", ACCES_TABLE);
                $ids = db::result_array_one_value($res, 'id');

                $id_conserve = array_shift($ids);
                if (count($ids)) {
                    $res_obj->add_effacement($ids);

                    $sql_ids = implode(',', $ids);
                    $res = db::query("
                            DELETE FROM match_feuille
                            WHERE id IN ($sql_ids)
                        ", ACCES_TABLE);

                }
                continue;
            }

            # rendu ici, le nb de fins de périodes = 1

            if ($data_periode['chrono_fin_periode'] < $data_periode['chrono']) {
                $res_obj->add_msg(sprintf(self::msg('evenement_apres_fin_periode'), $no_periode, $data_periode['chrono_fin_periode']));
            }
        }

        $res_obj->insert();

        return $res_obj->result();
    }


    /**
     * vérifier qu'une période existe; si c'est le cas, juste retourner;
     * sinon, insérer un enregistrement de fin de période
     *
     */
    function fn_choisir_periode()
    {
        /**
         * @var $id_match int
         * @var $periode int
         */
        extract(self::check_params(
            'id_match;unsigned',
            'periode;unsigned'
        ));
        if (!perm::marqueur_match($id_match)) {
            $this->non_autorise();
        }
        /**
         * @var $max_chrono string
         * @var $nb_fins_per int
         */
        $res = db::query("
                SELECT MAX(chrono) max_chrono,
                COUNT(IF(periode = $periode, 1, NULL)) nb_fins_per
                FROM match_feuille
                WHERE id_match = $id_match AND type_enreg = 'fin_periode'
            ", ACCES_TABLE);
        extract($res->fetch_assoc());
        if ($nb_fins_per) {
            $this->succes();
        }
        $std_chrono = cfg_yml::get('matchs', 'duree_periodes');
        if (preg_match('#^\d?\d$#', $std_chrono)) {
            $std_chrono = sprintf('%02u', $std_chrono) . ':00';
        } else if (!preg_match('#^\d\d:\d\d$#', $std_chrono)) {
            $std_chrono = '22:00';
        }

        $chrono = max($max_chrono, $std_chrono);

        $res = db::query("
                INSERT INTO match_feuille
                SET id_match = $id_match,
                  chrono = '$chrono',
                  type_enreg = 'fin_periode',
                  periode = $periode

            ", ACCES_TABLE);
        $id = db::get('insert_id');
        $res = db::query("
                SELECT *
                FROM match_feuille
                WHERE id = $id
            ", ACCES_TABLE);

        self::$data['nouveau'] = $res->fetch_assoc();
        $this->succes();

    }


    function verifier_forfait(record_stats_match $record, $lock = false)
    {
        $id_match = $record->id;


        $lock_share_mode = $lock ? 'LOCK IN SHARE MODE' : '';

        self::$data['erreurs'] = [];

        if ($record->forfait1 or $record->forfait2) {

            self::$data['erreurs'][] = 'deja_forfait';
            self::$data['deja_forfait'] = true;
            return false;
        }

        if ($record->locked) {
            self::$data['erreurs'][] = 'match_verrouille';
            return false;
        }


        if ($record->futur) {
            self::$data['erreurs'][] = 'match_futur';
            return false;
        }

        if ($record->a_replanifier) {
            self::$data['erreurs'][] = 'a_replanifier';
            return false;
        }

        if ($record->annule) {
            self::$data['erreurs'][] = 'match_annule';
            return false;
        }

        $equipe_gagnante = false;
        if (!is_null($record->pts1) and !is_null($record->pts2)) { // si les 2 eq ont un résultat
            $pts_gagnants = cfg_yml::get('matchs', 'pts_forfait_gagnant');
            if (max($record->pts1, $record->pts2) != $pts_gagnants or min($record->pts1, $record->pts2) != cfg_yml::get('matchs', 'pts_forfait_perdant')) {
                self::$data['erreurs'][] = 'pointage_existe';
            } else {
                $equipe_gagnante = [];
                if ($pts_gagnants == $record->pts1) {
                    $equipe_gagnante[] = $record->id_equipe1;
                }
                if ($pts_gagnants == $record->pts2) {
                    $equipe_gagnante[] = $record->id_equipe2;
                }
            }
        } else {
            if (!(is_null($record->pts1) and is_null($record->pts2))) { // si au moins une equipe a un résultat
                self::$data['erreurs'][] = 'pointage_existe';
            }
        }

        # vérifier que des joueurs ont été associés au match seulement pour équipe gagnante
        /**
         * @var $id_equipe_gagnante int
         */
        $res = db::query("
            SELECT DISTINCT id_equipe id_equipe_gagnante
            FROM match_joueurs
            WHERE id_match = $id_match
            $lock_share_mode
		", ACCES_TABLE, '');


        if ($res->num_rows == 0) {
            self::$data['erreurs'][] = 'pas_de_joueurs';
        } else if ($res->num_rows > 1) {
            self::$data['erreurs'][] = 'joueurs_deux_equipes';
        }
        extract($res->fetch_assoc());
        if ($equipe_gagnante and !in_array($id_equipe_gagnante, $equipe_gagnante)) {
            self::$data['erreurs'][] = 'pointage_incompatible_joueurs';
        }

        /**
         * @var $nb int
         *
         * verifier que rien n'a été entré dans feuille match
         */

        $res = db::query("
            SELECT COUNT(*) nb
            FROM match_feuille
            WHERE id_match = $id_match
            $lock_share_mode
		", ACCES_TABLE, '');
        extract($res->fetch_assoc());
        if ($nb) {
            self::$data['erreurs'][] = 'evenements_entres';
        }


        if (count(self::$data['erreurs']) == 0) {
            if ($id_equipe_gagnante == $record->id_equipe1) {
                $record->assignment_ = array_merge($record->assignment_, [
                    'forfait1' => 0,
                    'forfait2' => 1,
                    'pts1' => cfg_yml::get('matchs', 'pts_forfait_gagnant'),
                    'pts2' => cfg_yml::get('matchs', 'pts_forfait_perdant'),
                    'sj_ok1' => 1,
                    'sj_ok2' => 1
                ]);

            } else {
                $record->assignment_ = array_merge($record->assignment_, [
                    'forfait1' => 1,
                    'forfait2' => 0,
                    'pts1' => cfg_yml::get('matchs', 'pts_forfait_perdant'),
                    'pts2' => cfg_yml::get('matchs', 'pts_forfait_gagnant'),
                    'sj_ok1' => 1,
                    'sj_ok2' => 1
                ]);
            }
        }

        return count(self::$data['erreurs']) > 0 ? false : true;

    }

    static function resp_match()
    {
        $id_marqueur = session::get('id_visiteur');
        if (!$id_marqueur) {
            return false;
        }
        /**
         * @var $nb int
         */

        $res = db::query("
            SELECT COUNT(*) nb
            FROM stats_matchs
            WHERE marqueur = $id_marqueur
		",
            ACCES_TABLE, '');
        extract($res->fetch_assoc());
        return $nb > 0;
    }

    function fn_get_joueurs()
    {
        /**
         * @var int $id_match
         * @var int $raw_list
         * @var boolean $process_list
         */

        extract(self::check_params(
            'id_match;unsigned',
            'raw_list;bool;bool_to_num;opt;default_empty',
            'process_list;bool;bool_to_num;opt;default_empty' # pour version mobile
        ));
        /**
         * @var $id_equipe1 int
         * @var $id_equipe2 int
         */

        $res = db::query("
            SELECT id_equipe1, id_equipe2
            FROM stats_matchs
            WHERE id = $id_match
            LOCK IN SHARE MODE
		", ACCES_TABLE, '');

        if ($res->num_rows == 0) {
            $this->fin('introuvable');
        }
        extract($res->fetch_assoc());
        $equipes = array();
        foreach (array($id_equipe1, $id_equipe2) as $id_equipe) {
            if (preg_match('#^\d+$#', $id_equipe)) {
                $equipes[] = $id_equipe;
                self::$data['liste'][$id_equipe] = array();
            }

        }
        if (count($equipes) == 0) {
            $this->fin('introuvable');
        }
        $liste_equipes = implode(',', $equipes);
        $res = db::query("
        SELECT a.*, COUNT(sj.id) + COUNT(fm.id) + COUNT(mff.id) locked FROM (
        
            # inclure les membres réguliers des équipes participant au match
            SELECT
                m.id,
                CONCAT(m.nom, ', ', m.prenom) nom,
                IFNULL(mj.no_chandail, dj.no_chandail) no_chandail,
                e.id_equipe,
                if(dj.position = 2, 1, 0) gardien_dj,
                IFNULL(IFNULL(mj.position, dj.position), -1) position,
                IF(mj.id_joueur IS NOT NULL, 1, 0) choisi,
                1 joueur_eq,
                0 substitut,
                mj.id_equipe id_equipe_match,
                CONCAT(e.nom, ' [', cl.classe, ']') nom_equipe,
                'membre' type
                
                
                FROM joueur_equipe je
                JOIN membres m ON je.id_joueur = m.id
                JOIN equipes e USING(id_equipe)
                JOIN classes cl ON cl.id = e.classe
                LEFT JOIN dossier_joueur dj ON dj.id_joueur = m.id AND dj.saison = e.id_saison
                LEFT JOIN match_joueurs mj ON mj.id_match = $id_match AND mj.id_joueur = m.id AND e.id_equipe = mj.id_equipe
                WHERE e.id_equipe IN ($liste_equipes)
                    
            UNION
            
            # ajouter ceux qui ont été choisis par l'intermédiaire de feuille de match, sans être membres réguliers
            # d'une équipe
            
            SELECT
                m.id,
                CONCAT(m.nom, ', ', m.prenom) nom,

                IFNULL(mj.no_chandail, dj.no_chandail) no_chandail,
                e.id_equipe,
                if(dj.position = 2, 1, 0) gardien_dj,
                IFNULL(mj.position, dj.position) position,
                1 choisi,  
                0 joueur_eq,
                1 substitut,
                mj.id_equipe id_equipe_match,
                CONCAT(e.nom, ' [', cl.classe, ']') nom_equipe,
                'feuille_match' type
                
                
                FROM match_joueurs mj
                JOIN membres m ON mj.id_joueur = m.id
                JOIN equipes e USING(id_equipe)
                JOIN classes cl ON cl.id = e.classe
                LEFT JOIN dossier_joueur dj ON dj.id_joueur = m.id AND dj.saison = e.id_saison
                LEFT JOIN joueur_equipe je ON je.id_equipe = e.id_equipe AND m.id = je.id_joueur
                WHERE mj.id_match = $id_match AND e.id_equipe IN ($liste_equipes) AND je.id_joueur IS NULL
                    
            UNION 
            
            # ajouter ceux qui auraient des stats pour le match, mais sans avoir été
            # entrés sur feuille de match et sans être membres d'équipe
            # cas pour substitut dont on entre les stats tout de suite après match, sans avoir entré feuille de match
            SELECT  
                m.id,
                CONCAT(m.nom, ', ', m.prenom) nom,
                dj.no_chandail,
                e.id_equipe,
                if(dj.position = 2, 1, 0) gardien_dj,
                # ne pas accepter position gardien si le joueur n'a pas joué comme gardien...
                IF(sj.resultat_gardien IS NOT NULL, 2, IF(dj.position = 2, -1, dj.position)) position,
                0 choisi,  
                0 joueur_eq,
                1 substitut,
                sj.id_equipe id_equipe_match,
                CONCAT(e.nom, ' [', cl.classe, ']') nom_equipe,
                'stats' type
            FROM stats_joueurs sj
            JOIN membres m ON sj.id_membre = m.id
            JOIN equipes e USING(id_equipe)
            JOIN classes cl ON cl.id = e.classe
            JOIN dossier_joueur dj ON dj.id_joueur = m.id AND dj.saison = e.id_saison
            LEFT JOIN joueur_equipe je ON je.id_joueur = m.id AND je.id_equipe = e.id_equipe
            LEFT JOIN match_joueurs mj ON mj.id_joueur = m.id AND mj.id_match = $id_match
            WHERE sj.id_match = $id_match AND je.id_joueur IS NULL AND mj.id_joueur IS NULL
            

                ) a
            LEFT JOIN stats_joueurs sj ON sj.id_match = $id_match AND sj.id_membre = a.id AND (sj.buts OR sj.buts_contre OR sj.passes OR sj.min_punition OR sj.resultat_gardien)
            LEFT JOIN match_feuille fm ON fm.id_match = $id_match AND a.id IN (
              fm.id_membre,
              fm.id_membre_passe,
              fm.id_membre_passe2,
              fm.id_membre_gardien,
              fm.gardien1,
              fm.gardien2
              )
            LEFT JOIN match_feuille_fusillade mff ON mff.id_fm = fm.id AND a.id IN (mff.id_joueur1, mff.id_joueur2)
            GROUP BY a.id  
            ORDER BY nom 
                
		", ACCES_TABLE, '');

        if ($raw_list) {
            self::$data['liste'] = db::result_array($res);
            if ($process_list) {
                A::setBooleanEach(self::$data['liste'], ['joueur_eq', 'locked', 'choisi', 'substitut', 'gardien_dj']);
                A::setIntEach(self::$data['liste'], ['position']);
                foreach (self::$data['liste'] as &$val) {
                    unset($val['nom_equipe']);
                }
            }
            $this->succes();
        }

        if ($res->num_rows) {

            while ($row = $res->fetch_assoc()) {
                $id_equipe = $row['id_equipe'];
                unset($row['id_equipe']);
                self::$data['liste'][$id_equipe][] = $row;
            }
        }
        $this->succes();
    }

    function fn_choisir_substitut()
    {
        /**
         * @var $id_membre int
         * @var $id_equipe int
         * @var $id_match int
         */

        extract(self::check_params(
            'id_membre;unsigned',
            'id_equipe;unsigned',
            'id_match;unsigned'
        ));
        /**
         * @var $id_saison int
         */

        $res = db::query("
            SELECT id_saison
            FROM equipes
            WHERE id_equipe = $id_equipe
		", ACCES_TABLE, '');

        if ($res->num_rows == 0) {
            $this->fin('equipe_introuvable');
        }
        extract($res->fetch_assoc());
        /**
         * @var $nb  int
         */


        # verifier que substitut pas déjà dans liste des participants
        $res = db::query("
            SELECT COUNT(*) nb
            FROM match_joueurs 
            WHERE id_joueur = $id_membre AND id_match = $id_match AND id_equipe = $id_equipe
            LOCK IN SHARE MODE
		", ACCES_TABLE, '');
        extract($res->fetch_assoc());
        if ($nb) {
            $this->fin('deja_substitut');
        }

        $res = db::query("
            SELECT
                m.id,
                CONCAT(m.nom, ', ', m.prenom) nom,
                dj.no_chandail,
                $id_equipe id_equipe,
                dj.position position,
                1 choisi,
                0 joueur_eq,
                1 substitut,
                COUNT(sj.id) locked
                
                FROM membres m
                LEFT JOIN dossier_joueur dj ON dj.id_joueur = m.id AND dj.saison = $id_saison
                LEFT JOIN stats_joueurs sj ON sj.id_match = $id_match AND sj.id_membre = m.id AND (sj.buts OR sj.buts_contre OR sj.passes OR sj.min_punition OR sj.resultat_gardien)
                WHERE m.id = $id_membre
                GROUP BY m.id    
                
		", ACCES_TABLE, '');

        self::$data['liste'] = db::result_array($res);
        $this->succes();
    }

    function fn_choisir_joueur_match()
    {
        /**
         * @var $id_joueur int
         * @var $id_match int
         * @var $id_equipe int
         * @var $choisi int
         * @var $marqueur int
         * @var $locked int
         */
        extract(self::check_params(
            'id_match;unsigned',
            'id_joueur;unsigned',
            'id_equipe;unsigned',
            'choisi;bool;bool_to_num'
        ));
        $res = db::query("
            SELECT marqueur, locked
            FROM stats_matchs
            WHERE id = $id_match
            LOCK IN SHARE MODE
		", ACCES_TABLE, '');
        if ($res->num_rows == 0) {
            $this->fin('introuvable');
        }
        extract($res->fetch_assoc());
        $id_visiteur = session::get('id_visiteur');
        if ($locked and !perm::test('admin')) {
            $this->fin('verrouille');
        }
        if (
            $id_visiteur != $marqueur
            and !perm::test('admin')
            and !perm::perm_resultats_eq($id_equipe)
        ) {
            $this->non_autorise();
        }

        # refuser effacement si des stats sont entrées pour ce joueur

        $res = db::query("
            
		", ACCES_TABLE, '');


    }

    function fn_choisir_joueurs_match()
    {
        /**
         * @var $choix array
         * @var $id_match int
         */


        extract(self::check_params(
            'id_match;unsigned',
            'choix;json;decode_array'
        ));
        # note: $choix = id_equipe1: [id, position, no_chandail], ..., id_equipe2: ...

        $data_match = $this->joueurs_avec_stats_ou_sur_fm($id_match);
        debug_print_once('joueurs avec stats ou sur fm' . print_r($data_match, 1));

        if ($data_match === false) {
            $this->fin('match_introuvable');
        }

        $values = array();

        # vérifier s'il y a des joueurs verrouillés par des stats qui ne seraient pas présents dans les choix faits


        $manque = array();
        #debug_print_once(print_r($choix,1));;
        foreach ($choix as $id_equipe => $choix_equipe) {
            if (is_null($choix_equipe)) {
                $choix_equipe = [];
            }
            #debug_print_once("id equipe = $id_equipe");
            if (!is_array($choix_equipe)) {
                $choix_equipe = [];
            }
            if (!array_key_exists($id_equipe, $data_match['equipes'])) {
                $this->fin('equipe_non_liee_au_match');
            }

            #debug_print_once("valid ok");

            $liste_ids_choisis_equipe = [];

            # chaque $joueur_choisi = [id: x, position: y, no_chandail]
            foreach ($choix_equipe as $joueur_choisi) {
                if (!preg_match('#^\d+$#', $joueur_choisi['id']) or
                    !in_array($joueur_choisi['position'], array(-1, 0, 1, 2, 3)) or
                    !preg_match('#^\d*$#', $joueur_choisi['no_chandail'])
                ) {
                    debug_print_once("Valeurs refusées choix joueur = id: {$joueur_choisi['id']}; pos= {$joueur_choisi['position']}");
                    $this->fin('mauvais_param');
                }
                # tous les joueurs avec stats doivent être choisis
                # debug_print_once("ajoute joueur choisi {$joueur_choisi['id']}");
                $liste_ids_choisis_equipe[] = $joueur_choisi['id'];
                if (!$joueur_choisi['no_chandail']) {
                    $joueur_choisi['no_chandail'] = 'NULL';
                }
//                $chandail = $joueur_choisi['no_chandail'] ?? 'NULL';
                $values[] = "$id_match, $id_equipe, {$joueur_choisi['id']}, {$joueur_choisi['position']}, {$joueur_choisi['no_chandail']}, 0";
            }
            #debug_print_once("diff entre " . print_r($data_match['equipes'][$id_equipe]['locked'],1) . ' ET ' . print_r($liste_ids_choisis_equipe,1));
//            $data_match['equipes'][$id_equipe]['choix_manquants'] = $temp = array_diff($data_match['equipes'][$id_equipe]['locked'], $liste_ids_choisis_equipe);
//            if (count($temp)) {
//                $manque = array_merge($manque, $temp);
//            }
            #debug_print_once("EST = " . print_r($temp,1));
        }

        $ids_membres_choisis = P::pipe(
            'array_values',
            P::flattenLevel(),
            P::pluck('id')
        )($choix);

        $manque = array_unique(array_diff($data_match['locked'], $ids_membres_choisis));


        if (count($manque)) {
            $sql_ids_manquent = implode(',', $manque);
            $res = db::query("
                    SELECT CONCAT(nom, ', ', prenom)
                    FROM membres
                    WHERE id IN ($sql_ids_manquent) 
                ", ACCES_TABLE);

            $noms = P::pipe(
                P::flatten(),
                P::implode('; ')
            )(db::result_array_values($res));

            $this->fin('Manquent: ' . $noms);
        }
        $res = db::query("
            UPDATE match_joueurs
            SET marque = 1
            WHERE id_match = $id_match
		", ACCES_TABLE, '');
        if (count($values)) {

            $liste_values = implode('),(', $values);
            $res = db::dquery("
                INSERT IGNORE INTO match_joueurs
                (id_match, id_equipe, id_joueur, position, no_chandail, marque)
                VALUES
                ($liste_values)
                ON DUPLICATE KEY UPDATE
                    cote = IF(position <> VALUES(position), NULL, cote),
                    groupe = IF(position <> VALUES(position), NULL, groupe),
                    position = VALUES(position),
                    no_chandail = VALUES(no_chandail),
                    marque = 0
            ", ACCES_TABLE, '');
        }
        $res = db::query("
            DELETE mj
            FROM match_joueurs mj
            WHERE marque AND id_match = $id_match
		", ACCES_TABLE, '');
        //db::rollback();
        $this->succes();
    }

    function info_joueurs_pour_selection($ids, $id_equipe, $id_match)
    {
        if ($this->id_saison) {
            $id_saison = $this->id_saison;
        } else {
            /**
             * @var $id_saison int
             */
            $res = db::query("
                SELECT id_saison
                FROM equipes
                WHERE id_equipe = $id_equipe
            ", ACCES_TABLE, '');
            if ($res->num_rows == 0) {
                $this->fin("equipe $id_equipe introuvable");
            }
            extract($res->fetch_assoc());
            $this->id_saison = $id_saison;
        }

        $liste = implode(',', $ids);
        $res = db::query("
            SELECT
                m.id,
                CONCAT(m.nom, ', ', m.prenom) nom,
                dj.no_chandail,
                $id_equipe id_equipe,
                dj.position position,
                1 choisi,
                IF(je.id_joueur IS NOT NULL, 1, 0) joueur_eq,
                IF(je.id_joueur IS NULL, 1, 0) substitut,
                COUNT(sj.id) locked
                
                FROM membres m
                LEFT JOIN dossier_joueur dj ON dj.id_joueur = m.id AND dj.saison = $id_saison
                LEFT JOIN stats_joueurs sj ON sj.id_match = $id_match AND sj.id_membre = m.id AND (sj.buts OR sj.buts_contre OR sj.passes OR sj.min_punition OR sj.resultat_gardien)
                     LEFT JOIN joueur_equipe je ON je.id_equipe = $id_equipe AND je.id_joueur = m.id
                WHERE m.id IN ($liste)
                GROUP BY m.id    
                
		", ACCES_TABLE, '');
        return db::result_array($res);
    }


    function joueurs_avec_stats_ou_sur_fm($id_match)
    {
        $res = db::query("
            SELECT sm.id_equipe1, sm.id_equipe2, 
                GROUP_CONCAT(DISTINCT IF(sj.id_equipe = sm.id_equipe1, sj.id_membre, NULL) SEPARATOR ',') locked1,
                GROUP_CONCAT(DISTINCT IF(sj.id_equipe = sm.id_equipe2, sj.id_membre, NULL) SEPARATOR ',') locked2,
                GROUP_CONCAT(CONCAT_WS(',',
                    sj.id_membre,
                    mf.id_membre,
                    mf.id_membre_passe,
                    mf.id_membre_passe2,
                    mf.id_membre_gardien,
                    mf.gardien1,
                    mf.gardien2
                  ) SEPARATOR ',') locked
            FROM stats_matchs sm
            LEFT JOIN stats_joueurs sj ON sj.id_match = $id_match AND (
                sj.buts OR 
                sj.passes OR 
                sj.min_punition OR 
                sj.buts_contre OR
                sj.resultat_gardien
                )
            LEFT JOIN match_feuille mf ON mf.id_match = $id_match
            WHERE sm.id = $id_match
            GROUP BY sm.id
            
            LOCK IN SHARE MODE
		", ACCES_TABLE, '');
        if ($res->num_rows == 0) {
            return false;
        }
        $val = $res->fetch_assoc();
        $to_ret = array();
        if ($val['id_equipe1']) {
            if ($val['locked1']) {
                $val['locked1'] = explode(',', $val['locked1']);
            } else {
                $val['locked1'] = [];
            }
            $to_ret['equipes'][$val['id_equipe1']]['locked'] = $val['locked1'];
        }

        if ($val['id_equipe2']) {
            if ($val['locked2']) {
                $val['locked2'] = explode(',', $val['locked2']);
            } else {
                $val['locked2'] = [];
            }
            $to_ret['equipes'][$val['id_equipe2']]['locked'] = $val['locked2'];
        }

        if ($val['locked']) {
            $val['locked'] = array_filter(array_unique(explode(',', $val['locked'])));
        } else {
            $val['locked'] = [];
        }
        $to_ret['locked'] = $val['locked'];
        return $to_ret;
    }

    function fn_feuille_match()
    {
        /**
         * @var $id_match int
         */

        extract(self::check_params(
            'id_match;unsigned'
        ));
        $res = db::query("
            SELECT *
            FROM match_feuille
            WHERE id_match = $id_match
            ORDER BY periode, chrono
		", ACCES_TABLE, '');
        self::$data['liste'] = db::result_array($res);
    }

    function fn_collecter_stats()
    {
        /**
         * @var $id_match int
         * @var $sauvegarder int
         */
        extract(self::check_params(
            'id_match;unsigned',
            'sauvegarder;bool;bool_to_num;default_empty;opt'

        ));

        if ($sauvegarder) {
            if (!perm::marqueur_match($id_match)) {
                $this->non_autorise();
            }
        }

        $record = new record_stats_match();
        $record->load($id_match, 2);
        if (!$record->is_found) {
            $this->fin('Match introuvable');
        }

        if ($record->is_forfait()) {
            $this->fin("Aucune statistique à collecter quand une équipe perd par forfait.");
        }
        if ($record->locked) {
            $this->fin('Match verrouillé');
        }
        # vérifier que chaque periode a une fin et qu'elle est le dernier événement de la période
        $res = db::query("
                SELECT MAX(chrono) max_chrono,
                    periode,
                    MAX(IF(type_enreg = 'fin_periode', chrono, '00:00')) chrono_fin,
                    COUNT(IF(type_enreg = 'fin_periode', 1, NULL)) nb_fins
                FROM match_feuille
                WHERE id_match = $id_match
                GROUP BY periode
                ORDER BY periode
                LOCK IN SHARE MODE
            ", ACCES_TABLE);
        $liste_periodes = db::result_array($res);
        if (count($liste_periodes) == 0) {
            $this->fin('Aucune période trouvée');
        }
        $periodes_trouvees = [];
        $max_periode = 0;
        foreach ($liste_periodes as $item) {
            if ($item['nb_fins'] == 0) {
                $this->fin(sprintf(self::msg('manque_fin_per_x'), $item['periode']));
            } else if ($item['nb_fins'] > 1) {
                $this->fin(sprintf(self::msg('plusieurs_fins_per_x'), $item['periode']));
            } else if ($item['chrono_fin'] < $item['max_chrono']) {
                $this->fin(sprintf(self::msg('even_apres_fin_per_x'), $item['periode']));
            }
            $periodes_trouvees[] = (int)$item['periode'];
            $max_periode = (int)$item['periode'];
        }
        $periodes_manquantes = array_diff(range(1, $max_periode), $periodes_trouvees);

        if (count($periodes_manquantes)) {
            $this->fin(sprintf(self::msg('aucune_donnee_per_x'), implode(', ', $periodes_manquantes)));
        }

        # maintenant qu'on sait que les fins de périodes sont ok, initialiser classe pour
        # calcul des temps des gardiens en utilisant ces données
        temps_gardiens::init($id_match);


        $compilation_avantages_numeriques = $this->sauvegarder_an_dn($id_match);

        $buts = $this->get_buts_from_feuille_match($id_match);
        # retourne [[buts, id_membre, id_equipe] ... ]

        $res_gardiens = $this->get_resultats_gardiens_from_feuille_match($id_match);
        # retourne [[buts_contre, id_membre, id_equipe], ...]

        $temps_filet = $this->get_temps_filet($id_match);
        # retourne 'gardiens' => [ id_gardien => secondes de presence, ...] #****** paires
        #          'filet_desert_equipes' => [id_equipe => secondes_filet_desert] #***** paires
        if (is_string($temps_filet)) {
            $this->fin($temps_filet);
        }

        $temps_gardiens = $temps_filet['gardiens']; # paires
        $temps_filet_desert_eq = $temps_filet['filet_desert_equipes']; #paires

        //debug_print_once('temps gardiens = ' . print_r($temps_gardiens,1));

        $punitions = $this->get_punitions_from_feuille_match($id_match);
        # retourne [[duree_punition, id_membre, id_equipe], ...]

        $passes = $this->get_passes_from_feuille_match($id_match);
        # retourne[[passes, id_membre, id_equipe], ...]

        $buts_filet_vide = $this->get_resultats_filet_vide($id_match); # retourne paires
        # retourne [[id_equipe, [buts_filet_vide]], ...]


        $joueurs = [];
        $equipes = [];
        #debug_print_once("===============================================================");
        #debug_print_once(print_r(array_merge($buts, $res_gardiens, $punitions, $passes, $buts_filet_vide), 1));
        foreach (array_merge($buts, $res_gardiens, $punitions, $passes, $buts_filet_vide) as $vals) {
            if (array_key_exists('id_membre', $vals) and $vals['id_membre']) {
                $joueurs[] = $vals['id_membre'];
            }
            if (array_key_exists('id_equipe', $vals) and $vals['id_equipe']) {
                $equipes[] = $vals['id_equipe'];
            }
        }
        $joueurs_fm = array_merge(array_unique($joueurs));
        $equipes_fm = array_merge(array_unique($equipes));

        $presences = $this->get_presences_from_feuille_match($id_match);
        # retourne [[id_equipe, id_membre, gardien=>0|1], ...]

        $joueurs_presents = [];
        $equipes_match = [];

        foreach ($presences as $vals) {
            $joueurs_presents[] = $vals['id_membre'];
            $equipes_match[] = $vals['id_equipe'];
        }
        $joueurs_presents = array_unique($joueurs_presents);
        $equipes_match = array_merge(array_unique($equipes_match));


        if (count($equipes_match) < 2) {
            $this->fin("Joueurs de moins de 2 équipes sur la feuille de match");
        }

        if (count($equipes_match) > 2) {
            $this->fin("Joueurs de plus de 2 équipes sur la feuille de match");
        }

        if (count(array_diff($equipes_fm, $equipes_match))) {
            $this->fin("Joueurs non enregistrés pour ce match sur la feuille de match");
        }

        $this->verifier_buts_augmentent($id_match, $equipes_match);

        $liste_equipes = implode(',', $equipes_match);
        $liste_joueurs = implode(',', $joueurs_presents);

        $res = db::query("
            SELECT
                e.id_equipe,
                e.nom,
                n.categ,
                n.classe,
                e.id_saison,
                e.division
            FROM equipes e
            JOIN niveaux n USING(niveau)
            
            WHERE e.id_equipe IN ($liste_equipes)
                
		", ACCES_TABLE, '');
        $data_equipes = db::result_array($res, 'id_equipe');


        # vérifier que deux équipes de même saison
        $id_saison = 0;
        $id_division = 0;
        foreach ($data_equipes as $data_equipe) {
            if ($id_saison and $id_saison != $data_equipe['id_saison']) {
                $this->fin("Match entre équipes de saisons différentes...");
            }
            if ($id_division and $id_division != $data_equipe['division']) {
                $this->fin("Match entre équipes de divisions différentes...");
            }
            $id_saison = $data_equipe['id_saison'];
            $id_division = $data_equipe['division'];

        }
        if (!$id_saison) {
            $this->fin('Saison inconnue');
        }

        # vérifier que joueurs associés à buts et passes moins joueurs dont la présence est indiquée sur feuille de match = rien
        if (count($diff = array_diff($joueurs_fm, $joueurs_presents))) {
            self::set_data('erreur', [
                'type' => 'membres_en_trop',
                'msg' => "Joueurs non enregistrés pour ce match sur la feuille de match",
                'liste' => $this->get_data_membres($diff, $id_saison, $id_division)
            ]);

            $this->fin();
        }

        $data_membres = $this->get_data_membres($liste_joueurs, $id_saison, $id_division);
        # retourne [[nom, no_chandail, id_membre], ...]

        /**
         * @var $resultat int
         * @var $resultat_adversaire int
         * @var $id_equipe int
         */
        $res = db::query("
            SELECT  resultat, resultat_adversaire, id_equipe
            FROM match_feuille
            WHERE id_match = $id_match AND type_enreg = 'but'
            ORDER BY periode DESC, chrono DESC
            LIMIT 1
		", ACCES_TABLE, '');
        if ($res->num_rows == 0) {
            $resultat = 0;
            $resultat_adversaire = 0;
            $resultats_buts = [$equipes_match[0] => 0, $equipes_match[1] => 0];
        } else {
            extract($res->fetch_assoc());
            foreach ($equipes_match as $eq) {
                $resultats_buts[$eq] = (int) $resultat_adversaire; # mettre resultat_adversaire aux deux équipes
            }
            $resultats_buts[$id_equipe] = (int) $resultat; # corriger le résultat de l'équipe qui a marqué le dernier but

        }

        # resultats_finaux = resultats_buts + fusillade
        $resultats_finaux = $resultats_buts;


        /**
         * @var $pts1 int
         * @var $pts2 int
         * @var $id_equipe1 int
         * @var $id_equipe2 int
         */

        extract($record->select('pts1, pts2, id_equipe1, id_equipe2'));

        # NOTE $resultats est le résultat tenant compte des buts comptés normalement, avant une éventuelle fusillade
        # ce n'est pas le résultat final du match s'il y a fusillade
        # $resultats doit être en accord avec la somme des buts enregistrés avec type_enreg=but


        $res = db::query("
                SELECT id, resultat, resultat_adversaire, id_equipe
                FROM match_feuille
                WHERE id_match = $id_match AND type_enreg = 'fusillade'
            ", ACCES_TABLE);
        if ($res->num_rows > 1) {
            $this->fin("Une seule fusillade permise par match");
        }
        # s'il y a eu fusillade,
        $liste_fusillade = false;
        $gagnant_fusillade = null;
        if ($res->num_rows == 1) {
            $resultat_fusillade = $res->fetch_object();

            # vérifier que le match était nul et que le pointage final est ok
            if ($resultat != $resultat_adversaire) {
                $this->fin(sprintf($this->msg('fusillade_match_non_nul'), $resultat, $resultat_adversaire));
            }
            # vérifier que le pointage final est un bris d'égalité
            if (abs($resultat_fusillade->resultat - $resultat_fusillade->resultat_adversaire) != 1) {
                $this->fin(sprintf($this->msg('resultat_fusillade_non_bris_egalite'), $resultat_fusillade->resultat, $resultat_fusillade->resultat_adversaire));
            }
            # vérifier que la fusillade augmente le pointage de 1
            $points_gagnant = max($resultat_fusillade->resultat, $resultat_fusillade->resultat_adversaire);
            if ($points_gagnant - $resultat != 1) {
                $this->fin(sprintf($this->msg('fusillade_doit_faire_gagner_1_pt'), $resultat, $points_gagnant));
            }
            if (!in_array($resultat_fusillade->id_equipe, $equipes_match)) {
                $this->fin("Fusillade impliquant équipe ne participant pas au match");
            }
            $resultats_finaux[$resultat_fusillade->id_equipe]++;

            # vérifier que la fusillade est ok
            $res = db::query("
                    SELECT *
                    FROM match_feuille_fusillade
                    WHERE id_fm = $resultat_fusillade->id
                    ORDER BY ordre
                ", ACCES_TABLE);
            if ($res->num_rows == 0) {
                $this->fin("Aucun tir enregistré pour la fusillade");
            }
            $liste_fusillade = db::result_array($res);
            if ($liste_fusillade[0]['ronde'] != 1) {
                $this->fin(sprintf($this->msg('fusillade_doit_commencer_ronde_1'), $liste_fusillade[0]['ronde']));
            }


            # vérifier que fusillade n'implique que joueurs présents et équipes participantes au match
            # et que la fusillade progresse normalement d'une ronde à la suivante
            $ronde = 1;

            $buts_fusillade = [$id_equipe1 => 0, $id_equipe2 => 0];
            foreach ($liste_fusillade as $item) {
                if (!in_array($item['ronde'], [$ronde, $ronde + 1])) {
                    $this->fin(sprintf($this->msg('mauvaise_ronde'), $item['ordre'] + 1));
                }
                $ronde = $item['ronde'];
                if (!$item['id_joueur1'] or !$item['id_joueur2']) {
                    $this->fin(sprintf($this->msg('manque_joueur_fusillade'), $item['ordre'] + 1));
                }

                foreach (['id_joueur1', 'id_joueur2'] as $id_j) {
                    if (!in_array($item[$id_j], $joueurs_presents)) {
                        $nom_joueur = $this->nom_membre($item[$id_j]);
                        $this->fin(sprintf($this->msg('joueur_fusillade_non_present'), $nom_joueur, $item['ordre'] + 1));
                    }
                }


                if ($item['but1']) {
                    $buts_fusillade[$id_equipe1]++;
                }
                if ($item['but2']) {
                    $buts_fusillade[$id_equipe2]++;
                }

            }

            if ($buts_fusillade[$id_equipe1] == $buts_fusillade[$id_equipe2]) {
                $this->fin('La fusillade doit être remportée par une équipe');
            }

            $gagnant_fusillade = ($buts_fusillade[$id_equipe1] > $buts_fusillade[$id_equipe2] ? $id_equipe1 : $id_equipe2);
            if ($gagnant_fusillade != $resultat_fusillade->id_equipe) {
                $this->fin('Les résultats du match désignent comme gagnante l\'équipe ayant perdu la fusillade');
            }

        }



        # vérifier que buts concordent avec resultats AVANT FUSILLADE
        $resultats_fm = [];
        #initialiser à zéro pour chaque équipe
        foreach ($equipes_match as $equipe) {
            $resultats_fm[$equipe] = 0;
        }
        #debug_print_once('Buts = ' . print_r($buts,1));
        foreach ($buts as $but) {
            #debug_print_once("ajoute {$but['buts']} à {$but['id_equipe']}");
            $resultats_fm[$but['id_equipe']] += $but['buts'];
        }

        #debug_print_once("Résultat calcul fm = " . print_r($resultats_fm,1));
        #debug_print_once("dernier résultat fm = " . print_r($resultats,1));

        foreach ($resultats_buts as $id_eq => $result) {
            #debug_print_once("compare resultats pour equipe $id_eq = $result avec {$resultats_fm[$id_eq]}");
            if ($resultats_fm[$id_eq] != $result) {
                self::set_data('erreur', [
                    'type' => 'difference_fm_dernier_but',
                    'msg' => "Différence entre résultat selon buts enregistrés et résultat inscrit avec le dernier but",
                    'resultats_fm' => $resultats_fm,
                    'resultats_inscrits' => $resultats_buts,
                    'data_equipes' => $data_equipes
                ]);

                #debug_print_once("pas egal");
                $this->fin();
            }
        }

        # vérifier que si des résultats ont été entrés, ils concordent avec les buts trouvés sur la feuille de match
        if (!is_null($pts1) or !is_null($pts2)) {
            if ($resultats_finaux[$id_equipe1] != $pts1 or $resultats_finaux[$id_equipe2] != $pts2) {
                self::set_data('erreur', [
                    'type' => 'difference_fm_resultat',
                    'msg' => "Résultats de feuille de match divergent du pointage enregistré pour le match",
                    'resultats_stats_match' => array($id_equipe1 => $pts1, $id_equipe2 => $pts2),
                    'resultats_fm' => $resultats_finaux,
                    'data_equipes' => $data_equipes
                ]);
                $this->fin();
            }
        }

        if (is_string($temps_gardiens)) {
            self::set_data('erreur', [
               'type' => 'temps_gardien',
                'msg' => 'Problème de compilation du temps de présence des gardiens',
                'details' => $temps_gardiens
            ]);
            $this->fin();
        }

        if (!$sauvegarder) {

            $resultats_finaux = P::pipe(
                P::toPairs(),
                P::sortBy(P::pipe(P::prop('1'), P::negate()))
            )($resultats_finaux);

            self::$data['buts'] = $buts;
            self::$data['passes'] = $passes;
            self::$data['punitions'] = $punitions;
            self::$data['res_gardiens'] = $res_gardiens;
            self::$data['data_membres'] = $data_membres;
            self::$data['data_equipes'] = $data_equipes;
            self::$data['buts_filet_vide'] = $buts_filet_vide;
            self::$data['resultat_match'] = $resultats_finaux;
            self::$data['temps_gardiens'] = $temps_gardiens; #paires
            self::$data['temps_filet_desert_eq'] = $temps_filet_desert_eq; #paires
            self::$data['compilation_avantages_num'] = $compilation_avantages_numeriques;
            self::$data['fusillade'] = $liste_fusillade;
            self::$data['id_equipe1'] = $id_equipe1;
            self::$data['id_equipe2'] = $id_equipe2;
            self::$data['gagnant_fusillade'] = $gagnant_fusillade;

            $this->succes();
        }

        $temps_gardiens = P::fromPairs($temps_gardiens);

        $data = [];


        $pts = array_values($resultats_fm);

        if ($pts[0] == $pts[1]) {
            $mult = 0;
        } else {
            $mult = 1;
        }

        $resultat_gardien = array();
        $max = max($pts[0], $pts[1]);
        foreach ($resultats_fm as $id_equipe => $val) {
            $resultat_gardien[$id_equipe] = ($val == $max ? 1 : -1) * $mult; # resultat = 1 pour pointage max; -1 pour minimumSecondes, 0 pour nulle
        }


        foreach ($buts as $but) {
            $data[$but['id_membre']][$but['id_equipe']]['buts'] = $but['buts'];
        }
        foreach ($passes as $passe) {
            $data[$passe['id_membre']][$passe['id_equipe']]['passes'] = $passe['passes'];
        }
        foreach ($punitions as $punition) {
            $data[$punition['id_membre']][$punition['id_equipe']]['min_punition'] = $punition['duree_punition'];
        }
//        debug_print_once("res gardiens = " . print_r($res_gardiens,1));
        foreach ($res_gardiens as $res_gardien) {
            $data[$res_gardien['id_membre']][$res_gardien['id_equipe']]['buts_contre'] = $res_gardien['buts_contre'];
            if (!array_key_exists($res_gardien['id_equipe'], $resultat_gardien)) {
                $this->fin("Résultat gardien associé à équipe non partie au match");
            }
            # le resultat_gardien est lié à 
            #$data[$res_gardien['id_membre']][$res_gardien['id_equipe']]['resultat_gardien'] = $resultat_gardien[$res_gardien['id_equipe']];
        }
//        $this->fin('fini ..........');
        #debug_print_once(print_r($data,1));
        $res = db::query("
            UPDATE stats_joueurs
            SET marque = 1
            WHERE id_match = $id_match
            
		", ACCES_TABLE, '');

        $empty_record = [
            'buts' => 0,
            'buts_contre' => 0,
            'passes' => 0,
            'min_punition' => 0,
            'resultat_gardien' => 'null',
            'temps_filet' => 0
        ];

        $stats_a_sauvegarder = [];
        foreach ($presences as $joueur) {
            $fld_vals = [];
            $fld_vals[] = $joueur['id_membre'];
            $fld_vals[] = $id_match;
            $fld_vals[] = $joueur['id_equipe'];

            $stats = array_merge($empty_record, isset($data[$joueur['id_membre']][$joueur['id_equipe']]) ? $data[$joueur['id_membre']][$joueur['id_equipe']] : []);

            if ($joueur['gardien']) {
                $stats['resultat_gardien'] = $resultat_gardien[$joueur['id_equipe']];
                $stats['temps_filet'] = $temps_gardiens[$joueur['id_membre']];
            }

            foreach (['buts', 'buts_contre', 'passes', 'min_punition', 'resultat_gardien', 'temps_filet'] as $ind) {
                $fld_vals[] = $stats[$ind];
            }
            $fld_vals[] = '0'; # marque

            $stats_a_sauvegarder[] = implode(',', $fld_vals);
        }
        $stats_a_sauvegarder = implode('),(', $stats_a_sauvegarder);

        $res = db::query("
            INSERT IGNORE INTO stats_joueurs
            (id_membre,id_match,id_equipe,buts,buts_contre,passes,min_punition,resultat_gardien,temps_filet,marque)
            VALUES
            ($stats_a_sauvegarder)
            ON DUPLICATE KEY UPDATE
            buts =          VALUES(buts),
            buts_contre =   VALUES(buts_contre),
            passes =        VALUES(passes),
            min_punition =  VALUES(min_punition),
            resultat_gardien = VALUES(resultat_gardien),
            temps_filet = VALUES(temps_filet),
            marque =        VALUES(marque)
		", ACCES_TABLE, '');

        $res = db::query("
            DELETE FROM stats_joueurs
            WHERE id_match = $id_match
                AND  marque
		", ACCES_TABLE, '');

        $buts_filet_vide1 = 0;
        $buts_filet_vide2 = 0;
        if (count($buts_filet_vide)) {
            foreach ($buts_filet_vide as $buts) {
                if ($buts['id_equipe'] == $id_equipe1) {
                    $buts_filet_vide1 += $buts['buts_filet_vide'];
                } else {
                    $buts_filet_vide2 += $buts['buts_filet_vide'];
                }
            }
        }

        # stocker info d'avantages/désavantages numériques

        $info_an_dn = [];
        foreach ($compilation_avantages_numeriques['data_equipes'] as $an_dn_equipe) {
            if (!array_key_exists('id_equipe', $an_dn_equipe)) {
                continue;
            }
            $id_equipe = $an_dn_equipe['id_equipe'];
            if ($id_equipe == $id_equipe1) {
                $no = '1';
            } else if ($id_equipe == $id_equipe2) {
                $no = '2';
            } else {
                $this->fin('Échec de la compilation des avantages numériques');
            }
            if ($no == '1') { # champs temps_an2 et temps_dn2 n'existent pas...
                $info_an_dn["temps_an$no"] = $an_dn_equipe['an']['temps'];
                $info_an_dn["temps_dn$no"] = $an_dn_equipe['dn']['temps'];
            }
            $info_an_dn["buts_an$no"] = $an_dn_equipe['an']['buts'];
            $info_an_dn["buts_dn$no"] = $an_dn_equipe['dn']['buts'];
        }


        $record->update(array_merge([
                'sj_ok1' => 1,
                'sj_ok2' => 1,
                'pts1' => $resultats_finaux[$id_equipe1],
                'pts2' => $resultats_finaux[$id_equipe2],
                'buts_filet_vide1' => $buts_filet_vide2,
                'buts_filet_vide2' => $buts_filet_vide1,
                'duree_match' => $compilation_avantages_numeriques['duree_match']
            ],
                $info_an_dn
            )
        );


        cache::suppress("feuille_match.$id_match");
        /**
         * @var $divisions_classes_str string
         * @var $divisions string
         */

        $res = db::query("
            SELECT 
                GROUP_CONCAT(CONCAT(e.division, '.', e.classe) SEPARATOR ',') divisions_classes_str, 
               GROUP_CONCAT(e.division SEPARATOR ',') divisions
            FROM equipes e
            JOIN stats_matchs sm ON e.id_equipe IN (sm.id_equipe1, sm.id_equipe2)
            WHERE sm.id = $id_match
		", ACCES_TABLE, '');
        extract($res->fetch_assoc());

        $divisions_classes = explode(',', $divisions_classes_str);
//        $divisions = explode(',', $divisions);

        foreach ($divisions_classes as $div_cl) {
            cache::suppress("classement.tableau.$div_cl");
            cache::suppress("joueurs_gardiens.$div_cl%");
        }


        self::$data['stats_acceptees'] = 1;
        self::$data['data_match'] = gestion_matchs::get_un_match($id_match);
        $this->succes();

    }


//    function fn_collecter_stats_manquantes()
//    {
//        /**
//         * @var int $nb_matchs
//         **/
//        extract(self::check_params(
//            'nb_matchs;unsigned'
//        ));
//
//        $res = db::query("
//                SELECT DISTINCT sm.id
//                FROM stats_matchs sm
//                JOIN match_feuille f ON sm.id = f.id_match
//                LEFT JOIN stats_joueurs j on sm.id = j.id_match
//                WHERE j.id IS NULL AND erreur_collection_stats IS NULL
//                ORDER BY sm.date, sm.debut
//                LIMIT $nb_matchs
//            ", ACCES_TABLE);
//
//        if ($res->num_rows == 0) {
//            $this->fin('aucun_match_sans_stats');
//        }
//
//        $ids = db::result_array_one_value($res, 'id');
//        $ids_processed = [];
//        $ids_error = [];
//        debug_print_once('ids: ' . json_encode($ids));
//        foreach ($ids as $id) {
//            try {
//                self::$do_not_die = true;
//                debug_print_once("c********************ollecte $id");
//                $this->collecter_stats($id, true, true);
//                self::$do_not_die = false;
//                $ids_processed[] = $id;
//            } catch (fin_return $http_json_erreur) {
//                $ids_error[] = $id;
////                self::set_data('errors', $ids_error);
////                self::set_data('processed', $ids_processed);
//                debug_print_once('erreur--------->' . print_r($http_json_erreur->getMessage(), 1));
//                $sql_erreur = db::sql_str(substr($http_json_erreur->getMessage(), 0, 500));
//                db::rollback();
//                db::$mysqli->begin_transaction();
//
//                $res = db::query("
//                        UPDATE stats_matchs
//                        SET erreur_collection_stats = $sql_erreur
//                        WHERE id = $id
//                    ", ACCES_TABLE);
//                db::commit();
//                db::$mysqli->begin_transaction();
//            } catch (Exception $erreur) {
//                $ids_error[] = $id;
//                $sql_erreur = db::sql_str(substr($erreur->getMessage(), 0, 500));
//                debug_print_once('=============erreur = ' . $erreur->getMessage());
//                $res = db::query("
//                        UPDATE stats_matchs
//                        SET erreur_collection_stats = $sql_erreur
//                        WHERE id = $id
//                    ", ACCES_TABLE);
//                db::commit();
//                db::$mysqli->begin_transaction();
//            }
//        }
//
//        self::set_data('errors', $ids_error);
//        self::set_data('processed', $ids_processed);
//        debug_print_once(print_r(self::$data, 1));
//        $this->succes();
//
//    }

//    function collecter_stats($id_match, $sauvegarder, $maj_stats_joueurs_seulem = false)
//    {
//
//        if ($sauvegarder) {
//            if (!perm::marqueur_match($id_match)) {
//                $this->non_autorise();
//            }
//        }
//
//        $record = new record_stats_match();
//        $record->load($id_match, 2);
//        if (!$record->is_found) {
//            throw new Exception('introuvable');
//        }
//
//        if ($record->is_forfait()) {
//            throw new Exception('info_collecter_forfait');
//        }
//        if ($record->locked) {
//            throw new Exception('match_verrouille');
//        }
//        # vérifier que chaque periode a une fin et qu'elle est le dernier événement de la période
//        $res = db::dquery("
//                SELECT MAX(chrono) max_chrono,
//                    periode,
//                    MAX(IF(type_enreg = 'fin_periode', chrono, '00:00')) chrono_fin,
//                    COUNT(IF(type_enreg = 'fin_periode', 1, NULL)) nb_fins
//                FROM match_feuille
//                WHERE id_match = $id_match
//                GROUP BY periode
//                ORDER BY periode
//                LOCK IN SHARE MODE
//            ", ACCES_TABLE);
//        $liste_periodes = db::result_array($res);
//        if (count($liste_periodes) == 0) {
//            throw new Exception('aucune_periode_trouvee');
//        }
//        $periodes_trouvees = [];
//        $max_periode = 0;
//        foreach ($liste_periodes as $item) {
//            if ($item['nb_fins'] == 0) {
//                throw new Exception(sprintf(self::msg('manque_fin_per_x'), $item['periode']));
//            } else if ($item['nb_fins'] > 1) {
//                throw new Exception(sprintf(self::msg('plusieurs_fins_per_x'), $item['periode']));
//            } else if ($item['chrono_fin'] < $item['max_chrono']) {
//                throw new Exception(sprintf(self::msg('even_apres_fin_per_x'), $item['periode']));
//            }
//            $periodes_trouvees[] = (int)$item['periode'];
//            $max_periode = (int)$item['periode'];
//        }
//        $periodes_manquantes = array_diff(range(1, $max_periode), $periodes_trouvees);
//
//        if (count($periodes_manquantes)) {
//            throw new Exception(sprintf(self::msg('aucune_donnee_per_x'), implode(', ', $periodes_manquantes)));
//        }
//
//        # maintenant qu'on sait que les fins de périodes sont ok, initialiser classe pour
//        # calcul des temps des gardiens en utilisant ces données
//        temps_gardiens::init($id_match);
//
//
//        $compilation_avantages_numeriques = $this->sauvegarder_an_dn($id_match);
//
//        $buts = $this->get_buts_from_feuille_match($id_match);
//        # retourne [[buts, id_membre, id_equipe] ... ]
//
//        $res_gardiens = $this->get_resultats_gardiens_from_feuille_match($id_match);
//        # retourne [[buts_contre, id_membre, id_equipe], ...]
//
//        $temps_filet = $this->get_temps_filet($id_match);
//        # retourne 'gardiens' => [ id_gardien => secondes de presence, ...]
//        #          'filet_desert_equipes' => [id_equipe => secondes_filet_desert]
//        if (is_string($temps_filet)) {
//            throw new Exception($temps_filet);
//        }
//
//        $temps_gardiens = $temps_filet['gardiens'];
//        $temps_filet_desert_eq = $temps_filet['filet_desert_equipes'];
//
//        //debug_print_once('temps gardiens = ' . print_r($temps_gardiens,1));
//
//        $punitions = $this->get_punitions_from_feuille_match($id_match);
//        # retourne [[duree_punition, id_membre, id_equipe], ...]
//
//        $passes = $this->get_passes_from_feuille_match($id_match);
//        # retourne[[passes, id_membre, id_equipe], ...]
//
//        $buts_filet_vide = $this->get_resultats_filet_vide($id_match);
//        # retourne [[buts_filet_vide, id_equipe], ...]
//
//
//        $joueurs = [];
//        $equipes = [];
//        #debug_print_once("===============================================================");
//        #debug_print_once(print_r(array_merge($buts, $res_gardiens, $punitions, $passes, $buts_filet_vide), 1));
//        foreach (array_merge($buts, $res_gardiens, $punitions, $passes, $buts_filet_vide) as $vals) {
//            if (array_key_exists('id_membre', $vals) and $vals['id_membre']) {
//                $joueurs[] = $vals['id_membre'];
//            }
//            if (array_key_exists('id_equipe', $vals) and $vals['id_equipe']) {
//                $equipes[] = $vals['id_equipe'];
//            }
//        }
//        $joueurs_fm = array_merge(array_unique($joueurs));
//        $equipes_fm = array_merge(array_unique($equipes));
//
//        $presences = $this->get_presences_from_feuille_match($id_match);
//        # retourne [[id_equipe, id_membre, gardien=>0|1], ...]
//
//        $joueurs_presents = [];
//        $equipes_match = [];
//
//        foreach ($presences as $vals) {
//            $joueurs_presents[] = $vals['id_membre'];
//            $equipes_match[] = $vals['id_equipe'];
//        }
//        $joueurs_presents = array_unique($joueurs_presents);
//        $equipes_match = array_merge(array_unique($equipes_match));
//
//
//        if (count($equipes_match) < 2) {
//            throw new Exception('moins_de_2_eq');
//        }
//
//        if (count($equipes_match) > 2) {
//            throw new Exception('plus_de_2_eq');
//        }
//
//        if (count(array_diff($equipes_fm, $equipes_match))) {
//            throw new Exception('equipe_fm_en_trop');
//        }
//
//        $this->verifier_buts_augmentent($id_match, $equipes_match);
//
//        $liste_equipes = implode(',', $equipes_match);
//        $liste_joueurs = implode(',', $joueurs_presents);
//
//        $res = db::dquery("
//            SELECT
//                e.id_equipe,
//                e.nom,
//                n.categ,
//                n.classe,
//                e.id_saison,
//                e.division
//            FROM equipes e
//            JOIN niveaux n USING(niveau)
//
//            WHERE e.id_equipe IN ($liste_equipes)
//
//		", ACCES_TABLE, '');
//        $data_equipes = db::result_array($res, 'id_equipe');
//
//
//        # vérifier que deux équipes de même saison
//        $id_saison = 0;
//        $id_division = 0;
//        foreach ($data_equipes as $data_equipe) {
//            if ($id_saison and $id_saison != $data_equipe['id_saison']) {
//                throw new Exception('equipes_saisons_diff');
//            }
//            if ($id_division and $id_division != $data_equipe['division']) {
//                throw new Exception('equipes_division_diff');
//            }
//            $id_saison = $data_equipe['id_saison'];
//            $id_division = $data_equipe['division'];
//
//        }
//        if (!$id_saison) {
//            throw new Exception('saison_inconnue');
//        }
//
//        # vérifier que joueurs associés à buts et passes moins joueurs dont la présence est indiquée sur feuille de match = rien
//        if (count($diff = array_diff($joueurs_fm, $joueurs_presents))) {
//            self::$data['membres_en_trop'] = $this->get_data_membres($diff, $id_saison, $id_division);
//            throw new Exception('joueurs_fm_en_trop');
//        }
//
//        $data_membres = $this->get_data_membres($liste_joueurs, $id_saison, $id_division);
//        # retourne [[nom, no_chandail, id_membre], ...]
//
//        /**
//         * @var $resultat int
//         * @var $resultat_adversaire int
//         * @var $id_equipe int
//         */
//        $res = db::query("
//            SELECT  resultat, resultat_adversaire, id_equipe
//            FROM match_feuille
//            WHERE id_match = $id_match AND type_enreg = 'but'
//            ORDER BY periode DESC, chrono DESC
//            LIMIT 1
//		", ACCES_TABLE, '');
//        if ($res->num_rows == 0) {
//            $resultat = 0;
//            $resultat_adversaire = 0;
//            $resultats_buts = array($equipes_match[0] => 0, $equipes_match[1] => 0);
//        } else {
//            extract($res->fetch_assoc());
//            foreach ($equipes_match as $eq) {
//                $resultats_buts[$eq] = $resultat_adversaire; # mettre resultat_adversaire aux deux équipes
//            }
//            $resultats_buts[$id_equipe] = $resultat; # corriger le résultat de l'équipe qui a marqué le dernier but
//
//        }
//
//        # resultats_finaux = resultats_buts + fusillade
//        $resultats_finaux = $resultats_buts;
//
//
//        /**
//         * @var $pts1 int
//         * @var $pts2 int
//         * @var $id_equipe1 int
//         * @var $id_equipe2 int
//         */
//
//        extract($record->select('pts1, pts2, id_equipe1, id_equipe2'));
//
//        # NOTE $resultats est le résultat tenant compte des buts comptés normalement, avant une éventuelle fusillade
//        # ce n'est pas le résultat final du match s'il y a fusillade
//        # $resultats doit être en accord avec la somme des buts enregistrés avec type_enreg=but
//
//
//        $res = db::dquery("
//                SELECT id, resultat, resultat_adversaire, id_equipe
//                FROM match_feuille
//                WHERE id_match = $id_match AND type_enreg = 'fusillade'
//            ", ACCES_TABLE);
//        if ($res->num_rows > 1) {
//            throw new Exception('plus_d_une_fusillade');
//        }
//        # s'il y a eu fusillade,
//        $liste_fusillade = false;
//        $gagnant_fusillade = null;
//        if ($res->num_rows == 1) {
//            $resultat_fusillade = $res->fetch_object();
//
//            # vérifier que le match était nul et que le pointage final est ok
//            if ($resultat != $resultat_adversaire) {
//                throw new Exception(sprintf($this->msg('fusillade_match_non_nul'), $resultat, $resultat_adversaire));
//            }
//            # vérifier que le pointage final est un bris d'égalité
//            if (abs($resultat_fusillade->resultat - $resultat_fusillade->resultat_adversaire) != 1) {
//                throw new Exception(sprintf($this->msg('resultat_fusillade_non_bris_egalite'), $resultat_fusillade->resultat, $resultat_fusillade->resultat_adversaire));
//            }
//            # vérifier que la fusillade augmente le pointage de 1
//            $points_gagnant = max($resultat_fusillade->resultat, $resultat_fusillade->resultat_adversaire);
//            if ($points_gagnant - $resultat != 1) {
//                throw new Exception(sprintf($this->msg('fusillade_doit_faire_gagner_1_pt'), $resultat, $points_gagnant));
//            }
//            if (!in_array($resultat_fusillade->id_equipe, $equipes_match)) {
//                throw new Exception('fusillade_mauvaise_eq');
//            }
//            $resultats_finaux[$resultat_fusillade->id_equipe]++;
//
//            # vérifier que la fusillade est ok
//            $res = db::query("
//                    SELECT *
//                    FROM match_feuille_fusillade
//                    WHERE id_fm = $resultat_fusillade->id
//                    ORDER BY ordre
//                ", ACCES_TABLE);
//            if ($res->num_rows == 0) {
//                throw new Exception('fusillade_vide');
//            }
//            $liste_fusillade = db::result_array($res);
//            if ($liste_fusillade[0]['ronde'] != 1) {
//                throw new Exception(sprintf($this->msg('fusillade_doit_commencer_ronde_1'), $liste_fusillade[0]['ronde']));
//            }
//
//
//            # vérifier que fusillade n'implique que joueurs présents et équipes participantes au match
//            # et que la fusillade progresse normalement d'une ronde à la suivante
//            $ronde = 1;
//
//            $buts_fusillade = [$id_equipe1 => 0, $id_equipe2 => 0];
//            foreach ($liste_fusillade as $item) {
//                if (!in_array($item['ronde'], [$ronde, $ronde + 1])) {
//                    throw new Exception(sprintf($this->msg('mauvaise_ronde'), $item['ordre'] + 1));
//                }
//                $ronde = $item['ronde'];
//                if (!$item['id_joueur1'] or !$item['id_joueur2']) {
//                    throw new Exception(sprintf($this->msg('manque_joueur_fusillade'), $item['ordre'] + 1));
//                }
//
//                foreach (['id_joueur1', 'id_joueur2'] as $id_j) {
//                    if (!in_array($item[$id_j], $joueurs_presents)) {
//                        $nom_joueur = $this->nom_membre($item[$id_j]);
//                        throw new Exception(sprintf($this->msg('joueur_fusillade_non_present'), $nom_joueur, $item['ordre'] + 1));
//                    }
//                }
//
//
//                if ($item['but1']) {
//                    $buts_fusillade[$id_equipe1]++;
//                }
//                if ($item['but2']) {
//                    $buts_fusillade[$id_equipe2]++;
//                }
//
//            }
//
//            if ($buts_fusillade[$id_equipe1] == $buts_fusillade[$id_equipe2]) {
//                throw new Exception('fusillade_sans_gagnant');
//            }
//
//            $gagnant_fusillade = ($buts_fusillade[$id_equipe1] > $buts_fusillade[$id_equipe2] ? $id_equipe1 : $id_equipe2);
//            if ($gagnant_fusillade != $resultat_fusillade->id_equipe) {
//                throw new Exception('victoire_a_perdant_fusillade');
//            }
//
//        }
//
//        # vérifier que buts concordent avec resultats AVANT FUSILLADE
//        $resultats_fm = [];
//        #initialiser à zéro pour chaque équipe
//        foreach ($equipes_match as $equipe) {
//            $resultats_fm[$equipe] = 0;
//        }
//        #debug_print_once('Buts = ' . print_r($buts,1));
//        foreach ($buts as $but) {
//            #debug_print_once("ajoute {$but['buts']} à {$but['id_equipe']}");
//            $resultats_fm[$but['id_equipe']] += $but['buts'];
//        }
//
//        #debug_print_once("Résultat calcul fm = " . print_r($resultats_fm,1));
//        #debug_print_once("dernier résultat fm = " . print_r($resultats,1));
//
//        foreach ($resultats_buts as $id_eq => $result) {
//            #debug_print_once("compare resultats pour equipe $id_eq = $result avec {$resultats_fm[$id_eq]}");
//            if ($resultats_fm[$id_eq] != $result) {
//                self::$data['resultats_fm'] = $resultats_fm;
//                self::$data['resultats_inscrits'] = $resultats_buts;
//                self::$data['data_equipes'] = $data_equipes;
//                #debug_print_once("pas egal");
//                throw new Exception('divergence_resultats');
//            }
//        }
//
//        # vérifier que si des résultats ont été entrés, ils concordent avec les buts trouvés sur la feuille de match
//        if (!is_null($pts1) or !is_null($pts2)) {
//            if ($resultats_finaux[$id_equipe1] != $pts1 or $resultats_finaux[$id_equipe2] != $pts2) {
//                self::$data['resultats_stats_match'] = array($id_equipe1 => $pts1, $id_equipe2 => $pts2);
//                self::$data['resultats_fm'] = $resultats_finaux;
//                self::$data['data_equipes'] = $data_equipes;
//                throw new Exception('resultats_different_stats_match');
//            }
//        }
//        if (is_string($temps_gardiens)) {
//            self::$data['err_temps_gardien'] = $temps_gardiens;
//            throw new Exception('temps_gardien');
//        }
//        debug_print_once('=========================================');
//
//        if (!$sauvegarder) {
//            return [
//                'buts' => $buts,
//                'passes' => $passes,
//                'punitions' => $punitions,
//                'res_gardiens' => $res_gardiens,
//                'data_membres' => $data_membres,
//                'data_equipes' => $data_equipes,
//                'buts_filet_vide' => $buts_filet_vide,
//                'resultat_match' => $resultats_finaux,
//                'temps_gardiens' => $temps_gardiens,
//                'temps_filet_desert_eq' => $temps_filet_desert_eq,
//                'compilation_avantages_num' => $compilation_avantages_numeriques,
//                'fusillade' => $liste_fusillade,
//                'id_equipe1' => $id_equipe1,
//                'id_equipe2' => $id_equipe2,
//                'gagnant_fusillade' => $gagnant_fusillade
//            ];
//
//        }
////        debug_print_once(print_r([
////            'buts' => $buts,
////            'passes' => $passes,
////            'punitions' => $punitions,
////            'res_gardiens' => $res_gardiens,
////            'data_membres' => $data_membres,
////            'data_equipes' => $data_equipes,
////            'buts_filet_vide' => $buts_filet_vide,
////            'resultat_match' => $resultats_finaux,
////            'temps_gardiens' => $temps_gardiens,
////            'temps_filet_desert_eq' => $temps_filet_desert_eq,
////            'compilation_avantages_num' => $compilation_avantages_numeriques,
////            'fusillade' => $liste_fusillade,
////            'id_equipe1' => $id_equipe1,
////            'id_equipe2' => $id_equipe2,
////            'gagnant_fusillade' => $gagnant_fusillade
////        ], 1));
//        $data = [];
//
//
//        $pts = array_values($resultats_fm);
//
//        if ($pts[0] == $pts[1]) {
//            $mult = 0;
//        } else {
//            $mult = 1;
//        }
//
//        $resultat_gardien = array();
//        $max = max($pts[0], $pts[1]);
//        foreach ($resultats_fm as $id_equipe => $val) {
//            $resultat_gardien[$id_equipe] = ($val == $max ? 1 : -1) * $mult; # resultat = 1 pour pointage max; -1 pour minimumSecondes, 0 pour nulle
//        }
//
//
//        foreach ($buts as $but) {
//            $data[$but['id_membre']][$but['id_equipe']]['buts'] = $but['buts'];
//        }
//        foreach ($passes as $passe) {
//            $data[$passe['id_membre']][$passe['id_equipe']]['passes'] = $passe['passes'];
//        }
//        foreach ($punitions as $punition) {
//            $data[$punition['id_membre']][$punition['id_equipe']]['min_punition'] = $punition['duree_punition'];
//        }
//        #debug_print_once("res gardiens = " . print_r($res_gardiens,1));
//        foreach ($res_gardiens as $res_gardien) {
//            $data[$res_gardien['id_membre']][$res_gardien['id_equipe']]['buts_contre'] = $res_gardien['buts_contre'];
//            if (!array_key_exists($res_gardien['id_equipe'], $resultat_gardien)) {
//                throw new Exception('resultat_gardien_eq_introuvable');
//            }
//            # le resultat_gardien est lié à
//            #$data[$res_gardien['id_membre']][$res_gardien['id_equipe']]['resultat_gardien'] = $resultat_gardien[$res_gardien['id_equipe']];
//        }
//        #debug_print_once(print_r($data,1));
//        $res = db::query("
//            UPDATE stats_joueurs
//            SET marque = 1
//            WHERE id_match = $id_match
//
//		", ACCES_TABLE, '');
//
//        $empty_record = [
//            'buts' => 0,
//            'buts_contre' => 0,
//            'passes' => 0,
//            'min_punition' => 0,
//            'resultat_gardien' => 'null',
//            'temps_filet' => 0
//        ];
//
//        $stats_a_sauvegarder = [];
//        foreach ($presences as $joueur) {
//            $fld_vals = [];
//            $fld_vals[] = $joueur['id_membre'];
//            $fld_vals[] = $id_match;
//            $fld_vals[] = $joueur['id_equipe'];
//
//            $stats = array_merge($empty_record, isset($data[$joueur['id_membre']][$joueur['id_equipe']]) ? $data[$joueur['id_membre']][$joueur['id_equipe']] : []);
//
//            if ($joueur['gardien']) {
//                $stats['resultat_gardien'] = $resultat_gardien[$joueur['id_equipe']];
//                $stats['temps_filet'] = $temps_gardiens[$joueur['id_membre']];
//            }
//
//            foreach (['buts', 'buts_contre', 'passes', 'min_punition', 'resultat_gardien', 'temps_filet'] as $ind) {
//                $fld_vals[] = $stats[$ind];
//            }
//            $fld_vals[] = '0'; # marque
//
//            $stats_a_sauvegarder[] = implode(',', $fld_vals);
//        }
//        $stats_a_sauvegarder = implode('),(', $stats_a_sauvegarder);
//
//        $res = db::dquery("
//            INSERT IGNORE INTO stats_joueurs
//            (id_membre,id_match,id_equipe,buts,buts_contre,passes,min_punition,resultat_gardien,temps_filet,marque)
//            VALUES
//            ($stats_a_sauvegarder)
//            ON DUPLICATE KEY UPDATE
//            buts =          VALUES(buts),
//            buts_contre =   VALUES(buts_contre),
//            passes =        VALUES(passes),
//            min_punition =  VALUES(min_punition),
//            resultat_gardien = VALUES(resultat_gardien),
//            temps_filet = VALUES(temps_filet),
//            marque =        VALUES(marque)
//		", ACCES_TABLE, '');
//
//        $res = db::query("
//            DELETE FROM stats_joueurs
//            WHERE id_match = $id_match
//                AND  marque
//		", ACCES_TABLE, '');
//
//        $buts_filet_vide1 = 0;
//        $buts_filet_vide2 = 0;
//        if (count($buts_filet_vide)) {
//            foreach ($buts_filet_vide as $buts) {
//                if ($buts['id_equipe'] == $id_equipe1) {
//                    $buts_filet_vide1 += $buts['buts_filet_vide'];
//                } else {
//                    $buts_filet_vide2 += $buts['buts_filet_vide'];
//                }
//            }
//        }
//
//        # stocker info d'avantages/désavantages numériques
//        if (!$maj_stats_joueurs_seulem) {
//            $info_an_dn = [];
//            foreach ($compilation_avantages_numeriques['data_equipes'] as $an_dn_equipe) {
//                if (!array_key_exists('id_equipe', $an_dn_equipe)) {
//                    continue;
//                }
//                $id_equipe = $an_dn_equipe['id_equipe'];
//                if ($id_equipe == $id_equipe1) {
//                    $no = '1';
//                } else if ($id_equipe == $id_equipe2) {
//                    $no = '2';
//                } else {
//                    throw new Exception('echec_compilation_an_dn');
//                }
//                if ($no == '1') { # champs temps_an2 et temps_dn2 n'existent pas...
//                    $info_an_dn["temps_an$no"] = $an_dn_equipe['an']['temps'];
//                    $info_an_dn["temps_dn$no"] = $an_dn_equipe['dn']['temps'];
//                }
//                $info_an_dn["buts_an$no"] = $an_dn_equipe['an']['buts'];
//                $info_an_dn["buts_dn$no"] = $an_dn_equipe['dn']['buts'];
//            }
//
//
//            $record->update(array_merge([
//                    'sj_ok1' => 1,
//                    'sj_ok2' => 1,
//                    'pts1' => $resultats_finaux[$id_equipe1],
//                    'pts2' => $resultats_finaux[$id_equipe2],
//                    'buts_filet_vide1' => $buts_filet_vide2,
//                    'buts_filet_vide2' => $buts_filet_vide1,
//                    'duree_match' => $compilation_avantages_numeriques['duree_match'],
//                    'erreur_collection_stats' => null
//                ],
//                    $info_an_dn
//                )
//            );
//            cache::suppress("feuille_match.$id_match");
//        } else {
//            $record->update(['erreur_collection_stats' => null]);
//        }
//
//
//        /**
//         * @var $divisions_classes_str string
//         * @var $divisions string
//         */
//
//        $res = db::query("
//            SELECT
//                GROUP_CONCAT(CONCAT(e.division, '.', e.classe) SEPARATOR ',') divisions_classes_str,
//               GROUP_CONCAT(e.division SEPARATOR ',') divisions
//            FROM equipes e
//            JOIN stats_matchs sm ON e.id_equipe IN (sm.id_equipe1, sm.id_equipe2)
//            WHERE sm.id = $id_match
//		", ACCES_TABLE, '');
//        extract($res->fetch_assoc());
//
//        $divisions_classes = explode(',', $divisions_classes_str);
//        $divisions = explode(',', $divisions);
//
//        foreach ($divisions_classes as $div_cl) {
//            cache::suppress("classement.tableau.$div_cl");
//            cache::suppress("joueurs_gardiens.$div_cl%");
//        }
//
//
//        return [
//            'stats_acceptees' => 1,
//            'data_match' => $this->get_un_match(null, $id_match)
//
//        ];
//
//    }

    function nom_membre($id)
    {
        /**
         * @var string $nom
         **/
        $res = db::query("
                SELECT CONCAT(nom, ', ', prenom) nom
                FROM membres
                WHERE id = $id
            ", ACCES_TABLE);
        if ($res->num_rows == 0) {
            return '';
        }
        extract($res->fetch_assoc());
        return ucwords($nom);
    }

    function fn_sauvegarder_an_dn()
    {
        /**
         * @var $id_match int
         */
        extract(self::check_params(
            'id_match;unsigned'
        ));
        $record = new record_stats_match();
        $record->load($id_match, 1);
        if ($record->locked) {
            $this->fin('match_verrouille');
        }
        $compilation = $this->sauvegarder_an_dn($id_match);
        self::$data['an'] = $compilation['ids_an'];
        self::$data['dn'] = $compilation['ids_dn'];
        $this->succes();
    }

    function sauvegarder_an_dn($id_match)
    {
        $compilation_avantages_numeriques = $this->get_avantages_numeriques_from_feuille_match($id_match);

        if ($compilation_avantages_numeriques['ids_an'] or $compilation_avantages_numeriques['ids_dn']) {

            if (count($compilation_avantages_numeriques['ids_an'])) {
                $liste_an = implode(',', $compilation_avantages_numeriques['ids_an']);
            } else {
                $liste_an = '-1';
            }

            if (count($compilation_avantages_numeriques['ids_dn'])) {
                $liste_dn = implode(',', $compilation_avantages_numeriques['ids_dn']);
            } else {
                $liste_dn = '-1';
            }

            db::query("
                    UPDATE match_feuille mf
                    SET avantage_numerique = IF(id IN ($liste_an), 1, IF(id IN ($liste_dn), -1, 0))
                     
                    WHERE id_match = $id_match
                    
                ", ACCES_TABLE, '');

        } else {
            db::query("
                UPDATE match_feuille
                SET 
                    avantage_numerique = 0
                WHERE id_match = $id_match
            ", ACCES_TABLE, '');
        }

        return $compilation_avantages_numeriques;

    }

    function get_equipe_joueur_pour_match($id_match)
    {
        $res = db::query("
            SELECT id_joueur,
                id_equipe,
                position
            FROM match_joueurs
            WHERE id_match  = $id_match
		", ACCES_TABLE, '');
        return db::result_array($res, 'id_joueur');
    }

    function get_data_membres($liste, $id_saison, $id_division)
    {
        if (is_array($liste)) {
            $liste = implode(',', $liste);
        }
        $res = db::query("
            SELECT CONCAT(m.nom, ', ', m.prenom, IF(!ISNULL(dj.no_chandail), CONCAT(' [#', dj.no_chandail, ']'), '')) nom,
                dj.no_chandail,
                m.id id_membre
            FROM membres m
            LEFT JOIN dossier_joueur dj ON m.id = dj.id_joueur AND dj.saison = $id_saison AND dj.id_division = $id_division
            WHERE m.id IN ($liste)
		", ACCES_TABLE, '');
        return db::result_array($res, 'id_membre');

    }

    function verifier_buts_augmentent($id_match, $equipes)
    {
        $res = db::query("
            SELECT periode, chrono, resultat, resultat_adversaire, id_equipe
            FROM match_feuille
            WHERE id_match = $id_match and type_enreg = 'but'
            ORDER BY periode, chrono
            
		", ACCES_TABLE, '');
        if ($res->num_rows == 0) {
            return true;
        }

        $results = new deux_eq($equipes);

        while ($row = $res->fetch_assoc()) {
            /**
             * @var $periode int
             * @var $chrono string
             * @var $resultat int
             * @var $resultat_adversaire int
             * @var $id_equipe int
             */
            extract($row);
            # si la marque de l'équipe qui compte n'a pas augmenté de 1 depuis le dernier but
            # ou si la marque de l'équipe qui n'a pas compté a changé depuis le dernier but
            if ($resultat != $results->val($id_equipe) + 1 or $resultat_adversaire != $results->val_autre($id_equipe)) {
//                $resultat_avant = $results->val($id_equipe);
//                $resultat_adv = $results->val_autre($id_equipe);


                self::set_data('erreur', [
                    'type' => 'progression_pointage_incorrecte',
                    'msg' => 'Un but doit faire monter le pointage d\'une seule équipe d\'un seul point',
                    'periode' => $periode,
                    'chrono' => $chrono
                ]);
//                self::$data['periode'] = $periode;
//                self::$data['chrono'] = $chrono;
                $this->fin();
            }
            $results->val($id_equipe, $resultat);
        }


    }

    function get_buts_from_feuille_match($id_match)
    {
        $res = db::query("
            SELECT COUNT(*) buts,
                mf.id_equipe,
                # COUNT(IF(mf.but_propre_filet, 1, NULL)) buts_propre_filet,
                mf.id_membre
                
            FROM match_feuille mf
            WHERE mf.id_match = $id_match AND mf.type_enreg = 'but'
            GROUP BY mf.id_equipe, mf.id_membre
            
		", ACCES_TABLE, '');

        return db::result_array($res);
    }

    function get_passes_from_feuille_match($id_match)
    {
        $res = db::query("
            SELECT 
                SUM(passes) passes,
                id_equipe,
                id_membre
            FROM (

                SELECT COUNT(*) passes,
                    mf.id_equipe,
                    mf.id_membre_passe2 id_membre
                FROM match_feuille mf
                WHERE mf.id_match = $id_match AND mf.type_enreg = 'but' and mf.id_membre_passe2
                GROUP BY mf.id_equipe, mf.id_membre_passe2

                UNION ALL

                SELECT COUNT(*) passes,
                    mf.id_equipe,
                    mf.id_membre_passe id_membre

                FROM match_feuille mf
                WHERE mf.id_match = $id_match AND mf.type_enreg = 'but' and mf.id_membre_passe
                GROUP BY mf.id_equipe, mf.id_membre_passe
                ) liste
            GROUP BY id_equipe, id_membre
            
		", ACCES_TABLE, '');
        return db::result_array($res);
    }

    function get_avantages_numeriques_from_feuille_match($id_match)
    {
        /**
         * @var $id_equipe1 int
         * @var $id_equipe2 int
         */

        $res = db::query("
            SELECT id_equipe1, id_equipe2
            FROM stats_matchs
            WHERE id = $id_match
		", ACCES_TABLE, '');

        if ($res->num_rows == 0) {
            $this->fin('introuvable');
        }
        extract($res->fetch_assoc());

        $punitions_avec_an = cfg_yml::get('matchs', 'temps_punitions_avantage_num');
        if ($punitions_avec_an) {
            if (!preg_match('#^\d+(,\d+)*$#', $punitions_avec_an)) {
                $this->fin('Liste de temps de punition avec avantage numérique mal spécifiée');
            }
            $filtre_punitions = "mf.duree_punition IN ($punitions_avec_an)";
        } else {
            $filtre_punitions = '1';
        }

        $res = db::query("
            SELECT
                mf.id,
                periode, 
                chrono, 
                duree_punition,
                # expulsion,
                id_equipe,
                IF(id_equipe = sm.id_equipe1, 1, 2) no_equipe,
                IF(type_enreg = 'but', 1, NULL) but
            FROM match_feuille mf
            JOIN stats_matchs sm ON sm.id = mf.id_match
            WHERE mf.id_match = $id_match AND 
                #(($filtre_punitions AND NOT expulsion) OR mf.type_enreg = 'but')
                ($filtre_punitions OR mf.type_enreg = 'but')
            ORDER BY periode, chrono
		", ACCES_TABLE, '');

        $compilation = new compilation_avantages_numeriques($id_match);


        if ($res->num_rows == 0) {
            return [
                'avantage_num' => [
                    ['id_equipe' => $id_equipe1, 'temps' => 0, 'buts' => 0],
                    ['id_equipe' => $id_equipe2, 'temps' => 0, 'buts' => 0]
                ],
                'desavantage_num' => [
                    ['id_equipe' => $id_equipe1, 'temps' => 0, 'buts' => 0],
                    ['id_equipe' => $id_equipe2, 'temps' => 0, 'buts' => 0]
                ],
                'ids_an' => [],
                'ids_dn' => []

            ];
        }


        while ($row = $res->fetch_assoc()) {
            if ($row['but']) {
                $compilation->ajout_but($row['periode'], $row['chrono'], $row['no_equipe'] == '1', $row['id']);
//            } else if ($row['expulsion']){
//                continue;
                #$compilation->ajout_expulsion($row['periode'], $row['chrono'], $row['no_equipe'] == '1');
            } else {
                # seule autre possibilité est que c'est  une punition de durée donnée
                $compilation->ajout_punition($row['periode'], $row['chrono'], 60 * $row['duree_punition'], $row['no_equipe'] == '1');
            }
        }

        $compilation->compiler();

        #debug_print_once(print_r($compilation, 1));
        $to_ret = [
            'data_equipes' =>
                [
                    [
                        'id_equipe' => $id_equipe1,
                        'an' => [
                            'temps' => $compilation->temps_avantage_num,
                            'buts' => $compilation->buts_an
                        ],
                        'dn' => [
                            'temps' => $compilation->temps_desavantage_num,
                            'buts' => $compilation->buts_dn
                        ]
                    ],
                    [
                        'id_equipe' => $id_equipe2,
                        'an' => [
                            'temps' => $compilation->temps_desavantage_num,
                            'buts' => $compilation->buts_an_autre_eq
                        ],
                        'dn' => [
                            'temps' => $compilation->temps_avantage_num,
                            'buts' => $compilation->buts_dn_autre_eq
                        ]
                    ]
                ],

            'ids_an' => $compilation->avantages_num,
            'ids_dn' => $compilation->desavantages_num,
            'duree_match' => $compilation->duree_match

        ];
        return $to_ret;
    }

    function get_resultats_gardiens_from_feuille_match($id_match)
    {
        $res = db::query("
            SELECT COUNT(*) buts_contre,
                IFNULL(mj.id_equipe, 0) id_equipe,
                mf.id_membre_gardien id_membre
                
            FROM match_feuille mf
            LEFT JOIN match_joueurs mj ON mf.id_membre_gardien = mj.id_joueur AND mj.id_match = $id_match
            WHERE mf.id_match = $id_match AND mf.type_enreg = 'but' AND mf.id_membre_gardien
            GROUP BY mf.id_equipe, mf.id_membre_gardien
		", ACCES_TABLE, '');

        return db::result_array($res);

    }

    function get_temps_filet($id_match)
    {
        # 1) vérifier combien de gardiens il y a par équipe. Si un seul, assumer qu'ils sont devant leur filet
        # au début
        $res = db::query("
            SELECT id_equipe, GROUP_CONCAT(id_joueur SEPARATOR ',') gardiens
            FROM match_joueurs
            WHERE id_match = $id_match
                AND position = 2
            GROUP BY id_equipe
		", ACCES_TABLE, '');

        if ($res->num_rows == 0) {
            return 'Aucun gardien';
        }
        $gardiens = new gardiens(db::result_array($res), self::msg()); // fetchall

        $res = db::query("
            SELECT IF(type_enreg = 'but', 1, NULL) but,
                id_equipe, 
                id_membre_gardien gardien_dejoue, 
                gardien1, 
                gardien2, 
                IF(type_enreg = 'changement_gardien', 1, NULL) changement_gardien,
                periode, 
                chrono
            FROM match_feuille
            WHERE id_match = $id_match AND type_enreg IN ('but', 'changement_gardien' )
            ORDER BY periode, chrono
            
		", ACCES_TABLE, '');

        $max_periode = null;
        $max_chrono = null;
        if ($res->num_rows) {
            $premier = true;
            while ($row = $res->fetch_assoc()) {
                if ($premier and !$gardiens->buts_occupes() and !$row['changement_gardien']) {
                    return self::msg('designez_gardiens_au_debut');
                }
                $premier = false;
                $max_periode = $row['periode'];
                $max_chrono = $row['chrono'];
                if ($row['but']) {
                    $gardiens->verifier_but($row['id_equipe'], $row['gardien_dejoue']);

                } else {
                    $gardiens->set_gardiens($row['periode'], $row['chrono'], $row['gardien1'], $row['gardien2']);
                }
                if ($gardiens->erreur) {
                    return $gardiens->erreur . sprintf(self::msg('per_chrono'), $row['periode'], $row['chrono']);
                }
            }
        } else {
            if (!$gardiens->buts_occupes()) {
                return self::msg('designez_gardiens_au_debut');
            }
        }

//        if (!$max_periode or $max_periode <= cfg_yml::get('matchs', 'nb_periodes')){
//            $periode_fin_match = cfg_yml::get('matchs', 'nb_periodes');
//            $chrono_fin_match = cfg_yml::get('matchs', 'duree_periodes') . ':00';
//        } else {
//            $periode_fin_match = $max_periode;
//            $chrono_fin_match = $max_chrono;
//        }
//
//        if ($gardiens->au_moins_un_but_occupe()){
//           $gardiens->set_gardiens($periode_fin_match, $chrono_fin_match, null, null);
//        }
//        $duree_match = temps_gardiens::chrono_to_temps($periode_fin_match, $chrono_fin_match);

        $gardiens->finir_match();

        $temps_filet_desert_eq = $gardiens->get_temps_filet_garde_equipe();
        foreach ($temps_filet_desert_eq as $id=>&$val) {
            $val = [$id, temps_gardiens::$duree_match - $val];
        }
        unset($val);

        return [
            'gardiens' => $gardiens->get_temps_gardiens(),
            'filet_desert_equipes' => array_values($temps_filet_desert_eq)
        ];

    }

    function get_resultats_filet_vide($id_match)
    {
        $res = db::query("
            SELECT COUNT(*) buts_filet_vide,
                mf.id_equipe,
                mf.id_membre
                
            FROM match_feuille mf
            WHERE mf.id_match = $id_match AND mf.type_enreg = 'but' AND mf.id_membre_gardien IS NULL
            GROUP BY mf.id_equipe, mf.id_membre
            
		", ACCES_TABLE, '');

        return db::result_array_group_pairs($res, 'id_equipe');

    }

    function get_punitions_from_feuille_match($id_match)
    {
        $res = db::query("
            SELECT SUM(mf.duree_punition) duree_punition,
                mf.id_equipe,
                mf.id_membre
                
            FROM match_feuille mf
            WHERE mf.id_match = $id_match
            GROUP BY mf.id_equipe, mf.id_membre
            HAVING duree_punition > 0
            ORDER BY duree_punition
		", ACCES_TABLE, '');

        return db::result_array($res);

    }

    function get_presences_from_feuille_match($id_match)
    {
        $res = db::query("
            SELECT id_equipe, 
                id_joueur id_membre,
                if(position = 2, 1, 0) gardien
            FROM match_joueurs mj
            WHERE id_match = $id_match
		", ACCES_TABLE, '');
        return db::result_array($res);
    }

}

class deux_eq
{
    public $equipe1, $equipe2;

    public $somme1 = 0, $somme2 = 0;

    function __construct($equipes)
    {
        // debug_print_once(print_r($equipes, 1));
        $this->equipe1 = $equipes[0];
        $this->equipe2 = $equipes[1];
    }

    function autre($id)
    {
        return ($id == $this->equipe1) ? $this->equipe2 : $this->equipe1;
    }

    function val($id, $new_val = null)
    {
        if (is_null($new_val)) {
            if ($id == $this->equipe1) {
                return $this->somme1;
            }
            return $this->somme2;
        }
        if ($id == $this->equipe1) {
            $this->somme1 = $new_val;
            return $new_val;
        }
        $this->somme2 = $new_val;
        return $new_val;

    }

    function val_autre($id, $new_val = null)
    {
        return $this->val($this->autre($id), $new_val);
    }

    function result_string()
    {
        return "$this->equipe1: $this->somme1;  $this->equipe2: $this->somme2";
    }

}

class temps_gardiens
{
    static $temps_debut = [1 => 0];
    static $init = false;
    static $duree_match;


    static function init($id_match)
    {
        $res = db::query("
                    SELECT periode, chrono
                    FROM match_feuille
                    WHERE id_match = $id_match AND type_enreg = 'fin_periode' AND periode > 0
                    ORDER BY periode
                ", ACCES_TABLE);
        $liste = db::result_array($res);
        $total = 0;
        foreach ($liste as $item) {
            $temps = ((int)substr($item['chrono'], 0, 2)) * 60 + (int)substr($item['chrono'], 3);
            $total += $temps;
            self::$temps_debut[$item['periode'] + 1] = $total;
        }
        self::$duree_match = $total;

    }

    static function chrono_to_temps($periode, $chrono)
    {
        if (($pos = strpos($chrono, ':')) === false) {
            $temps_periode = 0;
        } else {
            $minutes = substr($chrono, 0, $pos);
            $minutes = $minutes ? $minutes : 0;
            $secondes = substr($chrono, $pos + 1);
            $secondes = $secondes ? $secondes : 0;
            $temps_periode = $minutes * 60 + $secondes;
        }


        #debug_print_once("Calcul de durée  période $periode ; chrono $chrono = $temps_periodes_prec pour pér précédente(s) + $temps_periode pour cette période = " . ($temps_periodes_prec + $temps_periode));
        return self::$temps_debut[$periode] + $temps_periode;

    }

}

class un_gardien extends temps_gardiens
{
    public $id_equipe, $id_gardien;
    public $periodes = [];
    public $debut_periode;

    public $total = 0;

    function __construct($id_gardien, $id_equipe)
    {

        $this->id_gardien = $id_gardien;
        $this->id_equipe = $id_equipe;
    }

    function is_actif()
    {
        return !is_null($this->debut_periode);
    }

    function activer($periode, $chrono = '00:00')
    {
        if ($periode == 0) {
            $this->debut_periode = 0;
            return;
        }
        if (is_null($this->debut_periode)) {
            $this->debut_periode = self::chrono_to_temps($periode, $chrono);
        }
        #debug_print_once("Gardien $this->id_gardien : debut = $periode / $chrono = $this->debut_periode");
    }

    function retirer($periode, $chrono)
    {
        if (is_null($this->debut_periode)) {
            return;
        }
        #debug_print_once("Gardien $this->id_gardien : fin = $periode / $chrono = " . self::chrono_to_temps($periode, $chrono));
        $this->periodes[] = [$this->debut_periode, $fin = self::chrono_to_temps($periode, $chrono)];
        $this->total += $fin - $this->debut_periode;

        $this->debut_periode = null;
    }

    function recalcule_total()
    {
        if (!is_null($this->debut_periode)) {
            debug_print_once('recalc total ferme periode à ' . self::$duree_match);
            $this->periodes[] = [$this->debut_periode, self::$duree_match];
            $this->debut_periode = null;
        }
        $this->total = 0;
        foreach ($this->periodes as $vals) {
            $this->total += $vals[1] - $vals[0];
        }
        debug_print_once('total = ' . $this->total);
        return $this->total;
    }

    function finir_match()
    {
        if (!is_null($this->debut_periode)) {
            $this->periodes[] = [$this->debut_periode, self::$duree_match];
            $this->total += self::$duree_match - $this->debut_periode;
            $this->debut_periode = null;
        }
    }

}

class gardiens extends temps_gardiens
{
    /**
     * @var $this ->gardiens array(un_gardien)
     */
    public $gardiens = [];

    public $gardien_actif_equipe = []; // id_equipe => objet_gardien. Max deux elements évidemment

    public $erreur = '';

    public $autre_equipe = []; # id_equipe => id_autre_equipe

    public $equipes = [];
    public $msgs;

    #config = id_equipe=> comma_separated_ids, id_equipe=> comma_...
    function __construct($config, $msgs)
    {

        $equipes = [];
        foreach ($config as $vals) {
            $id_equipe = $vals['id_equipe'];
            foreach (explode(',', $vals['gardiens']) as $id_gardien) {
                #debug_print_once("crée gardien $id_gardien pour equipe $id_equipe");
                $this->gardiens[$id_gardien] = new un_gardien($id_gardien, $id_equipe);
                $this->gardien_actif_equipe[$id_equipe] = 0;
            }
            $equipes[] = $id_equipe;
        }
        if (count($this->gardien_actif_equipe) != 2) {
            $this->erreur = 'un_gardien_par_eq_minimum';
            return;
        }
        # si juste deux gardiens, alors les mettre devant le filet au début de la partie

        if (count($this->gardiens) == 2) {

            foreach ($this->gardiens as $gardien) {
                $this->gardien_actif_equipe[$gardien->id_equipe] = $gardien;
                $gardien->activer(0);
            }
        }

        $equipes = array_merge(array_unique($equipes));
        $this->autre_equipe[$equipes[0]] = $equipes[1];
        $this->autre_equipe[$equipes[1]] = $equipes[0];
        $this->equipes = $equipes;
        $this->msgs = $msgs;


    }

    function buts_occupes()
    {
        foreach ($this->gardien_actif_equipe as $gardien_actif) {
            if (!$gardien_actif) {
                return false;
            }
        }
        return true;
    }

    function au_moins_un_but_occupe()
    {
        foreach ($this->gardien_actif_equipe as $gardien_actif) {
            if ($gardien_actif) {
                return true;
            }
        }
        return false;
    }

    function verifier_but($equipe_but, $gardien_dejoue)
    {
        $gardien_actif_autre_equipe = $this->gardien_actif_equipe[$this->autre_equipe[$equipe_but]];
        if (!$gardien_dejoue and !$gardien_actif_autre_equipe) {
            debug_print_once('but vilet désert ok');
            return true;
        }
        #debug_print_once("Verifier but equipe $equipe_but avec gardien dejoue = $gardien_dejoue");
        #debug_print_once("Gardien actif autre eq = $gardien_actif_autre_equipe->id_gardien");
        if ($gardien_actif_autre_equipe and $gardien_actif_autre_equipe->id_gardien == $gardien_dejoue) {
            return true;
        }
        $this->erreur = $this->msgs['gardien_dejoue_pas_actif'];
        return false;
    }

    function gardiens_connus($id_gardien1, $id_gardien2)
    {
        $equipes = [];
        if ($id_gardien1) {
            if (!array_key_exists($id_gardien1, $this->gardiens)) {
                return false;
            }
            $equipes[] = $this->gardiens[$id_gardien1]->id_equipe;
        }

        if ($id_gardien2) {
            if (!array_key_exists($id_gardien2, $this->gardiens)) {
                return false;
            }
            $equipes[] = $this->gardiens[$id_gardien2]->id_equipe;
        }
        return $equipes;

    }

    function set_gardiens($periode, $chrono, $id_gardien1, $id_gardien2)
    {
        # 1) vérifier que les deux gardiens sont connus et d'équipes différentes
        $equipes = $this->gardiens_connus($id_gardien1, $id_gardien2);
        if ($equipes === false) {
            $this->erreur = sprintf($this->msgs['gardien_inconnu'], $periode, $chrono);
            return false;
        }

        if (count($equipes) == 2) {
            if (count(array_unique($equipes)) == 1) {
                $this->erreur = sprintf($this->msgs['deux_gardiens_meme_equipe'], $periode, $chrono);
                return false;
            }
        }
        foreach ([$id_gardien1, $id_gardien2] as $id_gardien) {
            $this->activer_gardien($id_gardien, $periode, $chrono);
        }
        $equipes_sans_gardien = array_diff($this->equipes, $equipes);

        foreach ($equipes_sans_gardien as $id_equipe) {
            $this->retirer_gardien_equipe($id_equipe, $periode, $chrono);
        }

        return true;
    }

    function activer_gardien($id_gardien, $periode, $chrono)
    {
        if (!$id_gardien) {
            return;
        }
        /**
         * @var $gardien_actif un_gardien
         */
        #debug_print_once("activer gardien $id_gardien per $periode @ $chrono");
        $equipe_gardien = $this->gardiens[$id_gardien]->id_equipe;
        $gardien_actif = $this->gardien_actif_equipe[$equipe_gardien];

        if ($gardien_actif) {
            if ($id_gardien == $gardien_actif->id_gardien) {
                #debug_print_once("pas de chg gardien $id_gardien");
                #debug_print_once(print_r($gardien_actif,1));
                return;
            }
            $gardien_actif->retirer($periode, $chrono);
        }
        $this->gardien_actif_equipe[$equipe_gardien] = $this->gardiens[$id_gardien];
        $this->gardiens[$id_gardien]->activer($periode, $chrono);
        #debug_print_once(print_r($this->gardiens[$id_gardien],1));

    }

    function retirer_gardien_equipe($id_equipe, $periode, $chrono)
    {
        if (($gardien_actif = $this->gardien_actif_equipe[$id_equipe])) {
            #debug_print_once("retire gardien equipe $id_equipe per $periode @ $chrono");
            $gardien_actif->retirer($periode, $chrono);
            $this->gardien_actif_equipe[$id_equipe] = null;
            #debug_print_once(print_r($gardien_actif,1));
        } else {
            #debug_print_once("Pas de gardien actif");
        }
    }

    function get_temps_gardiens()
    {
        if ($this->erreur) {
            return $this->erreur;
        }
        $to_ret = [];

        foreach ($this->gardiens as $id_gardien => $gardien_obj) {
            $to_ret[] = [$id_gardien, $gardien_obj->total];
        }
        return $to_ret;
    }

    function get_temps_filet_garde_equipe()
    {
        $to_ret = [];
        foreach ($this->gardiens as $gardien_obj) {
            $id_equipe = $gardien_obj->id_equipe;
            if (!array_key_exists($id_equipe, $to_ret)) {
                $to_ret[$id_equipe] = 0;
            }
            $to_ret[$id_equipe] += $gardien_obj->total;
        }
        return $to_ret;
    }

    function finir_match()
    {
        foreach ($this->gardiens as $gardien) {
            $gardien->finir_match();
            #debug_print_once('Fin match ' . print_r($gardien,1) );
        }
    }


}

class compilation_avantages_numeriques
{
    public $liste = []; # [temps => class changement_punition, ...]
    public $duree_periode;
    public $duree_match = 0;
    public $temps_avantage_num = 0;
    public $temps_desavantage_num = 0;
    public $buts_an = 0;
    public $buts_dn = 0;
    public $buts_an_autre_eq = 0;
    public $buts_dn_autre_eq = 0;
    public $desavantages_num; # = [id_fm, ...]
    public $avantages_num;
    public $temps_punitions_mineures = []; # durée en s des punitions auxquelles un but en AN met fin
    public $punitions_mineures = [];

    function __construct($id_match)
    {
        $this->duree_periode = 60 * cfg_yml::get('matchs', 'duree_periodes');
        $this->nb_periodes = cfg_yml::get('matchs', 'nb_periodes');


        $this->temps_punitions_mineures = cfg_yml::get('matchs', 'temps_punitions_annulees_par_but');

        if ($this->temps_punitions_mineures) {
            $this->temps_punitions_mineures = explode(',', $this->temps_punitions_mineures);
            foreach ($this->temps_punitions_mineures as &$temps) {
                $temps *= 60;
            }
            unset($temps);
        }

        $this->duree_match = $this->duree_periode * $this->nb_periodes;
        # trouver la durée du match
        /**
         * @var $periode int
         * @var $chrono string
         */
        $res = db::query("
            SELECT periode, chrono
            FROM match_feuille
            WHERE id_match = $id_match
            ORDER BY periode DESC, chrono DESC
            LIMIT 1
		", ACCES_TABLE, '');
        if ($res->num_rows) {
            extract($res->fetch_assoc());
            if ($periode > $this->nb_periodes) {
                $this->duree_match = $this->chrono_to_s($chrono, $periode);
            }
        }

    }

    function chrono_to_s($chrono, $periode)
    {
        preg_match('#^ *(\d+):(\d+) *$#', $chrono, $components);

        $t = $components[1] * 60 + $components[2];

        $t += ($periode - 1) * $this->duree_periode;
        return $t;

    }

    /**
     *
     * @param type $chrono mm:ss temps écoulé depuis début match
     * @param type $duree duree en secondes
     * @param type $equipe vrai ou faux selon que concerne équipe ou autre
     */
    function ajout_punition($periode, $chrono, $duree, $equipe)
    {
//        debug_print_once("ajout punition per $periode - $chrono duree $duree equipe " . ($equipe?1:2));
        $val = $equipe ? 1 : -1;
        $debut = $this->chrono_to_s($chrono, $periode);

        if (array_key_exists($debut, $this->liste)) {
            $this->liste[$debut]->punition($val);
        } else {
            $this->liste[$debut] = new evenement($debut, $val, $chrono == '00:00');
        }
        $fin = $debut + $duree;
        if (array_key_exists($fin, $this->liste)) {
            $this->liste[$fin]->punition(-$val);
        } else {
            $this->liste[$fin] = new evenement($fin, -$val);
        }
        # stocker les punitions mineures pour les faire terminer prématurément en cas de but en AN lors de la compilation
        if (in_array($duree, $this->temps_punitions_mineures)) {
            $this->punitions_mineures[] = new punition_mineure($equipe, $debut, $duree);
        }
    }

    function ajout_expulsion($periode, $chrono, $equipe)
    {
        $temps = $this->chrono_to_s($chrono, $periode);
        $duree = $this->duree_match - $temps;
        $this->ajout_punition($periode, $chrono, $duree, $equipe);
    }

    function ajout_but($periode, $chrono, $equipe, $id_feuille_match)
    {
        $temps = $this->chrono_to_s($chrono, $periode);
        if (!array_key_exists($temps, $this->liste)) {
            $this->liste[$temps] = new evenement($temps, 0, $chrono == '00:00');
        }
        #debug_print_once("ajout but $periode - $chrono " . ($equipe? ' equipe' : ' autre eq'));
        $this->liste[$temps]->but($equipe, $id_feuille_match);
    }

    function compiler()
    {
        $nb = 0;
        $temps_prec = 0;
        $this->temps_desavantage_num = $this->temps_avantage_num = 0;
        ksort($this->liste);

        $this->desavantages_num = [];
        $this->avantages_num = [];

        foreach ($this->liste as $temps => $obj) {
            if ($nb > 0) { # si equipe est en désavantage num
                $this->temps_desavantage_num += $temps - $temps_prec;
            } else if ($nb < 0) {
                $this->temps_avantage_num += $temps - $temps_prec;
            }
            $temps_prec = $temps;
            $statut = $nb;


//            debug_print_once("=====================================nb = $nb");
//            debug_print_once(print_r($obj,1));

            if ($obj->but_eq) {
                if ($statut > 0) {
                    $this->buts_dn++;
                    $this->desavantages_num[] = $obj->id_feuille_match;
                } else if ($statut < 0) {
                    $this->buts_an++;
                    $this->avantages_num[] = $obj->id_feuille_match;
//                    debug_print_once("finir punition mineure equipe 2 à $temps");
//                    debug_print_once('==========LISTE AVANT');
//                    debug_print_once(print_r($this->liste,1));
                    $this->finir_punition_mineure(false, $temps); # finir punition d'autre équipe
//                    debug_print_once("============= liste apres");
//                    debug_print_once(print_r($this->liste,1));
                }
            } else if ($obj->but_autre_eq) {
                if ($statut > 0) {
                    $this->buts_an_autre_eq++;
                    $this->avantages_num[] = $obj->id_feuille_match;
//                    debug_print_once("finir p min equipe 1 à $temps");
                    $this->finir_punition_mineure(true, $temps); # finir punition d'équipe, puisque autre équipe a marqué en AN
                } else if ($statut < 0) {
                    $this->buts_dn_autre_eq++;
                    $this->desavantages_num[] = $obj->id_feuille_match;

                }
            }

            # laisser ici parce que $obj->val peut avoir changé si une punition a été terminée prématurément
            $nb = $obj->cumul = $nb + $obj->val;
        }

        if ($temps_prec < $this->duree_match) {
            if ($nb > 0) { # si equipe est en désavantage num
                $this->temps_desavantage_num += $this->duree_match - $temps_prec;
            } else if ($nb < 0) {
                $this->temps_avantage_num += $this->duree_match - $temps_prec;
            }
        }
    }

    function finir_punition_mineure($equipe, $temps)
    {
        $eq = $equipe ? '1' : '2';
//        debug_print_once("FINIR PUNITION MINEURE EQUIPE $eq à temps $temps");
        # maintenant qu'un but a été compté en AN, mettre fin immédiatement à la prochaine punition mineure qui se termine
//        debug_print_once("-----------> punitions");
//        debug_print_once(print_r($this->punitions_mineures,1));
        $punition_a_terminer = null;
        foreach ($this->punitions_mineures as $punition) {
            if ($punition->equipe !== $equipe) {
                continue;
            }
            if ($punition->terminee_prematurement) { # si la punition a déjà été terminée prématurément
                continue;
            }
            if ($punition->active($temps)) {
                if (!$punition_a_terminer) {
                    $punition_a_terminer = $punition;
                    continue;
                }
                if ($punition_a_terminer->fin > $punition->fin) { # retenir celle qui se termine en premier
                    $punition_a_terminer = $punition;
                }
            }
        }
        # si on a trouvé une punition à terminer avec un but marqué
        if ($punition_a_terminer) {
            $punition_a_terminer->terminee_prematurement = true;
            $fin_punition = $this->liste[$punition_a_terminer->fin];
            $nouvelle_fin = $this->liste[$temps];
            if ($equipe) {
                $fin_punition->val++;
                $nouvelle_fin->val--;
            } else {
                $fin_punition->val--;
                $nouvelle_fin->val++;
            }
            return true;
        }
        return false;
    }

    function find_val($chrono, $periode)
    {
        $temps = $this->chrono_to_s($chrono, $periode);

        $nb = 0;
        foreach ($this->liste as $temps_obj => $obj) {
            if ($temps_obj <= $temps) {
                $nb = $obj->cumul;
            } else {
                break;
            }
        }
        return $nb;
    }

    function avantage_num($chrono, $periode)
    {
        return $this->find_val($chrono, $periode) < 0; # signifie que l'adversaire a plus de punitions que l'équipe à ce moment
    }

    function desavantage_num($chrono, $periode)
    {
        return $this->find_val($chrono, $periode) > 0;
    }


}

class evenement
{
    public $temps; #secondes depuis début match
    public $val = 0; # -1 si un joueur de l'équipe commence sa punition; -1 s'il la termine; l'inverse pour autre eq
    public $cumul = 0; #somme des $val de tous les enregistrements précédents, plus celui-ci
    public $but_eq = 0;
    public $but_autre_eq = 0;
    public $debut_periode = false;
    public $id_feuille_match;

    /**
     *
     * @param type $temps secondes depuis début match
     * @param type $val 1 pour joueur en punition, -1 pour fin de punition; inverse pour autre équipe; 0 pour but
     * @param type $debut_periode si vrai, alors un but est compté dans l'état actuel d'avantage num. sinon, dans l'état immédiatement avant
     */
    function __construct($temps, $val = 0, $debut_periode = false)
    {
        $this->temps = $temps;
        $this->val = $val;
        $this->debut_periode = $debut_periode;
    }

    function punition($val)
    {
        $this->val += $val;
    }

    function but($eq, $id_feuille_match)
    {
        if ($eq) {
            $this->but_eq = 1;
        } else {
            $this->but_autre_eq = 1;
        }
        $this->id_feuille_match = $id_feuille_match;
    }


}

class punition_mineure
{
    public $debut, $fin, $duree, $terminee_prematurement = false, $equipe;

    function __construct($equipe, $debut, $duree)
    {
        $this->debut = $debut;
        $this->fin = $debut + $duree;
        $this->duree = $duree;
        $this->equipe = $equipe;
//        debug_print_once("Ajout punition mineure debut: $debut; duree: $duree; equipe: " . ($equipe?1:2));
    }

    # retourne true si $temps est entre debut et fin
    function active($temps)
    {
        return $temps >= $this->debut and $temps <= $this->fin;
    }
}

class result_insertion_fins_periodes
{
    public $msgs = [];
    public $insertions = [];
    public $erreurs = [];
    public $effacements = [];
    public $nouv_modeles = [];

    function add_insertion($insertion)
    {
        $this->insertions[] = $insertion;
    }

    function add_msg($msg)
    {
        $this->msgs[] = $msg;
    }

    function add_erreur($err_msg)
    {
        $this->erreurs[] = $err_msg;
    }

    function result()
    {
        return [
            'msgs' => $this->msgs,
            'erreurs' => $this->erreurs,
            'effacements' => $this->effacements,
            'nouveaux_modeles' => $this->nouv_modeles
        ];
    }

    function add_effacement($id)
    {
        if (!is_array($id)) {
            $id = [$id];
        }
        $this->effacements = array_merge($this->effacements, $id);
    }

    function insert()
    {
        if (!$this->insertions) {
            return;
        }
        $insert_ids = [];
        foreach ($this->insertions as $insertion) {
            $vals = implode(',', $insertion);
            $res = db::query("
                INSERT INTO match_feuille
                (id_match, periode, chrono, type_enreg)
                VALUES
                ($vals)
            ", ACCES_TABLE);
            $insert_ids[] = db::get('insert_id');
        }
        $sql_ids = implode(',', $insert_ids);
        $res = db::query("
                SELECT *
                FROM match_feuille
                WHERE id IN ($sql_ids)
            ", ACCES_TABLE);
        $this->nouv_modeles = array_merge($this->nouv_modeles, db::result_array($res));

    }


}