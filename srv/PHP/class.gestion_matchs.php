<?php
/**
 * Created by PhpStorm.
 * User: micra
 * Date: 2018-05-21
 * Time: 20:35
 */

class gestion_matchs extends http_json
{
    function __construct($no_op = false)
    {
        parent::__construct();
        if (!$no_op) {
            self::execute_op();
        }
    }

    function fn_get_all() {

        list($liste, $restants) = $this->get_matchs();

        self::$data = [
            'liste' => $liste,
            'nb_non_charges' => $restants,
            'lieux' => $this->get_arenas(),
            'equipes' => $this->get_equipes(),
            'classes' => $this->get_classes(),
            'id_saison' => saisons::get('courante'),
            'saison' => saisons::get_fld('nom_saison'),
            'codes_punition' => $this->get_codes_punition(),
            'config' => $this->get_config()
        ];
        $this->succes();
    }
    function get_codes_punition() {
        $res = db::query("
                SELECT  description, if(ordre, 1, 0) frequent
                FROM std_punitions
                ORDER BY if(ordre, ordre, 9999), description
            ", 'acces_table');
        $liste = db::result_array($res);

        $liste_ind = [];
        $liste_modif = [];
        foreach($liste as $val) {
            $ind = preg_match('#^\d+#', $val['description'], $match);
            if(!$ind or in_array($match[0], $liste_ind)) {
                continue;
            }
            $liste_ind[] = $match[0];
            $val['code'] = $match[0];
            $liste_modif[] = $val;
        }

        A::setBooleanEach($liste_modif, ['frequent']);
        return $liste_modif;
    }

    function get_config() {
        $config = array_merge(
            [
                'duree' => 90,
                'duree_periodes' => '22:00',
                'pts_forfait_gagnant' =>  1,
                'pts_forfait_perdant' =>  0,
                'temps_punitions' =>  '3,6,10,12,15',
                'temps_punitions_avantage_num' =>  '3,6',
                'temps_punitions_annulees_par_but' =>  5
            ]
            ,
            cfg_yml::get('matchs')
        );

        A::explode($config, ['temps_punitions', 'temps_punitions_avantage_num']);
        foreach(['temps_punitions', 'temps_punitions_avantage_num'] as $fld) {
            $config[$fld] = array_map(function($v) use ($fld) {return (int) $v;}, $config[$fld]);
        }

        return $config;
    }


    function get_more() {
        /**
         * @var int $nb_a_charger
         * @var array $exclure
         **/
        extract(self::check_params(
            'exclure:array_unsigned',
            'nb_a_charger;unsigned;opt'
        ));

        if (!isset($nb_a_charger)) {
            $nb_a_charger = 20;
        }

        list($liste, $restants) = $this->get_matchs($nb_a_charger, $exclure);

        self::$data = [
            'liste' => $liste,
            'nb_non_charges' => $restants
        ];
        $this->succes();
    }

    function get_matchs($nb = 20, $exclure = []) {

        $debut = date('Y-m-d');
        $sql_debut = db::sql_str($debut);

        if (count($exclure) == 0) {
            $exclure = [0];
        }
        $sql_exclure = implode(',', $exclure);

        $res = db::query("
                SELECT SQL_CALC_FOUND_ROWS 
                    m.id,
                    m.id_tournoi,
                    m.id_groupe,
                    m.date,
                    SUBSTR(m.debut, 1, 5) debut,
                    m.lieu,
                    m.id_equipe1,
                    m.id_equipe2,
                    CONCAT(me.prenom, ' ', me.nom) marqueur,
                    m.marqueur id_marqueur,
                    m.marqueur_confirme,
                    m.pts1,
                    m.pts2,
                    eq1.classe id_classe1,
                    eq2.classe id_classe2,
                    locked,
                    m.saison id_saison,
                    m.forfait1,
                    m.forfait2,
                    m.sj_ok1,
                    m.sj_ok2
                FROM stats_matchs m 
                LEFT JOIN membres me ON m.marqueur = me.id
                LEFT JOIN equipes eq1 ON m.id_equipe1 = eq1.id_equipe
                LEFT JOIN equipes eq2 ON m.id_equipe2 = eq2.id_equipe
                LEFT JOIN saisons s ON m.saison = s.id
                WHERE date >= $sql_debut AND m.id NOT IN ($sql_exclure) AND s.statut = 1
                ORDER BY date, debut
                LIMIT $nb
                    
            ", 'acces_table');

        $liste = db::result_array($res);
        A::setBooleanEach($liste, ['marqueur_confirme', 'locked', 'forfait1', 'forfait2']);
        A::setIntEach($liste, ['pts1', 'pts2', 'sj_ok1', 'sj_ok2'], false);
        $liste = A::group_by_unique($liste, 'id');

        /**
         * @var int $nb_total
         **/
        $res = db::query("
                SELECT FOUND_ROWS() nb_total
            ", 'acces_table');
        extract($res->fetch_assoc());

        return [$liste, $nb_total - count($liste)];

    }

    static function get_un_match($id_match) {
        $res = db::query("
                    SELECT
                    m.id,
                    m.id_tournoi,
                    m.id_groupe,
                    m.date,
                    SUBSTR(m.debut, 1, 5) debut,
                    m.lieu,
                    m.id_equipe1,
                    m.id_equipe2,
                    CONCAT(me.prenom, ' ', me.nom) marqueur,
                    m.marqueur id_marqueur,
                    m.marqueur_confirme,
                    m.pts1,
                    m.pts2,
                    eq1.classe id_classe1,
                    eq2.classe id_classe2,
                    locked,
                    m.saison id_saison,
                    m.forfait1,
                    m.forfait2,
                    m.sj_ok1,
                    m.sj_ok2
                FROM stats_matchs m 
                LEFT JOIN membres me ON m.marqueur = me.id
                LEFT JOIN equipes eq1 ON m.id_equipe1 = eq1.id_equipe
                LEFT JOIN equipes eq2 ON m.id_equipe2 = eq2.id_equipe
                LEFT JOIN saisons s ON m.saison = s.id
                WHERE m.id = $id_match
                    
            ", 'acces_table');

        if (!$res->num_rows) {
            return [];
        }
        $record = $res->fetch_assoc();
        A::setBoolean($record, ['marqueur_confirme', 'locked', 'forfait1', 'forfait2']);
        A::setInt($record, ['pts1', 'pts2', 'sj_ok1', 'sj_ok2'], false);
        return $record;
    }

    function fn_get_mes_matchs() {
        list($liste, $restants) = $this->get_mes_matchs();

        self::$data = [
            'liste' => $liste,
            'nb_non_charges' => $restants,
            'lieux' => $this->get_arenas(),
            'equipes' => $this->get_equipes(),
            'classes' => $this->get_classes(),
            'id_saison' => saisons::get('courante'),
            'saison' => saisons::get_fld('nom_saison')
        ];
        $this->succes();

    }

    function get_mes_matchs_more() {
        /**
         * @var int $nb_a_charger
         * @var array $exclure
         **/
        extract(self::check_params(
            'exclure:array_unsigned',
            'nb_a_charger;unsigned;opt'
        ));

        if (!isset($nb_a_charger)) {
            $nb_a_charger = 20;
        }

        list($liste, $restants) = $this->get_matchs($nb_a_charger, $exclure);

        self::$data = [
            'liste' => $liste,
            'nb_non_charges' => $restants
        ];
        $this->succes();
    }


    function get_mes_matchs($nb = 10, $exclure = []) {

        if (count($exclure) == 0) {
            $sql_exclure = '0';
        } else {
            $sql_exclure = implode(',', $exclure);
        }

        $date_limite = new DateTime();
        $date_limite->modify('+ 8 hours');

        $sql_date_limite = db::sql_str($date_limite->format('Y-m-d H:i'));
        $id_visiteur = session::get('id_visiteur');
        if (!$id_visiteur) {
            return [[], 0];
        }

        $res = db::query("
                SELECT SQL_CALC_FOUND_ROWS
                    m.id,
                    m.id_tournoi,
                    m.id_groupe,
                    m.date,
                    SUBSTR(m.debut, 1, 5) debut,
                    m.lieu,
                    m.id_equipe1,
                    m.id_equipe2,
                    CONCAT(me.prenom, ' ', me.nom) marqueur,
                    m.marqueur id_marqueur,
                    m.marqueur_confirme,
                    m.pts1,
                    m.pts2,
                    eq1.classe id_classe1,
                    eq2.classe id_classe2,
                    locked,
                    m.saison id_saison,
                    m.forfait1,
                    m.forfait2,
                    m.sj_ok1,
                    m.sj_ok2
                FROM stats_matchs m 
                LEFT JOIN membres me ON m.marqueur = me.id
                LEFT JOIN equipes eq1 ON m.id_equipe1 = eq1.id_equipe
                LEFT JOIN equipes eq2 ON m.id_equipe2 = eq2.id_equipe
                LEFT JOIN saisons s ON s.id = m.saison
                WHERE CONCAT_WS(' ', m.date, SUBSTR(m.debut, 1, 5)) <= $sql_date_limite
                  AND m.id NOT IN ($sql_exclure)
                  AND m.marqueur = $id_visiteur
                  AND s.statut = 1
                  AND !m.annule
                ORDER BY date DESC, debut DESC
                LIMIT $nb
                    
            ", 'acces_table');

        $liste = db::result_array($res);
        A::setBooleanEach($liste, ['marqueur_confirme', 'locked', 'forfait1', 'forfait2']);
        A::setIntEach($liste, ['pts1', 'pts2', 'sj_ok1', 'sj_ok2'], false);
        $nb_trouves = count($liste);
        $liste = A::group_by_unique($liste, 'id');

        $res = db::query("
                SELECT FOUND_ROWS() nb_total
            ", 'acces_table');
        /**
         * @var int $nb_total
         **/
        extract($res->fetch_assoc());




        return [$liste, $nb_total - $nb_trouves];

    }

    function get_equipes() {
        $res = db::query("
                SELECT 
                    eq.id_equipe id,
                    eq.nom,
                    eq.classe id_classe
                FROM equipes eq
                JOIN saisons s ON eq.id_saison = s.id
                WHERE s.statut = 1
                
            ", 'acces_table');

        $liste = db::result_array($res);
        return A::group_by_unique($liste, 'id');

    }

    function get_arenas() {
        $res = db::query("
                SELECT 
                    id,
                    lieu_propre description,
                    id_organisation id_org
                FROM gcal_lieux
            ", 'acces_table');

        $liste = db::result_array($res);
        return A::group_by_unique($liste, 'id');

    }

    function get_classes() {
        $res = db::query("
                SELECT DISTINCT 
                    cl.id,
                    cl.classe,
                    cl.description,
                    cl.ordre
                FROM classes cl
                JOIN equipes eq ON eq.classe = cl.id
                JOIN saisons s ON s.id = eq.id_saison
                WHERE s.statut = 1
            ", 'acces_table');

        $liste = db::result_array($res);
        return A::group_by_unique($liste, 'id');
    }

    function fn_get_info_match() {
        /**
         * @var int $id
         **/
        extract(self::check_params(
            'id;unsigned'
        ));

        $res = db::query("
                SELECT 
                  m.id,
                  IFNULL(gcl.lieu_propre, m.lieu) lieu,
                  m.date,
                  m.debut,
                  m.id_equipe1,
                  m.id_equipe2,
                  CONCAT(eq1.nom, ' [', cl1.classe, ']') equipe1,
                  CONCAT(eq2.nom, ' [', cl2.classe, ']') equipe2,
                  m.sj_ok1,
                  m.sj_ok2,
                  m.fm_ok,
                  m.locked,
                  m.forfait1,
                  m.forfait2,
                  CONCAT_WS(' ', mm.prenom, mm.nom) marqueur,
                  m.pts1,
                  m.pts2
              
                  
                FROM stats_matchs m
                LEFT JOIN gcal_lieux gcl ON m.lieu = gcl.id
                LEFT JOIN equipes eq1 ON eq1.id_equipe = id_equipe1
                LEFT JOIN equipes eq2 ON eq2.id_equipe = id_equipe2
                LEFT JOIN classes cl1 on eq1.classe = cl1.id
                LEFT JOIN classes cl2 on eq2.classe = cl2.id
                LEFT JOIN membres mm ON mm.id = m.marqueur
                WHERE m.id = $id
                
            ", 'acces_table');
        if ($res->num_rows == 0) {
            $this->fin('introuvable');
        }
        
        $data = $res->fetch_assoc();
        A::setInt($data, ['pts1', 'pts2', 'sj_ok1', 'sj_ok2']);
        A::setBoolean($data, ['fm_ok', 'locked', 'forfait1', 'forfait2']);

        $res = db::query("
                SELECT 
                  mj.id_equipe,
                  CONCAT_WS(', ', proper(m.nom), proper(m.prenom)) nom,
                  mj.position,
                  mj.no_chandail
                FROM match_joueurs mj
                JOIN membres m ON mj.id_joueur = m.id
                WHERE mj.id_match = $id
                ORDER BY m.nom, m.prenom
            ", 'acces_table');
        
        $joueurs = db::result_array($res);
        A::setIntEach($joueurs, ['position']);
        
        $data['joueurs1'] = array_merge(array_filter($joueurs, function($val) use ($data) {return $val['id_equipe'] === $data['id_equipe1'];}));
        $data['joueurs2'] = array_merge(array_filter($joueurs, function($val) use ($data) {return $val['id_equipe'] === $data['id_equipe2'];}));

        unset($data['id_equipe1']);
        unset($data['id_equipe2']);

        self::set_data('info', $data);
        $this->succes();
    }
}