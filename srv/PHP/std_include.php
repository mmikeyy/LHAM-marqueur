<?php
//echo 'asdflkfvsaljdpvjkhlsyfga kjhl fasdkhlgafsdhklsafdkhjlfsadjkh';
//file_put_contents(__DIR__ . '/../logs/debug/debug_info_all_users.txt',  "\n--------" . print_r($_REQUEST, 1), FILE_APPEND);
//echo __DIR__ . '/../PHP/class.pathparser.php';
if (!isset($_SESSION)) {
    session_set_cookie_params(3600 * 24 * 30 * 6);
    session_start();
    $session_was_started = true;
}
$orig_session = $_SESSION;
$S =& $_SESSION;


setcookie('debug', 0);

include_once __DIR__ . '/class.pathparser.php';

// include path réglé temporairement pour qu'il soit possible d'utiliser classes définies dans PHP au départ
$is_windows = preg_match('#windows#i', php_uname('s'));

    $path_separator = ($is_windows ? ';':':');

set_include_path(get_include_path() . $path_separator . implode($path_separator, array(
    __DIR__
)));

//************************************* root
$root = __DIR__ . '/../';
$real_root = realpath($root);

$path = new PathParser();
$input_stream = file_get_contents("php://input");

$root = $path->findRelativePath(realpath('./'), $root);
if (empty($root)){ // super important. ne pas enlever
    $root = './';
}
//die("\nroot = $root");
define('ROOT', $root);
const ACCES_TABLE = "Difficulté d'accès à une table";
//************************************** end root


if (isset($_SERVER['HTTP_HOST']) and strpos($_SERVER['HTTP_HOST'], 'localhost') ===false and strpos($_SERVER['HTTP_HOST'], '127.0.0.1') ===false) {
    $is_local = $G['is_local'] = false;
} else{
    $is_local = $G['is_local'] = true;
}

// ************** debug

if (file_exists($fname= __DIR__ . '/../logs/debug/debug.cfg'))
    $debug_config = file_get_contents($fname);
else
    $debug_config = '*';

require __DIR__ . '/class.debug__.php';

$GLOBALS['debug'] = $debug = new debug__($debug_config, ($is_local?'1':(array_key_exists('id_editeur', $S) and $S['id_editeur'])));


if (false and $is_local) {
    $debug->global_active = true;
    $debug->fname = ROOT . 'logs/debug/debug.txt';
}


//***************** end debug


if (!function_exists('my_autoload')) {

    function my_autoload($class_name)
    {
        //debug_print_once("appelé avec $class_name");
        // debug_print_once(get_include_path());
//        if (preg_match('#^Zend_Gdata.*$#', $class_name)) {
//            require_once __DIR__ . '/../ZendFramework-1.12.9/library/Zend/Loader.php';
//            Zend_Loader::loadClass($class_name);
//            return;
//        }
        //if ($class_name == 'edit_contents') debug_print_once("autoload class.$class_name.php");
        $widget_dir = __DIR__ . '/widgets';
        if (in_array($class_name, [
            'widget'
        ])){
            @include_once "$widget_dir/class.{$class_name}.php";
            return;
        }
        if (in_array($class_name, array(
            'assiduite',
            'contacts_equipe',
            'horaires',
            'liste_joueurs',
            'pub',
            'sondage',
            'tableau',
            'section_entraineur',
            'stats_joueurs',
            'event_cal',
            'twitter',
            'videos'
        ))) {
            @include_once "$widget_dir/$class_name/class.$class_name.php";
            return;
        }
        if (array_key_exists($class_name, $list = array(
            'gcal_update' => "$widget_dir/horaires/gcal_update.php",
            'gcal_entry' => "$widget_dir/horaires/class.gcal_entry.php",
            'horaires_fncts' => "$widget_dir/horaires/horaires_fncts.php",
            'horaires_importation' => "$widget_dir/horaires/class.horaires_importation.php",
            'widgets' => "$widget_dir/widgets.php",
            'widget' => "$widget_dir/widget.php",
            'config_importation' => "$widget_dir/class.config_importation.php",
            'stats_lehockey_ca' => "$widget_dir/horaires/lehockey_ca_base_.php",
            'XLS_hockey_ca' => "$widget_dir/horaires/XLS_hockey_ca.php",
            'lehockey_ca_matchs_XL' => "$widget_dir/horaires/lehockey_ca_matchs_XL.php",
            'lehockey_ca_classement' => "$widget_dir/horaires/lehockey_ca_classement.php",
            'lehockey_ca_resultats' => "$widget_dir/horaires/lehockey_ca_resultats_.php",
            'publ_sportsV2' => "$widget_dir/horaires/publ_sportsV2.php",
            'publ_sportsV2_saison' => "$widget_dir/horaires/publ_sportsV2_saison.php",
        ))) {
            require_once $list[$class_name];
            return;
        }


        if (strpos($class_name, 'class.') === false) @include_once "class.{$class_name}.php";
        if (class_exists($class_name)) return;
        @include_once "{$class_name}.php";
    }
}
spl_autoload_register('my_autoload');

