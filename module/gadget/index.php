<?php

// Include required files
require_once( dirname(dirname(__FILE__)) . '/base/index.php' );

/**
 * The gadget module.
 *
 * This module provides particular functions to use.
 *
 * @author  Martin Ng <martin@avalade.com>
 * @since   2011-11-28
 */
class gadget_module extends base_module
{
    /**
     * Constructor.
     *
     * @since   2009-01-29
     * @param   kernel      The kernel
     */
    function __construct( &$kernel )
    {
        parent::__construct( $kernel );
    }

    /**
     * Process the request.
     *
     * @since   2009-01-29
     * @return  Processed or not
     */
    function process()
    {
        // Choose operation, if not yet processed
        if ( !parent::process() )
        {
            $op = array_ifnull( $_GET, 'op', 'index' );
            switch ( $op )
            {
                case 'tree':
                    $this->tree();
                    return TRUE;
                    
                case 'form2both':
                    $this->form2crm();
                    //$this->form2email();
                    return TRUE;

                case 'proxy':
                    $this->proxy();
                    return TRUE;

                default:
                    return FALSE;
            }
        }
        return TRUE;
    }

    /**
     * Get the tree for sitemap.
     *
     * @since   2009-06-17
     * @param   webpages    The webpages
     * @param   index       The order index
     * @return  The tree
     */
    function get_tree( &$webpages, $index, $mod_from_doc )
    {
        $menu = array();
        foreach ( $index as $i => $index_alias )
        {
            $webpage = $webpages[$index_alias];
            $has_child = isset( $webpage['child_webpages'] );

            // Wrapper HTML for webpage title
            $open_html = '';
            $close_html = '';
            if ( $webpage['status'] == 'pending' )
            {
                $open_html .= '<i>';
                $close_html .= '</i>';
            }
            if ( $webpage['deleted'] == 1 )
            {
                $open_html .= '<del>';
                $close_html .= '</del>';
            }

            $submenu = array(
                'text' => sprintf(
                    '<a href="%1$s" onclick="%4$s" title="%3$s">%2$s</a>',
                    htmlspecialchars( "{$mod_from_doc}{$webpage['path']}" ),
                    $open_html . htmlspecialchars( $webpage['short_title'] ) . $close_html,
                    htmlspecialchars( $webpage['alias'] ),
                    'var e = arguments[0] || window.event; if ( e.stopPropagation ) e.stopPropagation(); else e.cancelBubble = true;'
                )
            );
            if ( $has_child )
            {
                if ( $webpage['path'] == '/' )
                {
                    $submenu['expanded'] = TRUE;
                    $submenu['children'] = $this->get_tree( $webpage['child_webpages'], $webpage['index'], $mod_from_doc );
                }
                else
                {
                    $submenu['id'] = $webpage['path'];
                    $submenu['hasChildren'] = TRUE;
                }
            }

            $menu[] = $submenu;
        }

        return $menu;
    }

