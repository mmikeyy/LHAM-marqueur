<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of class
 *
 * @author michel
 */
class db
{
    /**
     * @var mysqli $mysqli
     * @var mysqli $mysqli_ext
     */
    static $mysqli;
    static $mysqli_ext;
    static $server_override = array();
    static $db_cfg = 'config_db.yml';
    static $hostname;
    static $username;
    static $password;
    static $schema;
    static $schema_ext;
    static $query_last_id = 0;
    static $last_query = '';
    static $last_error = '';
    public $res;
    public $query;
    public $query_id;

    static function reset()
    {
        self::$mysqli = null;
        self::$mysqli_ext = null;
        self::$server_override = array();
        self::$db_cfg = 'config_db.yml';
        self::$hostname = null;
        self::$username = null;
        self::$password = null;
        self::$schema = null;
        self::$schema_ext = null;
        self::$last_query = '';
    }

    static function connect_schema($schema)
    {
        self::$hostname = 'localhost';
        self::$username = 'mmikeyy';
        self::$password = 'JvsJjK24MpB2phwq';
        self::$schema = $schema;
        self::complete_connection();
    }

    static function server($key)
    {
        if (array_key_exists($key, self::$server_override)) {
            return self::$server_override[$key];
        }
        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }
        return null;
    }

    /**
     *
     * @return  null
     *
     * permet de mettre valeurs dans propriété 'server_override', qui contient une liste de propriétés
     * qui auront priorité sur les valeurs correspondantes de $_SERVER
     * surtout utile lors de tests unitaires
     *
     * si un seul paramètre:
     *  -- doit être array;
     *  -- contientra liste de <param> => <val>
     *
     * si deux params
     *  -- premier est une clef
     *  -- second est valeur à assigner
     */
    static function server_override()
    {
        if (func_num_args() == 1) {
            $list = func_get_arg(0);
            if (!is_array($list)) {
                return;
            }
            self::$server_override = $list;
        } else if (func_num_args() == 2) {
            self::$server_override[func_get_arg(0)] = func_get_arg(1);
        }
    }

    static function find_params()
    {

//		$ini = @parse_ini_file($ini_name = __DIR__ . '/../cfg/' . self::$ini_file , true);
        $ini = Spyc::YAMLLoad($ini_name = __DIR__ . '/../cfg/' . self::$db_cfg);
        if ($ini === false) {
            throw new fin_return('ini introuvable ' . $ini_name);
        }

        $choix_db = session::get('choix_db');

        $hostname = self::server('HTTP_HOST');
        if (is_null($hostname)) {
            $hostname = 'localhost';
        }

        foreach ($ini as $location) {

            if ($choix_db != $location['test_choix_db']) {
                //echo "choix_db = $choix_db <> {$location['test_choix_db']}</br/>";
                continue;
            }

            if (!preg_match($location['test_hostname'], $hostname)) {
                //echo "\nhostname = $hostname  ;  loca = {$location['test_hostname']}</br/>\n\n";
                continue;
            }
            self::$hostname = $location['hostname'];
            self::$username = $location['username'];
            self::$password = $location['password'];
            self::$schema = $location['schema'];
            self::$schema_ext = $location['external_schema'];
            return true;
        }
        return false;
    }

    static function complete_connection()
    {
//        debug_print_once('hostname = ' . self::$hostname);
//        debug_print_once('username = ' . self::$username);
//        debug_print_once('password = ' . self::$password);
//        debug_print_once('schema = ' . self::$schema);

        self::$mysqli = new mysqli(self::$hostname, self::$username, self::$password, self::$schema);
        if (!self::$mysqli) {
            throw new fin_return('erreur_ouverture_db');
        }
        //self::autocommit(false);

//		self::$mysqli->query("set names 'utf8'");
        $t = date('Z') / 60;
        $t_diff = sprintf('%s%u:%02u',
            $t >= 0 ? '+' : '-',
            floor(abs($t) / 60),
            abs($t) % 60
        );

        //debug_print($query = "set time_zone = '$t_diff'");
        /**
         * @var string $mode
         **/
        $res = db::query("
                SELECT @@sql_mode as mode
            ", 'acces_table');
        extract($res->fetch_assoc());

        $remove = [
            'ONLY_FULL_GROUP_BY',
            'STRICT_TRANS_TABLES'
        ];

        if ($mode) {
            $mode = implode(',', array_diff(explode(',', $mode), $remove));
        } else {
            $mode = '';
        }

        self::$mysqli->set_charset('utf8');
        self::$mysqli->multi_query($q = "
		    set time_zone = '$t_diff';
		    set @@sql_mode = '$mode'
		    ");

        do {
            if ($res = self::$mysqli->store_result()) {
                $res->free_result();
            }
            if (!self::$mysqli->more_results()) {
                break;
            }
        } while (self::$mysqli->next_result());


        $GLOBALS['mysqli'] = self::$mysqli;
    }
    static function complete_connection_ext()
    {
//        debug_print_once('hostname = ' . self::$hostname);
//        debug_print_once('username = ' . self::$username);
//        debug_print_once('password = ' . self::$password);
//        debug_print_once('schema = ' . self::$schema);

        self::$mysqli_ext = new mysqli(self::$hostname, self::$username, self::$password, self::$schema_ext);
        if (!self::$mysqli_ext) {
            throw new fin_return('erreur_ouverture_db');
        }
        //self::autocommit(false);

//		self::$mysqli->query("set names 'utf8'");
        $t = date('Z') / 60;
        $t_diff = sprintf('%s%u:%02u',
            $t >= 0 ? '+' : '-',
            floor(abs($t) / 60),
            abs($t) % 60
        );

        //debug_print($query = "set time_zone = '$t_diff'");
        /**
         * @var string $mode
         **/
        $res = db::query_ext("
                SELECT @@sql_mode as mode
            ", 'acces_table');
        extract($res->fetch_assoc());

        $remove = [
            'ONLY_FULL_GROUP_BY',
            'STRICT_TRANS_TABLES'
        ];

        if ($mode) {
            $mode = implode(',', array_diff(explode(',', $mode), $remove));
        } else {
            $mode = '';
        }

        self::$mysqli_ext->set_charset('utf8');
        self::$mysqli_ext->multi_query($q = "
		    set time_zone = '$t_diff';
		    set @@sql_mode = '$mode'
		    ");

        do {
            if ($res = self::$mysqli_ext->store_result()) {
                $res->free_result();
            }
            if (!self::$mysqli_ext->more_results()) {
                break;
            }
        } while (self::$mysqli_ext->next_result());


        $GLOBALS['mysqli_ext'] = self::$mysqli_ext;
    }

    static function connect()
    {
        if (self::$mysqli) {
            return true;
        }
        if (!self::find_params()) {
            echo 'echec connexion hostname=' . self::server('HTTP_HOST');
            throw new fin_return('echec_connexion_db');
        }
        self::complete_connection();
        return true;
    }

    static function connect_ext()
    {
        if (self::$mysqli_ext) {
            return true;
        }
        if (!self::find_params()) {
            echo 'echec connexion hostname=' . self::server('HTTP_HOST');
            throw new fin_return('echec_connexion_db');
        }
        self::complete_connection_ext();
        return true;
    }

    static function rollback()
    {
        if (self::connected()) {
            self::$mysqli->rollback();
        }
    }

    // ne surtout pas déclarer méthode statique
    static function dquery($query, $msg_fin = null, $msg_ref = null)
    {
        debug_print_once($query);
        return self::query($query, $msg_fin, $msg_ref);
    }

    static function dquery_ext($query, $msg_fin = null, $msg_ref = null)
    {
        debug_print_once($query);
        return self::query_ext($query, $msg_fin, $msg_ref);
    }

    /**
     * @param $query
     * @param null $msg_fin
     * @param null $msg_ref
     * @return mysqli_result
     * @throws fin_return
     */
    static function query($query, $msg_fin = null, $msg_ref = null)
    {

        self::connect();
        self::$last_query = $query;
        self::$query_last_id++;
//		if (isset($this) and get_class($this) == __CLASS__){
//
//			$this->query_id = self::$query_last_id;
//			$this->query = $query;
//
//			$this->res = self::$mysqli->query($query);
//			self::report_error();
//			if ($msg_fin and !$this->res){
//				self::$last_error = self::$mysqli->error;
//				throw new fin_return($msg_fin, $msg_ref);
//			}
//
//			return $this->res;
//		} else{
//		}
        $res = self::$mysqli->query($query);
        self::report_error();
        if ($msg_fin and !$res) {
            self::$last_error = self::$mysqli->error;

            return http_json::conditional_fin($msg_fin, $msg_ref);

        }
        return $res;

    }
    /**
     * @param $query
     * @param null $msg_fin
     * @param null $msg_ref
     * @return mysqli_result
     * @throws fin_return
     */

    static function query_ext($query, $msg_fin = null, $msg_ref = null)
    {

        self::connect_ext();
        self::$last_query = $query;
        self::$query_last_id++;
//		if (isset($this) and get_class($this) == __CLASS__){
//
//			$this->query_id = self::$query_last_id;
//			$this->query = $query;
//
//			$this->res = self::$mysqli->query($query);
//			self::report_error();
//			if ($msg_fin and !$this->res){
//				self::$last_error = self::$mysqli->error;
//				throw new fin_return($msg_fin, $msg_ref);
//			}
//
//			return $this->res;
//		} else{
//		}
        $res = self::$mysqli_ext->query($query);
        self::report_error_ext();
        if ($msg_fin and !$res) {
            self::$last_error = self::$mysqli_ext->error;

            return http_json::conditional_fin($msg_fin, $msg_ref);

        }
        return $res;

    }



    static function quote_fld($fld) {
        if (is_array($fld)) {
            return array_map(function($val){return self::quote_fld($val);}, $fld);
        }
        return "`$fld`";
    }

    static function report_error()
    {
        self::$last_error = self::$mysqli->error;
        if (!self::$mysqli->errno) {
            return;
        }

        debug_print_once("ERREUR QUERY \n\n" . self::$last_query . "\n\nERREUR = " . self::$last_error . "\nTrace:\n" . self::format_trace());
    }
    static function report_error_ext()
    {
        self::$last_error = self::$mysqli_ext->error;
        if (!self::$mysqli_ext->errno) {
            return;
        }

        debug_print_once("ERREUR QUERY \n\n" . self::$last_query . "\n\nERREUR = " . self::$last_error . "\nTrace:\n" . self::format_trace());
    }

    static function autocommit($mode = true)
    {
        self::connect();
        self::$mysqli->autocommit($mode);
    }

    static function commit()
    {
        self::connect();
        return self::$mysqli->commit();
    }

    /**
     * @param $val
     * @param string $ifnull
     * @return string | array
     */
    static function sql_str($val, $ifnull = 'null')
    {
        if (is_array($val)) {
            foreach ($val as &$item) {
                $item = self::sql_str($item, $ifnull);
            }
            return $val;
        }
        self::connect();
        if (is_null($val)) {
            return $ifnull;
        }
        if (is_numeric($val) and preg_match('#^-?[0-9]+$#', $val)) {
            return "$val";
        }
        return "'" . self::$mysqli->real_escape_string($val) . "'";

    }

    static function sql_str_(&$val, $ifnull = 'null')
    {
        $val = self::sql_str($val, $ifnull);
    }

    static function GetSQLValueString($theValue, $theType, $theDefinedValue = "", $theNotDefinedValue = "")
    {
        self::connect();

        if (is_null($theValue))
            return 'null';
        $theValue = get_magic_quotes_gpc() ? stripslashes($theValue) : $theValue;

        $theValue = self::$mysqli->real_escape_string($theValue);

        switch ($theType) {
            case "text":
                $theValue = ($theValue != "") ? "'" . $theValue . "'" : "''";
                break;
            case "long":
            case "int":
                if ($theValue == 'NULL')
                    break;
                $theValue = ($theValue != "") ? intval($theValue) : "0";
                break;
            case "double":
            case "float" :
                $theValue = ($theValue != "") ? "'" . doubleval($theValue) . "'" : "'0.0'";
                break;
            case "date":
                $theValue = ($theValue != "") ? "'" . $theValue . "'" : "NULL";
                break;
            case "defined":
                $theValue = ($theValue != "") ? $theDefinedValue : $theNotDefinedValue;
                break;
        }
        return $theValue;
    }

    static function connected()
    {
        return is_object(self::$mysqli);
    }

    public function __get($name)
    {
        debug_print_once("Accès à propriété db::$name");
        switch ($name) {
            case 'num_rows':
                return $this->res->num_rows;
            case 'affected_rows':
                return self::$mysqli->affected_rows;
            case 'insert_id':
                return self::$mysqli->insert_id;
        }
        throw new fin_return("paramètre non reconnu: $name");
    }

    static function get_mysqli()
    {
        self::connect();
        return self::$mysqli;
    }

    static function get($what)
    {
        if ($what == 'mysqli') {
            return self::get_mysqli();
        }
        self::connect();
        return '' . self::$mysqli->$what;
    }

    static function format_trace($trace = null)
    {
        $to_ret = '';
        if ($trace == null) {
            $trace = debug_backtrace();
            $premier = true;
        } else {
            $premier = false;
        }
        foreach ($trace as $s) {
            if ($premier) {
                $premier = false;
                continue;
            }
            foreach (array('file', 'line', 'function') as $ind) {
                if (!isset($s[$ind])) {
                    $s[$ind] = '?';
                }
            }
            $to_ret .= "Fichier {$s['file']} LIGNE #{$s['line']}; fnct = {$s['function']}\n";
        }
        return $to_ret;
    }

    static function prep_db()
    {
        db::query("
			create or replace view roles_courants as
			SELECT *
			FROM role_equipe re
			JOIN equipes e USING(id_equipe)
			JOIN saisons s ON s.id = e.id_saison
			WHERE s.statut = 1
		");
    }

    static function lock_statement($level = 0)
    {
        switch ($level) {
            case 1:
            case 'share':
                return 'LOCK IN SHARE MODE';
            case 2:
            case 'update':
                return 'FOR UPDATE';
            default:
                return '';
        }
    }

    static function lock_to_statement(&$level = 0)
    {
        $level = self::lock_statement($level);
    }

    static function err($msg = '')
    {
        return http_json::conditional_fin('acces_table', $msg);
    }

    static function champs_table($table, $without = [])
    {
        $res = db::query("
			SHOW COLUMNS FROM $table
		");
        if (!$res) {
            return http_json::conditional_fin('acces_table', 'show columns');
        }
        $to_ret = array();
        if ($res->num_rows) {
            while ($row = $res->fetch_assoc()) {
                $to_ret[] = $row['Field'];
            }
        }
        if ($without) {
            $to_ret = array_diff($to_ret, $without);
        }
        return $to_ret;

    }

    static function update_statement_components($data, $omit_in_update = [])
    {
        $flds = self::quote_fld(array_keys($data[0]));
        $values = implode('),(', array_map(function ($v) {
            return implode(',', db::sql_str(array_values($v)));
        }, $data));

        $update_statement = implode(', ', array_map(
            function ($v) {
                return "$v = VALUES($v)";
            }
            , array_diff($flds, self::quote_fld($omit_in_update))));

        return [implode(',', $flds), $values, $update_statement];
    }



    static function update_values($flds, $omit = [])
    {
        $flds = array_diff($flds, $omit);
        return implode(', ', array_map(function ($v) {
            return "$v = VALUES($v)";
        },
            $flds));
    }

    /**
     * @param mysqli_result $res
     * @param string $key_fld
     * @return array
     */
    static function result_array($res, $key_fld = '', $remove_key = true)
    {
        if ($res->num_rows == 0) {
            return array();
        }
        $res->data_seek(0);
        $to_ret = [];
        $row = $res->fetch_assoc();
        if ($key_fld and array_key_exists($key_fld, $row)) {
            do {
                $key = $row[$key_fld];
                if ($remove_key) {
                    unset($row[$key_fld]);
                }
                $to_ret[$key] = $row;
            } while ($row = $res->fetch_assoc());
        } else {
            do {
                $to_ret[] = $row;
            } while ($row = $res->fetch_assoc());
        }
        return $to_ret;
    }

    /**
     * @param mysqli_result $res
     * @param  string $key_fld
     * @return array
     */
    static function result_array_group($res, $key_fld)
    {
        if ($res->num_rows == 0) {
            return [];
        }
        $res->data_seek(0);
        $to_ret = [];
        while ($row = $res->fetch_assoc()) {
            $key = $row[$key_fld];
            unset($row[$key_fld]);
            $to_ret[$key][] = $row;
        }
        return $to_ret;
    }

    /**
     * @param mysqli_result $res
     * @param  string $key_fld
     * @return array
     */
    static function result_array_group_pairs($res, $key_fld)
    {
        if ($res->num_rows == 0) {
            return [];
        }
        $res->data_seek(0);
        $to_ret = [];
        while ($row = $res->fetch_assoc()) {
            $key = $row[$key_fld];
            unset($row[$key_fld]);
            $to_ret[$key][] = $row;
        }
        $to_ret2 = [];
        foreach($to_ret as $ind => $vals) {
            $to_ret2[] = [$ind, $vals];
        }
        return $to_ret2;
    }

    static function result_array_values($res, $key_fld = '')
    {
        $rows_array = self::result_array($res, $key_fld);
        foreach ($rows_array as &$val) {
            $val = array_values($val);
        }
        return $rows_array;
    }

    static function result_array_one_value($res, $value_fld, $key_fld = '')
    {
        $rows_array = self::result_array($res, $key_fld);
        foreach ($rows_array as &$val) {
            $val = $val[$value_fld];
        }
        return $rows_array;
    }

    static function result_array_values_one($res, $key_fld = '')
    {
        $rows_array = self::result_array($res, $key_fld);
        foreach ($rows_array as $ind => &$val) {
            $val = array_values($val);
            $val = $val[0];
        }
        return $rows_array;
    }

    static function result_row($res, $key_fld = '')
    {
        $val = self::result_array($res, $key_fld);
        if (count($val)) {
            return $val[0];
        }
        return $val;
    }

    static function make_assignment($array, $omit_keys = [], $as_is = [])
    {
        $to_ret = array();
        if (is_string($omit_keys)) {
            $omit_keys = explode(',', $omit_keys);
        }
        foreach ($array as $fld => $val) {
            if (in_array($fld, $omit_keys)) {
                continue;
            }
            if (in_array($fld, $as_is)) {
                $to_ret[] = "`$fld` = " . $val;
            } else {
                $to_ret[] = "`$fld` = " . db::sql_str($val);
            }

        }
        return implode(', ', $to_ret);
    }

    static function verif_trouve($res)
    {
        if ($res->num_rows == 0) {
            return http_json::conditional_fin('introuvable');
        }
        return null;
    }

    /**
     * @param mysqli_result $res
     * @param string $flds
     * @param string $data
     */
    static function unzip($res, $flds_fld = 'flds', $data_fld = 'data')
    {
        $flds = self::fld_names($res);
        $data = $res->fetch_all();
        return [$flds_fld => $flds, $data_fld => $data];
    }

    /**
     * @param mysqli_result $res
     * @return array
     */
    static function fld_names($res)
    {
        return array_map(function ($f) {
            return $f->name;
        }, $res->fetch_fields());
    }

    /**
     * @param mysqli_result $res
     * @param string $key_fld
     * @param string $value_fld
     * @param null|array $data_flds
     * @return array
     */
    static function select_options($res, $key_fld, $value_fld, $data_flds = null)
    {
        $res->data_seek(0);
        $to_ret = array();
        if (is_string($data_flds)) {
            $data_flds = explode(',', $data_flds);
        } else if (is_null($data_flds)) {
            $data_flds = array();
        }
        if ($res->num_rows) {
            while ($row = $res->fetch_assoc()) {
                $list_data_attr = array();
                foreach ($data_flds as $data_fld) {
                    $list_data_attr[] = "data-$data_fld = \"" . esc_q2($row[$data_fld]) . '"';
                }
                $list_data_attr = implode(' ', $list_data_attr);
                $to_ret[] = '<option value="' . esc_q2($row[$key_fld]) . '"' . $list_data_attr . '>' . conv($row[$value_fld]) . '</option>';
            }
        }
        return $to_ret;

    }

    static function found_rows()
    {
        /**
         * @var int $nb
         **/
        $res = self::query("
			SELECT FOUND_ROWS() nb
		",
            'acces_table', 'found rows');
        extract($res->fetch_assoc());
        return $nb;
    }

    static function tick_mark($list) {
        return array_map(function($fld){return "`$fld`";}, $list);
    }

    static function implode_flds($list) {
        return implode(', ', array_map(function($fld){ return "`$fld`";}, $list));
    }
}

