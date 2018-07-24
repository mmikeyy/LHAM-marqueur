<?php

class record_stats_match extends record{

    public $validation_ = [
        'id'=>'unsigned',
        "ref"=> 'unsigned',
        'id_tournoi'=> 'unsigned',
        "date"=>'date',
        "debut"=>'regex:#^([0-1][0-9]|2[0-4]):[0-5]\d(:\d\d)$#',
        "lieu"=>'string;max:50',
        'id_equipe1' => 'unsigned;default_null;empty_string_to_null',
        "equipe1"=> 'string;max:35',
        "sj_ok1"=> 'unsigned;max:1',
        'eq_gagne_match_1'=> 'unsigned;default_null;empty_string_to_null',
        'perd_match_1' => 'unsigned;max:1',
        "id_equipe2"=> 'unsigned;default_null;empty_string_to_null',
        "equipe2" => 'string;max:35',
        "sj_ok2"=> 'unsigned;max:1',
        'eq_gagne_match_2'=> 'unsigned;default_null;empty_string_to_null',
        'perd_match_2' => 'unsigned;max:1',
        "source"=> 'string;max:10',
        "groupe"=> 'string;max:10',
        "saison"=> 'unsigned',
        "pts1" => 'unsigned;default_null',
        "fj1" => 'unsigned',
        "pts2" => 'unsigned;default_null',
        "fj2" => 'unsigned',
        "a_replanifier" => 'unsigned;max:1',
        "type_ev"=>'unsigned',
        "marqueur"=> 'unsigned',
        "marqueur_confirme" => 'unsigned;max:1',
        "marqueur_confirme_par"=> 'unsigned',
        "marqueur_confirme_date" => 'date',
        "message_marqueur" => 'string',
        "locked"=> 'unsigned;max:1',
        "last_edit"=>'date',
        "fm_ok" => 'unsigned;max:1',
        "buts_propre_filet1"=> 'unsigned',
        "buts_propre_filet2"=> 'unsigned',
        "passes_propre_filet1"=> 'unsigned',
        "passes_propre_filet2"=> 'unsigned',
        "buts_filet_vide1"=> 'unsigned',
        "buts_filet_vide2"=> 'unsigned',
        "annule" => 'unsigned;max:1',
        "info"=> 'string;max:100',
        "date_results_updated"=>'date',
        'forfait1' => 'unsigned;max:1',
        'forfait2' => 'unsigned;max:1',
        'temps_an1'=> 'unsigned',
        'temps_dn1'=> 'unsigned',
        'duree_match'=> 'unsigned',
        'buts_an1'=> 'unsigned',
        'buts_an2'=> 'unsigned',
        'buts_dn1'=> 'unsigned',
        'buts_dn2'=> 'unsigned',
        'paye' => 'unsigned;max:1'
    ];
    public $derived_flds_ = [
        'now' => 'NOW()',
        'futur' => "IF(CONCAT(%sdate, ' ', %sdebut) > NOW(), 1, 0)",
        'jour' => 'DAY(%sdate)',
        'mois' => 'MONTH(%sdate)',
        'jour_sem' => 'DAYOFWEEK(%sdate)-1',
        'debut' => 'SUBSTR(%sdebut,1,5)',
        'age_min' => 'FLOOR(TIME_TO_SEC(TIMEDIFF(NOW(), CONCAT(%sdate, " ", %sdebut)))/60)'
    ];
    public $table_ = 'stats_matchs';
    public $id_ = 'id';

    public $found_ = 0;



    # donner true comme param pour vérifier que le visiteur  peut l'éditer comme marqueur
    function is_editable_marqueur($marqueur = false)
    {
        if ($this->annule or $this->a_replanifier or $this->locked){
            return false;
        }
        if ($marqueur){
            if ($this->marqueur == session::get('id_visiteur')){
                return true;
            }
            return perm::marqueur_match($this->id);
        }
        return false;
    }

    function is_forfait()
    {
        return $this->forfait1 or $this->forfait2;
    }

