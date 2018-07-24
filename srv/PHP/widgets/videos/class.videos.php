<?php

/**
 * Created by PhpStorm.
 * User: micra
 * Date: 2017-05-04
 * Time: 23:35
 */
class videos extends http_json
{
    function __construct($no_op = false)
    {
        parent::__construct();
        if (!$no_op) {
            self::execute_op();
        }
    }

    function fn_save_liste_videos() {
        /**
         * @var int $id_structure
         * @var string $data
         **/
        extract(self::check_params(
            'id_structure;unsigned',
            'data;json;sql'
        ));

        if (!perm::test_video($id_structure)) {
            self::non_autorise();
        }

        $this->test_widget_video($id_structure);

        $res = db::query("
                UPDATE structure2
                SET contexte_widget_data = $data
                WHERE element_structure = $id_structure
            ", 'acces_table');

        $this->succes();

    }

    function fn_save_params_videos() {
        /**
         * @var int $id_structure
         * @var int $params
         **/
        extract(self::check_params(
            'id_structure;unsigned',
            'params;json;sql'
        ));
        if (!perm::test_video($id_structure)) {
            self::non_autorise();
        }
        $this->test_widget_video($id_structure);

        $res = db::dquery("
                UPDATE structure2
                SET contexte_widget_params = $params
                WHERE element_structure = $id_structure
            ", 'acces_table');

        $this->succes();


    }

    function test_widget_video($id_structure) {
        /**
         * @var string $contexte_widget
         **/
        $res = db::query("
                SELECT contexte_widget
                FROM structure2
                WHERE element_structure = $id_structure
                FOR UPDATE 
            ", 'acces_table');

        if (!$res->num_rows) {
            $this->fin('introuvable');
        }
        extract($res->fetch_assoc());
        if ($contexte_widget != 'videos') {
            $this->fin('mauvais_widget');
        }
    }
}