include_once __DIR__ . '/vendor/autoload.php';



use Underscore\Types\Arrays;
use Underscore\Types\Strings;
require __DIR__ . '/underscore_extend.php';

ini_set('allow_url_fopen', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error_log.html');
ini_set('error_prepend_string', "\n");
$this_dir = __DIR__;


cfg_yml::loadFile("$this_dir/../cfg/config.yml");
//cfg_yml::mergeDefault("$this_dir/cfg/config_default.yml");




$G =& $GLOBALS;


$detect = new Mobile_Detect();
$GLOBALS['is_mobile'] = $detect->isMobile();




if (!$is_windows){
    $root_adr = 'http://' . cfg_yml::get('ckfinder', 'license_name'); //$ini['ckfinder']['license_name'];;
    set_include_path(get_include_path() . ":$real_root/include:$real_root/PHP:$real_root/lang:..:../..:../../..:../../../..:../../../../..:$real_root/ckfinder:$real_root/ZendFramework-1.12.9/library");
} else {
    $root_adr = 'localhost';
    set_include_path(get_include_path() . ';../;../..;../../..;./lang;'. ROOT . 'PHP;' . ROOT . 'include;'. ROOT . 'ckfinder;' . ROOT . 'ZendFramework-1.12.9/library');

}

//$saisons['courante'] = $saisons[1] =cfg_yml::get('saisons', 'courante'); //$ini['saisons']['courante'];
//$saisons['prochaine'] = $saisons[2] = cfg_yml::get('saisons', 'prochaine'); //$ini['saisons']['prochaine'];
//$saisons['inscription'] = $saisons[3] = cfg_yml::get('saisons', 'inscription'); //$ini['saisons']['inscription'];


function low_level_debug($msg){
    //file_put_contents(__DIR__ . '/../logs/debug/debug_info_all_users.txt', "\n\n$msg\n", FILE_APPEND);
}

login_visiteur::clean_up();



if (!isset($S['id_visiteur']) or $S['id_visiteur'] == -1){
    $S['statut'] = 'adulte';
}


function valider_mdp_contenu($no){

    static $valide = array();
    if (isset($valide[$no])) return $valide[$no];
    if (is_null($mdp = cfg_yml::get('mots_de_passe', "mdp_$no"))){
        $valide[$no] = false;
        return false;
    }
    $session_mdp = session::get('mdp_contenu', $no, 'mdp');
    $session_mdp_exp = session::get('mdp_contenu', $no, 'exp');

    if (    is_null($session_mdp) or
            is_null($session_mdp_exp) or
            !is_numeric($session_mdp_exp) or
            time() > $session_mdp_exp or
            $mdp != $session_mdp
            ){
        $valide[$no] = false;
        return false;
    }
    $valide[$no] = true;
    return true;

}


function rien_faire($param){
    return $param;
}

$lang_defaut = cfg_yml::get('langue', 'defaut');

$G['schema'] = 'mro';
define('EMAIL_FORMAT', "#^['_a-z0-9-]+(\.['_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3}|\.aero|\.coop|\.info|\.museum|\.name)$#i");
define('POSTAL_CODE_FORMAT', '#^[a-z]\d[a-z] \d[a-z]\d$#i');
if (!isset($_COOKIE['langue']))
    $_COOKIE['langue'] = $lang_defaut; //$ini['langue']['defaut'];

$langue = $_COOKIE['langue'];
if ($langue != 'fr' and $langue != 'en'){
    $langue = $lang_defaut; //$ini['langue']['defaut'];
}
$autre_langue = ($lang_defaut == $langue? cfg_yml::get('langue', 'autre') : $lang_defaut);

lang::set_lang($langue);


if (strlen(ROOT) == 0){

}

//if (!isset($userdata)) $userdata = array();
date_default_timezone_set('America/Montreal');
function language_file($fname) {
    global $site_root;

    $langue = lang::lang();
    $autre_langue = lang::autre_lang();


    if (file_exists($res = ROOT . "srv/PHP/lang/$langue/$fname")){
        return $res;
    }

    if (file_exists($res = ROOT . "srv/PHP/lang/$autre_langue/$fname")){
        return $res;
    }
    return '';

}


function include_lang($fname, $name_only = false) {
    $match = array();
    $nb = preg_match('#^.+\.([^.]+)$#', $fname, $match);
    if ($nb == 0) die('Fichier mal nommé = ' . $fname);
    $ext = $match[1];
    $res = language_file($fname);
    if ($res)
        if ($ext == 'php' or $name_only){
            return $res;
        }else {
            echo "<script type=\"text/javascript\" src=\"$res\"></script>\n";
        }
    else {
        //debug_print('Pas trouvé: ' . $res);
        if ($ext == 'php'){
            //echo "Missing = $fname";
        } else{
            //echo "<!-- fichier manquant = $fname  -->\n";
        }
    }
}


// recevoir tag et string et retourner <tag> string </tag>
// attributs peuvent être fournis sous forme array(nom=>val, ...);
function wrap($tag, $str, $attr='') {
    $attributes = '';
    if ($attr)
        foreach($attr as $nom=>$val) $attributes .= " $nom=\"$val\"";
    return "<$tag$attributes>\n$str\n</$tag>\n";
}




function debug_on() {
    global $debug;
    $debug->on();

}
debug_off();
function debug_off() {
    global $debug;
    $debug->off();
}
function debug_print($string) {
    global $debug;
    $debug->add($string);
    return $string;
}

function debug_print_once($string){

    global $debug;
    debug_push(1);
    debug_print($string);
    debug_pop();
    return $string;
}

function debug_print_r($string) {
    global $debug;
    $debug->add(print_r($string, true));
    return $string;
}
function debug_dump($val){
    global $debug;
    debug_push(1);
    $debug->dump($val);
    debug_pop();
}
function debug_export($val){
    global $debug;
    debug_push(1);
    $debug->export($val);
    debug_pop();
}
function debug_trace()
{
    ob_start();
    debug_print_backtrace();
    debug_print_once(ob_get_contents());
    ob_end_clean();
}

function debug_clr() {
    global $debug;
    $debug->clr();
}
function debug_push($mode=null) {
    global $debug;
    $debug->push($mode);
}
function debug_pop() {
    global $debug;
    $debug->pop();
}

function std_html_header($version, $path = '') {
    if (!file_exists($fname = ROOT . "include/include_lists/$version.txt")) {
        echo "<!-- introuvable: $fname -->";
        return;
    }
    $text = file_get_contents($fname );

    if ($path) {
        $text = preg_replace(array('#src=(\'|")#m','#href=(\'|")#m' ), "$0$path", $text);
    }
    if (($a = cfg_yml::get('general', 'code_client'))){
        $text = str_replace('/#code_client#/', "/$a/", $text);
    }

    return $text;
}


//debug_on();
//debug_print('=============================================== ---------------------------------------------std_include');
//debug_print('std_include 2: session_id = ' . $_SESSION['id']);

function num_table_fields($table, $row=null) {
// fonction utile pour envoyer des données numériques par JSON.
    if ($table == 'structure2') {
        $num_fields = array(
            'element_structure',
            'id_document',
            'id_contenu',
            'ordre',
            'niveau',
            'marge_haut',
            'marge_bas',
            'marge_gauche',
            'marge_droite',
            'hauteur',
            'largeur_min',
            'largeur_max',
            'hr_haut',
            'hr_bas',
            'centrer',
            'largeur'
        );
        if ($row == null) {
            return $num_fields;
        }
        foreach($row as $ind=>$val) {
            if(in_array($ind, $num_fields)) {
                if(is_numeric($val)){
                    $row[$ind] = (integer) $val;
                } else if ($val == 'null'){
                    $row[$ind] = null;
                }
            }
        }
    }
    return($row);
}

function log_out(){
    login_visiteur::logout();
    return;

}

function defaut_self(&$id_editeur){

    if (!$id_editeur)
        $id_editeur = session::get('id_editeur');
    return $id_editeur;
}




function get_layout_docs($id_document){


    $res = db::query("

        SELECT id_layout
        FROM documents
        WHERE id_document = $id_document

    ", 'acces_table', 'recherche layout');
    if ($res->num_rows == 0){
        fin('document introuvable');
    }
    /**
     * @var int $id_layout
     **/
    extract($res->fetch_assoc());
    if ($id_layout){
        $res = db::query("

            SELECT id_child_document id
            FROM layout_document
            WHERE id_document = $id_document

        ", 'acces_table', "recherche sous-doc");
        $liste = array($id_document);
        if ($res->num_rows){
            while ($row = $res->fetch_assoc()){
                $liste[] = $row['id'];
            }
        }
        if (count($liste)){
            return $liste;
        }
    }
    return $id_document;
}

function is_member(){
    global $S;
    return (isset($S['id_visiteur']) and isset($S['statut']) and is_numeric($S['id_visiteur']) and $S['id_visiteur']>=0);
}


function convQ($str) {
    return(htmlspecialchars($str, ENT_QUOTES, "UTF-8"));
}

function conv($str) {
    return(htmlspecialchars($str, ENT_NOQUOTES, "UTF-8"));
}

function esc_q($str) {
    return(mb_ereg_replace("'", "\'", $str));
}
function esc_q2($str) {
    return(mb_ereg_replace('"', '\"', $str));
}


function num_param($name){
    //$param = $_REQUEST[$name];
    if (!isset($_REQUEST[$name]) or !is_numeric($param = $_REQUEST[$name])) return false;
    return $param;
}
function param($name){
    if (isset($_GET[$name]))
        return $_GET[$name];
    else if (isset($_POST[$name]))
        return $_POST[$name];
    else
        return false;
}

//debug_print('-------------------->'.$_SERVER['SCRIPT_FILENAME']);
//debug_print('-------------------->'.$_SERVER['SCRIPT_NAME']);

///debug_print(substr_count($_SERVER['SCRIPT_NAME'], '/') . ' slashes');
//debug_print('Root = ' . ROOT);
$page_status = 'normal';
function page_status($mode=''){
    global $page_status;
    if (!$mode)
        return $page_status;
    switch($mode){
        case 'brouillon': {
            return $page_status == $mode;
        }
    }
    return false;
}
function set_page_status($mode){
    global $page_status;
    $page_status = $mode;
}

//function valid_login(){
//    global $S;
//    if(!($res = db::query(sprintf('select count(*) as nb from editeurs where id_membre=0%u and mot_passe=%s and id = %s',
//        $S['id_editeur'],
//        GetSQLValueString($S['mdp'], 'text'),
//        GetSQLValueString($S['id'], 'text')
//        )))){
//            return false;
//        }
//    /**
//     * @var int $nb
//     **/
//    if ($res->num_rows){
//        extract($res->fetch_assoc());
//        return $nb > 0 ? true : false;
//    } else
//        return false;
//}

function all_int(&$array){
    foreach($array as &$val) $val = (integer) $val;
}

function assign_str($vals){
    $to_ret = array();
    foreach($vals as $ind=>$val){
        $to_ret[] = $ind . '=' . db::sql_str($val);
    }
    return implode(',', $to_ret);
}

//debug_clr();
function last($arr) {
    if (count($arr) == 0) return null;
    return($arr[count($arr)-1]);
}

function get_($param, $val){
    return (isset($_GET[$param]) and $_GET[$param] == $val);
}
function post_($param, $val){
    return (isset($_POST[$param]) and $_POST[$param] == $val);
}
function get_or_post($str){
    if (isset($_GET[$str])){
        return $_GET[$str];
    } else if (isset($_POST[$str])){
        return $_POST[$str];
    } else
        return null;
}
function column_list($table, $return_if_fail=false){

    $res = db::query("show columns from $table");
    if (!$res) {
        if($return_if_fail){
            return false;
        }else
            fin('acces_table', "show columns from $table");
    }
    $to_ret = array();
    if ($res->num_rows){
        while ($row = $res->fetch_assoc()){
            $to_ret[] = $row['Field'];
        }
    }
    //debug_print(print_r($to_ret, true));
    return $to_ret;
}
function maj(&$str){
    return ($str = ucfirst($str));
}

function compte($tag = 'main'){
    //require_once 'connect.php';
    //global $mysqli;
    $ip = $_SERVER['REMOTE_ADDR'];
    $tag = db::sql_str($tag);

    if (!in_array($ip, array(
        '66.131.105.104',
        '24.201.227.123'
        )

    )
        ){


        $res = db::query("

        SELECT *
        FROM visites
        WHERE compteur = $tag
        FOR UPDATE

        ");
        if (!$res){
            return -1;
        }
        if ($res->num_rows == 0){
            $res = db::query("

            INSERT INTO visites
            SET compteur = $tag, nombre_visites = 0

            ");
            if (!$res){
                return -1;
            }
        }


        if (!($res = db::query("

        SELECT count(*) as nb
        FROM compteurs
        WHERE adresse = '$ip'
        AND
        id_compteur = $tag
        AND
        TIMESTAMPDIFF(minute, heure, now()) < 5


        "))){
            return -1;
        }

        /**
         * @var int $nb
         **/
        extract($res->fetch_assoc());
        if ($nb == 0){
            if (!($res = db::query("

                INSERT INTO compteurs
                SET adresse = '$ip',
                heure = NOW(),
                id_compteur = $tag

            "))){
                return -1;
            }
            $res = db::query("

            UPDATE visites
            SET nombre_visites = nombre_visites + 1
            WHERE compteur = $tag

            ");
            if (!$res){
                return -1;
            }

        }
    }
    $res = db::query("
        
    SELECT nombre_visites
    FROM visites
    WHERE compteur = $tag

    ");
    if (!$res or $res->num_rows == 0){
        return -1;
    }
    /**
     * @var int $nombre_visites
     **/
    extract($res->fetch_assoc());
    return $nombre_visites;
}



//
//function is_officiel($equipe){
//    global $S;
//    //debug_print_once(print_r($S, true));
//    if (!$S['officiel'] or $S['statut'] != 'adulte' or !isset($S['id_visiteur'])){
//        return false;
//    }
//    //debug_print_once('remplit conditions');
//    $res = db::query(("
//
//        SELECT count(*) nb
//        FROM role_equipe
//        WHERE id_equipe = $equipe and id_adulte = {$S['id_visiteur']}
//
//    ") ) or
//    fin('acces_table', "select from role equipe");
//    $row = $res->fetch_assoc();
//    return ($row['nb'] > 0);
//}
//function saison($statut, $lock = false){
//    global $saisons;
//    $id = $saisons[$statut];
//    if (!$lock){
//        return $id;
//    }
//
//
//    $res = db::query("
//
//        SELECT id
//        FROM saisons
//        WHERE id = $id
//        LOCK IN SHARE MODE
//
//    ");
//    if (!$res or $res->num_rows == 0) return false;
//
//
//    return $id;
//
//}
//function saison_desc(){
//
//    $saisons = array($s1 = saisons::courante());
//    if (($s2 = saisons::prochaine()) > 0){
//        $saisons[] = $s2;
//    }
//    $saisons = implode(',', $saisons);
//    $res = db::query("
//
//        SELECT id, nom_saison
//        FROM saisons
//        WHERE id in ($saisons)
//
//    ") or
//      fin('acces_table', "obtenir descriptions saisons");
//
//    if ($res->num_rows == 0){
//        return array();
//    }
//    $to_ret = array();
//    while ($row = $res->fetch_assoc()){
//        if ($row['id'] == $s1){
//            $to_ret[$s1] = $to_ret['courante'] = $row['nom_saison'];
//        } else{
//            $to_ret[$s2] = $to_ret['prochaine'] = $row['nom_saison'];
//        }
//    }
//    return $to_ret;
//}
//function saison_prec($saison){
//
//    $res = db::query("
//
//        SELECT debut
//        FROM saisons
//        WHERE id = $saison
//
//    ");
//    if (!$res or $res->num_rows == 0) return false;
//    extract($res->fetch_assoc());
//    $res = db::query("
//
//        SELECT id
//        FROM saisons
//        WHERE debut < '$debut'
//        ORDER BY debut DESC
//        LIMIT 1
//
//    ");
//    if (!$res or $res->num_rows == 0) return false;
//    extract($res->fetch_assoc());
//    return $id;
//}
//function save_saison($statut, $id=-1){
//    $s = new ConfigMagik('./include/config.ini', true);
//    switch ($statut){
//        case 1:
//            $statut = 'courante';
//            break;
//        case 2:
//            $statut = 'prochaine';
//            break;
//        case 3:
//            $statut = 'inscription';
//            break;
//
//    }
//    $s.set($statut, $id, 'saisons');

//}
//function div_desc($div){
//
//    $div = db::sql_str($div);
//    $res = db::query("
//
//        SELECT description  d
//        FROM rang_niveau
//        WHERE categ = $div
//
//    ");
//    if (!$res){
//        return false;
//    }
//    extract($res->fetch_assoc());
//    return $d;
//
//}

function log_event($categ, $op, $desc){
    event_log::add($categ, $op, $desc);
    return true;
}

function check_exist($list){
    if (!is_array($list)){
        $list = explode(',', $list);
    }
    if (count($_GET)){
        $src =& $_GET;
    } else if (count($_POST)){
        $src =& $_POST;
    } else{
        fin('param_manquant');
    }
    foreach($list as $param){
        if (!isset($src[$param])){
            fin('param_manquant', $param);
        }
    }


}

function add_line(&$to, $data, $col_widths){
    $line = '';

    foreach($data as $titre=>$val){
        $line .= "\n  <td " .(isset($col_widths[$titre])?"width='{$col_widths[$titre]}'":""). ">" . $val . '</td>';
    }
    $to .= "\n<tr valign='top'>$line</tr>";
}

function html_wordwrap($str, $width){
    $str = str_replace('<br/>', "\n", $str);
    $str = wordwrap($str, $width, "\n");
    return str_replace("\n", '<br/>', conv($str));
}

$mysqli_debug = true;
function d(){
    global $mysqli_debug,$S, $is_local;
    if (($S['superhero'] or $is_local) and $mysqli_debug){
        return ' ' . db::get('error');
    } else{
        return '';
    }
}
function if_not($a, $b){
    if (!$a) return $b;
    return $a;
}

function my_array_merge($a, $b){
    $res = array_merge($a, $b);
    foreach($a as $key=>$array){
        if (isset($b[$key]) and is_array($array) and is_array($b[$key])){
            $res[$key] = my_array_merge($array, $b[$key]);
        }
    }
    return $res;
}
//function check_params(){
//    $nb_args = func_num_args();
//    $to_ret = array();
//    $echec = 0;
//
//    for ($i = 0; $i < $nb_args; $i++){
//        $arg = func_get_arg($i);
//        $spec = explode(';', $arg);
//        $param = array_shift($spec);
//        $existe = isset($_REQUEST[$param]);
//        $opt = in_array('opt', $spec);
//        $sql = in_array('sql', $spec);
//        if (!$existe){
//            if ($opt){
//                continue;
//            } else{
//                fin('manque_param', $param  . d());
//            }
//        }
//        $non_vide = in_array('non_vide', $spec);
//
//
//
//        $type = array_shift($spec);
//        $val = $_REQUEST[$param];
//        if ($non_vide and strlen($val) == 0){
//            fin('manque_param', $param . d());
//        }
//        if (strlen($val) or !$opt){
//            switch($type){
//                case 'num':
//                    if (!is_numeric($val)){
//                        $echec = true;
//                    }
//                    break;
//                case 'int':
//                    if (filter_var($val, FILTER_VALIDATE_INT) === false){
//                        $echec = true;
//                    }
//                    break;
//                case 'unsigned':
//                    if (filter_var($val, FILTER_VALIDATE_INT, array('options'=>array('min_range'=>0))) === false){
//                        $echec = true;
//                    }
//                    break;
//                case 'courriel':
//                    if (filter_var($val, FILTER_VALIDATE_EMAIL) === false){
//                        $echec = true;
//                    }
//                    break;
//                case 'bool':
//                    if ($val != '0' and $val != '1'){
//                        $echec = true;
//                    }
//                    break;
//                case 'unsigned_list':
//                    if (!preg_match('#^\d+(,\d+)*$#', $val)){
//                        $echec = true;
//                    }
//                    break;
//                case 'tel':
//                    $fmt_val = preg_replace('#[^\d]#', '', $val);
//                    if (strlen($fmt_val) < 10){
//                        fin('no_tel_trop_court', $val);
//                    }
//                    $val = '(' . substr($fmt_val, 0, 3) . ') ' . substr($fmt_val, 3, 3) . '-' . substr($fmt_val, 6, 4) . (strlen($fmt_val) > 10?' #' . substr($fmt_val,10):'');
//            }
//        }
//        if ($echec){
//            fin('mauvais_param', "$param = $val; type = $type" . d());
//        }
//        $to_ret[$param] = ($sql?db::sql_str($val):$val);
//    }
//    return $to_ret;
//}
function get_nom(){
    global $S;
    if (isset($S['id_editeur']) and $S['id_editeur'] > 0){
        return $S['id_msg'];
    }
    if (isset($S['id_visiteur']) and $S['id_visiteur'] > 0){
        return $S['nom_visiteur'];
    }
    return false;
}

function get_table_style($fmt){
    if (!file_exists($f = ROOT . "css/table_styles/$fmt.ini")){
        return false;
    }
    $style = parse_ini_file($f, true);
    $ret_style = array();
    foreach($style as $ind=>$vals){
        foreach($vals as $ind2=>$val){
            $ret_style[$ind . '_' . $ind2] = preg_replace('#url\(([^)]*)\)#', "./css/table_styles/$fmt/$1", $val);
        }
    }
    return $ret_style;
}


//debug_print_once('OPEN BASEDIR = ' . print_r($a = ini_get_all(),true));
/*
foreach($a as $ind=>$val){
    if ($val['global_value'] == $val['local_value']){
        unset($a[$ind]);
    }
}*/
//debug_print_once('OPEN BASEDIR = ' . print_r($a,true));


//debug_print_once('répertoire courant = ' . __FILE__);
//debug_print_once($is_local?'LOCAL':'remote');


function all_or_none(){
    $nb_nuls = $nb_non_nuls = 0;

    for ($i = 0; $i < func_num_args()-1; $i++){
        if (is_null(func_get_arg($i))){
            $nb_nuls++;
        } else{
            $nb_non_nuls++;
        }
    }
    return ($nb_non_nuls*$nb_nuls == 0);

}

//debug_print_once(print_r($_SESSION,1));
function array_extract($array, $ind){
    if (is_string($ind)){
        $ind = explode(',', $ind);
    }
    $to_ret = array();
    foreach($ind as $index){
        $index = explode(';', $index);
        if (count($index) == 1){
            array_push($index, $index[0]);
        }
        $to_ret[$index[1]] = $array[$index[0]];
    }
    return $to_ret;
}
function array_extract_regex($array, $regex){
    $to_ret = array();
    foreach($array as $ind=>$val){
        if (preg_match($regex, $ind)){
            $to_ret[$ind] = $val;
        }
    }
    return $to_ret;
}
if (!function_exists('erreur_a_exception')){
    function erreur_a_exception($errno, $errstr, $errfile, $errline ) {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

}
if (!function_exists('flatten')){
    function flatten(array $array) {
        $return = array();
        array_walk_recursive($array, function($a) use (&$return) { $return[] = $a; });
        return $return;
    }
}

// file_put_contents(__DIR__ . '/../logs/debug/debug_info_all_users.txt',  "\n--------fin std include", FILE_APPEND);

if (!function_exists('dpar')) {
    function dpar(array $array, $msg = '') {
        debug_print_once($msg . ' ' . print_r($array, 1));
    }
}

