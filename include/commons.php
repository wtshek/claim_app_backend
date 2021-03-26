<?php

////////////////////////////////////////////////////////////////////////////////
// Description: Common functions and classes                                  //
// Date: 2014-02-11                                                           //
////////////////////////////////////////////////////////////////////////////////

/**
 * Gets the first key of an array
 * https://www.php.net/manual/en/function.array-key-first.php
 *
 * @since   2020-12-17
 * @param   array   An array
 * @return  The first key of array
 */
if ( !function_exists('array_key_first') )
{
    function array_key_first( $array )
    {
        foreach ( $array as $key => $unused )
        {
            return $key;
        }
        return NULL;
    }
}

/**
 * Gets the last key of an array
 * https://www.php.net/manual/en/function.array-key-last.php
 *
 * @since   2020-12-17
 * @param   array   An array
 * @return  The last key of array
 */
if ( !function_exists('array_key_last') )
{
    function array_key_last( $array )
    {
        return key( array_slice($array, -1, 1, TRUE) );
    }
}

/**
 * Make a string's first character lowercase
 * http://php.net/manual/en/function.lcfirst.php
 *
 * @since 2011-02-17
 * @param   str     The input string
 * @return  The resulting string
 */
if ( !function_exists('lcfirst') )
{
    function lcfirst( $str )
    {
        $str[0] = strtolower( $str[0] );
        return $str;
    }
}

/**
 * Reads a line.
 * http://www.php.net/manual/en/function.readline.php
 *
 * @since 2013-12-19
 * @param   prompt  You may specify a string with which to prompt the user
 * @return  A single string from the user
 */
if ( !function_exists('readline') )
{
    function readline( $prompt )
    {
        echo $prompt;
        return stream_get_line( STDIN, 1024, PHP_EOL );
    }
}

/**
 * Return an array key value, or default value if key doesn't exist
 *
 * @since 2006-10-13
 * @param   array   The source array
 * @param   key     The key that contains the value
 * @return  The value of that key, or the default value if key doesn't exist
 */
function array_ifnull( $array, $key, $default )
{
    if ( !is_array($array) || !array_key_exists($key, $array) )
    {
        return $default;
    }
    else
    {
        return $array[$key];
    }
}

/**
 * Removes duplicate values from an array (case insensitive)
 * http://www.php.net/array_unique
 *
 * @since   2012-11-14
 * @param   array   The input array
 * @return  The filtered array
 */
function array_iunique( $array )
{
    return array_intersect_key(
        $array, array_unique( array_map('strtolower', $array) )
    );
}

/**
 * Recursively bypass a function over the items of an array
 * http://www.php.net/array_unique
 *
 * @param   callback    Callback function to run for each element in each array
 * @param   array       An array to run through the callback function
 * @return  An array containing all the elements of array after applying the callback function to each one
 */
function array_map_recursive( $callback, $array )
{
    foreach ( $array as $key => $value )
    {
        if ( is_array($array[$key]) )
        {
            $array[$key] = array_map_recursive( $callback, $array[$key] );
        }
        else
        {
            $array[$key] = call_user_func( $callback, $array[$key] );
        }
    }
    return $array;
}

/**
 * Merge two or more arrays, where array values are appended,
 * while non-array values are overwritten
 * http://www.php.net/array_merge_recursive
 *
 * @since   2007-10-25
 * @param   A list of arrays to be merged
 * @return  The merged array
 */
function array_merge_recursive_unique()
{
    $arrays = func_get_args();
    $remains = $arrays;

    // We walk through each arrays and put value in the results (without
    // considering previous value).
    $result = array();

    // loop available array
    foreach ( $arrays as $array )
    {
        // The first remaining array is $array. We are processing it. So
        // we remove it from remaing arrays.
        array_shift( $remains );

        // We don't care non array param, like array_merge since PHP 5.0.
        if ( is_array($array) )
        {
            // Loop values
            foreach ( $array as $key => $value )
            {
                if ( is_array($value) )
                {
                    // We gather all remaining arrays that have such key available
                    $args = array();
                    foreach ( $remains as $remain )
                    {
                        if ( array_key_exists($key, $remain) )
                        {
                            array_push( $args, $remain[$key] );
                        }
                    }

                    if ( count($args) > 2 )
                    {
                        // Put the recursion
                        $result[$key] = call_user_func_array( __FUNCTION__, $args );
                    }
                    else
                    {
                        foreach ( $value as $vkey => $vval )
                        {
                            $result[$key][$vkey] = $vval;
                        }
                        if ( count($value) == 0 )
                        {
                            $result[$key] = array();
                        }
                    }
                }
                else
                {
                    // Simply put the value
                    $result[$key] = $value;
                }
            }
        }
    }
    return $result;
}

