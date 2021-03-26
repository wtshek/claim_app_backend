<?php

// Include required files
require_once( dirname(dirname(__FILE__)) . '/base/index.php' );

/**
 * The cron module.
 *
 * This module is used by the cronjob.
 *
 * @author  Martin Ng <martin@avalade.com>
 * @since   2010-06-28
 */
class cron_module extends base_module
{
    public $module = 'cron_module';
    public $user = null;
    public $platform = 'desktop';

    /**
     * Constructor.
     *
     * @since   2010-06-28
     * @param   kernel      The kernel
     */
    function __construct( &$kernel )
    {
        parent::__construct( $kernel );
    }

    /**
     * Process the request.
     *
     * @since   2010-06-28
     * @return  Processed or not
     */
    function process()
    {
        if(!$this->kernel->cli) {
            exit;
        }

        // Choose operation
        $op = array_ifnull( $_GET, 'op', 'index' );
        switch ( $op )
        {
            default:            $this->index();         break;
        }

        return TRUE;
    }

    /**
     * Cron job to be run every minute.
     *
     * @since   2010-06-28
     */
    function index()
    {
        $now = strtotime( convert_tz('now', 'gmt', $this->kernel->conf['timezone']) );
        $is_midnight = date( 'H', $now ) == '00' && date( 'i', $now ) == '00';

        // Change working directory
        chdir( "{$this->kernel->sets['paths']['app_root']}/file/" );

        // Publicize scheduled webpages
        $this->publicize_scheduled_webpages();

        // Clear cache at 00:00
        if ( $is_midnight )
        {
            $this->clear_cache();
        }

        // Delete expired archive webpages on Monday 00:00
        if ( date('w', $now) == 1 && $is_midnight )
        {
            $this->delete_expired_archive_webpages();
        }

        $this->kernel->log('message', sprintf('Automatic process run successfully at %s (GMT+0)', gmdate('Y-m-d H:i:s')) );
    }

    private function clearLivePreviews() {
        $preview_dir = $this->kernel->sets['paths']['temp_root'] . '/live-previews/';

        if (is_dir($preview_dir) && $handle = opendir($preview_dir)) {

            while (false !== ($file_name = readdir($handle))) {
                $path = $preview_dir . $file_name;
                if(is_file($path) && (filemtime($path) + 86400) < time()) {
                    unlink($path);
                }
            }

            closedir($handle);
        }
    }

