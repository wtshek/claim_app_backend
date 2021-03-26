<?php

// This script can only be run using command line
if ( php_sapi_name() != 'cli' )
{
    header( 'Content-Type: text/plain' );
    header( 'HTTP/1.1 403 Forbidden' );
    echo 'HTTP/1.1 403 Forbidden';
    exit;
}

// Handle according to arguments
if ( $argc < 4 )
{
?>
Usage:
<?php echo $argv[0]; ?> <HTTPS> <HTTP_HOST> <DOCUMENT_ROOT>

<HTTPS> use HTTPS or not
e.g. 1 or 0

<HTTP_HOST> the host name or IP address of the web server.
e.g. www.avalade.com or 127.0.0.1

<DOCUMENT_ROOT> the absolute or relative document root of the web server.
e.g. /web/default/public_html/ or ../
<?php
}
else
{
    // Get HTTPS from arguments
    if ( $argv[1] )
    {
        $_SERVER['HTTPS'] = 'on';
    }
    else
    {
        unset( $_SERVER['HTTPS'] );
    }
    $_SERVER['SERVER_PORT'] = isset( $_SERVER['HTTPS'] ) ? 443 : 80;

    // Get the host name from arguments
    $http_host = $argv[2];
    if ( long2ip(ip2long($http_host)) != $http_host     // Not an IP address
        && gethostbyname($http_host) == $http_host )    // Not a host name
    {
        echo "HTTP_HOST {$argv[2]} is not valid!\r\n";
        exit;
    }
    $_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = $http_host;

    // Get document root from arguments
    $document_root = realpath( $argv[3] );
    if ( $document_root == '' )
    {
        echo "DOCUMENT_ROOT {$argv[3]} does not exist!\r\n";
        exit;
    }
    if ( strpos(__FILE__, $document_root) !== 0 )
    {
        echo "DOCUMENT_ROOT {$argv[3]} is not valid!\r\n";
        exit;
    }
    $_SERVER['DOCUMENT_ROOT'] = $document_root;

    // No time limit for cron job
    set_time_limit( 0 );

    // Create kernel
    require_once( dirname(__FILE__) . '/kernel.php' );
    $kernel = new kernel( 'admin_site', 'en/' );

    $tables = array(
        'webpage_locale_banners',
        'webpage_locale_contents',
        'webpage_locales',
        'webpage_offers',
        'webpage_permissions',
        'webpage_platforms',
        'webpages'
    );

    foreach ( $tables as $table )
    {
        $sql = sprintf(
            'UPDATE %1$s a JOIN %1$s b ON(a.domain = \'public\' AND b.domain = \'private\' AND a.%2$s = b.%2$s)'
                . ' SET a.major_version = (SELECT MAX(major_version) FROM (SELECT %2$s, major_version FROM %1$s WHERE domain = \'private\') t WHERE %2$s = b.%2$s)',
            $table,
            $table == 'webpages' ? 'id' : 'webpage_id'
        );
        $statement = $kernel->db->query( $sql );
        if ( !$statement )
        {
            $kernel->quit( 'DB Error: ' . array_pop($kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
        }
    }

    foreach ( $tables as $table )
    {
        $sql = sprintf(
            'DELETE FROM %s WHERE domain = \'private\' AND %s NOT IN(SELECT DISTINCT id FROM (SELECT id FROM webpages WHERE domain = \'public\') t)',
            $table,
            $table == 'webpages' ? 'id' : 'webpage_id'
        );
        $statement = $kernel->db->query( $sql );
        if ( !$statement )
        {
            $kernel->quit( 'DB Error: ' . array_pop($kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
        }
    }

    // Get public webpages
    $prefix = $kernel->conf['aws_enabled'] ? '' : "{$kernel->sets['paths']['app_root']}/file/";
    $unlink = $kernel->conf['aws_enabled'] ? 's3_deleteDir' : 'rm';
    $rename = $kernel->conf['aws_enabled'] ? 's3_rename' : 'rename';
    $sql = "SELECT * FROM webpages WHERE domain = 'public'";
    $statement = $kernel->db->query( $sql );
    while ( $webpage = $statement->fetch() )
    {
        foreach ( $tables as $table )
        {
            $sql = sprintf(
                'DELETE FROM %s WHERE domain = \'private\' AND %s = %s AND major_version < %s',
                $table,
                $table == 'webpages' ? 'id' : 'webpage_id',
                $webpage['id'],
                $webpage['major_version']
            );
            $statement2 = $kernel->db->query( $sql );
            if ( !$statement2 )
            {
                $kernel->quit( 'DB Error: ' . array_pop($kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
            }
        }

        for ( $i = $webpage['major_version'] - 1; $i > 0; $i-- )
        {
            $unlink( "{$prefix}webpage/page/archive/p{$webpage['id']}/{$i}_0/" );
        }
        $old_path = "webpage/page/archive/p{$webpage['id']}/{$webpage['major_version']}_0/";
        $new_path = "webpage/page/archive/p{$webpage['id']}/1_0/";
        $rename( $prefix . $old_path, $prefix . $new_path );
    }

    $tables[] = 'webpage_snippets';
    $tables[] = 'webpage_versions';
    foreach ( $tables as $table )
    {
        $sql = sprintf('UPDATE %s SET major_version = 1, minor_version = 0', $table);
        $statement = $kernel->db->query( $sql );
        if ( !$statement )
        {
            $kernel->quit( 'DB Error: ' . array_pop($kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
        }
    }

    // Close kernel
    $kernel->close();
}