/**
 * Get byte length
 * http://pear.php.net/bugs/bug.php?id=7659
 *
 * @since 2006-09-22
 * @param   data    The data
 * @return  The number of bytes in data
 */
function bytelen( $data )
{
   if ( function_exists('mb_strlen') )
   {
       return mb_strlen( $data, 'latin1' );
   }

   return strlen( $data );
}

/**
 * Validate a time, complementary to checkdate()
 *
 * @since   2006-09-25
 * @param   hours       The number of hours
 * @param   minutes     The number of minutes
 * @param   seconds     The number of seconds
 * @return  Is a valid time or not
 */
function checktime( $hours, $minutes, $seconds )
{
    return is_int( $hours ) && is_int( $minutes ) && is_int( $seconds )
        && ( $hours >= 0 && $hours < 24 )
        && ( $minutes >= 0 && $minutes < 60 )
        && ( $seconds >= 0 && $seconds < 60 );
}

/**
 * Timezone conversion for a datetime value
 * http://blog.boxedice.com/2009/03/21/handling-timezone-conversion-with-php-datetime/
 *
 * @since   2011-08-10
 * @param   dt          The datetime value
 * @param   from_tz     The source timezone
 * @param   to_tz       The target timezone
 * @return  The converted datetime value
 */
function convert_tz( $dt, $from_tz, $to_tz )
{
    if ( $dt )
    {
        $datetime = new DateTime( $dt, new DateTimeZone($from_tz) );
        $timezone = new DateTimeZone( $to_tz );
        return date( 'Y-m-d H:i:s', $datetime->format('U') + $timezone->getOffset($datetime) );
    }
    return NULL;
}

/**
 * List directory as array
 *
 * @param   directory   The path
 * @param   recursive   Recursive or not
 * @return  The directory listing as an array
 */
function directoryToArray( $directory, $recursive )
{
    $array_items = array();
    if ( $handle = opendir($directory) )
    {
        while ( false !== ($file = readdir($handle)) )
        {
            if ( $file != '.' && $file != '..' )
            {
                if ( is_dir($directory. '/' . $file) )
                {
                    if ( $recursive )
                    {
                        $array_items = array_merge( $array_items, directoryToArray($directory. '/' . $file, $recursive) );
                    }
                    $file = $directory . '/' . $file;
                    $array_items[] = preg_replace( '/\/\//si', '/', $file );
                }
                else
                {
                    $file = $directory . '/' . $file;
                    $array_items[] = preg_replace( '/\/\//si', '/', $file );
                }
            }
        }
        closedir( $handle );
    }
    return $array_items;
}

/**
 * Execute an external program in background
 * https://www.php.net/manual/en/function.exec.php
 * https://stackoverflow.com/questions/27924184/execute-bat-file-from-php-in-background
 * https://stackoverflow.com/questions/14612371/how-do-i-run-multiple-background-commands-in-bash-in-a-single-line
 *
 * @since   2011-04-20
 * @param   command     The command that will be executed
 */