    private function publicize_scheduled_webpages()
    {
        $this->kernel->db->beginTransaction();

        // Prepared SQLs
        $tables = array( 'webpage_locales', 'webpage_locale_contents', 'webpage_locale_banners', 'webpage_snippets' );
        $delete_sqls = $insert_sqls = array();
        foreach ( $tables AS $table )
        {
            if ( $table == 'webpage_snippets' )
            {
                // Delete SQL
                $delete_sqls['webpage_snippets'] = "DELETE FROM webpage_snippets WHERE webpage_id = :webpage_id AND webpage_locale = :locale";

                // Insert SQL
                $sql = 'INSERT INTO webpage_snippets(webpage_id, snippet_id, webpage_locale, webpage_status, major_version, minor_version)';
                $sql .= " VALUES(:webpage_id, :snippet_id, :locale, 'publish', :public_major_version, :public_minor_version)";
                $insert_sqls['webpage_snippets'] = $sql;
            }
            else
            {
                // Delete SQL
                $delete_sqls[$table] = "DELETE FROM $table WHERE domain = 'public' AND webpage_id = :webpage_id AND locale = :locale";

                // Insert SQL
                $fields = array();
                $statement = $this->kernel->db->query( "DESCRIBE $table" );
                while ( $row = $statement->fetch() )
                {
                    $field_key = $field_value = $row['Field'];
                    if ( $field_key == 'domain' )
                    {
                        $field_value = "'public'";
                    }
                    else if ( in_array($field_key, array('major_version', 'minor_version')) )
                    {
                        $field_value = ":public_$field_key";
                    }
                    else if ( $field_key == 'content' )
                    {
                        $field_value = ':content';
                    }
                    else if ( $field_key == 'url' )
                    {
                        $field_value = "REPLACE(url, CONCAT('[file_loc_folder:', webpage_id, ']'), CONCAT('public/p', webpage_id))";
                    }
                    else if ( in_array($field_key, array('image_xs', 'image_md', 'image_lg')) )
                    {
                        $field_value = "IF($field_key LIKE 'webpage/page/private/%', CONCAT('webpage/page/public/', SUBSTRING($field_key, 23)), $field_key)";
                    }
                    $fields[$field_key] = $field_value;
                }
                $sql = "INSERT INTO $table(" . implode( ', ', array_keys($fields) ) . ')';
                $sql .= ' SELECT ' . implode( ', ', $fields ) . " FROM $table";
                $sql .= " WHERE domain = 'private' AND webpage_id = :webpage_id AND locale = :locale AND major_version = :major_version AND minor_version = :minor_version";
                $insert_sqls[$table] = $sql;
            }
        }

        // Get the webpages that reached publish date
        $messages = array();
        $sql = 'SELECT w.type, wl.*, wlc.content, pw.major_version AS public_major_version, pw.minor_version AS public_minor_version';
        $sql .= ' FROM webpages AS w';
        $sql .= ' JOIN webpage_locales AS wl ON (w.domain = wl.domain AND w.id = wl.webpage_id';
        $sql .= ' AND w.major_version = wl.major_version AND w.minor_version = wl.minor_version';
        $sql .= " AND wl.publish_date <= UTC_TIMESTAMP() AND wl.status = 'approved')";
        $sql .= ' LEFT OUTER JOIN webpage_locale_contents AS wlc ON (wl.domain = wlc.domain AND wl.webpage_id = wlc.webpage_id';
        $sql .= ' AND wl.locale = wlc.locale AND wl.major_version = wlc.major_version AND wl.minor_version = wlc.minor_version';
        $sql .= " AND wlc.platform = 'desktop' AND wlc.type = 'content')";
        $sql .= " JOIN webpages AS pw ON (pw.domain = 'public' AND w.id = pw.id)";
        $sql .= " LEFT OUTER JOIN webpage_locales AS pwl ON (pwl.domain = 'public' AND wl.webpage_id = pwl.webpage_id";
        $sql .= ' AND wl.locale = pwl.locale AND wl.updated_date <= pwl.updated_date)';
        $sql .= " WHERE w.domain = 'private' AND w.deleted = 0 AND wl.webpage_title IS NOT NULL AND pwl.webpage_id IS NULL";
        $sql .= ' ORDER BY w.id, wl.publish_date, wl.major_version, wl.minor_version';
        $statement = $this->kernel->db->query( $sql );
        while ( $record = $statement->fetch() )
        {
            extract( $record );
            $params = array_map( array($this->kernel->db, 'escape'), array(
                ':webpage_id' => $webpage_id,
                ':locale' => $locale,
                ':major_version' => $major_version,
                ':minor_version' => $minor_version,
                ':public_major_version' => $public_major_version,
                ':public_minor_version' => $public_minor_version,
                ':content' => NULL
            ) );
            $snippet_ids = array();

            // Check content
            if ( !is_null($content) )
            {
                // Replace content
                if ( in_array($type, array('static', 'structured_page')) )
                {
                    $content = $this->imgPathDecode( $webpage_id, $content, $type );
                }
                $params[':content'] = $this->kernel->db->escape( $content );

                // Get snippet IDs
                if ( $type == 'static' )
                {
                    // Traditional
                    $snippet_calls = array();
                    preg_match_all( '/\{\{[^\}]+\}\}/', $content, $snippet_calls );
                    foreach ( $snippet_calls[0] as $snippet_call )
                    {
                        $snippet_call = html_entity_decode(
                            strip_tags( substr($snippet_call, 2, strlen($snippet_call)-4) ),
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        $snippet_call_parts = explode( '=', $snippet_call );
                        $snippet_ids[] = $snippet_call_parts[1];
                    }

                    // avacontent block
                    $doc = new DOMDocument();
                    $doc->loadHTML( "<?xml version='1.0' encoding='UTF-8'?><body>$content</body>" );
                    $content_blocks = iterator_to_array( $doc->getElementsByTagName('avacontentblock') );
                    foreach ( $content_blocks as $content_block )
                    {
                        $snippet_ids[] = $content_block->getAttribute( 'id' );
                    }
                }
                $snippet_ids = array_unique( $snippet_ids );
            }

            // Update tables
            foreach ( $tables as $table )
            {
                // Delete existing records
                $sql = strtr( $delete_sqls[$table], $params );
                $statement2 = $this->kernel->db->query( $sql );
                if ( !$statement2 )
                {
                    $error_msg = array_pop( $this->kernel->db->errorInfo() );
                    $this->kernel->db->rollback();
                    $this->kernel->quit( 'DB Error: ' . $error_msg, $sql, __FILE__, __LINE__ );
                }

                if ( $table == 'webpage_snippets' )
                {
                    // Insert new webpage snippets
                    foreach ( $snippet_ids as $snippet_id )
                    {
                        $params[':snippet_id'] = $snippet_id;
                        $sql = strtr( $insert_sqls[$table], $params );
                        $statement2 = $this->kernel->db->query( $sql );
                        if ( !$statement2 )
                        {
                            $error_msg = array_pop( $this->kernel->db->errorInfo() );
                            $this->kernel->db->rollback();
                            $this->kernel->quit( 'DB Error: ' . $error_msg, $sql, __FILE__, __LINE__ );
                        }
                    }
                }

                else
                {
                    // Insert new records
                    $sql = strtr( $insert_sqls[$table], $params );
                    $statement2 = $this->kernel->db->query( $sql );
                    if ( !$statement2 )
                    {
                        $error_msg = array_pop( $this->kernel->db->errorInfo() );
                        $this->kernel->db->rollback();
                        $this->kernel->quit( 'DB Error: ' . $error_msg, $sql, __FILE__, __LINE__ );
                    }
                }
            }

            // Update files
            $this->pageFilesCopy( $webpage_id, $major_version, $minor_version, $locale );

            $messages[] = "$webpage_id - $visual_version.$minor_version ($webpage_title: " . $this->kernel->sets['public_locales'][$locale] . ')';
        }
        if ( count($messages) > 0 )
        {
            $this->clear_cache();
            $this->kernel->log( 'message', 'Automatic process published webpages ' . implode(', ', $messages) . '.', __FILE__, __LINE__ );
        }

        $this->kernel->db->commit();
    }

    /**
     * Decoded URLs to public URLs
     * @see webpage_admin_module#imgPathDecode
     */
    private function imgPathDecode( $id, $content, $type )
    {
        $pattern = '/\[file_loc_folder:' . $id . '\]/';
        $replacement = 'public/p' . $id;
        if ( $type == 'static' )
        {
            return preg_replace( $pattern, $replacement, $content, -1 );
        }
        else
        {
            $tmp = json_decode( $content, TRUE );
            if ( is_null($tmp) )
            {
                return '{}';
            }
            else
            {
                foreach ( $tmp as &$val )
                {
                    foreach ( $val as &$v )
                    {
                        if ( gettype($v) == 'string' )
                        {
                            $v = preg_replace( $pattern, $replacement, $v, -1 );
                        }
                    }
                }
                return json_encode( $tmp );
            }
        }
    }

    /**
     * Copy page-specific files to public directory
     * @see webpage_admin_module#pageFilesCopy
     */
    private function pageFilesCopy( $id, $major_version, $minor_version, $locale )
    {
        $file_exists = $this->kernel->conf['aws_enabled'] ? 's3_file_exists' : 'file_exists';
        $mkdir = $this->kernel->conf['aws_enabled'] ? 's3_mkdir' : 'force_mkdir';

        $source_path = "webpage/page/archive/p{$id}/{$major_version}_{$minor_version}/$locale/";
        $target_path = "webpage/page/public/p{$id}/$locale/";

        if ( $file_exists($source_path) )
        {
            $mkdir( "webpage/page/public/" );
            $mkdir( "webpage/page/public/p{$id}/" );

            if ( $this->kernel->conf['aws_enabled'] )
            {
                if ( s3_is_dir($target_path) )
                {
                    s3_deleteDir( $target_path );
                }
                else
                {
                    s3_unlink( $target_path );
                }
                s3_mkdir( $target_path );
                $paginator = $this->kernel->s3->getPaginator( 'ListObjects', array(
                    'Bucket' => $this->kernel->conf['s3_bucket'],
                    'Prefix' => $source_path,
                    'Delimiter' => '/'
                ) );
                $source_filelist = array();
                foreach ( $paginator->search('[CommonPrefixes[].Prefix, Contents[].Key][]') as $key )
                {
                    if ( $key != $source_path )
                    {
                        $source_filelist[] = $key;
                    }
                }
                foreach ( $source_filelist as $dir_to_copy )
                {
                    rcopy( $dir_to_copy, $target_path, FALSE, 's3_' );
                }
            }
            else
            {
                rm( $target_path );
                force_mkdir( $target_path );
                smartCopy( $source_path, $target_path );
            }
        }
    }

    private function delete_expired_archive_webpages()
    {
        $prefix = $this->kernel->conf['aws_enabled'] ? '' : "{$this->kernel->sets['paths']['app_root']}/file/";
        $unlink = $this->kernel->conf['aws_enabled'] ? 's3_deleteDir' : 'rm';
        $tables = array(
            'webpage_locale_banners',
            'webpage_locale_contents',
            'webpage_locales',
            'webpage_offers',
            'webpage_permissions',
            'webpage_platforms',
            'webpage_snippets',
            'webpages'
        );
        if($this->kernel->conf['webpage_n_month_before']>0)
        {
            $sql = 'SELECT wl.webpage_id AS id, wl.major_version, wl.minor_version';
            $sql .= ' FROM webpage_locales AS wl';
            $sql .= ' LEFT OUTER JOIN webpage_versions AS wv ON (wl.domain = wv.domain AND wl.webpage_id = wv.id AND wl.major_version = wv.major_version AND wl.minor_version = wv.minor_version)';
            $sql .= " WHERE wl.domain = 'private' AND wv.id IS NULL";
            $sql .= ' GROUP BY wl.webpage_id, wl.major_version, wl.minor_version';
            $sql .= ' HAVING MAX(wl.updated_date) < DATE_SUB(UTC_TIMESTAMP(), INTERVAL '.$this->kernel->conf['webpage_n_month_before'].' MONTH)';
            $statement = $this->kernel->db->query( $sql );
            while ( $record = $statement->fetch() )
            {
                extract( $record );
                foreach ( $tables as $table )
                {
                    $id_field = $table == 'webpages' ? 'id' : 'webpage_id';
                    $sql = "DELETE FROM $table WHERE domain = 'private' AND $id_field = $id AND major_version = $major_version AND minor_version = $minor_version";
                    if ( $table == 'webpage_snippets' )
                    {
                        $sql = str_replace( "domain = 'private' AND ", '', $sql );
                    }
                    $this->kernel->db->exec( $sql );
                }
                $unlink( "{$prefix}webpage/page/archive/p{$id}/{$major_version}_{$minor_version}/" );
            }
        }
    }

    function processException($e) {
        echo $e->getTraceAsString();
        exit;
    }
}
