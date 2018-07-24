<?php

/**
 * Created by PhpStorm.
 * User: micra_000
 * Date: 2016-02-07
 * Time: 17:20
 */
class testSucces extends Exception
{
    function __construct($message = 'succes', $code = 0)
    {
        parent::__construct($message, $code);
    }
}