<?php

////////////////////////////////////////////////////////////////////////////////
// Description: Configuration settings (values are CASE SENSITIVE!)           //
// Date: 2014-02-11                                                           //
////////////////////////////////////////////////////////////////////////////////

$CONF = array(
    /***************************************************************************
     *
     * Application settings
     *
     **************************************************************************/

    //
    // app_id: A unique identifier of this application within this server.
    //
    // Please use only alphanumeric characters and underscore, as other
    // characters may not be allowed to use in the file system.
    //
    'app_id' => 'force100_cms_demo',

    //
    // app_client: The name of the client licensed to use this application.
    //
    'app_client' => 'Force100',

    //
    // default_domain: The root domain
    //
    'default_domain' => '',

    //
    // mobile_domain: The root mobile domain
    //
    'mobile_domain' => '',

    //
    // debug: Debug mode or not
    //
    // Depending on the configuration of error_reporting in php.ini,
    // PHP may output error, warning and/or notice messages to output.
    // This is useful during development, but not desirable under production.
    // Setting this option to FALSE will prevent message from appearing in HTML.
    //
    // Notice that no matter what, this application will save error, warning
    // and notice message to the database table named "logs".
    // And for fatal errors, error message will still appear in the HTML.
    //
    'debug' => TRUE,

    //
    // security_key: The security key used in AES encryption and decryption.
    //
    // Because of security reason, this key is not editable in configuration admin.
    //
    'security_key' => '9A79806E539486D6A5CD9B241C64C3A995D0E9C0C1869D8CB4670D21C1070F37',

    /***************************************************************************
     *
     * Database settings
     *
     **************************************************************************/

    //
    // db_type: The type of database.
    //
    // The list of supported database can be found in
    // http://www.php.net/manual/en/pdo.installation.php
    //
    // This should be set as 'mysql' and should not be changed.
    // Changing database vendor requires significant changes to the SQL
    // statements used throughout this application.
    //
    'db_type' => 'mysql',

    //
    // db_host: The name of the database server.
    //
    'db_host' => 'localhost',

    //
    // db_schema: The name of the database schema.
    //
    'db_schema' => 'c1force100demo',

    //
    // db_user: The username of the database user.
    //
    'db_user' => 'c1force100demo',

    //
    // db_pass: The password of the database user.
    //
    'db_pass' => 'c1force100demo11',

    /***************************************************************************
     *
     * Email settings
     *
     **************************************************************************/

    //
    // mailer_name: The name of the mailer.
    //
    'mailer_name' => '',

    //
    // mailer_email: The email of the mailer.
    //
    'mailer_email' => '',

    //
    // mailer_smtp_host: The name of the SMTP server.
    //
    'mailer_smtp_host' => '',

    //
    // mailer_smtp_port: The port of the SMTP server.
    //
    // The standard SMTP port is 25.
    //
    'mailer_smtp_port' => 25,

    //
    // mailer_smtp_auth: Use SMTP authentication or not.
    //
    'mailer_smtp_auth' => TRUE,

    //
    // mailer_smtp_secure: The secure for SMTP authentication.
    //
    'mailer_smtp_secure' => '',

    //
    // mailer_smtp_username: The username for SMTP authentication.
    //
    'mailer_smtp_username' => '',

    //
    // mailer_smtp_password: The password for SMTP authentication.
    //
    'mailer_smtp_password'  => '',

    //
    // crm settings.
    //
    // crm_url: The url of crm system
    //
    'crm_url' => 'http://force100-demo.avacrm.com/crm/en/contact/?op=process_subscription',

    //
    // auth_usrname: username for authentication. It must be the same as the configuration file in crm system
    //
    'auth_username' => 'curl_admin',

    //
    // auth_password: password for authentication. It must be the same as the configuration file in crm system
    //
    'auth_password' => 'password',

    /***************************************************************************
     *
     * Pagation settings
     *
     **************************************************************************/

    //
    // page_enabled: Whether pagation is enabled or not.
    //
    'page_enabled' => TRUE,

    //
    // page_sortable: Whether records are sortable or not.
    //
    'page_sortable' => TRUE,

    //
    // page_size: The maximum number of records per page.
    //
    'page_size' => 20,

    //
    // page_limit: The maximum number of pages per screen
    //
    'page_limit' => 10,

    /***************************************************************************
     *
     * Webpage settings
     *
     **************************************************************************/

    //
    // 404_webpage_id: The webpage ID of the 404 page not found webpage.
    //
    '404_webpage_id' => 0,

    //
    // footer_webpage_id: The webpage ID of the footer webpage.
    //
    'footer_webpage_id' => 0,

    //
    // offer_webpage_id: The webpage ID of the offer webpage.
    //
    'offer_webpage_id' => 0,

    //
    // login_webpage_id: The webpage ID of the login webpage.
    //
    'login_webpage_id' => 0,

    /***************************************************************************
     *
     * AWS settings
     *
     **************************************************************************/

    //
    // aws_enabled: Whether AWS is enabled or not.
    //
    'aws_enabled' => FALSE,

    //
    // aws_access_key: The access key of Amazon Web Services.
    //
    'aws_access_key' => '',

    //
    // aws_secret_key: The secret key of Amazon Web Services.
    //
    'aws_secret_key' => '',

    //
    // s3_region: The S3 region.
    //
    's3_region' => '',

    //
    // s3_bucket: The S3 bucket name.
    //
    's3_bucket' => '',

    //
    // s3_domain: The S3 domain name.
    //
    's3_domain' => '',

    //
    // cloudfront_domain: The CloudFront domain name.
    //
    'cloudfront_domain' => '',

    /***************************************************************************
     *
     * Image dimension settings
     *
     **************************************************************************/

    //
    // banner_image_dimension_*: The required dimension for banner image in XS, MD and XL viewports.
    //
    'banner_image_dimension_xs' => '414 × 896px',
    'banner_image_dimension_md' => '1024 × 768px',
    'banner_image_dimension_xl' => '1920 × 1080px',

    //
    // offer_image_dimension: The required dimension for offer image.
    //
    'offer_image_dimension' => '748 × 423px',

    /***************************************************************************
     *
     * hCaptcha settings
     *
     **************************************************************************/

    //
    // hcaptcha_sitekey: The hCaptcha sitekey.
    //
    'hcaptcha_sitekey' => '',

    //
    // hcaptcha_secret: The hCaptcha secret.
    //
    'hcaptcha_secret' => '',

    /***************************************************************************
     *
     * Miscellaneous settings
     *
     **************************************************************************/

    //
    // unwanted_matching_characters: Characters not wanted in matching
    //
    'unwanted_matching_characters' => array(
        ' ', "\t", "\r", "\n",
        '<', '>', '{', '}', '[', ']', '(', ')',
        '_', '+', '-', '*', '=', '/', "\\",
        '"', "'", ',', '.', ':', ';', '`', '!', '@', '#', '$', '%', '^', '&', '*', '?'
    ),

    //
    // timezone: The timezone.
    //
    'timezone' => 'Asia/Hong_Kong',

    //
    // dialog_timeout: The number of seconds for dialog timeout.
    //
    'dialog_timeout' => 5,

    //
    // user_session_timer: The number of seconds for user session timeout.
    //
    'user_session_timer' => 86400,

    //
    // page_session_timer: The number of seconds for web page session timeout.
    //
    'page_session_timer' => 86400,

    //
    // spreadsheet_type: The preferred type of spreadsheet.
    //
    'spreadsheet_type' => 'xls',

    //
    // Api
    //
    'apiuser' => 'force100_cms_demo_api',
    'apikey' => 'C1070F375D0E9C0C189A798066A5CD9B241C64C3A9969D8CB4670D21E539486D',

    'webpage_n_month_before' => 13
);
