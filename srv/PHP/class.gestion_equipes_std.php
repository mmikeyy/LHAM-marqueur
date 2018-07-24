<?php

/**
 * Created by PhpStorm.
 * User: micra_000
 * Date: 2016-08-01
 * Time: 16:49
 */
class gestion_equipes_std extends http_json
{
    static $liste = []; // liste ordonnée
    static $ref = []; // id => data
    static $liste_loaded = false;
    static $identifiant_association = '';

    function __construct($no_op = false)
    {
        parent::__construct();

        if (!$no_op){
            $this->execute_op();
        }
    }

    static function load_data($force = false){
        if (self::$liste_loaded and !$force){
            return;
        }
        self::$identifiant_association = cfg_yml::get('noms_equipes', 'mot_clef_recherche');
        $res = db::query("
                SELECT *
                FROM noms_equipes
                ORDER BY ordre
            ", 'acces_table');

        self::$liste = $res->fetch_all(MYSQLI_ASSOC);
        self::$ref = db::result_array($res, 'id');
        self::$liste_loaded = true;
    }

    static function is_valid_id($id){
        self::load_data();
        return array_key_exists($id, self::$ref);
    }

    static function get_id_nom_std($nom, $verifier_assoc = true) {
        self::load_data();
        if ($verifier_assoc and stripos($nom, self::$identifiant_association) === false) {
            return null;
        }
        foreach(self::$liste as $eq) {
            if (stripos($nom, $eq['nom_std']) !== false) {
                return $eq['id'];
            }
        }
        return null;
    }
}