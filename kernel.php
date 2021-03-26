<?php

// Get the root directory of this application
// e.g. C:\www\avalade_cms\kernel.php -> C:\www\avalade_cms
$APP_ROOT = dirname( __FILE__ );

// Include required files
require_once( "$APP_ROOT/include/commons.php" );
/*
require_once( "$APP_ROOT/include/GeoIP2/ProviderInterface.php" );
require_once( "$APP_ROOT/include/GeoIP2/Model/Country.php" );
require_once( "$APP_ROOT/include/GeoIP2/Database/Reader.php" );
require_once( "$APP_ROOT/include/MaxMind/Db/Reader.php" );
require_once( "$APP_ROOT/include/Mobile_Detect/Mobile_Detect.php"  );
require_once( "$APP_ROOT/module/file_admin/include/php_image_magician.php" );
*/
require_once( "$APP_ROOT/vendor/autoload.php" );

// use GeoIp2\Database\Reader;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * The kernel.
 *
 * This class contains shared objects like database connection and passes the
 * request to the corresponsing module to handle.
 *
 * It also contains shared methods like HTTP redirection and list generation.
 *
 * The internal character encoding for data (charset) is utf-8.
 * $this->response['charset'] controls what charset will be used in the output.
 *
 * @author  Martin Ng <martin@avalade.com>
 * @since   2008-10-31
 */
class kernel
{
    public $cli = false;
    public $entity_admin_def = array();

