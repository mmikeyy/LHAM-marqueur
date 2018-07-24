<?php

class crud extends http_json
{

    static $msg;
    static $msg_file = 'class_crud';

    public $id_fld = 'id';          # champ clef

    # mettre ici les règles de validation champ=>spec
    public $valid = array( # rendre obligatoires seulement champs requis pour update et create
                            # présence des champs non vérifiée pour autres opérations
        'id' => 'regex:#^\d*$#;opt;empty_string_to_null',

    );
    public $auto_conv_empty_string_to_null = true;

    public $boolean_flds = [];
    public $int_flds = [];


    public $postvar_flds = array(); # = liste des keys de array this->valid, tout simplement
    //
    // toute construction d'assignment pour écriture à db ne peut contenir que ces champs
    // permet d'envoyer plus de valeurs sans s'inquiéter de faire crasher avec mauvais nom de champ
    // ce qui permettrait aussi injection sql
    // pour accepter tous les champs validables comme champs de db, on peut
    //exécuter $this->accept_all_valid_data_to_db() au lieu de lister les champs un à un
    public $db_fields = array(
    );
    public $input_values = [];
    # exceptions qui ne seront pas automatiquement inclues dans liste de db_fields par fonction 'accept_all_valid_data_to_db';
    public $std_db_field_exclusions = array();

    public $no_update_fields = array( # champs à toujours exclure de tout 'update'
        'id'
    );
     # certaines valeurs viennent de l'URL et aboutissent dans $_GET; doivent être 'mergées' avec valeurs lues dans input
    public $merged_get_data = array(
        // champ de $_GET => champ db
        'id' => 'id'
        );

    public $table = '';                 # table à modifier
    public $std_query = '';             # requête pour envoyer info au client
    public $std_filter_clause = '1';    # ajoutée ainsi: where [la voici] AND (reste)
    public $std_group_clause = '';      # ajoutée à la toute fin de requête std au cas où de l'info secondaire récoltée multiplierait les enregistrements retournés
    public $std_order_clause = '';
    public $unique_flds_or_expr;

    public $fld_list = '*';      # string contenant la liste des champs à insérer dans query pour retourner les valeurs std au client
    public $table_fld_qualifier = ''; # sera utilisé seulement dans std_query
    #------------------- ci-dessus, paramètres réglables dans sous-classes


    public $return_result = false; # sera mis à true pour $no_op = true, pour appel d'une autre classe
    public $lock_method;
    public $access_rights = array(
      'PUT' => 'admin',
      'PATCH' => 'admin',
      'GET' => '',
      'POST' => 'admin',
      'DELETE' => 'admin'
    );

    public $include_nulls = false;



    public $all_vals;         # recevra toutes les valeurs reçues, (seulement celles pouvant être validées)
    public $db_vals;          # = all_vals, ne contenant que les valeurs correspondant à des champs de db, sauf id
    public $champs_obligatoires_manquants = array();    # la fonction de validation y met les champs obligatoires non fournis
    # = champs sans mention 'opt' dans règle de validation
    # NOTE : ne font pas échouer validation;
    public $champs_db_obligatoires_manquants = array(); # après validation, liste de 'champs_obligatoires_manquants' réduite pour contenir seulement champs db
    public $champs_convertis_null = array();            # la fnct de validation y met les champs convertis à null et leur valeur originale
    public $refuse_null = []; # liste nom=>val de valeurs à donner aux champs donnés lorsque leur valeur est nulle
    public $id;

    public $previous_vals = [];
    // contient une array = enregistrement pour 'id' lorsque fourni
    // produit comme sous-produit de la validation, pour consultation ultérieure si requis
    public $id_data;

    public $updated = false;


