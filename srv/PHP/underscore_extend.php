<?php
/**
 * Created by PhpStorm.
 * User: micra_000
 * Date: 2015-06-20
 * Time: 17:34
 */
namespace UnderscoreExtensions;

use \Underscore\Types\Arrays;
use \Underscore\Types\Strings;



Arrays::extend('pick', function($array, $picked = []){

    if (is_string($picked)){
        $picked = explode(',', $picked);
    }
    $res = [];
    foreach($picked as $key){
        if (array_key_exists($key, $array)){
            $res[$key] = $array[$key];
        }
    }

    return $res;

});

Arrays::extend('pick_multi', function($array, $picked = []){
    if (is_string($picked)){
        $picked = explode(',', $picked);
    }
    foreach($array as &$item){
        $res = [];
        foreach($picked as $key){
            if (array_key_exists($key, $item)){
                $res[$key] = $item[$key];
            }
        }
        $item = $res;

    }
    return $array;
});
Arrays::extend('omit', function($array, $picked = []){

    if (is_string($picked)){
        $picked = explode(',', $picked);
    }
    $result = [];
    foreach($array as $index=>$val){
        if (!in_array($index, $picked)){
            $result[$index] = $val;
        }
    }
    return $result;

});
Arrays::extend('values', function($array){
    return array_values($array);
});

Arrays::extend('unique', function($array, $mode = SORT_STRING){
    return array_unique($array, $mode);
});
Arrays::extend('unique_reg', function ($array) {
    return array_unique($array, SORT_REGULAR);
});
Arrays::extend('unique_fn', function($array, $fn){
    $ind_uniq =  array_keys(array_unique(array_map($fn, $array)));
    return array_values(Arrays::pick($array, $ind_uniq));
});
Arrays::extend('unique_at', function ($array, $ind, $mode = SORT_STRING) {
    $arr =  Arrays::create()->from($array);

    if (!is_array($ind)) {
        $val = $arr->get($ind);
        if (is_array($val)) {
            return $arr->set($ind, array_unique($val, $mode))->obtain();
        } else {
            return $array;
        }
    }
    foreach($ind as $i){
        $val = $arr->get($i);
        if (is_array($val)) {
            $arr->set($i, array_unique($val, $mode));
        }
    }
    return $arr->obtain();
});
Arrays::extend('explode_at', function ($array, $ind,$with, $limit = null) {
    $arr = Arrays::from($array);

    if (is_string($ind)){
        $ind = explode(',', $ind);
    }
    foreach($ind as $one_ind){
        $val = $arr->get($one_ind);
        if (is_string($val)){
            $arr->set($one_ind, $a = explode($with, $val));
        }
    }
    return $arr->obtain();
});
Arrays::extend('explode_ind', function($array, $ind, $with = ',', $limit = null){
    foreach($array as &$val){
        if ($val[$ind]){
            if (is_null($limit)){
                $val[$ind] = explode($with, $val[$ind]);
            } else {
                $val[$ind] = explode($with, $val[$ind], $limit);
            }

        } else {
            $val[$ind] = [];
        }
    }
    return $array;
});

Arrays::extend('default_at', function($array, $ind, $val){
    if (is_string($ind)){
        $ind = explode(',', $ind);
    }
    foreach($ind as $i){
        if (!isset($array[$i])){
            $array[$i] = $val;
        }
    }
    return $array;
});
Arrays::extend('default_at_', function($array, $ind, $val){
    $array = Arrays::from($array);
    if (is_string($ind)){
        $ind = explode(',', $ind);
    }
    foreach($ind as $i){
        if (is_null($array->get($i))){
            $array->set($i, $val);
        }
    }
    return (array) $array->obtain();
});

