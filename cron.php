#!/opt/php/bin/php -q
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

    // Process the request
    require_once( dirname(__FILE__) . '/kernel.php' );
    $instance = new kernel( 'public_site', 'cron/', true);
    $instance->cli = true;
    $instance->process();
    $instance->response['mimetype'] = 'text/plain';
    $instance->output();
    $instance->close();

    echo 'done';
}

?>