<?php

// This script can only be run using command line
if ( php_sapi_name() != 'cli' )
{
    header( 'Content-Type: text/plain' );
    header( 'HTTP/1.1 403 Forbidden' );
    echo 'HTTP/1.1 403 Forbidden';
    exit;
}

// Get data from command line
$password = readline( 'Enter password: ' );
$password_confirm = readline( 'Retype password: ' );

// Check data
if ( $password == '' && $password_confirm == '' )
{
    echo 'No password supplied';
}
else if ( $password !== $password_confirm )
{
    echo 'Sorry, passwords do not match';
}
else
{
    echo 'Password hash: ' . password_hash( $password, PASSWORD_BCRYPT );
}