    function __construct($no_op = false){
        parent::__construct();
        # si ne finit pas par ., alors l'ajouter à condition qu'il soit mentionné
        if ($this->table_fld_qualifier and !preg_match('#\.$#', $this->table_fld_qualifier)){
            $this->table_fld_qualifier .= '.';
        }
        $fld_qualifier = str_replace('.', '', $this->table_fld_qualifier);
        self::$default_msgs = self::$msg_file;

        switch($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                $this->lock_method = 'LOCK IN SHARE MODE';
                break;
            default:
                $this->lock_method = 'FOR UPDATE';
        }

        debug_print_once('valid....' . print_r($this->valid, 1));
        $this->postvar_flds = array_keys($this->valid);
//        foreach($this->valid as $key=>$val){
//            $this->postvar_flds[] = $key;
//        }

        $this->all_vals = $this->valide($this->select($this->get_input(), $this->postvar_flds), $this->postvar_flds);

        # toujours laisser ceci APRES la validation pour que id soit validé aussi
        if (array_key_exists('id', $this->all_vals)){
            $this->id = $this->all_vals['id'];
            unset($this->all_vals['id']);
        }
        #debug_print_once("all vals apres enlevement id = $this->id" . print_r($this->all_vals,1));

        $this->db_vals = $this->select($this->all_vals, $this->db_fields);

        debug_print_once('db_vals = ' . print_r($this->db_vals, 1));

        $this->champs_db_obligatoires_manquants = $this->select($this->champs_obligatoires_manquants, $this->db_fields);


        if (!$this->fld_list){
            $this->fld_list = $this->table_fld_qualifier .  '*';
        }

        if (!$this->std_query){
            $this->std_query = "
                SELECT $this->fld_list
                FROM $this->table $fld_qualifier

                ";
        }

        if ($no_op){
            $this->return_result = true;
        }
    }

    static function add_msg_file($file)
    {
        self::$msg_file .= ',' . $file;
    }

    function succes_($msg1='', $msg2='')
    {
        if (!$this->return_result){
            self::succes($msg1,$msg2);
        }

    }

    static function succes($msg1='', $msg2 = '')
    {
        parent::succes($msg1,$msg2);
    }



    static function load_msg(){
        if (!self::$msg_file){
            return false;
        }
		if (self::$msg){
			return true;
		}
		self::$msg = new msg(self::$msg_file);
        return true;
	}
	static function msg($ind = ''){
		if (!self::load_msg()){
            return '?';
        }

		return self::$msg->get($ind);
	}
    static function erreur($msg = '')
    {


        if ($msg){
            if (self::load_msg()){
                $msg = self::msg($msg);
            }
        }

        parent::fin($msg);

    }

    function select($array_source, $keys, $not_null = false)
    {
        if (!$array_source){
            return array();
        }
        if (is_string($keys)){
            $keys = explode(',', $keys);
        }
        $to_ret = array();
        foreach($keys as $key){
            if (array_key_exists($key, $array_source) and !($not_null and is_null($array_source[$key]))){
                $to_ret[$key] = $array_source[$key];
            }
        }
        return $to_ret;
    }

    function accept_all_valid_data_to_db($except = null){
        if (is_null($except)){
            $except = $this->std_db_field_exclusions;;
        } else if (is_string($except)){
            $except = explode(',', $except);
        }
        foreach($this->valid as $fld=>$spec){
            if (in_array($fld, $except)){
                continue;
            }
            $this->db_fields[] = $fld;
        }
    }

    static function remove_keys($array, $keys)
    {
        foreach($keys as $key){
            if (array_key_exists($key, $array)){
                unset($array[$key]);
            }
        }
        return $array;
    }
    function validate_access($retourner = false)
    {
        debug_print_once('validation accès');
        if (perm::test('admin')){
            debug_print_once('ok: admin');
            return true;
        }

        if (!array_key_exists($_SERVER['REQUEST_METHOD'], $this->access_rights)){
            if ($retourner){
                return null;
            }
            self::erreur('Droits non définis pour l\'opération tentée (' . $_SERVER['REQUEST_METHOD'] . ')');
        }
        if (!$this->access_rights[$_SERVER['REQUEST_METHOD']]){
            return true;
        }

        $perm = perm::test($this->access_rights[$_SERVER['REQUEST_METHOD']]);
        if ($retourner){
            return $perm;
        }
        if (!$perm){
            self::erreur('non_autorise');
        }
        return null;

    }

