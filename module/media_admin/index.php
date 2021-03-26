<?php

// Include required files
require_once( dirname(dirname(__FILE__)) . '/admin/index.php' );

/**
 * The media admin module.
 *
 * This module allows user to administrate media files.
 *
 * @author  Martin Ng <martin@avalade.com>
 * @since   2008-12-23
 */
class media_admin_module extends admin_module
{
    public $module = 'share_file_admin';

    /**
     * Constructor.
     *
     * @since   2008-12-23
     * @param   kernel      The kernel
     */
    function __construct( &$kernel )
    {
        parent::__construct( $kernel );
    }

    /**
     * Process the request.
     *
     * @since   2008-12-23
     * @return  Processed or not
     */
    function process()
    {
        try{
            $op = array_ifnull( $_GET, 'op', 'index' );
            switch ( $op )
            {
                case "index":
                    $this->rights_required[] = Right::ACCESS;
                    $this->method = "index";
                    break;
                case "check_file_usage":
                    $this->rights_required[] = Right::ACCESS;
                    $this->method = "check_file_usage";
                    break;
                default:
                    return parent::process();
            }

            // process right checking and throw error and stop further process if any
            $this->user->checkRights($this->module, array_unique($this->rights_required));

            if($this->method) {
                call_user_func_array(array($this, $this->method), $this->params);
            }

            return TRUE;
        } catch(Exception $e) {
            $this->processException($e);
        }

        return FALSE;
    }

    /**
     * Ajax File Manager.
     *
     * @since   2008-11-06
     */
    function index()
    {
        // Make sure the media directory is present
        //require_once(dirname(dirname(__FILE__)) . '/file_admin/connectors/php/plugins/s3/filemanager.s3.class.php');
        //$AWS = new FilemanagerS3;
        //$media_dir = "{$this->kernel->sets['paths']['app_root']}/file/media";
        //force_mkdir( $media_dir );

        //$media_share_dir = "{$this->kernel->sets['paths']['app_root']}/file/media/share";
        //force_mkdir( $media_share_dir );

        //$this->kernel->control['editor_media_subfolder'] = 'share';
        //echo print_r($_SESSION);
        //echo print_r($this->user);exit;
        
        $this->kernel->response['content'] = $this->kernel->smarty->fetch( "module/media_admin/index.html" );
    }

    function get_folder_files($folder)
    {
        $temp_files = array();
        if(!$dh = @opendir($folder));
        else
        {
            while (false !== ($obj = readdir($dh))) {
                if($obj=='.' || $obj=='..') continue;
                try{
                    //if (!@unlink($folder.'/'.$obj)) $this->empty_folder($folder.'/'.$obj);
                    if (is_dir($folder.($folder[strlen($folder)-1] == '/' ? '' : '/').$obj)) $temp_files = array_merge($temp_files, $this->get_folder_files($folder.($folder[strlen($folder)-1] == '/' ? '' : '/').$obj));
                    else $temp_files[] = $folder.($folder[strlen($folder)-1] == '/' ? '' : '/').$obj;
                } catch(exception $e){}
            }
        }

        closedir($dh);
        return $temp_files;
    }

