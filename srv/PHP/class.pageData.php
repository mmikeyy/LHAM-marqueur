<?php
use Phamda\Phamda as P;
/**
 * Created by PhpStorm.
 * User: micra
 * Date: 2016-09-22
 * Time: 15:43
 */
class pageData extends http_json
{
    function __construct($no_op = false)
    {
        parent::__construct();

        if (!$no_op) {
            self::execute_op();
        }
    }

    function fn_page_data()
    {
        /**
         * @var int $page
         **/
        extract(self::check_params(
            'page;string;opt;accept_empty_string;empty_string_to_null'
        ));

        if (isset($page) and !preg_match('#^\d+$#', $page)) {
            $sql_page = db::sql_str($page);
            $res = db::query("
                    SELECT id_document page
                    FROM documents
                    WHERE alias = $sql_page
                ", 'acces_table');
            if ($res->num_rows != 1) {
                $page = null;
            } else {
                extract($res->fetch_assoc());
            }

        }

        debug_print_once('======================bbbbbbbb===== toto, page = ' . (isset($page) ? $page : '?'));
        self::set_data('data', self::page_data($page ?? null));
        $this->succes();
    }

    static function page_data($page = null)
    {

        $detect = new Mobile_Detect();
        $is_mobile = $detect->isMobile();
        debug_print_once('........... mobile = ' . ($is_mobile ? 'OUI' : 'NON'));
        $page_html = 'Pas une page';
        $layout_format = '{}';
        $default_layout = '';
        $layout_no = '';
        $tag_layout = cfg_yml::get('general', 'layout_defaut');
        $menu = [];

        if (!$page) {
            $page = cfg_yml::get('general', 'page_defaut');
            $default = true;
        } else {
            $default = ($page == cfg_yml::get('general', 'page_defaut'));
        }

        if (!$default and $page and !perm::test('admin')) {
            /**
             * @var int $affiche_gerant
             * @var int $affiche_entr
             **/
            $res = db::query("
                    SELECT affiche_gerant, affiche_entr
                    FROM documents
                    WHERE id_document = $page
                ", 'acces_table');

            if ($res->num_rows) {
                extract($res->fetch_assoc());
                if (($affiche_gerant or $affiche_entr) and !($affiche_gerant and perm::is_gerant() or $affiche_entr and perm::is_entraineur())) {
                    $page = cfg_yml::get('general', 'page_defaut');
                    if (!$page) {
                        $page = 0;
                    }
                    $default = true;
                }
            }
        }

        if (!contents_processing::document_exists($page)) {
            if ($default) {
                $page_html = 'La page définie comme page par défaut n\'existe pas';
            } else {
                $page_html = "La page demandée (no $page) n'existe pas.";
            }
        } else {
            $page_html = contents_processing::get_contents($page);
            $layout_format = contents_processing::$format_layout;
            $default_layout = contents_processing::$default_layout;
            $layout_no = contents_processing::$no_layout;
            $tag_layout = contents_processing::$tag_layout;
            $menu = gestion_pages::get_menu($page);
        }


        return [
            'login_info' => login_visiteur::login_data(),
            'sel' => cfg_yml::get_('general.salt'),
            'sessionId' => session_id(),
            'pub_data' => login_visiteur::pub_data(),
            'page_data' => [
                'page' => $page,
                'data' => $page_html,
                'layout_format' => $layout_format,
                'default_layout' => $default_layout,
                'layout_no' => $layout_no,
                'tag_layout' => $tag_layout,
                'default_page' => cfg_yml::get('general', 'page_defaut'),
                'menu' => $menu,
                'lang' => lang::lang(),
                'tout_montrer' => A::get_or($_SESSION, 'tout_montrer', false),
                'footer_html' => edit_footer::get_footer_html(),
                'page_aliases' => self::get_aliases()
            ],

            'ref_data' => contents_processing::get_ref_data(),
            'pub_insertions' => gestion_publicite::get_pub_ref(),
            'cfg' => [
                'mobile' => $is_mobile
            ]
        ];

    }

    static function get_aliases() {
        $res = db::query("
                SELECT alias, id_document
                FROM documents
                WHERE alias IS NOT NULL
                ORDER BY alias
            ", 'acces_table');

        return db::result_array_one_value($res, 'id_document', 'alias');

    }

    function html_page_data($page = null)
    {
        $spec = [];
        foreach (self::page_data($page) as $ind => $val) {
            $spec[] = ['id' => $ind, 'data' => $val];
        }
        return twig::render('json_script.html.twig', $spec);
    }

    function fn_get_sel()
    {
        self::set_data('sel', cfg_yml::get('general', 'salt'));
        $this->succes();
    }

    function fn_get_salt_sessid()
    {
        self::set_data('sel', cfg_yml::get('general', 'salt'));
        self::set_data('sessId', session_id());
        $this->succes();
    }

    function fn_get_siblings()
    {
        /**
         * @var int $element_structure
         */
        extract(self::check_params(
            'element_structure;unsigned'
        ));

        $res = db::dquery(
            "
            SELECT s.element_structure,
              s.type_insertion,
              s.niveau,
              s.parent,
              c.contenu_fr,
              s.ordre,
              s.contexte_widget,
              s.contexte_titre_fr
            FROM structure2 s
            LEFT JOIN contenus c USING(id_contenu)
            JOIN (SELECT id_document
              FROM structure2 s
              WHERE element_structure = $element_structure
            ) s2 USING(id_document)
            WHERE s.archive IS NULL AND s.id_editeur IS NULL
            ORDER BY s.ordre
            "
            , 'acces_table');

        $rows = db::result_array($res);

        $row = array_filter($rows, function ($r) use ($element_structure) {
            return $r['element_structure'] == $element_structure;
        });

        if (!$row) {
            $this->fin('introuvable');
        }
        $row = array_values($row)[0];
        $begin = -1;
        $end = -1;
        $niveau = $row['niveau'];
        $self_id = $row['element_structure'];
        $self_found = false;

        foreach ($rows as $ind => $r) {
            $is_self = ($self_id == $r['element_structure']);

            if ($is_self) {
                $self_found = true;
                $end = $ind;
                if ($begin == -1) {
                    $begin = $ind;
                }
            } else if (!$self_found) {
                if ($r['niveau'] < $niveau) {
                    $begin = -1;
                } else if ($begin == -1) {
                    $begin = $ind;
                }
            } else if ($r['niveau'] < $niveau) {
                break;
            } else {
                $end = $ind;
            }
        }

        $sliced = array_slice($rows, $begin, $end - $begin + 1);
        $indexed_rows = [];
        $root = [
            'children' => [],
            'complete' => false,
            'last_type' => ''
        ];
        foreach ($sliced as $val) {
            $val['children'] = [];
            $val['complete'] = false;
            $val['last_type'] = '';
            $indexed_rows[$val['element_structure']] = $val;

            if ($val['niveau'] > $niveau) {
                $parent = &$indexed_rows[$val['parent']];
            } else {
                $parent = &$root;
            }

            $type = ($val['type_insertion'] == 'suite' ? 'suite' : 'onglet');
            if (!$parent['children'] or
                $parent['complete'] or
                $parent['last_type'] !== $val['type_insertion']
            ) {
                $parent['children'][] = [
                    'type' => $type,
                    'ids' => [$val['element_structure']]
                ];
                $parent['complete'] = ($val['type_insertion'] == 'fin_onglet');
                $parent['last_type'] = $type;
            } else {
                $parent['children'][count($parent['children']) - 1]['ids'][] = $val['element_structure'];
            }
            unset($parent);
        }


        self::set_data('liste', $indexed_rows);
        self::set_data('root', $root);
        self::succes();


    }

    function fn_get_ref_data()
    {
        self::set_data('data', contents_processing::get_ref_data());
        self::succes();
    }

    function fn_save_styles_classes()
    {
        /**
         * @var integer $id_structure
         * @var array $styles
         * @var string $classes
         * @var string $autres
         **/
        extract(self::check_params(
            'id_structure;unsigned',
            'styles;json;decode_array',
            'classes;string;max:150;empty_string_to_null',
            'autres;array'
        ));

        http_json::set_source_check_params_once($styles);

        $vals = self::check_params(
            'marge_haut;int;accept_null',
            'marge_bas;int;accept_null',
            'marge_gauche;int;accept_null',
            'marge_droite;int;accept_null',
            'padding_haut;int;accept_null',
            'padding_bas;int;accept_null',
            'padding_gauche;int;accept_null',
            'padding_droite;int;accept_null',
            'background_color;regex:/^#([0-9A-F]{3}){1,2}$/i;accept_null;empty_string_to_null',
            'border_style;regex:#^(solid|inset|outset|ridge|double|dashed|dotted)$#;accept_null;empty_string_to_null',
            'border_width;unsigned;empty_string_to_null',
            'border_color;regex:/^#([0-9A-F]{3}){1,2}$/i;accept_null;empty_string_to_null',
            'border_radius;unsigned;empty_string_to_null'
        );

        $vals['classes'] = $classes;

        http_json::set_source_check_params_once($autres);

        $vals_autres = self::check_params(
            'cache_date_maj;boolean;bool_to_num;opt'
        );



        $vals = array_merge($vals, $vals_autres);

        /**
         * @var integer $nb
         */
        $res = db::query("
            SELECT COUNT(*) nb
            FROM structure2
            WHERE element_structure = $id_structure
            ",
            'acces_table');

        extract($res->fetch_assoc());
        if (!$nb) {
            $this->fin('introuvable');
        }
        $assignment = db::make_assignment($vals);
        db::query("
            UPDATE structure2
            SET $assignment
            WHERE element_structure = $id_structure
            ",
            'acces_table');

        $flds = P::pipe(
            'array_keys',
            function($v) {return db::quote_fld($v);},
            P::implode(',')
        )($vals);


        $res = db::query("
            SELECT $flds, classes
            FROM structure2
            WHERE element_structure = $id_structure
            ",
            'acces_table');

        $row = $res->fetch_assoc();

        $null_flds = P::pipe(
            P::filter(function($v){return is_null($v);}),
            'array_keys'
        )
        ($row);

        self::set_data('null_flds', $null_flds);

        A::nonNull($row);
        $row['styles'] = contents_processing::get_styles($row);

        self::set_data('updates', $row);

        self::succes();

    }

    function fn_save_event_info() {
        if (!login_visiteur::logged_in()) {
            $this->fin('ouvrir_session');
        }
        $id_visiteur = session::get('id_visiteur');


        /**
         * @var int $id
         * @var string $info
         * @var string $sql_info
         **/
        extract(self::check_params(
            'id;unsigned',
            'info;string;accept_null;empty_string_to_null'
        ));

        $sql_info = db::sql_str($info);

        if (!perm::test('superhero')) {
            /**
             * @var int $nb
             **/
            $res = db::query("
                    SELECT COUNT(g.id) nb
                    FROM event_guests g
                    JOIN role_equipe re ON re.id_equipe = g.id_equipe
                    WHERE g.id_event = $id AND re.role <= 1
                ", 'acces_table');
            extract($res->fetch_assoc());
            if (!$nb) {
                self::non_autorise();
            }

        }

        if (!is_null($info) and strlen($info) > 150) {
            $info = substr($info, 0, 150);
            $sql_info = db::sql_str($info);
        }


        $res = db::query("
                UPDATE events
                SET info = $sql_info
                WHERE id = $id
            ", 'acces_table');

        self::set_data('update', ['info' => $info]);
        $this->succes();

    }

    function fn_save_widget_data() {
        /**
         * @var int $id_structure
         * @var int $widget
         * @var array  $params
         **/
        extract(self::check_params(
            'id_structure;unsigned',
            'widget;regex:#^(horaires)$#',
            'params;array'
        ));

        $this->verif_admin();

        /**
         * @var string $contexte_widget
         **/
        $res = db::query("
                SELECT contexte_widget
                FROM structure2
                WHERE element_structure = $id_structure
                FOR UPDATE 
            ", 'acces_table');
        if ($res->num_rows == 0) {
            $this->fin('introuvable');
        }
        extract($res->fetch_assoc());

        if ($widget != $contexte_widget) {
            $this->fin('mauvais_widget');
        }

        switch($widget) {
            case 'horaires':
                $params = json_encode(['resultats_seulem' => !!A::get_or($params, 'resultats_seulem', false)]);
                break;
            default:
                $params = '';
        }
        $sql_params = db::sql_str($params);

        $res = db::query("
                UPDATE structure2
                SET contexte_widget_params = $sql_params
                WHERE element_structure = $id_structure
            ", 'acces_table');

        self::set_data('params', $params);

        $this->succes();

    }
}