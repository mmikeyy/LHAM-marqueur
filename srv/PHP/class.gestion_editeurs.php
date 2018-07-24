<?php

/**
 * Created by PhpStorm.
 * User: micra_000
 * Date: 2016-08-12
 * Time: 22:26
 */
class gestion_editeurs extends http_json
{
    static $perm_flds = [
        'perm_structure',
        'perm_ajout_document',
        'perm_admin',
        'perm_admin_mdp',
        'perm_admin_mdp_admin',
        'perm_admin_perm',
        'perm_inscription',
        'perm_admin_inscription',
        'perm_convert',
        'perm_communications',
        'perm_securite_inscription_ev',
        'perm_horaires',
        'perm_pratiques',
        'perm_pratiques_maitre',
        'perm_dispo_ress',
        'perm_alloc_ress',
        'inactif',
        'perm_tout_contenu',
        'perm_tout_publier',
        'perm_insertion_contenu',
        'perm_edit_classes',
        'perm_defilement',
        'perm_comm_seulem'
    ];

    static $menu_admin_tags = [
        'edit_menus',
        'edit_pages',
        'gestion_editeurs',
        'tableau_bord',
        'courriels',
        'themes',
        'horaires_themes',
        'liste_arenas',
        'gestion_cal',
        'membres',
        'resp_niveaux'
    ];

    function __construct($no_op = false)
    {
        parent::__construct();

        if (!perm::test('admin')) {
            $this->fin('non_autorise');
        }

        if (!$no_op) {
            self::execute_op();
        }
    }

