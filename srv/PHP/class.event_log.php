<?php

use Phamda\Phamda as P;

class event_log extends http_json {
    function __construct($no_op = false)
    {
        parent::__construct();

        self::verif_admin();

        if (!$no_op) {
            self::execute_op();
        }
    }

    static $buffer = array();
	
	static function add_commit($categ,$op,$desc, $id = 0){
		self::add($categ,$op,$desc, $id);
		self::commit();
	}
	
	static function commit(){
		
		if (!count(self::$buffer)){
			//debug_print_once("event buffer vide");
			return;
		}
		$values = implode(',', self::$buffer);
		
		$res = db::query("
			INSERT INTO event_log
			(date,categ,op,description,id_user,nom)
			VALUES
			$values

	") ;
		db::commit();
	}
	static function add($categ,$op,$desc = '',$id = 0){
		
		if ($id == 0){
			$id = session::get('id_visiteur');
		}
		if (!is_numeric($id) or $id < 0){
			$id = 0;
		}
		$nom_visiteur = db::sql_str(session::get('nom_visiteur'));
		
		$categ = db::sql_str($categ);
		$op = db::sql_str($op);
		$desc = db::sql_str($desc);
		$now = date('Y-m-d H:i:s');
		self::$buffer[] = "('$now',$categ,$op,$desc,$id,$nom_visiteur)";
	}


	function fn_obtain_entries() {
        /**
         * @var int $after_id
         * @var int $nb
         * 0, 0 => envoyer 100;
         * id, 0 => id est le premier chargé; envoyer les plus nouveaux s'il y en a
         **/
        extract(self::check_params(
            'after_id;unsigned',
            'nb;unsigned'
        ));

        if ($after_id) {
            /**
             * @var string $date
             **/
            $res = db::query("
                SELECT date
                FROM event_log
                WHERE id = $after_id
            ", 'acces_table');
            $this->verif_trouve($res);
            extract($res->fetch_assoc());

            $sql_date = db::sql_str($date);
            if ($nb) {
                $cond = "ev.date <= $sql_date";
            } else {
                $cond = "ev.date >= $sql_date";
            }


        } else {
            $cond = 1;
        }
        $limit = '';
        if ($nb) {
            $inc_nb = $nb + 1;
            $limit = "LIMIT $inc_nb";
        } else {
            if (!$after_id) {
                $limit = "LIMIT 100";
            }
        }

        $cond_superhero = (perm::test('superhero') ? '1' : 'NOT ed.superhero');

        $res = db::dquery("
                    SELECT ev.*, dhm(ev.date) date
                    FROM event_log ev
                    LEFT JOIN editeurs ed ON ev.id_user = ed.id_membre
                    WHERE ($cond) AND $cond_superhero
                    ORDER BY ev.`date` desc
                    $limit
                ", 'acces_table');

        $liste = db::result_array($res);

        if ($after_id) {
            if ($nb) { // si on demande ceux apres id (puisque $nb > 0)
                $pos = P::findIndex(function($v) use ($after_id){return $v['id'] == $after_id;}, $liste);
                if (!is_null($pos)) {
                    array_slice($liste, $pos);
                }
            } else {
                $pos = P::findIndex(function($v) use ($after_id){return $v['id'] == $after_id;}, $liste);
                if (!is_null($pos)) {
                    array_splice($liste, $pos);
                }
            }

        }

        self::set_data('liste', $liste);

        $this->succes();

    }
}

