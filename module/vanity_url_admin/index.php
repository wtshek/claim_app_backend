<?php

// Include required files
require_once( dirname(dirname(__FILE__)) . '/admin/index.php' );
require_once( dirname(dirname(__FILE__)) . '/webpage_admin/index.php' );

/**
 * The vanity URLs admin module.
 *
 * This module allows user to administrate vanity urls.
 *
 * @author	Steve Hua <stevehua1992@gmail.com>
 * @author  Martin Ng <martin@avalade.com>
 * @since   2015-06-11
 */
class vanity_url_admin_module extends admin_module
{
    public $module = 'vanity_url_admin';

    /**
     * Constructor.
     *
     * @since   2008-12-01
     * @param   kernel      The kernel
     */
    function __construct( &$kernel )
    {
        parent::__construct( $kernel );

        // Load additional dictionary file
        require( dirname(__FILE__) . "/locale/{$this->kernel->request['locale']}.php" );
        $this->kernel->dict = array_merge_recursive_unique( $this->kernel->dict, $DICT );
    }

    /**
     * Process the request.
     *
     * @since   2015-06-11
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
				case "delete":
					$this->rights_required[] = Right::EDIT;
					$this->method = "delete_url";
					break;
                case "tree":
                    $this->rights_required[] = Right::VIEW;
                    $this->method = "tree";
                    break;
                case "get_webpage_nodes":
                    $this->rights_required[] = Right::VIEW;
                    $this->method = "getWebpageNodes";
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
     * Get the tree for webpage.
     *
     * @since   2009-04-27
     * @param   name        Name of radio input
     * @param   webpages    The webpages
     * @param   index       The order index
     * @param   path        The opened path
     * @return  The tree
     */
    function get_tree( $name, &$webpages, $index, $path = '' )
    {
        $menu = array();
        foreach ( $index as $i => $index_alias )
        {
            $webpage = $webpages[$index_alias];
            $has_child = isset( $webpage['child_webpages'] );
            $opened = strpos( $path, $webpage['path'] ) !== FALSE;

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
                    '<label title="%4$s" onclick="%7$s"><input type="radio" name="%1$s" value="%2$s"%5$s%6$s>%3$s</label>',
                    $name,
                    $webpage['webpage_id'],
                    $open_html . htmlspecialchars( "{$webpage['short_title']} (#{$webpage['webpage_id']})" ) . $close_html,
                    htmlspecialchars( $webpage['alias'] ),
                    $webpage['path'] === $path ? ' checked' : '',
                    $name == 'footer_webpage_id' && $webpage['path'] == '/' ? ' disabled' : '',
                    'var e = arguments[0] || window.event; if ( e.stopPropagation ) e.stopPropagation(); else e.cancelBubble = true;'
                )
            );
            if ( $has_child )
            {
                if ( $opened )
                {
                    $submenu['expanded'] = TRUE;
                    $submenu['children'] = $this->get_tree( $name, $webpage['child_webpages'], $webpage['index'], $path );
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
     * Tree for webpage.
     *
     * @since   2009-04-27
     */
    function tree()
    {
        // Get data from query string
        $target_path = trim( array_ifnull($_GET, 'root', '/') );
        if ( $target_path == 'source' )
        {
            $target_path = '/';
        }

        // Get the sitemap
        $sitemap = $this->get_sitemap( 'edit' );

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
                array_ifnull( $_GET, 'name', '' ),
                $target_child_webpages,
                $target_index,
                array_ifnull( $_GET, 'path', '' )
            ) );
        }
        else if ( isset( $target_webpage['child_webpages'] ) )
        {
            $target_child_webpages = $target_webpage['child_webpages'];
            $target_index = $target_webpage['index'];
            $this->kernel->response['content'] = json_encode( $this->get_tree(
                array_ifnull( $_GET, 'name', '' ),
                $target_child_webpages,
                $target_index,
                array_ifnull( $_GET, 'path', '' )
            ) );
        }
        else
        {
            $this->kernel->response['content'] = '[]';
        }
    }

    function getWebpageNodes($type = 'json', $platform = 'desktop', $target = 0, $disable_target = false) {
        return webpage_admin_module::getWebpageNodes($type, $platform, $target, $disable_target, webpage_admin_module::getWebpageAccessibility());
    }

    /**
     * List / Edit vanity URLs.
     *
     * @since   2015-06-11
     */
    function index()
    {
        // Data container
        $data = array();
        $data_locales = array();
        $site_tree = array();
 
        // get locales of public site
        $sql = sprintf('SELECT * FROM locales WHERE site=%1$s AND enabled=1 ORDER BY `default` DESC, order_index ASC',
            $this->conn->escape('public_site')
        );
        $statement = $this->conn->query($sql);
        $locales = $statement->fetchAll();

        $ajax = (bool)array_ifnull($_REQUEST, 'ajax', 0);

        try {
            if(count($_POST) > 0) {
                $errors = array();

                // Get data from query string and form post
                $data['vanity_url_id'] = array_ifnull($_POST, 'vanity_url_id', array());
                $data['vanity_url_alias'] = array_ifnull($_POST, 'vanity_url_alias', array());
                $data['redirect_to'] = array_ifnull($_POST, 'redirect_to', array());
                $data['redirect_to_id'] = array_ifnull($_POST, 'internal_id', array());
                $data['description'] = array_ifnull($_POST, 'description', array());
                $data['start_date'] = array_ifnull($_POST, 'start_date', array());
                $data['end_date'] = array_ifnull($_POST, 'end_date', array());
				$data['active'] = array_ifnull($_POST, 'active', array());
				$data['deleted'] = array_ifnull($_POST, 'deleted', array());
				$data['record_modified'] = array_ifnull($_POST, 'record_modified', array());

                //clean data
                $text_fields = array('vanity_url_alias', 'redirect_to', 'description', 'start_date', 'end_date');
                $int_fields = array('vanity_url_id', 'redirect_to_id', 'active', 'deleted', 'record_modified');
                foreach($text_fields as $t_field)
                {
                    $data[$t_field.'s'] = array_map('trim', $data[$t_field]);
                }
                foreach($int_fields as $i_field)
                {
                    $data[$i_field.'s'] = array_map('intval', $data[$i_field]);
                }

                //Data Validation
                //vanity_url_alias: not blank, invalid chars, distinct; redirect_to||redirect_to_id: not blank, invalid chars; start date not blank; start_date+end_date range invalid
                $alias_temp = array();
				//$sql = ""
                foreach($data['vanity_url_aliass'] as $k=>$alias)
                {
                   	// Only check the rows that modified or new added, but not deleted rows
                    if(($data['record_modifieds'][$k] == 1 || $data['vanity_url_ids'][$k] == '') && $data['deleteds'][$k] == 0)
					{
						if($alias!='')
	                    {
	                        //invalid chars
	                        if(preg_match("/[\?='\"#\/\\\&%\^\*]+/", $alias))
	                        {
	                            $errors['vanity_url_alias_'.$k][] = $this->kernel->dict['ERROR_vanity_url_alias_invalid'];
	                        }
							else {
								if(in_array($alias, $alias_temp))
								{
									$errors['vanity_url_alias_'.$k][] = $this->kernel->dict['ERROR_vanity_url_alias_duplicate'];
								}
								else {
									$alias_temp[] = $alias;
									$sql = 'SELECT IFNULL(count(*), 0) AS num FROM vanity_urls WHERE id<>'.intval($data['vanity_url_ids'][$k]).' AND vanity_url_alias='.$this->conn->escape($alias);
									$statement = $this->conn->query($sql);
                                    extract($statement->fetch());
									if($num>0)
										$errors['vanity_url_alias_'.$k][] = $this->kernel->dict['ERROR_vanity_url_alias_duplicate'];
								}
							}
	                    }
	                    else
	                    {
							//alias blank
	                        $errors['vanity_url_alias_'.$k][] = $this->kernel->dict['ERROR_vanity_url_alias_empty'];	
	                    }
	            	}

					if($data['redirect_tos'][$k] == '' && $data['redirect_to_ids'][$k] == 0)
					{
						$errors['redirect_to_'.$k][] = $this->kernel->dict['ERROR_redirect_to_empty'];
					}
					else if($data['redirect_to_ids'][$k] == 0 && $data['redirect_tos'][$k] != '')
					{
						/*if(!preg_match("/^[A-Za-z][A-Za-z\-_0-9\/]$/", $data['redirect_tos'][$k]))
                        {
                            $errors['redirect_to_'.$k][] = $this->kernel->dict['ERROR_redirect_to_invalid'];
                        }*/
					}
					
					if($data['start_dates'][$k] == '')
					{
						$errors['start_date_'.$k][] = $this->kernel->dict['ERROR_start_date_empty'];
					}
					elseif($data['start_dates'][$k] != '' && $data['end_dates'][$k] != '')
					{
						if(strtotime($data['start_dates'][$k])>=strtotime($data['end_dates'][$k]))
							$errors['start_date_'.$k][] = $this->kernel->dict['ERROR_date_range_invalid'];
					}
                }

                // continue to process (successfully)
                if(count($errors) > 0) {
                    //throw new fieldsException($errors);
                    if($ajax) {
                        $this->apply_template = FALSE;
                        $this->kernel->response['mimetype'] = 'application/json';
                        $this->kernel->response['content'] = json_encode( array(
                                                                               'result' => 'error',
                                                                               'errors' => $errors
                                                                          ));
                	}				
					return TRUE;
				}
				else{
                    $this->conn->beginTransaction();
					
					foreach($data['vanity_url_aliass'] as $k=>$alias)
	                {
	                   	// Only check the rows that modified, new added or deleted rows
	                    if($data['record_modifieds'][$k] == 1 || $data['vanity_url_ids'][$k] == '')
						{
							// Convert local time to UTC time
							if($data['start_dates'][$k] != '')
                            {
                                $data['start_dates'][$k] = strtotime($data['start_dates'][$k]) - 3600*8;
                                $data['start_dates'][$k] = date('Y-m-d H:i:s', $data['start_dates'][$k]);
                            }
                            if($data['end_dates'][$k] != '')
                            {
                                $data['end_dates'][$k] = strtotime($data['end_dates'][$k]) - 3600*8;
                                $data['end_dates'][$k] = date('Y-m-d H:i:s', $data['end_dates'][$k]);
                            }
                            else
                            {
                                $data['end_dates'][$k] = NULL;
                            }                                
                            
							if($data['deleteds'][$k] == 1 && $data['vanity_url_ids'][$k] != '')
							{
							 /* Done by ajax
								$sql = 'UPDATE vanity_urls SET deleted=1 WHERE id='.$data['vanity_url_ids'][$k];
								$this->conn->exec( $sql );
                    			$this->kernel->log( 'message', "Vanity URL {$data['vanity_url_ids'][$k]} was deleted by user {$this->user->getId()}", __FILE__, __LINE__ );*/
							}
							elseif($data['vanity_url_ids'][$k] == '')
							{
								// Insert new record
								$sql = sprintf('INSERT INTO vanity_urls (vanity_url_alias, redirect_to, redirect_to_id, description, start_date, end_date, active, deleted, creator_id, created_time) VALUES (%1$s, %2$s, %3$d, %4$s, %5$s, %6$s, %7$d, %8$d, %9$d, UTC_TIMESTAMP())'
								, $this->kernel->db->escape($data['vanity_url_aliass'][$k])
								, $this->kernel->db->escape($data['redirect_tos'][$k])
								, $data['redirect_to_ids'][$k]
								, $this->kernel->db->escape($data['descriptions'][$k])
								, $this->kernel->db->escape($data['start_dates'][$k])
								, $this->kernel->db->escape($data['end_dates'][$k])
								, $data['actives'][$k]
								, $data['deleteds'][$k]
								, $this->user->getId()
								);
								$this->conn->exec( $sql );
								
								$this->kernel->log( 'message', "Vanity URL {$this->kernel->db->lastInsertId()} was created by user {$this->user->getId()}", __FILE__, __LINE__ );
							}
							elseif($data['vanity_url_ids'][$k] != '')
							{
								// Update old record
								$sql = sprintf('UPDATE vanity_urls SET vanity_url_alias=%1$s, redirect_to=%2$s, redirect_to_id=%3$d, description=%4$s, start_date=%5$s, end_date=%6$s, active=%7$d, deleted=%8$d, updater_id=%9$d, updated_time=UTC_TIMESTAMP() WHERE id=%10$d'
								, $this->kernel->db->escape($data['vanity_url_aliass'][$k])
								, $this->kernel->db->escape($data['redirect_tos'][$k])
								, $data['redirect_to_ids'][$k]
								, $this->kernel->db->escape($data['descriptions'][$k])
								, $this->kernel->db->escape($data['start_dates'][$k])
								, $this->kernel->db->escape($data['end_dates'][$k])
								, $data['actives'][$k]
								, $data['deleteds'][$k]
								, $this->user->getId()
								, $data['vanity_url_ids'][$k]
								);
								$this->conn->exec( $sql );
								
								$this->kernel->log( 'message', "Vanity URL {$data['vanity_url_ids'][$k]} was updated by user {$this->user->getId()}", __FILE__, __LINE__ );
							}
						}
					}
					              
                    $this->conn->commit();

                    $redirect = $this->kernel->sets['paths']['mod_from_doc'] . "?" .
                        http_build_query(array(
                                              'op' => 'dialog',
                                              'type' => 'message',
                                              'code' => 'DESCRIPTION_saved',
                                              'redirect_url' => '.'
                                         ));
                    if($ajax) {
                        $this->apply_template = FALSE;
                        $this->kernel->response['mimetype'] = 'application/json';
                        $this->kernel->response['content'] = json_encode( array(
                                                                               'result' => 'success',
                                                                               'redirect' => $redirect
                                                                          ));
                    }
                    return TRUE;
                }
            }
			else
			{
				// Pagination
				$page_index = $this->kernel->conf['page_enabled'] && isset( $_GET["vanity_url_page"] ) ? $_GET["vanity_url_page"] : 0;
				// Row counting
		        $count_query = "SELECT COUNT(*) AS record_count FROM vanity_urls WHERE deleted=0";
		        $count_statement = $this->kernel->db->query( $count_query );
		        if ( !$count_statement )
		        {
		            $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $count_query, __FILE__, __LINE__ );
		        }
		        extract( $count_statement->fetch() );
		        $page_index = min( $page_index, floor(abs($record_count-1)/$this->kernel->conf['page_size']) );
				
				$sql = "SELECT id, vanity_url_alias, redirect_to, redirect_to_id, description, active, deleted, CONVERT_TZ(start_date, 'UTC', ".$this->kernel->conf['escaped_timezone'].") AS start_date, CONVERT_TZ(end_date, 'UTC', ".$this->kernel->conf['escaped_timezone'].") AS end_date FROM vanity_urls WHERE deleted=0 ORDER BY updated_time DESC";
				if ( $this->kernel->conf['page_enabled'] )
		        {
		            $sql .= ' LIMIT ' . ( $page_index * $this->kernel->conf['page_size'] ) . ", {$this->kernel->conf['page_size']}";
		        }
				$statement = $this->conn->query($sql);
                $row_count = 0;
				while($r = $statement->fetch())
				{
					$data[$r['id']] = $r;
					if($r['redirect_to_id']>0)
					{
						$sql = 'SELECT webpage_title, webpage_id FROM webpage_locales wl WHERE domain=\'public\' AND webpage_id='.$r['redirect_to_id'].' AND locale='.$this->kernel->db->escape($this->kernel->default_public_locale);
						$statement2 = $this->conn->query($sql);
						$data[$r['id']]['redirect_to'] = ($r2 = $statement2->fetch()) ? $r2['webpage_title'].' - [#'.$r2['webpage_id'].']' : '';
					}
                    $row_count++;
				}
				
				// Construct list summary
		        $summary = array(
		            'id' => 'vanity_url',
		            'page_size' => $this->kernel->conf['page_size'],
		            'page_index' => $page_index,
		            'page_count' => $this->kernel->conf['page_enabled'] ? ceil( $record_count/$this->kernel->conf['page_size'] ) : 1,
		            'record_count' => $record_count
		        );
		
		        $summary['formatted_page_index'] = sprintf( $this->kernel->dict['FORMAT_page_index'], $summary['page_index'] + 1 );
		        $summary['formatted_page_count'] = sprintf( $this->kernel->dict['FORMAT_page_count'], $summary['page_count'] );
		        $summary['formatted_record_count'] = sprintf( $this->kernel->dict['FORMAT_record_count'], ($row_count == 0 ? 0 : $summary['page_index'] * $this->kernel->conf['page_size'] + 1), ($summary['page_index'] * $this->kernel->conf['page_size'] + $row_count), $record_count, ($record_count === 1 ? $this->kernel->dict['LABEL_entry'] : $this->kernel->dict['LABEL_entries']) );

		        // Construct page list
		        $pages = array();
		        if ( $page_index >= 0 && $page_index < ceil($this->kernel->conf['page_limit']/2)-1 )            // Head
		        {
		            $pages = range( 0, min($this->kernel->conf['page_limit'], $summary['page_count'])-1 );
		        }
		        else if ( $page_index >= $summary['page_count']-floor($this->kernel->conf['page_limit']/2)      // Tail
		            && $page_index < $summary['page_count'] )
		        {
		            $pages = range( max(0, $summary['page_count']-$this->kernel->conf['page_limit']), $summary['page_count']-1 );
		        }
		        else                                                                                    // Middle
		        {
		            $pages = range( $page_index-floor(($this->kernel->conf['page_limit']-1)/2), $page_index+ceil(($this->kernel->conf['page_limit']-1)/2) );
		        }
                
                $this->kernel->smarty->assign( 'summary', $summary );
                $this->kernel->smarty->assign( 'pages', $pages );
			}
        } catch(Exception $e) {
            $this->processException($e);
        }

        // continue to process if not ajax
        if(!$ajax) {
            // Assign data to view
            $this->kernel->smarty->assignByRef( 'data', $data );
            $this->kernel->smarty->assignByRef( 'locales', $locales );

            $this->kernel->response['content'] = $this->kernel->smarty->fetch( 'module/vanity_url_admin/index.html' );
        }
    }

    function delete_url(){
    	$id = intval(array_ifnull($_POST, 'id', 0));
		$ajax = intval(array_ifnull($_POST, 'ajax', 0));
		
		if($id && $ajax)
		{
			$sql = 'UPDATE vanity_urls SET deleted=1, updated_time=UTC_TIMESTAMP(), updater_id='.$this->user->getId().' WHERE id='.$id;
			$this->conn->exec( $sql );
            $this->kernel->log( 'message', "Vanity URL {$id} was deleted by user {$this->user->getId()}", __FILE__, __LINE__ );
			
			$this->apply_template = FALSE;
            $this->kernel->response['mimetype'] = 'application/json';
            $this->kernel->response['content'] = json_encode( array(
                                                 	'result' => 'success'
            									 ));
		}		
		return TRUE;
    }
	
    function generateDynaTree($ary = array(), $return = 'json', $id = 0, $disabled = false, $deleted = false, $status = false) {
        $output = array();

        foreach($ary as $item) {
            $classes = array(
                'status' => false
            );
            $child = array(
                'title' => ($item['title'] ? $item['title'] : '(' . $this->kernel->dict['LABEL_no_title'] . ')') . ' - [#' . $item['id'] . ']',
                'key' => isset($item['id']) ?  $item['id'] : $item['name'],
                'href' => $this->kernel->sets['paths']['server_url'] . $this->kernel->sets['paths']['app_from_doc'] . '/[lang]' . $item['path'],
                'tooltip' => $item['path'],
                'unselectable' => false,
                'selected' => false
            );

            if($item['deleted'] || $deleted) {
                $classes[] = 'deleted';
            }

            if($disabled) {
                $classes[] = 'disabled';
                $child['unselectable'] = true;
            }

            if($child['key'] == $id) {
                $child['selected'] = true;
            }

            if(in_array($item['status'], array("pending", "draft")) || $status) {
                $classes['status'] = in_array($item['status'], array("pending", "draft")) ? $item['status'] : $status;
            }

            if($item['hasChild']) {
                $child['lazy'] = true;
                if(isset($item['children'])) {
                    if($child['key'] == $id) {
                        //exit;
                    }
                    $tmp = $this->generateDynaTree($item['children'], $return, $id, ($child['key'] == $id || $disabled), ($deleted || in_array('deleted', $classes)), $classes['status'] == "pending" ? "pending" : false);
                    if($tmp) {
                        $child['children'] = $tmp;
                    }
                }
            }

            $child['addClass'] = implode( ' ', array_filter($classes, 'strlen') );

            $output[] = $child;
        }

        if($return == "html") {
            $html = "<ul>";
            foreach($output as $item) {
                $data = array();
                if($item['unselectable']) {
                    $data[] = 'unselectable: true';
                }
                if(isset($item['addClass']) && $item['addClass']) {
                    $data[] = sprintf("addClass: '%s'", $item['addClass']);
                }
                if($item['selected']) {
                    $data[] = 'selected: true';
                }
                $html .= sprintf('<li id="%1$s" class="%4$s %5$s" data="%7$s"><a href="%3$s" title="%2$s">%2$s</a>%6$s</li>'
                    , $item['key']
                    , htmlspecialchars($item['title'])
                    , htmlspecialchars($item['href'])
                    , isset($item['isLazy']) ? "lazy" : ""
                    , isset($item['children']) ? "expanded" : ""
                    , isset($item['children']) ? $item['children'] : ""
                    , implode(', ', $data));
            }
            $html .= "</ul>";
            return $html;
        } else {
            return $output;
        }
    }
}