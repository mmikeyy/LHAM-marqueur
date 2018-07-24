<?php


class model_feuille_match extends crud {
    const unsigned_opt = 'regex:#^\d*$#;opt;empty_string_to_null';
    const bool_opt = 'bool;opt;bool_to_num';
    public $valid = array(
        'id' => self::unsigned_opt,
        'id_match' => 'unsigned',
        'id_equipe' => self::unsigned_opt,
        'id_membre' => self::unsigned_opt,
        'periode' => 'unsigned',
        'chrono' => 'regex:#^\d\d:\d\d$#',
        'duree_punition' => self::unsigned_opt,
        'expulsion' => self::bool_opt,
        'codes_punition' => 'string;opt',
        'but' => self::bool_opt,
        'but_special' => 'regex:#^(|penalite)$#;opt;empty_string_to_null',
        'but_propre_filet' => self::bool_opt,
        'id_membre_passe' => self::unsigned_opt,
        'id_membre_passe2' => self::unsigned_opt,
        'passe_propre_filet' => self::bool_opt,
        'commentaire' => 'string;max:500;opt',
        'id_membre_gardien' => self::unsigned_opt,
        'resultat' => self::unsigned_opt,
        'resultat_adversaire' => self::unsigned_opt,
        'avantage_numerique' => 'int;min:-1;max:1;opt;empty_string_to_null',
        'changement_gardien' => self::bool_opt,
        'gardien1' => self::unsigned_opt,
        'gardien2' => self::unsigned_opt,
        'type_enreg' => 'regex:#^(punition|but|changement_gardien|fin_periode|fusillade)$#'

        
    );

    public $boolean_flds = [
        'expulsion',
        'but',
        'but_propre_filet',
        'passe_propre_filet',
        'changement_gardien',
        'fin_periode'
    ];

    public $int_flds = [
        'periode',
        'duree_punition',
        'resultat',
        'resultat_adversaire',
        'avantage_numerique'
    ];
    
    public $merged_get_data = array(
        // champ de $_GET => champ db
        'id' => 'id',
        'id_match' => 'id_match'
        );
    public $table = 'match_feuille';
    
    public $std_db_field_exclusions = ['avantage_numerique','desavantage_numerique'];
    public $std_order_clause = "ORDER BY periode, IF(type_enreg = 'fin_periode', 1, 0), chrono";
    
    function __construct()
    
    {   
        self::add_msg_file('model_feuille_match');
        $this->accept_all_valid_data_to_db();
        parent::__construct();
        
        $this->std_filter_clause = "id_match = {$this->all_vals['id_match']}";
        
        self::execute_op();
        
    }
    
    function validate_access($retourner = false)
    {
        if (parent::validate_access(true)){
            /*sajdfhksadhfkjlsahdfkjhasdkjfsahdfsad f*/
            
            return true;
        }
        
        if (count($this->champs_db_obligatoires_manquants)){
            #debug_print_once("params manquants " . implode(',', $this->champs_db_obligatoires_manquants));
            $this->erreur('params_manquent');
        }
        if (!login_visiteur::logged_in()){
            debug_print_once('........... pas logged in');
            $this->erreur('ouvrir_session');
        }
//        $id_visiteur = session::get('id_visiteur');
        
        $id_match = $this->all_vals['id_match'];
        if (!$id_match){
            $this->erreur('no_match_manquant');
        }
        $record = new record_stats_match();
        $record->load($id_match, 1);
        
//        $res = db::query("
//            SELECT marqueur, id_equipe1, id_equipe2
//            FROM stats_matchs
//            WHERE id = $id_match
//		", 			'acces_table', '');
        
        if (!$record->is_found){
            $this->erreur('introuvable');
        }
        if (!$record->is_editable_marqueur(true)){
            $this->non_autorise();
        }
        ;
        if ($record->forfait1 or $record->forfait2){
            
            $this->erreur('pas_de_events_pour_forfait');
        }
        
        return true;
    }
    
