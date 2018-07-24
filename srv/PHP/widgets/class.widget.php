<?php
use Phamda\Phamda as P;
use \Underscore\Types\Arrays;
/**
 * Created by PhpStorm.
 * User: micra_000
 * Date: 2016-07-19
 * Time: 11:12
 */
class widget
{
    static $match_cond = [];
    static $pratiques_cond = [];
    static $eq_cond = [];
    static $fix_ehl_done = false;
    static $ids_menus = [];

    static $ids_equipes = [];
    static $ids_membres = [];
    static $ref_equipes = [];
    static $ref_equipes_cl = [];
    static $ref_equipes_done = false;
    static $ref_menus = [];
    static $classement_cond = [];

    static $widget_types = [
        'horaires',
        'contacts_equipe',
        'liste_joueurs',
        'google_calendar',
        'tableau',
        'section_entraineur',
        'stats_joueurs',
        'admin_division',
        'twitter',
        'tableau_arenas',
        'acces_rapide',
        'calendrier',
        'galerie',
        'twitter',
        'videos',
        'exclusion_courriel',
        'menu_mosaique',
        'admin_equipe',
        'classement_ps',
        'palmares',
        'pratique_edition'
        ];

    function __construct()
    {
    }

    static function add_match_cond($cond)
    {
        if (in_array($cond, self::$match_cond)) return;
        self::$match_cond[] = $cond;
    }

    static function add_pratiques_cond($div, $cl) {
        $div_cl = "id_division = $div AND id_classe = $cl";
        if (in_array($div_cl, self::$pratiques_cond)) {
            return;
        }
        self::$pratiques_cond[] = $div_cl;
    }

    static function add_classement_cond($div, $cl = null) {
        if (!$div) {
            return;
        }
        self::$classement_cond[] = "rel.id_division = $div" . ($cl ? " AND rel.id_classe = $cl" : '');
    }

    static function add_equipe_cond($cond){
        if (in_array($cond, self::$eq_cond)) return;
        self::$eq_cond[] = $cond;
    }

    static function add_menu($id_menu) {
        if (in_array($id_menu, self::$ids_menus)) {
            return;
        }
        self::$ids_menus[] = $id_menu;
    }


