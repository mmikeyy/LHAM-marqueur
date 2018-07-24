<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of class
 *
 * @author Michel
 */
class horaires_manuels extends table{




	private $pas_a_trf_ehl = array();
    public $query_result;
    public $raison = '';

	function __construct($no_op = 0, $table='stats_matchs'){
		parent::__construct($table);

		self::set_default_msgs('class_horaires_manuels');

		$this->champs = array(
			'division'		=> 'string;min:1',
			'classe'		=> 'string;min:1',
			'lieu'			=> 'string;max:40',
			'date'			=> 'date',
			'debut'			=> 'time',
			'equipe1'		=> 'string;max:35',
			'equipe2'		=> 'string;max:35',
			'id_equipe1'	=> 'unsigned',
			'id_equipe2'	=> 'unsigned',
			'saison'		=> 'unsigned',
			'pts1'			=> 'unsigned',
			'pts2'			=> 'unsigned',
			'fj1'			=> 'unsigned',
			'fj2'			=> 'unsigned',
			'source'		=> 'string;min:1;max:10',
			'groupe'		=> 'string;min:1;max:10'
		);
		$this->champ_clef = 'id';





		if (!$no_op){
			self::execute_op();
		}
	}
	function fn_get_choix_menu(){

	}
    function update_gcal()
    {
        $gcal = new gcal_update();
        $gcal->update();
    }

//    function fn_ajouter_modifier_match()
//    {
//        /**
//         * @var $division string
//         * @var $classe string
//         * @var $vals array
//         */
//        extract(self::check_params(
//				'division;string;min:1',
//				'classe;string;min:1',
//				'vals;array'
//		));
//		if (!$this->verif_perm_horaire($division, $classe)){
//			$this->fin($this->err_msg);
//		}
//        $res = $this->ajouter_modifier_match($division, $classe, $vals);
//
//        if (is_array($res)){
//            if (in_array('update', $res)){
//                self::$data['texte_colonnes'] = $this->texte_colonnes($res['assign']);
//            }
//            $this->update_gcal();
//            $this->succes();
//        }
//        if ($res === true){
//            $this->update_gcal();
//            $this->succes();
//        }
//        $this->fin('echec');
//
//    }
	/**
     *
     * @param char(2) $division
     * @param char(3) $classe
     * @param type $vals = [id = id d'enregistrement de stats_matchs (fourni seulement pour vérification; optionnel)
     *  saison (num) = id_saison optionnel (defaut = courante)
     *  date : oblig
     *  id_equipe1, id_equipe2 id de eq existante ou 0
     *  nom
     *
     * @return boolean
     */
//	function ajouter_modifier_match($division, $classe, $vals){
//
//		if (array_key_exists('id', $vals)){
//			$id_stats_matchs = $vals['id'];
//			unset($vals['id']);
//		}
//
//		$assign = $this->validate_updates($vals);
//		$assign['groupe'] = "$division-$classe";
//		if (!isset($assign['saison'])){
//			$assign['saison'] = saisons::get('courante');
//		}
//		$assign['source'] = 'MAN';
//
//		if ($vals['date'] < saisons::get_fld_for_id('debut', $assign['saison']) or $vals['date'] > saisons::get_fld_for_id('fin', $assign['saison'])){
//			$this->fin('hors_saison');
//		}
//
//		// vérifier pour chaque eq, si elle est une equipe de la ligue, si elle appartient à la saison
//		foreach(array('equipe1', 'equipe2') as $equipe){
//			$id_eq = $vals["id_$equipe"];
//			$nom = trim($vals[$equipe]);
//			if ($id_eq){
//
//				$res = db::query("
//					SELECT n.categ, n.classe, e.id_saison, e.nom, n.niveau
//					FROM niveaux n
//					JOIN equipes e USING(niveau)
//					WHERE e.id_equipe = $id_eq
//					LOCK IN SHARE MODE
//				",
//						'acces_table','verification equipe');
//				if ($res->num_rows == 0){
//					$this->fin('equipe_inconnue', "no $id_eq = $nom");
//				}
//				$row = $res->fetch_assoc();
//				if ($row['id_saison'] != $assign['saison']){
//					$this->fin('equipe_autre_saison', "no $id_eq = $nom");
//				}
//				if ($row['categ'] != $division or $row['classe'] != $classe){
//					$this->fin('equipe_autre_div_cl', "no $id_eq = $nom; niveau = {$row['niveau']}");
//				}
//				$assign[$equipe] = $row['nom']; // standardiser le nom de l'équipe en fonction de celui trouvé dans db
//			}
//            if (strlen($assign[$equipe]) == 0){
//                $this->fin('nom_equipe_manque');
//            }
//
//
//		}
//
//		// si ref est donné, alors c'est une mise à jour. Vérifier que le match à mettre à jour appartient au même groupe
//		// et provient de meme source
//		if ($vals['ref']){
//			$res = db::query("
//				select source, groupe, saison, id, pts1, pts2
//				FROM stats_matchs
//				WHERE source = 'MAN' and ref = {$vals['ref']}
//				FOR UPDATE
//			",
//				'acces_table', 'recherche match existant');
//			if ($res->num_rows == 0){
//				$this->fin('introuvable', "match ref {$vals['ref']}");
//			}
//			if ($res->num_rows > 1){
//				$this->fin('plus_de_un_match');
//			}
//			$row = $res->fetch_assoc();
//			if ($row['groupe'] != $division . '-' . $classe){
//				$this->fin('mauvais_groupe', $division . '-' . $classe);
//			}
//			if($row['saison'] and $row['saison'] != $assign['saison']){
//				$this->fin('mauvaise_saison', saisons::get_fld('nom_saison', $row['saison']));
//			}
//			if (isset($id_stats_matchs) and $id_stats_matchs != $row['id']){
//				$this->fin('introuvable', 'id ne concordent pas');
//			}
//
//			if (!is_null($row['pts1']) or !is_null($row['pts2'])){
//
//				if (isset($vals['date']) and $vals['date'] > date('Y-m-d')){
//					$this->fin('err_match_futur_avec_pts');
//				}
//			}
//			if ($assign['id_equipe1'] and $assign['id_equipe2'] and $assign['id_equipe1'] == $assign['id_equipe2']){
//                $this->fin('erreur_une_seule_equipe');
//            }
//
//			$this->apply_updates($row['id'], $assign);
//			return array('update', 'assign'=>$assign, 'id'=>$row['id']);
//
//		}
//
//		$id = $this->insert($assign);
//		$res = db::query("
//			UPDATE stats_matchs
//			SET ref = id
//			WHERE id = $id
//		",
//				'acces_table','set ref');
//		$assign['ref'] = $id;
//		unset($assign['id']);
//
//
//        return array('assign'=>$assign, 'id'=>$id);
//
//	}

	function texte_colonnes($data){
		$to_ret = array();
		if ($data['date'] and $data['debut']){
			$to_ret['date_time'] = horaires::format_date($data['date'], $data['debut']);
			$to_ret['date'] = $data['date'];
			$to_ret['debut'] = $data['debut'];
		}
		if ($data['lieu']){
			$lieu = db::sql_str($data['lieu']);
            /**
             * @var $lieu_propre string
             */
            $res = db::query("
				SELECT lieu_propre
				from gcal_lieux
				WHERE lieu_original = $lieu
			",
					'acces_table','recherche lieu');
			if ($res->num_rows == 0){
				$to_ret['lieu'] = $data['lieu'];
			} else {
				extract($res->fetch_assoc());
				$to_ret['lieu'] = $lieu_propre;
			}
		}
		foreach(array('equipe1','equipe2','pts1','pts2','fj1','fj2') as $champ){
			if(array_key_exists($champ, $data)){
				$to_ret[$champ] = $data[$champ];
			}
		}
		return $to_ret;
	}




	function fn_get_equipes(){
        /**
         * @var $division string
         * @var $classe string
         * @var $arenas boolean
         */
        extract(self::check_params(
				'division;string;min:1;sql',
				'classe;string;min:1;sql',
				'arenas;bool'
		));

		if ($arenas){
			$res = db::query("
				SELECT lieu_propre, lieu_original
				FROM gcal_lieux
				ORDER BY lieu_original

			",
					'acces_table','lieux');
			self::$data['choix_arenas'] = db::select_options($res, 'lieu_original','lieu_propre');
		}
		$res = db::query("
			SELECT e.id_equipe,e.nom
			FROM equipes_courantes e
			JOIN niveaux n USING(niveau)
			WHERE n.categ = $division AND n.classe = $classe
			ORDER BY e.nom
		",
				'acces_table','liste equipes');
		self::$data['choix_equipes'] = db::select_options($res, 'id_equipe','nom');
		$this->succes();

	}