    function valide($vals, $flds)
    {
//        debug_print_once('debut valide, $vals = ' . print_r($vals,1));
        $vals = parent::valide($vals, $flds);
        
        
        
        if ($this->is_create() or $this->is_patch() or $this->is_update()){
            if (array_key_exists('id_membre_gardien', $vals) and !$vals['id_membre_gardien']){
                $vals['id_membre_gardien'] = null;
            }
            if (!$this->is_create()){
                $res = db::query("
                    SELECT id_equipe, id_membre, periode, codes_punition, but, but_special, resultat, resultat_adversaire, type_enreg
                    FROM $this->table
                    WHERE id = {$vals['id']}
                    FOR UPDATE
                ") or $this->erreur('acces_table');
                if ($res->num_rows == 0){
                    $this->fin('introuvable');
                }
                
                $valeurs_existantes = $res->fetch_assoc();
                if (array_key_exists('type_enreg', $vals) and $vals['type_enreg'] != $valeurs_existantes['type_enreg']){
                    $this->erreur('chg_type_enreg_non_permis');
                }
                $updated_values = array_merge($valeurs_existantes, $vals);

            } else {
                $updated_values = $vals;
            }
            $check = new validation_help($updated_values);

            # but sans fusillade doit être attribué à un joueur et une équipe
            if ($check->is('but') and !($check->is_true('id_membre') and $check->is_true('id_equipe'))){
                $this->erreur('but_non_attribue');
            }


            if ($check->is_true('duree_punition')){
                if (!$check->is_true('id_membre')){
                    $this->erreur('punition_non_attribuee');
                }
                if (!$check->is_true('id_equipe')){
                    $this->erreur('punition_sans_equipe');
                }
            }
            if ($check->is_true('duree_punition') and !$check->is_true('codes_punition')){
                $this->erreur('punition_sans_codes');
            }
            if ($check->any_true(['id_membre_passe','id_membre_passe2']) and !$check->is('but')){
                $this->erreur('passe_sans_but');
            }
            if ($check->all_true(['id_membre_passe','id_membre_passe2'])){
                if ($updated_values['id_membre_passe'] == $updated_values['id_membre_passe2']){
                    $this->erreur('deux_passes_meme_joueur');
                }
            }
            if (($check->null_or_not_set('resultat') or $check->null_or_not_set('resultat_adversaire')) and $check->is('but')){
                $this->erreur('manque_result_avec_but');
            }
            # s'assurer que si un but est 'spécial', il y a bien un but
            if ($check->is_true('but_special') and !$check->is('but')){
                $this->erreur('but_special_mais_pas_de_but');
            }
            # s'assurer que fin de période est mutuellement exclusif avec but ou punition
            if ($check->is('fin_periode')){
                if($check->is_true('but')){
                    $this->erreur('but_en_fin_de_periode');
                }
                # pas de punition à fin de période
                if ($check->is_true('duree_punition')){
                    $this->erreur('punition_en_fin_de_periode');
                }
                # pas de changement de gardien si fin de période
                if ($check->is_true('changement_gardien')){
                    $this->erreur('chg_gardien_en_fin_periode');
                }
                # pas d'expulsion si fin de période
                if ($check->is_true('expulsion')){
                    $this->erreur('expulsion_en_fin_periode');
                }
                # vérifier qu'un n'y a qu'une fin de période par période, et qu'elle est
                # le dernier événement de la période
                $match = $updated_values['id_match'];
                $periode = $updated_values['periode'];

                if ($this->is_create()){
                    $condition_id = 1;
                } else {
                    $condition_id = "id <> {$updated_values['id']}";
                }
                /**
                 * @var $nb_fins_per int
                 * @var $chrono string
                 */
                $res = db::dquery("
                    SELECT COUNT(IF(type_enreg = 'fin_periode', 1, NULL)) nb_fins_per,
                        max(chrono) chrono
                    FROM $this->table
                    WHERE id_match = $match
                    AND periode = $periode
                    AND $condition_id
                ") or $this->erreur('acces_table');
                extract($res->fetch_assoc());
                if ($nb_fins_per){
                    $this->erreur('une_seule_fin_de_periode_par_periode');
                }
                if ($chrono > A::get_or($updated_values, 'chrono', '')){
                    $val_chrono = A::get_or($updated_values, 'chrono', '???');
//                    debug_print_once(print_r($updated_values, 1));
//                    debug_print_once("refusé $chrono > $val_chrono");
                    $this->erreur('aucun_evenement_apres_fin_periode');
                }
            }

        }
        
        return $vals;
    }
    
    function transform($vals)
    {
        $this->null_to_empty_str_if_exists($vals, 'codes_punition');
        return $vals;
    }

//    function do_if_updated($id = null) {
//        $id_match = $this->all_vals['id_match'];
//        if (!$id_match) {
//            return;
//        }
//        $record = new record_stats_match();
//        $record->load($id_match, 2);
//        $record->update(['sj_ok2' => 0, 'sj_ok1' => 0]);
//    }

}

class validation_help
{
    public $vals;
    function __construct($vals)
    {
        $this->vals = $vals;
    }
    function is_true($index)
    {
        return array_key_exists($index, $this->vals) and $this->vals[$index];
    }
    function all_true($inds)
    {
        foreach($inds as $ind){
            if (!$this->is_true($ind)){
                return false;
            }
        }
        return true;
    }
    function any_true($inds)
    {
        foreach($inds as $ind){
            if ($this->is_true($ind)){
                return true;
            }
        }
        return false;
    }
    function null_or_not_set($ind)
    {
        if (!array_key_exists($ind, $this->vals)){
            return true;
        }
        if (is_null($this->vals[$ind])){
            return true;
        }
        return false;
    }
    function is($type_enreg)
    {
        if (array_key_exists('type_enreg', $this->vals) and $this->vals['type_enreg'] == $type_enreg){
            return true;
        }
        return false;
    }

}