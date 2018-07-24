<?php

/**
 * Created by PhpStorm.
 * User: micra
 * Date: 2016-12-18
 * Time: 00:23
 */

use Phamda\Phamda as P;

class gestion_arenas extends http_json
{
    function __construct($no_op = false)
    {
        parent::__construct();
        if (!$no_op) {
            self::execute_op();
        }
    }

    function fn_get_arenas()
    {

        $res = db::query("
            SELECT a.*, GROUP_CONCAT(r.id SEPARATOR ',') ressources
            FROM arenas a
            LEFT JOIN ressources r ON r.id_arena = a.id
            GROUP BY a.id
            ORDER BY nom
            ",
            'acces_table');

        $liste = array_map(function ($v) {
            $v['ressources'] = ($v['ressources'] ? explode(',', $v['ressources']) : []);
            return $v;
        }, db::result_array($res));
        $liste = A::group_by_unique($liste, 'id');
        self::set_data('liste', $liste);

        $res = db::query("
            SELECT *
            FROM ressources
            ",
            'acces_table');
        self::set_data('ressources', A::group_by_unique(db::result_array($res), 'id'));

        self::succes();
    }

    function fn_save_arena()
    {
        if (!perm::test('admin')) {
            $this->fin('non_autorise');
        }
        /**
         * @var integer $id
         **/
        extract($vals = self::check_params(
            'id;unsigned',
            'nom;string;max:30',
            'adresse;string;max:75',
            'quartier;string;max:30',
            'tel;tel;max:30;accept_empty_string',
            'url;regex:#^$|^http://[^.]+.*\.[a-z]{2,}([?/].+)?$#'
        ));

        $assign = db::make_assignment($vals, ['id']);
        if (!$id) {
            $res = db::query("
                INSERT INTO arenas
                SET $assign
                ",
                'acces_table');
            $id = db::get('insert_id');
        } else {
            $res = db::query("
                UPDATE arenas
                SET $assign
                WHERE id = $id
                ",
                'acces_table');
        }
        $res = db::dquery("
            SELECT a.*, GROUP_CONCAT(r.id SEPARATOR ',') ressources
            FROM arenas a
            LEFT JOIN ressources r ON a.id = r.id_arena
            WHERE a.id = $id
            ",
            'acces_table');
        if ($res->num_rows == 0) {
            self::set_data('deletedId', $id);
        } else {
            $data = $res->fetch_assoc();
            A::explode($data, ['ressources']);
            self::set_data('data', $data);
        }

        self::succes();
    }

    function fn_save_fld()
    {
        if (!perm::test('admin')) {
            $this->fin('non_autorise');
        }
        /**
         * @var integer $id
         * @var string $fld
         * @var string $val
         **/
        extract(self::check_params(
            'id;unsigned',
            'fld;regex:#^(nom|adresse|quartier|tel|url)$#',
            'val;string;trim'
        ));

        if ($fld == 'nom') {
            $this->chk_len($val, 4, 30);
        } else if ($fld == 'adresse') {
            $this->chk_len($val, 4, 75);
        } else if ($fld == 'quartier') {
            $this->chk_len($val, 4, 30);
        } else if ($fld == 'tel') {
            $val = preg_replace('#[^0-9]#', '', $val);
            $len = strlen($val);
            if ($len and $len < 10) {
                $this->fin('au_moins_10_chiffres_pour_tel');
            }
            if ($len) {
                $val = '(' . substr($val, 0, 3) . ') ' . substr($val, 3, 3) . '-' . substr($val, 6, 4) . (strlen($val) > 10 ? ' #' . substr($val, 10) : '');
            }

        } else if ($fld == 'url') {
            if (!preg_match('#^http://[^.].*\.[a-z]{2,}$#', $val)) {
                $this->fin('pas_un_url');
            }
        } else {
            $this->fin('champ_inconnu', $fld);
        }
        $sql_val = db::sql_str($val);

        $res = db::query("
            UPDATE arenas
            SET $fld = $sql_val
            WHERE id = $id
            ",
            'acces_table');
        self::set_data('val', $val);
        self::succes();

    }

    function chk_len($val, $min, $max)
    {
        $len = strlen($val);
        if ($len < $min) {
            $this->fin('trop_court');
        }
        if ($len > $max) {
            $this->fin('trop_long');
        }
    }

    function fn_effacer()
    {
        if (!perm::test('admin')) {
            $this->fin('non_autorise');
        }
        /**
         * @var integer $id
         **/
        extract(self::check_params(
            'id;unsigned'
        ));
        /**
         * @var integer $nb
         */
        $res = db::query("
            SELECT COUNT(*) nb
            FROM arenas
            WHERE id = $id
            ",
            'acces_table');
        extract($res->fetch_assoc());
        if (!$nb) {
            $this->fin('introuvable');
        }
        $res = db::query("
            DELETE FROM arenas
            WHERE id = $id
            ",
            'acces_table');
        self::succes();
    }

