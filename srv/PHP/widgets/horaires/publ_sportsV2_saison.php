<?php
require_once(dirname(__FILE__) . '/../../std_include.php');
require_once dirname(__FILE__) . '/class.horaires_importation.php';

//require_once('http.php');
if (!function_exists('convert_utf8')) {
    function convert_utf8($match)
    {
        return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
    }
}

use Phamda\Phamda as P;

class publ_sportsV2_saison extends horaires_importation
{
    private $colonnes = array(
        'groupe' => 0,
        'source' => 0,
        "date" => 'date',    #attention: réglé depuis timestamp
        "debut" => 'debut',    #attention: réglé depuis timestamp
        "equipe1" => 'visitorName',
        "pts1" => 'visitorScore',
        "fj1" => 'visitorFairPlayPTS',
        "equipe2" => 'localName',
        "pts2" => 'localScore',
        "fj2" => 'localFairPlayPTS',
        "lieu" => 'gameLocationName',
        "ref" => 'gameNum'
    );

    public $event_types = array('3');


    public $cfg; // objet contenant groupe
    public $url;
    public $texte;
    public $valid = false;

    private $filename;
    public $err_detail = '';
    private $valid_params = '#^(cfg|url|source)$#i';
    private $contenu;

    public $result_code = '';
    public $sample = '';
    public $nb_sample = 10;

    // si url fourni, le fichier source est lu et stocké dans la classe
    function __construct($params = null)
    {
        parent::__construct();

        $this->h = new http();
        $this->configure($params);

    }

    /*
    function err($res, $msg, $detail=''){
        if (!$res){
            $this->err_msg = $msg;
            $this->detail = $detail;
            $this->log_data .= "\n*************************\nErreur: ".$this->err_msg . ($this->err_detail?"\nDetail:". $this->err_detail:'');
            throw new Exception ($msg);
        }
    }
*/

    function set_cfg($cfg = null)
    {
        if ($cfg) {
            if (is_string($cfg)) {
                $cfg = json_decode($cfg);
                if (is_object($cfg)) {
                    $this->cfg = $cfg;
                    return $cfg;
                } else {
                    return false;
                }
            } else if (is_object($cfg)) {
                $this->cfg = $cfg;
                return $cfg;
            } else
                return false;
        }

    }


    // configurer la classe avec les paramètres stockés pour définir l'importation de données:
    // $cfg  = JSON {"url": url, "groupe":.. }
    function configure($params)
    {
        if (is_string($params)) {
            $params = json_decode($params, true);
        }
        if (is_array($params)) {
            foreach ($params as $param => $data) {
                if ($param == 'cfg' or $param == 'config') {
                    if (is_string($data)) {

                        $this->cfg = json_decode($data);
                    } else if (is_array($data)) {
                        $this->cfg = json_decode(json_encode($data));
                    }
                } else if (preg_match($this->valid_params, $param)) {
                    $this->$param = $data;
                }
            }
        }

        //debug_print('this = ' . print_r($this, true));
        $this->filename = __DIR__ . "/data_versions/last_loaded_data[saison $this->source].txt";
        return true;
    }

    function source_changed()
    {
        $dernier_contenu = @file_get_contents($this->filename);
        if (!$dernier_contenu) {
            return true;
        }
        $this->get_source();
        //$this->contenu = $this->xpath->evaluate($this->contenu_path);
        //debug_on();
        //debug_print('Dernier contenu = ' . $this->contenu);
        //debug_print('Ce contenu = ' . $this->contenu);
        //debug_print(($dernier_contenu != $this->contenu)?'pas égaux':'égaux' );
        if ($dernier_contenu != $this->contenu) {
            $a = $dernier_contenu;
            $b = $this->contenu;
            debug_print_once("Le fichier {$this->filename} a changé.");
            /*
            for($i=0; $i<strlen($a);$i++){
                if ($a[$i] != $b[$i]){
                    debug_print( "Position = $i\nOriginal = ". substr($a, $i, 100) . "\nNouv = " . substr($b, $i, 100));
                    break;
                }
            }

             */

        }
        debug_off();
        return $dernier_contenu != $this->contenu;


    }

