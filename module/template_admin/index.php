<?php

// Include required files
require_once( dirname(dirname(__FILE__)) . '/admin/index.php' );

/**
 * The template admin module.
 *
 * This module allows user to administrate templates.
 *
 * @author  Patrick Yeung <patrick@avalade.com>
 * @since   2013-12-10
 */
class template_admin_module extends admin_module
{
    private $tpl_base_dir;
    private $tpl_rel_dir = 'file/template/';
    public $module = 'template_admin';
    public $root_templates;
    private static $salt = 'q$C{L8s0[fIPW\'C>C]WKHY&P"j_Q,I?Uf*.*"3jYE\TGm2s<H6k&Psumg}&,b5b';

    /**
     * Constructor
     *
     * @param $kernel
     * @since 2013-12-10
     */
    function __construct( &$kernel )
    {
        parent::__construct( $kernel );

        $this->tpl_base_dir = $this->kernel->sets['paths']['app_root'] . '/' . $this->tpl_rel_dir;
        $this->root_templates = array(
            'desktop' => 0/*,
            'mobile' => 100*/
        );

        // Load additional dictionary file
        require( dirname(__FILE__) . "/locale/{$this->kernel->request['locale']}.php" );
        $this->kernel->dict = array_merge_recursive_unique( $this->kernel->dict, $DICT );
    }