    function check_file_usage()
    {
        $_POST['t'] = trim(array_ifnull($_POST, 't', 'share'));
        $_POST['p'] = intval(array_ifnull($_POST, 'p', 0));
        $_POST['f'] = array_map('trim', array_ifnull($_POST, 'f', array()));
        $_POST['d'] = array_map('trim', array_ifnull($_POST, 'd', array()));

        $webpage_count = 0;

        foreach($_POST['d'] as $d)
        {
            if($d != '' && ($_POST['t'] == 'share' || ($_POST['t'] == 'page' && $_POST['p'] > 0)))
            {
                $pattern = (($_POST['t'] == 'share') ? '/(.+)(\/file)(\/media)(\/share\/)/' : '/(.+)(\/file\/media)(\/page\/)(temp\/' . $this->user->getId() . '_\d+\/)/');
                $replacement = '${2}${3}${4}';
                $rd = preg_replace($pattern, $replacement, $d);

                if(is_dir($this->kernel->sets['paths']['app_root'] . $rd))
                {
                //  $this->kernel->log($this->kernel->sets['paths']['app_root'] . $rd);
                    $files = $this->get_folder_files($this->kernel->sets['paths']['app_root'] . $rd);

                    foreach($files as $p)
                    {
                        $pattern = '/(.+)(\/file)(\/media)(\/' . (($_POST['t'] == 'share') ? 'share' : 'page\/temp\/' . $this->user->getId()) . '_\d+\/)(.+)/';
                        $replacement = '${2}${3}${4}${5}';
                        $add_file_path = preg_replace($pattern, $replacement, $p);
                        //$this->kernel->log('message', $add_file_path);

                        if($add_file_path != '')
                            $_POST['f'][] = $add_file_path;
                    }
                }
            }
        }

        foreach($_POST['f'] as $f)
        {
            if($f != '' && $_POST['t'] == 'share')
            {
                $pattern = '/(.+file)(\/media)(\/share\/)/';
                $replacement = 'file${2}${3}${4}';
                $rf = preg_replace($pattern, $replacement, $f);

                //$sql = '(SELECT webpage_id, locale FROM private_static_webpage_locale_content WHERE content LIKE ' . $this->kernel->db->escape('%'.$rf.'%') . ' )'
                        //. ' UNION '
                        //. '(SELECT webpage_id, locale FROM public_static_webpage_locale_content WHERE content LIKE ' . $this->kernel->db->escape('%'.$rf.'%') . ' )';
                $sql = 'SELECT webpage_id, locale FROM webpage_locale_contents WHERE content LIKE '. $this->kernel->db->escape('%'.$rf.'%');
                $result = $this->kernel->db->execute($sql);
                if ( $this->kernel->db->errorNo() )
                {
                    $error_msg = $this->kernel->db->errorMsg();
                    $this->kernel->quit( 'DB Error: ' . $error_msg, $sql, __FILE__, __LINE__ );
                }
                $webpage_count += $result->recordCount();
                //$this->kernel->log('message', $sql);
            }
            else if($f != '' && $_POST['t'] == 'page' && $_POST['p'] > 0)
            {
                $rp_num = 0;
                $pattern = '/(.+)(\/media)(\/page\/)(temp\/' . $this->user->getId() . '_\d+\/)/';
                $replacement = '[file_loc_folder:' . $_POST['p'] . ']/';
                $encoded_path = preg_replace($pattern, $replacement, $f, -1, $rp_num);

                if($rp_num > 0)
                {
                    $replacement = '${2}${3}private/' . $_POST['p'] . '/';
                    $private_path = preg_replace($pattern, $replacement, $f, -1, $rp_num);

                    $replacement = '${2}${3}public/' . $_POST['p'] . '/';
                    $public_path = preg_replace($pattern, $replacement, $f, -1, $rp_num);

                    //$sql = '(SELECT webpage_id, locale FROM private_static_webpage_locale_content WHERE content LIKE ' . $this->kernel->db->escape('%'.$encoded_path.'%') . ' OR content LIKE ' . $this->kernel->db->escape('%'.$private_path.'%') . ' )'
                        //. ' UNION '
                        //. '(SELECT webpage_id, locale FROM private_static_webpage_locale_content WHERE content LIKE ' . $this->kernel->db->escape('%'.$encoded_path.'%') . ' OR content LIKE ' . $this->kernel->db->escape('%'.$public_path.'%') . ' )';
                    $sql = 'SELECT webpage_id, locale FROM webpage_locale_contents WHERE content LIKE '.$this->kernel->db->escape('%'.$encoded_path.'%').'OR content LIKE '.$this->kernel->db->escape('%'.$public_path.'%').'OR content LIKE '.$this->kernel->db->escape('%'.$private_path.'%');
                    $result = $this->kernel->db->execute($sql);
                    if ( $this->kernel->db->errorNo() )
                    {
                        $error_msg = $this->kernel->db->errorMsg();
                        $this->kernel->quit( 'DB Error: ' . $error_msg, $sql, __FILE__, __LINE__ );
                    }
                    $webpage_count += $result->recordCount();

                    //$this->kernel->log('message', $sql);
                }
            }
        }

        $json_msg = array(
            'webpage_count' => $webpage_count
        );

        /*
        $sql = '(SELECT webpage_id, locale FROM archive_static_locale_webpages)'
                . ' UNION '
                . '(SELECT webpage_id, locale FROM private_static_locale_webpages)'
                . ' UNION '
                . '(SELECT webpage_id, locale FROM public_static_locale_webpages)'
        */

        $this->apply_template = FALSE;
        $this->kernel->response['mimetype'] = 'application/json';
        $this->kernel->response['content'] = json_encode($json_msg);
    }
}