function multi_explode($string, $seps, $keys, $limit)  {

    $sep = array_shift($seps);

    if ($seps){
        return array_map(function($elem) use($seps, $keys, $limit) {
            return multi_explode($elem, $seps, $keys, $limit);
        }, explode($sep, $string));

    } else {
        if ($keys){
            return array_combine($keys, explode($sep, $string, $limit));
        }
        return explode($sep, $string, $limit);
    }

}

Strings::extend('explode_multi',
    function($string, $with, $limit = null){

        if (is_array($with[0])){
            $separateurs = array_shift($with);
            if ($with){
                $keys = $with[0];
            }
            else {
                $keys = [];
            }
        } else {
            $separateurs = $with;
            $keys = [];
        }
        return multi_explode($string, $separateurs, $keys, $limit);
    }

);

Arrays::extend('explode_at_multi',

    function ($array, $ind,$with, $limit = null) {

        if (is_array($with[0])){
            $separateurs = array_shift($with);
            if ($with){
                $keys = $with[0];
            }
            else {
                $keys = [];
            }
        } else {
            $separateurs = $with;
            $keys = [];
        }

        if (is_string($ind)){
            $targets = explode(',', $ind);
        } else {
            $targets = $ind;
        }


        foreach($targets as $target){
            if (trim($array[$target])) {
                $array[$target] = multi_explode(trim($array[$target]), $separateurs, $keys, $limit);
            } else {
                $array[$target] = [];
            }
        }

        return $array;

    });
Arrays::extend('explode_at_multi_all',

    function ($array, $ind,$with, $limit = null) {

        if (is_array($with[0])){
            $separateurs = array_shift($with);
            if ($with){
                $keys = $with[0];
            }
            else {
                $keys = [];
            }
        } else {
            $separateurs = $with;
            $keys = [];
        }

        if (is_string($ind)){
            $targets = explode(',', $ind);
        } else {
            $targets = $ind;
        }

        foreach($array as &$line) {

            foreach ($targets as $target) {
                if (trim($line[$target])) {
                    $line[$target] = multi_explode(trim($line[$target]), $separateurs, $keys, $limit);
                } else {
                    $line[$target] = [];
                }
            }
        }

        return $array;

    });

Arrays::extend('from_res', function($res, $pick = null){
    /**
     * @var $res mysqli_result
     */
    if (!$res or $res->num_rows == 0){
        return Arrays::from([]);
    }
    $liste = [];
    $res->data_seek(0);
    if (!is_null($pick)){
        if (is_string($pick)){
            $pick = explode(',', $pick);
        }
        $picked_keys = array_fill_keys($pick, 0);
        while ($row = $res->fetch_assoc()) {
            $liste[] = array_intersect_key($row, $picked_keys);
        }
    } else {
        while ($row = $res->fetch_assoc()) {
            $liste[] = $row;
        }
    }
    return Arrays::from($liste);
});

Arrays::extend('from_res_values', function($res, $pick = null){
    /**
     * @var $res mysqli_result
     */
    if ($res->num_rows == 0){
        return Arrays::from([]);
    }
    $liste = [];
    $res->data_seek(0);
    if (!is_null($pick)){
        if (is_string($pick)){
            $pick = explode(',', $pick);
        }
        $picked_keys = array_fill_keys($pick, 0);
        while ($row = $res->fetch_assoc()) {
            $liste[] = array_values(array_intersect_key($row, $picked_keys));
        }
    } else {
        while ($row = $res->fetch_row()) {
            $liste[] = $row;
        }
    }
    return Arrays::from($liste);
});

Arrays::extend('from_yaml', function($name_or_content, $is_fname = true){
    if ($is_fname){
        $res = \Spyc::YAMLLoad($name_or_content);
    } else {
        $res = \Spyc::YAMLLoadString($name_or_content);
    }
    if (is_array($res)){
        return Arrays::from($res);
    } else {
        return Arrays::from([]);
    }
});