    function get_source()
    {
        $this->valid = false;
        debug_print_once("getting data from $this->url");
        if ($this->url) {
            if (!($contenu = file_get_contents($this->url))) {
                return false;
            }

            $contenu = preg_replace(['#jQuery[0-9_]+#', '#"generationTime": *[0-9.]+#'], ['jquery', '"generationTime": 0'], $contenu);

            $this->contenu = $contenu;
            $this->valid = true;
            file_put_contents('dernier_contenu_telecharge.txt', utf8_decode($contenu));
            return true;
        }
        #debug_print_once("contenu = false");
        return false;


    }

    function import_if_changed($force = false)
    {

        if (!$force and !$this->source_changed()) {
            $this->result_code = 'aucun_changement';
            return;
        } else {
            $this->get_source();
        }
        try {
            $this->import(false);
        } catch (Exception $e) {
            $this->result_code = 'erreur';
            return $this->return_err($e);
        }
    }

    function return_err($e)
    {
        if ($this->result_type == 'string') {
            return $e->getMessage() . ' (' . $e->getTraceAsString() . ')';
        } else {
            return array('lignes' => '', 'msg' => $e->getMessage());
        }

    }

    function import_sample($nb = 10)
    {
        try {
            $this->nb_samples = $nb;
            $this->sample = $this->import(true);
            $this->result_code = 'ok';
            return $this->sample;

        } catch (Exception $e) {
            return $this->return_err($e);
        }
    }

    function check_success_data($contenu)
    {
        #debug_print_once(print_r($contenu,1));
        if (!array_key_exists('data', $contenu)) {
            throw new Exception('Ne trouve pas "data" comme élément du contenu');
        }
        if (!array_key_exists('success', $contenu)) {
            throw new Exception('Ne trouve pas "success" comme élément du contenu');
        }
        if (!$contenu['success']) {
            throw new Exception('valeur de "success" = false dans données reçues');
        }
        return $contenu['data'];
    }


