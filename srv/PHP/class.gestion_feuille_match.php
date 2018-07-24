<?php

class gestion_feuille_match extends http_json
{
    function __construct($no_op = false)
    {
        parent::__construct();
        self::set_default_msgs('gestion_feuille_match');

        if (!$no_op){
            self::execute_op();
        }
    }

    function fn_get_fusillade()
    {
        /**
         * @var $id_fm int
         * @var $id_match int
         */
        extract(self::check_params(
            'id_fm;unsigned',
            'id_match;unsigned'
        ));
        self::$data['liste'] = $this->get_fusillade($id_match, $id_fm);
        $this->succes();
    }


    function get_fusillade($id_match, $id_fm, $id = null)
    {
        $conds = ["mf.id_match = $id_match"];
        if (!is_null($id)){
            $conds[] = "mff.id = $id";
        }
        $cond = implode(' AND ', $conds);

        $res = db::query("
                SELECT 
                  mff.id,
                  mff.id_joueur1, 
                  mff.id_joueur2, 
                  mff.ronde, 
                  mff.ordre, 
                  mff.but1, 
                  mff.but2,
                  CONCAT(m1.nom, ' ', SUBSTRING(m1.prenom,1,2), '. ', IFNULL(CONCAT('#', mj1.no_chandail), '')) nom1,
                  CONCAT(m2.nom, ' ', SUBSTRING(m2.prenom,1,2), '. ', IFNULL(CONCAT('#', mj2.no_chandail), '')) nom2
                FROM match_feuille_fusillade mff
                JOIN match_feuille mf ON mf.id = mff.id_fm AND mf.type_enreg = 'fusillade'
                JOIN match_joueurs mj1 ON mj1.id_match = $id_match AND mff.id_joueur1 = mj1.id_joueur
                JOIN membres m1 ON m1.id = mff.id_joueur1
                JOIN match_joueurs mj2 ON mj2.id_match = $id_match AND mff.id_joueur2 = mj2.id_joueur
                JOIN membres m2 ON m2.id = mff.id_joueur2
                WHERE $cond
                ORDER BY mff.ronde, mff.ordre
            ", 'acces_table');
        $liste = db::result_array($res);
        A::setBooleanEach($liste, ['but1', 'but2']);
        A::setIntEach($liste, ['ronde', 'ordre']);
        if (is_null($id)) {
            return $liste;

        } else {
            if ($res->num_rows == 0){
                return null;
            } else {
                return $liste[0];
            }
        }

    }


    function fn_save_fusillade()
    {
        /**
         * @var $id_match int
         * @var $data array
         * @var $id_fm int
         */
        extract(self::check_params(
            'id_match;unsigned',
            'id_fm;unsigned',
            'data;json;decode_array'
        ));
        if (!perm::marqueur_match($id_match)){
            $this->non_autorise();
        }
        /**
         * @var $id int
         * @var $nb int
         */
        $res = db::query("
                SELECT id
                FROM match_feuille
                WHERE id_match = $id_match AND type_enreg = 'fusillade'
                LOCK IN SHARE MODE
            ", 'acces_table');

        if ($res->num_rows == 0){
            $this->fin('introuvable');
        }
        extract($res->fetch_assoc());
        if ($id !=$id_fm){
            $this->fin('erreur acces donnees');
        }

        if (count($data) == 0){
            $res = db::query("
                    DELETE FROM match_feuille_fusillade
                    WHERE $id_fm = id_fm
                ", 'acces_table');
            $this->succes();
        }

        # obtenir la liste des joueurs des équipes
        /**
         * @var $joueurs1 string
         * @var $joueurs2 string
         */
        $res = db::query("
                SELECT
                    GROUP_CONCAT(IF(mj.id_equipe = sm.id_equipe1, mj.id_joueur, NULL) SEPARATOR ',') joueurs1,
                    GROUP_CONCAT(IF(mj.id_equipe = sm.id_equipe2, mj.id_joueur, NULL) SEPARATOR ',') joueurs2
                FROM match_joueurs mj
                JOIN stats_matchs sm ON sm.id = mj.id_match
                WHERE sm.id = $id_match
            ", 'acces_table');
        if (!$res->num_rows){
            $this->fin('liste joueurs non dispo');
        }
        extract($res->fetch_assoc());
        if (!$joueurs1 or !$joueurs2){
            $this->fin('joueurs_eq_non_dispo');
        }
        $liste_joueurs1 = explode(',', $joueurs1);
        $liste_joueurs2 = explode(',', $joueurs2);

        # valider données recues
        $data_joueurs1 = [];
        $data_joueurs2 = [];
        $values = [];
        foreach($data as $record){
            $value = [$id_fm];
            foreach(['ronde','ordre','id_joueur1','id_joueur2','but1','but2'] as $fld){
                if (in_array($fld, ['but1', 'but2'])) {
                    $value[] = $record[$fld] ? '1' : '0';
                } else {
                    if (!preg_match('#^\d+$#', $record[$fld])){
                        $this->fin(sprintf(self::msg('donnees_invalides'), "$fld = {$record[$fld]}"));
                    }
                    $value[] = "$record[$fld]";
                }
            }
            $data_joueurs1[] = $record['id_joueur1'];
            $data_joueurs2[] = $record['id_joueur2'];
            $values[] = implode(',', $value);
        }

        # vérifier que les joueurs n'ont pas été associés à mauvaise équipe

        if (count(array_diff($data_joueurs1, $liste_joueurs1)) or count(array_diff($data_joueurs2, $liste_joueurs2))){
            $this->fin('fusillade_implique_mauvais_joueurs');
        }

        $liste_values = implode('),(', $values);



        $res = db::query("
                DELETE
                FROM match_feuille_fusillade
                WHERE id_fm = $id_fm
            ", 'acces_table');

        $res = db::query("
                INSERT INTO match_feuille_fusillade
                (id_fm, ronde, ordre, id_joueur1, id_joueur2, but1, but2)
                VALUES
                ($liste_values)
            ", 'acces_table');

        self::$data['liste'] = $this->get_fusillade($id_match, $id_fm);
        $this->succes();
    }

}