function exec_in_background( $command )
{
    if ( strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' )
    {
        $tmp_path = tempnam( sys_get_temp_dir(), 'tmp' );
        $bat_path = $tmp_path . '.bat';
        rename( $tmp_path, $bat_path );
        file_put_contents( $bat_path, $command . ' & del ' . escapeshellarg($bat_path) );
        $command = $bat_path;
        pclose( popen("start /B $command >nul 2>&1", 'r') );
    }
    else
    {
        exec( "($command) > /dev/null &" );
    }
}

/**
 * Flatten an array
 *
 * @param   array   The array to be flattened
 * @return  The flattened array
 */
function flat_array( $array )
{
    $flattern_array = array();

    foreach ( $array as $item )
    {
        if ( is_array($item) )
        {
            $flattern_array = array_merge( $flattern_array, $item );
        }
        else
        {
            $flattern_array[] = $item;
        }
    }

    return $flattern_array;
}

/**
 * Make sure the given name is a directory
 *
 * @param   name    The name of the directory
 * @return  Success or not
 */
function force_mkdir( $name )
{
    if ( is_writable(dirname($name)) )
    {
        if ( file_exists($name) && !is_dir($name) )
        {
            unlink( $name );
        }
        if ( !file_exists($name) )
        {
            mkdir( $name );
        }
        return TRUE;
    }
    return FALSE;
}

/**
 * Generate a random password
 * http://www.php.net/rand
 *
 * @since   2007-09-25
 * @param   length  The length of password
 * @return  The random password
 */
function generate_password( $length = 8 )
{
    $chars = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPRQSTUVWXY13456789';
    $code = '';
    while ( strlen($code) < $length )
    {
        $code .= $chars[mt_rand(0, strlen($chars) - 1)];
    }
    return $code;
}

/**
 * Convert HTML to PDF
 *
 * @since   2015-08-04
 * @param   html        The HTML content
 * @param   options     The options for wkhtmltopdf (See http://wkhtmltopdf.org/usage/wkhtmltopdf.txt)
 * @return  The PDF content
 */
function html2pdf( $html, $options = array() )
{
    // Set paths
    $path = sys_get_temp_dir() . '/' . uniqid( rand(), TRUE );
    $html_path = $path . '.html';
    $pdf_path = $path . '.pdf';

    // Convert HTML to PDF
    file_put_contents( $html_path, $html );
    exec( sprintf(
        'wkhtmltopdf %s %s %s',
        implode( ' ', $options ),
        escapeshellarg( $html_path ),
        escapeshellarg( $pdf_path )
    ), $lines );

    // Get PDF content
    $pdf = FALSE;
    if ( file_exists($pdf_path) )
    {
        $pdf = file_get_contents( $pdf_path );
    }
    rm( $path . '.*' );
    return $pdf;
}

/**
 * Compare two IP addresses
 * http://www.php.net/ip2long
 *
 * @since   2007-11-5
 * @param   ip1     First IP address
 * @param   ip2     Second IP address
 * @param   mask    IP mask
 * @return  Match or not
 */
function ipmatch( $ip1, $ip2, $mask )
{
    return ( ip2long($ip1) & ~(pow(2, 32 - $mask) - 1) )
        == ( ip2long($ip2) & ~(pow(2, 32 - $mask) - 1) );
}

function isBot()
{
    /* This function will check whether the visitor is a search engine robot */
    $bots = array("Teoma", "alexa", "froogle", "Gigabot", "inktomi",
    "looksmart", "URL_Spider_SQL", "Firefly", "NationalDirectory",
    "Ask Jeeves", "TECNOSEEK", "InfoSeek", "WebFindBot", "girafabot",
    "crawler", "www.galaxy.com", "Googlebot", "Scooter", "Slurp",
    "msnbot", "appie", "FAST", "WebBug", "Spade", "ZyBorg", "rabaz",
    "Baiduspider", "Feedfetcher-Google", "TechnoratiSnoop", "Rankivabot",
    "Mediapartners-Google", "Sogou web spider", "WebAlta Crawler","TweetmemeBot",
    "Butterfly","Twitturls","Me.dium","Twiceler");
    foreach( $bots as $bot )
    {
        if ( preg_match('#' . preg_quote($bot) . '#i', $_SERVER['HTTP_USER_AGENT']) )
            return true;
    }
    return false;
}

/**
 * See if the browser is Internet Explorer
 * http://www.coolcode.cn/?p=183
 *
 * @since 2006-09-22
 * @return  Is Internet Explorer or not
 */
function is_ie()
{
    $useragent = strtolower( array_ifnull($_SERVER, 'HTTP_USER_AGENT', '') );
    if ( strpos($useragent, 'opera') !== FALSE
        || strpos($useragent, 'konqueror') !== FALSE )
    {
        return FALSE;
    }
    if ( strpos($useragent, 'msie ') !== FALSE )
    {
        return TRUE;
    }
    return FALSE;
}

/**
 * List file names of a directory.
 *
 * @since   2006-11-17
 * @param   path    The path of the directory
 * @param   depth   The depth of the directory
 * @return  An array of file names in that directory
 */
function list_files( $path, $depth = 1 )
{
    $names = array();
    if ( is_dir($path) )
    {
        $handle = NULL;
        if ( $handle = opendir($path) )
        {
            while ( ($name = readdir($handle)) !== FALSE )
            {
                if ( !in_array($name, array('.', '..')) )
                {
                    $subpath = "$path/$name";
                    switch ( filetype($subpath) )
                    {
                        case 'dir':
                            $names = array_merge( $names, list_files($subpath, $depth+1) );
                            break;

                        case 'file':
                            $names[] = implode( '/', array_reverse(array_slice(array_reverse(explode('/', $subpath)), 0, $depth)) );
                            break;

                        default:
                            // Nothing
                    }
                }
            }
            closedir( $handle );
        }
    }
    return $names;
}

/**
 * Split a string by newline
 *
 * @since   2012-07-17
 * @param   string      The string value
 * @return  The array of strings
 */
function nl_explode( $string )
{
    return explode( "\n", nl_replace($string, "\n") );
}

/**
 * Replace newlines in string
 *
 * @since   2012-07-17
 * @param   subject     The string value
 * @param   replace     The replacement value
 * @return  The replaced string
 */
function nl_replace( $subject, $replace )
{
    return str_replace( array("\r\n", "\r", "\n"), $replace, $subject );
}

/**
 * Count the number of pages of PDF file.
 * http://www.pdflabs.com/docs/pdftk-cli-examples/
 *
 * @since   2011-03-23
 * @param   path    The path of the PDF file
 * @return  The number of pages
 */
function pdf_page_count( $path )
{
    if ( is_readable($path) )
    {
        exec( sprintf('pdftk %s dump_data', escapeshellarg($path)), $lines );
        foreach ( $lines as $line )
        {
            $pair = array_map( 'trim', explode(': ', $line) );
            if ( $pair[0] == 'NumberOfPages' )
            {
                return intval( $pair[1] );
            }
        }
    }

    return FALSE;
}

/**
 * Vigorously erase files and directories
 * http://www.php.net/unlink
 *
 * @param $fileglob mixed If string, must be a file name (foo.txt), glob pattern (*.txt), or directory name.
 *                        If array, must be an array of file names, glob patterns, or directories.
 * @return  Success or not
 */
function rm( $fileglob )
{
   if ( is_string($fileglob) )
   {
       if ( is_file($fileglob) )
       {
           return unlink($fileglob);
       }
       else if ( is_dir($fileglob) )
       {
           $ok = rm( "$fileglob/*" );
           if ( !$ok )
           {
               return FALSE;
           }
           return rmdir( $fileglob );
       }
       else
       {
           $matching = glob( $fileglob );
           if ( $matching === FALSE )
           {
               trigger_error( sprintf('No files match supplied glob %s', $fileglob), E_USER_WARNING );
               return FALSE;
           }
           $rcs = array_map( 'rm', $matching );
           if ( in_array(FALSE, $rcs) )
           {
               return FALSE;
           }
       }
   }
   else if ( is_array($fileglob) )
   {
       $rcs = array_map( 'rm', $fileglob );
       if ( in_array(FALSE, $rcs) )
       {
           RETURN FALSE;
       }
   }
   else
   {
       trigger_error( 'Param #1 must be filename or glob pattern, or array of filenames or glob patterns', E_USER_ERROR );
       return FALSE;
   }

   return TRUE;
}

/**
 * Format a number to a size
 *
 * @since   2006-04-07
 * @param   size        The size in terms of bytes
 * @param   precision   The number of digits after the decimal point
 * @return  The size in terms of B, KB, MB, etc
 */
function size_format( $size, $precision = 2 )
{
    $unit = array( 'B', 'KB', 'MB', 'GB', 'TB', 'PB' );
    $pos = 0;
    while ( $size >= 1024 && $pos < count($unit) )
    {
        $size /= 1024;
        $pos++;
    }
    return round( $size, $precision ) . ' ' . $unit[$pos];
}

//http://php.net/manual/en/function.copy.php
/**
 * Copy file or folder from source to destination, it can do
 * recursive copy as well and is very smart
 * It recursively creates the dest file or directory path if there weren't exists
 * Situtaions :
 * - Src:/home/test/file.txt ,Dst:/home/test/b ,Result:/home/test/b -> If source was file copy file.txt name with b as name to destination
 * - Src:/home/test/file.txt ,Dst:/home/test/b/ ,Result:/home/test/b/file.txt -> If source was file Creates b directory if does not exsits and copy file.txt into it
 * - Src:/home/test ,Dst:/home/ ,Result:/home/test/** -> If source was directory copy test directory and all of its content into dest
 * - Src:/home/test/ ,Dst:/home/ ,Result:/home/**-> if source was direcotry copy its content to dest
 * - Src:/home/test ,Dst:/home/test2 ,Result:/home/test2/** -> if source was directoy copy it and its content to dest with test2 as name
 * - Src:/home/test/ ,Dst:/home/test2 ,Result:->/home/test2/** if source was directoy copy it and its content to dest with test2 as name
 * @todo
 *  - Should have rollback technique so it can undo the copy when it wasn't successful
 *  - Auto destination technique should be possible to turn off
 *  - Supporting callback function
 *  - May prevent some issues on shared enviroments : http://us3.php.net/umask
 * @param $source //file or folder
 * @param $dest ///file or folder
 * @param $options //folderPermission,filePermission
 * @return a list of files copied
 */
function smartCopy( $source, $dest, $options = array('folderPermission' => 0777, 'filePermission' => 0777) )
{
    $result=false;
    $file_paths = array();

    if (is_file($source)) {
        if ($dest[strlen($dest)-1]=='/') {
            if (!file_exists($dest)) {
                cmfcDirectory::makeAll($dest,$options['folderPermission'],true);
            }
            $__dest=$dest."/".basename($source);
        } else {
            $__dest=$dest;
        }

        $f = "";
        $tmp = $__dest;
        $sp = array_filter(explode('/', $tmp), 'strlen');

        if ($dest[strlen($dest)-1]!='/') {
            array_pop($sp);
        }

        foreach($sp as $sub) {
            $f .= "/" . $sub;
            if(!is_dir($f)) {
                mkdir($f);
            }
        }

        $result=copy($source, $__dest);
        if($result)
            $file_paths[] = $__dest;
        chmod($__dest,$options['filePermission']);

    } elseif(is_dir($source)) {
        if ($dest[strlen($dest)-1]=='/') {
            if ($source[strlen($source)-1]=='/') {
                //Copy only contents
            } else {
                //Change parent itself and its contents
                $dest=$dest.basename($source);
                @force_mkdir($dest);
                chmod($dest,$options['filePermission']);
            }
        } else {
            if ($source[strlen($source)-1]=='/') {
                //Copy parent directory with new name and all its content
                @force_mkdir($dest,$options['folderPermission']);
                chmod($dest,$options['filePermission']);
            } else {
                //Copy parent directory with new name and all its content
                @force_mkdir($dest,$options['folderPermission']);
                chmod($dest,$options['filePermission']);
            }
        }

        $files=scandir($source);
        foreach($files as $file)
        {
            if($file!="." && $file!="..")
            {
                $file_paths = array_merge($file_paths, smartCopy($source."/".$file, $dest."/".$file, $options));
            }
        }

    } else {
        $result=false;
    }
    return $file_paths;
}

/**
 * Shift a string
 * https://stackoverflow.com/questions/2423802/rotate-a-string-n-times-in-php
 *
 * @param   str     The string
 * @param   len     The number of characters to shift
 * @return  The shifted string
 */
function str_shift($str, $len) {
    $len = $len % strlen($str);
    return substr($str, $len) . substr($str, 0, $len);
}

/**
 * Build regular expression string
 *
 * @param   s       The text string
 * @param   sub     Substitute or not
 * @param   opt     Options if not substituted
 * @return  The regular expression string
 */
function string_build_regexp( $s = '', $sub = true, $opt = array('i') )
{
    if ( $sub )
    {
        return sprintf( '(?:%1$s)', preg_quote($s) );
    }
    else
    {
        return sprintf( '#%1$s#%2$s', preg_quote($s), implode('', $opt) );
    }
}

/**
 * Parse a string to a size
 * http://www.php.net/ini_get
 *
 * @since   2006-05-10
 * @param   val     The string value
 * @return  The size in terms of bytes
 */
function string_to_byte( $val )
{
    $val = trim( $val );
    $last = strtolower( substr($val, -1) );
    switch( $last )
    {
        // The 'G' modifier is available since PHP 5.1.0
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return $val;
}

/**
 * Parse a string to a date/time
 *
 * @since   2006-09-25
 * @param   value       The string value
 * @param   has_time    Parse as datetime or date
 * @return  The date/time value
 */
function string_to_date( $value, $has_time = FALSE )
{
    $datetime = $value === '' ? FALSE : date_create( $value );
    if ( $datetime )
    {
        return $datetime->format( $has_time ? 'Y-m-d H:i:s' : 'Y-m-d' );
    }
    return NULL;
}

/**
 * Parse a string to a time
 *
 * @since   2006-09-25
 * @param   value       The string value
 * @return  The time value
 */
function string_to_time( $value )
{
    $time = strtotime( "2000-01-01 $value" );
    if ( $time )
    {
        return date( 'H:i:s', $time );
    }
    return NULL;
}

/**
 * Strip punctuation and excessive whitespaces from text.
 * http://www.php.net/manual/en/regexp.reference.unicode.php
 *
 * @since   2012-08-20
 * @param   text          The text value
 * @return  The stripped text value
 */
function strip_punctuation( $text )
{
    return trim( preg_replace(
        '/[\p{Cf}\p{Cn}\p{Co}\p{Cs}\p{P}\p{S}\p{Zl}\p{Zp}]/u',  // Other (format, unassigned, private use and surrogate), punctuation, symbol and separator (line and paragraph)
        '',
        preg_replace( array('/[\s]/u', '/ +/'), ' ', $text )    // Standardize the whitespace character
    ) );
}

/**
 * Format a time to a string in form of HH:MM:SS (e.g. 90 to '00:01:30')
 *
 * @since   2006-09-08
 * @param   time    The time in seconds
 * @return  The time in form of string
 */
function time_format( $time )
{
    $hours = floor( $time / 3600 );
    $minutes = floor( ($time % 3600) / 60 );
    $seconds = $time % 60;
    return sprintf( '%02d:%02d:%02d', $hours, $minutes, $seconds );
}

/**
 * Format a number to a timezone string (e.g. 8 to '+08:00')
 *
 * @since   2006-09-08
 * @param   offset  The timezone offset in hours
 * @return  The timezone offset in form of string
 */
function timezone_format( $offset )
{
    // The valid offset range is -12:00 to +13:00
    $offset = floatval( $offset );
    if ( $offset < -12 || $offset > 14 )
    {
        $offset = $offset % 12;
    }

    $sign = $offset < 0 ? '-' : '+';
    $hours = abs( intval( $offset ) );
    $minutes = ( abs($offset) - $hours ) * 60;

    return $sign . sprintf('%02u', $hours) . ':' . sprintf('%02u', $minutes);
}

/**
 * Get the duration of video file.
 * http://www.ffmpeg.org/ffmpeg.html
 *
 * @since   2011-04-14
 * @param   path    The path of the video file
 * @return  The duration
 */
function video_duration( $path )
{
    if ( is_readable($path) )
    {
        exec( sprintf('ffmpeg -i %s 2>&1', escapeshellarg($path)), $lines );
        preg_match( '/Duration: (.*?),/', implode(' ', $lines), $matches );
        if ( count($matches) > 0 )
        {
            return $matches[1];
        }
    }

    return FALSE;
}

/**
 * A wrapper class for handling HTTP response.
 *
 * @author Martin Ng <martin@avalade.com>
 * @since   2006-10-10
 */
class HttpResponse
{
    /**
     * Constructor.
     */
    function __construct()
    {
        $this->_responseHeaders = array();
    }

    /**
     * Set a HTTP response header for HTTP response.
     *
     * @param   header  The name of the response header
     * @param   value   The response header value
     */
    function setResponseHeader( $header, $value )
    {
        $this->_responseHeaders[$header] = $value;
    }

    /**
     * Transmits the response with optional string/DOM content.
     *
     * @param   content     An optional string/DOM content
     * @param   charset     An optional character set
     * @param   type        An optional MIME type
     * @param   code        An optional response code
     */
    function send( $content = '', $charset = '', $type = 'text/plain', $code = 200 )
    {
        // Send status code
        header( ' ', false, $code );

        // Send the response headers
        foreach( $this->_responseHeaders as $key => $value )
        {
            if ( $key != 'Content-Type' && $key != 'Content-Length' )
            {
                header( "$key: $value" );
            }
        }

        // PHP5 DOM
        // http://hk2.php.net/dom
        if ( get_class($content) == 'DOMDocument' )
        {
            $content = $content->saveXML();
            if ( $type == 'text/plain' )
            {
                $type = 'text/xml';
            }
        }

        // PHP4 DOM XML
        // http://hk2.php.net/manual/en/ref.domxml.php
        else if ( get_class($content) == 'DomDocument' )
        {
            $content = $content->dump_mem();
            if ( $type == 'text/plain' )
            {
                $type = 'text/xml';
            }
        }

        if ( $type == '' )
        {
            $type = 'text/plain';
        }

        if ( $charset != '' )
        {
            $type .= "; charset=$charset";
        }

        // Try to compress for text content
        if ( preg_match('/text\/.*/', $type)
            || in_array($type, array('application/json', 'application/xml')) )
        {
            ini_set( 'zlib.output_compression', 'Off' );
            ob_start( 'ob_gzhandler' );
        }
        else
        {
            //header( 'Content-Length: ' . bytelen($content)  );
        }

        // Send the remaining response headers and the response body
        header( "Content-Type: $type" );
        if ( $content != '' )
        {
            echo $content;
        }
    }

    /**
     * Sends a redirect response to the client using the specified URL.
     *
     * @param   url     A URL
     * @param   code    An optional response code
     */
   function sendRedirect( $url, $code = 302 )
   {
       header( "Location: $url", TRUE, $code );
   }

    // Private variables
    var $_responseHeaders;      // Response headers
}

/**
 * Database related functions.
 *
 * @author Martin Ng <martin@avalade.com>
 * @since   2020-03-02
 */

class RDBMS extends PDO
{
    /**
     * Escape a value so it can be safely used in a query.
     *
     * @since   2008-12-18
     * @param   value               The value
     * @param   escape_wildcards    Escape wildcard characters ('%' and '_') or not
     * @return  The escaped value
     */
    public function escape( $value, $escape_wildcards = FALSE )
    {
        // Handle non-string values
        if ( is_null($value) )
        {
            return 'NULL';
        }
        else if ( is_int($value) || is_float($value) )
        {
            return $value;
        }
        else if ( is_bool($value) )
        {
            return $value ? 1 : 0;
        }

        // Quote the string in SQL way
        $new_value = (string)$value;
        if ( $escape_wildcards )
        {
            $new_value = $this->escapeWildCards( $new_value );
        }
        return $this->quote( $new_value );
    }

    /**
     * Escape wildcard characters ('%' and '_') in a string.
     *
     * @since   2008-12-18
     * @param   value       The value
     * @return  The escaped value
     */
    public function escapeWildCards( $value )
    {
        return str_replace( array('%', '_'), array('\%', '\_'), $value );
    }

    /**
     * Generate the LIKE expression.
     *
     * @since   2008-12-18
     * @param   fields  An array of fields
     * @param   value   The text string to search
     */
    public function likeSearch( $fields, $value )
    {
        $keywords = preg_split( '/[\s]+/', $value );
        $where = array();
        foreach ( $keywords as $keyword )
        {
            $value = $this->quote( '%' . $this->escape(array('%', '_'), array('\%', '\_'), $keyword) . '%' );
            foreach ( $fields as $field )
            {
                $where[] = "$field LIKE $value";
            }
        }
        return implode( ' OR ', $where );
    }

    /**
     * Translate an SQL field using an associative array.
     *
     * @since   2008-12-18
     * @param   field   The field to be translated
     * @param   arr     An associative array containing the translation defination
     * @param   alias   The optional alias
     * @return  The SQL expression for the translation
     */
    public function translateField( $field, $arr, $alias = '' )
    {
        $sql = '';

        if ( count($arr) > 0 )
        {
            $sql = "CASE $field";
            foreach ( $arr as $key => $val )
            {
                $sql .= " WHEN '" . str_replace( "'", "''", $key ) . "' THEN '" . str_replace( "'", "''", $val ) . "'";
            }
            $sql .= " ELSE $field";
            $sql .= ' END';
        }
        else
        {
            $sql = $field;
        }

        if ( $alias !== '' )
        {
            $sql .= " AS $alias";
        }

        return $sql;
    }

    /**
     * Cleanup an SQL field so that its value contains only the characters for matching.
     *
     * @since   2006-05-24
     * @param   field       The field that contains the value for matching
     * @param   unwanted    An array of characters not wanted for matching
     */
    function cleanupFieldForMatching( $field, $unwanted )
    {
        $sql = $field;
        foreach ( $unwanted as $ch )
        {
            $sql = "REPLACE( $sql, " . $this->quote($ch) . ", '' )";
        }
        return $sql;
    }

    /**
     * Cleanup an string so that it contains only the characters for matching.
     *
     * @since   2006-05-24
     * @param   value       The value for matching
     * @param   unwanted    An array of characters not wanted for matching
     */
    function cleanupStringForMatching( $value, $unwanted )
    {
        return is_null($value) ? NULL : str_replace( $unwanted, '', (string)$value );
    }
}

class db {
    /** @var  extras_ADOConnection $db */
    public $db;

    public static function Instance()
    {
        static $inst = null;
        if ($inst === null) {
            $inst = new db();
        }
        return $inst;
    }

    public function escape($value, $escape_wildcards = FALSE) {
        if(!isset($value))
            $value = NULL;
        return $this->db->escape($value, $escape_wildcards);
    }

    public function translateField( $field, $arr, $alias = '' ) {
        return $this->db->translateField($field, $arr, $alias);
    }

    /**
     * Private ctor so nobody else can instance it
     *
     */
    private function __construct() {
    }

    public function setDb($db) {
        $this->db = $db;
    }

    public function exec($sql) {
        return $this->db->exec( $sql );
    }

    public function query($sql) {
        $statement = $this->db->query( $sql );
        if ( !$statement )
        {
            $this->error($this->db->errorInfo());
        }
        return $statement;
    }

    public function getAll($sql) {
        $statement = $this->db->query( $sql );
        if ( !$statement )
        {
            $this->error($this->db->errorInfo());
        }
        return $statement->fetchAll();
    }

    public function Insert_ID() {
        return $this->db->lastInsertId();
    }

    private function log($type, $description, $locale, $module, $file_path = __FILE__, $line_number = __LINE__) {
        if ( $this->db )
        {
            $sql = 'INSERT INTO logs(type, locale, module, description, file_path,';
            $sql .= ' line_number, ip_address, user_agent, referer_uri,';
            $sql .= ' request_uri, logged_date) VALUES (';
            $sql .= $this->db->escape($type) . ',';
            $sql .= $this->db->escape($locale) . ',';
            $sql .= $this->db->escape($module) . ',';
            $sql .= $this->db->escape($description ) . ',';
            $sql .= $this->db->escape($file_path) . ',';
            $sql .= intval($line_number) . ',';
            $sql .= $this->db->escape(array_ifnull($_SERVER, 'REMOTE_ADDR', NULL)) . ',';
            $sql .= $this->db->escape(array_ifnull($_SERVER, 'HTTP_USER_AGENT', NULL)) . ',';
            $sql .= $this->db->escape(array_ifnull($_SERVER, 'HTTP_REFERER', NULL)) . ',';
            $sql .= $this->db->escape(array_ifnull($_SERVER, 'REQUEST_URI', NULL)) . ',';
            $sql .= 'UTC_TIMESTAMP())';
            $statement = $this->db->query( $sql );
            return !!$statement;
        }
    }

    private function errorHandling($stack) {
    }

    private function error($message, $error = NULL, $redirect = NULL, $code = 0) {
//        header( 'Content-Type: text/plain; charset=utf-8' );
//        $output = 'DB Error: ' . ($message === '' ? '' : "\r\n\r\n$message");
//
//        $debug_stack = array($message);
//        $debug = debug_backtrace();
//        $c = count($debug);
//        $line = __LINE__;
//        $path = __FILE__;
//        $module = "";
//        $locale = "en";
//
//
//        for($i = 1; $i < $c; $i++) {
//            $s = $debug[$i];
//
//            $msg = sprintf("%s(Line: %s) - %s::%s(%s)"
//                            , $s['file']
//                            , $s['line']
//                            , $s['class']
//                            , $s['function']
//                            , isset($s['args']) && count($s['args']) > 0 ? implode(', ', $s['args']) : ""
//                    );
//
//            $debug_stack[] = $msg;
//
//            if($i == 1) {
//                $line = $s['line'];
//                $path = $s['file'];
//            }
//
//            if($module == "" && preg_match("#_module$#i", $s['class'])) {
//                $module = preg_replace("#_module$#i", "", $s['class']);
//            } elseif ($s['class'] == "staticPage") {
//                $locale = $s['object']->getLocale();
//            }
//        }
//
//        $this->db->execute("ROLLBACK");
//
//        $this->log("error", implode("\n>>", $debug_stack), $locale, $module, $path, $line);
//        $output .= "\r\n\r\nIf problem persists, please contact the system administrator.";
//        exit( $output );

        throw new sqlException($message);
    }
}
