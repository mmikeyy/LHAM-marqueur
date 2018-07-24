<?php


class gestion_envoi_codes_validation {
	private $table = 'envois_codes_validation';
	private $max_envois_par_raison = array(
		'total'=>5,
		'transfert'=>2,
		'obtenir_mdp'=>2,
		'nouveau_courriel'=>2
	);
	private $courriel;
	private $sql_courriel;
	private $raison;
	private $sql_raison;
	private $max_envois_par_id = 5;
	
	static $msg;

	public $exceptions = 1;

	public $email;
	
	static $nom_ligue;
	
	function __construct($courriel, $raison = 'obtenir_mdp'){
		
		$this->set_courriel($courriel);
		$this->set_raison($raison);
		
		self::$msg = new msg('class_gestion_envoi_codes_validation', $this);
		
	}
	
	function set_courriel($courriel){
		$this->courriel = $courriel;
		$this->sql_courriel = db::sql_str($this->courriel);
	}
	function set_raison($raison){
		if (!array_key_exists($raison, $this->max_envois_par_raison)){
			throw new Exception("mauvaise raison: $raison");
		}
		$this->raison = $raison;
		$this->sql_raison = db::sql_str($this->raison);
	}
	
	function purge_vieux(){
		$res = db::query("
			DELETE FROM {$this->table}
			WHERE heure < date_sub(now(),INTERVAL 1 day)
		");
		if (!$res){
			return http_json::conditional_fin('acces_table','purge vieux');
		}
        return null;
	}
	
	function fin($index, $info = null){
		return self::$msg->fin($index,$info);

	}
	
	function ajout_possible($id=0){
        
		$table = $this->table;
		
		$this->purge_vieux();

        /**
         * @var int $nb
         * @var int $nb_raison
         **/
		$res = db::query("
			SELECT count(*) nb, count(if(raison = $this->sql_raison,1,null)) nb_raison
			from $table
			WHERE courriel = $this->sql_courriel 
		");
		
		if (!$res){
			return http_json::conditional_fin('acces_table', 'décompte envois à courriel');
		}
		
	
		extract($res->fetch_assoc());
		if ($nb > $this->max_envois_par_raison['total'] or $nb_raison > $this->max_envois_par_raison[$this->raison]){
			//debug_print_once("limite de $nb > {$this->max_envois['total']} ou $nb_raison > {$this->max_envois[$this->raison]}" );
			return $this->fin('limite_atteinte');
		}
		if ($id){
			$res = db::query("
				SELECT count(*) nb
				FROM $table
				WHERE id = $id
			");
			if (!$res){
				return http_json::conditional_fin('acces_table', 'décompte envois à idl');
			}
			extract($res->fetch_assoc());
			if ($nb > $this->max_envois_par_id){
				return $this->fin('limite_atteinte');
			}
			
		}
		return true;
	}
	function ajout_envoi($id = 0){
		
		$res = db::query("
			INSERT INTO envois_codes_validation
			SET courriel = $this->sql_courriel, id = $id, heure = now(), raison = $this->sql_raison
		");
		if (!$res){
			return http_json::conditional_fin('acces_table','insertion envoi');
		}
		return $this;
	}
	
	function preparer_envoi(){
		$this->email =  new myPHPMailer();
		$this->email->set_origin('ne_pas_repondre');
		if (!self::$nom_ligue){
			//self::$nom_ligue = cfg::get('titre_court', 'general');
			self::$nom_ligue = cfg_yml::get('general', 'titre_court');
		}
		$nom_ligue = self::$nom_ligue;
		$this->email->Subject   = "Votre code de validation pour $nom_ligue / Your $nom_ligue validation code";
		}
	function ajouter_destinataire($val = null, $nouveau_courriel = 0){
		if (!$this->email){
			$this->preparer_envoi();
		}
		$champ_courriel = ($nouveau_courriel?'nouveau_courriel':'courriel');
		$this->email->AddBCC('micrang@videotron.ca');
		
		if (is_numeric($val)){
			$vals = gestion_membres::get($val, "$champ_courriel courriel,prenom,nom");
			if (count($vals) == 0){
				return $this->fin('destinataire_introuvable');
			}
			if (filter_var($vals['courriel'], FILTER_VALIDATE_EMAIL) === false){
				return $this->fin('adresse_invalide');
			}

			if (!$this->email->AddAddress($vals['courriel'], "{$vals['prenom']} {$vals['nom']}")){
				return $this->fin('echec_ajout_dest', '->' . $vals['courriel']);
			}
			return $this;
			
		}
		if (!$val){
			$val = $this->courriel;
		}
		
		
		if (!$this->email->AddAddress($val)){
			return $this->fin('echec_ajout_dest', '= ' . $val);
		}
		return $this;

	}
	
	/*
	 * fournir $id = un seul id_membre ou un array
	 */
	function msg_valider_adr_existante($id){
		if (!$this->email){
			$this->preparer_envoi();
		}
		if (!is_array($id)){
			$id = array($id);
		}
		$data = gestion_membres::get($id, "concat(prenom,' ',nom) nom,code_validation code" );
		if (count($data) == 0){
			$this->fin('destinataire_introuvable');
		}
		if (count($data) > 1){
			$msg = "
	<p>Plusieurs personnes sont présentes dans nos dossiers avec ce courriel.  Les codes de validation apparaissant au bas du présent message ont été assignés, qui permettront à n'importe laquelle de ces personnes de valider son identité indépendamment des autres..</p>

	<p>Notez que l'utilisation d'un de ces codes invalide les autres. Vous devez donc demander une nouvelle série de codes s'il vous faut faire reconnaître une autre personne apparaissant dans cette liste.</p>

	<p>Pour être reconnu sur le site, retournez à l'endroit d'où vous avez déclenché l'envoi du présent courriel. Entrez un des codes fournis ici de même que l'adresse de courriel, puis cliquez sur 'Valider'. Le site acceptera ensuite l'identifiant et le mot de passe que vous entrerez, que vous serviront à vous identifier par la suite.</p>

	<p>Merci et bonne visite!</p>
	<p>---------------------</p>

	<p>More than one person are on file with this email address. Different validation codes were assigned to these persons below, allowing any of them to validate his/her identity independently from the others.</p>

	<p>Note that using one of these codes invalidates the others. You must therefore ask for a new series of codes to register another user appearing in this list.</p>

	<p>To be recognized as a member, return to the place from where you triggered the sending of this email. Enter the email address as well as one of the codes below, and click 'Validate'. You will then be allowed to provide a new id and a new password, which you will use thereafter to identify yourself.</p>

	<p>Thank you and enjoy your visit!</p>

	<p>CODES DE VALIDATION / VALIDATION CODES:</p>

	";
			foreach($data as $val){
				$msg .= sprintf("%s  code = %u\n" , $val['nom'],  $val['code']);
			}
		} else{
			$nb = $data[0]['code'];
			$msg = "(english follows)
	<p>Votre code de validation est $nb.</p>

	<p>Une fois ce code validé, vous pourrez vous inscrire avec un nouveau code d'utilisateur et un nouveau mot de passe. Procédez comme suit:</p>

	<p>1) Retournez à l'endroit d'où vous avez déclenché l'envoi du présent courriel. </p>
	<p>2) Entrez le code ci-dessus de même que l'adresse de courriel, puis cliquez à l'endroit indiqué pour valider votre adresse. </p>
	<p>3) Le site acceptera ensuite le code d'utilisateur et le mot de passe que vous entrerez, que vous serviront à vous identifier à chaque visite par la suite.</p>

	<p>Bonne visite!</p>

	<p>-------------</p>

	<p>Your validation code is $nb.</p>

	<p>Once this code is validated, you can register with a new id and a new password. Proceed as follows:</p>

	<p>1) return to the place where you clicked to trigger the sending of this email.</p>
	<p>2) Enter the email address as well as the code above, and click 'Validate'.</p>
	<p>3) You will then be allowed to provide a new id and a new password, which you will use thereafter to identify yourself.</p>

