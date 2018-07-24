<?php

// array related helpers
use Underscore\Types\Arrays;
use Phamda\Phamda as P;

class A
{

    static public function get_or(array $a, $k, $d = null)
    {
        return array_key_exists($k, $a) ? $a[$k] : $d;
    }

    static public function get_or_(array $a, $k, $d = null)
    {
        return Arrays::get($a, $k) ?: $k;

    }

    static public function toPairs($array)
    {
        $res = [];
        foreach($array as $ind => $val) {
            $res[]  = [$ind, $val];
        }
        return $res;
    }

    static public function key_true(array $array, $key)

    {
        return array_key_exists($key, $array) and $array[$key];
    }

    static function if_key(array $array, $key, $then = null, $else = null)
    {
        if (self::key_true($array, $key)) {
            return $then;
        } else {
            return $else;
        }
    }

    static function renameKey(array &$a, string $old, string $new, $default = null)
    {

        $a[$new] = A::get_or($a, $old, $default);
        if (array_key_exists($old, $a)) {
            unset($a[$old]);
        }
        return true;
    }

    static function renameKeyEach(array &$a, string $old, string $new, $default = null)
    {
        foreach ($a as &$val) {
            self::renameKey($val, $old, $new, $default);
        }
    }

    static function renameKeys(array &$a, array $replacement_keys_sets)
    {

        foreach ($replacement_keys_sets as $replacement_keys) {
            self::renameKey($a, ...$replacement_keys);
        }

    }

    static function renameKeysEach(array &$a, array $replacement_keys_sets)
    {
        foreach ($a as &$val) {
            self::renameKeys($val, $replacement_keys_sets);
        }
    }