    /**
     * @since 2008-10-31
     * @param $site           Admin site or public site?
     * @param $path_info  The requested path
     */
    function __construct( $site, $path_info, $cli = false )
    {
        /***********************************************************************
         * Configuration
         */
        require( dirname(__FILE__) . '/conf/config.php' );

        /** @var array conf */
        $this->conf = $CONF;
        extract( $this->conf );

        // 16-byte security key to be compatible with MySQL aes-128-ecb
        $this->conf['security_key'] = str_pad( substr($this->conf['security_key'], 0, 16), 16 );

        $this->cli = $cli;

        /***********************************************************************
         * Request
         */
        $path_segments = array_values(array_filter(explode( '/', $path_info ), 'strlen'));

        $this->request = array(
            'site' => $site,
            'path_segments' => $path_segments, // real path segments with empty segments removed
            'locale' => count($path_segments)? $path_segments[0] : ''
        );

        // public site structure
        if($site == 'public_site')
        {
            if(count($path_segments)>1)
                $this->request['locale'] = $this->request['locale'].'/'.$path_segments[1];
        }

        // add empty path segments for easier processing later on
        $path_segments[] = '';
        if(!count($this->request['path_segments'])) {
            array_unshift($path_segments, '');
        }

        /***********************************************************************
         * Computed sets
         */
        /** @var array sets */
        $this->sets = array();

        // Define paths
        $this->sets['paths'] = array();
        $this->sets['paths']['server_url'] = (isset($_SERVER['HTTPS']) ? 'https' : 'http')
            . '://' . array_ifnull($_SERVER, 'HTTP_X_FORWARDED_SERVER', $_SERVER['HTTP_HOST'])
            . (in_array($_SERVER['SERVER_PORT'], array(80, 443)) ? '' : ":{$_SERVER['SERVER_PORT']}");
        $this->sets['paths']['doc_root'] = str_replace( '\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) );
        $this->sets['paths']['app_root'] = str_replace( '\\', '/', dirname(__FILE__) );
        $this->sets['paths']['temp_root'] = realpath( sys_get_temp_dir() ) . '/' . $this->conf['app_id'];
        $this->sets['paths']['app_from_doc'] = substr( $this->sets['paths']['app_root'], strlen($this->sets['paths']['doc_root']) );
        //$this->sets['paths']['mod_from_doc'] = "{$this->sets['paths']['app_from_doc']}/{$this->request['locale']}";

        $this->sets['public_locales'] = array();

        // Error handling and logging
        error_reporting( $this->conf['debug'] ? E_ALL : 0 );
        set_error_handler( array(&$this, 'errorHandler') );

        // Find out the limits
        $this->conf['post_max_size']        = string_to_byte( ini_get('post_max_size') );
        $this->conf['upload_max_filesize']  = string_to_byte( ini_get('upload_max_filesize') );

        // Check for required extensions
        $required_extensions = array( 'curl', 'iconv', 'intl', 'json', 'libxml', 'mbstring', 'mysqli', 'openssl', 'xml', 'zip', 'zlib' );
        foreach ( $required_extensions as $required_extension )
        {
            if ( !extension_loaded($required_extension) )
            {
                $this->quit( "Error finding PHP extension '$required_extension'." );
            }
        }

        // Set internal settings
        mb_internal_encoding( 'utf-8' );
        date_default_timezone_set( 'UTC' );
        libxml_use_internal_errors( TRUE );

        /***********************************************************************
         * Database
         */
        try
        {
            $this->db = new RDBMS( "$db_type:host=$db_host;dbname=$db_schema", $db_user, $db_pass );
            $this->db->setAttribute( PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC );
            $this->db->exec( 'SET CHARACTER SET utf8mb4' );
            $this->db->exec( "SET NAMES 'utf8mb4'" );
            db::Instance()->setDb( $this->db );                 // To allow classes that do not use the kernel instance to use the database connection
        }
        catch ( PDOException $e )
        {
            $this->quit( 'Error connecting to database.', $e->getMessage() );
        }

        /***********************************************************************
         * Override configuration
         */
        $query = 'SELECT name, value FROM configurations';
        $exported_conf = $this->get_set_from_db( $query );
        foreach ( $exported_conf as $name => $exported_value )
        {
            $this->conf[$name] = eval( "return $exported_value;" );
        }
        $this->conf['escaped_timezone'] = $this->db->escape( $this->conf['timezone'] );

        // extra path settings
        $this->sets['paths']['default_server_url'] = $this->conf['default_domain'] ?
            (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $this->conf['default_domain'] . (in_array($_SERVER['SERVER_PORT'], array(80, 443)) ? '' : ":{$_SERVER['SERVER_PORT']}") : $this->sets['paths']['server_url'];
        $this->sets['paths']['mobile_server_url'] = $this->conf['mobile_domain'] ?
            (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $this->conf['mobile_domain'] . (in_array($_SERVER['SERVER_PORT'], array(80, 443)) ? '' : ":{$_SERVER['SERVER_PORT']}") : $this->sets['paths']['server_url'];

        // Define BOM (Byte Order Mark)
        // http://en.wikipedia.org/wiki/Byte_Order_Mark
        $this->sets['bom'] = array(
            'utf-8' => chr(239) . chr(187) . chr(191),
            'utf-16be' => chr(254) . chr(255),
            'utf-16le' => chr(255) . chr(254),
            'utf-32be' => 0 . 0 . chr(254) . chr(255),
            'utf-32le' => chr(255) . chr(254) . 0 . 0
        );

        // Define attributes for spreadsheet file types
        $this->sets['spreadsheet_type_attributes'] = array(
            'csv' => array(
                'class' => 'Csv',
                'mimetype' => 'text/csv',
                'file_extension' => 'csv'
            ),
            'xls' => array(
                'class' => 'Xls',
                'mimetype' => 'application/vnd.ms-excel',
                'file_extension' => 'xls'
            ),
            'xlsx' => array(
                'class' => 'Xlsx',
                'mimetype' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'file_extension' => 'xlsx'
            )
        );
        $this->conf['spreadsheet_type_attributes'] = $this->sets['spreadsheet_type_attributes'][$this->conf['spreadsheet_type']];

        /***********************************************************************
         * Dictionary
         */
        $query = 'SELECT alias FROM locales WHERE site='.$this->db->escape('admin_site').' AND enabled=1 AND `default`=1';
        $statement = $this->db->query($query);
        if($record = $statement->fetch())
        {
            require( "{$this->sets['paths']['app_root']}/locale/{$record['alias']}.php" );
            $this->dict = $DICT;

            require( "{$this->sets['paths']['app_root']}/locale/{$record['alias']}_def.php" );
            $this->entity_admin_def = $ENTITY_ADMIN_DEF;
            foreach ( $this->entity_admin_def as $entity_admin => $entity_admin_def )
            {
                $this->dict['SET_modules'][$entity_admin] = $entity_admin_def['name'];
            }
        }

        // Define locales
        $default_locale = "";
        $locales = array();

        $sql = 'SELECT alias, `name`, `default`, `site` FROM locales WHERE'
            . ' enabled = 1 ORDER BY `default` DESC, order_index ASC';
        $statement = $this->db->query($sql);
        while($row = $statement->fetch()) {
            if($this->request['site'] == $row['site']) {
                $locales[] = $row['alias'];
                $this->sets['locales'][$row['alias']] = $row['name'];

                if(!$default_locale || $row['default'])
                    $default_locale = $row['alias']; // if request "public site", $default_locale is global default locale
            }

            if($row['site'] == 'public_site') {
                $this->sets['public_locales'][$row['alias']] = $row['name'];
                if($row['default'] && is_null($this->default_public_locale)) {
                    $this->default_public_locale = $row['alias']; // global default locale
                }
            }
        }

        // Filter out public locales without public content
        if ( count($this->request['path_segments']) == 0 && count($locales) > 0 )
        {
            $sql = "SELECT DISTINCT locale FROM webpages AS w";
            $sql .= " JOIN webpage_platforms AS wp ON (w.domain = wp.domain AND w.id = wp.webpage_id AND w.major_version = wp.major_version AND w.minor_version = wp.minor_version AND wp.platform = 'desktop')";
            $sql .= ' JOIN webpage_locales AS wl ON (wl.domain = w.domain AND wl.webpage_id = w.id AND w.major_version = wl.major_version AND w.minor_version = wl.minor_version)';
            $sql .= " WHERE w.domain = 'public' AND w.shown_in_site = 1";
            $sql .= ' AND UTC_TIMESTAMP() BETWEEN IFNULL(wl.publish_date, UTC_TIMESTAMP()) AND IFNULL(wl.removal_date, UTC_TIMESTAMP())';
            $sql .= ' AND wp.deleted = 0 AND wl.locale IN (' . implode( ', ', array_map(array($this->db, 'escape'), $locales) ) . ')';
            $locales = $this->get_set_from_db( $sql );
            $public_locales = $this->sets['public_locales'];
            $this->sets['public_locales'] = array();
            foreach ( $public_locales as $locale => $locale_name )
            {
                if ( in_array($locale, $locales) )
                {
                    $this->sets['public_locales'][$locale] = $locale_name;
                }
            }
        }

        //overwrite the requested locale for public site
        if($this->request['site'] == 'public_site')
        {
            /*
            $userip = '';
            // Geo IP Logic
            $geoip_locale = '';
            $reader = new Reader("{$this->sets['paths']['app_root']}/include/GeoIP2/GeoLite2-Country.mmdb");
            if (isset($_SERVER['HTTP_CLIENT_IP'])) {
                $userip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $userip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            } elseif(isset($_SERVER['REMOTE_ADDR'])) {
                $userip = $_SERVER['REMOTE_ADDR'];
            }
            //echo print_r($_SERVER);

            try {
                $record = $reader->country($userip);
                $sql = 'SELECT alias FROM country_default_locale cdl LEFT JOIN locales l ON (l.id=cdl.locale_id) WHERE iso_code='.$this->db->escape($record->country->isoCode);
                $statement = $this->db->query($sql);
                $rows = $statement->fetchAll();
                if(count($rows)>0 && $rows[0]['alias'] != '')
                {
                    if(isset($_COOKIE['default_loc']) && $_COOKIE['default_loc'] !='')
                        $geoip_locale = $_COOKIE['default_loc'];
                    else
                        $geoip_locale = $rows[0]['alias'];

                    // Overwrite request locale with the country default locale if set;
                    if($this->request['locale']== '')
                    {
                        //$this->request['locale'] = $rows[0]['alias'];
                        //$default_locale = $rows[0]['alias'];
                        $this->request['locale'] = $geoip_locale;
                        $default_locale = $geoip_locale;
                    }
                }
            } catch(Exception $e) {
            }
            */

            if ( array_key_exists('default_loc', $_COOKIE) && array_key_exists($_COOKIE['default_loc'], $this->sets['public_locales']) )
            {
                $default_locale = $_COOKIE['default_loc'];
            }
            else if ( array_key_exists('HTTP_ACCEPT_LANGUAGE', $_SERVER) )
            {
                // PHP's locale_accept_from_http depands on system locales, so a custom parser is used instead
                // Break up string into pieces (languages and q factors)
                // http://www.thefutureoftheweb.com/blog/use-accept-language-header
                preg_match_all( '/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $_SERVER['HTTP_ACCEPT_LANGUAGE'], $lang_parse );
                if ( count($lang_parse[1]) )
                {
                    // Create a list like "en" => 0.8
                    $langs = array_combine( $lang_parse[1], $lang_parse[4] );

                    // Set default to 1 for any without q factor
                    foreach ( $langs as $lang => $val )
                    {
                        if ( $val === '' )
                            $langs[$lang] = 1;
                    }

                    // Sort list based on value
                    arsort( $langs, SORT_NUMERIC );

                    // Expand the locales
                    $locales = $this->sets['public_locales'];
                    foreach ( $locales as $locale => $locale_name )
                    {
                        $locales[$locale] = $locale;
                        if ( strpos($locale, '-') > 0 )
                        {
                            $locale_key = current( explode('-', $locale) );
                            if ( !array_key_exists($locale_key, $locales) )
                            {
                                $locales[$locale_key] = $locale;
                            }
                        }
                    }
                    if ( array_key_exists('zh-hans', $locales) )
                    {
                        $locales['zh-cn'] = 'zh-hans';
                        $locales['zh-my'] = 'zh-hans';
                        $locales['zh-sg'] = 'zh-hans';
                    }
                    if ( array_key_exists('zh-hant', $locales) )
                    {
                        $locales['zh-hk'] = 'zh-hant';
                        $locales['zh-mo'] = 'zh-hant';
                        $locales['zh-tw'] = 'zh-hant';
                    }

                    // Try to use the most preferred locale
                    foreach ( $langs as $lang => $q )
                    {
                        $locale_key = locale_lookup( array_keys($locales), $lang, FALSE );
                        if ( $locale_key )
                        {
                            $default_locale = $locales[$locale_key];
                        }
                        break;
                    }
                }
            }
        }

        // Override previous locale detection if:
        // No valid locale specified in the path; and
        // Valid locale specified in the query string, which was added by mod_rewrite based on domain name
        $locale = array_ifnull( $_GET, 'locale', '' );
        if ( !array_key_exists($this->request['locale'], $this->sets['public_locales']) && array_key_exists($locale, $this->sets['public_locales']) )
        {
            $default_locale = $geoip_locale = $locale;
        }

        /*
        if(isset($_COOKIE['default_loc']) && $_COOKIE['default_loc'] !='' && $this->request['locale']== '')
            $this->request['locale'] = $_COOKIE['default_loc'];
        */

        // Set default locale in cookie
        if(array_key_exists($this->request['locale'], $this->sets['public_locales']))
            $this->set_cookie( 'default_loc', $this->request['locale'], time()+2592000 );

        $redirect = FALSE;

        if(!$this->cli) {
            $redirect_code = 302;

            // Logic for vanity URL redirect
            $vanity_redirect = false;
            $external_redirect = false;
            if(count($path_segments)>0 && $path_segments[0] != '')
            {
                $vanity_url = implode('/', $path_segments);
                $vanity_url = preg_replace('/\/$/i', '', $vanity_url);

                $sql = 'SELECT id, redirect_to, redirect_to_id FROM vanity_urls WHERE active=1 AND deleted=0 AND vanity_url_alias='.$this->db->escape(urldecode($vanity_url)).' AND UTC_TIMESTAMP() BETWEEN start_date AND IFNULL(end_date, UTC_TIMESTAMP()) LIMIT 0,1';
                $statement = $this->db->query($sql);
                $rows = $statement->fetchAll();
                if(count($rows)==1)
                {
                    $r = $rows[0];

                    if($r['redirect_to_id'] == 0 && $r['redirect_to'] != '')
                        $external_redirect = true;
                    elseif($r['redirect_to_id']>0)
                    {
                        $sql = 'SELECT path FROM webpage_platforms WHERE webpage_id='.$r['redirect_to_id'].' AND domain='.$this->db->escape('public');
                        $statement = $this->db->query($sql);
                        $rows = $statement->fetchAll();
                        if(count($rows)>0)
                        {
                            $path_info = $r['redirect_to'] = $rows[0]['path'];
                            $internal_segments = explode('/', $r['redirect_to']);
                            $this->request['path_segments'] = $path_segments = array();
                            foreach($internal_segments as $seg)
                            {
                                if($seg != '')
                                    $this->request['path_segments'][] = $path_segments[] = $seg;
                            }
                            $path_segments[] = '';
                        }
                    }

                    if($r['redirect_to'] != '')
                        $vanity_redirect = true;
                    else {
                        $vanity_redirect = false;
                    }
                }
            }

            if($vanity_redirect)
            {
                $redirect = true;
                $redirect_code = 302;
                // Insert vanity url redirect record
                $sql = sprintf('INSERT INTO vanity_url_trackings (vanity_url, redirect_to, visitor_ip, visitor_country, visit_time)'
                     . 'VALUES(%1$s, %2$s, %3$s, %4$s, UTC_TIMESTAMP())'
                     , $this->db->escape(urldecode($vanity_url))
                     , $this->db->escape($r['redirect_to'])
                     , $this->db->escape($userip)
                     , $this->db->escape($record->country->isoCode)
                     );
                $this->db->exec($sql);
            }
            //else
            //{
                // Add compatability of the old style locale structure of public site
                if(preg_match('/\//', $this->request['locale']))
                {
                    $this->request['old_locale'] = $path_segments[0];
                }
                else
                    $this->request['old_locale'] = '';

                if(!$this->request['locale']) {
                    $this->request['locale'] = $default_locale;

                    $redirect = TRUE;
                    $redirect_code = 302;
                } elseif(!array_key_exists($this->request['locale'], $this->sets['locales']) && !array_key_exists($this->request['old_locale'], $this->sets['locales'])) {

                    if($vanity_redirect)
                        $this->request['locale'] = $geoip_locale!='' ? $geoip_locale : $default_locale;
                    else
                        $this->request['locale'] = $default_locale;

                    $i = 0;
                    foreach(array_keys($this->dict['SET_reserved_words']) as $alias) {
                        if(preg_match("#^" . preg_quote($this->sets['paths']['app_from_doc'] . '/' . $alias . '/', "#") . "#", '/' . implode('/', $path_segments))) {
                            break;
                        }

                        $i++;
                    }

                    if(count(array_keys($this->dict['SET_reserved_words'])) == $i) {
                        $redirect = TRUE;
                        if(count($this->request['path_segments'])) {
                            $redirect_code = 302;
                        }
                    }

                    if($path_segments[0])
                        array_unshift($path_segments, '');
                } else {
                    // replace locale segment width empty segment
                    $path_segments[0] = '';

                    // remove locale path segment
                    array_shift($this->request['path_segments']);

                    // remove the second part of locales path segment as it is in format country/locale/ currently
                    if($this->request['site'] == 'public_site')
                    {
                        if(array_key_exists($this->request['locale'], $this->sets['locales'])) // remove the second part of locales path segment as it is in format country/locale/ currently
                        {
                            array_shift($this->request['path_segments']);
                            if(preg_match('#/#', $this->request['locale']))
                            {
                                $path_segments[1] = '';
                                array_shift($path_segments);
                            }

                        }
                        else if(array_key_exists($this->request['old_locale'], $this->sets['locales'])) // or just use the old locale
                        {
                            $this->request['locale'] = $this->request['old_locale'];
                            $redirect = FALSE;
                        }
                    }


                    if(!preg_match('#\/$#', $path_info)) {
                        // found locale but no backslash at the end of the path
                        $redirect = TRUE;
                    }
                }
            //}
        }

        if($redirect) {
            if($external_redirect)
            {
                header('Location: '.$r['redirect_to'], TRUE, $redirect_code);
            }
            else {
                /*if($vanity_redirect)
                {
                    echo sprintf('Location: %s/%s%s%s%s',
                    str_replace( '\\', '/', substr(
                        realpath(dirname($_SERVER['SCRIPT_FILENAME'])),
                        strlen(realpath($_SERVER['DOCUMENT_ROOT']))
                    ) ),
                    $this->request['site'] == 'admin_site' ? 'admin/' : '',
                    $this->request['locale'],
                    implode( '/', $path_segments ),
                    $_SERVER['QUERY_STRING'] === '' ? '' : "?{$_SERVER['QUERY_STRING']}"
                );exit;
                }*/
                header( sprintf('Location: %s/%s%s%s%s',
                    str_replace( '\\', '/', substr(
                        realpath(dirname($_SERVER['SCRIPT_FILENAME'])),
                        strlen(realpath($_SERVER['DOCUMENT_ROOT']))
                    ) ),
                    $this->request['site'] == 'admin_site' ? 'admin/' : '',
                    $this->request['locale'],
                    implode( '/', $path_segments ),
                    $_SERVER['QUERY_STRING'] === '' ? '' : "?{$_SERVER['QUERY_STRING']}"
                ), TRUE, $redirect_code );
            }

            // exit directly if redirect
            exit;
        }

        $prefix_path = "";

        if($this->request['site'] == 'public_site') {
            // localized configurations
            $sql = sprintf('SELECT * FROM configurations_locale WHERE locale IN(%s) ORDER BY locale'
                , implode(', ', array_map(array($this->db, 'escape'), $locales))
            );
            $statement = $this->db->query($sql);
            while($row = $statement->fetch()) {
                $this->conf[$row['locale']][$row['name']] = $row['value'];
            }

            $prefix_path = "{$this->sets['paths']['app_from_doc']}/";
        } else {
            $prefix_path = "{$this->sets['paths']['app_from_doc']}/admin/";
        }

        $this->sets['paths']['mod_from_doc'] = $prefix_path . $this->request['locale'];

        // Define locale URLs for current page
        $this->sets['locale_urls'] = array();
        foreach ( $this->sets['locales'] as $locale => $locale_name )
        {
            $this->sets['locale_urls'][$locale] = $prefix_path . $locale . implode('/', $path_segments);
        }

        //Public site template dictionary
        if($this->request['site'] == 'public_site')
        {
            if ( $this->cli )
            {
                $this->request['locale'] = $default_locale;
            }

            //escape request_locale
            $this->request['escaped_locale'] = preg_replace('/\//', '~', $this->request['locale']);
            $f = "{$this->sets['paths']['app_root']}/file/template/locale/{$this->request['escaped_locale']}.txt";

            if(file_exists($f)) {
                $dict = array();
                $cache_path = $this->sets['paths']['app_root'] . '/file/cache/' . md5($f) . '.locale_tpl';

                if(file_exists($cache_path)) {
                    try {
                        $dict = unserialize(file_get_contents($cache_path));
                        if(!$dict || !is_array($dict)) {
                            throw new Exception("");
                        }
                    } catch(Exception $e) {
                        $dict = array();
                    }
                }

                if(!count($dict)) {
                    $dict = kernel::decode_locale_file($f);

                    if(count($dict)) {
                        file_put_contents( $cache_path, serialize($dict) );
                    }
                }

                $this->dict = array_merge($this->dict, $dict);
            }
        }

        // Try to set system locale
        // Required for pathinfo to function properly
        setlocale( LC_ALL, 'en_US.UTF-8' );

        /***********************************************************************
         * Response
         */
        $this->response = array(
            'status_code' => 200,
            'module' => '',
            'content' => '',
            'mimetype' => 'text/html',
            'filename' => 'index.html',
            'charset' => 'utf-8',
            'direction' => $this->dict['VALUE_direction'],
            'disposition' => 'inline',
            'refresh' => '',
            'bodyCls' => array()
        );

        /***********************************************************************
         * Control
         */
        $this->control = array(
            'editor_media_subfolder'    => '',                  // Path of TinyMCE media file subfolder relative to "[app_root]/file/"
            'editor_content_css_file'   => 'style/index.css',   // Path of TinyMCE content CSS file relative to "[app_root]/"
            'editor_body_id'            => ''                   // Body ID of TinyMCE
        );

        /***********************************************************************
         * PHPMailer
         */
        /** @var PHPMailer mailer */
        $this->mailer = new PHPMailer();
        $this->mailer->IsSMTP();
        $this->mailer->SMTPAuth = $this->conf['mailer_smtp_auth'];
        $this->mailer->SMTPKeepAlive = TRUE;
        $this->mailer->Host = $this->conf['mailer_smtp_host'];
        $this->mailer->Port = $this->conf['mailer_smtp_port'];
        $this->mailer->SMTPSecure = $this->conf['mailer_smtp_secure'];
        $this->mailer->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        $this->mailer->Username = $this->conf['mailer_smtp_username'];
        $this->mailer->Password = $this->conf['mailer_smtp_password'];
        $this->mailer->From = $this->conf['mailer_email'];
        $this->mailer->FromName = $this->conf['mailer_name'];
        $this->mailer->IsHTML( TRUE );
        $this->mailer->CharSet = 'utf-8';

        /***********************************************************************
         * Smarty Template Engine
         */
        /** @var Smarty smarty */
        $this->smarty = new Smarty();
        $this->smarty->config_dir = "{$this->sets['paths']['app_root']}/conf";
        $this->smarty->template_dir = $this->sets['paths']['app_root'];
        $this->smarty->compile_dir = "{$this->sets['paths']['temp_root']}/templates_c";
        $this->smarty->cache_dir = "{$this->sets['paths']['temp_root']}/cache";
        if ( !force_mkdir($this->sets['paths']['temp_root']) )
        {
            $this->quit( 'Error creating temporary directory.', $this->sets['paths']['temp_root'] );
        }
        force_mkdir( $this->smarty->compile_dir );
        force_mkdir( $this->smarty->cache_dir );
        $this->smarty->assignByRef( 'request', $this->request );
        $this->smarty->assignByRef( 'response', $this->response );
        $this->smarty->assignByRef( 'control', $this->control );
        $this->smarty->assignByRef( 'sets', $this->sets );
        $this->smarty->assignByRef( 'conf', $this->conf );
        $this->smarty->assignByRef( 'dict', $this->dict );
        $this->smarty->assignByRef( 'entity_admin_def', $this->entity_admin_def );
        $this->smarty->assignByRef( 'mailer', $this->mailer );
        $this->smarty->assignByRef( 'default_public_locale', $this->default_public_locale );
        $this->smarty->assignByRef( '_cookie', $_COOKIE );
        $this->smarty->assignByRef( '_get', $_GET );
        $this->smarty->assignByRef( '_post', $_POST );
        $this->smarty->assignByRef( '_server', $_SERVER );

        /***********************************************************************
         * AWS S3 client
         */
        /** @var S3Client s3 */
        if ( $this->conf['aws_enabled'] )
        {
            $s3_regional_endpoint = 's3_' . str_replace( '-', '_', $this->conf['s3_region'] ) . '_regional_endpoint';
            $this->s3 = Aws\S3\S3Client::factory( array(
                'version' => 'latest',
                'region' => $this->conf['s3_region'],
                'credentials' => array(
                    'key' => $this->conf['aws_access_key'],
                    'secret' => $this->conf['aws_secret_key']
                ),

                // Avoid warnings due to open_basedir restriction
                // https://github.com/aws/aws-sdk-php/issues/1931
                'use_arn_region' => \Aws\S3\UseArnRegion\ConfigurationProvider::fallback(),
                'csm' => \Aws\ClientSideMonitoring\ConfigurationProvider::fallback(),
                'retries' => \Aws\Retry\ConfigurationProvider::fallback(),
                $s3_regional_endpoint => \Aws\S3\RegionalEndpoint\ConfigurationProvider::fallback(),
            ) );
        }

        /***********************************************************************
         * Session
         */
        if ( !isset($_SESSION) )
        {
            // Session ID keeps changing in Safari/Chrome if non-standard port is used and path is not '/'
            $path = array_key_exists( 'HTTP_USER_AGENT', $_SERVER )
                && strpos( $_SERVER['HTTP_USER_AGENT'], 'KHTML' )
                && $_SERVER['SERVER_NAME'] != $_SERVER['HTTP_HOST']
                ? '/' : "{$this->sets['paths']['app_from_doc']}/";

            // Domain must be a valid domain name (e.g. cannot be localhost)
            // http://blog.perplexedlabs.com/2008/12/21/php-sessions-on-localhost/
            $domain = current( explode(':', $_SERVER['SERVER_NAME']) );
            $domain = preg_match( '/^([a-z0-9]+[a-z0-9\-]*[a-z0-9]+)\.([a-z]+[a-z\.]*[a-z]+)$/i', $domain ) == 0 ? '' : $domain;

            // Check session ID
            $session_name = session_name();
            $session_id = '';
            if ( isset($_COOKIE[$session_name]) )
            {
                $session_id = $_COOKIE[$session_name];
            }
            else if ( isset($_GET[$session_name]) )
            {
                $session_id = $_GET[$session_name];
            }
            if ( !preg_match('/^[-,a-zA-Z0-9]{1,128}$/', $session_id) )
            {
                session_id( uniqid() );
            }

            session_set_cookie_params(
                0,                          // Lifetime
                $path,                      // Path
                $domain,                    // Domain
                isset($_SERVER['HTTPS']),   // Secure
                TRUE                        // HttpOnly
            );
            session_start();
        }

        self::$instance = $this; // define the instance
    }

    /**
     * Destructor.
     *
     * @since   2008-10-31
     */
    function close()
    {
    }

    /**
     * Custom error logging function
     * http://www.php.net/errorfunc
     *
     * @since   2008-10-31
     * @param   code            The error code
     * @param   description     The error description
     * @param   path            The path to the file that generated the error
     * @param   line_number     The line number of the code that generated the error
     * @param   vars            The variable trace for user errors
     */
    function errorHandler( $code, $description, $path, $line_number, $vars )
    {
        // PHP predefined error codes
        $code_types = array(
            'error' => array(
                E_ERROR => 'PHP Error',
                E_PARSE => 'PHP Parsing Error',
                E_CORE_ERROR => 'PHP Core Error',
                E_COMPILE_ERROR => 'PHP Compile Error',
                E_USER_ERROR => 'PHP User Error'
            ),
            'warning' => array(
                E_WARNING => 'PHP Warning',
                E_CORE_WARNING => 'PHP Core Warning',
                E_COMPILE_WARNING => 'PHP Compile Warning',
                E_USER_WARNING => 'PHP User Warning'
            ),
            'notice' => array(
                E_NOTICE => 'PHP Notice',
                E_USER_NOTICE => 'PHP User Notice'
            )
        );

        // Sniff for error type
        $type = '';
        $type_name = '';
        foreach ( $code_types as $code_type => $codes )
        {
            if ( array_key_exists($code, $codes) )
            {
                $type = $code_type;
                $type_name = $codes[$code];
            }
        }

        // Log error (warning and notice from PHP library like Smarty is ignored)
        $temp_dir = realpath( sys_get_temp_dir() );
        $include_dir = realpath( "{$this->sets['paths']['app_root']}/include" );
        $file_admin_include_dir = realpath( "{$this->sets['paths']['app_root']}/module/file_admin/include" );
        if ( $type === 'error' || ($type !== ''
            && strncasecmp($path, $temp_dir, strlen($temp_dir)) !== 0
            && strncasecmp($path, $include_dir, strlen($include_dir)) !== 0
            && strncasecmp($path, $file_admin_include_dir, strlen($file_admin_include_dir)) !== 0
            && strpos($description, 'unserialize(): Error at offset') !== 0) )
        {
            $desc = str_replace( ']: ', "]\r\n\r\n", strip_tags($description) );
            $this->log( $type, "$type_name: $desc", $path, $line_number );
        }
    }

    /**
     * Die with error log.
     *
     * @since   2008-10-31
     * @param   title           The error title
     * @param   description     The error description
     * @param   path            The path
     * @param   line_number     The line number
     */
    function quit( $title, $description = '', $path = __FILE__, $line_number = __LINE__ )
    {
        header( 'Content-Type: text/plain; charset=utf-8' );
        $output = $title . ($description === '' ? '' : "\r\n\r\n$description");
        $this->log( 'error', $output, $path, $line_number );
        $output .= "\r\n\r\nIf problem persists, please contact the system administrator.";
        exit( $output );
    }

    /**
     * Log a message.
     *
     * @since   2008-10-31
     * @param   type            The type
     * @param   description     The description
     * @param   file_path       The file path
     * @param   line_number     The line number
     * @return  bool Success or not
     */
    function log( $type, $description, $file_path = __FILE__, $line_number = __LINE__ )
    {
        if ( $this->db )
        {
            $sql = 'INSERT INTO logs(type, locale, module, description,';
            $sql .= ' file_path, line_number, ip_address, user_agent,';
            $sql .= ' referer_uri, request_uri, logged_date)';
            $sql .= ' VALUES (:type, :locale, :module, :description,';
            $sql .= ' :file_path, :line_number, :ip_address, :user_agent,';
            $sql .= ' :referer_uri, :request_uri, UTC_TIMESTAMP())';
            $statement = $this->db->prepare( $sql );
            return $statement->execute( array(
                ':type' => $type,
                ':locale' => $this->request['locale'],
                ':module' => $this->response['module'],
                ':description' => $description,
                ':file_path' => $file_path,
                ':line_number' => $line_number,
                ':ip_address' => array_ifnull( $_SERVER, 'REMOTE_ADDR', NULL ),
                ':user_agent' => array_ifnull( $_SERVER, 'HTTP_USER_AGENT', NULL ),
                ':referer_uri' => array_ifnull( $_SERVER, 'HTTP_REFERER', NULL ),
                ':request_uri' => array_ifnull( $_SERVER, 'REQUEST_URI', NULL )
            ) );
        }

        return FALSE;
    }

    /**
     * Sends a redirect response to the client using the specified URL.
     *
     * @since   2008-10-31
     * @param   url     A URL
     * @param   code    An optional response code
     */
    function redirect( $url, $code = 302 )
    {
        // Fix empty URL
        if ( $url === '' )
        {
            $url = '.';
        }

        // Fix URL for WebKit
        if ( strlen($url) > 0 && $url[0] === '?' )
        {
            $url = ".$url";
        }

        HttpResponse::sendRedirect( $url, $code );
        $this->response['status_code'] = $code;
        $this->response['content'] = '';
    }

    /**
     * Sends an authentication challenge to the client.
     *
     * @since   2012-12-09
     */
    function send_challenge()
    {
        header( sprintf('WWW-Authenticate: Basic realm="%s"', $this->dict['APP_title']) );
        header( 'HTTP/1.0 401 Unauthorized' );

        $this->response['status_code'] = 401;
    }

    /**
     * Process the request.
     *
     * @since   2008-10-31
     */
    function process()
    {
        // Select module to load
        if($this->request['site'] == 'admin_site')
            $module_name = 'admin';
        else
            $module_name = 'default';
        $module_segments = array();


        for ( $i = 0; $i < count($this->request['path_segments']); $i++ )
        {
            $sliced_path_segments = array_slice($this->request['path_segments'], 0, $i+1);
            if($this->request['site'] == 'admin_site')
            {
                $module_name_sets = $sliced_path_segments;
                $module_name_sets[] = $module_name;

                $possible_module_name = implode( '_', $module_name_sets );

                if ( array_key_exists($possible_module_name, $this->dict['SET_modules']) )
                {
                    $module_name = $possible_module_name;
                    $module_segments = $sliced_path_segments;

                    break;
                }
            }
            else
            {
                switch(strtolower($this->request['path_segments'][0])) {
                    case 'preview':
                        $this->request['site'] == 'admin_site';
                        $module_name = 'preview';
                        break;
                    case 'cron':
                        $this->request['site'] == 'admin_site';
                        $module_name = 'cron';
                        break;
                    case 'gadget':
                        $module_name = 'gadget';
                        break;
                }

                break;
            }
        }

        // Check the module
        if ( !array_key_exists($module_name, $this->dict['SET_modules']) )
        {
            $this->quit( "Error using module '$module_name'." );
        }
        if ( $module_name != 'default' && $module_name != 'preview' )
        {
            // admin
            if(count($module_segments)>0)
                $this->sets['paths']['mod_from_doc'] .= '/' . implode( '/', $module_segments );

            $this->response['titles'][] = $this->dict['SET_modules'][$module_name];
        }

        $this->response['module'] = $module_name;
        $this->response['filename'] = "{$this->request['locale']}+" . implode( '+', array_slice($this->request['path_segments'], 1) ) . 'index.html';
        require( "{$this->sets['paths']['app_root']}/module/$module_name/index.php" );

        // Process the request
        $class_name = "{$module_name}_module";

        try {
            $instance = new $class_name( $this );
            kernel::$module =& $instance;

            // no module could be found - for admin module
            if(count($this->request['path_segments']) && $module_name == 'admin' && !$this->cli) {
                $user = kernel::$module->user;
                if(!isset($user) || !is_array($user) || !isset($user['id']) || !$user['id']) {
                    throw new loginException($this->dict['MESSAGE_login_to_continue'], null, "{$this->sets['paths']['app_from_doc']}/admin/{$this->request['locale']}/"
                        . '?redirect_url=' . urlencode(array_ifnull($_SERVER, 'REQUEST_URI', "{$this->sets['paths']['mod_from_doc']}/")));
                }
                $url = $this->sets['paths']['app_from_doc'] . '/admin/' . $this->request['locale'] . '/';
                if($_SERVER['QUERY_STRING']) {
                    $url .= '?' . $_SERVER['QUERY_STRING'];
                }
                throw new requestException('module_not_found', $url);
            }

            $instance->process();
            $instance->output();
            $instance->close();
        } catch(Exception $e) {
            $instance->processException($e);
        }
    }

    /**
     * Output the response.
     *
     * @since   2008-10-31
     */
    function output()
    {
        // Try to minify for HTML content
        if ( $this->response['mimetype'] == 'text/html' )
        {
            try
            {
                $this->response['content'] = Minify_HTML::minify(
                    $this->response['content'],
                    array(
                        'cssMinifier' => array( 'Minify_CSS', 'minify' ),
                        'jsMinifier' => array( 'JSMin\\JSMin', 'minify' )
                    )
                );
            }
            catch ( Exception $e )
            {
                // Nothing
            }
        }

        // Change character set, if needed
        if ( $this->response['charset'] !== '' && $this->response['charset'] !== 'utf-8' )
        {
            $this->response['content'] = iconv( 'utf-8', "{$this->response['charset']}//TRANSLIT", $this->response['content'] );
        }

        // Output response finally
        $response = new HttpResponse();
        if ( $this->response['disposition'] !== '' )
        {
            // Workaround for Internet Explorer bugs
            // http://support.microsoft.com/kb/234067
            if ( is_ie() )
            {
                $response->setResponseHeader( 'Pragma', 'no-cache' );
                $response->setResponseHeader( 'Cache-Control', 'no-cache' );
                $response->setResponseHeader( 'Expires', '-1' );
                $this->response['filename'] = rawurlencode( $this->response['filename'] );
            }

            $filename = $this->response['filename'] !== '' ? "; filename=\"{$this->response['filename']}\"" : '';
            $response->setResponseHeader( 'Content-Disposition', $this->response['disposition'] . $filename );
            $response->setResponseHeader( 'Content-Language', $this->request['locale'] );
        }
        if ( $this->response['refresh'] !== '' )
        {
            $response->setResponseHeader( 'Refresh', $this->response['refresh'] );
        }
        $response->send(
            $this->response['content'],
            $this->response['charset'],
            $this->response['mimetype'],
            $this->response['status_code']
        );
    }

    /**
     * Generate a list from database using Smarty
     *
     * @since   2008-11-05
     * @param   name    The name of the cookie
     * @param   value   The value of the cookie
     * @param   expire  The time the cookie expires
     * @return  The list content and its summary
     */
    function set_cookie( $name, $value = '', $expire = 0 )
    {
        setcookie(
            $name,
            $value,
            $expire,
            "{$this->sets['paths']['app_from_doc']}/",
            $_SERVER['SERVER_NAME'],
            isset( $_SERVER['HTTPS'] ),
            $this->request['site'] == 'public_site'
        );
    }

    /**
     * Get a set from database, where the first and second column
     * of the query are used as the key and value respectively.
     *
     * If there is only one column, that column is used for value.
     * Integer keys are generated instead.
     *
     * @since   2008-11-10
     * @param   query   The SQL query
     * @return  An associative array
     */
    function get_set_from_db( $query )
    {
        $assoc = array();
        $statement = $this->db->query( $query );
        while ( $record = $statement->fetch(PDO::FETCH_NUM) )
        {
            if ( count($record) > 1 )
            {
                $assoc[$record[0]] = $record[1];
            }
            else
            {
                $assoc[] = $record[0];
            }
        }
        return $assoc;
    }

    /**
     * Generate a list table using smarty with the sql and data provided
     *
     * @param int|string $id              An unique identifier of the list
     * @param int|string $primary_key     The primary key of the query
     * @param string     $query           An associative array of SQL elements
     * @param array      $record_input    The input type and value(s) for the record selector
     * @param array      $record_actions  The actions related to individual record
     * @param array      $list_actions    The actions related to the whole list
     * @param string     $hash            The optional hash to append after the order and pagination links
     * @param string     $filename        The optional filename of the smarty template to use
     * @param array      $html_fields     Fields that display in html
     * @param array      $action_hashes   The hashes append to the action url
     * @param array      $hidden_fields   Fields that do not display in the list
     * @param array      $field_actions   fields that have a link in it
     * @param array      $actions_ref      the actions in action fields that do not use id as the key
     * @param array      $dependent_actions the actions that dependent on the result
     * @return array
     */
    function get_smarty_list_from_db( $id, $primary_key, $query,
        $record_input = array(), $record_actions = array(),
        $list_actions = array(), $hash = '', $filename = 'list.html'
        , $html_fields = array(), $action_hashes = array()
        , $hidden_fields = array(), $field_actions = array()
        , $actions_ref = array(), $dependent_actions = array())
    {
        // Get parameters from query string
        $order_by = trim( array_ifnull($_GET, "{$id}_order_by", $query['default_order_by']) );
        $order_dir = trim( strtoupper(array_ifnull($_GET, "{$id}_order_dir", $query['default_order_dir'])) );
        $page_index = $this->conf['page_enabled'] && isset( $_GET["{$id}_page"] ) ? $_GET["{$id}_page"] : 0;

        // Cleanup parameters
        if ( !in_array($order_dir, array('ASC', 'DESC')) )
        {
            $order_dir = 'ASC';
        }

        // Construct the base query
        $base_query = '';
        if ( trim($query['from']) !== '' )
        {
            $base_query .= "FROM {$query['from']}\r\n";
        }
        if ( trim($query['where']) !== '' )
        {
            $base_query .= "WHERE {$query['where']}\r\n";
        }
        if ( trim($query['group_by']) !== '' )
        {
            $base_query .= "GROUP BY {$query['group_by']}\r\n";
        }
        if ( trim($query['having']) !== '' )
        {
            $base_query .= "HAVING {$query['having']}\r\n";
        }

        // Row counting
        $count_query = "SELECT COUNT(*) AS counter\r\n$base_query";
        $count_statement = $this->db->query( $count_query );
        if ( !$count_statement )
        {
            $this->quit( 'DB Error: ' . array_pop($this->db->errorInfo()), $count_query, __FILE__, __LINE__ );
        }
        $counts = $count_statement->fetchAll();
        $record_count = trim( $query['group_by'] ) === '' ? $counts[0]['counter'] : count( $counts );
        $page_index = min( $page_index, floor(abs($record_count-1)/$this->conf['page_size']) );

        // Actual query
        $actual_query = "SELECT {$query['select']}\r\n$base_query\r\n";
        if ( $order_by !== '' )
        {
            $actual_query .= "ORDER BY $order_by $order_dir\r\n";
            if ( $primary_key !== '' )
            {
                $actual_query .= ", $primary_key $order_dir\r\n";
            }
        }
        if ( $this->conf['page_enabled'] )
        {
            $actual_query .= 'LIMIT ' . ( $page_index * $this->conf['page_size'] ) . ", {$this->conf['page_size']}\r\n";
        }
        $actual_statement = $this->db->query( $actual_query );
        if ( !$actual_statement )
        {
            // Retry query with default order by
            if ( trim(array_ifnull($_GET, "{$id}_order_by", '')) !== ''
                && trim($query['default_order_by']) !== '' )
            {
                $actual_query = str_replace(
                    "ORDER BY $order_by $order_dir\r\n",
                    "ORDER BY {$query['default_order_by']} $order_dir\r\n",
                    $actual_query
                );
                $order_by = $query['default_order_by'];
                $actual_statement = $this->db->query( $actual_query );
            }
            if ( !$actual_statement )
            {
                $this->quit( 'DB Error: ' . array_pop($this->db->errorInfo()), $actual_query, __FILE__, __LINE__ );
            }
        }
        $records = $actual_statement->fetchAll();

        // Translate header fields
        $keys = array();
        if ( count($records) > 0 )
        {
            $fields = array_keys(current($records));
            foreach ( $fields as $field )
            {
                $keys[$field] = array_ifnull( $this->dict, "LABEL_$field", '' );
            }
        }

        // Construct query string
        $_get = $_GET;
        unset( $_get["{$id}_page"] );
        unset( $_get["{$id}_order_by"] );
        unset( $_get["{$id}_order_dir"] );
        $query_string = http_build_query( $_get );

        // Construct list summary
        $summary = array(
            'id' => $id,
            'primary_key' => $primary_key,
            'page_size' => $this->conf['page_size'],
            'page_index' => $page_index,
            'page_count' => $this->conf['page_enabled'] ? ceil( $record_count/$this->conf['page_size'] ) : 1,
            'record_count' => $record_count,
            'order_by' => $order_by,
            'order_dir' => $order_dir,
            'query_string' => $query_string,
            'record_input' => $record_input,
            'hash' => $hash
        );

        $summary['formatted_page_index'] = sprintf( $this->dict['FORMAT_page_index'], $summary['page_index'] + 1 );
        $summary['formatted_page_count'] = sprintf( $this->dict['FORMAT_page_count'], $summary['page_count'] );
        $summary['formatted_record_count'] = sprintf( $this->dict['FORMAT_record_count'], (count($records) == 0 ? 0 : $summary['page_index'] * $this->conf['page_size'] + 1), ($summary['page_index'] * $this->conf['page_size'] + count($records)), $record_count, ($record_count === 1 ? $this->dict['LABEL_entry'] : $this->dict['LABEL_entries']) );

        // Construct page list
        $pages = array();
        if ( $page_index >= 0 && $page_index < ceil($this->conf['page_limit']/2)-1 )            // Head
        {
            $pages = range( 0, min($this->conf['page_limit'], $summary['page_count'])-1 );
        }
        else if ( $page_index >= $summary['page_count']-floor($this->conf['page_limit']/2)      // Tail
            && $page_index < $summary['page_count'] )
        {
            $pages = range( max(0, $summary['page_count']-$this->conf['page_limit']), $summary['page_count']-1 );
        }
        else                                                                                    // Middle
        {
            $pages = range( $page_index-floor(($this->conf['page_limit']-1)/2), $page_index+ceil(($this->conf['page_limit']-1)/2) );
        }

        global $__record;
        if(count($dependent_actions)) {
            foreach($records as $i => $record) {
                $__record = $record;
                $d_actions = array();
                foreach($dependent_actions as $action_name => $action) {
                    $d_actions[$action_name] = preg_replace_callback('#\[([^\]]+?)\]#i', function($matches){
                        global $__record;
                        if(isset($__record[$matches[1]])) {
                            return $__record[$matches[1]];
                        }

                        return $matches[0];
                    }, $action);
                }

                foreach($d_actions as $action_name => $action) {
                    eval('$b = (' . $action . ') ? true : false;');
                    $d_actions[$action_name] = $b;
                }

                $records[$i]['__d_actions'] = $d_actions;
            }
        }

        // Assign values
        $this->smarty->assign( 'keys', $keys );
        $this->smarty->assign( 'records', $records );
        $this->smarty->assign( 'summary', $summary );
        $this->smarty->assign( 'record_actions', $record_actions );
        $this->smarty->assign( 'list_actions', $list_actions );
        $this->smarty->assign( 'pages', $pages );
        $this->smarty->assign( 'html_fields', $html_fields );
        $this->smarty->assign( 'action_hashes', $action_hashes );
        $this->smarty->assign( 'hidden_fields', $hidden_fields );
        $this->smarty->assign( 'field_actions', $field_actions );
        $this->smarty->assign( 'actions_ref', $actions_ref );
        $this->smarty->assign( 'hash', $hash );
        //$this->smarty->assign( 'dependent_actions', $dependent_actions );

        // Pre-set cookie, if there are type and values in record_input
        if ( !isset($_COOKIE[$id]) && is_array($record_input)
            && isset($record_input['type'])
            && isset($record_input['values']) )
        {
            $values = is_array( $record_input['values'] )
                ? implode( ',', $record_input['values'] )
                : $record_input['values'];
            $this->set_cookie( $id, $values, 0 );
        }

        return array(
            'content' => iconv( 'utf-8', 'utf-8//IGNORE', $this->smarty->fetch($filename) ),
            'summary' => $summary
        );
    }

    /**
     * Generate a spreadsheet list from database
     *
     * @since   2010-10-26
     * @param   query   The SQL query
     * @return  The list content and its record count
     */
    function get_spreadsheet_list_from_db( $query )
    {
        // PhpSpreadsheet object
        $excel = new Spreadsheet();
        $excel->getDefaultStyle()->applyFromArray( array(
            'alignment' => array(
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP,
                'wrap' => TRUE
            )
        ) );

        // Break into a page for every 65536 rows
        $sheet = NULL;
        $sheet_id = 0;
        $row_id = 0;

        // Get all records
        $statement = $this->db->query( $query );
        if ( !$statement )
        {
            $this->quit( 'DB Error: ' . array_pop($this->db->errorInfo()), $query, __FILE__, __LINE__ );
        }
        $record_count = 0;
        $fields = array();
        $field_types = array();
        while ( $record = $statement->fetch() )
        {
            $i = $row_id % 65536;

            // First record
            if ( $row_id == 0 )
            {
                $fields = array_keys( $record );
                foreach ( $fields as $j => $field )
                {
                    $field_type = $statement->getColumnMeta( $j )['native_type'];
                    switch ( $field_type )
                    {
                        case 'DECIMAL':
                        case 'TINY':
                        case 'SHORT':
                        case 'LONG':
                        case 'LONGLONG':
                        case 'INT24':
                            $field_type = \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC;
                            break;

                        case 'TIMESTAMP':
                            $field_type = 'datetime';
                        case 'DATETIME':
                        case 'DATE':
                        case 'TIME':
                            break;

                        default:
                            $field_type = \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING;
                    }
                    $field_types[] = $field_type;
                }
            }

            // First record of the sheet
            if ( $i == 0 )
            {
                // Add sheet
                $sheet = $sheet_id > 0 ? $excel->createSheet() : $excel->getActiveSheet();
                $sheet->setTitle( 'Sheet' . ($sheet_id + 1) )->freezePane( 'A2' );
                $sheet->getPageSetup()
                    ->setOrientation( \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE )
                    ->setPaperSize( \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4 )
                    ->setRowsToRepeatAtTopByStartAndEnd( 1, 1 );
                $sheet_id++;

                // Add header row
                foreach ( $fields as $j => $field )
                {
                    $sheet->setCellValueByColumnAndRow( $j + 1, 1, array_ifnull($this->dict, "LABEL_$field", '') );
                    $sheet->getColumnDimension( $sheet->getHighestColumn() )->setAutoSize( TRUE );
                }
            }

            // Add body row
            $record_count++;
            $row_id++;
            $j = 0;
            foreach ( $record as $field )
            {
                $field_type = $field_types[$j];
                if ( is_null($field) )
                {
                    $field_type = \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NULL;
                }
                else if ( in_array($field_type, array('datetime', 'date', 'time')) )
                {
                    $field = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel( strtotime($field) );
                    $field_type = \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC;
                }

                $sheet->setCellValueByColumnAndRow(
                    $j + 1,
                    $i + 2,
                    $field,
                    $field_type
                );
                $j++;
            }
        }

        // Format the sheets
        for ( $s = 0; $s < $sheet_id; $s++ )
        {
            $sheet = $excel->getSheet( $s );

            // Set format for date / time fields
            foreach ( $field_types as $j => $field_type )
            {
                if ( in_array($field_type, array('datetime', 'date', 'time')) )
                {
                    $format_code = '';
                    if ( strpos($field_type, 'date') !== FALSE )
                    {
                        $format_code .= ' yyyy-mm-dd';
                    }
                    if ( strpos($field_type, 'time') !== FALSE )
                    {
                        $format_code .= ' hh:mm:ss';
                    }
                    $format_code = trim( $format_code );

                    $sheet->getStyle( sprintf(
                        '%1$s%2$s:%1$s%3$s',
                        \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( $j + 1 ),
                        2,
                        $sheet->getHighestRow()
                    ) )->getNumberFormat()->setFormatCode( $format_code );
                }
            }

            $sheet->setSelectedCells( 'A1:A1' );
        }

        // Add a dummy sheet if no records returned
        if ( $row_id == 0 )
        {
            $excel->getActiveSheet()
                ->setTitle( 'Sheet1' )
                ->setCellValueByColumnAndRow( 1, 1, $this->dict['LABEL_no_records'] );
        }

        // Set active sheet
        $excel->setActiveSheetIndex( 0 );

        // Get the output
        $temp_path = "{$this->sets['paths']['temp_root']}/" . md5( uniqid(rand(), TRUE) );
        $class = $this->conf['spreadsheet_type_attributes']['class'];
        $excel_writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter( $excel, $class );
        $excel_writer->save( $temp_path );
        $content = file_get_contents( $temp_path );
        unlink( $temp_path );

        return array(
            'content' => $content,
            'count' => $record_count
        );
    }


    public static function getInstance() {
        if (self::$instance === null) {
            return false;
        }

        return self::$instance;
    }


    /**
     * decode and return locale set in array
     *
     * @param $f
     * @return array
     */
    public static function decode_locale_file($f) {
        $dict = array();
        $h = @fopen($f, "r");
        if ($h) {
            $label = null;
            $str = "";
            $prev_line = "";

            while (($line = fgets($h, 4096)) !== false) {
                $line = trim($line);

                if($line !== "") {
                    $prefix1 = '[a-z0-9\-\_\.]';
                    $re1 = '#^(' . $prefix1 . '+)\:\s*\'(.*)\',?(\s*\/\/.*)?$#i';
                    $re2 = '#^(' . $prefix1 . '+)\:\s*\"(.*)\",?(\s*\/\/.*)?$#i';
                    $re3 = '#^()\'(.*)\',?(\s*\/\/.*)?$#i';
                    $re4 = '#^()\"(.*)\",?(\s*\/\/.*)?$#i';

                    $rel = array(1,2,3,4);
                    foreach($rel as $i) {
                        $rec = 're' . $i;
                        if(preg_match($$rec, $line)) {
                            // a new label
                            if(!is_null($label) && !is_null($str)) {
                                //$ls->set($label, $str);
                                $dict[$label] = $str;
                            }

                            $p1 = preg_replace($$rec, '\\1', $line);
                            $p2 = preg_replace($$rec, '\\2', $line);

                            if(preg_match('#\,#', $prev_line) && (!is_null($label) && !is_null($str))) {
                                //$ls->set($label, $str);
                                $dict[$label] = $str;
                                $label = null;
                                $str = "";
                            }

                            if($i < 3 || !preg_match('#\,$#', $prev_line)) {
                                if($p1 && $p1 !== "") {
                                    $label = $p1;
                                }

                                if($p2 !== '')
                                    $str .= $p2;

                                $prev_line = $line;
                            }

                            break;
                        }
                    }
                }
            }
            fclose($h);
            if(!is_null($label)) {
                $dict[$label] = $str;
            }

            foreach ( $dict as $key => $value )
            {
                if ( strpos($key, '.') > -1 )
                {
                    $key_parts = explode( '.', $key );
                    $d =& $dict;
                    foreach ( $key_parts as $k )
                    {
                        if ( !array_key_exists($k,$d) )
                        {
                            $d[$k] = array();
                        }
                        $d =& $d[$k];
                    }
                    $d = $value;
                }
            }

            return $dict;
        }

        return array();
    }

    /**
     * Encrypt a value
     * https://github.com/noprotocol/php-mysql-aes-crypt
     *
     * @since   2014-04-02
     * @param   val The value to be encrypted
     * @return  The encrypted value
     */
    function encrypt( $val )
    {
        //return openssl_encrypt( $val, 'aes-128-ecb', $this->conf['security_key'] );
        return bin2hex( openssl_encrypt(
            str_pad( $val, intval(16 * (floor(strlen($val) / 16) + 1)), chr(16 - (strlen($val) % 16)) ),
            'aes-128-ecb',
            $this->conf['security_key'],
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING
        ) );
    }

    /**
     * Decrypt a value
     * https://github.com/noprotocol/php-mysql-aes-crypt
     *
     * @since   2014-04-02
     * @param   val The value to be decrypted
     * @return  The decrypted value
     */
    function decrypt( $val )
    {
        //return openssl_decrypt( $val, 'aes-128-ec', $this->conf['security_key'] );
        if ( ctype_xdigit($val) && strlen($val) % 2 == 0 )
        {
            $val = openssl_decrypt(
                hex2bin( $val ),
                'aes-128-ecb',
                $this->conf['security_key'],
                OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING
            );
            if ( $val !== FALSE )
            {
                return rtrim( $val, "\x00..\x10" );
            }
        }
        return FALSE;
    }

    /**
     * Convert a value to alias
     *
     * @since   2015-09-04
     * @param   val The value to be converted
     * @return  The alias
     */
    function to_alias( $val )
    {
        return mb_strtolower( str_replace(
            array( ' *', '/', ' ' ),
            array( '', '', '-' ),
            $val
        ) );
    }

    /**
     * Decode a value with time
     *
     * @since   2020-12-29
     * @param   value   The value to be decoded
     * @return  The decoded value
     */
    function time_decode( $value )
    {
        $decoded = json_decode( $this->decrypt($value), TRUE );
        if ( !is_null($decoded) && array_key_exists('shifted', $decoded) && array_key_exists('offset', $decoded) )
        {
            $decoded = json_decode( str_shift($decoded['shifted'], -$decoded['offset']), TRUE );
            if ( !is_null($decoded) && array_key_exists('value', $decoded) && array_key_exists('time', $decoded) )
            {
                return $decoded;
            }
        }
        return NULL;
    }

    /**
     * Encode a value with time
     *
     * @since   2020-12-29
     * @param   value   The value to be encoded
     * @return  The encoded value
     */
    function time_encode( $value )
    {
        $shifted = json_encode( array(
            'value' => $value,
            'time' => time()
        ) );
        $offset = random_int( 0, strlen($shifted) - 1 );
        $shifted = str_shift( $shifted, $offset );
        return $this->encrypt( json_encode(compact('shifted', 'offset')) );
    }

    /**
     * Send a file using X-Sendfile instead of readfile
     *
     * iOS uses HTTP byte-ranges for requesting audio and video files
     * https://mobiforge.com/design-development/content-delivery-mobile-devices
     *
     * Setting up X-Sendfile
     * https://www.h3xed.com/programming/how-to-use-x-sendfile-with-php-apache
     *
     * Make sure mod_xsendfile for Apache2 is installed and enabled
     * Then add the following Apache Directives:
     * XSendFile On
     * XSendFilePath /var/www/clients/client1/web1/web/file/
     *
     * @since   2021-01-06
     * @param   path        The path of the file
     * @param   filename    The filename to be disposited
     * @return  Success or not
     */
    function send_file( $path, $filename = '' )
    {
        if ( file_exists($path) )
        {
            if ( $filename === '' )
            {
                $filename = basename( $path );
            }
            $finfo = finfo_open( FILEINFO_MIME_TYPE );
            header( 'Content-Type: ' . finfo_file($finfo, $path) );
            header( 'Content-Disposition: inline; filename="' . $filename . '"' );
            finfo_close( $finfo );
            header( 'X-Sendfile: ' . $path );
            return TRUE;
        }
        return FALSE;
    }

    // Member variables
    var $request;           // The request values
    var $response;          // The response values
    var $sets;              // The computed sets
    var $conf;              // The configuration settings
    var $dict;              // The dictionary for localization
    var $mailer;            // The PHPMailer
    var $smarty;            // The Smarty Template Engine
    var $s3;                // The AWS S3 client
    var $db;                // The database connection
    var $default_public_locale = null;
    /** @var \kernel $instance */
    public static $instance; // only one kernel should exist for the whole life cycle, this represents the kernel
    public static $module; // only one module call by kernel
}
