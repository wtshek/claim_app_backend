<?php

/**
 * The Subscription Form snippet.
 *
 * The subscription form
 *
 * @since   2015-08-03
 * @param   module      The module
 * @param   snippet     The snippet
 * @param   parameters  The parameters
 * @return  HTML content
 */
function enews_signup_form_snippet( &$module, &$snippet, $parameters )
{
    // Process form
    if ( $_SERVER['REQUEST_METHOD'] == 'POST' )
    {
        $is_xhr = array_ifnull( $_SERVER, 'HTTP_X_REQUESTED_WITH', '' ) == 'XMLHttpRequest';
        $errors = array();

        // Get data from form post
        $data = array();
        $data['locale'] = $module->kernel->request['locale'];
        $data['title'] = trim( substr(array_ifnull($_POST, 'title', ''), 0, 45) );
        $data['first_name'] = trim( substr(array_ifnull($_POST, 'first_name', ''), 0, 255) );
        $data['last_name'] = trim( substr(array_ifnull($_POST, 'last_name', ''), 0, 255) );
        $data['email'] = trim( substr(array_ifnull($_POST, 'email', ''), 0, 255) );
        $data['phone'] = trim( substr(array_ifnull($_POST, 'phone', ''), 0, 255) );
        $data['address'] = trim( substr(array_ifnull($_POST, 'address', ''), 0, 255) );
        $data['city'] = trim( substr(array_ifnull($_POST, 'city', ''), 0, 255) );
        $data['zip_code'] = trim( substr(array_ifnull($_POST, 'zip_code', ''), 0, 255) );
        $data['country'] = trim( substr(array_ifnull($_POST, 'country', ''), 0, 255) );
        $hcaptcha_response = array_ifnull($_POST, 'h-captcha-response', '' );

        // Cleanup and validate data
        $required_fields = array( 'title', 'first_name', 'last_name', 'email', 'country' );
        foreach ( $data as $field => $value )
        {
            if ( in_array($field, $required_fields) )
            {
                if ( $value === '' )
                {
                    $errors[$field] = $parameters["ERROR_{$field}_blank"];
                }
                else
                {
                    if ( $field == 'title' && !array_key_exists($data['title'], $module->kernel->dict['SET_titles']) )
                    {
                        $errors['title'] = $parameters['ERROR_title_blank'];
                    }
                    if ( $field == 'email' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL) )
                    {
                        $errors['email'] = $parameters['ERROR_email_invalid'];
                    }
                    if ( $field == 'country' )
                    {
                        $country = array_search( $data['country'], $module->kernel->dict['SET_countries'] );
                        if ( $country === FALSE )
                        {
                            $errors[$field] = $parameters['ERROR_country_invalid'];
                        }
                        else
                        {
                            $data['country'] = $country;
                        }
                    }
                }
            }
            else if ( $value === '' )
            {
                $data[$field] = NULL;
            }
        }
        if ( $hcaptcha_response === '' )
        {
            $errors['h-captcha-response'] = $module->kernel->dict['ERROR_hcaptcha_response_blank'];
        }

        // Validate hCaptcha response
        if ( count($errors) == 0 )
        {
            $error = $module->validate_hcaptcha_response( $hcaptcha_response );
            if ( !is_null($error) )
            {
                $errors['h-captcha-response'] = 'hCaptcha Error: ' . $error;
            }
        }

        // Insert new subscription
        if ( count($errors) == 0 )
        {
            $values = array();
            foreach ( $data as $value )
            {
                $values[] = $module->kernel->db->escape( $value );
            }
            $sql = 'INSERT INTO subscriptions(' . implode( ', ', array_keys($data) ) . ', created_date, creator_id)';
            $sql .= ' VALUES(' . implode( ', ', $values ) . ', UTC_TIMESTAMP(), NULL)';
            $statement = $module->kernel->db->query( $sql );
            if ( !$statement )
            {
                $module->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
            }
            $id = $module->kernel->db->lastInsertId();
            $module->kernel->log( 'message', "Subscription $id <{$data['email']}> created", __FILE__, __LINE__ );
        }

        // Set page content
        header( 'Content-Type: application/json' );
        echo json_encode( $errors );
        exit();
    }

    // Display form
    else
    {
        $module->kernel->smarty->assignByRef( 'snippet_data', $parameters );
        return $module->kernel->smarty->fetch( "file/snippet/{$snippet['id']}/index.html" );
    }
}
