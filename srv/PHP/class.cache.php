<?php

class cache {
    
    static $updated;
    static $contents;
    static $age;
    static $found;
    static $tag;
    
    
   
    static function read($tag)
    {
        self::$tag = $tag;
        $sql_tag = db::sql_str($tag);
        
        $res = db::multi_query("
            START TRANSACTION;
            
            SELECT data, 
                date,
                TIME_TO_SEC(TIMEDIFF(NOW(), date)) age
            FROM cache
            WHERE tag = $sql_tag AND NOT deleted;
            
            UPDATE cache
            SET nb_reads = nb_reads + 1
            WHERE tag = $sql_tag AND NOT deleted;
                
            COMMIT
            ");
        # debug_print_once(print_r($res,1));
        if ($res === false or count($res[0]) == 0){
            #debug_print_once("======= result false");
            self::$found = false;
            return false;
        }
        
        #debug_print_once("------------------------" . print_r($res,1));
        
        
        self::$found = true;
        extract($res[0][0]);
        self::$updated = $date;
        self::$age = $age;
        
        
        return $data;
    }
    static function age($tag = null)
    {
        if (is_null($tag)){
            return self::$age;
        }
        self::$contents = self::read($tag);
        if (!self::$found){
            return -1;
        }
        return self::$age;
    }
    
    static function md_suppress($md_tag)
    {
        self::suppress($md_tag, true);
    }
    
    static function suppress($tag, $md = false)
    {
        self::$tag = $tag;
        
        $sql_tag = db::sql_str($tag);
        $prefix = ($md? 'md_': '');
        if (strpos('%', $tag) !== false){
            $where = "{$prefix}tag LIKE $sql_tag";
        } else {
            $where = "{$prefix}tag = $sql_tag";
        }
        
        $res = db::query("
            UPDATE cache
            SET deleted = 1, data=''
            WHERE $where
            
		");
        self::$found = (db::get('affected_rows') > 0);
        
    }
    static function update($tag, $val)
    {
        self::$tag = $tag;
        $sql_val = db::sql_str($val);
        $sql_tag = db::sql_str($tag);
        
        $res = db::multi_query("
            START TRANSACTION;
            INSERT IGNORE INTO cache
            SET tag = $sql_tag, data = $sql_val, date = NOW(), md_tag = MD5($sql_tag)
            ON DUPLICATE KEY UPDATE
            data = $sql_val,
            date = NOW(),
            deleted=0,
            nb_regens = nb_regens + 1
            ;
            COMMIT
		");
        if ($res === false){
            debug_print_once("echec écriture cache tag $tag");
        }
    }
    
    
    
    
}
?>