    function fn_save_ressource()
    {
        /**
         * @var integer $id
         * @var integer $id_arena
         * @var string $description
         **/
        extract(self::check_params(
            'id;unsigned',
            'description;string;trim',
            'id_arena;unsigned'
        ));
        $sql_description = db::sql_str($description);
        if ($id) {
            /**
             * @var integer $id_arena_trouve
             */
            $res = db::query("
                SELECT id_arena id_arena_trouve
                FROM ressources
                WHERE id = $id
                ",
                'acces_table');
            if (!$res->num_rows) {
                $this->fin('introuvable');
            }
            extract($res->fetch_assoc());
            if ($id_arena !== $id_arena_trouve) {
                $this->fin('mauvais_arena');
            }
            if (!$description) {
                $res = db::query("
                    DELETE FROM ressources
                    WHERE id = $id
                    ",
                    'acces_table');
                self::set_data('delete_ressource', $id);
            } else {
                /**
                 * @var integer $nb
                 */
                $res = db::query("
                    SELECT COUNT(*) nb
                    FROM ressources
                    WHERE id_arena = $id_arena AND description = $sql_description AND id <> $id
                    ",
                    'acces_table');
                extract($res->fetch_assoc());
                if ($nb) {
                    $this->fin('existe');
                }
                $res = db::query("
                    UPDATE ressources
                    SET description = $sql_description
                    WHERE id = $id
                    ",
                    'acces_table');
            }
        } else {
            if (!$id_arena) {
                $this->fin('manque_arena');
            }

            /**
             * @var integer $nb
             */
            $res = db::query("
                SELECT COUNT(*) nb
                FROM arenas
                WHERE id = $id_arena
                LOCK IN SHARE MODE 
                ",
                'acces_table');
            extract($res->fetch_assoc());
            if (!$nb) {
                $this->fin('arena_introuvable');
            }
            $res = db::query("
                SELECT COUNT(*) nb
                FROM ressources
                WHERE description = $sql_description
                ",
                'acces_table');
            extract($res->fetch_assoc());
            if ($nb) {
                $this->fin('existe');
            }
            $res = db::query("
                INSERT INTO ressources
                SET description = $sql_description, id_arena = $id_arena
                ",
                'acces_table');
            self::set_data('id', db::get('insert_id'));
        }
        $res = db::query("
            SELECT a.*, GROUP_CONCAT(r.id SEPARATOR ',') ressources
            FROM arenas a 
            LEFT JOIN ressources r ON a.id = r.id_arena
            WHERE a.id = $id_arena
            ",
            'acces_table');
        $arena = $res->fetch_assoc();
        $arena['ressources'] = ($arena['ressources'] ? explode(',', $arena['ressources']) : []);
        self::set_data('arena', $arena);
        self::succes();
    }

    function fn_save_ressources_nommees()
    {
        $this->verif_admin();
        /**
         * @var int $id_arena
         * @var int $nb
         **/
        extract(self::check_params(
            'id_arena;unsigned',
            'nb;unsigned;min:1;max:4'
        ));

        /**
         * @var int $nb_existent
         **/
        $res = db::query("
                SELECT COUNT(*) nb_existent
                FROM ressources
                WHERE id_arena = $id_arena
                LOCK IN SHARE MODE 
            ", 'acces_table');
        extract($res->fetch_assoc());

        if ($nb_existent) {
            $this->fin('des glaces existent deja');
        }
        /**
         * @var string $nom
         **/
        $res = db::query("
                SELECT nom
                FROM arenas
                WHERE id = $id_arena
            ", 'acces_table');
        if ($res->num_rows == 0) {
            $this->fin('introuvable');
        }
        extract($res->fetch_assoc());

        if ($nb > 1) {
            $values = array_map(
                function ($v) use ($nom, $id_arena) {
                    return [
                        'id_arena' => $id_arena,
                        'description' => "$nom #$v"
                    ];
                },
                range(1, $nb));
            $updates = db::update_statement_components($values);

            $res = db::query("
                    INSERT INTO ressources
                      ({$updates[0]})
                      VALUES
                      ({$updates[1]})
                ", 'acces_table');


        } else {
            $sql_nom = db::sql_str($nom);
            $res = db::query("
                    INSERT INTO ressources
                    SET id_arena = $id_arena,
                      description = $sql_nom
                ", 'acces_table');

        }
        $res = db::query("
                SELECT *
                FROM ressources
                WHERE id_arena = $id_arena
            ", 'acces_table');

        self::set_data('updates', A::group_by_unique(db::result_array($res), 'id'));
        $this->succes();

    }
}