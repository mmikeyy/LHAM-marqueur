<?php

/**
 * Created by PhpStorm.
 * User: micra_000
 * Date: 2016-02-07
 * Time: 17:23
 */
class testEchec extends Exception
{
    function __construct($message = 'echec', $code = 0)
    {
        parent::__construct($message, $code);
    }
}