    static public function first_key($a, $keys, $default_pair = [null, null])
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $a))
                return [$k, $a[$k]];
        }
        return $default_pair;
    }

    static public function assert_key(array &$a, $k)
    {
        if (!array_key_exists($k, $a))
            throw new Exception('array does not contain key ' . $k);
    }

    static public function ensure(array &$a, $k, $v)
    {
        if (!array_key_exists($k, $a))
            $a[$k] = $v;
    }

    static public function push(&$a, $key, $v)
    {
        A::ensure($a, $key, []);
        $a[$key][] = $v;
    }

    static public function push_unique(&$a, $key, $v)
    {
        A::ensure($a, $key, []);
        if (!in_array($v, $a[$key])) {
            $a[$key][] = $v;
        }
    }

    static public function group_by($array, $key, $erase_val = false)
    {
        if (is_string($key) or is_array($key) and count($key) == 1) {
            if (is_array($key)) {
                $key = $key[0];
            }
            $r = [];
            foreach ($array as $a) {
                $key_val = $a[$key];
                if ($erase_val) {
                    unset($a[$key]);
                }
                $r[$key_val][] = $a;
            }
            return $r;
        }
        $k = array_shift($key);
        $temp = self::group_by($array, $k, $erase_val);
        foreach ($temp as $ind => &$val) {
            $val = self::group_by($val, $key, $erase_val);
        }
        return $temp;
    }


    static public function group_by_unique($array, string $key, $erase_val = false)
    {

        $r = [];

        foreach ($array as $a) {
            $key_val = $a[$key];
            if ($erase_val) {
                unset($a[$key]);
            }

            $r[$key_val] = $a;
        }
        return $r;
    }

    static function max_(array $array, $key = null, $fn)
    {
        if (count($array) == 0) {
            return [null, null];
        }

        $max_val = (is_null($key) ? $fn($array[0]) : $fn($array[0][$key]));
        $max_ind = 0;
        foreach ($array as $ind => $item) {
            $val = (is_null($key) ? $fn($item) : $fn($item[$key]));
            if ($val > $max_val) {
                $max_ind = $ind;
                $max_val = $val;
            }
        }
        return [$max_val, $max_ind];
    }

    static function max(array $array, $key = null, $fn = null)
    {
        if (count($array) == 0) {
            return null;
        }
        if (!isset($fn)) {
            $fn = function ($v) {
                return $v;
            };
        }
        return self::max_($array, $key, $fn)[0];
    }

    static function max_ind(array $array, $key = null, $fn = null)
    {
        if (count($array) == 0) {
            return null;
        }
        if (!isset($fn)) {
            $fn = function ($v) {
                return $v;
            };
        }
        return self::max_($array, $key, $fn)[1];
    }

    static function min_(array $array, $key = null, $fn)
    {
        if (count($array) == 0) {
            return [null, null];
        }
        $min_val = (is_null($key) ? $fn($array[0]) : $fn($array[0][$key]));
        $min_ind = 0;
        foreach ($array as $ind => $item) {
            $val = (is_null($key) ? $fn($item) : $fn($item[$key]));
            if ($val < $min_val) {
                $min_ind = $ind;
                $min_val = $val;
            }
        }
        return [$min_val, $min_ind];
    }

    static function min(array $array, $key = null, $fn = null)
    {
        if (count($array) == 0) {
            return null;
        }
        if (!isset($fn)) {
            $fn = function ($v) {
                return $v;
            };
        }
        return self::min_($array, $key, $fn)[0];
    }

    static function min_ind(array $array, $key = null, $fn = null)
    {
        if (count($array) == 0) {
            return null;
        }
        if (!isset($fn)) {
            $fn = function ($v) {
                return $v;
            };
        }
        return self::min_($array, $key, $fn)[1];
    }


    public static function build($set)
    {
        $subset = array_shift($set);
        $cartesianSubset = self::build($set);

        $result = array();
        foreach ($subset as $value) {
            foreach ($cartesianSubset as $p) {
                array_unshift($p, $value);
                $result[] = $p;
            }
        }
        return $result;
    }

    static public function tree_by_parent_key($arr, $k_parent_key, $k_key)
    {
        $by_key = [];
        $roots_by_key = [];

        foreach ($arr as &$a) {
            A::ensure($a, 'children', []);
            $by_key[$a[$k_key]] =& $a;
        }
        unset($a);

        foreach ($arr as &$a) {
            $pk = $a[$k_parent_key];
            if (array_key_exists($pk, $roots_by_key)) {
                $roots_by_key[$pk]['children'][] =& $a;
            } else {
                foreach (array_keys($roots_by_key) as $k) {
                    if ($a[$k_key] == ($roots_by_key[$k][$k_parent_key])) {
                        $a['children'][] =& $roots_by_key[$k];
                        unset($roots_by_key[$k]);
                    }
                }
                $roots_by_key[$a[$k_key]] =& $a;
            }
        }
        unset($a);

        return $roots_by_key;
    }

    static function rand(array $array)
    {
        if (!$array) {
            return $array;
        }
        return $array[array_rand($array)];
    }

    static function numVals(array $array, $keys)
    {
        if (is_string($keys)) {
            $keys = explode(',', $keys);
        }
        foreach ($keys as $key) {
            if (array_key_exists($key, $array)) {
                $array[$key] = (integer)$array[$key];
            }
        }
        return $array;
    }

    static function setBoolean(&$array, $flds, $nulls_too = true)
    {
        foreach ($flds as $fld) {
            if (array_key_exists($fld, $array)) {
                if ($nulls_too or !is_null($array[$fld])) {
                    $array[$fld] = !!$array[$fld];
                }
            }
        }
    }

    static function setBooleanEach(&$array, $flds, $nulls_too = true)
    {
        foreach ($array as &$row) {

            foreach ($flds as $fld) {
                if (array_key_exists($fld, $row)) {
                    if ($nulls_too or !is_null($row[$fld])) {
                        $row[$fld] = !!$row[$fld];
                    }

                }
            }

        }
    }

    static function setInt(&$array, $flds, $nulls_too = true)
    {
        if ($nulls_too) {
            foreach ($flds as $fld) {
                if (array_key_exists($fld, $array)) {
                    $array[$fld] = (int)$array[$fld];
                }
            }

        } else {
            foreach ($flds as $fld) {
                if (array_key_exists($fld, $array)) {
                    $val = $array[$fld];
                    if (!is_null($val)) {
                        $array[$fld] = (int)$array[$fld];
                    }
                }
            }
        }

    }

    static function setIntEach(&$array, $flds, $nulls_too = true)
    {
        if ($nulls_too) {
            foreach ($array as &$row) {
                foreach ($flds as $fld) {
                    if (array_key_exists($fld, $row)) {
                        $row[$fld] = (integer)$row[$fld];
                    }
                }

            }

        } else {
            foreach ($array as &$row) {
                foreach ($flds as $fld) {
                    if (array_key_exists($fld, $row)) {
                        $val = $row[$fld];
                        if (!is_null($val)) {
                            $row[$fld] = (integer)$row[$fld];
                        }
                    }
                }
            }
        }

        unset($row);
    }

    static function setFloat(&$array, $flds, $nulls_too = true)
    {
        if ($nulls_too) {
            foreach ($flds as $fld) {
                if (array_key_exists($fld, $array)) {
                    $array[$fld] = (float)$array[$fld];
                }
            }

        } else {
            foreach ($flds as $fld) {
                if (array_key_exists($fld, $array)) {
                    $val = $array[$fld];
                    if (!is_null($val)) {
                        $array[$fld] = (float)$array[$fld];
                    }
                }
            }
        }

    }

    static function setFloatEach(&$array, $flds, $nulls_too = true)
    {
        if ($nulls_too) {
            foreach ($array as &$row) {
                foreach ($flds as $fld) {
                    if (array_key_exists($fld, $row)) {
                        $row[$fld] = (float)$row[$fld];
                    }
                }

            }

        } else {
            foreach ($array as &$row) {
                foreach ($flds as $fld) {
                    if (array_key_exists($fld, $row)) {
                        $val = $row[$fld];
                        if (!is_null($val)) {
                            $row[$fld] = (float)$row[$fld];
                        }
                    }
                }
            }
        }

        unset($row);
    }

    static function nonNull(&$array, $except = [])
    {
        $nulls = [];
        if (!$except) {
            foreach ($array as $ind => $val) {

                if (is_null($val)) {
                    $nulls[$ind] = 1;
                }
            }

        } else {
            foreach ($array as $ind => $val) {
                if (in_array($ind, $except)) {
                    continue;
                }
                if (is_null($val)) {
                    $nulls[$ind] = 1;
                }
            }


        }
        $array = array_diff_key($array, $nulls);
    }

    static function nonNullEach(&$array, $except = [])
    {
        if (!$except) {
            foreach ($array as &$row) {
                foreach ($row as $ind => $val) {
                    if (is_null($val)) {
                        unset($row[$ind]);
                    }
                }
            }

        } else {
            foreach ($array as &$row) {
                foreach ($row as $ind => $val) {
                    if (in_array($ind, $except)) {
                        continue;
                    }
                    if (is_null($val)) {
                        unset($row[$ind]);
                    }
                }
            }

        }

    }

    static function explode(&$array, $flds, $sep = ',')
    {
        foreach ($flds as $fld) {
            if (array_key_exists($fld, $array) and $array[$fld]) {
                debug_print_once('exploding ' . $array[$fld]);

                $array[$fld] = explode($sep, $array[$fld]);
                debug_print_once('result = ' . $array[$fld]);
            } else {
                $array[$fld] = [];
            }
        }
    }

    static function explodeEach(&$array, $flds, $sep = ',')
    {
        foreach ($array as &$row) {
            foreach ($flds as $fld) {
                if (array_key_exists($fld, $row) and $row[$fld]) {
                    $row[$fld] = explode($sep, $row[$fld]);
                } else {
                    $row[$fld] = [];
                }
            }
        }
    }


    static function select($array, $flds)
    {
        $res = [];
        foreach ($flds as $fld) {
            if (array_key_exists($fld, $array)) {
                $res[$fld] = $array[$fld];
            }
        }
        return $res;
    }

    static function indexesOf($array, $flds)
    {
        $res = [];
        foreach ($flds as $fld) {
            $pos = array_search($fld, $array);
            if ($pos !== false) {
                $res[] = $pos;
            }
        }
        return $res;
    }

    static function sumKeys(Array $array, Array $keys)
    {
        $total = 0;

        foreach ($keys as $key) {
            $total += $array[$key];
        }
        return $total;
    }

    static function sumKeysEach(Array $array, Array $keys)
    {
        $total = 0;
        foreach ($array as $item) {
            if (is_array($item)) {
                foreach ($keys as $key) {
                    $total += $item[$key];
                }
            }
        }
        return $total;
    }

    static function pluck($array, $fld)
    {
        if (is_string($fld)) {
            return array_map(function ($v) use ($fld) {
                return $v[$fld];
            }, $array);
        }
        $keys = [];
        foreach ($fld as $key) {
            $keys[$key] = 0;
        }
        return array_map(function ($v) use ($keys) {
            return array_intersect_key($v, $keys);
        }, $array);
    }

    static function withoutEach(Array &$array, Array $keys)
    {
        if (!$keys) {
            return;
        }
        $toOmit = [];
        foreach ($keys as $key) {
            $toOmit[$key] = null;
        }
        foreach ($array as &$val) {
            $val = array_diff_key($val, $toOmit);
        }
    }

    static function remove(Array &$arr, Array $vals)
    {
        foreach ($vals as $val) {
            $ind = array_search($val, $arr);
            if ($ind !== false) {
                array_splice($arr, $ind, 1);
            }

        }

    }

    static function get_fn_extract_unique($fld)
    {
        return P::pipe(
            P::pluck($fld),
            P::flatten(),
            'array_unique',
            'array_merge'
        );
    }

    static function extract_unique($array, $fld)
    {
        $fn = self::get_fn_extract_unique($fld);
        return $fn($array);
    }

    static function is_unique($array)
    {
        return count($array) == count(array_unique($array));
    }

    static function collect($array, $flds, $separ = ',')
    {
        $liste = [];
        foreach ($array as $item) {
            foreach ($flds as $fld) {
                $val = A::get_or($item, $fld);
                if (!$val) {
                    continue;
                }
                if (is_string($val)) {
                    array_push($liste, ...explode(',', $val));
                } else if (is_array($val)) {
                    array_push($liste, ...$val);
                }
            }
        }
        return array_merge(array_unique($liste));
    }

    static function levels_to_subs($array, $level_fld, $tag_sub = 'sub')
    {
        if (!count($array)) {
            return [];
        }
        $count = function ($a) use ($tag_sub, &$count) {
            $len = 0;
            if (!is_array($a)) {
                return 0;
            }
            foreach ($a as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $len++;
                if (array_key_exists($tag_sub, $item)) {
                    $len += $count($item[$tag_sub]);
                }
            }
            return $len;
        };
        $result = [];
        $ind = 0;
        $min_level = $array[0][$level_fld];
        while ($ind < count($array) and $array[$ind][$level_fld] >= $min_level) {
            if ($array[$ind][$level_fld] == $min_level) {
                $result[] = $array[$ind++];
            } else {
                $result[count($result) - 1][$tag_sub] = self::levels_to_subs(array_slice($array, $ind), $level_fld, $tag_sub);
                $ind += $count(end($result)[$tag_sub]);
            }

        }
        return $result;

    }

    static function group_rows($data_by_fld)
    {
        $res = [];
        for ($ind = 0; $ind < count($data_by_fld[0]); $ind++) {
            $line = [];
            foreach ($data_by_fld as $fld_vals) {
                $line[] = $fld_vals[$ind];
            }
            $res[] = $line;
        }
        return $res;
    }

}