	function verif_perm_horaire($division, $classe, $id_membre = null)
    {

		if(perm::test('admin')){
			return true;
		}
		if (is_null($id_membre)){
			$id_membre = session::get('id_visiteur');
		}
		if (!is_numeric($id_membre)){
            $this->err_msg = 'membre non numérique';
			return false;
		}

		$div = db::sql_str($division);
		$cl = db::sql_str($classe);

        /**
         * @var $nb int
         */
        $res = db::query("
			SELECT COUNT(*) nb
			FROM permissions_niveaux
			WHERE id_membre = $id_membre
				AND categ = $div
				AND (classe IS NULL OR classe = $cl)
				AND (horaire IS NOT NULL AND horaire > now() OR controleur IS NOT NULL AND controleur > now())
		",
				'acces_table','verification droit horaire');
		extract($res->fetch_assoc());
        if (!$nb){
            $this->err_msg = 'non_autorise';
        }
		return ($nb > 0);
	}
	function verif_perm_stats($division, $classe, $id_membre = null)
    {
		if(perm::test('admin')){
			return true;
		}

		if (is_null($id_membre)){
			$id_membre = session::get('id_visiteur');
		}
		if (!is_numeric($id_membre)){
			return false;
		}

		$div = db::sql_str($division);
		$cl = db::sql_str($classe);

        /**
         * @var $nb int
         */

        $res = db::query("
			SELECT COUNT(*) nb
			FROM permissions_niveaux
			WHERE id_membre = $id_membre
				AND categ = $div
				AND (classe IS NULL OR classe = $cl)
				AND (resultats IS NOT NULL AND resultats > now() OR controleur IS NOT NULL AND controleur > now())
		",
				'acces_table','verification droit horaire');
		extract($res->fetch_assoc());
		return ($nb > 0);
	}