    /**
     * Form to email.
     *
     * @since   2009-01-29
     * @return  Processed or not
     */
    function form2email()
    {
        // Compose the email
        $subject = '';
        $sender_email = $this->kernel->conf['mailer_email'];
        $recipient_email = $this->kernel->conf['mailer_email'];
        $body = array();
        foreach ( $_POST as $key => $value )
        {
            switch ( $key )
            {
                case 'subject':
                    $subject = $value;
                    break;

                case 'recipient_email':
                    //$recipient_email = $value;
                    //$recipient_email = 'info@urbanresortconcepts.com';
                    $recipient_email = 'draco.wang@avalade.com';
                    break;

                case 'redirect_url':
                case 'ajax':
                case 'undefined':
                case 'thankyou_msg':
                case 'title':
                case 'phone':
                case 'address':
                case 'city':
                case 'zipcode':
                    // Nothing
                    break;

                case 'submit':
                    // Nothing
                    break;

                case 'email':
                    $sender_email = $value;

                default:
                    $body[] = "$key: $value";
            }
        }

        // Try to send email
        $this->kernel->mailer->IsHTML( FALSE );
        $this->kernel->mailer->Subject    = $subject;
        $this->kernel->mailer->Body       = implode( "\r\n", $body );
		$this->kernel->mailer->ClearAllRecipients();
		$this->kernel->mailer->AddAddress( $recipient_email );
        $this->kernel->mailer->AddReplyTo( $sender_email );
        if ( $this->kernel->mailer->Send() )
        {
            $this->kernel->log( 'message', "Form to email sent", __FILE__, __LINE__ );
        }
        else
        {
			$this->kernel->log( 'error', $this->kernel->mailer->ErrorInfo, __FILE__, __LINE__ );
            //$this->kernel->quit( $this->kernel->mailer->ErrorInfo );
        }
        
        // Redirect to the next page
        $redirect_url = "{$this->kernel->sets['paths']['app_from_doc']}";

        if ( isset($_POST['redirect_url']) )
        {
            $redirect_url = $this->kernel->sets['paths']['server_url'].$redirect_url.$_POST['redirect_url'];
        }
        else if ( isset($_SERVER['HTTP_REFERER']) )
        {
            $redirect_url = $_SERVER['HTTP_REFERER'];
        }
        /*if ( preg_match("/^{$this->kernel->conf['url_preg']}$/", $redirect_url) == 0 )
        {
            $redirect_url = "{$this->kernel->sets['paths']['server_url']}$redirect_url";
        }*/

		if(isset($_POST['ajax']) && $_POST['ajax']==1)
		{
			$this->kernel->response['mimetype'] = 'application/json';
            $this->kernel->response['content'] = json_encode( array('result' => 'success') );
		}
		else
			$this->kernel->redirect( $redirect_url );
    }

    /**
     * Form to CRM.
     *
     * @since   2009-07-10
     * @return  Processed or not
     */
     function form2crm()
     {
        if ( $_SERVER['REQUEST_METHOD'] != 'POST' )
        {
            return;
        }

        $cache_msg = '';
        // create a new cURL resource
        $ch = curl_init();

        // set URL and other appropriate options
        curl_setopt($ch, CURLOPT_URL, $this->kernel->conf['crm_url']);
        curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, TRUE);

        $data_array = array();
        foreach ( $_POST as $key => $value )
        {
            $data_array[$key] = $value;
        }

        // generate the encryptted text for validation
        //--------previous validation method
        //$auth_text = md5($this->kernel->conf['auth_username']);
        //$auth_text .= md5($this->kernel->conf['auth_password']);
        //$data_array['auth_text'] = $auth_text;

        // build _POST list query from the array
        $data_list = http_build_query($data_array);