    function is_sj_ok()
    {
        return $this->sj_ok1 and $this->sj_ok2;
    }
    function is_editable()
    {
        return is_null($this->pts1) and is_null($this->pts2) and !$this->locked;
    }
    function defined_set($array, $index){
        foreach(explode(',', $index) as $ind) {
            if (!array_key_exists($ind, $array)) {
                return false;
            }
            if (!$array[$ind]) {
                return false;
            }
        }
        return true;
    }
    function validate($vals)
    {
        $validated = parent::validate($vals);
        # vérifier que pas match entre deux eq identiques
        $flds_equipes = 'id_equipe1,id_equipe2,eq_gagne_match_1,eq_gagne_match_2,perd_match_1,perd_match_2';
        $cur_vals = $this->select($flds_equipes);

        $updated_vals = array_merge($cur_vals, $validated);


        $updates = array_diff_assoc($updated_vals, $cur_vals);

        $updates_equipes = [];
        foreach(explode(',', $flds_equipes) as $fld){
            if (array_key_exists($fld, $updates)) {
                $updates_equipes[$fld] = $updates[$fld];
            }
        }

        if (count($updates_equipes)){
            # vérifier qu'on n'oppose pas une équipe à elle-même
            if ($this->defined_set($updated_vals, 'id_equipe1,id_equipe2') and $updated_vals['id_equipe1'] == $updated_vals['id_equipe2']){
                $this->erreur_ = 'meme_equipe';
                return false;
            }

            #verifier que si eq_gagne_match est spécifié, il ne réfère pas au match courant
            if (in_array($this->id, [$updated_vals['eq_gagne_match_1'], $updated_vals['eq_gagne_match_2']])){
                $this->erreur_ = 'equipe_gagnante_du_present_match';
                return false;
            }
            # verifier qu'on ne désigne pas comme équipes les gagnants ou perdants du même match
            if (
                $updated_vals['eq_gagne_match_1'] and
                $updated_vals['eq_gagne_match_1'] == $updated_vals['eq_gagne_match_2'] and
                $updated_vals['perd_match_1'] == $updated_vals['perd_match_2']
            ){
                $this->erreur_ = 'meme_equipe';
                return false;
            }


        }
        return $validated;



    }

    function post_update($vals, $res)
    {

        if (array_key_exists('pts1', $vals) or array_key_exists('pts2', $vals)){
            $this->load();
            $this->update_gagnants_perdants();
        }
    }
    /**
     * METTRE À JOUR EQUIPES DE MATCHS DÉFINIES COMME GAGNANTES OU PERDANTES
     * D'UN AUTRE MATCH, QUAND EN FONCTION DES RÉSULTATS DE CET AUTRE MATCH
     *
     * @var $pts1
     * @var $pts2
     * @var $id_equipe1
     * @var $id_equipe2
     */
    function update_gagnants_perdants()
    {

        if (!$this->found_){
            $this->load();
            if (!$this->found_){
                return false;
            }
        }



        if (is_null($this->pts1) or is_null($this->pts2) or is_null($this->id_equipe1) or is_null($this->id_equipe2)){
            #debug_print_once('return false 2');
            return false;
        }

        #debug_print_once(print_r($this->select('id_equipe1,pts1,id_equipe2,pts2'), 1));

        if ($this->pts1 != $this->pts2){
            if ($this->pts1 > $this->pts2){
                $id_gagnant = $this->id_equipe1;
                $id_perdant = $this->id_equipe2;
                #debug_print_once("gagnant = $id_gagnant ($this->pts1) perdant = $id_perdant ($this->pts2)");
            } else {
                $id_gagnant = $this->id_equipe2;
                $id_perdant = $this->id_equipe1;
                #debug_print_once("gagnant = $id_gagnant ($this->pts2) perdant = $id_perdant ($this->pts1)");
            }

            $res = db::query("
                UPDATE stats_matchs
                SET id_equipe1 = IF(eq_gagne_match_1 = $this->id, IF(perd_match_1, $id_perdant, $id_gagnant), id_equipe1),
                    id_equipe2 = IF(eq_gagne_match_2 = $this->id, IF(perd_match_2, $id_perdant, $id_gagnant), id_equipe2)
                WHERE $this->id IN (eq_gagne_match_1, eq_gagne_match_2) AND id <> $this->id
            ", 			'acces_table', '');
            #debug_print_once('return true');
            return true;
        }
        #debug_print_once('return false 3');
        return false;

    }
}

