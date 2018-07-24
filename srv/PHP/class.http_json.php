<?php
//class fin_return extends Exception{
//    function __construct($msg){
//        parent::__construct($msg);
//    }
//}
use Underscore\Types\Arrays;

class http_json{
    static $data = array();
    static $msg = array(); // messages traduits
    static $default_msgs = ''; // fname de messages par défaut
    static $result_msg;
    static $result_msg2;
    static $result = 0;
    static $op;
    static $val;
    static $msg_loaded = array();
    static $do_not_die = false; // = true dans contexte de test unitaire pour permettre aux tests de continuer
    static $op_force_seulement = false;
    static $testing = false;
    static $no_test =0;
    static $fin_active = false;
    static $source_check_params;
    static $original_request = [];


    function __construct(){
        self::activate_fin();
        self::$op = self::get_param('op');
        self::$original_request = $_REQUEST;
        if (isset($_REQUEST['data'])){
            $val = $_REQUEST['data'];
            if (is_string($val)){
                $val = json_decode($val, true);
            }
            $_REQUEST = $val;
        }
       //debug_print_once('new req ' . print_r($_REQUEST,1));
    }

    static function defined($tag){
        if (!self::$data){
            return false;
        }
        $multi = (strpos('.', $tag) !== false);
        $key_exists = array_key_exists($tag, self::$data);
        if ($key_exists){
            return true;
        }
        if (!$multi){
            return false;
        }
        return !is_null(Arrays::from(self::$data).get($tag));


    }

    function check_login(){
        if (!login_visiteur::logged_in()){
            return self::conditional_fin('non_autorise');
        }
        return null;
    }
    static function activate_fin(){
        db::autocommit(false);
        self::$fin_active = true;
    }

    /**
     * @param string $msg
     * @param string $msg2
     * @return bool|null
     */
    static function conditional_fin($msg = '', $msg2 = ''){
        if (!self::$fin_active){
            db::rollback();
            self::$result_msg = $msg;
            self::$result_msg2 = $msg2;
            event_log::commit();
            return false;
        }
        self::fin($msg, $msg2);
        return null;
    }
    /*
     * régler les messages traduits
     */
    static function set_default_msgs($fname){
        self::$default_msgs = $fname;
    }
    static function set_msg($fname, $obj = null){
        if (array_key_exists($fname, self::$msg)) return self::$msg[$fname];
        return self::$msg[$fname] = new msg($fname, $obj);
    }
    /*
     * obtenir la valeur d'un paramètre de la requête
     */
    static function get_param($id){
        if (!isset($_REQUEST[$id])) return null;
        return $_REQUEST[$id];
    }
    static function trim_all_data(){
        foreach($_POST as &$val){
            if (is_string($val))
                $val = trim($val);
        }
        unset($val);
        foreach($_GET as &$val){
            if (is_string($val))
                $val = trim($val);
        }
        if (is_string(self::$op)){
            self::$op = trim(self::$op);
        }
        foreach($_REQUEST as &$val){
            if (is_string($val))
                $val = trim($val);
        }
    }
    /*
     * obtenir un message dans la langue courante
     */
    static function msg($msg = null, $lang_file = '', $obj = null){
        if ($lang_file == ''){
            $lang_file = self::$default_msgs;
        } else {
            if (!self::$default_msgs){
                self::$default_msgs = $lang_file;
            }
        }
        if (!$lang_file){
            return $msg;
        }

        $msg_obj =  self::set_msg($lang_file, $obj);
        return $msg_obj->get($msg);
    }
    /*
     * régler le message à envoyer dans la réponse
     */
    static function set_result_msg($msg){
        self::$result_msg = $msg;
    }
    static function set_result_msg2($msg){
        self::$result_msg2 = $msg;
    }
    static function rien_a_faire(){
        self::fin('rien_a_faire');
    }
    static function fin($msg = '', $msg2 = ''){
        //debug_print_once(db::format_trace());
        db::rollback();
        self::$result = 0;
        self::finir($msg, $msg2);

    }
    static function non_autorise() {
        self::fin('non_autorise');
    }
    static function succes($msg = '', $msg2 = ''){
        if (!self::$testing){
            db::commit();
        }
        self::$result = 1;
        if (unitTesting::$testing){
            return unitTesting::succes($msg);
        }
        self::finir($msg, $msg2);
        return null;
    }
    static function finir($msg, $msg2){
        global $is_local;
        if (isset($msg)){
            self::set_result_msg($msg);
        }
        if (isset($msg2)){
            self::set_result_msg2($msg2);
            }
        if (self::$result_msg){
            self::$data['msg'] = self::msg(self::$result_msg);
        }
        if (self::$result_msg2){
            self::$data['ref'] = self::$result_msg2;
        }

        self::$data['result'] = self::$result;

        if (unitTesting::$testing){
            return unitTesting::echec($msg);
        }

        if (self::$do_not_die){
            throw new fin_return("fini avec msg = $msg ($msg2)");
        }

        header('Content-Type:application/json; charset=UTF-8');
        if ($is_local){
//            debug_print_once('LOCAL REQUEST');
//            header('Access-Control-Allow-Origin: http://mr.localhost');
//            header('Access-Control-Allow-Origin: http://bbb.localhost:8080');
//            header('Access-Control-Allow-Origin: *');
//            header('Access-Control-Allow-Credentials: true');
//            header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE');
        } else {
//            debug_print_once('request pas local');
        }
        event_log::commit();

//        debug_print_once(substr(self::$data['preview'], 0, 200));
//        debug_print_once(utf8_encode(substr(self::$data['preview'], 0, 200)));
        $json = json_encode(self::$data);
        if ($json === false) {
            die('{"result":0, "msg":"Erreur d\'encodage de la réponse du serveur"}');
        }

//        debug_print_once('json' . $json);
//        debug_print_once(gettype($json));
//        debug_print_once($json ? 'true' : 'false');
        die($json);

    }

