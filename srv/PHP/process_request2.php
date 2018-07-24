<?php

require_once __DIR__ . '/std_include.php';

//file_put_contents(__DIR__ . '/../logs/debug/debug_info_all_users.txt',  "\n--------continue process request", FILE_APPEND);
//
///debug_print_once('.............. process request2');
//file_put_contents(__DIR__ . '/../logs/debug/debug_info_all_users.txt',  "\n--------continue arrahhhglsaj", FILE_APPEND);
//
$input = file_get_contents("php://input");

if ($input){
    $_REQUEST = array_merge($_REQUEST, json_decode($input, 1) ?? []);
}


//debug_print_once('req = ' . print_r($_REQUEST,1));
//debug_print_once('post = ' . print_r($_POST,1));

//debug_print_once('process req files = ' . print_r($_FILES,1));
//debug_print_once('process req request = ' . print_r($_REQUEST,1));
//debug_print_once('process req globals = ' . print_r($GLOBALS,1));
if (isset($_REQUEST['context'])){
	$class = $_REQUEST['context'];


	try{
		if (empty ($class)){
			throw new fin_return('contexte_manquant');
		}
		if (isset($_REQUEST['widget__']) and $_REQUEST['widget__']){
			$widget = $_REQUEST['widget__'];
			//debug_print_once("class = $class");
			require_once "./widgets/$widget/$class.php";
		}
		$a = new $class();
	} catch(fin_return $err){
		http_json::fin('Erreur = ' . $err->getMessage());
	} catch(Exception $err){
		http_json::fin($err->getMessage());
	} catch (myException $err){
		http_json::fin($err->getMessage(), $err->info);
	}
} else{

	http_json::fin('contexte_manquant');
}


