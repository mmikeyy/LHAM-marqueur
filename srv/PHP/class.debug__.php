<?php
class debug__
{
    public $fname;
    public $global_active = 1;
    public $active = 1;
    private $debug_state = array();

    function __construct()
    {
//        file_put_contents(__DIR__ . '/../logs/debug/debug_info_all_users.txt',  "\ncreate debug", FILE_APPEND);

        $this->fname = __DIR__ . '/../logs/debug/debug_info_all_users.txt';



//        file_put_contents(__DIR__ . '/../logs/debug/debug_info_all_users.txt',  "\ndebug fname = $this->fname", FILE_APPEND);
    }

    function on()
    {
        $this->active = 1;
    }

    function off()
    {
        $this->active = 0;
    }

    function push($mode = null)
    {
        array_push($this->debug_state, $this->active);
        if (!is_null($mode)) $this->active = $mode;
    }

    function pop()
    {
        if (count($this->debug_state)) $this->active = array_pop($this->debug_state);
    }

    private function file_trim($size = 100000)
    {
        if (filesize($this->fname) > $size and $file = fopen($this->fname, 'r')) {
            fseek($file, -$size, SEEK_END);
            $data = fread($file, 150000);
            fclose($file);

            file_put_contents($this->fname, 'data = ' . $data, LOCK_EX);
        }
    }

    function add($str)
    {

//        file_put_contents(__DIR__ . '/../logs/debug/debug_info_all_users.txt',  "\ndebug add $str", FILE_APPEND);

        if (!$this->global_active or !$this->active) {
            file_put_contents(__DIR__ . '/../logs/debug/debug_info_all_users.txt',  "\ndebug retourne car not active", FILE_APPEND);
            return;
        }


        if (file_exists($this->fname) and filesize($this->fname) > 500000) {
            $this->file_trim();
        }
        if (($file = @fopen($this->fname, 'a'))) {
            $debut = date("H:i:s :   ");
            while (mb_strlen($str)) {
                $substr = mb_substr($str, 0, 4000);
                if (($pos = mb_strrpos($substr, '>')) > 3500) $substr = mb_substr($substr, 0, $pos + 1);
                fwrite($file, "$debut$substr\n\n");
                $debut = '';
                $str = mb_substr($str, mb_strlen($substr));
            }
            fclose($file);
        }
    }

    function clr($size = 0)
    {
        if ($size)
            $this->file_trim($size);
        else
            if (!($file = @fopen($this->fname, 'w'))) {

                return;
            };
        if (isset($file)){
            fclose($file);
        } 

    }

    function dump($var)
    {
        ob_start();
        var_dump($var);
        $this->add(ob_get_clean());
    }

    function export($var)
    {
        $this->add(var_export($var, true));
    }

}