    static function set_data($key, $val){
        if (strpos($key, '.') === false){
            self::$data[$key] = $val;
        } else {
            self::$data = Arrays::from(self::$data)->set($key,$val)->obtain();
        }

    }
    static function get_data($key = ''){
        if ($key === ''){
            return self::$data;
        }
        if (!array_key_exists($key, self::$data)){
            //echo "$key existe pas dans ";
            //print_r(self::$data);
            return null;
        }
        return self::$data[$key];
    }
    static function clear_data() {
        self::$data = array();
    }
    static function test_set($param){
        if (isset($_REQUEST[$param])){
            self::$val = $_REQUEST[$param];
            return true;
        } else{
            self::$val = '';
            return false;
        }
    }
    static function test_email($param, $optionnel = false){
        if (!self::test_set($param)){ // attention : ne pas changer ordre
            return $optionnel;
        }
        return filter_var(self::$val, FILTER_VALIDATE_EMAIL);
    }
    static function test_string($param, $min_len = 0, $max_len = -1){
        return self::test_set($param) and is_string(self::$val) and strlen(self::$val) >= $min_len and ($max_len > 0?(strlen(self::$val) <= $max_len):true);
    }
    static function test_unsigned($param, $min = '', $max = ''){
        $options['options']['min_range'] = (is_numeric($min)?$min:0);

        if (is_numeric($max)){
            $options['options']['max_range'] = $max;
        }
        return self::test_set($param) and filter_var(self::$val, FILTER_VALIDATE_INT, $options);
    }
    static function test_int($param, $min = '', $max = ''){
        $options = array();
        if (is_numeric($min)){
            $options['options']['min_range'] = $min;
        }
        if (is_numeric($max)){
            $options['options']['max_range'] = $max;
        }
        return self::test_set($param) and filter_var(self::$val, FILTER_VALIDATE_INT, $options);
    }
    static function test_regex($param, $regex){
        return self::test_set($param) and preg_match($regex, self::$val);
    }
    static function test_date($param){
        if (!self::test_set($param) or !preg_match('#^\d{4}-\d\d?-\d\d?$#', self::$val)){
            return false;
        }
        return self::date_is_valid(self::$val);
    }
    static function test_array($param, $min_count = 0){
        return self::test_set($param) and is_array(self::$val) and count(self::$val) >= $min_count;
    }

