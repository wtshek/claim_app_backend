<?php

// Include required files
require_once( dirname(dirname(__FILE__)) . '/admin/index.php' );

/**
 * The snippet code generator admin module.
 *
 * This module allows user to administrate snippet codes for webpages.
 *
 * @author  Draco Wang <draco.wang@avalade.com>
 * @since   2015-09-08
 */
class snippet_generator_admin_module extends admin_module
{
    public $module = 'snippet_generator_admin';
    public $exclude_snippet_ids = array(1, 2);

    /**
     * Constructor.
     *
     * @since   2008-11-06
     * @param   kernel      The kernel
     */
    function __construct( &$kernel )
    {
        parent::__construct( $kernel );

        // Load additional dictionary file
        require( dirname(__FILE__) . "/locale/{$this->kernel->request['locale']}.php" );
        $this->kernel->dict = array_merge_recursive_unique( $this->kernel->dict, $DICT );

        // Set body class
        $_GET['bare'] = array_ifnull( $_GET, 'bare', NULL );
        if ( $_GET['bare'] )
        {
            $this->kernel->response['bodyCls'][] = 'bare';
        }
    }

    /**
     * Process the request.
     *
     * @since   2008-11-06
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
                case "edit":
                    $this->rights_required[] = array_ifnull( $_REQUEST, 'id', 0 ) ? Right::EDIT : Right::CREATE;
                    $this->method = "edit";
                    break;
                    
                case "delete":
                    $this->rights_required[] = Right::EDIT;
                    $this->method = "delete";
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
     * Get FROM and WHERE values in SQL statement for users.
     *
     * @since   2008-11-06
     * @param   find    An array of query variables
     * @return  The FROM and WHERE values in SQL statement
     */
    function get_query_values( $find )
    {
        $unwanted = $this->kernel->conf['unwanted_matching_characters'];    // To shorten the name

        // Default FROM and WHERE values
        $from = 'customize_snippets cs LEFT JOIN snippets s ON (s.id=cs.snippet_type_id)';
        $from .=' LEFT JOIN ( SELECT snippet_id, GROUP_CONCAT(tmp.locales SEPARATOR ", ") AS active_pages, IFNULL(count(webpage_id), 0) AS num FROM (SELECT snippet_id, webpage_id, CONCAT("[#", webpage_id, "] (", GROUP_CONCAT(l2.name SEPARATOR " / "), ")") AS locales FROM (SELECT * FROM webpage_snippets GROUP BY snippet_id, webpage_id, webpage_locale) AS ws2 LEFT JOIN locales l2 ON (l2.alias=ws2.webpage_locale) WHERE l2.enabled=1 AND l2.site="public_site" GROUP BY snippet_id, webpage_id) tmp GROUP BY snippet_id ) ws ON (ws.snippet_id=cs.id)';
        
        $where = array( 'cs.deleted=0', 's.id NOT IN ('.implode(',', $this->exclude_snippet_ids).')' );

        // Keyword
        if ( $find['keyword'] !== '' )
        {
            $keyword_where = array();
            $fields = array(
                'cs.name',
                's.alias',
                'ws.active_pages'
            );
            $value = $this->kernel->db->escape( '%' . $this->kernel->db->cleanupStringForMatching($find['keyword'], $unwanted) . '%' );
            foreach ( $fields as $field )
            {
                $keyword_where[] = $this->kernel->db->cleanupFieldForMatching( $field, $unwanted ) . " LIKE $value";
            }
            $where[] = '(' . implode(' OR ', $keyword_where) . ')';
        }

        // name
        if ( $find['name'] !== '' )
        {
            $where[] = 'cs.name = ' . $this->kernel->db->escape($find['name']);
        }
        
        // webpage_id
        if( $find['webpage_id'] > 0)
        {
            $where[] = 'ws.active_pages LIKE ' . $this->kernel->db->escape('%'.$find['webpage_id'].'%');
        }
        
        // content_block_id
        if( $find['content_block_id'] > 0)
        {
            $where[] = 'cs.id LIKE ' . $this->kernel->db->escape('%'.$find['content_block_id'].'%');
        }

        return array(
            'from' => $from,
            'where' => $where
        );
    }