    static function get_classement_data() {
        if (!self::$classement_cond) {
            return null;
        }
        $lang = lang::get_lang();
        $cond = implode(' OR ', self::$classement_cond);
        $res = db::query("
                SELECT c.*, rel.id_division, rel.id_classe, rel.id_nom_std, rel.source
                FROM classement c 
                JOIN rel_equipe_ext rel ON c.id_equipe = rel.id_equipe_ext
                WHERE $cond
                ORDER BY c.rank
            ", 'acces_table');

        $liste = db::result_array($res);
        A::setIntEach($liste, ['rank','played','normalWin','overtimeWin','shootoutWin','normalLoss','overtimeLoss','shootoutLoss','null','goalsFor','goalsAgainst','differential','performancePTS','fairPlayPTS','pts','minorPenalty','majorPenalty','suspension']);
        A::setFloatEach($liste, ['moyen']);

        $res = db::query("
                SELECT id, fld, titre_$lang titre, tooltip_$lang tooltip, rang
                FROM cols_classement
                ORDER BY rang
                
            ", 'acces_table');

        $flds = db::result_array($res);
        A::setIntEach($flds, ['rang']);

        return ['data' => $liste, 'flds' => $flds];
    }

    static function get_palmares_data() {
        $res = db::query("
                SELECT 
                  c.rank,
                  CONCAT(rn.categ, '-', cl.classe, ' ', proper(noms.nom_std)) nom,
                  c.played,
                  c.normalWin + c.overtimeWin + c.shootoutWin victoires,
                  c.normalLoss + c.overtimeLoss + c.shootoutLoss defaites,
                  c.null nulles,
                  c.goalsFor,
                  c.goalsAgainst,
                  c.moyen 
                FROM classement c
                JOIN rel_equipe_ext rel ON c.id_equipe = rel.id_equipe_ext
                JOIN noms_equipes noms ON rel.id_nom_std = noms.id
                JOIN rang_niveau rn ON rn.id = rel.id_division
                JOIN classes cl ON cl.id = rel.id_classe 
                WHERE c.rank < 4
                ORDER BY c.rank, rn.rang, cl.ordre, noms.ordre
            ", 'acces_table');

        $liste = db::result_array($res);
        A::setIntEach($liste, ['rank', 'played', 'victoires', 'defaites', 'nulles', 'goals_for', 'goalsAgainst']);
        A::setFloatEach($liste, ['moyen']);
        return ['data' => $liste];

    }

    static function get_menus_ref() {
        if (!self::$ids_menus) {
            return [];
        }
        $sql_ids_menus = implode(',', db::sql_str(self::$ids_menus));
        $lang = lang::get_lang();
        $autre_lang = lang::autre_lang();
        $res = db::query("
                SELECT
                    m.menu_group,
                    m.id_menu id,
                    IF(m.menutext_$lang = '', m.menutext_$autre_lang, m.menutext_$lang) label, 
                    m.url, 
                    m.id_document,
                    m.classe,
                    m.marge_haut,
                    m.marge_bas,
                    m.bureau,
                    m.mobile,
                    m.type,
                    m.acces_rapide,
                    m.largeur,
                    m.niveau,
                    d.alias
                FROM menus m
                LEFT JOIN documents d USING(id_document)
                WHERE m.menu_group IN ($sql_ids_menus)
                ORDER BY m.menu_group, rang
            ", 'acces_table');
        $menus = db::result_array_group($res, 'menu_group');
        // debug_print_once('menus' . print_r($menus, 1));
        foreach($menus as &$items) {
            A::setIntEach($items, ['marge_haut', 'marge_bas', 'largeur', 'niveau']);
            A::setBooleanEach($items, ['bureau', 'mobile', 'acces_rapide']);
            $items = A::levels_to_subs($items, 'niveau', 'items');
        }

        // debug_print_once('menus reorg' . print_r($menus,1));
        return $menus;

    }

    static function get_arenas(){
        $res = db::query("
                SELECT a.*, GROUP_CONCAT(r.id ORDER BY r.description SEPARATOR ',') ressources
                FROM arenas a
                LEFT JOIN ressources r ON a.id = r.id_arena
                GROUP BY a.id
                ORDER BY nom
            ", 'acces_table');

        $liste = db::result_array($res);
        A::explodeEach($liste, ['ressources']);
        $to_ret = [];
        $to_ret['flds'] = db::fld_names($res);
        $to_ret['data'] = array_map(function($v) {return array_values($v);}, $liste);
        return $to_ret;
    }

    static function get_ressources() {
        $res = db::query("
                SELECT *
                FROM ressources
            ", 'acces_table');
        return [
            'flds' => db::fld_names($res),
            'data' => $res->fetch_all(MYSQLI_NUM)
        ];
    }


    static function get_match_data()
    {
        global $is_mobile;
        $to_ret = ['flds' => [], 'data' => []];
        if (!self::$match_cond) {
            return $to_ret;
        }
        $cond = implode(') OR (', self::$match_cond);
//        if (false){
//            $equipe1_expr = '(@equipe1 := IFNULL(IFNULL(abr1.abrev_mobile, abr1.abrev), equipe1))';
//            $equipe2_expr = '(@equipe2 := IFNULL(IFNULL(abr2.abrev_mobile, abr2.abrev), equipe2))';
//        } else {
//            $equipe1_expr = '(@equipe1 := IFNULL(abr1.abrev, equipe1))';
//            $equipe2_expr = '(@equipe2 := IFNULL(abr2.abrev, equipe2))';
//        }

        $res = db::query("
			SELECT
				proper(ifnull(gcal_lieux.lieu_propre, ehl.lieu)) as long_lieu,
				ehl.*,
				proper(IFNULL(abr1.abrev, ehl.equipe1)) equipe1, 
				proper(IFNULL(abr2.abrev, ehl.equipe2)) equipe2,
				proper(IFNULL(IFNULL(abr1.abrev_mobile, abr1.abrev), equipe1)) equipe1_mob,
				proper(IFNULL(IFNULL(abr2.abrev_mobile, abr2.abrev), equipe2)) equipe2_mob,
				ec1.id_equipe id_eq1,
				ec2.id_equipe id_eq2,
				IFNULL(ehl.display_ref, ehl.ref) ref,
				ehl.date,
				time_format(ehl.debut, '%Hh%i') as heure,
				substr(ehl.debut,1, 5) debut,
				if(ehl.date>=CURRENT_DATE and not ehl.a_replanifier, 1, 0) as futur
			FROM ehl
			LEFT JOIN gcal_lieux on ehl.lieu=gcal_lieux.lieu_original
			LEFT JOIN noms_eq_abrev  abr1 ON ehl.equipe1 = abr1.original
			LEFT JOIN noms_eq_abrev abr2 ON ehl.equipe2 = abr2.original
			LEFT JOIN equipes_courantes ec1 ON ec1.id_division = ehl.`div` AND ec1.id_classe = ehl.cl AND ec1.id_nom_std = ehl.eq1
			LEFT JOIN equipes_courantes ec2 ON ec2.id_division = ehl.`div` AND ec2.id_classe = ehl.cl AND ec2.id_nom_std = ehl.eq2
			

			WHERE ($cond)
			ORDER BY ehl.date, ehl.debut
            ", 'acces_table');

        if ($res->num_rows) {
            $to_ret['flds'] = db::fld_names($res);
            $to_ret['data'] = $res->fetch_all();

            A::setBooleanEach($to_ret['data'], A::indexesOf($to_ret['flds'], ['gcal_a_sauvegarder','a_replanifier','futur']));
            A::setIntEach($to_ret['data'], A::indexesOf($to_ret['flds'], ['pts1','fj1','pts2','fj2']), false);
        }

        return $to_ret;
    }

    static function get_pratiques_data() {
        $empty = ['data' => [], 'flds' => []];
        $to_ret = [];
        foreach(['pratiques','ref_equipes','ref_types_ev', 'ref_ress'] as $fld) {
            $to_ret[$fld] = $empty;
        }
        if (!self::$pratiques_cond) {
            return $to_ret;
        }

        $jours = cfg_yml::get('horaires', 'jours_afficher_pratiques');
        if (!$jours or !is_integer($jours)) {
            $jours = 28;
        }

        $cond = implode(' OR ', self::$pratiques_cond);

        $res = db::query("
                SELECT id_equipe id
                FROM equipes_courantes 
              
                WHERE $cond
             
            ", 'acces_table');

        if (!$res->num_rows) {
            return $to_ret;
        }
        $ids = implode(',', db::result_array_one_value($res, 'id'));

        $res = db::query("
                SELECT 
                  e.id, 
                  e.type_evenement, 
                  e.description, 
                  dhm(e.debut) debut, 
                  dhm(e.fin) fin, 
                  e.id_ressource, 
                  e.info, 
                  GROUP_CONCAT(g.id_equipe SEPARATOR ',') ids_equipes
                FROM events e
                JOIN event_guests g ON e.id = g.id_event
                WHERE  g.id_equipe IN ($ids) AND
                  DATE(debut) >= CURDATE() AND
                  DATE(debut) <= ADDDATE(CURDATE(), INTERVAL $jours DAY)
                GROUP BY e.id
                ORDER BY e.debut
            ", 'acces_table');

        if ($res->num_rows) {
            $liste_ev = $res->fetch_all();
            $liste_flds = db::fld_names($res);
            $pos_ids_equipes = array_search('ids_equipes', $liste_flds);
            A::explodeEach($liste_ev, [$pos_ids_equipes]);

            $to_ret['pratiques']['data'] = $liste_ev;
            $to_ret['pratiques']['flds'] = $liste_flds;

            $all_ids = A::extract_unique($liste_ev, $pos_ids_equipes);

            $sql_all_ids = implode(',', $all_ids);

            $res = db::query("
                    SELECT 
                    e.id_equipe id, 
                    CONCAT(e.groupe, ' ', proper(n.nom_std)) nom
                    FROM equipes_courantes e
                    JOIN noms_equipes n ON e.id_nom_std = n.id
                    WHERE e.id_equipe IN ($sql_all_ids)
                ", 'acces_table');

            if ($res->num_rows) {
                $to_ret['ref_equipes']['data'] = $res->fetch_all();
                $to_ret['ref_equipes']['flds'] = db::fld_names($res);
            }

            $extract_types = P::pipe(
                P::pluck(array_search('type_evenement', $liste_flds)),
                'array_unique',
                P::implode(',')
            );

            $sql_types = $extract_types($liste_ev);

            if ($sql_types) {
                $res = db::query("
                        SELECT id, 
                        description
                        FROM event_types
                    ", 'acces_table');


                $to_ret['ref_types_ev']['data'] = $res->fetch_all();
                $to_ret['ref_types_ev']['flds'] = db::fld_names($res);

            }


            $sql_ids_ressources = P::pipe(
                P::pluck(array_search('id_ressource', $liste_flds)),
                'array_unique',
                P::implode(',')
            )($liste_ev);

            if ($sql_ids_ressources) {
                $res = db::query("
                    SELECT id, description
                    FROM ressources
                    WHERE id IN ($sql_ids_ressources)
                ", 'acces_table');
            }
            if ($res->num_rows) {
                $to_ret['ref_ress']['flds'] = db::fld_names($res);
                $to_ret['ref_ress']['data'] = $res->fetch_all();
            }

        }


        return $to_ret;
    }

    static function get_equipes_data(){

        $saison = saisons::courante();
        $to_ret = ['flds'=>[], 'data'=>[]];
        if (!self::$eq_cond or !$saison){
            return $to_ret;
        }

        $cond = implode(') OR (', self::$eq_cond);
        $res = db::query("
                SELECT eq.id_equipe, eq.classe, eq.division, eq.nom, eq.niveau, eq.id_nom_std
                FROM equipes eq
                JOIN noms_equipes neq ON eq.id_nom_std = neq.id
                WHERE ($cond) AND eq.id_saison = $saison
                ORDER BY neq.ordre
            ", 'acces_table');

        $ids = [];
        if ($res->num_rows) {
            $to_ret['flds'] = db::fld_names($res);
            $to_ret['data'] = $res->fetch_all();
            $ind_id = array_search('id_equipe',$to_ret['flds']);
            foreach($to_ret['data'] as $row){
                $ids[] = $row[$ind_id];
            }
        }

        self::$ids_equipes = array_merge(self::$ids_equipes, $ids);

        return $to_ret;
    }

    static function get_joueurs(){
        $to_ret = ['flds'=>[], 'data'=>[]];
        if (!self::$ids_equipes){
            return $to_ret;
        }
        $saison = saisons::courante();
        $sql_ids = implode(',', self::$ids_equipes);

        $res = db::query("
                SELECT eq.id_equipe, je.id_joueur, dj.position, dj.no_chandail
                FROM equipes eq
                JOIN joueur_equipe je USING(id_equipe)
                LEFT JOIN dossier_joueur dj ON dj.saison = $saison AND dj.id_joueur = je.id_joueur
                JOIN membres m ON je.id_joueur = m.id
                WHERE eq.id_equipe IN ($sql_ids)
                ORDER BY m.nom, m.prenom
            ", 'acces_table');

        $ids = [];
        if ($res->num_rows) {
            $to_ret['flds'] = db::fld_names($res);
            $to_ret['data'] = $res->fetch_all();
            $ind_id = array_search('id_joueur',$to_ret['flds']);
            foreach($to_ret['data'] as $row){
                $ids[] = $row[$ind_id];
            }
        }
        self::$ids_membres = array_merge(self::$ids_membres, $ids);

        return $to_ret;

    }
    static function get_officiels(){
        $to_ret = ['flds'=>[], 'data'=>[]];
        if (!self::$ids_equipes){
            return $to_ret;
        }
        $sql_ids = implode(',', self::$ids_equipes);

        $res = db::query("
                SELECT re.id_equipe, id_adulte, role
                FROM role_equipe re
                WHERE re.id_equipe IN ($sql_ids)
            ", 'acces_table');

        $ids = [];
        if ($res->num_rows) {
            $to_ret['flds'] = db::fld_names($res);
            $to_ret['data'] = $res->fetch_all();
            $ind_id = array_search('id_adulte',$to_ret['flds']);
            foreach($to_ret['data'] as $row){
                $ids[] = $row[$ind_id];
            }
        }
        self::$ids_membres = array_merge(self::$ids_membres, $ids);
        return $to_ret;

    }
    static function get_membres(){
        $to_ret = ['flds'=>[], 'data'=>[]];
        if (!self::$ids_membres){
            return $to_ret;
        }
        $sql_ids = implode(',', self::$ids_membres);
        $res = db::query("
                SELECT proper(m.nom) nom, proper(m.prenom) prenom, id, age(m.date_naissance) age
                FROM membres m
                WHERE id IN ($sql_ids)
            ", 'acces_table');

        if ($res->num_rows) {
            $to_ret['flds'] = db::fld_names($res);
            $to_ret['data'] = $res->fetch_all();
        }
        return $to_ret;

    }

    static function get_membre($id){
        $is_array = true;
        if (!is_array($id)){
            $id = [$id];
            $is_array = false;
        }

        $sql_ids = implode(',', $id);

        $res = db::query("
                SELECT proper(m.nom) nom, proper(m.prenom) prenom, id, age(m.date_naissance) age
                FROM membres m
                WHERE id IN ($sql_ids)
            ", 'acces_table');

        if (!$is_array){
            return $res->fetch_assoc();
        }
        return db::result_array($res);
    }


    static function fix_ehl($force = false)
    {
        if (self::$fix_ehl_done and !$force){
            return;
        }

        self::$fix_ehl_done = true;

        $res = db::query("
                UPDATE ehl e
                JOIN (SELECT CONCAT(rn.categ, '-', cl.classe) groupe, rn.id `div`, cl.id cl
                    FROM rang_niveau rn
                    JOIN classes cl ON 1
                    ) g USING(groupe)
                    SET e.`div` = g.`div`, e.cl = g.cl
                    WHERE e.`div` IS NULL OR e.cl IS NULL
                
            ", 'acces_table');
        echo db::get('affected_rows') . ' rows affected div cl';


        $regex = cfg_yml::get('noms_equipes', 'sql_regex_une_equipe');
        if (!$regex){
            debug_print_once('!!!!!!! clef  noms_equipes/sql_regex_une_equipe manquante');
            return;
        }
        /**
         * @var string $noms
         **/
        $res = db::query("
                SELECT nom_std,
                id
                FROM noms_equipes
            ", 'acces_table');

        if (!$res->num_rows){
            debug_print_once('!!!!!!!!! noms introuvables dans noms_equipes');
        }

        $cond = [];

        while ($row = $res->fetch_assoc()){
            $case_regex = sprintf($regex, $row['nom_std']);
            $cond[] = "WHEN equipe%s REGEXP $case_regex THEN {$row['id']}";
        }
        $cond1 = implode(' ', array_map(function($v){return sprintf($v, '1');}, $cond));
        $cond2 = implode(' ', array_map(function($v){return sprintf($v, '2');}, $cond));

        $res = db::query("
                UPDATE ehl
                SET 
                eq1 = IF(eq1 IS NOT NULL, eq1, CASE $cond1 ELSE NULL END),
                eq2 = IF(eq2 IS NOT NULL, eq2, CASE $cond2 ELSE NULL END)
                WHERE eq1 IS NULL OR eq2 IS NULL
            ", 'acces_table');

        echo "\nequipes: " . db::get('affected_rows');

        //db::rollback();


    }

    static function fn($val)
    {
        echo $val;
    }

    static function get_equipe($div, $cl, $eq = 0){
        if (!self::$ref_equipes_done){
            self::$ref_equipes_done = true;
            $saison = saisons::courante();
            if (!$saison){
                return null;
            }
            $res = db::query("
                    SELECT CONCAT_WS(';', division, classe, id_nom_std) ref,
                        CONCAT_WS(';', division, classe) ref_cl,
                        id_equipe
                    FROM equipes
                    WHERE id_saison = $saison
                ", 'acces_table');
            self::$ref_equipes = db::result_array_one_value($res, 'id_equipe', 'ref');
            self::$ref_equipes_cl = db::result_array_group($res, 'ref_cl');
            //debug_print_once(self::$ref_equipes);

        }
        if ($eq) {
            return A::get_or(self::$ref_equipes, "$div;$cl;$eq", null);
        } else {
            return A::get_or(self::$ref_equipes_cl, "$div;$cl", []);
        }
    }


}