<?php



/**
*  PathParser 1.0
*
*  @author: Carlos Reche
*  @email:  carlosreche@yahoo.com
*
*  Dez 19, 2004
*
*/
class PathParser
{

    var $path;         // (string)  Parsed path

    var $root;         // (string)  Path root ("http://www.something.com", "C:/" or "/")
    var $dir;          // (string)  Path after root (only dirs)
    var $file;         // (string)  File name, if path doesn't end on dir
    var $extension;    // (string)  File extension

    // If path is an URL
    var $scheme;       // (string)  Ex. "http"
    var $host;         // (string)  Ex. "www.something.com"
    var $port;         // (int)     Host port
    var $user;         // (string)  User
    var $pass;         // (string)  Password
    var $querystring;  // (string)  Query string (part after the "?")
    var $fragment;     // (string)  Fragment (part after the "#")


    function __construct ($path = "")
    {
        $this->path = $path;

        $this->parse();
    }





    function parse($path = "")
    {
        $path = ($path != "")  ?  $path  :  $this->path;

        $this->root        = "";
        $this->dir         = "";
        $this->file        = "";
        $this->extension   = "";

        $this->scheme      = "";
        $this->host        = "";
        $this->port        = "";
        $this->user        = "";
        $this->pass        = "";
        $this->querystring = "";
        $this->fragment    = "";


        if ($path == "")
        {
            return false;
        }


        $this->path = $this->fix($path);

        preg_match_all("/^(\\/|\w:\\/|(http|ftp)s?:\\/\\/[^\\/]+\\/)?(.*)$/i", $this->path, $matches, PREG_SET_ORDER);

        $this->root = $matches[0][1];
        $dir        = $matches[0][3];


        if (preg_match("/\\/$/", $dir))
        {
            $this->dir = $dir;
        }
        else
        {
            $this->dir       = dirname($dir) . '/';
            $this->file      = preg_replace("/^([^\\?]*)\\??([^\\#]*)\\#?(.*)$/", "\\1", basename($dir));
            $this->extension = preg_replace("'^([^\\.]+\\.)+?([^\\.]+)$'", "\\2", $this->file);
        }

        if ($this->root == ""  ||  preg_match("/^(http|ftp|\\/)/", $this->root))
        {
            preg_match_all("/^([^\\?]*)\\??([^\\#]*)\\#?(.*)$/i", basename($dir), $matches, PREG_SET_ORDER);

            $this->querystring = $matches[0][2];
            $this->fragment    = $matches[0][3];

            if (preg_match("/^(https?|ftp)/", $this->root))
            {
                $parse_url = parse_url($this->root);

                $this->scheme = isset($parse_url['scheme'])  ?  $parse_url['scheme']  :  "";
                $this->host   = isset($parse_url['host'])    ?  $parse_url['host']    :  "";
                $this->port   = isset($parse_url['port'])    ?  $parse_url['port']    :  "";
                $this->pass   = isset($parse_url['pass'])    ?  $parse_url['pass']    :  "";
            }
        }



        return array(
            'root'        => $this->root,
            'dir'         => $this->dir,
            'file'        => $this->file,
            'extension'   => $this->extension,

            'scheme'      => $this->scheme,
            'host'        => $this->host,
            'port'        => $this->port,
            'user'        => $this->user,
            'pass'        => $this->pass,
            'querystring' => $this->querystring,
            'fragment'    => $this->fragment,
        );
    }





    function fix($path = "")
    {
        $path = ($path != "")  ?  $path  :  $this->path;

        // Sanity check
        if ($path == "")
        {
            return false;
        }

        // Converts all "\" to "/", and erases blank spaces at the beginning and the ending of the string
        $path = trim(preg_replace("/\\\\/", "/", (string)$path));

        /*  Checks if last parameter is a directory with no slashs ("/") in the end. To be considered a dir, 
        *   it can't end on "dot something", or can't have a querystring ("dot something ? querystring")
        */
        if (!preg_match("/(\.\w{1,4})$/", $path)  &&  !preg_match("/\?[^\\/]+$/", $path)  &&  !preg_match("/\\/$/", $path))
        {
            $path .= '/';
        }

        /*   Breaks the original string in to parts: "root" and "dir".
        *    "root" can be "C:/" (Windows), "/" (Linux) or "http://www.something.com/" (URLs). This will be the start of output string.
        *    "dir" can be "Windows/System", "root/html/examples/", "includes/classes/class.validator.php", etc.
        */
        preg_match_all("/^(\\/|\w:\\/|(http|ftp)s?:\\/\\/[^\\/]+\\/)?(.*)$/i", $path, $matches, PREG_SET_ORDER);

        $path_root = $matches[0][1];
        $path_dir  = $matches[0][3];

        /*  If "dir" part has one or more slashes at the beginning, erases all.
        *   Then if it has one or more slashes in sequence, replaces for only 1.
        */
        $path_dir = preg_replace(  array("/^\\/+/", "/\\/+/"),  array("", "/"),  $path_dir  );

        // Breaks "dir" part on each slash
        $path_parts = explode("/", $path_dir);

        // Creates a new array with the right path. Each element is a new dir (or file in the ending, if exists) in sequence.
        for ($i = $j = 0, $real_path_parts = array(); $i < count($path_parts); $i++)
        {
            if ($path_parts[$i] == '.')
            {
                continue;
            }
            else if ($path_parts[$i] == '..')
            {
                if (  (isset($real_path_parts[$j-1])  &&  $real_path_parts[$j-1] != '..')  ||  ($path_root != "")  )
                {
                    array_pop($real_path_parts);
                    $j--;
                    continue;
                }
            }

            array_push($real_path_parts, $path_parts[$i]);
            $j++;
        }

        return $path_root . implode("/", $real_path_parts);
    }





    function findRelativePath($path_1, $path_2)
    {
        if ($path_1 == ""  ||  $path_2 == "")
        {
            return false;
        }

        $path_1 = $this->fix($path_1);
        $path_2 = $this->fix($path_2);

        preg_match_all("/^(\\/|\w:\\/|https?:\\/\\/[^\\/]+\\/)?(.*)$/i", $path_1, $matches_1, PREG_SET_ORDER);
        preg_match_all("/^(\\/|\w:\\/|https?:\\/\\/[^\\/]+\\/)?(.*)$/i", $path_2, $matches_2, PREG_SET_ORDER);

        if ($matches_1[0][1] != $matches_2[0][1])
        {
            return false;
        }

        $path_1_parts = explode("/", $matches_1[0][2]);
        $path_2_parts = explode("/", $matches_2[0][2]);


        while (isset($path_1_parts[0])  &&  isset($path_2_parts[0]))
        {
            if ($path_1_parts[0] != $path_2_parts[0])
            {
                break;
            }

            array_shift($path_1_parts);
            array_shift($path_2_parts);
        }


        for ($i = 0, $path = ""; $i < count($path_1_parts)-1; $i++)
        {
            $path .= "../";
        }

        return $path . implode("/", $path_2_parts);
    }





    function parseQueryString($querystring = "")
    {
        $querystring = ($querystring != "")  ?  $querystring  :  $this->querystring;
        $querystring = preg_replace("/^\\??/", "", $querystring);
        $ampersand   = (preg_match("/&amp;/", $querystring))  ?  "&amp;"  :  "&";
        $return      = array();

        foreach (explode($ampersand, $querystring) as $definition)
        {
            $values = explode("=", $definition);
            $return[$values[0]] = isset($values[1])  ?  $values[1]  :  "";
        }

        return $return;
    }

}



?>