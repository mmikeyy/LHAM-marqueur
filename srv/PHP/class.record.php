<?php


class record {

    public $assignment_ = [];
    public $found_;
    public $flds_ = [];
    public $validation_ = [];
    public $erreur_ = '';
    public $required_insert_flds_ = [];

    function __construct($id = null, $vals = null)
    {
       if (!is_null($id)){
           $this->id = $id;
       }
       $this->flds_ = array_keys($this->validation_);

    }

    function select($vals)
    {
        $to_ret = [];
        if (is_string($vals)){
            $vals = preg_replace(['#^ *| *$#', '# *, *#', '# +#'], ['', ',', ' '], $vals);
            $vals_array = explode(',', $vals);
            $specs = [];
            foreach($vals_array as $fld){
                $fld = explode(' ', $fld);
                if (count($fld) == 1){
                    $specs[] = $fld[0];
                } else {
                    $specs[$fld[0]] = $fld[1];
                }
            }
        } else {
            $specs = $vals;
        }
        foreach($specs as $fld=>$alias){
            if (preg_match('#^\d+$#', $fld)){
                $to_ret[$alias] = $this->$alias;
            } else {
                $to_ret[$alias] = $this->$fld;
            }
        }
        return $to_ret;
    }
    function pre_update($vals)
    {
        return true;
    }
    function post_update($vals, $res)
    {
        return true;
    }
    function save()
    {
        $this->update();
    }
    function update($vals = null)
    {
        if (is_null($vals)){
            $vals = $this->flds_to_vals();
        }
        if (!($pre = $this->pre_update($vals))){
            return $pre;
        }
        $assign = db::make_assignment($vals);
        $res = db::query("
            UPDATE $this->table_
            SET $assign
            WHERE $this->id_ = $this->id
		", 			'acces_table', '');

        if (!$post = $this->post_update($vals, $res))
        {
            return $post;
        }

        $this->load();

        return $res;
    }

    function get_derived_flds($prefix = '')
    {
        if ($prefix){
            $prefix = "$prefix.";
        }
        if ($this->derived_flds_ and count($this->derived_flds_)){
            $derived_flds = [];
            foreach($this->derived_flds_ as $alias => $expr){
                $derived_flds[] = str_replace('%s', $prefix, $expr) . " $alias";
            }
            $derived_flds = ',' . implode(',', $derived_flds);
        } else {
            $derived_flds = '';
        }
        return $derived_flds;
    }

    function load($id = null, $lock_level = 0)
    {
        switch($lock_level){
            case 1:
                $lock_statement = 'LOCK IN SHARE MODE';
                break;
            case 2:
                $lock_statement = 'FOR UPDATE';
                break;
            default:
                $lock_statement = '';
        }
        if (!is_null($id)){
            $this->key_set($id);
        } else {
            $id = $this->key_val();
        }

        $derived_flds = $this->get_derived_flds();


        $res = db::query("
            SELECT * $derived_flds
            FROM $this->table_
            WHERE id = $id
		", 			'acces_table', '');
        if ($res->num_rows == 0) {
            $this->found_ = 0;
            foreach($this->flds_ as $fld){
                $this->$fld = null;
            }
            return false;
        }
        $this->found_ = 1;
        $vals = $res->fetch_assoc();
        foreach($vals as $fld=>$val){
            $this->$fld = $val;
        }

        return $vals;
    }

    function __get($name)
    {
        if (strpos($name, 'is_') === 0){
            $fld = substr($name, 3) . '_';
            return $this->$fld;
        }
    }
    function set($vals)
    {
        $set_vals =[];
        foreach($vals as $key=>$val){
             if (!in_array($key, $this->flds_)){
                continue;
             }
             $this->$key = $val;
        $set_vals[$key] = $val;
        }
        return $set_vals;
    }
    function found()
    {
        return $this->found_;
    }


    function validate($vals)
    {
        $http_json = new http_json();

        $http_json->set_source_check_params_once($vals);
//        debug_print_once("Vals = " . print_r($vals,1));
//        debug_print_once("validation = " . print_r($this->validation_,1));
//        debug_print_once('keys = ' . print_r(array_intersect_key($this->validation_, $vals),1));
        $validation_spec = [];
        foreach(array_intersect_key($this->validation_, $vals) as $fld=>$spec){
            $validation_spec[] = "$fld;$spec";
        }
//        debug_print_once("validerrrrrrrrrr " . print_r($vals,1));
//        debug_print_once("specs = " . print_r(                $validation_spec
#,1));
        debug_print_once('validation spec');
        debug_print_once(print_r($validation_spec,1));
        debug_print_once('validations = ');
        debug_print_once(print_r($this->validation_,1));
        debug_print_once('vals');
        debug_print_once(print_r($vals,1));
        $validated = $http_json->check_params(
                $validation_spec
		);

        return $validated;
    }
    function is_error()
    {
        return $this->erreur_;
    }
    function clr_error()
    {
        $this->erreur_ = '';
    }
    function get_error()
    {
        return $this->erreur_;
    }

    function all_exist($keys, $array)
    {
        if (is_string($keys)){
            $keys = explode(',', $keys);
        }
        $to_ret = [];
        foreach($keys as $key){
            if (!array_key_exists($key, $array) or !$array[$key]){
                return false;
            }
            $to_ret[$key] = $array[$key];
        }
        return $to_ret;
    }

    function defined($keys, $array)
    {
        $this->clr_error();

        if (is_string($keys)){
            $keys = explode(',', $keys);
        }
        $manquent = [];
        debug_print_once("verif " . print_r($array,1));
        foreach($keys as $key){
            if (!array_key_exists($key, $array)){
                $manquent[] = $key;
            }

        }
        if (count($manquent)){
            $this->erreur_ = implode(', ', $manquent);
        }

        return count($manquent) == 0;

    }
    function get_required_insert_flds()
    {
        if ($this->required_insert_flds_){
            return;
        }

        foreach($this->validation_ as $fld=>$valid){
            if ($fld == $this->id_){
                continue;
            }
            if (!preg_match('#.*\bopt\b.*#', $valid)){
                $this->required_insert_flds_[] = $fld;
            }
        }
    }
    function verif_champs_oblig($vals)
    {
        $this->get_required_insert_flds();
        debug_print_once("RRRRReq flds = " . print_r($this->required_insert_flds_,1));
        debug_print_once("vals = " . print_r($vals,1));
        foreach($this->required_insert_flds_ as $required_fld){
            if (!isset($vals[$required_fld])){
                $this->fin('champ_manquant', $required_fld);
            }
        }
    }

    function flds_to_vals()
    {
        $vals = [];
        foreach($this->flds_ as $fld){
            if (isset($this->$fld)){
                $vals[$fld] = $this->$fld;
            }
        }
        return $vals;
    }

    function insert($vals = null)
    {

        if (is_null($vals)){
            $vals = $this->flds_to_vals();
        }

        $id_fld = $this->id_;
        if (array_key_exists($this->id_, $vals)){
            return false;
        }
        $this->verif_champs_oblig($vals);

        if (!($pre = $this->pre_update($vals))){
            return $pre;
        }
        $assign = db::make_assignment($vals);
        $res = db::query("
            INSERT INTO $this->table_
            SET $assign

		");
        if (!$res){
            $this->fin('acces_table');
        }
        $this->$id_fld = db::get('insert_id');

        if (!$post = $this->post_update($vals, $res))
        {
            return $post;
        }

        $this->load();
        return $this->$id_fld;

    }
    function fin($msg = null, $msg2=null)
    {
        http_json::erreur($msg, $msg2);
    }
    function key_val()
    {
        $id = $this->id_;
        if (isset($this->$id)){
            return $this->$id;
        }
        return null;
    }
    function key_set($val)
    {
        $id = $this->id_;
        $this->$id = $val;
    }
    function array_select($array_source, $keys, $not_null = false)
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

}


