<?php
/**
 * Created by PhpStorm.
 * User: micra
 * Date: 2017-10-30
 * Time: 20:58
 */

use Phamda\Phamda as P;

class gestion_saisons extends http_json
{
    function __construct($no_op = false)
    {
        parent::__construct();
        if (!$no_op) {
            self::execute_op();
        }
    }

    function fn_get_stats_saisons()
    {
        $res = db::query("
                SELECT
                  s.id id_saison,
                  s.nom_saison,
                  s.debut,
                  s.statut,
                  COUNT(DISTINCT je.id_joueur) nb_joueurs,
                  COUNT(DISTINCT re.id_adulte) nb_officiels,
                  COUNT(DISTINCT eq.id_equipe) nb_equipes,
                  COUNT(DISTINCT par.id_parent) nb_parents
                FROM saisons s
                LEFT JOIN equipes eq ON s.id = eq.id_saison
                LEFT JOIN joueur_equipe je ON eq.id_equipe = je.id_equipe
                LEFT JOIN role_equipe re ON re.id_equipe = eq.id_equipe
                LEFT JOIN rel_parent par ON je.id_joueur = par.id_enfant
                GROUP BY s.id
                ORDER BY s.debut
            ", 'acces_table');

        $liste = db::result_array($res);

        $id_saison_courante = P::pipe(
            P::find(function ($v) {
                return $v['statut'] == 1;
            }),
            function ($saison) {
                return A::get_or($saison, 'id', 0);
            }
        )
        ($liste);

        A::setIntEach($liste, ['nb_joueurs', 'nb_officiels', 'nb_equipes', 'nb_parents']);
        $liste = A::group_by_unique($liste, 'id_saison');
        $ids_toutes_saisons = array_keys($liste);
        debug_print_once('toutes saisons'. print_r($ids_toutes_saisons, 1));


        $res = db::query("
        SELECT DISTINCT id_joueur, a.id
        FROM (
          SELECT DISTINCT je.id_joueur, s.id
          FROM joueur_equipe je
          JOIN equipes eq ON je.`id_equipe` = eq.`id_equipe`
          JOIN saisons s ON eq.`id_saison` = s.`id`
          
          UNION 
          
          SELECT DISTINCT m.id id_joueur, $id_saison_courante id
          FROM membres m
          JOIN hcr_donnees_nettoyees hcr USING(id_hcr)
          ) a
          
          JOIN saisons s USING(id) 
          HAVING id <> 0
          ORDER BY s.debut
    ", 'acces_table');

        $joueurs = db::result_array($res);

        $liste_saisons = A::group_by($joueurs, 'id');
        $ids_saisons = array_keys($liste_saisons);


        $ids = P::map(P::pluck('id_joueur'), array_values($liste_saisons));
        // => [ids 1ere saison] [ids 2e saison] ...


        // = [nb_quittent_saison1, ..saison2, ...]
        $quittent = P::map(function ($val, $ind, $array) {
            if ($ind >= count($array) - 1) {
                return 0;
            }
            return count(array_diff($val, ...array_slice($array, $ind + 1) ?? []));
        }, $ids);

        $liste = P::pipe(
            P::map(function ($nombre_quittent) {
                return ['quittent' => $nombre_quittent];
            }), // nb => [quittent => nb]
            P::zip($ids_saisons), // [ [id_saison, [quittent => nb]], ... ]
            P::fromPairs(), // { [id_saison]: [quittent => nb] }
            P::zip($liste), // { [id_saison]: [ [data], [quittent => nb] ] *** attention saisons sans joueurs pas incluses
            P::map(P::apply('array_merge')),
            function ($val) use ($liste, $ids_toutes_saisons) { // on ajoute saisons qui manquent (sans joueurs)
                $ids_sans_joueurs = array_diff($ids_toutes_saisons, array_keys($val));
                if ($ids_sans_joueurs) {
                    return P::pipe(
                        P::pick($ids_sans_joueurs),
                        P::map(P::assoc('quittent', 0)),
                        P::merge($val)
                    )
                    ($liste);
                } else {
                    return $val;
                }
            },
            'array_values',
            P::sortBy(P::prop('debut'))
        )
        ($quittent);


        self::set_data('saisons', $liste);

        self::set_data('data_membres', self::get_stats_membres());

        $this->succes();
    }

    static function get_stats_membres()
    {

        $to_ret = [];

        $res = db::query("
                SELECT COUNT(*) nb, IFNULL(age(date_naissance), 0) age
                FROM membres m
                GROUP BY age
                ORDER BY age
                
            ", 'acces_table');

        $liste = db::result_array($res);

        A::setIntEach($liste, ['age', 'nb']);

        $to_ret['ages'] = $liste;

        /**
         * @var int $nb_editeurs
         **/
        $res = db::query("
                SELECT COUNT(*) nb_editeurs
                FROM editeurs
            ", 'acces_table');

        extract($res->fetch_assoc());

        $to_ret['nb_editeurs'] = (int)$nb_editeurs;

        $res = db::query("
                SELECT COUNT(DISTINCT id_enfant) nb_enfants, COUNT(DISTINCT id_parent) nb_parents
                FROM rel_parent
            ", 'acces_table');

        $val = $res->fetch_assoc();
        A::setInt($val, ['nb_enfants', 'nb_parents']);

        $to_ret = array_merge($to_ret, $val);

        return $to_ret;

    }

    function implode_not_empty($array, $default_empty = '0')
    {
        if (count($array) == 0) {
            return [$default_empty];
        }
        return implode(',', $array);
    }

    function fn_effacer_saison()
    {
        if (!perm::test('superhero')) {
            $this->fin('non_autorise');
        }
        /**
         * @var int $id
         * @var int $proceder
         **/
        extract(self::check_params(
            'id;unsigned',
            'proceder;unsigned'
        ));

        $zero_if_empty = P::ifElse('count', P::identity(), P::prepend(0));

        $res = db::query("
                SELECT id, debut, statut, inscription
                FROM saisons
                ORDER BY debut
                FOR UPDATE
            ", 'acces_table');


        if ($res->num_rows < 3) {
            $this->fin('trois_saisons_min');
        }
        $saisons = db::result_array($res);

        $data_id = array_filter($saisons, function ($v) use ($id) {
            return $v['id'] == $id;
        });

        if (count($data_id) == 0) {
            $this->fin('introuvable');
        }

        $sql_saisons_actives = P::pipe(
            P::filter(function ($v) {
                return $v['statut'] or $v['inscription'];
            }),
            P::pluck('id'),
            $zero_if_empty,
            P::implode(',')
        )
        ($saisons);

//        debug_print_once(print_r($saisons, 1));

        if ($saisons[0]['id'] != $id) {
            $this->fin('pas_la_plus_ancienne_saison');
        }

        $date_12_mois = new DateTime();
        $date_12_mois->modify('-12 months');

        $sql_date_12_mois = db::sql_str($date_12_mois->format('Y-m-d'));
        $res = db::query("
                SELECT id_equipe, id_saison
                FROM equipes
                WHERE id_saison
                LOCK IN SHARE MODE 
            ", 'acces_table');

        $liste = db::result_array($res);

        $toutes_eq = P::pluck('id_equipe', $liste);

        $eq_autres_saisons = P::pipe(
            P::filter(function ($v) use ($id) {
                return $v['id_saison'] != $id;
            }),
            P::pluck('id_equipe'),
            $zero_if_empty
        )
        ($liste);
        $sql_eq_autres_saisons = implode(',', $eq_autres_saisons);

        $sql_eq_saison = P::pipe(
//            P::tap(function($v){
//                debug_print_once('toutes equipes ' . count($v));}),
            function ($v) use ($eq_autres_saisons) {
                return array_diff($v, $eq_autres_saisons);
            },
//            P::tap(function($v) use($eq_autres_saisons){
//                debug_print_once('eq autres saisons (' . count($eq_autres_saisons) . ') ' . print_r($eq_autres_saisons, 1));
//                debug_print_once('eq saison (' . count($v) . ') ' . print_r($v, 1));
//
//            }),
            $zero_if_empty,
            P::implode(',')
        )
        ($toutes_eq);


        $res = db::query("
            SELECT m.id, m.nom, m.prenom
            FROM membres m
            LEFT JOIN editeurs ed ON m.id = ed.id_membre
            
            WHERE ed.id_membre IS NULL # pas un editeur
            
            AND m.id NOT IN ( # pas un resp de niveau
              SELECT pn.id_membre
              FROM permissions_niveaux pn
              WHERE DATE(horaire) > $sql_date_12_mois OR DATE(resultats) > $sql_date_12_mois OR DATE(controleur) > $sql_date_12_mois
            )
            
            AND m.id NOT IN ( # pas joueur de saison plus recente
              SELECT DISTINCT je.id_joueur
              FROM joueur_equipe je
              WHERE je.id_equipe IN ($sql_eq_autres_saisons)
            )
            
            AND m.id NOT IN ( # pas officiel d'équipe plus récente
              SELECT DISTINCT re.id_adulte
              FROM role_equipe re
              WHERE re.id_equipe IN ($sql_eq_autres_saisons)
            )  
            
            AND m.id NOT IN ( # pas parent d'un joueur de saison plus récente
              SELECT DISTINCT rel.id_parent
              FROM rel_parent rel
              JOIN joueur_equipe je ON rel.id_enfant = je.id_joueur
              WHERE je.id_equipe IN ($sql_eq_autres_saisons)            
            )
            
            AND m.id NOT IN ( # enlever ceux qui ont ouvert une session dans les 12 mois
                SELECT DISTINCT id_user
                FROM event_log
                WHERE categ = 'login' AND DATE(date) > $sql_date_12_mois 
            )
            
            AND m.id NOT IN ( # enlever ceux qui ont été inscrits dans une saison active
              SELECT DISTINCT i.id_joueur
              FROM inscriptions i
              WHERE i.saison IN ($sql_saisons_actives)
            )
            
            AND m.id_hcr NOT IN ( # enlever ceux qui sont dans le dernier fichier hcr 
              SELECT id_hcr
              FROM hcr_donnees_nettoyees
              
            )
                 
            ORDER BY m.nom, m.prenom  
            ", 'acces_table');

        $liste = db::result_array($res);

        debug_print_once('nombre effacements: ' . count($liste));

        $sql_ids_effacement = P::pipe(
            P::pluck('id'),
            P::implode(',')
        )($liste);


        $nb_initial = $this->get_table_count('membres');
        db::query("
                DELETE FROM membres
                WHERE id IN ($sql_ids_effacement)
            ", 'acces_table');
        $nb_final = $this->get_table_count('membres');

        self::set_data('membres', [$nb_initial, $nb_final]);


        $nb_initial = $this->get_table_count('equipes');
        db::dquery("
                DELETE FROM equipes
                WHERE id_equipe IN ($sql_eq_saison)
            ", 'acces_table');
        $nb_final = $this->get_table_count('equipes');

        self::set_data('equipes', [$nb_initial, $nb_final]);

        $nb_initial = $this->get_table_count('saisons');
        db::query("
                DELETE FROM saisons
                WHERE id = $id
            ", 'acces_table');
        $nb_final = $this->get_table_count('saisons');

        self::set_data('saisons', [$nb_initial, $nb_final]);

        debug_print_once(print_r(self::$data, 1));

        if (!$proceder) {
            db::rollback();
            $this->succes();
        }


        self::set_data('data_membres', self::get_stats_membres());

        // db::rollback();
        $this->succes();
    }

    function get_table_count($table)
    {
        /**
         * @var int $nb
         **/
        $res = db::query("
                SELECT COUNT(*) nb
                FROM $table
            ", 'acces_table');
        extract($res->fetch_assoc());
        return (int)$nb;

    }
}