    /**
     * List snippets.
     *
     * @since   2015-09-16
     */
    function index()
    {
        $list_id = '_snippet_list';

        // Query condition
        $_GET['keyword'] = trim( array_ifnull($_GET, 'keyword', '') );
        $_GET['name'] = trim( array_ifnull($_GET, 'name', '') );
        $_GET['webpage_id'] = intval( array_ifnull($_GET, 'webpage_id', 0) );
        $_GET['webpage_id'] = $_GET['webpage_id'] == 0 ? '' : $_GET['webpage_id'];
        $_GET['content_block_id'] = intval( array_ifnull($_GET, 'content_block_id', 0) );
        $_GET['content_block_id'] = $_GET['content_block_id'] == 0 ? '' : $_GET['content_block_id'];

        // Query condition
        extract( $this->get_query_values($_GET) );

        // Actions
        $referer_url = '?' . http_build_query( $_GET );
        $record_actions = $list_actions = array();
        if ( $this->user->hasRights($this->module, array(Right::EDIT)) )
        {
            if ( $this->user->hasRights('webpage_admin', array(Right::EDIT)) )
            {
                $record_actions['#'] = $this->kernel->dict['ACTION_select'];
            }

            $record_actions['?' . http_build_query(array(
                'bare' => $_GET['bare'],
                'op' => 'edit',
                'referer_url' => $referer_url,
                'id' => ''
            ))] = $this->kernel->dict['ACTION_edit'];
            
            $record_actions['?' . http_build_query(array(
                'bare' => $_GET['bare'],
                'op' => 'delete',
                'referer_url' => $referer_url,
                'id' => ''
            ))] = $this->kernel->dict['ACTION_delete'];
            
            /*$list_actions['?' . http_build_query(array(
                'op' => 'edit',
                'referer_url' => $referer_url
            ))] = $this->kernel->dict['ACTION_new'];*/
        }

        // Get the requested snippets
        $select = array(
            'cs.id', 'cs.id AS id_display', 's.snippet_name AS snippet_type', 'cs.name', 'ws.active_pages', 'num'
        );

		$list = $this->kernel->get_smarty_list_from_db(
            'all'.$list_id,
            'id',
            array(
                'select' => implode( ',', $select ),
                'from' => $from,
                'where' => implode(' AND ', $where),
                'group_by' => '',
                'having' => '',
                'default_order_by' => 'id',
                'default_order_dir' => 'ASC'
            ),
            array(),
            $record_actions,
            $list_actions,
            'all'.$list_id,
            'module/snippet_generator_admin/list.html', 
            array(), 
            array(),
            array('num')
        );
        $this->kernel->smarty->assignByRef( 'list', $list);
        
        // Get snippet types list and get the snippets list by type
        $type_lists = array();
        $snippet_types = array();
        $sql = 'SELECT * FROM snippets WHERE deleted=0 AND id NOT IN ('.implode(',', $this->exclude_snippet_ids).') ORDER BY snippet_name';
        $statement = $this->kernel->db->query( $sql );
        if ( !$statement )
        {
            $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
        }
        while($record = $statement->fetch())
        {
            $snippet_types[$record['alias']] = $record;
        }
        
        foreach($snippet_types as $alias => $type)
        {
            $type_where = array_merge( $where, array('cs.snippet_type_id = ' . $this->kernel->db->escape($type['id'])) );
            $type_list = $this->kernel->get_smarty_list_from_db(
                $alias.$list_id,
                'id',
                array(
                    'select' => implode( ',', $select ),
                    'from' => $from,
                    'where' => implode(' AND ', $type_where),
                    'group_by' => '',
                    'having' => '',
                    'default_order_by' => 'id',
                    'default_order_dir' => 'ASC'
                ),
                array(),
                $record_actions,
                $list_actions,
                $alias.$list_id, 
                'module/snippet_generator_admin/list.html', 
                array(), 
                array(),
                array('num')
            );
            
            $type_list['name'] = $type['snippet_name'];
            $type_lists[$alias] = $type_list;
        }
        
        $this->kernel->smarty->assign( 'type_lists', $type_lists );
        $this->kernel->response['content'] = $this->kernel->smarty->fetch( 'module/snippet_generator_admin/index.html' );
    }


