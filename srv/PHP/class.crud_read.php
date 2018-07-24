<?php


class crud_read {


    static function query_gestion_membres($id_division, $id_saison, $inclure_inactifs=0, $id_membre = null, $all=null)
    {
        $en_saison = "dj.id IS NOT NULL OR je.id IS NOT NULL OR cap.id_adulte IS NOT NULL OR i.id_joueur IS NOT NULL OR pn.id IS NOT NULL OR m.marqueur OR m.arbitre";

        $hors_saison = 0;
        if (!is_null($id_membre)) {
            $cond_membre = "m.id = $id_membre";
            $hors_saison = "NOT($en_saison)";
        } else if ($all) {
            $cond_membre = 1;
            $hors_saison = "NOT($en_saison)";
        } else {
            $cond_membre = '';
            switch ($inclure_inactifs){
                case 2:
                    $cond_membre = 'NOT inactif';
                    break;
                case 1:
                    $cond_membre = 'inactif';
                    break;
                default:
                    $cond_membre = "NOT inactif AND ($en_saison)";
            }

//            $cond_membre = ($inclure_inactifs == 1 ? 1 : ($inclure_inactifs == 2 ? 'inactif' : 'NOT inactif') );
//            if (!$inclure_inactifs){
//                $cond_membre .= ' OR je.id IS NOT NULL OR cap.id_adulte IS NOT NULL';
//            }
        }
        $categ = gestion_divisions::get_desc_courte($id_division);
        $sql_categ = db::sql_str($categ);
        $res = db::query("
            SELECT id_equipe
            FROM equipes
            WHERE division = $id_division AND id_saison = $id_saison
		");
        $liste_eq = db::result_array_one_value($res, 'id_equipe');
        $liste_eq[] = -1;
        $liste_eq = implode(',', $liste_eq);
        $res = db::query("
            SELECT m.id,
                m.inactif,
                CONCAT(m.nom, ' ', m.prenom) nom,
                m.courriel,
                m.code_postal,
                $hors_saison hors_saison,
                IF(TRIM(CONCAT(tel_jour,tel_soir,cell)) = '',1,0) sans_tel,
                m.date_naissance,
                age(m.date_naissance) age,
                dj.no_chandail,
                dj.id id_dossier,
                0 id_inscr, # i.id id_inscr,
                0 id_equipe_inscr,  # i.id_equipe_inscr,
                '------' nom_equipe_inscr,   # ei.nom_equipe nom_equipe_inscr,
                m.arbitre,
                m.marqueur,
                GROUP_CONCAT(cap.id_equipe SEPARATOR ',') capitaine,
                GROUP_CONCAT(DISTINCT je.id_equipe SEPARATOR ',') equipes,
                not non_joueur joueur,
                IFNULL(dj.substitut,'0') substitut,
                dj.position,
                GROUP_CONCAT(DISTINCT CONCAT(IFNULL(cl.id, 0), ',',IF(pn.horaire > ADDDATE(NOW(), 7), pn.horaire, null)) SEPARATOR ';') perm_horaire,
                GROUP_CONCAT(DISTINCT CONCAT(IFNULL(cl.id, 0), ',',IF(pn.resultats > ADDDATE(NOW(), 7), pn.resultats, null)) SEPARATOR ';') perm_resultats,
                GROUP_CONCAT(DISTINCT CONCAT(IFNULL(cl.id, 0), ',',IF(pn.controleur > ADDDATE(NOW(), 7), pn.controleur, null)) SEPARATOR ';') perm_controleur,
                GROUP_CONCAT(DISTINCT CONCAT(IFNULL(cl.id, 0), ',',IF(pn.horaire <= NOW(), pn.horaire, null)) SEPARATOR ';') perm_horaire_exp,
                GROUP_CONCAT(DISTINCT CONCAT(IFNULL(cl.id, 0), ',',IF(pn.resultats <= NOW(), pn.resultats, null)) SEPARATOR ';') perm_resultats_exp,
                GROUP_CONCAT(DISTINCT CONCAT(IFNULL(cl.id, 0), ',',IF(pn.controleur <= NOW(), pn.controleur, null)) SEPARATOR ';') perm_controleur_exp,
                GROUP_CONCAT(DISTINCT CONCAT(IFNULL(cl.id, 0), ',',IF(pn.horaire BETWEEN NOW() AND ADDDATE(NOW(), 7), pn.horaire, null)) SEPARATOR ';') perm_horaire_exp_bientot,
                GROUP_CONCAT(DISTINCT CONCAT(IFNULL(cl.id, 0), ',',IF(pn.resultats BETWEEN NOW() AND ADDDATE(NOW(), 7), pn.resultats, null)) SEPARATOR ';') perm_resultats_exp_bientot,
                GROUP_CONCAT(DISTINCT CONCAT(IFNULL(cl.id, 0), ',',IF(pn.controleur BETWEEN NOW() AND ADDDATE(NOW(), 7), pn.controleur, null)) SEPARATOR ';') perm_controleur_exp_bientot


                FROM membres m
                LEFT JOIN dossier_joueur dj ON m.id = dj.id_joueur
                    AND dj.saison = $id_saison
                    AND dj.id_division = $id_division
                LEFT JOIN inscriptions i ON m.id = i.id_joueur AND i.saison = $id_saison AND i.id_division = $id_division
               # LEFT JOIN equipe_inscription ei ON i.id_equipe_inscr = ei.id
                LEFT JOIN joueur_equipe je ON je.id_joueur = m.id AND je.id_equipe IN ($liste_eq)
                LEFT JOIN role_equipe cap ON cap.id_adulte = m.id AND role = 0 AND cap.id_equipe IN ($liste_eq)
                LEFT JOIN permissions_niveaux pn ON pn.id_membre = m.id AND pn.categ = $sql_categ
                LEFT JOIN classes cl ON pn.classe = cl.classe
                WHERE ($cond_membre) AND NOT m.non_joueur
                GROUP BY m.id
                ORDER BY m.nom, m.prenom

		");
        if (!$res){
            return false;
        }
        $liste = db::result_array($res);;
        foreach($liste as &$vals){
            $vals['nom'] = mb_convert_case($vals['nom'], MB_CASE_TITLE, "UTF-8");
        }
        return $liste;
    }
}
?>