    /**
     * Process the request.
     *
     * @since   2013-12-10
     */
    function process()
    {
        try{
            $op = array_ifnull( $_GET, 'op', 'index' );

            switch ( $op )
            {
                case "index":
                    $this->rights_required[] = Right::VIEW;
                    $this->method = "index";
                    break;
                case "retrieve_file":
                    $this->rights_required[] = Right::VIEW;
                    $this->method = "retrieveFile";
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

        return TRUE;
    }

    /**
     * Index page
     *
     * @since   2013-12-10
     */
    function index()
    {
        // get a list of desktop and mobile template
        $templates = array();
        $selected_template = "";
        // to construct the template list
        $template_list = array();
        $tpl = trim(array_ifnull($_GET, 'tpl', ''));

        $ajax = (bool)array_ifnull($_REQUEST, 'ajax', 0);

        try {
            foreach($this->root_templates as $tname => $tid) {
                $path = $this->getTplPath($tid, 'index.html');
                $t = base64_encode($path['relative']);

                // base template path
                if(!$selected_template)
                    $selected_template = $t;

                $template_list[$this->kernel->dict['SET_webpage_page_types'][$tname]] = array();

                $templates[$t] = array(
                    'tid' => $tid,
                    'name' => $this->kernel->dict['LABEL_template'] . ' ' . $tid,
                    'related_templates' => array(),
                    'templates_used' => '',
                    'path' => $path['absolute'],
                    'r_path' => $path['relative'],
                    'locale' => $this->kernel->default_public_locale,
                    'platform' => $tname,
                    'type' => 'html',
                    'mode' => 'text/html'
                );

                $template_list[$this->kernel->dict['SET_webpage_page_types'][$tname]] = array(
                    $t => $templates[$t]['r_path'] . ': ' . $templates[$t]['name']  . ' - (' . $this->kernel->dict['LABEL_root_template'] . ')'
                );

                // get css files
                /*$css_files = $this->listFiles($this->tpl_base_dir . $tid . '/', 'css');
                foreach($css_files as $path) {
                    $r_path = preg_replace('#^' . preg_quote($this->tpl_base_dir, '#') . '#i', '', $path);
                    $t = base64_encode($r_path);
                    
                    $templates[$t] = array(
                        'tid' => $tid,
                        'name' => basename($path),
                        'related_templates' => array(),
                        'templates_used' => '',
                        'path' => $path,
                        'r_path' => $r_path,
                        'locale' => $this->kernel->default_public_locale,
                        'platform' => $tname,
                        'type' => 'css',
                        'mode' => 'text/css'
                    );
                    $template_list[$this->kernel->dict['SET_webpage_page_types'][$tname]][$t] = $r_path . ': ' . $this->kernel->dict['LABEL_css_file'];
                }*/
            }
            // get locale files
            $locale_files = $this->listFiles($this->tpl_base_dir . 'locale/', 'txt');
            foreach($locale_files as $path) {
                $r_path = preg_replace('#^' . preg_quote($this->tpl_base_dir, '#') . '#i', '', $path);
                $t = base64_encode($r_path);
                $n = preg_replace('#\.txt#i', '', basename($path));
                $n = preg_replace('#~#i', '/', $n);
                if(isset($this->kernel->sets['public_locales'][$n]))
                {
                    $templates[$t] = array(
                        'tid' => -1,
                        'name' => basename($path),
                        'related_templates' => array(),
                        'templates_used' => '',
                        'path' => $path,
                        'r_path' => $r_path,
                        'locale' => $n,
                        'platform' => null,
                        'type' => 'txt',
                        'mode' => 'plain/text'
                    );
                    $template_list[$this->kernel->dict['LABEL_locale_files']][$t] = $r_path . ': '
                        . (isset($this->kernel->sets['public_locales'][$n]) ? $this->kernel->sets['public_locales'][$n] : $this->kernel->dict['LABEL_unknown']);
                }
            }
            
            // get style/style.css
            /*
            $path = $this->tpl_base_dir . 'style/style.css';
            $r_path = 'style/style.css';
            $t = base64_encode($r_path);
            $templates[$t] = array(
                'tid' => -1,
                'name' => basename($path),
                'related_templates' => array(),
                'templates_used' => '',
                'path' => $path,
                'r_path' => $r_path,
                'locale' => $this->kernel->default_public_locale,
                'platform' => null,
                'type' => 'css',
                'mode' => 'text/css'
            );
            $template_list[$this->kernel->dict['LABEL_css_file']][$t] = $r_path;
            */

            $sql = 'SELECT platform, base_template_id, GROUP_CONCAT(template_name SEPARATOR ", ") AS template_rely, GROUP_CONCAT(id SEPARATOR ",") AS template_id_rely'
                        . ' FROM templates WHERE deleted = 0 GROUP BY base_template_id';
            $statement = $this->conn->query($sql);

            while($row = $statement->fetch()) {
                $tid = $row['base_template_id'];
                $path = $this->getTplPath($tid, 'index.html');
                if($path) {
                    $t = base64_encode($path['relative']);
                    $templates[$t] = array(
                        'tid' => $tid,
                        'name' => $this->kernel->dict['LABEL_template'] . ' ' . $tid,
                        'related_templates' => array(),
                        'templates_used' => $row['template_rely'],
                        'path' => $path['absolute'],
                        'r_path' => $path['relative'],
                        'locale' => $this->kernel->default_public_locale,
                        'platform' => $row['platform'],
                        'type' => 'html',
                        'mode' => 'text/html'
                    );

                    $template_list[$this->kernel->dict['SET_webpage_page_types'][$row['platform']]][$t] = $templates[$t]['r_path'] . ': ' . $templates[$t]['name']  . ' - ('
                        . $this->kernel->dict['LABEL_used_in_templates'] . $templates[$t]['templates_used'] . ')';

                    // get css files
                    $css_files = $this->listFiles($this->tpl_base_dir . $tid . '/css/', 'css');
                    //$files_list = array('1/style.css', '1/css/responsive.css');
                    foreach($css_files as $path) {
                        $r_path = preg_replace('#^' . preg_quote($this->tpl_base_dir, '#') . '#i', '', $path);
                        $t = base64_encode($r_path);
                        
                        if(/*in_array($r_path, $files_list)*/!preg_match('/\.min\.css$/i', $r_path))
                        {
                            $templates[$t] = array(
                                'tid' => $tid,
                                'name' => basename($path),
                                'related_templates' => array(),
                                'templates_used' => '',
                                'path' => $path,
                                'r_path' => $r_path,
                                'locale' => $this->kernel->default_public_locale,
                                'platform' => $row['platform'],
                                'type' => 'css',
                                'mode' => 'text/css'
                            );
                            $template_list[$this->kernel->dict['SET_webpage_page_types'][$row['platform']]][$t] = $r_path . ': ' . $this->kernel->dict['LABEL_css_file'];
                        }
                    }
                }
            }

			if(count($_POST)) {
                $errors = array();
                $_POST['template_content'] = trim(array_ifnull($_POST, 'template_content', ''));
                $_POST['template_content'] = preg_replace( '~\R~u', "\n", $_POST['template_content'] );
                $_POST['selected_template'] = trim(array_ifnull($_POST, 'selected_template', ''));
                $_POST['preview_template_id'] = intval(array_ifnull($_POST, 'preview_template_id', 0));
                $_POST['preview_locale'] = trim(array_ifnull($_POST, 'preview_locale', $this->kernel->default_public_locale));

                if(!$_POST['template_content']) {
                    $errors['template_content'][] = 'template_content_empty';
                }

                if(!isset($templates[$_POST['selected_template']])) {
                    $errors['errorsStack'][] = 'file_not_found';
                }

                if(count($errors) > 0) {
                    throw new fieldsException($errors);
                }

                $target_template = $templates[$_POST['selected_template']];

                if($_POST['preview_template_id']) {
                    // put file in live preview
                    $code = $this->createTplToken($_POST['preview_template_id'], $target_template['r_path'], $target_template['type']);

                    $sql = sprintf('SELECT * FROM templates WHERE id = %d', $_POST['preview_template_id']);
                    $statement = $this->conn->query($sql);
                    $tmp = $statement->fetch();

                    if(!is_dir($this->kernel->sets['paths']['temp_root'] . '/live-previews/')) {
                        mkdir($this->kernel->sets['paths']['temp_root'] . '/live-previews/');
                    }

                    $content = $_POST['template_content'];
                    $tmp_file = $this->kernel->sets['paths']['temp_root'] . '/live-previews/' . $code['token'] . '.tpl_' . $code['type'];
                    if($target_template['type'] == 'css') {
                        $dir = dirname($this->tpl_rel_dir . $target_template['r_path']) . '/';

                        $regExps = array('#url\(\'([^\)]+?)\'\)#i', '#url\(\"([^\)]+?)\"\)#i');

                        foreach($regExps as $regExp) {
                            $new_content = "";
                            while(preg_match($regExp, $content, $matches, PREG_OFFSET_CAPTURE)) {
                                $offset = $matches[0][1];

                                $new_path = $matches[0][0];
                                if(!preg_match('#^https?\:\/\/#i', $matches[1][0])) {
                                    $new_path = preg_replace($regExp, '\\1', $matches[0][0]);
                                    $new_path = $this->kernel->sets['paths']['server_url'] . $this->kernel->sets['paths']['app_from_doc'] . '/' . $this->tpl_rel_dir . $target_template['tid'] . '/' . $new_path;
                                }

                                $next_pos = $offset + strlen($matches[0][0]);
                                $new_content .= substr($content, 0, $matches[1][1]) . $new_path . substr($matches[0][0], -2);
                                $content = substr($content, $next_pos);
                            }
                            $content = $new_content . $content;
                        }
                    }
                    file_put_contents( $tmp_file, $content );
                    
                    // make sure preview a "content" type webpage
                    $preview_relative_url = '/';
                    $sql = 'SELECT path FROM webpage_platforms wp LEFT JOIN webpages w ON (w.id=wp.webpage_id AND w.domain=wp.domain) WHERE wp.domain=\'public\' AND w.deleted=0 AND w.type=\'static\' ORDER BY wp.webpage_id ASC LIMIT 0,1';
                    $statement = $this->conn->query($sql);
                    if($record = $statement->fetch())
                    {
                        $preview_relative_url = $record['path'];
                    }

                    $redirect = $this->kernel->sets['paths']['server_url'] . $this->kernel->sets['paths']['app_from_doc']
                        . '/' . $_POST['preview_locale'] . '/preview'.$preview_relative_url ;
                    $this->kernel->redirect($redirect . '?' . http_build_query(array(
                                                                                    'm' => ($tmp['platform'] == "mobile" ? 1 : 0),
                                                                                    'pvtpl' => $code['code']
                                                                               )));

                } else {
                    // continue to process - pass error checking
                    // get the content and compare current version with md5
                    $encoded_content = md5(preg_replace('#\t\s\n#i', '', $_POST['template_content']));
                    $sql = sprintf('SELECT id, hash FROM template_archives WHERE file = %s ORDER BY id DESC LIMIT 0, 1'
                                    , $this->conn->escape($target_template['r_path']));
                    $statement = $this->conn->query($sql);

                    if(($record = $statement->fetch()) && $record['hash'] == $encoded_content) {
                        // same as previous record (ignore spaces, tabs etc.)
                        $sql = sprintf('UPDATE template_archives SET content = %1$s, updated_date = UTC_TIMESTAMP(), updater_id = %2$d'
                                        . ' WHERE id = %3$d'
                                        , $this->conn->escape($_POST['template_content']), $this->user->getId(), $record['id']);
                        $this->conn->exec($sql);
                    } else {
                        // insert new record
                        $sql = sprintf('INSERT INTO template_archives(file, content, hash, created_date, creator_id, updated_date, updater_id)'
                                            . ' VALUES(%1$s, %2$s, %3$s, UTC_TIMESTAMP(), %4$d, UTC_TIMESTAMP(), %4$d)'
                                            , $this->conn->escape($target_template['r_path']), $this->conn->escape($_POST['template_content'])
                                            , $this->conn->escape($encoded_content), $this->user->getId());
                        $this->conn->exec($sql);
                    }

                    // replace current value to a new one
                    $result = file_put_contents($target_template['path'], $_POST['template_content']);
                    if($result === FALSE) {
                        throw new fieldsException(array(
                                                       'errorsStack' => 'write_file_failed'
                                                  ));
                    } else {
                        $this->kernel->log('message', sprintf('User %1$d edited template %2$s'
                                , $this->user->getId().' <'.$this->user->getEmail().'>'
                                , $templates[$_POST['selected_template']]['r_path']
                            ), __FILE__, __LINE__
                        );
                        $this->clear_cache();
                    }

                    // Redirection URL
                    $redirect_get = array(
                        'op' => 'dialog',
                        'type' => 'message',
                        'code' => 'DESCRIPTION_saved',
                        'redirect_url' => $this->kernel->sets['paths']['server_url']
                            . $this->kernel->sets['paths']['mod_from_doc'] . '?tpl=' . $_POST['selected_template']
                    );

                    // continue to process (successfully)
                    $redirect = $this->kernel->sets['paths']['mod_from_doc'] . '?' . http_build_query($redirect_get);
                    if($ajax) {
                        $this->apply_template = FALSE;
                        $this->kernel->response['mimetype'] = 'application/json';
                        $this->kernel->response['content'] = json_encode( array(
                                                                               'result' => 'success',
                                                                               'redirect' => $redirect
                                                                          ));
                        return TRUE;
                    } else {
                        $this->kernel->redirect($redirect);

                        return TRUE;
                    }
                }
			} else {
				if($tpl) {
					if(isset($templates[$tpl])) {
						$selected_template = $tpl;
					}
				}
			}

        } catch(Exception $e) {
            $this->processException($e);
        }

        // continue to process if not ajax
        if(!$ajax) {
            $title = sprintf($this->kernel->dict['SET_operations']['edit'], $templates[$selected_template]['name']);

            $this->_breadcrumb->push(new breadcrumbNode($title, $this->kernel->sets['paths']['mod_from_doc'] . '?tpl=' . $selected_template));

            $this->kernel->smarty->assign('title', $title);

            $content = file_get_contents(trim($templates[$selected_template]['path']));
            $this->kernel->smarty->assign('template_content', $content);
            $this->kernel->smarty->assign('templates', $templates);

            $preview_templates = array();
            $valid_for_preview = false;

            if($content) {
                switch($templates[$selected_template]['type']) {
                    case 'html':
                    case 'css':
                        $valid_for_preview = true;

                        if(in_array($templates[$selected_template]['tid'], array_values($this->root_templates))) {
                            $p = array_search($templates[$selected_template]['tid'], array_values($this->root_templates));
                            $template_platforms = array_keys($this->root_templates);
                            $sql = sprintf('SELECT platform, id, template_name FROM templates WHERE platform = %s AND deleted = 0', $this->conn->escape($template_platforms[$p]));
                            $statement = $this->conn->query($sql);
                        } else {
                            $sql = sprintf('SELECT platform, id, template_name FROM templates WHERE base_template_id = %d AND deleted = 0', $templates[$selected_template]['tid']);
                            $statement = $this->conn->query($sql);
                        }

                        break;
                    case 'txt':
                        $valid_for_preview = true;
                        $sql = sprintf('SELECT platform, id, template_name FROM templates WHERE deleted = 0 ORDER BY platform');
                        $statement = $this->conn->query($sql);
                        break;
                }

                if($valid_for_preview) {
                    while($row = $statement->fetch()) {
                        $preview_templates[] = array(
                            'template_name' => sprintf('[%s] %s',
                                $this->kernel->dict['SET_webpage_page_types'][$row['platform']]
                                , $row['template_name']),
                            'template_id' => $row['id']
                        );
                    }
                }
            }

            $this->kernel->smarty->assign('template_list', $template_list);
            $this->kernel->smarty->assign('selected_template', $selected_template);
            $this->kernel->smarty->assign('preview_templates', $preview_templates);

            $referer_url = array_ifnull($_SERVER, 'HTTP_REFERER', "");
            $this->kernel->smarty->assign('referer_url', $referer_url);

            $this->kernel->response['content'] = $this->kernel->smarty->fetch( 'module/template_admin/index.html' );
        }
    }

    function retrieveFile() {
        $code = trim(array_ifnull($_GET, 'f', ''));

        $info = template_admin_module::decodeTplToken($code);
        switch($info['token_type']) {
            case 'css':
                $this->apply_template = false;
                $this->kernel->response['mimetype'] = 'text/css';
                $this->kernel->response['content'] = file_get_contents($this->kernel->sets['paths']['temp_root'] . '/live-previews/' . $info['token'].'.tpl_css');
                break;
        }
    }

    function getTplPath($tid, $name) {
        if(file_exists($this->tpl_base_dir . $tid . '/' . $name)) {
            return array(
                'absolute' => $this->tpl_base_dir . $tid . '/' . $name,
                'relative' => $tid . '/' . $name
            );
        }
        return false;
    }

    function listFiles($dir, $file_type = "") {
        if(!preg_match('#\/$#', $dir))
            $dir .= '/';

        $lt = array();

        if (is_dir($dir) && $handle = opendir($dir)) {
            while (false !== ($file_name = readdir($handle))) {
                if ($file_name != "." && $file_name != "..") {
                    $p = $dir . $file_name;
                    if(is_dir($p)) {
                        $lt = array_merge($lt, $this->listFiles($p . '/', $file_type));
                    } elseif(!$file_type || preg_match('#\.' . preg_quote($file_type, '#') . '$#i', $p)) {
                        $lt[] = $p;
                    }
                }
            }
            closedir($handle);
        }

        return $lt;
    }

    public function createTplToken($tpl_id, $rpath, $type = "html") {
        $token_parts = array(
            $tpl_id,
            $type,  // type
            $rpath,    // id
            microtime(), // timestamp
            $this->user->getId(),
            generate_password(10) // random string
        );

        $token = md5(implode('|', $token_parts));

        return array(
            'type' => $type,
            'token' => $token,
            'code' => $this->encodeTplToken($tpl_id, $token, $rpath, $type)
        );
    }

    public function encodeTplToken($tpl_id, $token, $rpath, $type = "html") {
        return base64_encode(admin_module::xor_encode($tpl_id . '|' . $rpath . '|' . $type . '|' . $token, template_admin_module::$salt));
    }

    public static function decodeTplToken($encoded_content) {
        $code = base64_decode($encoded_content);

        $decoded_contents = explode('|', admin_module::xor_decode($code, template_admin_module::$salt));
        try {
            if(count($decoded_contents) == 4) {
                  return array(
                      'tpl_id' => $decoded_contents[0],
                      'path' => $decoded_contents[1],
                      'token_type' => $decoded_contents[2],
                      'token' => $decoded_contents[3]
                  );

            }

            throw new Exception("wrong code");
        } catch (Exception $e) {
            return false;
        }

        return false;
    }
}