    function log_op(){
        if (!self::$op){
            return;
        }
        event_log::add('execute_fn', self::$op . '---', print_r($_REQUEST,1));
    }
    function execute_op($force = false){

        // noter qu'avec http_json en mode test, op_force_seulement est TRUE
        if (self::$op_force_seulement and !$force) {
            return;
        }
        try{

            if (!self::$op){
                self::rien_a_faire();
            }
            $fn = 'fn_' . self::$op;
            if (!method_exists($this, $fn)){
                event_log::add('http_json', 'execute_fn', "fonction introuvable: $fn");
                self::fin('fonction inconnue', self::$op );
            }
            $this->$fn();
        }
        catch(fin_return $err){
            return;
        }
        catch(Exception $err){
            self::fin($err->getMessage());
        }
    }
    static function testing(){
        self::$do_not_die = true;
        self::$op_force_seulement = true;
        self::$testing = true;
        session::set('choix_db', 0);
        $_SERVER['HTTP_HOST'] = 'localhost';
    }

    static function set_source_check_params_once($vals){
        self::$source_check_params = $vals;
    }

    function get_values(){
        if(!is_null(self::$source_check_params)){
            $to_ret = self::$source_check_params;
            self::$source_check_params = null;
            return $to_ret;
        }
        $values = $_REQUEST;

        return $values;
    }
    function check_params(){
        $ref = '';
        // selon obtenir les valeurs à tester, normalement $_REQUEST]
        $values = self::get_values();
        $nb_args = func_num_args();
        $array_source = false;
        if ($nb_args == 1){
            $arg = func_get_arg(0);
            if (is_array($arg)){
                $nb_args = count($arg);
                $array_arguments = $arg;
                $array_source = true;
            }
        }
        $to_ret = array();
        $echec = 0;

        for ($i = 0; $i < $nb_args; $i++){
            if ($array_source){
                $arg = $array_arguments[$i];
            } else {
                $arg = func_get_arg($i);
            }

            if (preg_match('#^[^a-z_].*#i', $arg)){
                $separ = substr($arg, 0, 1);
                $arg = substr($arg,1);
            } else{
                $separ = ';';
            }

            $spec = explode($separ, $arg);
            foreach($spec as &$val){
                $val = trim($val);
            }
            unset($val);

            $param = array_shift($spec);
            $param_name = $varname = $param;
            unset($default_val);

            $sql = $non_vide = $json_encode = $opt = $force_accept_default = $default_null = $accept_null =
                $accept_empty_string = $decode_array = $explode = $empty_string_to_null = $trim = $sql_equal =
                $null_to_empty_string = $decode_object = $bool_to_num = $conv = $sha1 = $to_upper = $to_lower = false;
            $sql_equal_name = '';
            $min = $max = null;
            $type = array_shift($spec);
            $type = preg_replace('#\bregexp#', 'regex', $type);
            //echo "\n(type = $type) ";
            if (preg_match("#^ *((array_)?regex):(.*)$#", $type, $val)){
                    $regex = $val[3];
                    $type = $val[1];
                    //echo "\ntype = regex $regex  avec separateur $separ\n";
                }
            $original_val_varname = false;
//            debug_print_once("type = $type; opts = " . print_r($spec, true));
            foreach($spec as $item){
//                debug_print_once('item = ' . $item);
                if (preg_match('#^ *min: *(\d+) *$#', $item, $val)){
                    $min = $val[1];
                } else if (preg_match('#^ *max: *(\d+) *$#', $item, $val)){
                    $max = $val[1];
                } else if ($item == 'non_vide'){
                    $non_vide = true;
                } else if ($item == 'bool_to_num'){
                    $bool_to_num = true;
                } else if ($item == 'sql'){
                    $sql = true;
                } else if ($item == 'sql_equal'){
                    $sql_equal = true;
                } else if (preg_match('#^sql_equal: *([0-9a-xA-Z]+) *$#',$item,$val)){
                    $sql_equal = true;
                    $sql_equal_name = $val[1];
                }  else if ($item == 'json_encode'){
                    $json_encode = true;
                }  else if ($item == 'decode_object'){
                    $decode_object = true;
                }else if ($item == 'decode_array'){
                    $decode_array = true;
                } else if ($item == 'opt'){
                    $opt = true;
                } else if ($item == 'conv'){
                    $conv = true;
                } else if ($item == 'accept_null'){
                    $accept_null = true;

                } else if ($item == 'explode'){
                    $explode = true;
                } else if ($item == 'empty_string_to_null'){
                    $empty_string_to_null = true;
                    $accept_null = true;
                } else if ($item == 'accept_empty_string'){
                    debug_print_once('accept empty');
                    $accept_empty_string = true;
                } else if ($item == 'force_accept_default'){
                    $force_accept_default = true;
                } else if (preg_match('#^ *rename: *([a-z_0-9]+) *$#i', $item, $val)){
                    $varname = $val[1];
                } else if (preg_match('#^original: *([a-z_0-9]+) *$#', $item, $val)){
                    $original_val_varname = $val[1];
                } else if (preg_match("#^defaul?t: *([^$separ]+) *$#", $item, $val)){
                    $default_val = $val[1];
                } else if (preg_match('#^ *default_empty_string *$#', $item, $val)){
                    $default_val = '';
                    $force_accept_default = true;
                } else if ($item == 'trim') {
                    $trim = true;
                } else if ($item == 'null_to_empty_string'){
                    $null_to_empty_string = true;
                } else if (preg_match('#^ *defaul?t_empty *$#', $item, $val)){
                    switch($type){
                        case 'string':
                        case 'courriel':
                        case 'tel':
                        case 'date':
                        case 'md5':
                        case 'base64':

                            $default_val = '';
                            $force_accept_default = true;
                            break;
                        case 'int':
                        case 'unsigned':
                        case 'num':
                        case 'bool':
                        case 'boolean':
                            $default_val = 0;
                            $force_accept_default = true;
                            break;
                        case 'array':
                        case 'array_num':
                        case 'array_int':
                        case 'array_unsigned':
                        case 'array_date':
                        case 'array_div':
                        case 'array_cl':
                        case 'array_courriel':
                            $default_val = array();
                            $force_accept_default = true;
                            break;
                        case 'json':
                            $default_val = '{}';
                            break;

                    }

                } else if (preg_match('#^ *defaul?t_null *$#', $item, $val)) {
                    $default_val = null;
                    $force_accept_default = true;
                    $default_null = true;
                } else if ($item == 'to_upper') {
                    $to_upper = true;
                } else if ($item == 'to_lower') {
                    $to_lower = true;
                }
            }

            $param_fourni = array_key_exists($param, $values);
            $default = false;
            //debug_print_once("paramètre $param " . ($param_fourni?' fourni':' NON fourni'));
            if (!$param_fourni){
                if (isset($default_val) or $default_null){
                    $val = $default_val;
                    $default = true;
                } else if ($opt){
                    //debug_print_once("on continue");
                    continue;
                } else{
                    if (unitTesting::$testing){
                        throw new testEchecValidation($param);
                    }
                    self::fin('parametre_manquant', "non spécifié: $param");
                }
            } else{
                $val = $values[$param];
                if ($type == 'unsigned' and $val === '' and isset($default_val)){
                    $val = $default_val;
                }

                if (isset($default_val) and $val == $default_val){
                    $default = true;
                }
                if (is_null($val) and $null_to_empty_string){
                    $val = '';
                }
                if (is_string($val)) {
                    if ($to_upper) {
                        $val = strtoupper($val);
                    } else if ($to_lower) {
                        $val = strtolower($val);
                    }

                }
            }
            $original_val = $val;
            //debug_print_once("val = $val" . ' ' . (is_null($val)?'est nul':' pas nul'));
            if ($accept_null and ($val === 'null' or is_null($val))){
                $val = null;
            }
            if ($empty_string_to_null and $val === ''){
                $val = null;
            }
            if (!is_null($val) and $trim){
                $val = trim($val);
            }
            // pour tenir compte du fait qu'un objet vide est transmis comme une chaîne de caractères vide
            // au lieu d'une array
            if (preg_match('#^array.*$#', $type) and $val === ''){
                $val = array();
            }

        //    echo "\nVérifie param $param = $val; type = $type\n";

            if ($non_vide){
                if (self::size($val) == 0){
                    self::fin('parametre_manquant', "valeur vide: $param") ;
                }
            }

            $err_msg = false;
            //debug_print_once("param = $param = $val isnull? " . (is_null($val)?'oui':'non'). '; accept null? ' . ($accept_null?'OUI':'NON'));
            if (!( // lister les conditions qui forcent l'acceptation de la valeur fournie
                    $default and $force_accept_default
                    or
                    is_null($val) and $accept_null
                    or
                    $val === '' and $accept_empty_string
                    )){
                switch($type){
                    case 'base64':
                        if (!preg_match('#^([0-9a-zA-Z+/]+={0,3})?$#', $val) or strlen($val)%4 ){
                            $err_msg = 'codage_invalide';
                            break;
                        }
                        break;
                    case 'array_saisons':
                        foreach($val as $saison){
                            if (!saisons::existe($saison)){
                                $err_msg = 'saison_inconnue';
                                break;
                            }
                        }
                        break;
                    case 'saison':
                        if (!saisons::existe($val)){
                            $err_msg = 'saison_inconnue';
                        }
                        break;
                    case 'array_div':
                        foreach($val as $div){
                            if (!gestion_divisions::is_valid_id($div)){
                                $err_msg = 'division_invalide';
                                break;
                            }
                        }
                        break;
                    case 'div':
                        if (!gestion_divisions::is_valid_id($val)){
                            $err_msg = 'division_invalide';
                        }
                        break;
                    case 'array_cl':
                        foreach($val as $cl){
                            if (!gestion_classes::is_valid_id($cl)){
                                $err_msg = 'classe_invalide';
                                break;
                            }
                        }
                        break;
                    case 'cl':
                        if (!gestion_classes::is_valid_id($val)){
                        $err_msg = 'classe_invalide';

                        }
                        break;
                    case 'eq_std':
                        if (!gestion_equipes_std::is_valid_id($val)){
                        $err_msg = 'equipe_invalide';
                        }
                        break;
                    case 'widget':
                        if (!in_array($val, widget::$widget_types)){
                            $err_msg = 'pas_un_widget';
                        }
                        break;
                    case 'json':
                        $v = json_decode($val,true);
                        if (is_null($v)){
                            $err_msg = 'pas_json';
                        } else{
                            if ($decode_array){
                                $val = $v;
                            } else if ($decode_object){
                                $val = json_decode($val);
                            }
                        }
                        break;
                    case 'string':
                        if (!is_string($val)){
                            $err_msg = 'pas_string';
                            break;
                        }
                        $err_msg = self::check_size($val, $min, $max);
                        break;
                    case 'array':
                        if (!is_array($val)){
                            $err_msg = 'pas_array';
                            break;
                        }
                        $err_msg = self::check_size($val, $min, $max);
                        break;
                    case 'array_num':
                        if (!is_array($val)){
                            $echec = true;
                            break;
                        }
                        if ($err_msg = self::check_size($val, $min, $max)){
                            break;
                        }
                        foreach($val as $item){
                            if (!is_numeric($item)){
                                $err_msg = 'pas_numerique';
                                break;
                            }
                        }
                        break;
                    case 'array_int':
                        if (!is_array($val)){
                            $echec = true;
                            break;
                        }
                        if ($err_msg = self::check_size($val, $min, $max)){
                            break;
                        }
                        foreach($val as $item){
                            if (!filter_var($item, FILTER_VALIDATE_INT)){
                                $ref = "val = $item";
                                $err_msg = 'pas_int';
                                break;
                            }
                        }
                        break;
                    case 'array_unsigned':
                        if (!is_array($val)){
                            $err_msg = 'pas_array';
                            break;
                        }
                        if ($err_msg = self::check_size($val, $min, $max)){
                            break;
                        }
                        foreach($val as $item){
                            if ($item != 0 and !filter_var($item, FILTER_VALIDATE_INT, array('options'=>array('min_range'=>0)))){
                                $ref = "val = $item";
                                $err_msg = 'pas_unsigned';

                                break;
                            }
                        }
                        break;
                    case 'regex':
                        if (!preg_match($regex, $val)){
                            $err_msg = 'non_conforme';
                            break;
                        }
                        break;
                    case 'array_regex':
                        if (!isset($regex)){
                            $err_msg = 'manque_regex';
                            break;
                        }
                        if ($err_msg = self::check_size($val, $min, $max)){
                            break;
                        }
                        if (!is_array($val)){
                            $err_msg = 'pas_array';
                            break;
                        }
                        foreach($val as $item){
                            if (!preg_match($regex, $item)){
                                $err_msg = 'non_conforme';
                                break;
                            }
                        }

                        break;
                    case 'num':
                        if (!is_numeric($val)){
                            $err_msg = 'pas_numerique';
                        }
                        break;
                    case 'int':
                    case 'unsigned':

                        if ($type == 'int'){
                            $sign = '-?';
                        } else {
                            $sign = '';
                        }

                        if (!preg_match("#^$sign\d+$#", $val)){
                            $err_msg = "pas_$type";
                            break;
                        }
                        if (!is_null($min)){
                            if ($val < $min){
                                $err_msg = 'non_conforme';
                                $ref = "$val < $min";
                                break;
                            }
                        }
                        if (!is_null($max)){
                            if ($val > $max){
                                $err_msg = 'non_conforme';
                                $ref = "$val > $max";
                                break;
                            }
                        }
                        break;
//
//                    case 'unsigned':
//                        $options = array();
//
//                        if (!preg_match('#^\d+$#', $val)){
//                            $err_msg = 'pas_unsigned';
//                            break;
//                        }
//                        if (!is_null($min)){
//                            if ($val < $min){
//
//                            }
//                        }
//
//                        //debug_print_once("testing unsigned $param");
//                        if (is_null($min)){
//                            $options['options']['min_range'] = 0;
//                        } else {
//                            $options['options']['min_range'] = $min;
//                        }
//
//                        if (!is_null($max)){
//                            $options['options']['max_range'] = $max;
//                        }
//
//                        if (filter_var($val, FILTER_VALIDATE_INT, $options) === false){
//                            $err_msg = 'pas_unsigned';
//                        }
//                        break;
                    case 'courriel':
                        $val = strtolower($val);
                        if (filter_var($val, FILTER_VALIDATE_EMAIL) === false){
                            $err_msg = 'adresse_invalide';
                        }
                        break;
                    case 'array_courriel':
                        if (!is_array($val)){
                            $err_msg = 'pas_array';
                            break;
                        }
                        if (!$val) {
                            break;
                        }
                        $invalides = [];
                        foreach($val as &$adr) {
                            $adr = strtolower($adr);
                            if (filter_var($adr, FILTER_VALIDATE_EMAIL) === false){
                                $err_msg = 'adresse_invalide';
                                $invalides[] = $adr;
                            }
                        }
                        if ($invalides) {
                            $this->fin('adresses_invalides', implode(', ', $invalides));
                        }

                        break;
                    case 'bool':
                    case 'boolean':
                        if ($val != '0' and $val != '1' and $val != 'true' and $val != 'false'){
                            $err_msg = 'pas_booleen';
                        }
                        if ($bool_to_num){
                            $val = ($val or $val === 'true')?1:0;
                        }
                        break;
                    case 'unsigned_list':
                        if (!preg_match('#^$|^((^|,)\d+)+$#', $val)){
                            $err_msg = 'pas_une_liste';
                            break;
                        }
                        if (($err_msg = self::check_size($a  = explode(',',$val), $min, $max))){
                            break;
                        }
                        if ($explode){
                            $val = $a;
                        }
                        break;
                    case 'tel':

                        $fmt_val = preg_replace('#[^\d]#', '', $val);
                        if (strlen($fmt_val) < 10){
//                            debug_print_once('no tel trop court? ' . $varname . ' = [' . $val . ']' . gettype($val) . '- accept empty?' . ($accept_empty_string ? 'oui' : 'non') );
                            self::fin('no_tel_trop_court', $val);
                        }
                        $val = '(' . substr($fmt_val, 0, 3) . ') ' . substr($fmt_val, 3, 3) . '-' . substr($fmt_val, 6, 4) . (strlen($fmt_val) > 10?' #' . substr($fmt_val,10):'');
                        break;
                    case 'date':
                        if (!self::date_is_valid($val)){
                            $err_msg = 'mauvaise_date';
                            break;
                        }
                        break;
                    case 'code_postal':

                        $fmt_val = preg_replace('# #', '', strtoupper($val));
                        if (!preg_match('#^[A-Z]\d[A-Z]\d[A-Z]\d$#', $fmt_val)){
                            $err_msg = 'mauvais_code_postal';
                            break;
                        }
                        $fmt_val = substr($fmt_val, 0, 3) . ' ' . substr($fmt_val,3);
                        break;
                    case 'array_date':
                        if (!is_array($val)){
                            $err_msg = 'pas_array';
                            break;
                        }
                        foreach($val as $une_date){
                            if (!self::date_is_valid($une_date)){
                                $err_msg = 'mauvaise_date';
                                $ref = $une_date;
                                break;
                            }
                        }
                        break;

                    case 'datetime':
                        if (!self::verifyDate($val)){
                            $err_msg = 'mauvaise_date';
                        }
                        break;
                    case 'time':
                        if (!preg_match('/^(?:[0-9]|[0-1][0-9]?|2[0-4])(:[0-5][0-9])?$/', $val)){
                            $err_msg = 'mauvaise_heure';
                            break;
                        }
                        if (strpos($val, ':') === false){
                            $val .= ':00';
                        }
                        break;

                    case 'md5':
                        if (!preg_match('#^[a-f0-9]{32}$#', $val)){
                            $err_msg = 'md5_invalide';
                        }
                        break;
                    case 'sha1':
                        if (!preg_match('#^[a-f0-9]{40}$#', $val)){
                            $err_msg = 'sha1_invalide';
                        }
                        break;

                    default:
                        $err_msg = 'type inconnu';
                }
            }

            if ($echec or isset($err_msg) and $err_msg){
                if (unitTesting::$testing){
                    throw new testEchecValidation($param_name);
                }
                $msg_val = $val;
                if (is_array($val)){
                    $msg_val = 'Array: ' . implode(',', $val);
                }
                self::fin((isset($err_msg)?$err_msg:'mauvais_param'), "$param = $msg_val; type = $type - $ref");
            }
            if ($json_encode){
                $val = json_encode($val);
            }
            if ($conv){
                $val = conv($val);
            }
            $to_ret[$varname] = $sql?db::sql_str($val):$val;

            if ($sql_equal){
                $name = $sql_equal_name? $sql_equal_name:$varname;

                if (is_null($val)){
                    $to_ret[$name] = 'IS NULL';
                } else {
                    $to_ret[$name] = '= ' .  db::sql_str($val);
                }
            }



            if (is_string($original_val_varname)){
                $to_ret[$original_val_varname] = $original_val;
            }
        }
        return $to_ret;
    }
    private static function date_is_valid($val){
        $date_components = date_parse($val);
//        if ($date_components['warning_count'] + $date_components['error_count']){
//            debug_print_once(print_r($date_components,1));
//        }
        return $date_components['warning_count'] + $date_components['error_count'] == 0;

    }
    private static function check_size($val, $min, $max){
        if (!is_null($min) and self::size($val) < $min){
            return 'trop_court';
        }
        if (!is_null($max) and self::size($val) > $max){
            return 'trop_long';
        }
        return '';
    }
    static function size($val){
        if (is_null($val)){
            return 0;
        }
        if (is_array($val)){
            return count($val);
        }
        return strlen($val);
    }
    function valider_acces($niveau = 'admin'){
        if (!perm::test($niveau)){
            $this->fin('non_autorise');
        }
    }

