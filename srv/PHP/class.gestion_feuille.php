<?php
/**
 * Created by PhpStorm.
 * User: micra
 * Date: 2018-05-23
 * Time: 14:58
 */

class gestion_feuille extends http_json
{
    function __construct($no_op = false)
    {
        parent::__construct();

        if ($no_op) {
            self::execute_op();
        }
    }

    function fn_get_matchs() {

    }
}