    /**
     * Edit/Create a snippet based on customize snippet ID.
     *
     * @since   2015-09-08
     */
    function edit()
    {
        $ajax = (bool)array_ifnull($_REQUEST, 'ajax', 0);
        $snippet_type = trim(array_ifnull($_GET, 'snippet_type', '')); // snippet alias

        // Data container
        $data = array();
        $snippet_types = array();

        // Get data from query string
        $id = intval( array_ifnull($_REQUEST, 'id', 0) );

        try {
            if(count($_POST) > 0) {
                $errors = array();

                $data = $this->parseData($_POST, $snippet_type);

                $errors = $this->errorChecking($data, $snippet_type);
                
                // continue to process (successfully)
                if(count($errors) > 0) {
                    throw new fieldsException($errors);
                } else {
                    $this->conn->beginTransaction();
                    
                    $snippet_type_id = 0;
                    $sql = 'SELECT id FROM snippets WHERE alias='.$this->kernel->db->escape($data['snippet_type']);
                    $statement = $this->kernel->db->query( $sql );
                    if ( !$statement )
                    {
                        $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
                    }
                    if($record = $statement->fetch())
                        $snippet_type_id = $record['id'];
                    
                    if ( $id )
                    {
                        // Update existing snippet
                        $sql = 'UPDATE customize_snippets SET';
                        $sql .= ' snippet_type_id = ' . $snippet_type_id . ',';
                        $sql .= ' name = ' . $this->kernel->db->escape($data['snippet_name']) . ',';
                        $sql .= ' general_para_value=';
                        if(isset($data['general_para_value']))
                            $sql .= $this->kernel->db->escape($data['general_para_value']) . ',';
                        else
                            $sql .=  $this->kernel->db->escape(NULL) . ',';
                        $sql .= ' updated_time = UTC_TIMESTAMP(),';
                        $sql .= " updater_id = {$this->user->getId()}";
                        $sql .= " WHERE id = $id";
                        if ( $this->conn->exec($sql) > 0 )
                        {
                            $this->kernel->log( 'message', "Customized snippet $id updated", __FILE__, __LINE__ );
                        }
                        
                        foreach($data['snippet_content'] as $alias=>$content)
                        {
                            $sql = 'DELETE FROM customize_snippet_locales WHERE snippet_id='.$id.' AND locale='.$this->conn->escape($alias);
                            $this->conn->exec( $sql );
                            
                            $sql = 'INSERT INTO customize_snippet_locales (snippet_id, locale, parameter_values) VALUES (';
                            $sql .= $id.', '.$this->conn->escape($alias).', '.$this->kernel->db->escape($content);
                            $sql .= ')';
                            $this->conn->exec( $sql );
                        }
                    }
                    else
                    {
                        // Insert new snippet
                        $sql = 'INSERT INTO customize_snippets(snippet_type_id, name, general_para_value,';
                        $sql .= ' created_time, creator_id) VALUES(';
                        $sql .= $snippet_type_id . ',';
                        $sql .= $this->kernel->db->escape($data['snippet_name']) . ',';
                        $sql .= isset($data['general_para_value']) ? $this->kernel->db->escape($data['general_para_value']) . ',' : $this->kernel->db->escape(NULL) . ',';
                        $sql .= 'UTC_TIMESTAMP(),';
                        $sql .= "{$this->user->getId()})";
                        $this->conn->exec( $sql );
                        $id = $this->conn->lastInsertId();
                        $this->kernel->log( 'message', "Customized snippet $id created", __FILE__, __LINE__ );
                        
                        foreach($data['snippet_content'] as $alias=>$content)
                        {
                            $sql = 'INSERT INTO customize_snippet_locales (snippet_id, locale, parameter_values) VALUES (';
                            $sql .= $id.', '.$this->conn->escape($alias).', '.$this->kernel->db->escape($content);
                            $sql .= ')';
                            $this->conn->exec( $sql );
                        }
                    }

                    $this->conn->commit();
                }

                $redirect = $this->kernel->sets['paths']['mod_from_doc'] . '?' .
                    http_build_query(array(
                        'bare' => $_GET['bare'],
                        'op' => 'dialog',
                        'type' => 'message',
                        'code' => 'DESCRIPTION_saved',
                        'redirect_url' => $this->kernel->sets['paths']['server_url']
                            . $this->kernel->sets['paths']['mod_from_doc']
                            . '?' . http_build_query( array(
                                'bare' => $_GET['bare'],
                                'op' => 'edit',
                                'id' => $id,
                                'snippet_type' => $snippet_type,
                                'referer_url' => array_ifnull( $_GET, 'referer_url', '' )
                            ) )
                    ));
                if($ajax) {
                    $this->apply_template = FALSE;
                    $this->kernel->response['mimetype'] = 'application/json';
                    $this->kernel->response['content'] = json_encode( array(
                                                                           'result' => 'success',
                                                                           'redirect' => $redirect
                                                                      ));
                } else {
                    $this->kernel->redirect($redirect);
                }
                return TRUE;
            }

        } catch(Exception $e) {
            $this->processException($e);
        }

        // continue to process if not ajax
        if(!$ajax) {
            // Get the list of snippet types
            $sql = "SELECT * FROM snippets WHERE deleted=0 AND id NOT IN (".implode(',', $this->exclude_snippet_ids).") ORDER BY snippet_name";
            $statement = $this->kernel->db->query( $sql );
            if ( !$statement )
            {
                $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
            }
            for($i = 0; $record = $statement->fetch(); $i++)
            {
                if($i == 0)
                    $default_type = $record['alias'];
                $snippet_types[$record['alias']] = $record['snippet_name'].' [#'.$record['id'].']';
            }

            if($snippet_type == '' || !isset($snippet_types[$snippet_type]))
            {
                $snippet_type = $default_type;
            }

            $this->kernel->smarty->assign( 'snippet_types', $snippet_types );

            // Get the requested snippet
            $sql = "SELECT cs.*, s.alias AS snippet_type, CONVERT_TZ(cs.created_time, '+00:00', ";
            $sql .= " {$this->kernel->conf['escaped_timezone']}) AS created_date,";
            $sql .= " CONVERT_TZ(cs.updated_time, '+00:00',";
            $sql .= " {$this->kernel->conf['escaped_timezone']}) AS updated_date,";
            $sql .= ' creators.first_name AS creator_user_name,';
            $sql .= ' creators.email AS creator_email,';
            $sql .= ' updaters.first_name AS updater_user_name,';
            $sql .= ' updaters.email AS updater_email';
            $sql .= ' FROM customize_snippets cs';
            $sql .= ' LEFT OUTER JOIN users AS creators ON (cs.creator_id = creators.id)';
            $sql .= ' LEFT OUTER JOIN users AS updaters ON (cs.updater_id = updaters.id)';
            $sql .= ' LEFT JOIN snippets s on (s.id=cs.snippet_type_id)';
            $sql .= " WHERE cs.id = $id";
            $statement = $this->kernel->db->query( $sql );
            if ( !$statement )
            {
                $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
            }
            $data = ($record = $statement->fetch()) ? array_merge($record, $data) : array();

            if ( count($data) == 0 )
            {
                // Set default values for new snippet
                $data['id'] = $id = 0;
                $data['snippet_type'] = $snippet_type;
            }
            else
            {
                if($record['general_para_value'] != '')
                    $data['general_para_value'] = json_decode($record['general_para_value'], true);
                
                $snippet_type = $record['snippet_type'];

                // Group /para\d/ into parameter groups
                if(isset($this->kernel->dict['SET_snippet_parameter_groups'][$snippet_type]))
                {
                    $data['group_paras'] = array();
					if(isset($data['general_para_value']))
					{
						foreach($data['general_para_value'] as $para=>$value)
						{
							preg_match('/^([a-zA-Z\-_]+)(\d+)$/i', $para, $matches);
							if(!empty($matches) && in_array($matches[1], $this->kernel->dict['SET_snippet_parameter_groups'][$snippet_type]))
							{
								$data['group_paras'][$matches[2]][$matches[1]]=$value;
							}
						}
					}
                }

                // Get locale values of snippet
                $parameter_locales = array();
                $sql = 'SELECT * FROM customize_snippet_locales WHERE snippet_id='.$id;
                $statement = $this->kernel->db->query( $sql );
                if ( !$statement )
                {
                    $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
                }
                while($record = $statement->fetch())
                {
                    $data[$record['locale']] = json_decode($record['parameter_values'], true);
                    $parameter_locales[] = $record['locale'];
                }

                // Group /para\d/ into parameter groups
                if(isset($this->kernel->dict['SET_snippet_parameter_groups'][$snippet_type]))
                {
                    foreach($data as $locale=>&$paras)
                    {
                        if(in_array($locale, $parameter_locales))
                        {
                            $data[$locale]['group_paras'] = array();
                            if(in_array($snippet_type, array('print_stories_whitepaper')))
                            {
                                foreach($this->kernel->dict['SET_snippet_parameter_groups'][$snippet_type] as $group=>$items)
                                {
                                    $data[$locale]['group_paras'][$group] = array();
                                }

                                foreach($paras as $para=>$value)
                                {
                                    preg_match('/^([a-zA-Z\-_]+)(\d+)$/i', $para, $matches);
                                    if(!empty($matches))
                                    {
                                        foreach($this->kernel->dict['SET_snippet_parameter_groups'][$snippet_type] as $group=>$items)
                                        {
                                            if(in_array($matches[1], $items))
                                                $data[$locale]['group_paras'][$group][$matches[2]][$matches[1]]=$value;
                                        }
                                    }
                                }
                            }
                            else
                            {
                                foreach($paras as $para=>$value)
                                {
                                    preg_match('/^([a-zA-Z\-_]+)(\d+)$/i', $para, $matches);
                                    if(!empty($matches) && in_array($matches[1], $this->kernel->dict['SET_snippet_parameter_groups'][$snippet_type]))
                                    {
                                        $data[$locale]['group_paras'][$matches[2]][$matches[1]]=$value;
                                    }
                                }
                            }
                        }
                    }
                }
            }
            $this->kernel->smarty->assign( 'snippet_type', $snippet_type );

            // BreadCrumb
            $this->_breadcrumb->push(new breadcrumbNode($this->kernel->dict[$id == 0 ? 'ACTION_new' : 'ACTION_edit']
                    , $this->kernel->sets['paths']['mod_from_doc'] . '?op=edit' . ($id == 0 ? "" : ("&id=" . $id)))
            );

            // Assign data to view
            $this->kernel->smarty->assignByRef( 'data', $data );

            // Set page title
            if ( $id > 0 )
            {
                $this->kernel->dict['SET_operations']['edit'] = sprintf(
                    $this->kernel->dict['SET_operations']['edit'],
                    $data['name'].' [#'.$id.']'
                );
                $this->kernel->response['titles'][] = $this->kernel->dict['SET_operations']['edit'];
            }
            else
            {
                $this->kernel->dict['SET_operations']['new'] = sprintf(
                    $this->kernel->dict['SET_operations']['new'],
                    $this->kernel->dict['LABEL_new_snippet']
                );
                $this->kernel->response['titles'][] = $this->kernel->dict['SET_operations']['new'];
            }

            if($id > 0) {
                $info = array(
                    'created_date_message' => sprintf($this->kernel->dict['INFO_created_date'], '<b>' . $data['created_date'] . '</b>', '<b>' . $data['creator_user_name'] . '</b>', '<b>' . $data['creator_email'] . '</b>')
                );

                if($data['updated_date']) {
                    $info['last_update_message'] = sprintf($this->kernel->dict['INFO_last_update'], '<b>' . $data['updated_date'] . '</b>', '<b>' . $data['updater_user_name'] . '</b>', '<b>' . $data['updater_email'] . '</b>');
                }

                $this->kernel->smarty->assign('info', $info);
            }

            $this->kernel->smarty->assign('default_locale', $this->kernel->default_public_locale);
            
            $this->kernel->smarty->assign('type_specific_form', $this->kernel->smarty->fetch( 'module/snippet_generator_admin/'.$snippet_type.'.html' ));
            $this->kernel->response['content'] = $this->kernel->smarty->fetch( 'module/snippet_generator_admin/edit.html' );
        }
    }

    
    /**
     * Delete a snippet
     */
    function delete(){
        $snippet_id = intval(array_ifnull($_GET, 'id', 0));
        
        try{
            if($snippet_id >0 )
            {
                $sql  = 'UPDATE customize_snippets SET deleted=1 WHERE id='.$snippet_id;
                if ( $this->conn->exec($sql) > 0 )
                {
                    $this->kernel->log( 'message', "Customized snippet $snippet_id deleted", __FILE__, __LINE__ );
                }
            }
        }catch(Exception $e) {
            $this->processException($e);
        }
        
        $redirect = $this->kernel->sets['paths']['server_url']
                    . $this->kernel->sets['paths']['mod_from_doc']
                    . '?' . http_build_query( array(
                        'bare' => $_GET['bare'],
                        'op' => 'index',
                        'referer_url' => array_ifnull( $_GET, 'referer_url', '' )
                    ) );
        $this->kernel->redirect($redirect);
    }
    
