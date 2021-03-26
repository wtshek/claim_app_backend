<?php

////////////////////////////////////////////////////////////////////////////////
// Description: This file is the entry point of this web application          //
// Date: 2008-10-31                                                           //
// ----------------------------------History----------------------------------//
// Modified By: Patrick Yeung <patrick[at]avalade[dot]com>                    //
// Date: 2013-11-04                                                           //
// Purpose: Rewrite                                                           //
////////////////////////////////////////////////////////////////////////////////

require( dirname(__FILE__) . '/include/commons.php' );

// Try to find these values from the URL in the form of:
// http[s]://domain/admin/[locale]/[path segment 1]/.../[path segment n]/ OR
// http[s]://domain/[locale]/[path segment 1]/.../[path segment n]/
$app_root = str_replace( '\\', '/', substr(
            realpath(dirname($_SERVER['SCRIPT_FILENAME'])),
            strlen(realpath($_SERVER['DOCUMENT_ROOT']))
        ));

if ( isset($_SERVER['PATH_INFO']) )
{
    $path_info = $_SERVER['PATH_INFO'];
}
else
{
    $path_info = $_SERVER['REQUEST_URI'];
    $path_info = preg_replace('#\?.*#i', '', $path_info);

    $path_info = substr($path_info, strlen($app_root)+1);
}

$path_segments = array_values(array_map('strtolower', array_filter(explode( '/', $path_info ), 'strlen')));

// redirect all paths that have double slashes to the one that have only single slash
// the redirections handle here means that the path must be wrong
// no backslash at the end will handle in kernel.php (to consider it should return 404 or just a redirect instead)
if(preg_match('#\/\/#', $path_info) || $path_info != strtolower($path_info)) {
    // Redirect to the next page
    header( sprintf('Location: %s/%s/%s',
        str_replace( '\\', '/', substr(
            realpath(dirname($_SERVER['SCRIPT_FILENAME'])),
            strlen(realpath($_SERVER['DOCUMENT_ROOT']))
        ) ),
        implode( '/', $path_segments ),
        $_SERVER['QUERY_STRING'] === '' ? '' : "?{$_SERVER['QUERY_STRING']}"
    ), TRUE, 301 );
    exit;
}

$site = 'public_site';

if(count($path_segments) && $path_segments[0] == 'admin') {
    $site = 'admin_site';
    $path_info = preg_replace('#^\/?admin\/?#', '', $path_info);
}

require( dirname(__FILE__) . '/kernel.php' );
$kernel = new kernel( $site, $path_info );

// Add file manager utility functions
$_SESSION['RF']['verify'] = 'RESPONSIVEfilemanager';
require( dirname(__FILE__) . '/module/file_admin/include/utils.php' );

$kernel->process();
$kernel->output();
$kernel->close();

?>