    function import($sample_only = false)
    {


       $this->contenu = file_get_contents(dirname(__FILE__) . "/donnees_saison.txt");
//        debug_print_once("-------------- fname = $this->filename");
        $fields = false;
        try {
            if (!$this->contenu) {
                $this->get_source();
            }
            // file_put_contents(dirname(__FILE__) . "/donnees_saison.txt", $this->contenu);
            $nb = preg_match('#\{.*\}#', $this->contenu, $res);
            if (!$nb) {
                throw new Exception('ne trouve pas d\'expression JSON  ' . $this->cfg->groupe);
            }
            $contenu = json_decode($res[0], true);

            if (!is_array($contenu)) {
                throw new Exception('Échec decodage json de contenu publ_sports groupe = V2 saison');
            }


            $contenu = $this->check_success_data($contenu);
            if (!is_array($contenu) or count($contenu) < 2) {
                throw new Exception("Après premier niveau d'extraction, on ne trouve pas une array conforme");
            }
            # batch_data est de l'info fournie dans le premier élément de l'array, info concernant le transfert
            # dans son ensemble
            $batch_data = $this->check_success_data($contenu[0]);
            if (!array_key_exists('info', $batch_data)) {
                throw new Exception("Ne trouve pas info dans batch data");
            }

//            debug_print_once('............................... toto');
            $contenu = $this->check_success_data($contenu[1]);
//            debug_print_once('............................... toto11');

            $events = new data_access_v2_saison($contenu);
//            debug_print_once('............................... toto1111111');
            $events->store_locations();
//            debug_print_once('............................... toto22222');


            debug_print_once(gettype($this->cfg));

            $events->limiter_a_groupes($this->cfg->groupes);


            if (!$events->valid){
                throw new Exception("Type de donnée introuvable dans contenu téléchargé ($events->missing_key)");
            }


            $data_list = array(); # données regroupées par groupe
            $resultats_multi_groupes = array(); # données sur les matchs joués par groupes aussi

            $samples = array(); # liste simple pour échantillon seulement

            $nb_samples = $this->nb_samples;

            $i = 0;
            foreach ($events->get_events() as $timestamp => $liste) {
                foreach($liste as $event){

                    $new_line = $events->get_record($timestamp, $event);

                    if (!$new_line or !$new_line['groupe']){
                        continue;
                    }
                    $i++;

                    if ($sample_only){
                        if (!$new_line['a_replanifier']) {
                            $samples[] = implode(', ', $new_line);
                            if ($nb_samples-- < 0) {
                                break 2;
                            }
                        }
                        continue;
                    }

                    $is_result = !is_null($new_line['pts1']);

                    $groupe = $new_line['groupe'];

                    # formatter pour sql ou non selon echantillon ou non
                    foreach($new_line as $fld=>&$val){
                        $val = db::sql_str($val);
                    }
                    unset($val);



                    if (!$fields) {
                        $fields = array_keys($new_line);
//                        $fields = array();
//                        foreach ($new_line as $fld => $val) {
//                            $fields[] = $fld;
//                        }
                    }

                    $sql_new_line = implode(',', $new_line);

                    if (false and $is_result){
                        $resultats_multi_groupes[] = $sql_new_line;
                    } else {
                        if (!array_key_exists($groupe, $data_list)){
                            $data_list[$groupe] = [];
                        }
                        $data_list[$groupe][] = $sql_new_line;
                    }

                }

            }
            if ($sample_only) {
                $samples = implode('<br/>', $samples);
                return $samples;
            }
            #debug_print_once('sacrament===============================================');
            $this->log_data .= "\n$i lignes traitées";
            //require_once('connect.php');
            $nb_lignes = count($data_list);
            //$data_list = '(' . implode("),\n(", $data_list) . ')';

            db::autocommit(false);

            #debug_print_once("appel de importer_ehl");
            #debug_print_once("data list = $data_list");
            #$this->source = "PS_LIRF";
            foreach($data_list as $groupe=>$liste){
                if (!$liste){
                    debug_print_once("Empty list for group $groupe");
                    continue;
                }
                # debug_print_once('Ajout de ' . count($liste) . ' matchs pour groupe ' . $groupe);

                $this->cfg->groupe = $groupe;
                $sql_liste = '(' . implode('),(', $liste) . ')';
                $this->importer_ehl($fields, $sql_liste, 'horaire');
            }
            if ($resultats_multi_groupes){
                $this->resultats_multi_groupes = true;
                $sql_liste = '(' . implode('),(', $resultats_multi_groupes) . ')';
                # debug_print_once('======= resultats multi-groupes avec ' . count($resultats_multi_groupes));
                $this->importer_ehl($fields, $sql_liste, 'resultats');

            }

//            debug_print_once(print_r(data_access_v2_saison::$ref_equipes_ps, 1));
//            debug_print_once(print_r(data_access_v2_saison::$ref_equipes, 1));
//            debug_print_once(print_r(data_access_v2_saison::$ref_noms, 1));

           // $this->importer_ehl($fields, $data_list, 'horaire;resultats');

            $this->result_code = 'ok';
            #debug_print('contenu = ' . $this->contenu);
            file_put_contents($this->filename, $this->contenu);


            return true;

        } catch (Exception $err) {
            debug_print_once('---------------------');
            debug_print_once(print_r($err, 1));
            if (!$this->err_msg) {
                $this->err_msg = $err->getMessage();
            }
            if (!$this->err_detail) {
                $this->err_detail = $err->getFile() . ' ligne ' . $err->getLine();
            }
            debug_print_once("Erreur = $err");
            $this->result_code = 'erreur';

        }
    }




}