    public function fn_get_editeurs()
    {
//        $res = db::query("
//                SELECT
//                  e.id_membre,
//                  proper(CONCAT(m.nom, ', ', m.prenom)) nom,
//                  perm_structure,
//                  perm_ajout_document,
//                  perm_admin,
//                  perm_inscription,
//                  perm_admin_inscription,
//                  perm_convert,
//                  perm_communications,
//                  perm_securite_inscription_ev,
//                  perm_horaires,
//                  inactif,
//                  perm_tout_contenu,
//                  perm_tout_publier,
//                  perm_insertion_contenu,
//                  perm_edit_classes,
//                  perm_defilement,
//                  perm_comm_seulem,
//                  age(m.date_naissance) age,
//                  GROUP_CONCAT(DISTINCT IF(ro.role = 0, ro.id_equipe, NULL) SEPARATOR ',') gerant,
//                  GROUP_CONCAT(DISTINCT IF(ro.role = 1, ro.id_equipe, NULL) SEPARATOR ',') entraineur,
//                  GROUP_CONCAT(DISTINCT IF(ro.role = 2, ro.id_equipe, NULL) SEPARATOR ',') adjoint,
//                  GROUP_CONCAT(DISTINCT rp.id_enfant SEPARATOR ',') enfants,
//                  GROUP_CONCAT(DISTINCT IF(pd.id_perm IS NOT NULL AND pd.perm_edit_expire IS NULL, pd.id_document, NULL) SEPARATOR ',') perm_docs_interdits,
//                  GROUP_CONCAT(DISTINCT IF(pd.id_perm IS NOT NULL AND pd.perm_edit_expire < NOW(), CONCAT_WS(',', pd.id_document, pd.perm_edit_expire), NULL) SEPARATOR ',') perm_docs_edit_exp,
//                  GROUP_CONCAT(DISTINCT IF(pd.id_perm IS NOT NULL AND pd.perm_edit_expire >= NOW(), CONCAT_WS(',', pd.id_document, pd.perm_edit_expire), NULL) SEPARATOR ',') perm_docs_edit_temp,
//                  GROUP_CONCAT(DISTINCT IF(pd.id_perm IS NOT NULL AND pd.perm_publier_expire < NOW(), CONCAT_WS(',', pd.id_document, pd.perm_publier_expire), NULL) SEPARATOR ',') perm_docs_publ_exp,
//                  GROUP_CONCAT(DISTINCT IF(pd.id_perm IS NOT NULL AND pd.perm_publier_expire > NOW(), CONCAT_WS(',', pd.id_document, pd.perm_publier_expire), NULL) SEPARATOR ',') perm_docs_publ_temp,
//                  GROUP_CONCAT(DISTINCT IF(pd.id_perm IS NOT NULL AND pd.perm_publier, pd.id_document, NULL) SEPARATOR ',') perm_docs_publ_tjrs,
//
//
//                  GROUP_CONCAT(DISTINCT IF(pc.id_perm IS NOT NULL AND pc.interdit, pc.id_contenu, NULL) SEPARATOR ',') perm_cont_edit_interdit,
//                  GROUP_CONCAT(DISTINCT IF(pc.id_perm IS NOT NULL AND pc.perm_edit_expire IS NULL AND NOT pc.interdit, pc.id_contenu, NULL) SEPARATOR ',') perm_cont_edit_tjrs,
//                  GROUP_CONCAT(DISTINCT IF(pc.id_perm IS NOT NULL AND pc.perm_edit_expire >= NOW() AND NOT pc.interdit, CONCAT_WS(',', pc.id_contenu, pc.perm_edit_expire), NULL) SEPARATOR ',') perm_cont_edit_temp,
//                  GROUP_CONCAT(DISTINCT IF(pc.id_perm IS NOT NULL AND pc.perm_edit_expire < NOW() AND NOT pc.interdit, CONCAT_WS(',', pc.id_contenu, pc.perm_edit_expire), NULL) SEPARATOR ',') perm_cont_edit_exp,
//
//                  GROUP_CONCAT(DISTINCT IF(pc.id_perm IS NOT NULL AND  pc.interdit AND pc.perm_publier, pc.id_contenu, NULL) SEPARATOR ',') perm_cont_publ_interdit,
//                  GROUP_CONCAT(DISTINCT IF(pc.id_perm IS NOT NULL AND NOT pc.interdit AND pc.perm_publier, pc.id_contenu, NULL) SEPARATOR ',') perm_cont_publ_tjrs,
//                  GROUP_CONCAT(DISTINCT IF(pc.id_perm IS NOT NULL AND NOT pc.interdit AND NOT pc.perm_publier AND pc.perm_publier_expire <= NOW(), CONCAT_WS(',',pc.id_contenu, pc.perm_publier_expire), NULL) SEPARATOR ',') perm_cont_publ_exp,
//                  GROUP_CONCAT(DISTINCT IF(pc.id_perm IS NOT NULL AND NOT pc.interdit AND NOT pc.perm_publier AND pc.perm_publier_expire > NOW(), CONCAT_WS(',',pc.id_contenu, pc.perm_publier_expire), NULL) SEPARATOR ',') perm_cont_publ_temp,
//
//
//                  m.courriel,
//                  m.id_hcr,
//                  m.cell
//
//                FROM editeurs e
//                JOIN membres m ON e.id_membre = m.id
//                LEFT JOIN roles_courants ro ON ro.id_adulte = m.id
//                LEFT JOIN rel_parent rp ON rp.id_parent = m.id
//                LEFT JOIN permissions_documents pd ON pd.id_editeur = e.id_membre
//                LEFT JOIN permissions_contenu pc ON pc.id_editeur = e.id_membre
//                WHERE NOT superhero
//                GROUP BY e.id_membre
//                ORDER BY m.nom, m.prenom
//            ", 'acces_table');
//
//        $liste = db::result_array($res);
//        A::setIntEach($liste, [
//            'perm_structure',
//            'perm_ajout_document',
//            'perm_admin',
//            'perm_inscription',
//            'perm_admin_inscription',
//            'perm_convert',
//            'perm_communications',
//            'perm_securite_inscription_ev',
//            'perm_horaires',
//            'inactif',
//            'perm_tout_contenu',
//            'perm_tout_publier',
//            'perm_insertion_contenu',
//            'perm_edit_classes',
//            'perm_defilement',
//            'perm_comm_seulem'
//        ]);
//        A::explodeEach($liste, [
//            'gerant',
//            'entraineur',
//            'adjoint',
//            'enfants',
//            'perm_docs_interdits',
//            'perm_docs_edit_exp',
//            'perm_docs_edit_temp',
//            'perm_docs_publ_exp',
//            'perm_docs_publ_temp',
//            'perm_docs_publ_tjrs',
//            'perm_cont_edit_interdit',
//            'perm_cont_edit_tjrs',
//            'perm_cont_edit_temp',
//            'perm_cont_edit_exp',
//            'perm_cont_publ_interdit',
//            'perm_cont_publ_tjrs',
//            'perm_cont_publ_exp',
//            'perm_cont_publ_temp'
//        ]);
//        foreach([
//                    'perm_docs_edit_exp',
//                    'perm_docs_edit_temp',
//                    'perm_docs_publ_exp',
//                    'perm_docs_publ_temp',
//                    'perm_cont_edit_temp',
//                    'perm_cont_edit_exp',
//                    'perm_cont_publ_exp',
//                    'perm_cont_publ_temp'
//                ] as $tag){
//            foreach($liste as &$row){
//                if ($row[$tag]){
//                    $ref = [];
//                    foreach(array_chunk($row[$tag], 2) as $val){
//                        $ref[$val[0]] = $val[1];
//                    }
//                    $row[$tag] = $ref;
//                }
//
//            }
//        }
//


        self::set_data('liste', $this->get_perms_editeur());


        $res = db::query("
                SELECT c.id_contenu id, c.tag, GROUP_CONCAT(DISTINCT s.id_document SEPARATOR ',') docs
                FROM contenus c
                JOIN permissions_contenu pc USING(id_contenu)
                LEFT JOIN structure2 s ON s.id_contenu = c.id_contenu AND s.archive IS NULL AND s.id_editeur IS NULL 
                GROUP BY c.id_contenu
            ", 'acces_table');
        $liste = db::result_array($res, 'id');
        A::explodeEach($liste, ['docs']);
        self::set_data('ref_cont', $liste);

        $res = db::query("
                SELECT DISTINCT d.id_document id, d.tag_document `desc`
                FROM documents d
                LEFT JOIN permissions_documents pd USING(id_document)
                LEFT JOIN (
                  SELECT DISTINCT id_document  
                  FROM structure2 s
                  JOIN permissions_contenu pc USING(id_contenu)
                  WHERE s.archive IS NULL AND s.id_editeur IS NULL
                  ) docs_perm ON d.id_document = docs_perm.id_document
                WHERE pd.id_document IS NOT NULL OR docs_perm.id_document IS NOT NULL
            ", 'acces_table');

        self::set_data('ref_doc', db::result_array($res, 'id'));

        $ids_docs = array_keys(self::$data['ref_doc']);
        do {
            $sql_ids = implode($ids_docs);
            $res = db::query("
                    SELECT d.id_document id, d.tag_document `desc`
                    FROM layout_document ld 
                    JOIN documents d USING (id_document)
                    WHERE ld.id_child_document IN ($sql_ids) AND ld.id_document NOT IN ($sql_ids)
                ", 'acces_table');
            if ($res->num_rows){
                while ($row = $res->fetch_assoc()){
                    $ids_docs[] = $row['id'];
                    self::$data['ref_doc'][$row['id']] = ['desc'=>$row['desc']];
                }
            }

        } while ($res->num_rows);



        $res = db::query("
                SELECT DISTINCT eq.id_equipe id, CONCAT(rn.categ, '-', cl.classe, ' ', proper(ne.nom_std)) nom
                FROM equipes eq
                JOIN rang_niveau rn ON eq.division = rn.id
                JOIN classes cl ON eq.classe = cl.id
                JOIN noms_equipes ne ON ne.id = eq.id_nom_std
                JOIN roles_courants rc ON rc.id_equipe = eq.id_equipe
                JOIN editeurs ed ON rc.id_adulte = ed.id_membre
            ", 'acces_table');

        self::set_data('ref_equipes', db::result_array_one_value($res, 'nom', 'id'));

        $res = db::query("
                SELECT DISTINCT  m.id, proper(CONCAT_ws(' ', m.prenom, m.nom)) nom, age(m.date_naissance) age
                FROM membres m
                JOIN rel_parent rel ON rel.id_enfant = m.id
                JOIN editeurs ed ON rel.id_parent = ed.id_membre
            ", 'acces_table');

        self::set_data('ref_enfants', db::result_array($res, 'id'));



        $res = db::query("
            SELECT id_editeur id, LOWER(tag) tag
            FROM rel_editeur_admin_menu
            
        ", 'acces_table');
        $liste = db::result_array_group($res, 'id');
        foreach($liste as $id=>&$vals){
            $vals = array_map(function($v){return $v['tag'];}, $vals);
        }

        self::set_data('ref_admin_menu', $liste);
        self::set_data('liste_admin_menu_tags', self::$menu_admin_tags);


        $this->succes();
    }

    public function get_perms_editeur($id = null)
    {
        if (!$id){
            $cond = 1;
        } else {
            if (!is_array($id)){
                $cond = "e.id_membre = $id";
            } else {
                $sql_ids = implode(',', $id);
                $cond = "e.id_membre IN ($sql_ids)";
            }

        }
        $res = db::query("
                SELECT 
                  e.id_membre,
                  proper(CONCAT(m.nom, ', ', m.prenom)) nom,
                  perm_structure,
                  perm_ajout_document,
                  perm_admin,
                  perm_admin_mdp,
                  perm_admin_mdp_admin,
                  perm_admin_perm,
                  perm_inscription,
                  perm_admin_inscription,
                  perm_convert,
                  perm_communications,
                  perm_securite_inscription_ev,
                  perm_horaires,
                  perm_pratiques,
                  perm_pratiques_maitre,
                  perm_dispo_ress,
                  perm_alloc_ress,
                  inactif,
                  perm_tout_contenu,
                  perm_tout_publier,
                  perm_insertion_contenu,
                  perm_edit_classes,
                  perm_defilement,
                  perm_comm_seulem,
                  age(m.date_naissance) age,
                  GROUP_CONCAT(DISTINCT IF(ro.role = 0, ro.id_equipe, NULL) SEPARATOR ',') gerant,
                  GROUP_CONCAT(DISTINCT IF(ro.role = 1, ro.id_equipe, NULL) SEPARATOR ',') entraineur,
                  GROUP_CONCAT(DISTINCT IF(ro.role = 2, ro.id_equipe, NULL) SEPARATOR ',') adjoint,
                  GROUP_CONCAT(DISTINCT rp.id_enfant SEPARATOR ',') enfants,
                  GROUP_CONCAT(DISTINCT IF(pd.id_perm IS NOT NULL AND pd.perm_edit_expire IS NULL, pd.id_document, NULL) SEPARATOR ',') perm_docs_interdits,
                  GROUP_CONCAT(DISTINCT IF(pd.id_perm IS NOT NULL AND pd.perm_edit_expire < NOW(), CONCAT_WS(',', pd.id_document, pd.perm_edit_expire), NULL) SEPARATOR ',') perm_docs_edit_exp,
                  GROUP_CONCAT(DISTINCT IF(pd.id_perm IS NOT NULL AND pd.perm_edit_expire >= NOW(), CONCAT_WS(',', pd.id_document, pd.perm_edit_expire), NULL) SEPARATOR ',') perm_docs_edit_temp,
                  GROUP_CONCAT(DISTINCT IF(pd.id_perm IS NOT NULL AND pd.perm_publier_expire < NOW(), CONCAT_WS(',', pd.id_document, pd.perm_publier_expire), NULL) SEPARATOR ',') perm_docs_publ_exp,
                  GROUP_CONCAT(DISTINCT IF(pd.id_perm IS NOT NULL AND pd.perm_publier_expire > NOW(), CONCAT_WS(',', pd.id_document, pd.perm_publier_expire), NULL) SEPARATOR ',') perm_docs_publ_temp,
                  GROUP_CONCAT(DISTINCT IF(pd.id_perm IS NOT NULL AND pd.perm_publier, pd.id_document, NULL) SEPARATOR ',') perm_docs_publ_tjrs,
                  
                  
                  GROUP_CONCAT(DISTINCT IF(pc.id_perm IS NOT NULL AND pc.interdit, pc.id_contenu, NULL) SEPARATOR ',') perm_cont_edit_interdit,
                  GROUP_CONCAT(DISTINCT IF(pc.id_perm IS NOT NULL AND pc.perm_edit_expire IS NULL AND NOT pc.interdit, pc.id_contenu, NULL) SEPARATOR ',') perm_cont_edit_tjrs,
                  GROUP_CONCAT(DISTINCT IF(pc.id_perm IS NOT NULL AND pc.perm_edit_expire >= NOW() AND NOT pc.interdit, CONCAT_WS(',', pc.id_contenu, pc.perm_edit_expire), NULL) SEPARATOR ',') perm_cont_edit_temp,
                  GROUP_CONCAT(DISTINCT IF(pc.id_perm IS NOT NULL AND pc.perm_edit_expire < NOW() AND NOT pc.interdit, CONCAT_WS(',', pc.id_contenu, pc.perm_edit_expire), NULL) SEPARATOR ',') perm_cont_edit_exp,
                  
                  GROUP_CONCAT(DISTINCT IF(pc.id_perm IS NOT NULL AND  pc.interdit AND pc.perm_publier, pc.id_contenu, NULL) SEPARATOR ',') perm_cont_publ_interdit,
                  GROUP_CONCAT(DISTINCT IF(pc.id_perm IS NOT NULL AND NOT pc.interdit AND pc.perm_publier, pc.id_contenu, NULL) SEPARATOR ',') perm_cont_publ_tjrs,
                  GROUP_CONCAT(DISTINCT IF(pc.id_perm IS NOT NULL AND NOT pc.interdit AND NOT pc.perm_publier AND pc.perm_publier_expire <= NOW(), CONCAT_WS(',',pc.id_contenu, pc.perm_publier_expire), NULL) SEPARATOR ',') perm_cont_publ_exp,
                  GROUP_CONCAT(DISTINCT IF(pc.id_perm IS NOT NULL AND NOT pc.interdit AND NOT pc.perm_publier AND pc.perm_publier_expire > NOW(), CONCAT_WS(',',pc.id_contenu, pc.perm_publier_expire), NULL) SEPARATOR ',') perm_cont_publ_temp,
                  
                  
                  m.courriel,
                  m.id_hcr,
                  m.cell
                  
                FROM editeurs e
                JOIN membres m ON e.id_membre = m.id
                LEFT JOIN roles_courants ro ON ro.id_adulte = m.id
                LEFT JOIN rel_parent rp ON rp.id_parent = m.id
                LEFT JOIN permissions_documents pd ON pd.id_editeur = e.id_membre
                LEFT JOIN permissions_contenu pc ON pc.id_editeur = e.id_membre
                WHERE NOT superhero AND ($cond)
                GROUP BY e.id_membre
                ORDER BY m.nom, m.prenom
            ", 'acces_table');

        $liste = db::result_array($res);
        A::setIntEach($liste, self::$perm_flds);
        A::explodeEach($liste, [
            'gerant',
            'entraineur',
            'adjoint',
            'enfants',
            'perm_docs_interdits',
            'perm_docs_edit_exp',
            'perm_docs_edit_temp',
            'perm_docs_publ_exp',
            'perm_docs_publ_temp',
            'perm_docs_publ_tjrs',
            'perm_cont_edit_interdit',
            'perm_cont_edit_tjrs',
            'perm_cont_edit_temp',
            'perm_cont_edit_exp',
            'perm_cont_publ_interdit',
            'perm_cont_publ_tjrs',
            'perm_cont_publ_exp',
            'perm_cont_publ_temp'
        ]);
        foreach([
                    'perm_docs_edit_exp',
                    'perm_docs_edit_temp',
                    'perm_docs_publ_exp',
                    'perm_docs_publ_temp',
                    'perm_cont_edit_temp',
                    'perm_cont_edit_exp',
                    'perm_cont_publ_exp',
                    'perm_cont_publ_temp'
                ] as $tag){
            foreach($liste as &$row){
                if ($row[$tag]){
                    $ref = [];
                    foreach(array_chunk($row[$tag], 2) as $val){
                        $ref[$val[0]] = $val[1];
                    }
                    $row[$tag] = $ref;
                }

            }
        }

        return $liste;


    }

    public function fn_get_perms_un_editeur()
    {
        /**
         * @var int $id
         **/
        extract(self::check_params(
            'id;unsigned'
        ));

        $data = $this->get_perms_editeur($id);

        if (count($data)){
            self::set_data('data', $data[0]);
        }

        $this->succes();
    }


    public function fn_nettoyer_permissions()
    {
        /**
         * @var int $contenus_non_publies
         * @var int $expirees_depuis
         **/
        extract(self::check_params(
            'contenus_non_publies;bool',
            'expirees_depuis;unsigned;min:12;opt' // mois
        ));
        $ids_membres_refresh = [];

        if ($contenus_non_publies) {
            $res = db::query("
                SELECT pc.id_perm, pc.id_editeur, COUNT(IF(s.archive IS NULL AND s.id_editeur IS NULL AND s.`element_structure` IS NOT NULL, 1, NULL)) nb_publ
                FROM permissions_contenu pc
                LEFT JOIN structure2 s USING(id_contenu)
                GROUP BY pc.id_perm
                HAVING nb_publ = 0
                FOR UPDATE 
            ", 'acces_table');

            $liste = db::result_array_one_value($res, 'id_editeur', 'id_perm');
            if (count($liste)){
                $ids_membres_refresh = array_merge($ids_membres_refresh, array_unique(array_values($liste)));
                $ids_perms = array_keys($liste);

                $sql_ids_perms = implode(',', $ids_perms);
                $res = db::dquery("
                    DELETE FROM permissions_contenu
                    WHERE id_perm IN ($sql_ids_perms)
                ", 'acces_table');

            }


        }

        if (isset($expirees_depuis)){
            $exp = new DateTime();
            $exp->modify("- $expirees_depuis months");
            $exp = $exp->format('Y-m-d H:i:s');
            $sql_exp = db::sql_str($exp);
            $res = db::query("
                    select DISTINCT pc.id_editeur
                    FROM permissions_contenu pc
                    WHERE pc.perm_publier_expire <$sql_exp OR pc.perm_edit_expire < $sql_exp
                    OR (pc.perm_publier_expire IS NULL AND pc.perm_edit_expire IS NULL AND NOT pc.perm_publier AND NOT pc.interdit)
                ", 'acces_table');
            if ($res->num_rows){
                debug_print_once(print_r($res->fetch_all(),1));
                $ids_membres_refresh = array_unique(array_merge($ids_membres_refresh, db::result_array_one_value($res, 'id_editeur')));
            }

            $res = db::query("
                    DELETE FROM permissions_contenu
                    WHERE perm_edit_expire < $sql_exp AND perm_publier_expire < $sql_exp
                ", 'acces_table');


            $res = db::query("
                    UPDATE permissions_contenu
                    SET 
                      perm_publier_expire = IF(perm_publier_expire < $sql_exp, NULL, perm_publier_expire),
                      perm_edit_expire = IF(perm_edit_expire < $sql_exp, NULL, perm_edit_expire)
                  
                ", 'acces_table');

            $res = db::query("
                    DELETE pc FROM permissions_contenu pc
                    WHERE pc.perm_publier_expire IS NULL AND pc.perm_edit_expire IS NULL AND NOT pc.perm_publier AND NOT pc.interdit
                ", 'acces_table');


        }

        if ($ids_membres_refresh){
            self::set_data('updates', $this->get_perms_editeur($ids_membres_refresh));
        }

        db::rollback();

        $this->succes();

    }

    public function fn_changer_perm()
    {
        // ancienne valeur: inactif|perm_admin|perm_structure|perm_ajout_document|perm_inscription|perm_admin_inscription|perm_convert|perm_communications|perm_tout_contenu|perm_tout_publier|perm_insertion_contenu|perm_edit_classes|perm_defilement|perm_comm_seulem|perm_pratiques|perm_dispo_ress
        /**
         * @var int $id_editeur
         * @var string $perm
         * @var int $val
         **/
        extract(self::check_params(
            'id_editeur;unsigned',
            'perm;regex:#^(' . implode('|', self::$perm_flds) . ')$#',
            'val;bool;bool_to_num'
        ));

        /**
         * @var int $nb
         **/
        $res = db::query("
                SELECT COUNT(*) nb
                FROM editeurs
                WHERE id_membre = $id_editeur
            ", 'acces_table');

        extract($res->fetch_assoc());
        if (!$nb){
            $this->fin('introuvable');
        }
        $res = db::dquery("
                UPDATE editeurs
                SET $perm = $val
                WHERE id_membre = $id_editeur
                LIMIT 1
            ", 'acces_table');
        $this->succes();


    }

    public function fn_ajouter_editeur()
    {

        /**
         * @var int $id
         **/
        extract(self::check_params(
            'id;unsigned'
        ));
        /**
         * @var int $existe
         * @var int $editeur
         **/
        $res = db::query("
                SELECT COUNT(*) existe, COUNT(e.id_membre) editeur
                FROM membres m 
                LEFT JOIN editeurs e ON m.id = e.id_membre
                WHERE m.id = $id
                FOR UPDATE 
            ", 'acces_table');

        if ($res->num_rows == 0){
            $this->fin('introuvable');
        }
        extract($res->fetch_assoc());
        if ($existe == 0) {
            $this->fin('introuvable');
        }
        if ($editeur){
            $this->fin('deja_editeur');
        }
        $res = db::query("
                INSERT INTO editeurs
                SET id_membre = $id
            ", 'acces_table');
        $this->fn_get_editeurs();

    }

    public function fn_retirer_editeur()
    {

        /**
         * @var int $id
         **/
        extract(self::check_params(
            'id;unsigned'
        ));
        $res = db::query("
                DELETE FROM editeurs
                WHERE id_membre = $id
            ", 'acces_table');

        $this->succes();
    }

    public function fn_change_menu_admin_perm()
    {
        /**
         * @var int $id_membre
         * @var string $tag
         **/
        extract(self::check_params(
            'id_membre;unsigned',
            'tag;string'
        ));

        if (!in_array($tag, self::$menu_admin_tags)){
            $this->fin('invalide');
        }

        if (!perm::test('editeur', $id_membre)){
            $this->fin('membre_non_editeur');
        }


        $sql_tag = db::sql_str($tag);

        /**
         * @var int $nb
         **/
        $res = db::query("
                SELECT COUNT(*) nb
                FROM rel_editeur_admin_menu
                WHERE id_editeur = $id_membre AND tag = $sql_tag
                FOR UPDATE 
            ", 'acces_table');
        extract($res->fetch_assoc());
        if ($nb){
            $res = db::query("
                    DELETE FROM rel_editeur_admin_menu
                    WHERE id_editeur = $id_membre AND tag = $sql_tag
                ", 'acces_table');

        } else {
            $res = db::query("
                    INSERT IGNORE INTO rel_editeur_admin_menu
                    SET id_editeur = $id_membre, tag = $sql_tag
                ", 'acces_table');

        }

        $res = db::query("
                SELECT lower(tag) tag
                FROM rel_editeur_admin_menu
                WHERE id_editeur = $id_membre
            ", 'acces_table');
        self::set_data('update', db::result_array_one_value($res, 'tag'));

        $this->succes();

    }


}