    function parseData($post_data, $snippet_type){
        $data['snippet_type'] = trim(array_ifnull($post_data, 'snippet_type', ''));
        $data['snippet_name'] = trim(array_ifnull($post_data, 'snippet_name', ''));
        $data['status'] = trim(array_ifnull($post_data, 'status', ''));
        $data['id'] = intval( array_ifnull($post_data, 'id', 0) );
        
        $data['snippet_content'] = array();
        
        $accessible_alias = $this->user->getAccessibleLocales();
        foreach($post_data as $alias=>$para_values)
        {
            $recover_alias = preg_replace('/::/i', '/', $alias);
            if(in_array($recover_alias, $accessible_alias))
            {
                if(!isset($data['snippet_content'][$recover_alias]))
                    $data['snippet_content'][$recover_alias] = array();
                $data['snippet_content'][$recover_alias] = array_merge($post_data[$alias], $data['snippet_content'][$recover_alias]);            
            }
        }
        foreach($this->user->getAccessibleLocales() as $alias){
			if(isset($data['snippet_content'][$alias]))
				$data['snippet_content'][$alias] = json_encode($data['snippet_content'][$alias]);
			else
				$data['snippet_content'][$alias] = '';
        }
        
        if(isset($post_data['general_para_value']))
        {
            $data['general_para_value'] = json_encode($post_data['general_para_value']);
        }
        
        return $data;
    }
    
    function errorChecking($data, $snippet_type){
        // error checking
        $errors = array();
        
        if($data['snippet_name'] == '')
        {
            $errors['snippet_name'][] = 'snippet_name_blank';
        }
        
        return $errors;
        
    }
}
?>