Arrays::extend('int_at', function($array, $ind){
    if (is_string($ind)){
        $ind = explode(',', $ind);
    }
    foreach($ind as $i){
        if (array_key_exists($i, $array)){
            $array[$i] = (integer)$array[$i];
        }
    }
    return $array;
});
Arrays::extend('int_at_all', function($array, $ind){
    if (is_string($ind)){
        $ind = explode(',', $ind);
    }
    foreach($array as &$item) {
        foreach($ind as $i){
            if (array_key_exists($i, $item)){
                $item[$i] = (integer)$item[$i];
            }
        }

    }
    return $array;
});
Arrays::extend('sql_str_at', function($array, $ind){
    if (is_string($ind)){
        $ind = explode(',', $ind);
    }
    foreach($ind as $i){
        if (array_key_exists($i, $array)){
            $array[$i] = \db::sql_str($array[$i]);
        }
    }
    return $array;
});
Arrays::extend('sql_str_all_at', function($array, $ind){
    if (is_string($ind)){
        $ind = explode(',', $ind);
    }
    return array_map(function($v) use ($ind){
        foreach($ind as $i){
            if (array_key_exists($i, $v)){
                $v[$i] = \db::sql_str($v[$i]);
            }
        }
        return $v;

    },
        $array
    );

});

Arrays::extend('implode_at', function($array, $ind, $str = ','){
    if (is_string($ind)){
        $ind = explode(',', $ind);
    }
    foreach($ind as $i){
        if (array_key_exists($i, $array) and is_array($array[$i])){
            $array[$i] = implode($str, $array[$i]);
        }
    }
    return $array;
});
Arrays::extend('implode_all', function($array, $str = ','){
    return array_map(function($v) use($str){
        if (is_array($v)){
            return implode($str, $v);
        }
        return $v;
    }, $array);

});
Arrays::extend('sql_case', function($array, $vals){
    if (count($array) != count($vals)){
        throw new \Exception('Array counts not matching');
    }
    \db::sql_str_($vals);
    $liste = [];
    foreach($array as $ind=>$val1){
        $liste[] = "WHEN $val1 THEN {$vals[$ind]}";
    }
    return implode(' ', $liste);

});

Arrays::extend('remove_until', function($array, $spec, $keep = true){
    $copy = $array;
    if (is_array($spec)){
        foreach($array as $item){
            if (count(array_diff_assoc($spec, $item))){
                array_shift($copy);
            } else {
                if (!$keep){
                    array_shift($copy);
                }
                break;
            }
        }
    } else {
        foreach($array as $item){
            if (!$spec($item)){
                array_shift($copy);
            } else {
                if (!$keep){
                    array_shift($copy);
                }
                break;
            }
        }
    }
    return $copy;

});

Arrays::extend('levels_to_subs', function($array, $level_fld, $tag_sub = 'sub'){
    if (!count($array)){
        return [];
    }
    $count = function($a) use($tag_sub, &$count){
        $len = 0;
        if (!is_array($a)){
            return 0;
        }
        foreach($a as $item){
            if (!is_array($item)){
                continue;
            }
            $len++;
            if (array_key_exists($tag_sub, $item)){
                $len += $count($item[$tag_sub]);
            }
        }
        return $len;
    };
    $result = [];
    $ind = 0;
    $min_level = $array[0][$level_fld];
    while($ind < count($array) and $array[$ind][$level_fld] >= $min_level){
        if ($array[$ind][$level_fld] == $min_level){
            $result[] = $array[$ind++];
        } else {
            $result[count($result)-1][$tag_sub] = Arrays::levels_to_subs(array_slice($array, $ind), $level_fld, $tag_sub);
            $ind += $count(end($result)[$tag_sub]);
        }

    }
    return $result;

});

Arrays::extend('prefix', function($array, $prefix = ''){
    if (!$prefix){
        return $array;
    }
    foreach($array as $ind=>&$val){
        $val = "$prefix$val";
    }
    return $array;
});



Strings::extend('deburr', function($string){
    return strtr($string,'àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ','aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY');
});