    function verif_admin(){
        if (!perm::test('admin')){
            $this->fin('non_autorise');
        }
    }
    function verif_trouve($res, $msg = 'introuvable') {
        if ($res->num_rows == 0) {
            $this->fin($msg);
        }
    }
    static public function verifyDate($date, $strict = true)
    {
        $dateTime = DateTime::createFromFormat('Y-m-d H:i', $date);
        if ($strict) {
            $errors = DateTime::getLastErrors();
            if (!empty($errors['warning_count'])) {
                return false;
            }
        }
        return $dateTime !== false;
    }

    static function implode(&$array)
    {
        $array = implode(',', $array);
    }

    static function result_to($res, $ind = 'liste')
    {

        $val = db::result_array($res);
        self::set_data($ind, $val);
        return $val;
    }

    static function result_to_scalar($res, $fld='id',  $ind = 'liste')
    {

        self::$data[$ind] = db::result_array_one_value($res, $fld);
        return self::$data[$ind];
    }


    /**
     * @param mysqli_result $res
     * @param string $ind
     * @return mixed
     */
    static function result_to_1($res, $ind = 'liste')
    {
        if ($res->num_rows) {
            self::$data[$ind] = $res->fetch_assoc();
        } else {
            self::$data[$ind] = [];
        }

        return self::$data[$ind];
    }

}