if (!function_exists('sql_str_')) {
    function sql_str_($string)
    {
        return db::sql_str($string);
    }
}

/*

$test = new stats_LSL('{"url":"http://www.hockeylacst-louis.qc.ca/fre/matchs/PeeWee/AF","groupe":"EH-D1-PW-AF"}');
header('Content-Type:text/html; charset=UTF-8');
echo $test->import_sample();
*/

class data_access_v2_saison
{

    public $noms_lieux = array();

    public $eventsInfo;
    public $eventTypes;
    public $gamesInfo;
    public $locationsInfo;
    public $teamsInfo;

    public $categories = array();

    public $valid = true;
    public $missing_key = '';

    private $nb_reports = 0;

    private $groupes_conserves = array();
    private $groupes_ref = [];

    static $ref_equipes_ps = [];
    static $ref_equipes = [];
    static $ref_noms = [];
    static $ref_groupes = [];

    function __construct($contenu)
    {
        foreach(array('eventsInfo', 'gamesInfo', 'eventsTypes','locationsInfo','teamsInfo') as $type){
            if (!array_key_exists($type, $contenu)){
                $this->missing_key = $type;
                $this->valid = false;
                break;
            }
            $this->$type = $contenu[$type];
        }
        # debug_print_once(print_r($this->gamesInfo,1));
        if (!self::$ref_noms) {
            $res = db::query("
            SELECT CONCAT_WS('.', id_division, id_classe, id_nom_std) ref, id_equipe
             FROM equipes_courantes eq
             
            ",
                'acces_table');

            self::$ref_equipes = db::result_array_one_value($res, 'id_equipe', 'ref');

        }
        if (!self::$ref_noms) {
            $res = db::query("
                SELECT id, nom_std
                FROM noms_equipes
                ",
                'acces_table');
            self::$ref_noms = db::result_array_one_value($res, 'id', 'nom_std');
        }
        $res = db::query("
            SELECT DISTINCT CONCAT_WS('-', rn.categ, cl.classe) groupe, rn.id `div`, cl.id cl
            FROM rang_niveau rn
            LEFT JOIN classes cl ON 1
            WHERE 1
            ",
            'acces_table');
        self::$ref_groupes = db::result_array($res, 'groupe');
    }