    function is_update()
    {
        #if ($_SERVER['REQUEST_METHOD'] == 'PUT') #debug_print_once("============ update");
        if ($this->return_result){
            return $this->op == 'UPDATE';
        }

        return $_SERVER['REQUEST_METHOD'] == 'PUT';
    }
    function is_patch()
    {
        #if ($_SERVER['REQUEST_METHOD'] == 'PATCH') #debug_print_once("============ patch");
        if ($this->return_result){
            return $this->op == 'PATCH';
        }
        return $_SERVER['REQUEST_METHOD'] == 'PATCH';
    }
    function is_create()
    {
        #if ($_SERVER['REQUEST_METHOD'] == 'POST') #debug_print_once("============= create");
        if ($this->return_result){
            return $this->op == 'POST';
        }
        return $_SERVER['REQUEST_METHOD'] == 'POST';
    }
    function is_read()
    {
        #if ($_SERVER['REQUEST_METHOD'] == 'GET') #debug_print_once("============= READ");
        if ($this->return_result){
            return $this->op == 'GET';
        }
        return $_SERVER['REQUEST_METHOD'] == 'GET';
    }

    function is_delete()
    {
        #if ($_SERVER['REQUEST_METHOD'] == 'DELETE') #debug_print_once("============= DELETE");
        if ($this->return_result){
            return $this->op == 'DELETE';
        }
        return $_SERVER['REQUEST_METHOD'] == 'DELETE';
    }



    function get_input(){
        if ($this->input_values){
            return $this->input_values;
        }
        global $input_stream;
        debug_print_once('input stream ' . $input_stream);
        $vals = json_decode($input_stream, true);
        debug_print_once('vals input stream = ' . print_r($vals,1));

        foreach($this->merged_get_data as $get_fld => $db_fld){
            if (array_key_exists($get_fld, $_GET) and $_GET[$get_fld] != ''){
                $vals[$db_fld] = $_GET[$get_fld];
            }
        }
        $this->input_values = $vals;
        return $vals;
    }


    function get_assignment_data($updates, $implode = true)
    {
        db::sql_str_($updates);
        $flds = array();
        $values = array();
        foreach($updates as $fld=>$val){
            if (!in_array($fld, $this->db_fields)){
                continue;
            }
            $flds[] = $fld;
            $values[] = $val;
        }
        if ($implode){
            $flds = implode(',',$flds);
            $values = implode(',', $values);
        }
        return array($flds, $values);
    }



    function get_assignment($updates)
    {
        #debug_print_once('Updates = ' . print_r($updates,1));
        db::sql_str_($updates);
        $assign = array();
        foreach($updates as $fld=>$val){
            if (!in_array($fld, $this->db_fields)){
                continue;
            }
            $assign[] = "$fld=$val";
        }
        #debug_print_once("Fini = " . implode(',',$assign));
        return implode(',', $assign);
    }



