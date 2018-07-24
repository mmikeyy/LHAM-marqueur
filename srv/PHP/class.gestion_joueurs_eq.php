<?php
/**
 * Created by PhpStorm.
 * User: micra
 * Date: 2017-11-26
 * Time: 19:00
 */

class gestion_joueurs_eq extends http_json
{
    function __construct($no_op = false)
    {
        parent::__construct();
        if (!$no_op) {
            self::execute_op();
        }
    }

    function verifier_acces($id_equipe)
    {
        if (!login_visiteur::logged_in()) {
            $this->fin('ouvrez_session');
        }
        $id_visiteur = session::get('id_visiteur');

        /**
         * @var int $nb_eq
         * @var int $nb_roles
         * @var int $nb_perms
         **/
        $res = db::query("
                SELECT COUNT(ec.id) nb_eq, COUNT(re.id_adulte) nb_roles, COUNT(pn.id) nb_perms
                FROM equipes_courantes ec
                LEFT JOIN role_equipe re ON re.id_equipe = ec.id_equipe AND re.role < 2 AND re.id_adulte = $id_visiteur
                LEFT JOIN permissions_niveaux pn ON pn.categ = ec.categ AND (pn.classe = ec.classe OR pn.classe IS NULL) AND pn.id_membre = $id_visiteur 
                WHERE ec.id_equipe = $id_equipe
            ", 'acces_table');
        extract($res->fetch_assoc());
        if (!$nb_eq) {
            $this->fin('equipe_introuvable');
        }
        if (!$nb_roles and !$nb_perms and !perm::test('admin')) {
            $this->fin('non_autorise');
        }
    }

    function fn_get_joueurs_et_candidats()
    {
        /**
         * @var int $id_equipe
         **/
        extract(self::check_params(
            'id_equipe;unsigned'
        ));

        $id_saison = saisons::get('courante');
        if (!$id_saison) {
            $this->fin('pas_de_saison_courante');
        }

        importe_hcr::create_dossiers_joueurs($id_saison);
        /**
         * @var string $naissance_min
         * @var string $naissance_max
         **/
        $res = db::dquery("
                SELECT ta.naissance_min, ta.naissance_max
                FROM tableaux_ages ta
                JOIN equipes_courantes ec ON ta.id_division = ec.id_division
                WHERE ec.id_equipe = $id_equipe AND ta.saison = $id_saison
            ", 'acces_table');
        if ($res->num_rows == 0) {
            $this->fin('tableau_ages_manque');
        }
        extract($res->fetch_assoc());

        $sql_min = db::sql_str($naissance_min);
        $sql_max = db::sql_str($naissance_max);

        $max_plus = new DateTime($naissance_max);
        $max_plus->modify('+ 1 year');
        $sql_max_plus = db::sql_str($max_plus->format('Y-m-d'));


        $res = db::query("
                SELECT DISTINCT 
                    m.id, 
                    proper(CONCAT_WS(', ', m.nom, m.prenom)) nom,
                    age(m.date_naissance) age,
                    IF(date_naissance > $sql_max, 1, 0) plus_jeune,
                    GROUP_CONCAT(DISTINCT ec.id_equipe SEPARATOR ',') equipes,
                    dj.position,
                    dj.no_chandail
                FROM membres m
                LEFT JOIN joueur_equipe je ON m.id = je.id_joueur
                LEFT JOIN equipes_courantes ec ON je.id_equipe = ec.id_equipe
                LEFT JOIN dossier_joueur dj ON m.id = dj.id_joueur AND dj.saison = $id_saison
                WHERE m.date_naissance BETWEEN $sql_min AND $sql_max_plus AND dj.id IS NOT NULL OR je.id_equipe = $id_equipe
                GROUP BY m.id
                ORDER BY m.nom, m.prenom
            ", 'acces_table');

        $liste = db::result_array($res);
        A::setBooleanEach($liste, ['plus_jeune']);
        A::setIntEach($liste, ['age']);
        A::explodeEach($liste, ['equipes']);
        $ids_equipes = A::collect($liste, ['equipes']);
        self::set_data('liste', $liste);

        $sql_ids_equipes = implode(',', $ids_equipes);
        $res = db::query("
                SELECT eq.id_equipe id, CONCAT(n.categ, '-', cl.classe, ' ', proper(ne.nom_std)) nom
                FROM equipes eq
                JOIN noms_equipes ne
                JOIN rang_niveau n ON eq.division = n.id
                JOIN classes cl ON eq.classe = cl.id
                WHERE eq.id_equipe IN ($sql_ids_equipes)
            ", 'acces_table');

        $liste = db::result_array($res);
        self::set_data('equipes', A::group_by_unique($liste, 'id'));


        $this->succes();

    }

    function fn_ajout_joueur_eq()
    {
        /**
         * @var int $id_equipe
         * @var int $id_joueur
         * @var array $retrait
         **/
        extract(self::check_params(
            'id_equipe;unsigned',
            'id_joueur;unsigned',
            'retrait;array_unsigned' // liste d'autres équipes d'où retirer le joueur
        ));
        $id_saison = saisons::get('courante');
        if (!$id_saison) {
            $this->fin('saison_courante_non_definie');
        }

        $this->valider_acces($id_equipe);
        /**
         * @var int $nb
         **/
        $res = db::query("
                SELECT COUNT(*) nb
                FROM inscriptions
                WHERE id_joueur = $id_joueur AND saison = $id_saison
            ", 'acces_table');
        extract($res->fetch_assoc());
        if ($nb != 1) {
            $this->fin('membre non inscrit');
        }


        if (count($retrait)) {
            if (!perm::test('admin') and !perm::resp_niveau_equipe($id_equipe)) {
                $this->fin('non_autorise_pour_retrait');
            }
            if (in_array($id_equipe, $retrait)) {
                $this->fin('retrait_et_ajout_a_meme_equipe');
            }
            $retrait = array_unique($retrait);
            $sql_retrait = implode(',', $retrait);

            $res = db::query("
                    SELECT COUNT(*) nb
                    FROM equipes_courantes
                    WHERE id_equipe IN ($sql_retrait)
                ", 'acces_table');
            extract($res->fetch_assoc());
            if ($nb != count($retrait)) {
                $this->fin('equipe_retrait_introuvable');
            }
            $res = db::query("
                    DELETE FROM joueur_equipe
                    WHERE id_joueur = $id_joueur AND id_equipe IN ($sql_retrait)
                ", 'acces_table');


        }

        /**
         * @var string $naissance_min
         * @var string $naissance_max
         **/
        $res = db::query("
                SELECT t.naissance_min, DATE_ADD(t.naissance_max, INTERVAL 1 YEAR) naissance_max
                FROM tableaux_ages t
                JOIN equipes_courantes ec ON t.id_division = ec.id_division
                WHERE t.saison = $id_saison AND ec.id_equipe = $id_equipe
            ", 'acces_table');

        if ($res->num_rows == 0) {
            $this->fin('mettre_a_jour_tableau_ages');
        }
        extract($res->fetch_assoc());
        db::sql_str_($naissance_min);
        db::sql_str_($naissance_max);
        /**
         * @var int $age_ok
         **/
        $res = db::query("
                SELECT IF(date_naissance BETWEEN $naissance_min AND $naissance_max, 1, 0) age_ok
                FROM membres
                WHERE id = $id_joueur
            ", 'acces_table');
        if ($res->num_rows == 0) {
            $this->fin('membre_introuvable');
        }
        extract($res->fetch_assoc());
        if (!$age_ok) {
            $this->fin('age_joueur_incorrect_pour_division');
        }

        $res = db::query("
                INSERT IGNORE INTO joueur_equipe
                SET id_joueur = $id_joueur,
                 id_equipe = $id_equipe
            ", 'acces_table');

        $this->succes();
    }

    function fn_retrait_joueur_equipe()
    {
        /**
         * @var int $id_joueur
         * @var int $id_equipe
         **/
        extract(self::check_params(
            'id_joueur;unsigned',
            'id_equipe;unsigned'
        ));

        $this->verifier_acces($id_equipe);
        $res = db::query("
                DELETE FROM joueur_equipe
                WHERE id_joueur = $id_joueur AND id_equipe = $id_equipe
            ", 'acces_table');
        $this->succes();

    }

    function fn_set_joueur_pos()
    {
        /**
         * @var int $id_joueur
         * @var int $id_equipe
         * @var int $pos
         **/
        extract(self::check_params(
            'id_joueur;unsigned',
            'id_equipe;unsigned',
            'pos;unsigned;max:3;accept_null;sql'
        ));
        $id_saison = saisons::get('courante');

        $this->verifier_acces($id_equipe);
        /**
         * @var int $joueur_existe
         * @var int $joueur_equipe
         * @var int $equipe_courante
         * @var int $inscrit
         **/
        $res = db::query("
                SELECT COUNT(m.id) joueur_existe, COUNT(je.id) joueur_equipe, COUNT(ec.id) equipe_courante, dj.id inscrit
                FROM membres m
                LEFT JOIN joueur_equipe je ON m.id = je.id_joueur AND je.id_equipe = $id_equipe
                LEFT JOIN equipes_courantes ec ON ec.id_equipe = je.id_equipe
                LEFT JOIN dossier_joueur dj ON m.id = dj.id_joueur AND dj.saison = $id_saison
                WHERE je.id_equipe = $id_equipe AND je.id_joueur = $id_joueur
            ", 'acces_table');
        extract($res->fetch_assoc());
        if (!$joueur_existe) {
            $this->fin('joueur_introuvable');
        }
        if (!$joueur_equipe) {
            $this->fin('pas_joueur_d_equipe');
        }
        if (!$equipe_courante) {
            $this->fin('equipe_non_courante');
        }
        if (!$inscrit) {
            $this->fin('joueur_non_inscrit');
        }
        $res = db::query("
                UPDATE dossier_joueur
                SET position = $pos
                WHERE saison = $id_saison AND id_joueur = $id_joueur
                LIMIT 1
            ", 'acces_table');

        $this->succes();
    }

    function fn_change_no_chandail()
    {
        /**
         * @var int $id_joueur
         * @var int $id_equipe
         * @var int $no_chandail
         **/
        extract(self::check_params(
            'id_joueur;unsigned',
            'id_equipe;unsigned',
            'no_chandail;unsigned;empty_string_to_null;sql'
        ));

        $this->verifier_acces($id_equipe);
        /**
         * @var int $nb
         **/
        $res = db::query("
                SELECT COUNT(*) nb
                FROM joueur_equipe
                WHERE id_joueur = $id_joueur AND id_equipe = $id_equipe
            ", 'acces_table');
        extract($res->fetch_assoc());
        if (!$nb) {
            $this->fin('pas_joueur_de_cette_equipe');
        }
        $id_saison = saisons::get('courante');
        $res = db::query("
                SELECT COUNT(*) nb
                FROM dossier_joueur 
                WHERE id_joueur = $id_joueur AND saison = $id_saison
                FOR UPDATE
            ", 'acces_table');
        extract($res->fetch_assoc());
        if (!$nb) {
            $this->fin('joueur_pas_de_dossier_pour_saison');
        }
        $res = db::query("
                UPDATE dossier_joueur
                SET no_chandail = $no_chandail
                WHERE id_joueur = $id_joueur AND saison = $id_saison
            ", 'acces_table');

        $this->succes();

    }
}