	<p>See you soon!</p>
	";
		}
	
		$this->email->MsgHTML($msg);
		
		return $this;
	}
	function msg_valider_nouvelle_adresse($id, $code = 0){
		if (!$this->email){
			$this->preparer_envoi();
		}
		if (!$code){
			$res = db::query("
				select code_validation code, nouveau_courriel
				FROM membres
				WHERE id = $id AND code_pour_nouveau_courriel
			") or $this->fin('acces_table','recherche code validation');
			if ($res->num_rows == 0){
				$this->fin('introuvable');
			}
			extract($res->fetch_assoc());
		}
		$msg = sprintf(self::$msg->get('msg_valider_nouvelle_adresse'), $nouveau_courriel, $code);
		$this->email->MsgHTML($msg . '<br/><br/>' . $this->email->signature);
		return $this;
	}
	
	function msg_transfert_adresse($id){
		if (!$this->email){
			$this->preparer_envoi();
		}
		
		$code = gestion_membres::get_one($id, 'code_validation code');
		if (is_null($code)){
			return $this->fin('aucun_code');
		}
		
		$msg = "-- English follows --
			<p>Vous avez demandé que l'adresse de courriel de votre enfant soit transférée à votre dossier. Pour que cela soit possible, vous devez prouver que vous avez bien accès à l'adresse en question en nous retournant le code fourni ci-dessous.</p>
			<p>Retournez là d'où vous avez déclenché l'envoi du présent message, et entrez le code suivant: <b>$code</b>.</p>
			<p>Notez qu'une fois le transfert effectué, vous serez le seul détenteur de cette adresse de courriel.</p>
			<p>Merci!</p>
			<p>$this->signature</p>
			<p></p>
			<p>--------------------</p>
			<p>You have requested that your child's email address be transferred to you. For  this to be possible, you must prove that you have access to this address by sending us back the code provided below.</p>
			<p>Please go back to the page from which you triggered the sending of the present email, and enter this code: <b>$code</b></p>
			<p>Note that once the address is transferred, it will be in your name only.
			<p>Thank you!</p>
			<p>$this->signature</p>
			";
		$this->email->MsgHTML($msg);
		return true;
		
	}
	function message_special($index){
		if ($index == 'php_mailer'){
			return myPHPMailer::$erreur;
		} else {
			return false;
		}
	}
	function send(){
		if ($this->email->Send()){
			return true;
		} else {
	
			return $this->fin('php_mailer');
		}
	}
}
?>