        // set the _POST fields
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_list);

        // generate HTTP authentication data
        $auth = $this->kernel->conf['auth_username'] . ":" . $this->kernel->conf['auth_password'];

        // set the _POST username and password
        curl_setopt($ch, CURLOPT_USERPWD, $auth);

        ob_start();
        $exec_result = curl_exec($ch);
        $cache_msg = ob_get_contents();
        ob_end_clean();

        // if no error occur
        //if ( $exec_result && intval($cache_msg) == -1 )
        if ( strpos($exec_result, 'success') !== false )
            $this->kernel->log( 'message', "Form to crm sent", __FILE__, __LINE__ );
        else // output error message
            $this->kernel->quit( $exec_result ? $cache_msg : curl_error($ch) );

        // close cURL resource, and free up system resources
        curl_close($ch);

        // Redirect to the next page
        $redirect_url = "{$this->kernel->sets['paths']['app_from_doc']}/";
        if ( isset($_POST['redirect_url']) )
        {
            $redirect_url = $this->kernel->sets['paths']['server_url'].$redirect_url.$_POST['redirect_url'];
        }
        else if ( isset($_SERVER['HTTP_REFERER']) )
        {
            $redirect_url = $_SERVER['HTTP_REFERER'];
        }
        /*if ( preg_match("/^{$this->kernel->conf['url_preg']}$/", $redirect_url) == 0 )
        {
            $redirect_url = "{$this->kernel->sets['paths']['server_url']}/$redirect_url";
        }*/
        
		if(isset($_POST['ajax']) && $_POST['ajax']==1)
		{
			$this->kernel->response['mimetype'] = 'application/json';
            $this->kernel->response['content'] = json_encode( array('result' => 'success') );
		}
		else
			$this->kernel->redirect( $redirect_url );
    }

    /**
     * Tree for sitemap.
     *
     * @since   2009-06-17
     */
    function tree()
    {
        // Get data from query string
        $target_path = trim( array_ifnull($_GET, 'root', '/') );
        if ( $target_path == 'source' )
        {
            $target_path = '/';
        }
        $mod_from_doc = trim( array_ifnull($_GET, 'mod_from_doc', '') );
        if ( $mod_from_doc == '' )
        {
            $mod_from_doc = "{$this->kernel->sets['paths']['app_from_doc']}/{$this->kernel->request['locale']}";
        }

        // Get the sitemap
        $sitemap = $this->get_sitemap( trim(array_ifnull($_GET, 'mode', '')) );

        // Get the target webpage
        $target_path_segments = explode( '/', $target_path );
        $target_aliases = array_slice( $target_path_segments, 1, count($target_path_segments) - 2 );
        $target_webpage =& $sitemap['tree'];
        while ( $target_webpage && count($target_aliases) > 0 )
        {
            $target_alias = array_shift( $target_aliases );
            if ( isset($target_webpage['child_webpages'][$target_alias]) )
            {
                $target_webpage =& $target_webpage['child_webpages'][$target_alias];
            }
            else
            {
                $dummy_webpage = array();
                $target_webpage =& $dummy_webpage;
            }
        }

        $this->apply_template = FALSE;
        $this->kernel->response['mimetype'] = 'application/json';
        if ( isset($_GET['root']) && $_GET['root'] == 'source' )
        {
            $target_child_webpages = array( '' => $target_webpage );
            $target_index = array( '' );
            $this->kernel->response['content'] = json_encode( $this->get_tree(
                $target_child_webpages,
                $target_index,
                $mod_from_doc
            ) );
        }
        else if ( isset( $target_webpage['child_webpages'] ) )
        {
            $target_child_webpages = $target_webpage['child_webpages'];
            $target_index = $target_webpage['index'];
            $this->kernel->response['content'] = json_encode( $this->get_tree(
                $target_child_webpages,
                $target_index,
                $mod_from_doc
            ) );
        }
        else
        {
            $this->kernel->response['content'] = '[]';
        }
    }

    /**
     * HTTP proxy.
     *
     * @since   2009-06-22
     */
    function proxy()
    {
        // Get data from query string
        $url = trim( array_ifnull($_GET, 'url', '') );

        // Get the HTTP response
        $curl = curl_init( $url );
        curl_setopt( $curl, CURLOPT_HEADER, TRUE );
        curl_setopt( $curl, CURLOPT_FOLLOWLOCATION, TRUE );
        curl_setopt( $curl, CURLOPT_RETURNTRANSFER, TRUE );
        if ( count($_POST) > 0 )
        {
            curl_setopt( $curl, CURLOPT_POST, TRUE );
            curl_setopt( $curl, CURLOPT_POSTFIELDS, http_build_query($_POST) );
        }
        $content = curl_exec( $curl );
        curl_close( $curl );

        // Process the HTTP response
        if ( $content == NULL )
        {
            $this->kernel->response['status_code'] = 404;
        }
        else
        {
            list( $headers, $content ) = explode( "\r\n\r\n", $content );

            // Parse the content type and character set
            $headers = http_parse_headers( $headers );
            if ( array_key_exists('Content-Type', $headers) )
            {
                $header_parts = explode( ';', $headers['Content-Type'] );
                $this->kernel->response['mimetype'] = $header_parts[0];
                if ( count($header_parts) > 1 )
                {
                    for ( $i = 1; $i < count($header_parts); $i++ )
                    {
                        $header_parameter_parts = explode( '=', trim($header_parts[$i]) );
                        if ( $header_parameter_parts[0] == 'charset'
                            && count($header_parameter_parts) > 1 )
                        {
                            $charset = strtolower( $header_parameter_parts[1] );
                            $this->kernel->response['charset'] = $charset;
                            if ( $charset != 'utf-8' )
                            {
                                $content = iconv( $charset, 'utf-8//TRANSLIT', $content );
                            }
                        }
                    }
                }
            }

            $this->kernel->response['content'] = $content;
        }
    }
}