    function perm_effacer_match($ref, $lock = false)
    {
        if ($lock){
            $lock_statement = 'FOR UPDATE';
        } else {
            $lock_statement = '';
        }

        /**
         * @var $passe boolean
         */

        $res = db::query("
            SELECT COUNT(sj.id) stats_entrees,
                COUNT(f.id) feuille_match_existe,
                IF(sm.pts1 IS NOT NULL OR sm.pts2 IS NOT NULL, 1, 0) resultats_entres,
                IF(CONCAT(sm.date, ' ', sm.debut) < now(), 1, 0) passe,
                IF(CONCAT(sm.date, ' ', sm.debut) BETWEEN NOW() AND ADDDATE(NOW(), 2), 1, 0) futur_proche,
                COUNT(IF(CONCAT(sm.date, ' ', sm.debut) < NOW(), NULL, ma.id_membre)) arbitre_confirme,
                IF(marqueur AND marqueur_confirme AND CONCAT(sm.date, ' ', sm.debut) > NOW(), 1, 0) marqueur_confirme
            FROM stats_matchs sm
            LEFT JOIN stats_joueurs sj ON sm.id = sj.id_match
            LEFT JOIN match_feuille f ON f.id_match = sm.id
            LEFT JOIN match_arbitres ma ON ma.id_match = sm.id AND ma.confirme
            WHERE sm.id = $ref
            GROUP BY sm.id
            $lock_statement
		", 			'acces_table', '');

        if ($res->num_rows == 0){
            $this->query_result = null;
            return null;
        }

        list($division,$classe) = self::get_division_classe_match($ref);

        $this->query_result = $res->fetch_assoc();

        extract($this->query_result);

        if ($passe){
            return $this->verif_controleur_niveau($division,$classe);
        }
        return $this->verif_perm_horaire($division, $classe);
    }

	function verif_controleur_niveau($division, $classe, $id_membre = null){

		if (is_null($id_membre)){
			$id_membre = session::get('id_visiteur');
		}
		if (!is_numeric($id_membre)){
			return false;
		}

		$div = db::sql_str($division);
		$cl = db::sql_str($classe);

        /**
         * @var $nb int
         */

        $res = db::query("
			SELECT count(*) nb
			FROM niveaux
			WHERE categ = $div AND classe = $cl AND horaires_manuels
			LOCK IN SHARE MODE

		",
				'acces_table','verification horaire manuel');
		extract($res->fetch_assoc());
		if (!$nb){
			$this->err_msg = 'horaire_non_manuel';
			return 0;
		}
		if(perm::test('admin')){
			return true;
		}
		$res = db::query("
			SELECT COUNT(*) nb
			FROM permissions_niveaux
			WHERE id_membre = $id_membre
				AND categ = $div
				AND (classe IS NULL OR classe = $cl)
				AND (controleur IS NOT NULL AND controleur > now())
		",
				'acces_table','verification droit horaire');
		extract($res->fetch_assoc());
		return ($nb > 0);
	}

    static function get_division_classe_match($id_match)
    {
        /**
         * @var $division string
         * @var $classe string
         */
        $res = db::query("
            SELECT n.categ division, n.classe, e.id_equipe
            FROM equipes e
            JOIN stats_matchs m ON e.id_equipe = m.id_equipe2 OR e.id_equipe = m.id_equipe1
            JOIN niveaux n on e.niveau = n.niveau

            WHERE m.id = $id_match
            ORDER BY IF(e.id_equipe = m.id_equipe2, 0, 1)
		") or self::erreur('acces_table');

        if ($res->num_rows == 0){
            self::erreur('match introuvable ou équipe locale inconnue');
        }
        extract($res->fetch_assoc());
        return array($division, $classe);
    }

	function fn_effacer_resultats_stats() # attention dupliqué dans marqueur
    {
        /**
         * @var $ref int
         */
        extract(self::check_params(
				'ref;unsigned'

		));

        $match = new record_stats_match($ref);

        if (!$match->load(null, 2)){
            $this->fin('introuvable');
        }

        if (!$match->is_editable_marqueur(true) and !perm::test('admin')){
            $this->non_autorise();
        }


		$res = db::query("
			DELETE stats_joueurs
			FROM stats_joueurs
			JOIN stats_matchs m ON stats_joueurs.id_match = m.id
			WHERE  m.id = $ref
		",
			'acces_table',' delete stats');
		$res = db::query("
			UPDATE stats_matchs m
			SET m.pts1 = null, m.pts2 = null, m.forfait1 = 0, m.forfait2 = 0, m.sj_ok1 = 0, m.sj_ok2 = 0
			WHERE m.id = $ref
		",
			'acces_table','update stats_matchs');

        cache::suppress('stats_joueurs.%');
        cache::suppress('classement.%');

		$this->succes();

	}

    function fn_prep_efface()
    {

        /**
         * @var $ref int
         */
        extract(self::check_params(
                'ref;unsigned'
		));

        if (!$this->perm_effacer_match($ref, true)){
            $this->non_autorise();
        }
        /**
         * @var $futur_proche boolean
         * @var $marqueur_confirme boolean
         * @var $arbitre_confirme boolean
         * @var $passe boolean
         */

        extract($this->query_result);

        if (!($futur_proche and ($marqueur_confirme or $arbitre_confirme)) and !$passe){
            $res = db::query("
                DELETE FROM stats_matchs
                WHERE id = $ref
                LIMIT 1
            ", 			'acces_table', '');
            #db::rollback();
            self::$data['efface'] = 1;
            $this->succes();
        }

        self::$data['verifier'] = $this->query_result;

        $this->succes();

    }


    function fn_proceder_effacement_match()
    {
        /**
         * @var $ref int
         * @var $mdp string
         * @var $confirm boolean
         */
        extract(self::check_params(
                'ref;unsigned',
                'mdp;regex:#^[a-z\d]{32}$#;opt',
                'confirm;regex:#^(0|1)$#;opt',
                'aviser_participants;regex:#^(0|1)$#;opt'
		));
        if (!$this->perm_effacer_match($ref, true)){
            $this->non_autorise();
        }
        $id_visiteur = session::get('id_visiteur');
        if (!$confirm){
            $this->erreur('effacement_non_confirme');
        }


        if ($this->query_result['feuille_match_existe'] != '0' or $this->query_result['stats_entrees'] != '0' or $this->query_result['resultats_entres'] != '0'){
            if (!isset($mdp)){
                $this->erreur('mdp_requis');
            }
            /**
             * @var $mot_passe_db string
             */
            $res = db::query("
                SELECT MD5(CONCAT($ref, mot_passe)) mot_passe_db
                FROM membres
                WHERE id = $id_visiteur
            ", 			'acces_table', '');
            extract($res->fetch_assoc());
            if ($mot_passe_db != $mdp){
                $this->erreur('mauvais_mdp');
            }
        }
        if (isset($aviser_participants) and $aviser_participants){
            $res = db::query("
                SELECT IF(sm.marqueur_confirme, sm.marqueur, 0) marqueur,
                GROUP_CONCAT(DISTINCT ma.id_membre SEPARATOR ',') arbitres,
                GROUP_CONCAT(DISTINCT je1.id_joueur SEPARATOR ',') joueurs1,
                GROUP_CONCAT(DISTINCT je2.id_joueur SEPARATOR ',') joueurs2,
                DAYOFWEEK(sm.date) - 1 jour_semaine,
                DAYOFMONTH(sm.date) date,
                MONTH(sm.date) mois,
                SUBSTR(sm.debut,1,5) heure,
                sm.id ref,
                IFNULL(gcl.lieu_propre, sm.lieu) arena,
                sm.id_equipe1,
                sm.id_equipe2
                FROM stats_matchs sm
                LEFT JOIN match_arbitres ma ON sm.id = ma.id_match AND ma.confirme
                LEFT JOIN joueur_equipe je1 ON je1.id_equipe = sm.id_equipe1
                LEFT JOIN joueur_equipe je2 ON je2.id_equipe = sm.id_equipe2
                LEFT JOIN gcal_lieux gcl ON sm.lieu = gcl.id
                WHERE sm.id = $ref
            ", 			'acces_table', '');
            $liste = $res->fetch_assoc();
            $liste_dest = [];
            foreach(['marqueur','arbitres','joueurs1','joueurs2'] as $ind){

                if (!$liste[$ind]){
                    $liste[$ind] = [];
                    continue;
                }
                $val = $liste[$ind] = explode(',', $liste[$ind]);

                if ($val) {
                    $liste_dest = array_merge($liste_dest,$val) ;
                }
            }
            if (count($liste_dest)){
                $liste_dest_sql = implode(',',array_unique($liste_dest));
                $res = db::query("
                    SELECT DISTINCT courriel, CONCAT(prenom, ' ', nom) nom, id
                    FROM membres
                    WHERE id IN ($liste_dest_sql)
                ",	'acces_table', '');
                if ($res->num_rows == 0){
                    self::$data['msgs'][] = sprintf(self::msg('nb_envois'), 0);
                } else {
                    $ref_membres = db::result_array($res, 'id');

                    $msg = twig::render('message_annulation_match.html.twig', [
                        'ref' => $liste['ref'],
                        'jour' => cal::jour($liste['jour_semaine']),
                        'date' => $liste['date'],
                        'mois' => cal::mois($liste['mois']),
                        'heure' => $liste['heure'],
                        'equipe1' => $nom_equipe1 = equipes::nom_classe($liste['id_equipe1']),
                        'equipe2' => $nom_equipe2 = equipes::nom_classe($liste['id_equipe2']),
                        'arena' => $liste['arena'],
                        'from' => cfg_yml::get('general','courriel_info')
                    ]);

                    $mail = new myPHPMailer();
                    $mail::$erreur = '';
                    $nb_envois = 0;
                    $nb_echecs = 0;
                    foreach($ref_membres as $id=>&$data){
                        $data['envoi'] = false;
                        if (!$data['courriel']){
                            $nb_echecs++;
                            continue;
                        }
                        if ($mail->AddBCC($data['courriel'], $data['nom'])){
                            $nb_envois++;
                            $data['envoi'] = true;
                        } else {
                            $nb_echecs++;
                        }
                    }
                    unset($data);

                    if ($nb_envois){
                        $mail->MsgHTML($msg);

                        if (!$mail->Send()){
                            self::$data['msgs'][]  = sprintf(self::msg('erreur_envoi'), $mail::$erreur);
                        }
                    }
                    self::$data['msgs'][] = sprintf(self::msg('nb_envois'), $nb_envois);
                    if ($nb_echecs){
                        self::$data['msgs'][count(self::$data['msgs'])-1] .= sprintf(self::msg('nb_sautes'), $nb_echecs);
                    }

                    $msg_info = twig::render('message_annulation_match_info.html.twig',[
                        'msg' => $msg,
                        'ref_membres'=> $ref_membres,
                        'nom_equipe1' => $nom_equipe1,
                        'nom_equipe2' => $nom_equipe2,
                        'liste' => $liste,
                        'nb_envois' => $nb_envois
                    ]);
                    $mail = new myPHPMailer();
                    $mail::$erreur = '';

                    if ($mail->AddAddress(cfg_yml::get('general','courriel_info'))){
                        $mail->MsgHTML($msg_info);
                        if (!$mail->Send()){
                            self::$data['msgs'][] = sprintf(self::msg('erreur_envoi_info'), $mail::$erreur);
                        }
                    } else {
                        self::$data['msgs'][] = 'aucun_courriel_info';
                    }


                }
            }

        }

        $this->succes();

    }




	function fn_effacer_match()
    {
        /**
         * @var $ref int
         * @var $stats_aussi boolean
         */
        extract(self::check_params(
				'ref;unsigned',
				'stats_aussi;regex:#^(0|1)$#'
		));
        list($division,$classe) = self::get_division_classe_match($ref);


		// vérifier si autorité pour effacer match
		// doit être resp nivau pour effacer match passé
		$resp_niveau = null;

		if ($stats_aussi){
			if (($resp_niveau = $this->verif_controleur_niveau($division, $classe))){
				$res = db::query("
					DELETE stats_joueurs
					FROM stats_joueurs
					JOIN stats_matchs m
					WHERE m.id = $ref
				",
					'acces_table','recherche id match');

			} else {
				$this->fin('pas_resp_niveau');
			}
		}

        /**
         * @var $passe boolean
         * @var $stats_existent boolean
         * @var $resultats_existent boolean
         *
         */
        $res = db::query("
			SELECT if(CONCAT(m.date, ' ', m.debut) <= now(), 1, 0) passe, COUNT(j.id) stats_existent, COUNT(if(m.pts1 IS NOT NULL OR m.pts2 IS NOT NULL, 1, NULL)) resultats_existent
			from stats_matchs m
			LEFT JOIN stats_joueurs j ON j.id_match = m.id
			WHERE m.id = $ref
			GROUP BY m.id
		",
			'acces_table','verification');

		extract($res->fetch_assoc());
		if ($passe){
			if (is_null($resp_niveau)){
				$resp_niveau = $this->verif_controleur_niveau($division, $classe);
			}

			if ($stats_existent or  $resultats_existent){
				if (!$resp_niveau){
					$this->fin('resp_niveau_pour_effacer_match_avec_resultats_ou_stats');
				}

				if (!$stats_aussi){
					self::$data['stats_existent'] = 1;
					$this->succes();
				}
			}
		}
		if (!$resp_niveau){
			if (!$this->verif_perm_horaire($division, $classe)){
				$this->fin('non_autorise');
			}
		}

		$res = db::query("
			DELETE FROM stats_matchs
			WHERE id = $ref
			LIMIT 1
		",
				'acces_table','delete from stats_matchs');



		$this->succes();

	}
//
//	function fn_get_data_match(){
//		extract(self::check_params(
//				'ref;unsigned'
//
//		));
//
//		$groupe = db::sql_str("$division-$classe");
//
//		$res = db::query("
//			select *
//			FROM stats_matchs
//			WHERE id = $ref
//		",
//				'acces_table','lecture donnees stats_matchs');
//		if ($res->num_rows == 0){
//			$this->fin('introuvable');
//		}
//		$row = $res->fetch_assoc();
//		if ($row['saison'] != saisons::get('courante')){
//			$this->fin('match_autre_saison');
//		}
//		if ($row['source'] != 'MAN'){
//			$this->fin('match_non_editable_manuellement');
//		}
//		unset($row['saison']);
//		unset($row['groupe']);
//		unset($row['source']);
//		$row['debut'] = substr($row['debut'], 0, 5);
//		$row['date'] = substr($row['date'], 0, 10);
//
//		self::$data['vals'] = $row;
//		$this->succes();
//
//	}

	function fn_recharger_tableau()
    {
        /**
         * @var $id_classe int
         * @var $id_equipe int
         * @var $id_division int
         * @var $struct_elem int
         */
        extract(self::check_params(
				'id_equipe;unsigned;opt',
                'id_classe;unsigned;opt',
                'id_division;unsigned;opt',
                'struct_elem;unsigned'
		));
		$contexte = new gen_context();
        if (isset($id_equipe) and $id_equipe){
            $contexte->id_equipe = $id_equipe;
        } else {
            if (isset($id_division) and $id_division){
                $contexte->id_division = $id_division;

                if (isset($id_classe) and $id_classe){
                    $contexte->id_classe = $id_classe;
                }
            }
        }

        /**
         * @var $contexte_widget_params string
         */
        $res = db::query("
            SELECT contexte_widget_params
            FROM structure
            WHERE element_structure = $struct_elem
		", 			'acces_table', '');
        if ($res->num_rows){
            extract($res->fetch_assoc());
            $contexte->params = $contexte_widget_params;
        }

		$horaire = new horaires();
		self::$data['html'] = $horaire->render($contexte);
		$this->succes();
	}


	function fn_get_resultats_match()
    {
        /**
         * @var $ref int
         */
        extract(self::check_params(
				'ref;unsigned'

		));



		$res = db::query("
			SELECT sm.* , if(addtime(concat(substring(sm.date, 1, 11), sm.debut), '01:00:00') > now(), 1, 0) futur, cl1.classe cl1, cl2.classe cl2
			FROM stats_matchs sm
            JOIN equipes eq1 ON sm.id_equipe1 = eq1.id_equipe
            JOIN classes cl1 ON eq1.classe = cl1.id
            JOIN equipes eq2 ON sm.id_equipe2 = eq2.id_equipe
            JOIN classes cl2 ON eq2.classe = cl2.id
			WHERE sm.id = $ref
		");
		if ($res->num_rows == 0){
			$this->fin('introuvable');
		}
		if ($res->num_rows > 1){
			$this->fin('plus_d_un_match');
		}
        $data = $res->fetch_assoc();

        if ($data['cl1'] != $data['cl2']){
            $data['equipe1'] .= " [{$data['cl1']}]";
            $data['equipe2'] .= " [{$data['cl2']}]";
        }
        unset($data['cl1']);
        unset($data['cl2']);
		self::$data['data_match'] = $data;
		self::$data['data_match']['debut'] = substr(self::$data['data_match']['debut'], 0, 5);
		if ($data['futur']){
			$this->fin('match_futur');
		}
		$this->succes();
	}

    function perm_acces_stats_match($ref, $id_equipe)
    {
        if (!perm::est_capitaine_equipe($id_equipe) and !perm::test('admin') and !perm::perm_resultats_eq($id_equipe)){
            $this->non_autorise();
        }

		$res = db::query("
			SELECT * , if(addtime(concat(substring(date, 1, 11), debut), '01:00:00') > now(), 1, 0) futur
			FROM stats_matchs
			WHERE id = $ref
		", 'acces_table');
		if ($res->num_rows == 0){
			$this->fin('introuvable');
		}

		$data = $res->fetch_assoc();

        if ($data['saison'] != saisons::courante()){

            $this->erreur('match_appartient_a_saison_non_courante', $data['saison'] . ' vs ' . saisons::courante());
        }

		if (is_null($data['pts1']) or is_null($data['pts2'])){
			$this->erreur('entrer_resultats_avant');
		}


		if ($data['futur']){
			$this->erreur('match_futur');
		}

        if ($data['id_equipe1'] == $id_equipe){
            $data['pts'] = $data['pts1'];
            $data['autres_pts'] = $data['pts2'];
        } else if ($data['id_equipe2'] == $id_equipe){
            $data['pts'] = $data['pts2'];
            $data['autres_pts'] = $data['pts1'];
        } else {
            $this->erreur('match_concerne_pas_equipe');
        }

        return $data;
    }

	function fn_get_donnees_match_pour_stats()
    {
        /**
         * @var $ref unsigned
         * @var $id_equipe int
         */
        extract(self::check_params(
				'ref;unsigned',
                'id_equipe;unsigned'
		));

        $data = $this->perm_acces_stats_match($ref, $id_equipe);
        debug_print_once(print_r($data,1));

        if ($id_equipe == $data['id_equipe1']){
            $no_equipe = '1';
            $no_autre_equipe = '2';
        } else {
            $no_equipe = '2';
            $no_autre_equipe = '1';
        }

        $autre_equipe = $data["id_equipe$no_autre_equipe"];

        if (perm::est_capitaine_equipe($autre_equipe) or perm::test('admin') or perm::perm_resultats_eq($autre_equipe)){
            self::$data['autre_equipe'] = $autre_equipe;
        } else {
            self::$data['autre_equipe'] = 0;
        }



        self::$data['pts'] = $data['pts'];
        self::$data['autres_pts'] = $data['autres_pts'];

		self::$data['data_match'] = $data;
		self::$data['data_match']['debut'] = substr(self::$data['data_match']['debut'], 0, 5);

        self::$data['buts_propre_filet_adv'] = $data["buts_propre_filet$no_autre_equipe"];
        self::$data['passes_propre_filet_adv'] = $data["passes_propre_filet$no_autre_equipe"];
        self::$data['buts_filet_vide'] = $data["buts_filet_vide$no_equipe"];


		$nom = gestion_membres::prenom_nom_expr('m');
		$saison = saisons::get('courante');

        db::query('SET @a = 0');
        $res = db::query("
            SELECT
                IF(stats.id IS NOT NULL, stats.id, (@a := @a - 1)) id,
                m.id id_membre,
                $nom nom,
                dj.position,
                if(stats.resultat_gardien IS NOT NULL, 1, if(dj.position=2,1,0)) gardien,
                stats.buts,
                stats.buts_contre,
                stats.passes,
                stats.buts_propre_filet,
                stats.passes_propre_filet,
                stats.min_punition,
                abs(stats.resultat_gardien) resultat_gardien,
                if(je.id_joueur IS NULL OR je.id_equipe <> $id_equipe, 1, 0) substitut,
                IF(stats.id IS NULL, 0, 1) present

            FROM membres m
            LEFT JOIN joueur_equipe je ON m.id = je.id_joueur and je.id_equipe = $id_equipe
            LEFT JOIN equipes eq ON je.id_equipe = eq.id_equipe
            LEFT JOIN dossier_joueur dj ON dj.saison = $saison AND dj.id_joueur = m.id AND dj.id_division = eq.division # update dj
            LEFT JOIN stats_joueurs stats ON stats.id_match = $ref AND stats.id_membre = m.id
            WHERE (
                    je.id_equipe = $id_equipe
                        AND
                    !(stats.id_equipe IS NOT NULL AND stats.id_equipe <> $id_equipe)
                  )
                    OR
                  stats.id_equipe = $id_equipe
            ORDER BY gardien, nom
        ",
                'acces_table',"lecture données équipe 1 ($id_equipe)");
        self::$data['stats'] = db::result_array($res);
        self::$data['nom'] = equipes::get_nom($id_equipe);


		$this->succes();

	}
    function fn_get_choix_substituts()
    {
        /**
         * @var $division string
         * @var $classe string
         * @var $ref int
         * @var $exclure_joueurs array
         * @var $exclure_equipes array
         * @var $app string
         */
        extract(self::check_params(
                'division;string;sql',
				'classe;string;sql',
				'ref;unsigned',
                'exclure_joueurs;array_unsigned;opt;default_empty',
                'exclure_equipes;array_unsigned;opt;default_empty',
                'app;regex:#^entree_stats$#'
		));

        $saison = saisons::courante();

        $autres_champs = '';
        switch ($app){
            case 'entree_stats':
                $autres_champs = '';
        }
        if ($autres_champs){
            $autres_champs = ",$autres_champs";
        }

        $sql_exclure_joueurs = '';
        if (count($exclure_joueurs)){
            $sql_exclure_joueurs = 'AND m.id NOT IN (' . implode(',', $exclure_joueurs) . ')';
        }

        $sql_exclure_equipes = '';
        if (count($exclure_equipes)){
            $sql_exclure_equipes = 'AND je.id_equipe NOT IN (' . implode(',', $exclure_equipes) . ')';
        }

        $nom = gestion_membres::nom_prenom_expr('m');
        $res = db::query("
            SELECT DISTINCT m.id,
                $nom nom,
                age(m.date_naissance) age,
                m.courriel $autres_champs,
                IF(dj.position = 2,1,0) gardien,
                je.id_equipe
            FROM membres m
            JOIN dossier_joueur dj ON m.id = dj.id_joueur
            JOIN rang_niveau rn ON rn.id = dj.id_division   # update dj ok pas de changement
            LEFT JOIN joueur_equipe_courant je ON m.id = je.id_joueur
            LEFT JOIN substitut_joueur_classe sjc ON dj.id = sjc.id_dossier

            WHERE dj.saison = $saison
                AND rn.categ = $division
                AND dj.substitut AND (sjc.classe IS NULL OR sjc.classe = $classe)
                $sql_exclure_joueurs
                $sql_exclure_equipes
            ORDER BY nom
		", 			'acces_table', '');

        self::$data['liste'] = db::result_array($res);
        $this->succes();
    }





	function fn_sauvegarder_stats()
    {
        /**
         * @var $id_match int
         * @var $id_equipe int
         * @var $stats array
         * @var $buts_propre_filet_adv int
         * @var $passes_propre_filet_adv int
         * @var $buts_filet_vide int
         */
        extract(self::check_params(
				'id_match;unsigned',
                'id_equipe;unsigned',
				'stats;array',
                'buts_propre_filet_adv;unsigned',
                'passes_propre_filet_adv;unsigned',
                'buts_filet_vide;unsigned'

		));
        $data = $this->perm_acces_stats_match($id_match, $id_equipe);

        if ($id_equipe == $data['id_equipe1']){
            $no_equipe = '1';
            $no_autre_equipe = '2';
        } else {
            $no_equipe = '2';
            $no_autre_equipe = '1';
        }
        $id_autre_equipe = $data["id_equipe$no_autre_equipe"];

		// match doit exister; le verrouiller pour modif

        /**
         * @var $pts int
         * @var $autres_pts int
         * @var $
         */

        $res = db::query("
			SELECT
                sm.pts$no_equipe pts,
                sm.pts$no_autre_equipe autres_pts,

                sm.buts_propre_filet$no_equipe buts_propre_filet,
                sm.passes_propre_filet$no_equipe passes_propre_filet,

                GROUP_CONCAT(sj.id_membre SEPARATOR ',') joueurs_match,
                COUNT(sj.id_membre) nb_joueurs_match,
                GROUP_CONCAT(IF(sj.resultat_gardien IS NOT NULL, sj.id_membre, NULL) SEPARATOR ',') gardiens_match,
                COUNT(sj.resultat_gardien) nb_gardiens_match,
                GROUP_CONCAT(IF(sj.id_equipe <> $id_equipe, sj.id_membre, NULL) SEPARATOR ',') joueurs_adverses,
                IF(sm.id_equipe1 = $id_equipe, 1, 2) no_equipe
			FROM stats_matchs sm
            LEFT JOIN stats_joueurs sj ON sm.id = sj.id_match
			WHERE sm.id = $id_match and $id_equipe in (sm.id_equipe1, sm.id_equipe2)
            GROUP BY sm.id
			FOR UPDATE
		",
				'acces_table','verrouillage stats match');



		if ($res->num_rows == 0){
			$this->fin('introuvable');
		}

		extract($res->fetch_assoc());

		if (is_null($pts) or is_null($autres_pts)){
			$this->fin('entrer_resultats_avant');
		}
        $resultat_gardien = ($pts > $autres_pts) ? 1 : ($pts < $autres_pts ? -1 : 0);





        # valider les stats
        #$verif_unique = new add_unique(self::msg('doublon_joueur'));


        $membres_effaces_ou_mod = array();

        /**
         * @var $mod array
         * @var $ajout array
         * @var $efface array
         */


        http_json::set_source_check_params_once($stats);
        extract(self::check_params(
                'mod;array;opt;default_empty',
                'ajout;array;opt;default_empty',
                'efface;array_unsigned;opt;default_empty'
        ));

        $updates = array();

        $toutes = array_merge($mod, $ajout);

        $membres = array();
        $total_pts = $buts_propre_filet_adv;
        $total_autres_pts = $buts_filet_vide;

        foreach($toutes as $vals){

            http_json::set_source_check_params_once($vals);

            $update_vals = self::check_params(
                    'id_membre;unsigned',
                    'buts;unsigned',
                    'buts_contre;unsigned',
                    'passes;unsigned',
                    'min_punition;unsigned',
                    'gardien;regex:#^(0|1)$#',
                    'buts_propre_filet;unsigned',
                    'passes_propre_filet;unsigned'
            );
            $id_membre = $update_vals['id_membre'];
            $membres[] = $id_membre;

            $updates[] = array(
                $id_match,
                $id_equipe,
                $update_vals['id_membre'],
                $update_vals['buts'],
                $update_vals['passes'],
                $update_vals['min_punition'],
                $update_vals['gardien'] ? $resultat_gardien : 'NULL',
                $update_vals['gardien'] ? $update_vals['buts_contre'] : 0,
                $update_vals['buts_propre_filet'],
                $update_vals['passes_propre_filet'],
                0
            );

            if ($update_vals['gardien']){
                $total_autres_pts += $update_vals['buts_contre'];
            }
            $total_pts += $update_vals['buts'];
        }

        $cond_stats = "sj.id_match = $id_match AND sj.id_equipe = $id_equipe";

        if (count($updates) == 0){

            $this->erreur('utilisez_fnct_effacement');
        }

        if ($total_pts != $pts){
            $this->erreur('pointage_joueurs_differe');
        }
        if ($total_autres_pts != $autres_pts){
            $this->erreur('pointage_gardien_differe');
        }

        foreach($updates as &$update){
            $update = implode(',', $update);
        }
        unset($update);

        $liste_updates = implode($updates, '),(');

        $res = db::query("
            UPDATE stats_joueurs sj
            SET marque = 1
            WHERE $cond_stats
		", 			'acces_table', '');
        $res = db::query("
            INSERT IGNORE INTO stats_joueurs
            (id_match, id_equipe, id_membre, buts, passes, min_punition, resultat_gardien, buts_contre, buts_propre_filet, passes_propre_filet, marque)
            VALUES
            ($liste_updates)
            ON DUPLICATE KEY UPDATE
            buts = VALUES(buts),
            passes = VALUES(passes),
            min_punition = VALUES(min_punition),
            resultat_gardien = VALUES(resultat_gardien),
            buts_contre = VALUES(buts_contre),
            buts_propre_filet = VALUES(buts_propre_filet),
            passes_propre_filet = VALUES(passes_propre_filet),
            marque = 0

		", 			'acces_table', '');
        $res = db::query("
            DELETE sj FROM stats_joueurs sj
            WHERE $cond_stats AND marque
		", 			'acces_table', '');

        $res = db::query("
            SELECT
                SUM(buts_propre_filet) buts_propre_filet_autre_equipe,
                SUM(passes_propre_filet) passes_propre_filet_autre_equipe
            FROM stats_joueurs
            WHERE id_match = $id_match AND id_equipe = $id_autre_equipe
		", 			'acces_table', '');
        extract($res->fetch_assoc());

        $res = db::query("
            UPDATE stats_matchs
            SET
            buts_filet_vide$no_equipe = $buts_filet_vide,
            buts_propre_filet$no_autre_equipe = $buts_propre_filet_adv,
            passes_propre_filet$no_autre_equipe = $passes_propre_filet_adv
            WHERE id = $id_match
		", 			'acces_table', '');

        self::$data['validation_stats'] = $this->valider_stats_joueurs($id_match);
        if (is_null(self::$data['validation_stats'])){
            if ($this->raison){
                self::$data['erreur_validation'] = $this->raison;
            }
        }


        $this->succes();

	}
    function fn_valider_stats_joueurs()
    {
		/**
		 * @var int $id_match
		 */
        extract(self::check_params(
                'id_match;unsigned'
		));
        self::$data['validation_stats'] = $this->valider_stats_joueurs($id_match);
        if (is_null(self::$data['validation_stats'])){
            if ($this->raison){
                self::$data['erreur_validation'] = $this->raison;
            }
        }
        $this->succes();

    }

    function valider_stats_joueurs($id_match)
    {
        /**
         * @var int $id_equipe1
         * @var int $id_equipe2
		 * @var string $nom1
		 * @var string $nom2
         */
        $res = db::query("
            SELECT sm.pts1,
                sm.pts2,
                sm.id_equipe1,
                sm.id_equipe2,
                sm.buts_propre_filet1,
                sm.buts_propre_filet2,
                sm.passes_propre_filet1,
                sm.passes_propre_filet2,
                sm.buts_filet_vide1,
                sm.buts_filet_vide2,
                IF(e1.nom <> e2.nom, e1.nom, CONCAT(e1.nom, ' (', n1.categ, '-', n1.classe, ')')) nom1,
                IF(e1.nom <> e2.nom, e2.nom, CONCAT(e2.nom, ' (', n2.categ, '-', n2.classe, ')')) nom2

            FROM stats_matchs sm
            JOIN equipes e1 ON id_equipe1 = e1.id_equipe
            JOIN niveaux n1 ON e1.niveau = n1.niveau
            JOIN equipes e2 ON id_equipe2 = e2.id_equipe
            JOIN niveaux n2 ON e2.niveau = n2.niveau
            WHERE sm.id = $id_match
            FOR UPDATE

		", 			'acces_table', '');
        if ($res->num_rows == 0){
            $this->raison = 'introuvable';
            return null;
        }
        extract($stats_match = $res->fetch_assoc());

        if (!$id_equipe1 or !$id_equipe2){
            $this->raison = 'manque_equipe';
            return null;
        }


        $equipes = array($id_equipe1, $id_equipe2);

        $res = db::query("
            SELECT
                id_equipe,
                SUM(IFNULL(buts, 0)) buts,
                SUM(IFNULL(buts_contre, 0)) buts_contre,
                SUM(IFNULL(passes, 0)) passes,
                resultat_gardien,
                COUNT(DISTINCT resultat_gardien) nb_resultats_gardien,
                SUM(IFNULL(buts_propre_filet, 0)) buts_propre_filet,
                SUM(IFNULL(passes_propre_filet, 0)) passes_propre_filet
            FROM stats_joueurs
            WHERE id_match = $id_match
            GROUP BY id_equipe
            LOCK IN SHARE MODE

		", 			'acces_table', '');
        $stats_joueurs = db::result_array($res, 'id_equipe');
        $statut = array();
        debug_print_once(print_r($stats_joueurs,1));
        $results_sj = array();
        $updates = array();
        foreach($equipes as $equipe){
            $statut = array();
            $result = 1;
            $no_equipe = (($equipe == $id_equipe1) ? 1 : 2);
            $no_autre_equipe = (($no_equipe == 1) ? 2 : 1);


            if ($equipe == $id_equipe1){
                $autre_equipe = $id_equipe2;
            } else {
                $autre_equipe = $id_equipe1;
            }

            if (!($stats_entrees = array_key_exists($equipe, $stats_joueurs))){
                $statut[] = sprintf(self::msg('equipe_sans_stats_joueurs'), ($no_equipe == 1 ? $nom1 : $nom2) );
                $result = 0;
            } else {
                $stats_autres_entrees = array_key_exists($autre_equipe, $stats_joueurs);

                # vérifier que buts par équipe adverse dans propre filet = somme de tels buts dans stats joueurs adverses
                if ($stats_autres_entrees and ($a = $stats_match["buts_propre_filet$no_autre_equipe"]) != ($b = $stats_joueurs[$autre_equipe]['buts_propre_filet'])){
                    $statut[] = sprintf(self::msg('divergence_buts_propre_filet'), $a, $b );
                    $result = 2;
                }
                # vérifier que marque finale adverse = somme de buts comptés par joueurs + buts accidentels de l'adversaire dans son propre filet
                if (($a = $stats_joueurs[$equipe]['buts']) + ($b = $stats_match["buts_propre_filet$no_autre_equipe"]) != ($c = $stats_match["pts$no_equipe"])){
                    $statut[] = sprintf(self::msg('divergence_marque'), $c, $a) . ($b ? sprintf(self::msg('note_buts_accidentels_adv'), $b) : '');
                    $result = 2;
                }

                # vérifier que somme des buts_contre des gardiens + tirs dans filet vide = marque adverse
                if (($a = $stats_joueurs[$equipe]['buts_contre']) + ($b = $stats_match["buts_filet_vide$no_equipe"]) != ($c = $stats_match["pts$no_autre_equipe"])){
                    if ($b){
                        $statut[] = sprintf(self::msg('divergence_resultat_gardien_incluant_filet_vide'), $a, $b, $c );
                    } else {
                        $statut[] = sprintf(self::msg('divergence_resultat_gardien'), $a, $c );
                    }
                    $result = 2;
                }
                 # vérifier que le nombre de passes n'excède pas le nb de buts FOIS 2
                if (    ($a = $stats_joueurs[$equipe]['passes']) +
                        ($b = (array_key_exists($autre_equipe, $stats_joueurs) ? $stats_joueurs[$autre_equipe]['passes_propre_filet'] : 0))
                        >
                        2 * ($c = $stats_match["pts$no_equipe"])
                        )
                {
                    if ($b){
                        $statut[] = sprintf(self::msg('trop_de_passes_incluant_propre_filet_adv'), $a, $b, $c );
                    } else {
                        $statut[] = sprintf(self::msg('trop_de_passes'), $a, $c );
                    }
                    $result = 2;
                }
            }

            $results_sj[$equipe] = array('result'=>$result, 'statut'=>$statut, 'data_equipe'=>array('nom'=>$stats_match["nom$no_equipe"], 'pts'=>$stats_match["pts$no_equipe"]));
            $updates[] = "sj_ok$no_equipe = $result";
        }
        if (count($updates)){
            $updates = implode(',', $updates);
            $res = db::query("
                UPDATE stats_matchs
                SET $updates
                WHERE id = $id_match
            ", 			'acces_table', '');
        }
        return $results_sj;

    }

    function set_statut_sj($id_match, $id_equipe, $statut = '')
    {
        $statut = db::sql_str($statut);
        $res = db::query("
            UPDATE stats_matchs
            SET statut_sj1 = IF($id_equipe = id_equipe1, $statut, statut_sj1),
                statut_sj2 = IF($id_equipe = id_equipe2, $statut, statut_sj2)
            WHERE id = $id_match
		", 			'acces_table', '');

    }

    function fn_change_locked_status()
    {
        /**
         * @var $id_match int
         * @var $val int
         */
        extract(self::check_params(
                'id_match;unsigned',
                'val;unsigned;max:1'
		));
        if (!perm::test('admin')){
            $this->non_autorise();
        }
        $record = new record_stats_match();
        $record->load($id_match, 2);
        if (!$record->is_found){
            $this->erreur('introuvable');
        }
        if ($val and !$record->is_sj_ok()){
            $this->erreur('stats_joueurs_pas_ok');
        }
        $record->update(['locked' => $val]);
        $this->succes();
    }


	function fn_sauvegarder_resultats()
    {
        /**
         * @var $id_match int
         * @var $pts1 int
         * @var $pts2 int
         */
        extract(self::check_params(
				'id_match;unsigned',
				'pts1;unsigned',
				'pts2;unsigned'
		));

        # d'abord obtenir lock sur match et stats joueurs
        $res = db::query("
            SELECT COUNT(*) nb
            FROM stats_matchs sm
			LEFT JOIN stats_joueurs sj ON sj.id_match = sm.id
			WHERE sm.id = $id_match
			FOR UPDATE
		", 			'acces_table', '');
        extract($res->fetch_assoc());

        debug_print_once('aaaaaaaaaaaaaaa');

        $current_record = new record_stats_match();
        debug_print_once('......................');
        $current_record->load($id_match);

        if (!$current_record->is_found){
            $this->fin('introuvable');
        }
        /**
         * @var $id_equipe1 int
         * @var $id_equipe2 int
         * @var $old_pts1 int
         * @var $old_pts2 int
         * @var $marqueur int
         * @var $locked boolean
         * @var $forfait1 boolean
         * @var $forfait2 boolean
         */
        extract($current_record->select('id_equipe1, id_equipe2, pts1 old_pts1, pts2 old_pts2, marqueur, locked, forfait1, forfait2'));

        if ($locked){
            $this->fin('verrouille');
        }
        if ($forfait1 or $forfait2){
            $this->fin('erreur_resultats_equipe_forfait');
        }




        if (!$current_record->is_editable_marqueur(true)){
            $this->non_autorise();
        }

		// vérifier si un changement de pointage tranforme une victoire en défaite ou en nulle (ou l'inverse) pour une équipe

		if (!is_null($old_pts1) and !is_null($old_pts2)){
			$old_statut1 = ($old_pts1>$old_pts2?1:($old_pts1 == $old_pts2?0:-1));
			$statut1 = ($pts1>$pts2?1:($pts1 == $pts2?0:-1));
			$statut2 = -$statut1;

			if ($old_statut1 != $statut1){
				$resultat_gardien = "CASE id_equipe ";
				if ($id_equipe1){
					$resultat_gardien .= " WHEN $id_equipe1 THEN $statut1 ";
				}
				if ($id_equipe2){
					$resultat_gardien .= " WHEN $id_equipe2 THEN $statut2 ";
				}
				$resultat_gardien .= 'END';
				$res = db::query("
					UPDATE stats_joueurs
					SET resultat_gardien = $resultat_gardien
					WHERE id_match = $id_match AND resultat_gardien IS NOT NULL
				",
						'acces_table','changement resultat gardien');

			}
		}

        $current_record->update(['pts1' => $pts1, 'pts2' => $pts2]);

//		$res = db::query("
//			UPDATE stats_matchs
//			SET	pts1 = $pts1,
//				pts2 = $pts2
//			WHERE id = $id_match
//		",
//				'acces_table','sauvegarde résultats match');

        # vérifier si les statistiques des joueurs sont valides
        $this->valider_stats($id_match, true);

		$this->succes();
	}

    /**
     * @param $id_match INT
     *
     * le match dont les résultats se répercuteront sur
     * les d'autres matchs selon quelle équipe l'a remporté ou perdu
     */


    # set_data = true pour mettre résultats dans self::$data
    function valider_stats($id_match, $set_data = false)
    {
        $update = [];

        $to_ret = true;
        /**
         * @var $forfait1 boolean
         * @var $forfait2 boolean
         * @var $pts1 int
         * @var $pts2 int
         * @var $id_equipe1 int
         * @var $id_equipe2 int
         * @var $buts1 int
         * @var $buts_contre1 int
         * @var $buts_contre2 int
         * @var $nb_stats_1 int
         * @var $nb_stats_2 int
         * @var $buts2 int
         * @var $nb_stats int
         */
        $res = db::query("
            SELECT
            sm.forfait1,
            sm.forfait2,
            sm.pts1,
            sm.pts2,
            sm.id_equipe1,
            sm.id_equipe2,
            SUM(IF(sj.id_equipe = sm.id_equipe1, buts, 0)) buts1,
            SUM(IF(sj.id_equipe = sm.id_equipe1, buts_contre, 0)) buts_contre1,
            COUNT(IF(sj.id_equipe = sm.id_equipe1, 1, NULL)) nb_stats_1,

            sum(IF(sj.id_equipe = sm.id_equipe2, buts, 0)) buts2,
            SUM(IF(sj.id_equipe = sm.id_equipe2, buts_contre, 0)) buts_contre2,
            COUNT(IF(sj.id_equipe = sm.id_equipe2, 1, NULL)) nb_stats_2,

            COUNT(sj.id) nb_stats

            FROM stats_matchs sm
            LEFT JOIN stats_joueurs sj ON sj.id_match = sm.id
            WHERE sm.id = $id_match
            FOR UPDATE
		", 			'acces_table', '');
        extract($res->fetch_assoc());

        if ($forfait1 or $forfait2){
            if ($nb_stats
                    or max($pts1,$pts2) != cfg_yml::get('matchs', 'pts_forfait_gagnant')
                    or min($pts1,$pts2) != cfg_yml::get('matchs', 'pts_forfait_perdant')){
                if ($set_data){
                    self::$data['class_pts1'] = 'stats_joueurs2';
                    self::$data['class_pts2'] = 'stats_joueurs2';
                }
                $to_ret = false;
                $update['sj_ok1'] = 0;
                $update['sj_ok2'] = 0;
            }
        } else {


            if (is_null($pts1) or $nb_stats_1 == 0 and $pts1){
                $update['sj_ok1'] = 0;
            } else if ($buts1 == $pts1 and $buts_contre1 <= $pts2){ # buts_contre n'incluent pas buts avec filet désert
                $update['sj_ok1'] = 1;

            } else {
                $update['sj_ok1'] = 2;

            }



            if (is_null($pts2) or $nb_stats_2 == 0 and $pts2){
                $update['sj_ok2'] = 0;

            } else if ($buts2 == $pts2 and $buts_contre2 <= $pts1){
                $update['sj_ok2'] = 1;

            } else {
                $update['sj_ok2'] = 2;
            }

            if ($update['sj_ok1'] == 1 or $update['sj_ok2'] == 1){
                if ($pts1 > $pts2){
                    $data = "IF(id_equipe = $id_equipe1, 1, -1)";
                } else if ($pts2 < $pts1){
                    $data = "IF(id_equipe = $id_equipe1, -1, 1)";
                } else {
                    $data = 0;
                }
                /**
                 * @var $nb1 int
                 * @var $nb2 int
                 */
                $res = db::query("
                    SELECT COUNT(IF(resultat_gardien <> $data AND id_equipe = $id_equipe1, 1, NULL)) nb1,
                        COUNT(IF(resultat_gardien <> $data AND id_equipe = $id_equipe2, 1, NULL)) nb2
                    FROM stats_joueurs
                    WHERE id_match = $id_match

                ", 			'acces_table', '');
                if ($res->num_rows){
                    extract($res->fetch_assoc());
                    if ($nb1){
                        $update['sj_ok1'] = 2;
                    }
                    if ($nb2){
                        $update['sj_ok2'] = 2;
                    }
                }
            }

        }

        foreach($update as $val){
            if ($val != 1){
                $to_ret = false;
            }
        }

        if ($set_data){
           foreach([1,2] as $ind){
               self::$data["class_pts$ind"] = 'stats_joueurs' . $update["sj_ok$ind"];
           }
        }

        $updates = db::make_assignment($update);

        $res = db::query("
            UPDATE stats_matchs
            SET $updates
            WHERE id = $id_match
		", 			'acces_table', '');

        return $to_ret;
    }
    function fn_retirer_forfait()
    {
        /**
         * @var $id_match int
         */
        extract(self::check_params(
                'id_match;unsigned'
		));
        $record = new record_stats_match($id_match, 2);
        $record->load();
        if (!$record->is_found){
            $this->fin('introuvable');
        }
        if (!$record->is_editable_marqueur(true)){
            $this->non_autorise();
        }
        $record->update(['forfait1' => 0, 'forfait2' => '0']);
        $this->succes();
    }
    function fn_get_choix_marqueurs_arbitres()
    {
        if (!perm::test('admin')){
            $this->non_autorise();
        }
        /**
         * @var $id_match int
         * @var $marqueur boolean
         */
        extract(self::check_params(
            'id_match;unsigned',
            'marqueur;unsigned;max:1'
        ));
        /**
         * @var $lieu string
         * @var $date date
         * @var $debut date
         */
        $res = db::query("
                SELECT lieu, date, debut
                FROM stats_matchs
                WHERE id = $id_match
            ", 'acces_table');
        if ($res->num_rows == 0){
            $this->fin('introuvable');
        }
        extract($res->fetch_assoc());

        $sql_lieu = db::sql_str($lieu);

        $duree = cfg_yml::get('match', 'duree');
        if (!preg_match('#^\d{1,3}$#', $duree)){
            $duree = '90';
        }

        $duree_conflit_meme_lieu = $duree - 5;
        $duree_conflit_autre_lieu = $duree + 45;

        $time_match = "$date $debut";

        $cond_conflit = "
        (
            sm.id <>  $id_match

            AND
            (
                sm.lieu = $sql_lieu
                AND
                CONCAT(sm.date, ' ', sm.debut) BETWEEN
                    SUBDATE('$time_match', INTERVAL $duree_conflit_meme_lieu MINUTE)
                    AND
                    ADDDATE('$time_match', INTERVAL $duree_conflit_meme_lieu MINUTE)
                OR
                sm.lieu <> $sql_lieu
                AND
                CONCAT(sm.date, ' ', sm.debut) BETWEEN
                    SUBDATE('$time_match', INTERVAL $duree_conflit_autre_lieu MINUTE)
                    AND
                    ADDDATE('$time_match', INTERVAL $duree_conflit_autre_lieu MINUTE)
            )

        )
                ";

        $cond_marqueur_arbitre = $marqueur ? 'm.marqueur' : 'm.arbitre';

        if ($marqueur){
            $res = db::query("
                    SELECT marqueur
                    FROM stats_matchs
                    WHERE id = $id_match AND marqueur IS NOT NULL
                ", 'acces_table');
            self::$data['cur_val'] = db::result_array_one_value($res, 'marqueur');

            $res = db::query("
                    SELECT m.id,
                        CONCAT(m.prenom, ' ', m.nom) nom,
                        GROUP_CONCAT(DISTINCT IF($cond_conflit, sm.id, NULL) SEPARATOR ', ') conflits,
                        COUNT(dma.id) dispo_horaire,
                        am.dispo dispo_force,
                        IF(sm.id = $id_match, sm.marqueur_confirme, NULL) confirme

                    FROM membres m
                    LEFT JOIN match_arbitres ma ON m.id = ma.id_membre
                    LEFT JOIN stats_matchs sm ON m.id = sm.marqueur OR ma.id_match = sm.id
                    LEFT JOIN dispo_m_a dma ON m.id = dma.id_membre AND '$time_match' BETWEEN dma.debut AND dma.fin
                    LEFT JOIN applications_marqueurs am ON am.id_match = $id_match AND am.id_membre = m.id
                    WHERE m.marqueur = 1
                    GROUP BY m.id
                    ORDER BY m.nom, m.prenom


                ", 'acces_table');

        } else {
			/**
			 * @var int $nb_arbitres
			 */
			$res = db::query("
			        SELECT nb_arbitres
			        FROM stats_matchs
			        WHERE id = $id_match
			    ", 'acces_table');
			extract($res->fetch_assoc());
			self::$data['nb_arbitres'] = (int) $nb_arbitres;


			$res = db::query("
                    SELECT id_membre
                    FROM match_arbitres
                    WHERE id_match = $id_match AND (confirme IS NULL or confirme)
                ", 'acces_table');
            self::$data['cur_val'] = db::result_array_one_value($res, 'id_membre');

            $res = db::query("
                    SELECT m.id,
                        CONCAT(m.prenom, ' ', m.nom) nom,
                        GROUP_CONCAT(DISTINCT IF($cond_conflit, sm.id, NULL) SEPARATOR ', ') conflits,
                        COUNT(dma.id) dispo_horaire,
                        aa.dispo dispo_force,
                        MAX(IF(ma.id_match = $id_match, ma.confirme, NULL)) confirme

                    FROM membres m
                    LEFT JOIN match_arbitres ma ON m.id = ma.id_membre
                    LEFT JOIN stats_matchs sm ON m.id = sm.marqueur OR ma.id_match = sm.id
                    LEFT JOIN dispo_m_a dma ON m.id = dma.id_membre AND '$time_match' BETWEEN dma.debut AND dma.fin
                    LEFT JOIN applications_arbitres aa ON aa.id_match = $id_match AND aa.id_membre = m.id
                    WHERE m.arbitre = 1
                    GROUP BY m.id
                    ORDER BY m.nom, m.prenom


                ", 'acces_table');

        }
        if ($res->num_rows == 0){
            $this->fin($marqueur ? 'aucun_marqueur_dispo' : 'aucun_arbitre_dispo');
        }
        $liste = ['dispo' => [], 'non_dispo' => []];

        while ($row = $res->fetch_assoc()){
            if ($row['confirme'] or $row['dispo_force'] or ($row['dispo_horaire'] and is_null($row['dispo_force']))){
                $liste['dispo'][] = $row;
            } else {
                $liste['non_dispo'][] = $row;
            }
        }
        self::$data['liste'] = $liste;



        $this->succes();
    }
    function fn_retirer_officiels()
    {
        if(!perm::tests('admin')){
            $this->non_autorise();
        }
        /**
         * @var $id_match int
         * @var $marqueur boolean
         * @var $is_marqueur boolean
         * @var $nb_arbitres unsigned
         */
        extract(self::check_params(
            'id_match;unsigned',
            'marqueur;unsigned;max:1'
        ));

        $res = db::query("
                SELECT sm.marqueur is_marqueur, COUNT(ma.id) nb_arbitres
                FROM stats_matchs sm
                LEFT JOIN match_arbitres ma ON sm.id = ma.id_match
                WHERE id_match = $id_match
                FOR UPDATE
            ", 'acces_table');
        if($res->num_rows == 0){
            $this->fin('introuvable');
        }
        extract($res->fetch_assoc());
        if ($marqueur){
            if ($is_marqueur){
                $res = db::query("
                        UPDATE stats_match
                        SET marqueur = NULL,
                          marqueur_confirme = NULL,
                          marqueur_confirme_par = NULL,
                          marqueur_confirme_date = NULL
                        WHERE id = $id_match
                    ", 'acces_table');

            }
        } else {
            if ($nb_arbitres){
                $res = db::query("
                        DELETE FROM match_arbitres
                        WHERE id_match = $id_match
                    ", 'acces_table');
            }
        }
        $this->succes();
    }
    function fn_get_perms_match()
    {
        if (!login_visiteur::logged_in()){
            $this->non_autorise();
        }
        /**
         * @var $id_match int
         */
        extract(self::check_params(
            'id_match;unsigned'
        ));

        $id_visiteur = session::get('id_visiteur');
        $res = db::query("
                SELECT IF(sm.marqueur = $id_visiteur, 1, 0) marqueur,
                id_equipe1,
                id_equipe2,
                eq1.nom nom1,
                eq2.nom nom2,
                GROUP_CONCAT(re.id_equipe SEPARATOR ',') gerant_equipes,
                TIME_TO_SEC(TIMEDIFF(CONCAT(sm.date, ' ', sm.debut), NOW())) delai,
                sm.locked

                FROM stats_matchs sm
                LEFT JOIN role_equipe re ON re.id_equipe IN (sm.id_equipe1, sm.id_equipe2)
                  AND id_adulte = $id_visiteur
                  AND re.role = 0
                LEFT JOIN equipes eq1 ON sm.id_equipe1 = eq1.id_equipe
                LEFT JOIN equipes eq2 ON sm.id_equipe2 = eq2.id_equipe
                WHERE sm.id = $id_match
            ", 'acces_table');

        if ($res->num_rows == 0){
            $this->fin('introuvable');
        }
        self::$data = $res->fetch_assoc();

        # retourner info si perm horaires ou perm resultats ou controleur existe pour les équipes du match
        # en se fiant au groupe du match, ou à la division + classes associées au tournoi


        if (self::$data['id_equipe1'] and self::$data['id_equipe2']){
            self::$data['perm_horaire'] =
                perm::perm_horaires_eq(self::$data['id_equipe1'])
            and
                perm::perm_horaires_eq(self::$data['id_equipe2']);
            self::$data['perm_resultats'] =
                perm::perm_resultats_eq(self::$data['id_equipe1'])
            and
                perm::perm_resultats_eq(self::$data['id_equipe2']);
        } else if (self::$data['id_equipe1']){
            self::$data['perm_horaire'] = perm::perm_horaires_eq(self::$data['id_equipe1']);
            self::$data['perm_resultats'] = perm::perm_resultats_eq(self::$data['id_equipe1']);
        } else if (self::$data['id_equipe2']){
            self::$data['perm_horaire'] = perm::perm_horaires_eq(self::$data['id_equipe2']);
            self::$data['perm_resultats'] = perm::perm_resultats_eq(self::$data['id_equipe2']);
        }

        $this->succes();
    }

	function fn_get_charge_travail()
	{

		$saison = saisons::courante();

		$res = db::query("
    		SELECT a.id_membre,
    			a.nom,
    			SUM(a.nb_m) nb_m,
    			SUM(a.nb_m_futurs) nb_m_futurs,
    			SUM(a.nb_a) nb_a,
    			SUM(a.nb_a_futurs) nb_a_futurs,
    			SUM(a.nb_m + a.nb_a) nb_tot,
    			SUM(a.nb_m_futurs + a.nb_a_futurs) nb_tot_futurs
    		FROM (
		        SELECT ma.id_membre, CONCAT(m.nom, ', ', m.prenom) nom, COUNT(*) nb_a, COUNT(IF(sm.date > CURDATE(), 1, null)) nb_a_futurs, 0 nb_m, 0 nb_m_futurs
		        FROM  stats_matchs sm
		        JOIN match_arbitres ma ON ma.id_match = sm.id
		        JOIN membres m ON ma.id_membre = m.id
		        WHERE ma.confirme IS NULL OR ma.confirme AND sm.saison = $saison
		        GROUP BY ma.id_membre

		        UNION ALL

		        SELECT sm.marqueur, CONCAT(m.nom, ', ', m.prenom) nom, COUNT(*) nb_m, COUNT(IF(sm.date > CURDATE(), 1, NULL)) nb_m_futurs, 0 nb_a, 0 nb_a_futurs
		        FROM stats_matchs sm
		        JOIN membres m ON sm.marqueur = m.id
		        WHERE sm.saison = $saison
		        GROUP BY sm.marqueur
				) a
			GROUP BY id_membre
			ORDER BY nb_m + nb_a DESC, nom
		    ", 'acces_table');

		self::$data['liste'] = db::result_array($res);
		$this->succes();
	}

}



class add_unique
{
    public $array;
    public $msg;
    function __construct($msg = 'doublon non permis')
    {
        $this->set_msg($msg);
    }
    function set_msg($msg){
        $this->msg = $msg;
    }
    function add_unique($val)
    {
        if (in_array($val, $this->array)){
            http_json::erreur($this->msg);
        }
        $this->array[] = $val;
    }
}

