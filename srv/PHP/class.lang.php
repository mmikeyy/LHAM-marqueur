<?php

class lang {
	static $langues = array('lang'=>'fr','autre_lang'=>'en');

	static $langue = 'fr';
	static $autre_langue = 'en';
	static $lang_dir;
    /**
     * @var PathParser $path_parser
     */
	static $path_parser;
	static $real_root;

	function __construct(){
		self::init();
	}
	static function init(){
		if (self::$lang_dir){
			return;
		}
		self::$path_parser = new PathParser(realpath(__DIR__ . '/../..'));
		self::$lang_dir = __DIR__ . '/lang';
		self::$real_root = realpath(ROOT);

	}
	static function autre($lang = ''){
		if ($lang == ''){
			return self::$autre_langue;
		}
		if ($lang == self::$langue){
			return self::$autre_langue;
		} else {
			return self::$langue;
		}
	}
	static function get_contents_strict($file, $lang = ''){
		return self::get_contents($file, $lang, true);
	}
	static function get_contents($file, $lang = '', $strict = false){
		self::init();

		$lang = self::default_lang($lang);
		$lang_dir = self::$lang_dir;
		if (file_exists($f = "$lang_dir/$lang/$file")){
			return file_get_contents($f);
		} else {
			if ($strict){
				return '';
			}
			$lang = self::autre($lang);
			if (file_exists($f = "$lang_dir/$lang/$file")){
				return file_get_contents($f);
			} else{
				return '';
			}
		}
	}

	static function default_lang($lang = ''){
		if ($lang == '' or ($lang != self::$langue and $lang != self::$autre_langue)){
			$lang = self::$langue;
			return $lang;
		}
		return $lang;

	}
	static function set_lang($lang){
		if ($lang != self::$langue and $lang != self::$autre_langue){
			return false;
		}
		if ($lang == self::$autre_langue){
			self::$autre_langue = self::$langue;
			self::$langue = $lang;
			self::$langues = array('lang'=>self::$langue, 'autre_lang'=>self::$autre_langue);
		}
		return true;
	}
	static function get_lang($lang = 'lang'){
		if ($lang != 'autre_lang' and $lang != self::$autre_langue){
			return self::$langue;
		} else {
			return self::$autre_langue;
		}
	}
	static function switch_lang(){
		$lang = self::$langue;
		self::$langue = self::$autre_langue;
		self::$autre_langue = $lang;
		self::$langues = array('lang'=>self::$langue, 'autre_lang'=>self::$autre_langue);
	}
	static function from_root($file)
    {
        return self::$path_parser->findRelativePath(self::$real_root, $file);
    }

	static function language_file($file, $lang = ''){
		self::init();
		$lang = self::lang($lang);

		$lang_dir = self::$lang_dir;
		//debug_print_once("va chercher fichier $file dans dir $lang_dir" );
		if (!preg_match('#\.[a-z]{2,4}$#', $file)){
			$file .= '.php';
		}


		if (file_exists($f = "$lang_dir/$lang/$file")){
            
			return $f;
		}

		$lang = self::autre_lang($lang);
		if (file_exists($f = "$lang_dir/$lang/$file")){
			return $f;
		}
		event_log::add('lang', 'load', "Fichier introuvable $file");
		return '';


	}
	static function include_lang($file, $lang = ''){
		$f = self::language_file($file, $lang);
		if ($f){
			return $f;
		}
		return http_json::conditional_fin('Fichier_introuvable...', $file);

	}
	static function include_lang_script($file, $lang = '', $filename_only = false){
		$lang_file = self::language_file($file, $lang);
		if (!$lang_file){

			echo "\n<!-- fichier introuvable = $file -->\n";
            return null;
		}

		$lang_file = self::$path_parser->findRelativePath(self::$real_root, $lang_file);
		if($filename_only){
			return $lang_file;
		}
		echo "<script type=\"text/javascript\" src=\"$lang_file\"></script>";
        return null;
	}
	static function complete_name($file, $lang = 'lang'){
		self::init();
		$lang = self::lang($lang);
		$lang_dir = self::$lang_dir;
		return "$lang_dir/$lang/$file";
	}
	static function lang($lang = ''){
		if ($lang == ''){
			return self::$langue;
		}
		if (in_array($lang, self::$langues)){
			return $lang;
		}
		if (array_key_exists($lang, self::$langues)){
			return self::$langues[$lang];
		}
		return self::$langue;
	}
	static function autre_lang($lang = ''){
		if ($lang == ''){
			return self::$autre_langue;
		}
		if ($lang == self::$langue or $lang == 'lang'){
			return self::$autre_langue;
		}
		return self::$langue;

	}
	static function nom_langue($lang = 'lang'){
		$lang = self::lang($lang);
		$nom_langue = cfg_yml::get( 'langue', $lang);

		if ($nom_langue){
			return $nom_langue;
		} else{
			return $lang;
		}
	}
	static function une_langue($array, $lang = ''){
		$lang = self::lang($lang);
		$autre_lang = self::autre($lang);
		$to_ret = array();
		foreach($array as $fld=>$val){
			if (is_array($val)){
				$to_ret[$fld] = self::une_langue($val, $lang);
			} else {
				if (preg_match("#^(.*)_$lang$#", $fld, $a)){
					$to_ret[$a[1]] = $val;
				} else {
					if (!preg_match("#^(.*)_$autre_lang$#", $fld)){
						$to_ret[$fld] = $val;
					}
				}
			}
		}
		return $to_ret;
	}
}
?>
