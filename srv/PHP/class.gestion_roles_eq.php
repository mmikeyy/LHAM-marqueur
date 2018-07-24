<?php

/**
 * Created by PhpStorm.
 * User: micra_000
 * Date: 2016-07-22
 * Time: 19:36
 */
class gestion_roles_eq extends http_json
{

    function __construct($no_op = false)
    {
        parent::__construct();
        if (!$no_op){
            self::execute_op();
        }
    }


    function fn_get_contacts_equipe(){
        /**
         * @var int $id_equipe
         **/
        extract(self::check_params(
            'id_equipe;unsigned'
        ));

        $saison = saisons::courante();
        if (!$saison){
            $this->fin('saison_courante_non_definie');
        }

        $res = db::query("
                SELECT COUNT(*) nb
                FROM equipes
                WHERE id_equipe = $id_equipe AND id_saison = $saison
            ", 'acces_table');
        if ($res->num_rows == 0){
            $this->fin('introuvable');
        }


        $res = db::query("
                SELECT proper(CONCAT_WS(' ', m.prenom, m.nom)) nom, m.id, rel.role
                FROM membres m 
                JOIN role_equipe rel ON m.id = rel.id_adulte
                WHERE rel.id_equipe = $id_equipe
                ORDER BY rel.role
            ", 'acces_table');

        self::set_data('liste', db::result_array($res));

    }

    function fn_set_contacts_equipe(){
        /**
         * @var int $id_equipe
         * @var array $roles
         **/
        extract(self::check_params(
            'id_equipe;unsigned',
            'roles;array'
        ));
        if (!login_visiteur::logged_in()){
            $this->fin('ouvrir_session');
        }
        $id_visiteur = session::get('id_visiteur');
        if (!perm::test('perm_admin') and
            !perm::est_gerant_de($id_visiteur, $id_equipe) and
            !perm::est_resp_niveau_de($id_visiteur, $id_equipe)
        ){
            $this->fin('non_autorise');
        }

        /**
         * @var int $nb
         **/
        $res = db::query("
                SELECT COUNT(*) nb
                FROM equipes
                WHERE id_equipe = $id_equipe
                LOCK IN SHARE MODE 
            ", 'acces_table');
        extract($res->fetch_assoc());
        if (!$nb){
            $this->fin('introuvable');
        }

        $ids_membres = [];
        $values = [];
        foreach($roles as $role){
            if (count($role) !== 2){
                $this->fin('donnees_fournies_incorrectes');
            }
            foreach($role as $val){
                if (!preg_match('#^\d+$#', $val)){
                    $this->fin('donnee_invalide');
                }
            }
            $ids_membres[] = $role[0];
            if ($role[1] < 0 or $role[1] > 2){
                $this->fin('donnee_invalide');
            }
            $values[] = implode(',', [$id_equipe, $role[0], $role[1], 0]);
        }
        $unique_ids = array_unique($ids_membres);
        $sql_unique_ids = implode(',', $unique_ids);
        $res = db::query("
                SELECT COUNT($nb) nb
                FROM membres
                WHERE id IN ($sql_unique_ids)
                LOCK IN SHARE MODE 
            ", 'acces_table');
        extract($res->fetch_assoc());
        if ($nb != count($unique_ids)){
            $this->fin('membre_choisi_inconnu');
        }


        $res = db::query("
                UPDATE role_equipe
                SET marked = 1
                WHERE id_equipe = $id_equipe
            ", 'acces_table');

        $sql_values = implode('),(', $values);
        $res = db::dquery("
                INSERT IGNORE INTO role_equipe
                (id_equipe, id_adulte, role, marked)
                VALUES ($sql_values)
                ON DUPLICATE KEY UPDATE 
                marked = 0
            ", 'acces_table');

        $res = db::query("
                DELETE FROM role_equipe
                WHERE id_equipe = $id_equipe AND marked
            ", 'acces_table');


        $res = db::query("
                SELECT id_adulte, id_equipe, role
                FROM role_equipe
                WHERE id_equipe = $id_equipe
            ", 'acces_table');

        self::set_data('liste', db::result_array($res));

        self::set_data('membres', widget::get_membre($unique_ids));

        $this->succes();

    }
}