    function valide($post_vars, $flds = null)
    {


        #debug_print_once("///////////////////////////////");
        #debug_print_once(print_r($post_vars,1));
        #debug_print_once("flds = $flds");
        foreach($post_vars as $f=>$v){
            #debug_print_once("$f = " . gettype($v));
        }
        if (is_null($flds)){
            self::erreur('manque params à valider');
        }

        if (is_string($flds)){
            $champs_a_valider = explode(',', $flds);
        } else {
            $champs_a_valider = $flds;
        }
        $valeurs_a_valider = array();
        $regles_validation = array();


        if (!$this->is_read() and !$this->is_create() and !array_key_exists('id', $post_vars)){
            self::erreur('id requis pour update/delete');
        }

        if ($this->is_create() ) {
            if (array_key_exists('id', $post_vars)){
                if ($post_vars['id']){
                    self::erreur('pas de id pour création de dossier');
                }
                unset($post_vars['id']);
            }
        }


        foreach($champs_a_valider as $fld){
            $fld = trim($fld);
            if (!array_key_exists($fld, $this->valid)){
                continue;
            }
            $regle = $this->valid[$fld];
            # vérifier valeurs manquantes mais obligatoires; si trouvée, alors retenir nom du champ et NE PAS VALIDER
            if (!array_key_exists($fld, $post_vars)){
                if (!preg_match('#;opt\b#i', $regle)){
                    debug_print_once("Champ ********************************* $fld manquant pour règle $regle");
                    $this->champs_obligatoires_manquants[] = $fld;
                } else {
                    debug_print_once("champ ne manque pas: $fld");
                }
                continue;
            }
            if ($this->auto_conv_empty_string_to_null){
                if (strstr($regle, ';empty_string_to_null') === false){
                    $regle .= ';empty_string_to_null';
                    if (trim($post_vars[$fld]) == ''){
                        $this->champs_convertis_null[] = $fld;
                        #debug_print_once("champ $fld sera nul puisque string vide");
                    }
                }
            }
            $regles_validation[] = "$fld;$regle";
            if (array_key_exists($fld, $post_vars)){
                $valeurs_a_valider[$fld] = $post_vars[$fld];
                #debug_print_once("champ $fld sera validé avec $regle");
            } else {
                #debug_print_once("champ $fld ne sera pas validé puisque pas de règle de validation");
            }
        }
        #debug_print_once("valeurs = " . print_r($valeurs_a_valider,1));
        #debug_print_once("regles = " . print_r($regles_validation,1));
        http_json::set_source_check_params_once($valeurs_a_valider);
        //debug_print_once('postvars = ' . print_r($post_vars,1));
        $vals = self::check_params($regles_validation);
        #debug_print_once("retour check params " . print_r($vals,1));
        if (!$this->is_read()){

            $id = (array_key_exists('id', $vals) ? $vals['id'] : null);
            #debug_print_once("verif unique avec id = $id");
            if (isset($this->unique_flds_or_expr)){
                if (is_array($this->unique_flds_or_expr)){
                    foreach($this->unique_flds_or_expr as $fld){
                        $this->verif_unique($vals,$fld, $id);
                    }
                } else {
                    $this->verif_unique($vals, $this->unique_flds_or_expr, $id);
                }

            }
        }
        foreach($this->refuse_null as $fld=>$val){
            if (array_key_exists($fld, $vals) and is_null($vals[$fld])){
                $vals[$fld] = $val;
            }
        }
        #debug_print_once("RETOUR DE VALIDATION PARENT = " . print_r($vals,1));
        return $vals;
    }

    /**
     * appeler après __construct qui retient les noms de champs manquants
     *
     */
    function valide_champs_manquants()
    {

    }



    function no_nulls($vals)
    {
        $to_ret = array();
        foreach($vals as $key=>$val){
            if (!is_null($val)){
                $to_ret[$key] = $val;
            }
        }
        return $to_ret;
    }



    function array_keys_exist($liste_keys, $array)
    {
        if (!is_array($liste_keys)){
            $liste_keys = explode(',', $liste_keys);
        }
        foreach($liste_keys as $key){
            if (!array_key_exists($key, $array)){
                return $key;
            }
        }
        return true;
    }
    function array_at_least_one_keys_exist($liste_keys, $array)
    {
        if (!is_array($liste_keys)){
            $liste_keys = explode(',', $liste_keys);
        }
        foreach($liste_keys as $key){
            if (array_key_exists($key, $array)){
                return true;
            }
        }
        return false;
    }
    function signaler_champs_manquants($liste_keys, $array = null)
    {
        if (is_null($array)){
            $array = $this->all_vals;
        }
        if (is_string($liste_keys)){
            $liste_keys = explode(',', $liste_keys);
        }

        $manquent = $this->array_keys_exist($liste_keys, $array);
        if ($manquent !== true){
            self::erreur(sprintf(self::msg('au moins un champ oblig manque'), $manquent));
        }

    }