    function store_locations() {
        if (!$this->locationsInfo) {
            return false;
        }
        $values = P::pipe(
            P::filter(function($v) {return preg_match('#^\d+$#', $v['locationId']);}),
            P::map(function($v){
                $desc = 'proper(' . db::sql_str(preg_replace(['# +,#', '#  +#', '#(^ +)|( +$)#'], [',', ' ', ''], $v['locationName'])) . ')';
                return implode(', ', ["'ps'", $v['locationId'], $desc]);
            }),
            P::implode('), (')
        )($this->locationsInfo);

        $res = db::query("
                INSERT IGNORE INTO importation_locations
                (source, id_source, desc_source)
                VALUES
                ( $values )
                ON DUPLICATE KEY UPDATE
                desc_source = VALUES(desc_source)
            ", 'acces_table');


    }

    function limiter_a_groupes($liste)
    {
        if (is_string($liste)){
            $liste = explode(',', $liste);
        }
        #debug_print_once('liste =   ' . gettype($liste));
        foreach($liste as $groupe){
            $nb = preg_match('#^(.*) *\((\d+)\)$#', strtoupper(trim($groupe)), $matches);

            if ($nb){
                $this->groupes_ref[$matches[2]] = $matches[1];
                $this->groupes_conserves[] = $matches[1];
                continue;
            }

            $this->groupes_conserves[] = strtoupper(trim($groupe));
        }

    }

    function get_events()
    {
        return $this->eventsInfo;
    }
    function lieu($event)
    {
        if (!array_key_exists('locationId', $event)){
            return 'info manquante';
        }
        $id = $event['locationId'];
        if (!$id){
            return 'à déterminer';
        }
        if (array_key_exists($id, $this->noms_lieux)){
            return $this->noms_lieux[$id];
        }
        if (!array_key_exists($id, $this->locationsInfo) or !array_key_exists('locationName', $this->locationsInfo[$id])){
            return 'lieu non reconnu';
        }
        $lieu_long = $this->locationsInfo[$id]['locationName'];
        $lieu_court = preg_replace('# +,#', ',', $lieu_long);
        $this->noms_lieux[$id] = $lieu_court;

        return $lieu_court;

    }

    function get_record($timestamp, $event)
    {

        $ref = $this->ref($event);
        if (!$ref){
            return null;
        }

        $groupe = $this->get_group($event);
        if ($this->groupes_conserves and !in_array($groupe, $this->groupes_conserves)){
            return null;
        }

        list($div, $cl, $eq1, $eq2) = $this->get_div_cl_eq($groupe, $this->nom_visiteur($event), $this->nom_local($event));

        $record = [
            'ref' => $event['gameId'],
            'display_ref' => $ref,
            'date' => ($timestamp < 0 ? '0000-00-00' : date('Y-m-d', $timestamp)),
            'debut' => ($timestamp < 0 ? '00:00' : date('H:i', $timestamp)),
            'lieu' => $this->lieu($event),
            'groupe' => $groupe,
            'equipe1' => $this->nom_visiteur($event),
            'equipe2' => $this->nom_local($event),
            'pts1' => null,
            'fj1' => null,
            'pts2' => null,
            'fj2' => null,
            'a_replanifier' => ($timestamp < 0 ? 1 : 0),
            'div' => $div,
            'cl' => $cl,
            'eq1' => $eq1,
            'eq2' => $eq2,
            'id_lieu_source' => $this->locationId($event)
        ];

        $game = $this->get_game($event);



        if ($game and array_key_exists('gameIsPlayed', $game) and $game['gameIsPlayed'] and $timestamp > 0){
            if (!$this->check_game_info($game)){
                debug_print_once('========================= check_game_info retourne nul ');
                debug_print_once(print_r($record,1));
                return null;
            }
            $record['pts1'] = $game['gameVisitorScore'];
            $record['pts2'] = $game['gameLocalScore'];
            $record['fj1'] = $game['gameVisitorFairPlayPTS'];
            $record['fj2'] = $game['gameLocalFairPlayPTS'];
        }

        return $record;

    }
    function check_game_info($record)
    {
        foreach(array('gameLocalScore', 'gameLocalFairPlayPTS', 'gameVisitorScore', 'gameVisitorFairPlayPTS') as $fld){
            if (!array_key_exists($fld, $record)){
                if (!$this->nb_reports++ < 4){
                    debug_print_once("============== Attention. Trouvé partie avec info manquante ");
                    debug_print_once(print_r($record, 1));
                }
                return null;
            }
        }
        return true;
    }

    function get_game($event)
    {
        $id = $event['gameId'];
        if ($id and array_key_exists($id, $this->gamesInfo)){
            return $this->gamesInfo[$id];
        }
        return null;
    }

    function ref($event)
    {
        if (!array_key_exists('gameId', $event) or !$event['gameId']){
            return null;
        }
        $id = $event['gameId'];
        if (!array_key_exists($id, $this->gamesInfo) or !array_key_exists('gameNum', $this->gamesInfo[$id])){
            return null;
        }
        return $this->gamesInfo[$id]['gameNum'];
    }

    function nom_visiteur($event)
    {
        return $this->nom_equipe($event, 'eventVisitorTeamId');
    }
    function nom_local($event)
    {
        return $this->nom_equipe($event, 'eventLocalTeamId');
    }

    function locationId($event) {
        return A::get_or($event, 'locationId');
    }

    function nom_equipe($event, $type)
    {
        if (!array_key_exists($type, $event) or !$event[$type]){
            return 'non spécifiée';
        }
        if (!array_key_exists($event[$type], $this->teamsInfo)){
            return 'introuvable';
        }
        $team_data = $this->teamsInfo[$event[$type]];
        if (!array_key_exists('name', $team_data)){
            return 'nom non spécifié';
        }
        $nom =  $team_data['name'];

        return $nom;

    }

    function get_group($event)
    {


        $game = $this->get_game($event);
        if (!$game){
            return null;
        }

        if ($this->groupes_ref){
            if (array_key_exists($game['gameCategoryId'], $this->groupes_ref)){
                return $this->groupes_ref[$game['gameCategoryId']];
            }
            return null;
        }

        if (array_key_exists($game['gameCategoryId'], $this->categories)){
            return $this->categories[$game['gameCategoryId']]['groupe'];
        }
        $cat_desc = $game['gameCategoryName'];
        $debut_cat = strtoupper(substr($cat_desc, 0, 2));
        switch ($debut_cat)
        {
            case 'MI':
            case 'MD':
                $cat = 'MD';
                break;
            case 'NO':
                $cat = 'NO';
                break;
            case 'AT':
                $cat = 'AT';
                break;
            case 'BA':
            case 'BT':
                $cat = 'BT';
                break;
            case 'PE':
            case 'PW':
                $cat = 'PW';
                break;
            case 'JU':
            case 'JR':
            case 'JN':
                $cat = 'JR';
                break;
            default:
                debug_print_once("Categ inconnue importation PS: $cat_desc; debut = $debut_cat; game id = {$game['gameId']}");
                return false;
        }

        $id_division = gestion_divisions::get_id($cat);
        if (!$id_division){
            debug_print_once("Categ non reconnue (importation PS): $cat");
            return false;
        }

        $fin_cat = preg_replace('#(^ *pee.?wee|^ *[a-z]+)#i', '', $cat_desc);
        $classe = preg_replace('#[^a-z]#i', '', $fin_cat);
        $sql_classe = db::sql_str($classe);
        /**
         * @var $id_classe
         */
        $res = db::query("
                SELECT id id_classe
                FROM classes
                WHERE classe = $sql_classe
            ", 'acces_table');
        if ($res->num_rows != 1){
            debug_print_once("classe non reconnue importation PS: $classe ($cat_desc)");
            return false;
        }
        extract($res->fetch_assoc());

        $groupe = "$cat-$classe";

        $this->categories[$game['gameCategoryId']] = array(
            'division'=>$id_division,
            'classe' => $id_classe,
            'groupe' => $groupe
        );

        return $groupe;

    }

    function get_div_cl_eq($groupe, $nom1, $nom2) {

        if (array_key_exists($groupe, self::$ref_groupes)) {
            $div = self::$ref_groupes[$groupe]['div'];
            $cl = self::$ref_groupes[$groupe]['cl'];
        } else {
            return [null, null, null, null];
        }



        return [
            $div,
            $cl,
            $this->get_id_equipe($div, $cl, $nom1),
            $this->get_id_equipe($div, $cl, $nom2)
        ];

    }
    function get_id_equipe($div, $cl, $nom) {
        $key = "$div.$cl.$nom";
        if (array_key_exists($key, self::$ref_equipes)) {
            return self::$ref_equipes[$key];
        }

        foreach(self::$ref_noms as $nom_std => $id_eq_std) {

            if (preg_match("#\b$nom_std\b#i", $nom)) {
                $key = "$div.$cl.$id_eq_std";
//                debug_print_once("$nom_std trouvé dans $nom cherche $key");
                if (array_key_exists($key, self::$ref_equipes)) {
//                    debug_print_once('trouvé');
//                    $id_equipe = self::$ref_equipes[$key];
                    self::$ref_equipes_ps["$div.$cl.$nom"] = $id_eq_std;
                    return $id_eq_std;
                } else {
//                    debug_print_once('pas trouvé');
                }
            }

        }
        self::$ref_equipes_ps["$div.$cl.$nom"] = null;
        return null;
    }
}

?>
