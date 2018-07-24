<?php

class gestion_divisions extends http_json {
	
	static $liste_ordonnee = array();
	static $liste = array();
	static $liste_loaded = false;
	

	function __construct(){
		parent::__construct();
		
		$this->execute_op();
	}
	
	function fn_liste_divisions() {
		$res = db::query("

			SELECT *
			FROM rang_niveau
			WHERE 1
			ORDER BY rang

		",
				'acces_table', "liste divisions");
		self::$data['liste'] = array();
		if ($res->num_rows) {
			while ($row = $res->fetch_assoc()) {
				self::$data['liste'][] = $row;
			}
		}

		$this->succes();
	}


//if (get_('op', 'edite_division') or ($ajout = get_('op', 'ajout_division'))){
	
	
	function fn_ajout_division(){
		$this->fn_edite_division(true);
	}

	function fn_edite_division($ajout = false){
		if (!perm::test('admin')){
			$this->fin('non_autorise');
		}
		extract(self::check_params(
				'description;string;min:1;max:25;rename:desc;sql',
				'categ;string;min:1;max:2;rename:code;sql',
				'id;unsigned;opt'
		));
		if (preg_match('#-#', $code)){
            $this->fin('caractere_non_permis');
        }
		

		if (!$ajout){
			if (!$id){
				$this->fin('mauvais_param','id');
			}
			
			$res = db::query(("

				SELECT count(*) nb
				FROM rang_niveau
				WHERE categ = $code
					AND id <> $id

			"),
					'acces_table', "verification doublon");
			extract($res->fetch_assoc());
			if ($nb){
				$this->fin('doublon');
			}
			$res = db::query(("

				UPDATE rang_niveau
				set categ = $code,
				description = $desc
				WHERE id = $id

			"),
					'acces_table', "update table");
			self::$data = array_merge(self::$data, $_GET);
			$this->succes();

		} else {
			$res = db::query("

				SELECT count(*) nb
				FROM rang_niveau
				WHERE categ = $code
				FOR UPDATE

			",
					'acces_table', "verification doublons");
			extract($res->fetch_assoc());
			if ($nb){
				$this->fin('doublon');
			}
			$res = db::query("

				INSERT INTO rang_niveau
				SET categ = $code,
				description = $desc

			",
					'acces_table', "insertion nouvel enreg.");
			self::$data['id'] = db::get('insert_id');
			self::$data = array_merge(self::$data, $_GET);
			$this->succes();
		}
	}

	function fn_division_set_ordre(){
		$ordre = json_decode($_GET['ordre'], true);
		if (!is_array($ordre) or count($ordre) == 0){
			$this->fin('mauvais_param', 'ordre');
		}
		$nombre_divisions = count($ordre);
		$ordre = array_unique($ordre);
		if (count($ordre) != $nombre_divisions){
			$this->fin('doublons');
		}
		
		$liste = implode(',', $ordre);
		$res = db::query("

			SELECT count(*) nb
			FROM rang_niveau
			WHERE id not in ($liste)


		",
				'acces_table', "compte non inclus");
		extract($res->fetch_assoc());
		if ($nb){
			$this->fin('manque_divisions');
		}
		$res = db::query("

			SELECT id
			FROM rang_niveau
			WHERE 1
			FOR UPDATE

		",
				'acces_table', "lock");
		$res = db::query("
			UPDATE	rang_niveau
			SET rang = null
			WHERE 1
		");
		
		$case = 'case id ';
		foreach($ordre as $ind=>$val){
			$case .= " when $val then $ind";
		}
		$case .= ' end';
		$res = db::query(("

			UPDATE rang_niveau
			SET rang = $case
			WHERE 1

		"),
				'acces_table', "update");
		$this->succes();
	}
	function fn_division_effacer(){
		extract(self::check_params(
			'id;unsigned'
		));
		$res = db::query("

			SELECT id, count(id_equipe) nb
			FROM rang_niveau
			LEFT JOIN  niveaux USING(categ)
			LEFT JOIN equipes USING(niveau)
			WHERE id = $id
			GROUP BY id
			FOR UPDATE

		",
				'acces_table', "decompte");
		if ($res->num_rows == 0){
			$this->fin('introuvable');
		}
		$row = $res->fetch_assoc();
		if ($row['nb'] > 0){
			$this->fin('equipes_existent');
		}
		$res = db::query("

			DELETE
			FROM rang_niveau
			WHERE id = $id
			LIMIT 1

		",
				'acces_table', "delete");
		self::$data['id'] = $id;
		$this->succes();
	}
	
	static function get_selection($val = 'id', $text = 'description'){
		
		if ($val != 'id' and !in_array($val, tables::get_columns('rang_niveau'))){
			$val = 'id';
		}
		if ($text != 'description' and !in_array($text, tables::get_columns('rang_niveau'))){
			$val = 'description';
		}
		
		if ($val == 'id'){
			$val = "concat(id, '_')";
		}
		
		$res = db::query("
			SELECT $val val, $text text
			FROM rang_niveau
			ORDER BY rang
		",
				'acces_table','get selection (divisions)');
		$to_ret = array();
		if ($res->num_rows){
			while ($row = $res->fetch_assoc()){
				$to_ret[$row['val']] = $row['text'];
			}
		}
		return $to_ret;
	}
	static function get_desc($id_ou_categ, $categ = false){
		return self::get_desc_($id_ou_categ, $categ, false);
	}
	static function get_desc_courte($id_ou_categ, $categ = false){
		return self::get_desc_($id_ou_categ, $categ, true);
	}
	
	static function get_desc_($id_ou_categ, $categ = false, $courte = false){
		self::load_divisions();
		$ind = ($categ?'c':'i' ) . $id_ou_categ;
		
		if (array_key_exists($ind, self::$liste)){
			return self::$liste[$ind][$courte?'categ':'description'];
		} 
		return '?';
	}
	static function get_id($categ){
		self::load_divisions();
		if (!array_key_exists("c$categ", self::$liste)){
			return null;
		}
		return self::$liste["c$categ"]['id'];
	}
	static function lock($id, $lock_level = 1){
		if (is_array($id)){
			$cond = 'id in ('. implode($id) . ')';
		} else {
			$cond = 'id = ' . $id;
		}
		$lock = db::lock_statement($lock_level);
		$res = db::query("
			SELECT id
			FROM rang_niveau
			WHERE $cond
			$lock
		",
				'acces_table','verrouillage rang_niveau');
		return $res->num_rows;
	}
	
	static function load_divisions(){
		
		if (self::$liste_loaded){
			return;
		}
		self::$liste_loaded = true;
		
		$res = db::query("
			SELECT id, categ, description
			FROM rang_niveau
			WHERE 1
			ORDER BY rang
		",
				'acces_table','load divisions');
		if ($res->num_rows == 0){
			return;
		}
		while ($row = $res->fetch_assoc()){
			self::$liste_ordonnee[] = $row;
			self::$liste['i' . $row['id']] = $row;
			self::$liste['c' . $row['categ']] = $row;
		}
	}
	static function is_valid_id($id){
	    self::load_divisions();
        return array_key_exists("i$id", self::$liste);
    }
    function fn_get_niveaux_division(){
        extract(self::check_params(
			'categ;string;sql'
		));
        
        $res = db::query("
            SELECT cg.id_groupe_classe id, cg.abrev, cg.description, if(choix.id IS NULL, 0, 1) choix
            FROM classes_groupes cg
            LEFT JOIN 
            (
                SELECT dg.id_categ id, dg.id_groupe_classe
                FROM divisions_groupes dg
                JOIN rang_niveau rn ON dg.id_categ = rn.id
                WHERE rn.categ = $categ
                    ) choix USING(id_groupe_classe)
            ORDER BY cg.ordre
		",
			'acces_table','');
        self::$data['liste'] = db::result_array($res);
        $this->succes();
    }
    function fn_set_niveaux_division(){
        extract(self::check_params(
                'categ;string;min:1',
                'choix;array_unsigned'
		));
        $division = self::get_id($categ);
        if (is_null($division)){
            $this>fin('introuvable');
        }
        if (count($choix) == 0){
            $this->fin('au_moins_un_niveau_pour_div');
        }
        
        $choix_liste = implode(',', $choix);
        // vérifier que les groupes choisis existent toujours
        $res = db::query("
            SELECT COUNT(distinct id_groupe_classe) nb
            FROM classes_groupes
            WHERE id_groupe_classe IN ($choix_liste)
		",
			'acces_table','');
        extract($res->fetch_assoc());
        if ($nb != count($choix)){
            $this->fin('niveau_existe_plus');
        }
        $res = db::query("
            DELETE FROM divisions_groupes
            WHERE id_categ = $division
                AND id_groupe_classe NOT IN ($choix_liste)
		",
			'acces_table','');
        $valeurs = array();
        foreach($choix as $val){
            $valeurs[] = "$division, $val";
        }
        $valeurs = implode('),(', $valeurs);
        
        $res = db::query("
            INSERT IGNORE INTO divisions_groupes
            (id_categ, id_groupe_classe)
            VALUES
            ($valeurs)
		",
			'acces_table','');
        
        // relire données pour affichage
        $res = db::query("
            SELECT group_concat(cg.abrev ORDER BY cg.ordre SEPARATOR ', ') liste
            FROM classes_groupes cg
            JOIN divisions_groupes dg USING(id_groupe_classe)
            WHERE dg.id_categ = $division
            GROUP BY dg.id_categ
		",
			'acces_table','');
        extract($res->fetch_assoc());
        self::$data['liste'] = $liste;
        $this->succes();
    }
    function fn_sauve_valeurs_tableau_structure_checkboxes()
    {
        extract(self::check_params(
			'data;array'
		));
        $categs = array();
        $when_inscr_par_resp = '';
        $when_pas_de_fj = '';
        $when_gestion_adulte  = '';
        $when_pas_de_chandail = '';
        foreach($data as $categ=>$checks){
            $categs[] = $c = db::sql_str($categ);
            if (!is_array($checks)){
                $this->fin('mauvais_param', "checks = non array");
            }
            foreach($checks as $ind=>$check){
                if (!preg_match('#^(0|1)$#', $check)){
                    $this->fin('mauvais_param', "check = $check");
                }
                switch($ind){
                    case 0:
                        $when_inscr_par_resp .= " WHEN $c THEN $check";
                        break;
                    case 1:
                        $when_pas_de_fj .= " WHEN $c THEN $check";
                        break;
                    case 2:
                        $when_gestion_adulte .= " WHEN $c THEN $check";
                    case 3:
                        $when_pas_de_chandail .= " WHEN $c THEN $check";
                }
                
            }
            
        }
        $categs = implode(', ', $categs);
        $res = db::query("
            SELECT count(IF(categ IN ($categs), 1, null)) nb_ok, count(*) nb_tot
            FROM rang_niveau
		",
			'acces_table','');
        extract($res->fetch_assoc());
        if ($nb_ok != $nb_tot){
            $this->fin('liste_divisions_incorrecte');
        }
        $res = db::query("
            UPDATE rang_niveau
            SET inscr_par_responsable = CASE categ
                $when_inscr_par_resp
                ELSE 0
                END,
            pas_de_FJ = CASE categ
                $when_pas_de_fj
                ELSE 0
                END,
            gestion_adulte = CASE categ
                $when_gestion_adulte
                ELSE 0
                END,
            pas_de_chandail = CASE categ
                $when_pas_de_chandail
                ELSE 0
                END
            
            WHERE 1
		",
			'acces_table','');
        
        $this->succes();
    }
    function fn_get_tableau_ages()
    {
        extract(self::check_params(
			'id_saison;unsigned',
            'toutes_divisons;bool;bool_to_num;opt'
		));
        if (!isset($toutes_divisions)){
            $toutes_divisons = true;
        }
        
        self::$data['liste'] = $this->get_tableau_ages($id_saison, $toutes_divisions);
        $this->succes();
    }
    function get_tableau_ages($id_saison, $toutes_divisions = true)
    {
        $join = $toutes_divisons ? 'LEFT JOIN' : 'JOIN';
        $res = db::query("
            SELECT rn.id, rn.categ, rn.description, ifnull(ta.naissance_min, '?') naissance_min, ifnull(ta.naissance_max, '?') naissance_max
            FROM rang_niveau rn
            $join
            tableaux_ages ta ON rn.id = ta.id_division AND ta.saison = $id_saison
            WHERE 1
            ORDER BY rn.rang
		",
			'acces_table','');
        return db::result_array($res);
    }
    function fn_set_tableau_ages_1_categ()
    {
        if (!perm::test('admin')){
            return $this->fin('non_autorise');
        }
        extract(self::check_params(
                'id_saison;unsigned',
                'categ;string;min:1;sql',
                'min;date;sql',
                'max;date;sql'
		));
        if ($min > $max){
            $this->fin('err_ordre_dates');
        }
        $res = db::query("
            SELECT count(*) nb, id id_division
            FROM rang_niveau 
            WHERE categ = $categ
		",
			'acces_table','');
        extract($res->fetch_assoc());
        if ($nb != 1){
            $this->fin('division_inconnue');
        }
        $res = db::query("
            SELECT count(*) nb
            FROM saisons
            WHERE id = $id_saison
		",
			'acces_table','');
        extract($res->fetch_assoc());
        if ($nb != 1){
            $this->fin('saison_inconnue');
        }
        $res = db::query("
            INSERT INTO tableaux_ages
            (id_division, naissance_min, naissance_max, saison)
            VALUES
            ($id_division, $min, $max, $id_saison)
            ON DUPLICATE KEY UPDATE
            naissance_min = $min,
            naissance_max = $max
		",
			'acces_table','');
        $this->succes();
    }
    function fn_ajuste_ages_saison()
    {
        if (!perm::test('admin')){
            return $this->fin('non_autorise');
        }
        extract(self::check_params(
                'id_saison;unsigned',
                'id_saison_source;unsigned',
                'delta;int',
                'get_updated;bool;opt;default:1'
		));
        
        $saisons = array_unique(array($id_saison, $id_saison_source));
        
        if ($delta == 0 and count($saisons) == 1){
            $this->fin('rien_a_faire');
        }
        
        $liste_saisons = implode(',', $saisons);
        $res = db::query("
            SELECT count(*) nb
            FROM saisons
            WHERE id IN ($liste_saisons)
		",
			'acces_table','');
        extract($res->fetch_assoc());
        if ($nb != count($saisons)){
            $this->fin('saisons_inconnues');
        }
        $res = db::query("
            SELECT ta.id_division, ta.naissance_min, ta.naissance_max
            FROM tableaux_ages ta
            WHERE saison = $id_saison_source
		",
			'acces_table','');
        $liste = db::result_array($res);
        $values = array();
        if ($delta){
            foreach($liste as &$val){
                foreach(array('naissance_min', 'naissance_max') as $fld){
                    $date = new DateTime($val[$fld]);
                    $date->modify("$delta years");
                    $val[$fld] = db::sql_str($date->format('Y-m-d'));
                }
                $values[] = "$id_saison,{$val['id_division']},{$val['naissance_min']},{$val['naissance_max']}";
            }
        }
        if (count($values) == 0){
            $this->fin('rien_a_faire');
        }
        $values = implode('),(', $values);
        $res = db::query("
            INSERT INTO tableaux_ages
            (saison, id_division, naissance_min, naissance_max)
            VALUES
            ($values)
            ON DUPLICATE KEY UPDATE
            naissance_min = VALUES(naissance_min),
            naissance_max = VALUES(naissance_max)
		",
			'acces_table','');
        
        if ($get_updated){
            self::$data['liste'] = $this->get_tableau_ages($id_saison, true);
        }
        $this->succes();
    }
    function fn_get_ages_division()
    {
        extract(self::check_params(
                'id_division;unsigned',
                'id_saison;unsigned'
		));
        $res = db::query("
            SELECT naissance_min, naissance_max
            FROM tableaux_ages
            WHERE saison = $id_saison AND id_division = $id_division
                ", 			'acces_table', '');
        if ($res->num_rows == 0){
            $this->fin('verifier_tableau_ages');
        }
        self::$data = $res->fetch_assoc();
        $this->succes();
    }
    
    static function is_resp_categ($categ, $id_visiteur = null)
    {
        if (is_null($id_visiteur)){
            $id_visiteur = session::get('id_visiteur');
        }
        if (is_null($id_visiteur)){
            return false;
        }
        db::sql_str_($categ);
        $res = db::query("
            SELECT count(*) nb
            FROM permissions_niveaux
            WHERE classe IS NULL and controleur > NOW() AND categ = $categ and id_membre = $id_visiteur
		", 			'acces_table', '');
        extract($res->fetch_assoc());
        return $nb > 0;
    }
    static function liste_resp_categ($id_visiteur = null)
    {
        if (is_null($id_visiteur)){
            $id_visiteur = session::get('id_visiteur');
        }
        if (is_null($id_visiteur)){
            return array();
        }
        $res = db::dquery("
            SELECT categ
            FROM permissions_niveaux
            WHERE  classe IS NULL and controleur > NOW() AND id_membre = $id_visiteur
		", 			'acces_table', '');
        return db::result_array_values_one($res);
    }
    static function is_resp_div($id_division, $id_visiteur = null){
        return self::is_resp_categ(self::get_id($id_division), $id_visiteur);
    }
    static function liste_resp_div($id_visiteur = null)
    {
        if (is_null($id_visiteur)){
            $id_visiteur = session::get('id_visiteur');
        }
        if (is_null($id_visiteur)){
            return array();
        }
        $res = db::query("
            SELECT 
            FROM permissions_niveaux pn
            JOIN rang_niveau rn USING(categ)
            WHERE  pn.classe IS NULL and pn.controleur > NOW() AND pn.id_membre = $id_visiteur
		", 			'acces_table', '');
        return db::result_array_values_one($res);
        
    }
}
?>