    /**
     *
     * @param array $vals
     * @param string ou array $flds
     *  note: si string: on vérifie que $vals[elem] est une valeur unique du champ elem
     * où elem est la string elle-même ou un élément de la string décomposée en array
     *
     *
     * @param unsigned ou null $id
     *reçoit les valeurs à insérer ou mettre à jour
     *vérifie que les valeurs sont uniques pour champs $flds
     *
     *
     *      */
    function verif_unique($vals, $flds, $id = null)
    {
        /**
         * @var $nb int
         */

        if (!$flds){
            return true;
        }

        if (is_string($flds)){
            $flds = explode(',', $flds);
        }

        if (is_null($id)){
            $cond_id = 1;
        } else {
            $cond_id = "$this->id_fld <> $id";
        }

        $manquent_dans_vals = array(); # champs à tester non présents dans $vals
        $vals_a_tester = array();

        foreach ($flds as $fld){

            $fld = trim($fld);

            if (!array_key_exists($fld, $vals)){
                $manquent_dans_vals[] = $fld;
            } else {
                $vals_a_tester[$fld] = $vals[$fld];
            }
        }

        # si aucune valeur à tester n'est présente dans $vals, aucun risque de créer un doublon!
        if (count($vals_a_tester) == 0){
            return;
        }

        # si des valeurs ne sont pas fournies, ce doit être pour un id existant.
        if (count($manquent_dans_vals)){

            if (is_null($id)){
                self::erreur(printf(self::msg('au moins un champ oblig manque'), implode(',', $flds)));
            }
            # trouver les valeurs manquantes pour le id fourni
            $liste_champs = implode(',', $manquent_dans_vals);
            $res = db::query("
                SELECT $liste_champs
                FROM $this->table
                WHERE $this->id_fld = $id
            ");
            if (!$res){
                self::erreur('acces_table');
            }
            if ($res->num_rows == 0){
                self::erreur('introuvable');
            }
            $vals_a_tester = array_merge($vals_a_tester, $res->fetch_assoc());

            # maintenant $vals_a_tester contient tout ce qu'il faut tester
        }

        $sql_test_statement = array();
        foreach($vals_a_tester as $fld => $val){
            $sql_test_statement[] = "$fld = " . db::sql_str($val);
        }
        $sql_test_statement = implode(' AND ', $sql_test_statement);

        $res = db::dquery("
            SELECT COUNT(*) nb
            FROM $this->table
            WHERE ($sql_test_statement) AND $cond_id
		");
        if (!$res){
            self::erreur('acces_table');
        }        extract($res->fetch_assoc());

        if ($nb){

            self::erreur(sprintf(self::msg('doublon_champ'), implode(', ', $flds)));
        }

    }

    function record_id_to_client($id)
    {


        $res = db::query("
            $this->std_query
            WHERE ($this->std_filter_clause)  AND ( $this->table_fld_qualifier$this->id_fld = $id )
            $this->std_group_clause
            $this->std_order_clause
        ");
        if (!$res){
            self::erreur('acces_table');
        }

        $data = ($res->num_rows ? $res->fetch_assoc() : null);
        $this->process_data_for_client($data);
        self::$data['data'] = $data;
        $this->succes();
    }

    function process_data_for_client(&$data, $is_liste = false) {
        if (!$data) {
            return $data;
        }
        debug_print_once('bool: ' . implode(', ', $this->boolean_flds));
        if ($this->boolean_flds) {
            if ($is_liste) {
                A::setBooleanEach($data, $this->boolean_flds);
            } else {
                A::setBoolean($data, $this->boolean_flds);
            }
        }

        if ($this->int_flds) {
            if ($is_liste) {
                A::setIntEach($data, $this->int_flds);
            } else {
                A::setInt($data, $this->int_flds);
            }
        }
        return $data;
    }

    function verifier_champs_oblig_manquants($liste = null)
    {
        if (is_null($liste)){
            $liste = $this->champs_obligatoires_manquants;
        }
        if (count($liste) == 0){
            return;
        }
//        debug_print_once('validation champs manquants échec ' . implode(',', $liste));
        $liste = implode(', ', $liste);
        self::erreur(sprintf(self::msg('au moins un champ oblig manque'), $liste));
    }


    function fn_crud()
    {

        $this->validate_access();

        $vals = $this->db_vals;

        #debug_print_once("vals = " . print_r($vals,1));



        if ($this->is_read()){

            $this->read();


        } else if ($this->is_update()){

            $this->verifier_champs_oblig_manquants();
            $this->update();

        } else if ($this->is_patch())   {

            $this->update();

        } else if ($this->is_delete()){

            $this->delete();

        } else if ($this->is_create()){

            $this->verifier_champs_oblig_manquants();
            $this->create();

        }

        self::erreur('humm..');
    }


    function create($vals = null)
    {
        if (is_null($vals)){
            $vals = $this->db_vals;
        }

        $vals = $this->transform($vals);

        $assign = $this->get_assignment($vals);

        $res = db::query("
            INSERT INTO $this->table
            SET $assign
        ");
        if (!$res){
            self::erreur('acces_table');
        }
        $insert_id = db::get('insert_id');

        $this->do_if_updated($insert_id);

        $this->process_new_id($insert_id, $vals);

        $this->record_id_to_client($insert_id);

    }
    function process_new_id($insert_id, $vals){
        return;
    }
    function read($id = null)
    {
        // debug_print_once("============================== read");
        if (is_null($id)){
            $id = $this->id;
        }



        if ($id){
            $cond = "$this->table_fld_qualifier$this->id_fld= $id";
        } else {
            $cond = 1;
        }

        if ($this->include_nulls){
            $having_nulls = '';
        } else {
            $having_nulls = "HAVING $this->id_fld IS NOT NULL";
        }
        # $t = microtime(true);
        $res = db::query("
            $this->std_query
            WHERE ($cond)


            AND ($this->std_filter_clause)
            $this->std_group_clause
            $having_nulls
            $this->std_order_clause

        ");
        # debug_print_once('temps = ' . (microtime(true) - $t) . ' ms');
        # $t = microtime(true);
        if (!$res){
            self::erreur('acces_table');
        }

        // si id spécifié, c'est une requête du model
        if ($id){
            $this->record_id_to_client($id);
        }

        // sinon c'est une requête de collection

        $liste = db::result_array($res);
        self::$data['liste'] = $this->process_data_for_client($liste, true);
        # debug_print_once('temps traitement= ' . (microtime(true) - $t) . ' ms');
        $this->succes();

    }

    function delete($id = null)
    {
        if (is_null($id)){
            $id = $this->id;
        }
        $this->get_previous_vals($id);

        if (!is_numeric($id)){
            self::erreur('echec');
        }
        $res = db::query("
            DELETE FROM $this->table
            WHERE $this->id_fld = $id
            LIMIT 1

        ");
        if (!$res){
            self::erreur('acces_table');
        }
        $this->do_if_updated();
        self::$data['data'] = array('id', $id);
        //sleep(3);
        //db::rollback();

        $this->succes();

    }
    function update($id = null, $vals = null)

    {

        if (is_null($vals)){
            $vals = $this->db_vals;
        } else {
            $vals = $this->select($vals, $this->db_fields); #par sécurité, éliminer champs non db
        }
        #debug_print_once("aaaaaaaaaaaaaaa" . print_r($vals,1));

        $vals = $this->transform($vals);

        if (is_null($id)){
            $id = $this->id;
        }

        if (count($vals) == 0){
            self::erreur('aucune donnee fournie pour maj');
        }

        $this->get_previous_vals($id);

        $assign = $this->get_assignment($vals);

        $res = db::dquery("
            UPDATE $this->table
            SET $assign
            WHERE $this->id_fld = $id
        ");
        if (!$res){
            self::erreur('acces_table');
        }
        $this->do_if_updated();
       //sleep(3);
       //self::erreur();
       $this->record_id_to_client($id) ;

    }
    function do_if_updated($id = null)
    {

    }

    function transform($vals = null){
        return $vals;
    }

    function null_to_empty_str_if_exists(&$array, $ind)
    {
        if (is_string($ind)){
            $ind = explode(',', $ind);
        }
        foreach($ind as $i){
            if (!array_key_exists($i, $array)){
                continue;
            }
            if (is_null($array[$i])){
                $array[$i] = '';
            }
        }
    }
    function get_previous_vals($id = null)
    {
        if ($this->previous_vals){
            return $this->previous_vals;
        }
        if (is_null($id)){
            $id = $this->id;
        }
//        $res = db::query("
//            SELECT *
//            FROM $this->table
//            WHERE $this->id_fld = $id
//		", 			'acces_table', '');
        $res = db::query("
            $this->std_query
            WHERE $this->table_fld_qualifier$this->id_fld = $id
		") or $this->erreur('acces_table');
        return $this->previous_vals = $res->fetch_assoc();
    }

}
?>
