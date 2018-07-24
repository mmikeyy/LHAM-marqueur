<?php

class msg {
	
	private $msg = null;
	private $appelant;
	private $nom;
	private $last_msg;
	private $last_index;
	
	
	function __construct($nom, $appelant = null){
		
		$this->nom = $nom;
		$this->appelant = $appelant;

	}
	function init(){
		if ($this->msg !== null){
			return;
		}
		$this->msg = []; // laisser avant ligne suivante sinon boucle infinie en cas de fichier introuvable

        $noms = explode(',', $this->nom);
		foreach($noms as $nom) {
            @include lang::include_lang("$nom.php");
            if (isset($msg)){
                $this->msg = array_merge($this->msg, $msg);
            }

        }
	}
	function message_special($index){
		if ($this->appelant and method_exists($this->appelant, 'message_special')){
			return $this->appelant->message_special($index);
		}
		return false;
	}
	function fin($index, $info = ''){
		$this->last_index = $index;
		$msg = $this->message_special($index);
		if ($msg !== false){
			$this->last_msg = $msg;
			
			return http_json::conditional_fin($msg, $info);
			//throw new myException($msg, $info);
			
		}
		$this->init();
		if (array_key_exists($index, $this->msg)){
			$this->last_msg = $this->msg[$index];
			return http_json::conditional_fin($this->last_msg, $info);
			//throw new myException($this->last_msg, $info);
			
		} else {
			$this->last_msg = $index;
			return http_json::conditional_fin($index, $info);
			//throw new myException($index, $info);
		}
		
	}
	function get($index = null){
	    if (is_null($index)) {
	        return $this->get_msg();
        }
		$this->init();
		if (array_key_exists($index, $this->msg)){
			return $this->msg[$index];
		}
		return $index;
	}
	function get_msg(){
        $this->init();
		return $this->msg;
	}
	function set_appelant($obj){
		$this->appelant = $obj;
	}
}
?>
