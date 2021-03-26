<?php

// Include required files
require_once( dirname(dirname(__FILE__)) . '/admin/index.php' );
require_once( dirname(__FILE__) . '/offer.php');

/**
 * The offer admin module.
 *
 * This module allows user to administrate offers.
 *
 * @author  Patrick Yeung <patrick@avalade.com>
 * @since   2013-08-27
 *
 */
class offer_admin_module extends admin_module
{
    const MAX_OFFER = 3;
    public $module = 'offer_admin';

    protected $nodes_status = array();
    protected $selected_webpages = array();

    /**
     * Constructor
     *
     * @param $kernel
     * @since 2013-07-03
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
     * @since   2013-07-03
     */
    function process()
    {
        try {
            // Choose operation, if not yet processed
            //if ( !parent::process() )
            //{
            $op = array_ifnull( $_GET, 'op', 'index' );
            switch ( $op )
            {
                case "index":
                    $this->rights_required[] = Right::ACCESS;
                    $this->method = "index";
                    break;
                case "order_index_fast_update":
                    $this->rights_required[] = Right::EDIT;
                    $this->method = "order_index_fast_update";
                    break;
                case "view":
                    $this->rights_required[] = Right::VIEW;
                    $this->method = "view";
                    break;
                case "get_nodes":
                    $this->rights_required[] = Right::VIEW;
                    $this->method = "getNodes";
                    break;
                case "edit":
                    $this->rights_required[] = Right::CREATE;
                    if(array_ifnull($_REQUEST, 'id', 0))
                        $this->rights_required[] = Right::EDIT;
                    $this->method = "edit";
                    break;
                case "retrieve_offer_preview":
                    $this->rights_required[] = Right::VIEW;
                    $this->method = "retrieveOffer";
                    break;
                case "get_webpage_offers":
                    $this->rights_required[] = Right::VIEW;
                    $this->method = "getWebpageOffersInJson";
                    break;
                case "get_offer_page_trees":
                    $this->rights_required[] = Right::VIEW;
                    $this->method = "getOfferPageTree";
                    break;
                case "delete":
                case "undelete":
                    $this->rights_required[] = Right::EDIT;
                    $this->method = "setDelete";
                    $this->params = array( $op == 'delete' ? 1 : 0 );
                    break;
                case "approve":
                    $this->rights_required[] = Right::APPROVE;
                    $this->method = "setStatus";
                    $this->params = array( $op == 'approve' ? "approve" : "pending" );
                    break;
                case 'generate_token':
                    $webpage_id = intval(array_ifnull($_GET, 'id', ''));

                    $this->rights_required[] = Right::EDIT;
                    $this->method = "genToken";
                    $this->params = array($webpage_id);
                    break;
                case 'remove_token':
                    $webpage_id = intval(array_ifnull($_GET, 'id', ''));

                    $this->rights_required[] = Right::EDIT;
                    $this->method = "removeToken";
                    $this->params = array($webpage_id);
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
    }

    /**
     * Index
     *
     * @since 2013-07-03
     */
    function index() {
        // Get offers on page
        //$edit_action = $this->kernel->sets['paths']['app_from_doc'] . '/' . $this->kernel->request['locale'] . '/admin/webpage/' . "?op=edit&id=";
        $edit_action = $this->kernel->sets['paths']['app_from_doc'] . '/admin/' . $this->kernel->request['locale'] . '/webpage/' . "?op=edit&id=";

        // get webpage for offers
        $sm = $this->get_sitemap('edit', 'desktop');
        $offer_page = false;
        if($sm->getRoot() && $this->kernel->conf['offer_webpage_id']) {
            $offer_page = $sm->getRoot()->findById($this->kernel->conf['offer_webpage_id']);
        }

        // select only static or structured page
        $inner_select = sprintf("SELECT w.id AS webpage_id, SUBSTRING_INDEX(GROUP_CONCAT(wp.path ORDER BY wp.major_version DESC, wp.minor_version DESC), ',', 1) AS path,"
            . " SUBSTRING_INDEX(GROUP_CONCAT(w.major_version ORDER BY w.major_version DESC, w.minor_version DESC), ',', 1) AS major_version,"
            . " SUBSTRING_INDEX(GROUP_CONCAT(w.minor_version ORDER BY w.major_version DESC, w.minor_version DESC), ',', 1) AS minor_version,"
            . ' SUBSTRING_INDEX(GROUP_CONCAT(wl.webpage_title ORDER BY wp.major_version DESC, wp.minor_version DESC, locale = %1$s DESC, locale ASC SEPARATOR \'\r\n\'), \'\r\n\', 1) AS webpage_title,'
            . " SUBSTRING_INDEX(GROUP_CONCAT(wp.deleted ORDER BY wp.major_version DESC, wp.minor_version DESC), ',', 1) AS deleted,"
            . ' SUBSTRING_INDEX(GROUP_CONCAT(wl.status ORDER BY wp.major_version DESC, wp.minor_version DESC, locale = %1$s DESC, locale ASC), \',\', 1) AS status'
            . ' FROM webpages AS w'
            . ' JOIN webpage_platforms AS wp ON (w.domain = wp.domain AND w.id = wp.webpage_id AND w.major_version = wp.major_version AND w.minor_version = wp.minor_version)'
            . ' JOIN webpage_locales AS wl ON (w.domain = wl.domain AND w.id = wl.webpage_id AND w.major_version = wl.major_version AND w.minor_version = wl.minor_version)'
            . " WHERE w.domain = 'private' AND w.type IN ('static', 'structured_page') AND wp.platform = 'desktop'"
            . ' GROUP BY w.id'
            . " HAVING (deleted = 0 OR status <> 'approved')",
            $this->conn->escape($this->user->getPreferredLocale())
        );

        $extra_actions = array();

        if($this->user->hasRights('webpage_admin', Right::VIEW))
            $extra_actions['webpage_title'] = array(
                'prefix_url' => $this->kernel->sets['paths']['app_from_doc'] . '/' . $this->kernel->request['locale'] . '/preview',
                'postfix_url' => 'path',
                'target' => '_blank'
            );

        $page_offers = $this->kernel->get_smarty_list_from_db(
            'page_offers',
            'webpage_id',
            array(
                 'select' => '*',
                 'from' => sprintf('('
                     // direct pages
                     . '(SELECT tb.webpage_id, tb.webpage_title, tb.path, CONCAT("<span class=\"offers_count\">", COUNT(DISTINCT wo.offer_id), " (" , COUNT(IF(o.status = "approved", 1, NULL)) , " / ", COUNT(IF(o.status = "pending", 1, NULL)) , ")</span>" ) AS offers_count'
                     . ', "N/A" AS copy_from '
                     . ', CONCAT("<span class=\"offer_no\">", GROUP_CONCAT(wo.offer_id  ORDER BY wo.order ASC SEPARATOR "</span>&nbsp;<span class=\"offer_no\">"), "</span>") AS offers'
                     . ' FROM (%1$s) AS tb JOIN (SELECT * FROM webpage_offers WHERE domain = \'private\')'
                     . 'AS wo ON(wo.webpage_id = tb.webpage_id'
                     // -- versioning compare
                     . ' AND wo.major_version = tb.major_version AND wo.minor_version = tb.minor_version)'
                     . ' JOIN offers o ON(o.domain = \'private\' AND o.id = wo.offer_id)'
                     . ' WHERE NOT( o.deleted = 1 AND o.status = "approved") AND (o.end_date IS NULL OR o.end_date > UTC_TIMESTAMP()) '
                     . ' GROUP BY webpage_id)'

                     . ' UNION '

                     // inherited pages
                     . '(SELECT tb.webpage_id, tb.webpage_title, tb.path, CONCAT("<span class=\"offers_count\">", COUNT(DISTINCT wo.offer_id), " (" , COUNT(IF(o.status = "approved", 1, NULL)) , " / ", COUNT(IF(o.status = "pending", 1, NULL)) , ")</span>" ) AS offers_count'
                     . ', wo.webpage_title AS copy_from '
                     . ', CONCAT("<span class=\"offer_no\">", GROUP_CONCAT(wo.offer_id ORDER BY wo.order ASC SEPARATOR "</span>&nbsp;<span class=\"offer_no\">"), "</span>") AS offers'
                     . ' FROM (%1$s) AS tb'
                     . ' JOIN webpage_offer_inheritences it ON(it.domain = \'private\' AND it.webpage_id = tb.webpage_id)'
                     . ' JOIN (SELECT tb2.webpage_title, wo2.* FROM(%1$s) AS tb2 JOIN webpage_offers wo2 ON(wo2.domain = \'private\' AND wo2.webpage_id = tb2.webpage_id'
                     // -- versioning compare
                     . ' AND wo2.major_version = tb2.major_version AND wo2.minor_version = tb2.minor_version)'
                     . ') AS wo ON(wo.webpage_id = it.inherited_from_webpage)'
                     . ' JOIN offers o ON(o.domain = \'private\' AND o.id = wo.offer_id)'
                     . ' WHERE NOT( o.deleted = 1 AND o.status = "approved") AND (o.end_date IS NULL OR o.end_date > UTC_TIMESTAMP()) '
                     . ' GROUP BY webpage_id) '

                     . ') AS pages'
                     , $inner_select),
                 'where' => '',
                 'group_by' => '',
                 'having' => '',
                 'default_order_by' => 'offers_count',
                 'default_order_dir' => 'DESC'
            ),
            array(),
            array(
                 $edit_action => $this->kernel->dict['ACTION_edit']
            ),
            array(
                 // array for action in general (e.g. new / export)
            ),
            '/page-offers',
            'list.html',
            array('offers_count', 'offers'),
            array(
                 $this->kernel->dict['ACTION_edit'] => '/offers'
            ),
            array('path'),
            $extra_actions
        );
        $this->kernel->smarty->assignByRef( 'page_offers', $page_offers);



        $this->conn->translateField('status', $this->kernel->dict['SET_offer_statuses'], 'status');
        $where = array();
        $_GET['keywords'] = trim(array_ifnull($_GET, 'keywords', ''));
        $keywords = array();
        $replace_str = $_GET['keywords'];

        // find the keyword string which have quoted
        $quoted_signs = array('"', '\'');
        foreach($quoted_signs as $sign) {
            $regExp = sprintf('#\%1$s([^\%1$s]*?)\%1$s#i', $sign);
            while(preg_match($regExp, $replace_str, $matches, PREG_OFFSET_CAPTURE)) {
                $tmp = $matches[0][0];
                $keywords[] = trim($matches[1][0]);
                $s = $matches[0][1];
                $e = $s + strlen($tmp);
                $replace_str = substr($replace_str, 0, $s) . substr($replace_str, $e);
            }
        }

        $sep_signs = array(",", '\s');
        foreach($sep_signs as $sign) {
            $regExp = sprintf('#([^' . implode('', $sep_signs) . ']*?)%1$s#i', $sign);
            while(preg_match($regExp, $replace_str, $matches, PREG_OFFSET_CAPTURE)) {
                $s = $matches[0][1];
                $e = $s + strlen($matches[0][0]);
                $tmp = preg_replace(sprintf('#%1$s#i', $sign), "", trim($matches[0][0]));
                //echo $replace_str . $s . "<br />";
                $replace_str = trim(substr($replace_str, 0, $s) . substr($replace_str, $e));

                if($tmp !== "") {
                    $keywords[] = $tmp;
                }

            }
        }

        $keywords[] = $replace_str;

        $keywords = array_unique(array_filter(array_map('trim', $keywords), "strlen"));
        foreach($keywords as &$keyword) {
            $keyword = "%" . $keyword . "%";

            unset($keyword);
        }

        if(count($keywords) > 0) {
            $search_query = array();

            if(count($keywords) > 0) {
                //$fields = array('title', 'start_date', 'end_date');
                $fields = array('title');
                $query_str = array_map(array($this->conn, 'escape'), $keywords);
                foreach($fields as $field) {
                    $search_query[] = '(' . $field . ' LIKE ' . implode(' OR ' . $field . ' LIKE ', $query_str) . ')';
                }
            }

            if(count($search_query) > 0) {
                $where[] = '(' . implode(' OR ', $search_query) . ')';
            }
        }


        $offers_list_actions = array(
            '?op=edit&id=' => $this->kernel->dict['ACTION_edit'],
            '?op=edit&action=duplicate&id=' => $this->kernel->dict['ACTION_duplicate'],
            '?op=delete&id=' => $this->kernel->dict['ACTION_delete'],
            '?op=undelete&id=' => $this->kernel->dict['ACTION_undelete']
        );
        if($this->user->hasRights($this->module, Right::APPROVE)) {
            $offers_list_actions['?op=approve&id='] = $this->kernel->dict['ACTION_approve'];
        }

        require_once( dirname(dirname(__FILE__)) . '/webpage_admin/index.php' );
        $accessible_webpages = webpage_admin_module::getWebpageAccessibility();
        if($this->user->hasRights('webpage_admin', Right::VIEW) && in_array($this->kernel->conf['offer_webpage_id'], $accessible_webpages)) {
            $this->kernel->smarty->assign('has_extra_actions', true);
            $offers_list_actions['?op=generate_token&id='] = $this->kernel->dict['ACTION_generate_token'];
            $offers_list_actions['?op=remove_token&id='] = $this->kernel->dict['ACTION_remove_token'];
        }

        $actions_field = array();

        if($offer_page !== FALSE) {
            $p = $this->kernel->sets['paths']['app_from_doc'] . '/' . $this->kernel->request['locale'] . '/preview'
                . $offer_page->getItem()->getRelativeUrl('desktop');
            $offers_list_actions[$p] = $this->kernel->dict['ACTION_preview'];
            $actions_field[$p] = 'alias';
        }

        $active_status = $_GET['astat'] = trim(array_ifnull($_GET, 'astat', 'active'));
        if($active_status && isset($this->kernel->dict['SET_offer_active_stat'][$active_status])) {
            if($active_status == "active")
                $where[] = '(end_date IS NULL OR end_date >= UTC_TIMESTAMP())';
            if($active_status == "inactive")
                $where[] = '(end_date IS NOT NULL AND end_date < UTC_TIMESTAMP())';
        }

        $offers_list = $this->kernel->get_smarty_list_from_db(
            'offers_list',
            'id',
            array(
                 'select' => sprintf('id, title, img_url AS thumbnail, order_index, CONVERT_TZ(start_date, "+00:00", %1$s) AS start_date, CONVERT_TZ(end_date, "+00:00", %1$s) AS end_date, alias, deleted, status AS ori_status, IFNULL(t.token, "") AS token,'
                                . 'CONCAT((' . $this->conn->translateField('status', $this->kernel->dict['SET_offer_statuses'], '') . '), " ' . $this->kernel->dict['LABEL_and'] . ' ", ('
                                . $this->conn->translateField('(IF(end_date IS NULL OR end_date > UTC_TIMESTAMP(), "active", "inactive"))', $this->kernel->dict['SET_active_stat'], '') . ')) AS status'
                                , $this->kernel->conf['escaped_timezone']),
                 'from' => sprintf(
                     '(SELECT * FROM('
                      . 'SELECT o.*, l.title FROM offers o JOIN offer_locales l ON(o.domain = l.domain AND o.id = l.offer_id)'
                      . ' WHERE o.domain = \'private\' AND (o.deleted = 0 OR o.status <> "approved")'
                      . ' ORDER BY l.offer_id, l.locale = %1$s DESC'
                      . ') AS tb GROUP BY id) AS tb'
                      . ' LEFT JOIN webpage_preview_tokens t ON(t.type = "offer" AND t.initial_id = tb.id AND expire_time > UTC_TIMESTAMP())'
                      , $this->conn->escape($this->kernel->request['locale'])
                 ),
                 'where' => count($where) ? implode(' AND ', $where) : '',
                 'group_by' => '',
                 'having' => '',
                 //'default_order_by' => 'id',
                 'default_order_by' => 'order_index',
                 'default_order_dir' => 'ASC'
            ),
            array(),
            $offers_list_actions,
            array(
            ),
            '/manage-offers',
            'module/offer_admin/offers_list.html',
            array(),
            array(),
            array('alias', 'ori_status', 'deleted', 'token'),
            array(),
            $actions_field,
            array(
                 $this->kernel->dict['ACTION_delete'] => '[deleted] == 0',
                 $this->kernel->dict['ACTION_undelete'] => '[deleted] == 1',
                 $this->kernel->dict['ACTION_approve'] => '"[ori_status]" == "pending"',
                 $this->kernel->dict['ACTION_remove_token'] => '"[token]" != ""'
            )
        );
        $this->kernel->smarty->assignByRef('offers_list', $offers_list);

        $this->kernel->response['content'] = $this->kernel->smarty->fetch('module/offer_admin/index.html');
    }

    function order_index_fast_update(){
        $order_index = array_ifnull($_POST, 'order_index', array());
        $order_modified = array_ifnull($_POST, 'order_modified', array());
        $status = array_ifnull($_POST, 'status', array());
        $offer_id = array_ifnull($_POST, 'offer_id', array());
        $list_status = array_ifnull($_POST, 'list_status', '');
        $actions_perform = '';
        $approved_offers = array();
        $pending_offers = array();

        try{
            $this->conn->beginTransaction();
        
            foreach($order_modified as $k=>$modified)
            {
                if($modified == 1)
                {
                    $sql = 'UPDATE offers SET order_index ='.intval($order_index[$k]).', updater_id = '.$this->user->getId().', updated_date=UTC_TIMESTAMP() WHERE domain = \'private\' AND id='.$offer_id[$k];
                    $this->conn->exec($sql);

                    if($list_status == "approved") {
                        //$sql = sprintf('REPLACE INTO public_offers (SELECT * FROM private_offers WHERE id = %d)', $offer_id[$k]);
                        //$this->conn->exec($sql);
                        $this->publicize($offer_id[$k], array_keys($this->kernel->sets['public_locales']));
                        $actions_perform = 'approved order index fast update of promotions';
                        $approved_offers[] = $offer_id[$k];
                    } elseif($list_status == "pending") {
                        $pending_offers[] = $offer_id[$k];
                        //$this->send_pending_emails($offer_id[$k]);
                    }
                }
            }
            $this->conn->commit();

            if(count($pending_offers) > 0)
                $this->send_batch_pending_emails($pending_offers);

            if(count($approved_offers) > 0)
                $this->fast_approved_email($approved_offers);

            $action_log_msg = '';
            if($actions_perform != '' && count($offer_id)>0) {
                $action_log_msg = $actions_perform . implode(', ', $approved_offers);
                $this->kernel->log('message', sprintf('User %d %s', $this->user->getId(), $action_log_msg));
            }

            // continue to process (successfully)
            //$redirect = $this->kernel->sets['paths']['mod_from_doc'];
            $redirect = $this->kernel->sets['paths']['mod_from_doc'] . "?" .
                http_build_query(array(
                    'op' => 'dialog',
                    'type' => 'message',
                    'code' => 'DESCRIPTION_saved',
                    'redirect_url' => $this->kernel->sets['paths']['server_url']
                    . $this->kernel->sets['paths']['mod_from_doc']
                ));
            $this->kernel->redirect($redirect);
        } catch(Exception $e) {
            $this->processException($e);
        }
    }
    
    private function send_batch_pending_emails($bids = array()) {
        $success = false;
        /** @var roleNode $ExpApproveRole */
        $ExpApproveRole = $this->roleTree->findById($this->user->getRole()->getId());

        while($ExpApproveRole->getLevel() >= 0 && $ExpApproveRole = $ExpApproveRole->getParent()) {
            if($ExpApproveRole->hasRights('offer_admin', array(Right::APPROVE))) {
                break;
            }
        }

        if($ExpApproveRole->getItem()->getId() != $this->user->getRole()->getId()) {
            $id = $ExpApproveRole->getItem()->getId();

            $sql = sprintf('SELECT email, user_id, user_name FROM users u WHERE role_id = %d AND disabled = 0', $id);
            $statement = $this->conn->query($sql);
            $recipients = $statement->fetchAll();
        }

        $user_ids = array(0);

        if(count($recipients)) {
            $tmp = $recipients;
            $recipients = array();

            foreach($tmp as $u) {
                $user_ids[] = $u['user_id'];
                $recipients[$u['user_id']] = $u;
            }
            
            // has same number of requested webpage email sent to avoid sending again
            $sql = sprintf('SELECT DISTINCT target_user'
                . ' FROM approval_requests'
                . ' WHERE `type` = "fast_promotion" AND requested_by = %d AND target_id IN(%s)'
                . ' AND target_user IN(%s)'
                , $this->user->getId()
                , implode(', ', $bids)
                , implode(', ', $user_ids));
            $statement = $this->conn->query($sql);
            while($row = $statement->fetch()) {
                unset($recipients[$row['target_user']]);
            }
        }

        if(count($recipients)) {
            $sql = sprintf('SELECT * FROM offer_locales WHERE domain = \'private\' AND offer_id IN (%d) ORDER BY locale = %s DESC LIMIT 0, 1'
                , implode(',', $bids), $this->conn->escape($this->kernel->request['locale']));
            $statement = $this->conn->query($sql);

            $data = array(
                'offers' => array()
            );

            while($r = $statement->fetch())
            {
                $data['offers'][] = array(
                    'id' => $r['offer_id'],
                    'title' => $r['title']
                );
            }

            $data['recipients'] = $recipients;

            if(count($recipients)) {
                $this->kernel->smarty->assignByRef('data', $data);

                $sub_sqls = array();

                // Try to send email one by one
                $success = FALSE;
                $lines = explode( "\n", $this->kernel->smarty->fetch("module/offer_admin/locale/{$this->kernel->request['locale']}_fast_pending_email.html") );
                $this->kernel->mailer->isHTML( TRUE );
                $this->kernel->mailer->ContentType = 'text/html';
                $this->kernel->mailer->Subject = trim( array_shift($lines) );
                $this->kernel->mailer->Body = implode( "\n", $lines );

                foreach ( $recipients as $recipient )
                {
                    $data['recipient_email'] = $recipient['email'];
                    //$data['recipient_email'] = 'patrick@avalade.com';
                    $data['recipient_name'] = $recipient['user_name'];

                    $this->kernel->mailer->addAddress(
                        $data['recipient_email'],
                        $data['recipient_name']
                    );

                    $sub_sqls[] = sprintf('(%s, %d, %d, %d, UTC_TIMESTAMP())', '"fast_promotion"', $pid, $this->user->getId(), $recipient['user_id']);
                }

                if(count($sub_sqls)) {
                    $sql = sprintf('REPLACE INTO approval_requests(type, target_id, requested_by, target_user, requested_time)'
                        . ' VALUES %s', implode(', ', $sub_sqls));
                    $this->conn->query($sql);
                }
                
                try {
                    $success = $this->kernel->mailer->send();
                } catch(Exception $e) {
                    $this->kernel->log('message', sprintf("User %d experienced failure in sending mail: %s\n"
                        , $this->user->getId(), $e->getTraceAsString()), __FILE__, __LINE__);
                }
                $this->kernel->mailer->ClearAllRecipients();
            }
        }

        return $success;
    }

    function fast_approved_email($ids=array()){
        $recipient_ids = array();
        $recipients = array();

        $sql = sprintf('SELECT DISTINCT requested_by FROM approval_requests WHERE `type` = "fast_promotion" AND target_id IN (%s)', implode(',', $ids));
        $statement = $this->conn->query($sql);
        while($row = $statement->fetch()) {
            $recipient_ids[] = $row['requested_by'];
        }

        if(count($recipient_ids)) {
            $sql = sprintf('SELECT * FROM users u WHERE user_id IN(%s) AND disabled = 0', implode(', ', $recipient_ids));
            $statement = $this->conn->query($sql);
            $recipients = $statement->fetchAll();
        }

        if(count($recipients)) {
            $sql = sprintf('SELECT * FROM offer_locales WHERE domain = \'private\' WHERE offer_id IN (%d) ORDER BY locale = %s DESC LIMIT 0, 1'
                , implode(',', $ids), $this->conn->escape($this->kernel->request['locale']));
            $statement = $this->conn->query($sql);

            $data = array(
                'offers' => array()
            );

            while($r = $statement->fetch())
            {
                $data['offers'][] = array(
                    'id' => $r['offer_id'],
                    'title' => $r['title']
                );
            }

            $data['recipients'] = $recipients;

            $this->kernel->smarty->assignByRef('data', $data);

            // Try to send email one by one
            $lines = explode( "\n", $this->kernel->smarty->fetch("module/offer_admin/locale/{$this->kernel->request['locale']}_fast_approved_email.html") );
            $this->kernel->mailer->isHTML( TRUE );
            $this->kernel->mailer->ContentType = 'text/html';
            $this->kernel->mailer->Subject = trim( array_shift($lines) );
            $this->kernel->mailer->Body = implode( "\n", $lines );

            foreach ( $recipients as $recipient )
            {
                $data['recipient_email'] = $recipient['email'];
                //$data['recipient_email'] = 'patrick@avalade.com';
                $data['recipient_name'] = $recipient['user_name'];

                $this->kernel->mailer->addAddress(
                    $data['recipient_email'],
                    $data['recipient_name']
                );
            }

            try {
                $success = $this->kernel->mailer->send();
            } catch(Exception $e) {
                $this->kernel->log('message', sprintf("User %d experienced failure in sending mail: %s\n"
                    , $this->user->getId(), $e->getTraceAsString()), __FILE__, __LINE__);
            }
            $this->kernel->mailer->ClearAllRecipients();
        }

        $sql = sprintf('DELETE FROM approval_requests WHERE `type` = "fast_promotion" AND target_id IN (%s)', implode(',', $ids));
        $this->conn->exec($sql);

        // clear webpage cache
        $this->clear_cache();
    }

    function edit() {
        $id = intval(array_ifnull($_REQUEST, 'id', 0));
        $ajax = (bool)array_ifnull($_REQUEST, 'ajax', 0);
        $offer_type = trim(array_ifnull($_POST, 't', ''));
        $isSubmit = (bool)intval(array_ifnull($_POST, 'submitForm', 1));
        $action = trim(array_ifnull($_REQUEST, 'action', ''));
        $errors = array();
        $data = array();

        try {
            $sitemap = $this->get_sitemap('edit');

            // make it desktop by default
            // a temp sitemap for visible distribution
            $sm = new sitemap('desktop');

            // tmp page node as wrapper
            $tmp = new staticPage();
            $tmp->setPlatforms(array('desktop'));
            $tmp->setId(-1);
            $root = new pageNode($tmp, 'desktop');

            $sm->add($root);

            // display as root
            $node = $sitemap->getRoot()->cloneNode();
            $root->AddChild($node);

            if($id > 0) {
                $sql = sprintf('SELECT o.*'
                    . ', CONVERT_TZ(o.start_date, "+00:00", %2$s) AS start_date'
                    . ', CONVERT_TZ(o.end_date, "+00:00", %2$s) AS end_date'
                    . ', CONVERT_TZ(o.period_from, "+00:00", %2$s) AS period_from'
                    . ', CONVERT_TZ(o.period_to, "+00:00", %2$s) AS period_to'
                    . ', CONVERT_TZ(o.created_date, "+00:00", %2$s) AS created_time'
                    . ', CONVERT_TZ(o.updated_date, "+00:00", %2$s) AS updated_time'
                    . ', o.creator_id, o.updater_id'
                    . ', creator.first_name AS creator_name, creator.email AS creator_email'
                    . ', updater.first_name AS updater_name, updater.email AS updater_email'
                    . ' FROM offers o JOIN users creator ON(creator.id = o.creator_id)'
                    . ' LEFT JOIN users updater ON(updater.id = o.updater_id)'
                    . ' WHERE o.domain = \'private\' AND o.id = %1$d AND (o.deleted = 0 OR o.status <> "approved")'
                    , $id, $this->kernel->conf['escaped_timezone']);

                $statement = $this->conn->query($sql);

                if($record = $statement->fetch()) {
                    $data = $record;
                    if((!count($_POST) || !$isSubmit) && $action != 'duplicate') {
                        $webpages = $this->getOfferDistributions($id);
                        $webpage_ids = $this->selected_webpages = array_keys($webpages);
  
                        foreach($webpage_ids as $wid) {
                            $node2 = $sitemap->getRoot()->findById($wid);
                            if($node2) {
                                $sm->copyNodeStruct($node2);
                            }
  
                            unset($node2);
                        }
  
                        if(!$offer_type)
                            $offer_type = $data['type'];
                    }
  
                    $sql = sprintf('SELECT * FROM offer_locales WHERE domain = \'private\' AND offer_id = %d', $id);
                    $statement = $this->conn->query($sql);
  
                    while($row = $statement->fetch()) {
                        $data['title'][$row['locale']] = $row['title'];
                        $data['seo_title'][$row['locale']] = $row['seo_title'];
                        $data['action_text'][$row['locale']] = $row['action_text'];
                        $data['action_url'][$row['locale']] = $row['action_url'];
                        $data['url'][$row['locale']] = $row['url'];
                        $data['content'][$row['locale']] = $row['content'];
                        $data['short_description'][$row['locale']] = $row['short_description'];
                        $data['reservation_info'][$row['locale']] = $row['reservation_info'];
                        $data['keywords'][$row['locale']] = $row['keywords'];
                        $data['description'][$row['locale']] = $row['description'];
                    }
          
                    //category
                    $sql = sprintf('SELECT * FROM offer_categories WHERE domain = \'private\' AND offer_id = %d', $id);
                    $statement = $this->conn->query($sql);
                    while($row = $statement->fetch())
                    {
                        $data['categories'][] = $row['category_id'];
                    }
          
                    //dining
                    $sql = sprintf('SELECT * FROM offer_dinings WHERE domain = \'private\' AND offer_id = %d', $id);
                    $statement = $this->conn->query($sql);
                    while($row = $statement->fetch())
                    {
                        $data['dinings'][] = $row['webpage_id'];
                    }
          
                    //room
                    $sql = sprintf('SELECT * FROM offer_rooms WHERE domain = \'private\' AND offer_id = %d', $id);
                    $statement = $this->conn->query($sql);
                    while($row = $statement->fetch())
                    {
                        $data['rooms'][] = $row['webpage_id'];
                    }
          
                    //banner
                    $sql = sprintf('SELECT * FROM offer_locale_banners WHERE domain = \'private\' AND offer_id = %d ORDER BY locale, banner_id', $id);
                    $statement = $this->conn->query($sql);
                    while($row = $statement->fetch())
                    {
                        $data['banners'][$row['locale']][] = $row;
                    }
          
                    //menu
                    $sql = sprintf('SELECT * FROM offer_locale_menus WHERE domain = \'private\' AND offer_id = %d ORDER BY locale, menu_id', $id);
                    $statement = $this->conn->query($sql);
                    while($row = $statement->fetch())
                    {
                        $data['menus'][$row['locale']][] = $row;
                    }
                } else {
                    $id = 0;
                }
            } else {
                $tmp = $sitemap->getRoot();
                $tmp_children = $tmp->getChildren(0);

                for($i = 0; $i < count($tmp_children); $i++) {

                    $tmp_node = $tmp_children[$i]->cloneNode();
                    // node is the root node
                    $node->AddChild($tmp_node);
                }
            }

            if($action == 'duplicate' && !count($_POST)) {
                // reset data uncessary for duplication
                $id = $_GET['id'] = $_REQUEST['id'] = $data['id'] = 0;
                $data['alias'] = $data['start_date'] = $data['end_date'] = '';

            }

            /** @var pageNode $tmp */
            $tmp = null;
            foreach($sm->getRoot()->getChildren() as $tmp) {
                $this->nodes_status[$tmp->getItem()->getId()] = array();
            }

            // get nodes status (copy from section or full)
            $this->getNodesStatus();
            $this->kernel->smarty->assign('tree_html', $this->generateDynaTreeOffer($root));

            if(!$offer_type) {
                $offer_type = "page";
            }

            $offer_type = $offer_type == "link" ? "link" : "page";

            $cls = $offer_type . 'Offer';
            /** @var pageOffer | linkOffer $offer */
            $offer = new $cls();

            if(count($data)) {
                $offer->setData($data);
            }

            $this->kernel->smarty->assignByRef('offer', $offer);

            if(count($_POST) > 0) {
                if($this->kernel->conf['aws_enabled'])
                {
                    $_POST['img_url'] = preg_replace('#^' . preg_quote('http://'
                        . $this->kernel->conf['s3_domain'] . '/', '#') . '?#', '', $_POST['img_url']);
                }
                else
                {
                    $_POST['img_url'] = preg_replace('#^' . preg_quote($this->kernel->sets['paths']['server_url']
                        . $this->kernel->sets['paths']['app_from_doc'] . '/file/', '#') . '?#', '', $_POST['img_url']);
                }

                $webpage_ids = array_unique(array_map('intval', array_ifnull($_POST, 'webpage_id', array())));
        //$webpage_ids[] = $root_id;
                // remove inherited pages
                if(count($webpage_ids) > 0) {
                    $sql = sprintf('SELECT * FROM webpage_offer_inheritences i WHERE domain = \'private\' AND webpage_id IN(%s)'
                                    , implode(', ', $webpage_ids));
                    $statement = $this->conn->query($sql);

                    while($row = $statement->fetch()) {
                        unset($webpage_ids[array_search($row['webpage_id'], $webpage_ids)]);
                    }
                }

                $_POST['webpage_id'] = $webpage_ids;

                if($isSubmit) {
                    $this->conn->beginTransaction();
                    $_POST['deleted'] = intval(array_ifnull($_POST, 'deleted', 0));
                    $_POST['categories'] = array_ifnull($_POST, 'categories', array());
                    $_POST['dinings'] = array_ifnull($_POST, 'dinings', array());
                    $_POST['rooms'] = array_ifnull($_POST, 'rooms', array());
                    $_POST['locales_to_save'] = trim(array_ifnull($_POST, 'save_locale_str', array()));
                    $_POST['price'] = array_ifnull($_POST, 'price', '');
                    if ( $_POST['price'] == '' )
                    {
                        $_POST['price'] = NULL;
                    }
                    /*
                    else
                    {
                        $_POST['price'] = max( intval($_POST['price']), 0 );
                    }
                    */
                    $locales_to_save = explode(',', $_POST['locales_to_save']);

                    $errors = $this->errorChecking($_POST, $offer_type);

                    // set data here because it may have been changed before (in error Checking)
                    $offer->setData($_POST);

                    if($offer_type == "page") {
                        // check if the alias will collide
                        $sql = sprintf('SELECT COUNT(*) AS collide FROM offers WHERE domain = \'private\' AND alias = %s AND id <> %d'
                            , $this->conn->escape($offer->getAlias()), intval($offer->getId()));
                        $statement = $this->conn->query($sql);
                        extract($statement->fetch());
                        if($collide) {
                            $errors['alias'][] = 'alias_collide';
                        }
                    }

                    if(count($errors) > 0) {
                        throw new fieldsException($errors);
                    } else {
                        $actions_perform = array(!is_null($offer->getId()) && $offer->getId() ? 'edited' : 'created');

                        // continue process if no error
                        $id = $offer->save($this->user->getId(), $locales_to_save);

                        if($_POST['status'] == "approved") {
                            $this->publicize($id, $locales_to_save);
                            $actions_perform[] = 'approved';
                        } elseif($_POST['status'] == "pending") {
                            $this->send_pending_emails($id);
                        }

                        $action_log_msg = '';

                        if(count($actions_perform) > 1) {
                            $action_log_msg = ' and ' . array_pop($actions_perform);
                        }

                        $action_log_msg = implode(', ', $actions_perform) . $action_log_msg;

                        $this->kernel->log('message'
                                            , sprintf('User %d %s offer %d', $this->user->getId(), $action_log_msg, $id));


                        // continue to process (successfully)
                        $redirect = $this->kernel->sets['paths']['mod_from_doc'] . "?" .
                            http_build_query(array(
                                                  'op' => 'dialog',
                                                  'type' => 'message',
                                                  'code' => 'DESCRIPTION_saved',
                                                  'redirect_url' => $this->kernel->sets['paths']['server_url']
                                                  . $this->kernel->sets['paths']['mod_from_doc']
                                                  . '?id=' . $id,
                                                  'actions' => array(
                                                      $this->dialogActionEncode(
                                                          $this->kernel->sets['paths']['mod_from_doc'] . '?op=edit&id=' . $id,
                                                          $this->kernel->dict['ACTION_continue_editing'],
                                                          '_top',
                                                          'icon-edit'
                                                      )
                                                  )
                                             ));

                        if($ajax) {
                            $this->apply_template = FALSE;
                            $this->kernel->response['mimetype'] = 'application/json';
                            $this->kernel->response['content'] = json_encode( array(
                                                                                   'result' => 'success',
                                                                                   'redirect' => $redirect,
                                                                                   'target' => '_top'
                                                                              ));
                        } else {
                            $this->kernel->redirect($redirect);
                        }
                    }
                    $this->conn->commit();
                } else {
                    $offer->setData($_POST);
                }
            }
        } catch(Exception $e) {
            $this->processException($e);
        }

        if(!$ajax) {
            $queries = array();
            foreach($_GET as $k => $v) {
                switch($k) {
                    default:
                        $queries[$k] = trim($v);
                        break;
                }
            }

            // BreadCrumb
            if(!$id) {
                $action_title = sprintf($this->kernel->dict['SET_operations']['new']
                    , $this->kernel->dict['LABEL_offer']);
                $this->_breadcrumb->push(new breadcrumbNode($action_title, $this->kernel->sets['paths']['mod_from_doc'] . '/?op=edit'));
            } else {

                $info = array(
                    'created_date_message' => sprintf($this->kernel->dict['INFO_created_date'], '<b>' . $offer->getCreatedTime() . '</b>', '<b>' . $data['creator_name'] . '</b>', '<b>' . $data['creator_email'] . '</b>')
                );

                if($data['updated_date']) {
                    $info['last_update_message'] = sprintf($this->kernel->dict['INFO_last_update'], '<b>' . $offer->getUpdatedTime() . '</b>', '<b>' . $data['updater_name'] . '</b>', '<b>' . $data['updater_email'] . '</b>');
                }

                $this->kernel->smarty->assign('info', $info);

                $action_title = sprintf($this->kernel->dict['SET_operations']['edit'], $offer->getTitle()->getData());
                $this->_breadcrumb->push(new breadcrumbNode(sprintf('%1$s - [#%2$d]'
                        , $action_title, $offer->getId())
                        , $this->kernel->sets['paths']['mod_from_doc'] . '/?op=edit&id=' . $_GET['id'])
                );
            }

            $queries["op"] = "edit";
            $this->kernel->smarty->assign('query_str', http_build_query($queries));
            $this->kernel->smarty->assign('locale_set', $this->kernel->sets['public_locales']);
            $this->kernel->smarty->assign('id', $id);
            $this->kernel->smarty->assignByRef('offer', $offer);
            $this->kernel->smarty->assign('action_title', $action_title);
            $this->kernel->smarty->assign('hasShareFolderRight', $this->user->hasRights('share_file_admin', Right::VIEW));

            $page_specific_content = array();
            foreach(array_keys($this->kernel->sets['public_locales']) as $locale) {
                $this->kernel->smarty->assign('locale', $locale);
                $page_specific_content[$locale] = $this->kernel->smarty->fetch(sprintf('module/offer_admin/edit_%s.html', $offer_type));
            }
      
            //categories
            $category = array();
            $sql = 'SELECT category_id, name FROM categories WHERE categories.locale='.$this->conn->escape($this->kernel->request['locale']);
            $statement = $this->conn->query($sql);
            while($row = $statement->fetch()) {
              $category[$row['category_id']]=$row['name'];
            }
            $this->kernel->smarty->assign('category', $category);

            $this->kernel->smarty->assign('page_specific_content', $page_specific_content);
            $this->kernel->smarty->assign('offer_type', $offer_type);

            $this->kernel->response['content'] = $this->kernel->smarty->fetch('module/offer_admin/edit.html');
        }
    }

    function publicize($id, $locales) {
        $id = intval($id);

        $tbs = array('offer_locales', 'offers', 'offer_categories', 'offer_dinings', 'offer_rooms', 'offer_locale_banners', 'offer_locale_menus');

        // remove published table with same id
        foreach($tbs as $tb) {
            $sql = sprintf('DELETE FROM %s WHERE domain = \'public\' AND %s = %d', $tb, $tb == 'offers' ? 'id' : 'offer_id', $id);
            $this->conn->exec($sql);
        }

        // describe table to get fields
        $tb_fields = array();
        foreach($tbs as $tb) {
            $sql = sprintf('DESCRIBE %s', $tb);
            $statement = $this->conn->query($sql);

            while($row = $statement->fetch()) {
                $tb_fields[$tb][$row['Field']] = $row['Field'];
            }
        }

        $sql = sprintf('SELECT * FROM offers WHERE domain = \'private\' AND id = %d AND deleted = 0', $id);
        $statement = $this->conn->query($sql);

        if($record = $statement->fetch()) {
            foreach($tbs as $tb) {
                $sql = sprintf(
                    'REPLACE INTO %1$s(%2$s) SELECT %3$s FROM %1$s'
                        . ' WHERE domain = \'private\' AND %4$s = %5$d',
                    $tb,
                    implode( ', ', $tb_fields[$tb] ),
                    implode( ', ', array_merge($tb_fields[$tb], array('domain' => "'public'")) ),
                    $tb == 'offers' ? 'id' : 'offer_id',
                    $id
                );
                $this->conn->exec($sql);
            };

            // replace urls from private to public and to replace the image
            $sql = sprintf('SELECT ol.* FROM offer_locales AS ol'
                . ' JOIN offers AS o ON (ol.domain = o.domain AND ol.offer_id = o.id AND o.type = \'page\')'
                . ' WHERE ol.domain = \'public\' AND ol.offer_id = %d', $id);
            if (!is_null($locales)) {
                $sql .= count($locales) > 0 ? ' AND ol.locale IN (' . implode(', ', array_map(array($this->conn, 'escape'), $locales)) . ')' : ' AND 0 = 1';
            }
            $statement = $this->conn->query($sql);
            $regExp = '#(offer\/)(private)(\/[0-9a-z\-\_\s]+?)?(\/[0-9a-z\-\_\s]*?\.[a-z0-9]{2,4})#i';

            while($row = $statement->fetch()) {
                if(preg_match($regExp, $row['content'])) {

                    $row['content'] = preg_replace_callback($regExp, function($matches){
                        $private_path = $matches[0];
                        $public_path = $matches[1] . 'public' . $matches[3] . $matches[4];
                        if($this->kernel->conf['aws_enabled'] && s3_file_exists($private_path)) {
                            s3_copy($private_path, $public_path);
                            return $public_path;
                        }
                        else if (!$this->kernel->conf['aws_enabled'] && file_exists('file/' . $private_path)) {
                            $public_dirs = explode('/', dirname($public_path));
                            for($i = 3; $i <= count($public_dirs); $i++)
                            {
                                force_mkdir('file/' . implode('/', array_slice($public_dirs, 0, $i)));
                            }
                            smartCopy('file/' . $private_path, 'file/' . $public_path);
                            return $public_path;
                        }
                        return $private_path;
                    }, $row['content']);

                    $sql = sprintf('UPDATE offer_locales SET content = %s WHERE domain = \'public\' AND offer_id = %d AND locale = %s'
                                    , $this->conn->escape($row['content']), $row['offer_id'], $this->conn->escape($row['locale']));
                    $this->conn->exec($sql);
                }
            }

            // for the thumbnail image
            $sql = sprintf('SELECT * FROM offers WHERE domain = \'public\' AND id = %d', $id);
            $statement = $this->conn->query($sql);

            if($record = $statement->fetch()) {
                $updated_thumbnail_path = preg_replace_callback($regExp, function($matches){
                    $private_path = $matches[0];
                    $public_path = $matches[1] . 'public' . $matches[3] . $matches[4];
                    if($this->kernel->conf['aws_enabled'] && s3_file_exists($private_path)) {
                        s3_copy($private_path, $public_path);
                        return $public_path;
                    }
                    else if (!$this->kernel->conf['aws_enabled'] && file_exists('file/' . $private_path)) {
                        $public_dirs = explode('/', dirname($public_path));
                        for($i = 3; $i <= count($public_dirs); $i++)
                        {
                            force_mkdir('file/' . implode('/', array_slice($public_dirs, 0, $i)));
                        }
                        smartCopy('file/' . $private_path, 'file/' . $public_path);
                        return $public_path;
                    }
                    return $private_path;
                }, $record['img_url']);

                if($updated_thumbnail_path != $record['img_url']) {
                    $sql = sprintf('UPDATE offers SET img_url = %s WHERE domain = \'public\' AND id = %d'
                        , $this->conn->escape($updated_thumbnail_path), $id);
                    $this->conn->exec($sql);
                }
            }

            // offer locale banner
            $images = array( 'image_xs',  'image_md', 'image_xl' );
            $sql = "SELECT * FROM offer_locale_banners WHERE domain = 'public' AND offer_id = $id AND (image_xs LIKE 'offer/private/%' OR image_md LIKE 'offer/private/%' OR image_xl LIKE 'offer/private/%')";
            $statement = $this->conn->query($sql);
            while ($row = $statement->fetch()) {
                foreach ( $images as $image )
                {
                    if ( strpos($row[$image], 'offer/private/') === 0 )
                    {
                        $public_path = 'offer/public' . substr($row[$image], 13);
                        if($this->kernel->conf['aws_enabled'] && s3_file_exists($row[$image])) {
                            s3_copy($row[$image], $public_path);
                        }
                        else if (!$this->kernel->conf['aws_enabled'] && file_exists('file/' . $row[$image])) {
                            $public_dirs = explode('/', dirname($public_path));
                            for($i = 3; $i <= count($public_dirs); $i++)
                            {
                                force_mkdir('file/' . implode('/', array_slice($public_dirs, 0, $i)));
                            }
                            smartCopy('file/' . $row[$image], 'file/' . $public_path);
                        }
                        $row[$image] = $public_path;
                    }
                }
                $sql = sprintf(
                    'UPDATE offer_locale_banners SET image_xs = %s, image_md = %s, image_xl = %s WHERE domain = \'public\' AND offer_id = %d AND locale = %s AND banner_id = %d',
                    $this->conn->escape($row['image_xs']),
                    $this->conn->escape($row['image_md']),
                    $this->conn->escape($row['image_xl']),
                    $id,
                    $this->conn->escape($row['locale']),
                    $row['banner_id']
                );
                $this->conn->exec($sql);
            }

            // offer locale menu
            $sql = "SELECT * FROM offer_locale_menus WHERE domain = 'public' AND offer_id = $id AND file LIKE 'offer/private/%'";
            $statement = $this->conn->query($sql);
            while ($row = $statement->fetch()) {
                $public_path = 'offer/public' . substr($row['file'], 13);
                if($this->kernel->conf['aws_enabled'] && s3_file_exists($row['file'])) {
                    s3_copy($row['file'], $public_path);
                }
                else if (!$this->kernel->conf['aws_enabled'] && file_exists('file/' . $row['file'])) {
                    $public_dirs = explode('/', dirname($public_path));
                    for($i = 3; $i <= count($public_dirs); $i++)
                    {
                        force_mkdir('file/' . implode('/', array_slice($public_dirs, 0, $i)));
                    }
                    smartCopy('file/' . $row['file'], 'file/' . $public_path);
                }
                $sql = sprintf(
                    'UPDATE offer_locale_menus SET file = %s WHERE domain = \'public\' AND offer_id = %d AND locale = %s AND menu_id = %d',
                    $this->conn->escape($public_path),
                    $id,
                    $this->conn->escape($row['locale']),
                    $row['menu_id']
                );
                $this->conn->exec($sql);
            }
        } else {
            $sql = sprintf('DELETE FROM offers WHERE domain = \'public\' AND id = %d', $id);
            $this->conn->exec($sql);

            $sql = sprintf('DELETE FROM webpage_offers WHERE domain = \'public\' AND offer_id = %d', $id);
            $this->conn->exec($sql);
        }

        $recipient_ids = array();
        $recipients = array();

        $sql = sprintf('SELECT DISTINCT requested_by FROM approval_requests WHERE `type` = "offer" AND target_id = %d', $id);
        $statement = $this->conn->query($sql);
        while ($row = $statement->fetch()) {
            $recipient_ids[] = $row['requested_by'];
        }

        if(count($recipient_ids)) {
            $sql = sprintf('SELECT * FROM users u WHERE id IN(%s) AND enabled = 1', implode(', ', $recipient_ids));
            $statement = $this->conn->query($sql);
            $recipients = $statement->fetchAll();
        }

        if(count($recipients)) {
            $sql = sprintf('SELECT * FROM offer_locales WHERE domain = \'private\' AND offer_id = %d ORDER BY locale = %s DESC LIMIT 0, 1'
                , $id, $this->conn->escape($this->kernel->request['locale']));
            $statement = $this->conn->query($sql);

            $data = array(
                'offers' => array()
            );

            if($record = $statement->fetch()) {
                $data['offers'][] = array(
                    'id' => $record['offer_id'],
                    'title' => $record['title']
                );
            }

            $data['recipients'] = $recipients;

            $this->kernel->smarty->assignByRef('data', $data);

            // Try to send email one by one
            $lines = explode( "\n", $this->kernel->smarty->fetch("module/offer_admin/locale/{$this->kernel->request['locale']}_approved_email.html") );
            $this->kernel->mailer->isHTML( TRUE );
            $this->kernel->mailer->ContentType = 'text/html';
            $this->kernel->mailer->Subject = trim( array_shift($lines) );
            $this->kernel->mailer->Body = implode( "\n", $lines );

            foreach ( $recipients as $recipient )
            {
                $data['recipient_email'] = $recipient['email'];
                $data['recipient_name'] = $recipient['first_name'];

                $this->kernel->mailer->addAddress(
                    $data['recipient_email'],
                    $data['recipient_name']
                );
            }

            try {
                $success = $this->kernel->mailer->send();
            } catch(Exception $e) {
                $this->kernel->log('message', sprintf("User %d experienced failure in sending mail: %s\n"
                    , $this->user->getId(), $e->getTraceAsString()), __FILE__, __LINE__);
            }
            $this->kernel->mailer->ClearAllRecipients();
        }

        $sql = sprintf('DELETE FROM approval_requests WHERE `type` = "offer" AND target_id = %d', $id);
        $this->conn->exec($sql);

        // clear webpage cache
        $this->clear_cache();
    }

    function errorChecking(&$data, $offer_type, $id = 0) {
        $locales = array_keys($this->kernel->sets['public_locales']);
        $errors = array();

        // check following fields to ensure they are not empty
        // locale
        $ary = array(
            /*'title' => array(
                'type' => 'locale',
                'pointer' => 'title',
                'method' => 'trim',
                'max_length' => 60,
                'draft_required' => true
            ),
            'action_text' => array(
                'type' => 'locale',
                'pointer' => 'action_text',
                'method' => 'trim',
                'max_length' => 55,
                'draft_required' => false
            ),*/
            'img_url' => array(
                'type' => 'single_field',
                'pointer' => 'img_url',
                'method' => 'trim',
                'draft_required' => false
            )
        );
        switch($offer_type) {
            case "page":
                $ary = array_merge($ary, array(
                                              /*'content' => array(
                                                  'type' => 'locale',
                                                  'pointer' => 'content',
                                                  'method' => 'trim',
                                                  'draft_required' => false
                                              ),
                                              'keywords' => array(
                                                  'type' => 'locale',
                                                  'pointer' => 'keywords',
                                                  'method' => 'trim',
                                                  'draft_required' => false
                                              ),
                                              'description' => array(
                                                  'type' => 'locale',
                                                  'pointer' => 'description',
                                                  'method' => 'trim',
                                                  'draft_required' => false
                                              ),*/
                                              'alias' => array(
                                                  'type' => 'single_field',
                                                  'pointer' => 'alias',
                                                  'method' => 'trim',
                                                  'draft_required' => false
                                              )
                                         )
                );
                break;
            case "link":
                $ary = array_merge($ary, array(
                                              'target' => array(
                                                  'type' => 'single_field',
                                                  'pointer' => 'target',
                                                  'method' => 'trim',
                                                  'draft_required' => false
                                              )/*,
                                              'url' => array(
                                                  'type' => 'locale',
                                                  'pointer' => 'url',
                                                  'method' => 'trim',
                                                  'draft_required' => false
                                              )*/
                                   )
                );
                break;
            default:
                $errors["errorsStack"][] = 'offer_type_invalid';
                break;
        }

        $locale_require_fields = array();
        foreach($ary as $name => $itm) {
            if($itm['type'] == "locale") {
                $data[$name] = array_map($itm['method'], array_ifnull($data, $name, array()));

                foreach($locales as $locale) {
                    if(($data['status'] != 'draft' || $itm['draft_required'] ) && (!isset($data[$name][$locale]) || $data[$name][$locale] === "") ) {
                        //$errors[$name . "[{$locale}]"][] = $name . '_empty';
                        $locale_require_fields[$locale][$name . "[{$locale}]"][] = $name . '_empty';
                    }

                    if(isset($itm['max_length']) && mb_strlen($data[$name][$locale]) > $itm['max_length']) {
                        $err_lbl = $name . '_length_exceed';

                        $this->kernel->dict['ERROR_' . $err_lbl] = sprintf($this->kernel->dict['ERROR_length_exceed'], $itm['max_length']);
                        //$errors[$name . "[{$locale}]"][] = $err_lbl;
                        $locale_require_fields[$locale][$name . "[{$locale}]"][] = $err_lbl;
                    }
                }
            } elseif($itm['type'] == "single_field") {
                $data[$name] = $itm['method'](array_ifnull($data, $name, ''));
                if(($data['status'] != 'draft' || $itm['draft_required'] ) && (!isset($data[$name]) || $data[$name] === "") ) {
                    $errors[$name][] = $name . '_empty';
                }

                if(isset($itm['max_length']) && mb_strlen($data[$name]) > $itm['max_length']) {
                    $err_lbl = $name . '_length_exceed';

                    $this->kernel->dict['ERROR_' . $err_lbl] = sprintf($this->kernel->dict['ERROR_length_exceed'], $itm['max_length']);
                    $errors[$name][] = $err_lbl;
                }
            }
        }

        $no_input_locale_count = 0;
        $tmp_wrapper = array();
        foreach($locale_require_fields as $n => $locale_fields) {
            if(($data['status'] != 'draft' && count($locale_fields) != 3) || ($data['status'] == 'draft' && count($locale_fields) != 1)) { // all fields for that locale have not entered at all
                $errors = array_merge($errors, $locale_fields);
            } else {
                $no_input_locale_count++;
                $tmp_wrapper = array_merge($tmp_wrapper, $locale_fields);
            }
        }

        if($no_input_locale_count == count($this->kernel->sets['public_locales'])) {
            $errors = array_merge($errors, $tmp_wrapper);
        }

        if($offer_type == "link") {
            foreach($data['url'] as $locale => $value) {
                if(!isset($errors["url[{$locale}]"]) && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $errors["url[{$locale}]"][] = 'url_invalid';
                }
            }
        }

        if($data['end_date'] && !strtotime($data['end_date'])) {
            $errors["end_date"][] = 'date_invalid';
        } elseif($data['end_date']) {
            $data['end_date'] = date('Y-m-d H:i:s', strtotime($data['end_date']) - 8 * 3600);
        }

        if($data['start_date'] && !strtotime($data['start_date'])) {
            $errors["start_date"][] = 'date_invalid';
        } elseif($data['start_date']) {
            $data['start_date'] = date('Y-m-d H:i:s', strtotime($data['start_date']) - 8 * 3600);
        }
    
    if($data['period_from'] && !strtotime($data['period_from'])) {
            //$errors["period_from"][] = 'date_invalid';
        } elseif($data['period_from']) {
            $data['period_from'] = date('Y-m-d H:i:s', strtotime($data['period_from']) - 8 * 3600);
        }

        if($data['period_to'] && !strtotime($data['period_to'])) {
            $errors["period_to"][] = 'date_invalid';
        } elseif($data['period_to']) {
            $data['period_to'] = date('Y-m-d H:i:s', strtotime($data['period_to']) - 8 * 3600);
        }

        return $errors;
    }

    public static function getOfferDistributions($id = 0) {
        /** @var offer_admin_module $module */
        $module = kernel::$module;
        $conn = $module->conn;

        $distributions = array();

        // get latest_webpage
        //$latest_pages = sprintf('SELECT * FROM(SELECT id, `type`, major_version, minor_version, deleted, status'
        //                    . ' FROM webpages WHERE domain = \'private\' ORDER BY id, major_version DESC, minor_version DESC)'
        //                    . ' AS w GROUP BY id');
        $latest_pages = sprintf('SELECT w.id, w.`type`, w.major_version, w.minor_version, w.deleted'
                            . ' FROM webpages w JOIN webpage_versions wv ON (wv.id=w.id AND w.domain=wv.domain AND w.major_version=wv.major_version AND w.minor_version=wv.minor_version) WHERE w.domain = \'private\'');

        //the webpage which directly get the offers
        $sql = sprintf('SELECT webpage_id FROM webpage_offers o WHERE domain = \'private\' AND offer_id = %d AND EXISTS(SELECT * FROM(%s) AS pages'
                        . ' WHERE pages.`type` IN("static", "structured_page") AND pages.deleted = 0'
                        . ' AND o.webpage_id = pages.id AND o.major_version = pages.major_version AND o.minor_version = pages.minor_version'
                        . ')', $id
                        , $latest_pages);

        $sqls = array();
        $sqls[] = sprintf('(SELECT "direct" AS `type`, webpage_id FROM(%s) AS tb)', $sql);
        $sqls[] = sprintf('(SELECT "inherit" AS `type`, webpage_id FROM webpage_offer_inheritences i WHERE'
                            . ' domain = \'private\' AND i.inherited_from_webpage IN(%s))', $sql);

        $sql = implode(' UNION ALL ', $sqls);

        $statement = $conn->query($sql);
        while($row = $statement->fetch()) {
            $distributions[$row['webpage_id']] = $row['type'];
        }

        return $distributions;

    }

    function generateDynaTreeOffer(pageNode $node, $output = "html", $lazy = true) {
        $children = $node->getChildren(0);

        if($output == "html") {
            $html = "<ul>";
        } else {
            $ary = array();
        }

        /** @var pageNode $child */
        $child = null;
        foreach($children as $child) {
            $innerhtml = "";
            $classes = array();
            $data = array(
                'key' => $child->getItem()->getId()
            );
            $extras = array();

            if($child->hasChild()) {
                //if($output == "ajax")
                //echo print_r($child);
                if(!count($child->getChildren(0))) {
                    if($lazy) {
                        $classes[] = 'lazy';
                        $data['isLazy'] = $output == "html" ? 'true' : true;
                    }
                } else {
                    $innerhtml = $this->generateDynaTreeOffer($child, $output, $lazy);
                    $classes[] = 'expanded';
                    $data['expand'] = $output == "html" ? 'true' : true;
                }
            }

            if(isset($this->nodes_status[$child->getItem()->getId()])) {
                foreach($this->nodes_status[$child->getItem()->getId()] as $status) {
                    switch($status) {
                        case 'inherited':
                            $data['unselectable'] = $output == "html" ? 'true' : true;
                            $extras[] = sprintf('<span class="webpage-status inherited">[%s]</span>', $this->kernel->dict['LABEL_inherited']);
                            break;
                        case 'full':
                            if(!in_array($child->getItem()->getId(), $this->selected_webpages))
                                $data['unselectable'] = $output == "html" ? 'true' : true;
                            $extras[] = sprintf('<span class="webpage-status full">[%s]</span>', $this->kernel->dict['LABEL_full']);
                            break;
                    }
                }
            }

            if(isset($data['unselectable']) && @$data['unselectable']) {
                $classes[] = 'disabled';
            }

            if(in_array($child->getItem()->getId(), $this->selected_webpages)) {
                $data['selected'] = $output == "html" ? 'true' : true;
            }

            $data['extraClasses'] = implode(' ', $classes);

            $item = $child->getItem();
            $data['href'] = $this->kernel->sets['paths']['app_from_doc'] . '/' . $this->kernel->request['locale'] . '/preview'
                . $item->getRelativeUrl();

            $title = sprintf('<span>%1$s%2$s</span>'
                , ($item->getTitle() ? $item->getTitle() : '(' . $this->kernel->dict['LABEL_no_title'] . ')') . ' (#' . $item->getId() . ')'
                , count($extras) > 0 ? " " . implode("\n ", $extras) : "");

            if($output == "html") {
                $html .= sprintf('<li class="%4$s" data-json="%5$s"><a href="%3$s">%1$s</a>%2$s</li>'
                    , $title, $innerhtml
                    , htmlspecialchars($data['href'])
                    , htmlspecialchars($data['extraClasses'])
                    , htmlspecialchars(json_encode($data)));
            } else {
                $data['children'] = $innerhtml;
                $tmp = array_merge(array(
                    'title' => $title,
                ), $data);

                $ary[] = $tmp;
            }
        }

        if($output == "html") {
            $html .= "</ul>";

            return $html;
        } else {
            return $ary;
        }
    }

    function getNodes() {
        $ajax = (bool)intval(array_ifnull($_REQUEST, 'ajax', 0));
        $parent = intval(array_ifnull($_REQUEST, 'parent', 0));
        $id = intval(array_ifnull($_REQUEST, 'id', 0));
        $platform = "desktop";

        try {

            if($parent > 0) {
                $sitemap = $this->get_sitemap('edit');

                // make it desktop by default
                // a temp sitemap for visible distribution
                $sm = new sitemap($platform);

                // display as root
                $node = $sitemap->getRoot()->findById($parent);
                $tmp = $node->cloneNode();
                $sm->add($tmp);
                $children = $node->getChildren(0);

                foreach($children as $child) {
                    $sm->copyNodeStruct($child);
                    $this->nodes_status[$child->getItem()->getId()] = array();
                }

                // get nodes status (copy from section or full)
                $this->getNodesStatus();

                $this->apply_template = false;
                $this->kernel->response['mimetype'] = 'application/json';
                $this->kernel->response['content'] = json_encode($this->generateDynaTreeOffer($sm->getRoot(), 'ajax'));
            }

        } catch (Exception $e) {
            $this->processException($e);
        }
    }

    function getNodesStatus() {
        $ids = array_keys($this->nodes_status);

        if(count($ids) > 0) {

            // get latest_webpage
            $latest_pages = sprintf('SELECT * FROM(SELECT id, `type`, major_version, minor_version, deleted'
            . ' FROM webpages WHERE domain = \'private\' ORDER BY id, major_version DESC, minor_version DESC)'
            . ' AS w GROUP BY id');

            $sql = sprintf('SELECT * FROM webpage_offer_inheritences i WHERE domain = \'private\' AND webpage_id IN(%s)', implode(', ', $ids));
            $statement = $this->conn->query($sql);

            while($row = $statement->fetch()) {
                $this->nodes_status[$row['webpage_id']][] = 'inherited';
            }

            // get nodes which are full
            // direct and inherited
            $sqls = array(
                sprintf('(SELECT p.webpage_id, COUNT(DISTINCT o.id) AS offer_count FROM webpage_offers p'
                    . ' JOIN (%s) AS pages ON(pages.type IN("static", "structured_page") AND pages.deleted = 0'
                    . ' AND p.webpage_id = pages.id AND p.major_version = pages.major_version'
                    . ' AND p.minor_version = pages.minor_version)'
                    . ' JOIN offers o ON(p.domain = o.domain AND p.offer_id = o.id)'
                    . ' WHERE p.domain = \'private\' AND (o.deleted = 0 OR o.status <> "approved") AND (UTC_TIMESTAMP() < o.end_date OR o.end_date IS NULL)'
                    . ' AND p.webpage_id IN(%s) GROUP BY p.webpage_id HAVING offer_count >= %d)'
                    , $latest_pages, implode(', ', $ids), offer_admin_module::MAX_OFFER
                ),
                sprintf('(SELECT i.webpage_id, COUNT(DISTINCT o.id) AS offer_count FROM webpage_offer_inheritences i JOIN webpage_offers p'
                    . ' ON(p.domain = i.domain AND p.webpage_id = i.inherited_from_webpage)'
                    . ' JOIN (%s) AS pages ON(pages.type IN("static", "structured_page") AND pages.deleted = 0'
                    . ' AND p.webpage_id = pages.id AND p.major_version = pages.major_version'
                    . ' AND p.minor_version = pages.minor_version)'
                    . ' JOIN offers o ON(p.domain = o.domain AND p.offer_id = o.id)'
                    . ' WHERE i.domain = \'private\' AND (o.deleted = 0 OR o.status <> "approved") AND (UTC_TIMESTAMP() < o.end_date OR o.end_date IS NULL)'
                    . ' AND i.webpage_id IN(%s) GROUP BY i.webpage_id HAVING offer_count >= %d)'
                    , $latest_pages, implode(', ', $ids), offer_admin_module::MAX_OFFER
                )
            );

            $sql = sprintf('%s', implode(' UNION ALL ', $sqls));

            $statement = $this->conn->query($sql);
            while($row = $statement->fetch()) {
                $this->nodes_status[$row['webpage_id']][] = 'full';
            }
        }

    }

    function retrieveOffer() {
        try {
            $id = intval(array_ifnull($_GET, 'id', 0));
            $json = array();

            $sql = sprintf('SELECT * FROM (SELECT o.id, o.type, o.img_url, o.status, o.deleted'
                            . ', CONVERT_TZ(o.start_date, "+00:00", %3$s) AS start_date, CONVERT_TZ(o.end_date, "+00:00", %3$s) AS end_date'
                            . ', CONVERT_TZ(o.created_date, "+00:00", %3$s) AS created_date, o.creator_id, CONVERT_TZ(o.updated_date, "+00:00", %3$s) AS updated_date'
                            . ', o.updater_id, l.title, l.seo_title, l.action_text'
                            . ' FROM offers o JOIN offer_locales l ON(o.domain = l.domain AND o.id = l.offer_id)'
                            . ' WHERE o.domain = \'private\' AND o.id = %1$d AND (o.deleted = 0 OR o.status <> "approved") ORDER BY l.locale = %2$s DESC) AS tb GROUP BY id '
                            , $id, $this->conn->escape($this->kernel->request['locale'])
                            , $this->kernel->conf['escaped_timezone']);
            $statement = $this->conn->query($sql);

            if($data = $statement->fetch()) {
                if(strpos($data['img_url'], '//') === FALSE)
                    $data['img_url'] = $this->kernel->sets['paths']['server_url'] . $this->kernel->sets['paths']['app_from_doc'] . '/' . $data['img_url'];
                $this->kernel->smarty->assign('data', $data);
                $html = $this->kernel->smarty->fetch('module/offer_admin/offer_preview.html');
                //$html = htmlspecialchars($html);
                $json['html'] = $html;
                $json['title'] = $data['title'] . ': #' . $data['id'];
                $json['id'] = $data['id'];
            }

            $this->apply_template = FALSE;
            $this->kernel->response['mimetype'] = 'application/json';
            $this->kernel->response['content'] = json_encode($json);
        } catch(Exception $e) {
            $this->processException($e);
        }
    }

    public static function getWebpageOffers($id, $mode = "private", $include_not_started = false) {
        if($mode != "private")
            $mode = "public";

        /** @var db $conn */
        $conn = kernel::$module->conn;
        $kernel = kernel::getInstance();

        $offers = array();

        // select only static page
        /*
        $inner_select = sprintf('SELECT * FROM(SELECT w2.id AS webpage_id, l.webpage_title, w2.major_version, w2.minor_version'
            . ' FROM (SELECT * FROM (SELECT * FROM webpages w'
            . ' WHERE domain = %1$s'
            . ' ORDER BY id ASC, major_version DESC, minor_version DESC'
            . ') AS tb GROUP BY id HAVING type IN("static", "structured_page") AND NOT(deleted = 1 AND status = "approved")) AS w2'
            . ' JOIN webpage_locales l ON(l.domain = %1$s AND l.webpage_id = w2.id'
            . ' AND w2.major_version = l.major_version AND w2.minor_version = l.minor_version)) AS tb'
            . ' GROUP BY webpage_id', $conn->escape($mode));
        */
        $inner_select = sprintf( 'SELECT wl.webpage_id,'
            . " SUBSTRING_INDEX(GROUP_CONCAT(w.offer_source ORDER BY w.major_version DESC), ',', 1) AS offer_source,"
            . " SUBSTRING_INDEX(GROUP_CONCAT(wl.webpage_title ORDER BY w.major_version DESC), ',', 1) AS webpage_title,"
            . " SUBSTRING_INDEX(GROUP_CONCAT(wl.major_version ORDER BY w.major_version DESC), ',', 1) AS major_version,"
            . " SUBSTRING_INDEX(GROUP_CONCAT(wl.minor_version ORDER BY w.major_version DESC), ',', 1) AS minor_version"
            . ' FROM webpages AS w'
            . ' JOIN webpage_locales AS wl ON (w.domain = wl.domain AND w.id = wl.webpage_id AND w.major_version = wl.major_version AND w.minor_version = wl.minor_version)'
            . ' WHERE w.domain = ' . $conn->escape($mode) . " AND w.type IN('static', 'structured_page') AND NOT(w.deleted = 1 AND wl.status = 'approved')"
            . ' GROUP BY w.id', $conn->escape($mode));

        $sql = sprintf('SELECT * FROM('
            // direct pages
            . '(SELECT * FROM(SELECT o.*, l.title, wo.order'
            . ' FROM (%1$s) AS tb JOIN webpage_offers wo ON(wo.domain = %4$s AND wo.webpage_id = tb.webpage_id'
            // -- versioning compare
            . ' AND wo.major_version = tb.major_version AND wo.minor_version = tb.minor_version'
            . ') JOIN offers o ON(o.domain = wo.domain AND o.id = wo.offer_id)'
            . ' JOIN offer_locales l ON(l.domain = l.domain AND l.offer_id = o.id)'
            . " WHERE tb.offer_source = 'specific' AND NOT(o.deleted = 1 AND o.status = 'approved')"
            . ($mode == "private" || $include_not_started ? '' : ' AND (o.start_date IS NULL OR o.start_date <= UTC_TIMESTAMP())')
            . ' AND (o.end_date IS NULL OR o.end_date > UTC_TIMESTAMP()) AND wo.webpage_id = %2$d'
            . ($mode == 'private' ? '' : ' AND l.locale = %3$s') // limited to offers with same locale
            . ' ORDER BY l.locale = %3$s DESC '
            . ') AS direct_pages GROUP BY id)'

            . ' UNION ALL '

            // inherited pages
            . '(SELECT * FROM(SELECT o.*, l.title, wo.order'
            . ' FROM (%1$s) AS tb'
            . ' JOIN webpage_offer_inheritences it ON(it.domain = %4$s AND it.webpage_id = tb.webpage_id)'
            . ' JOIN (SELECT tb2.webpage_title, wo2.* FROM(%1$s) AS tb2 JOIN webpage_offers wo2 ON(wo2.domain = %4$s AND wo2.webpage_id = tb2.webpage_id'
            // -- versioning compare
            . ' AND wo2.major_version = tb2.major_version AND wo2.minor_version = tb2.minor_version'
            . ')) AS wo ON(wo.webpage_id = it.inherited_from_webpage)'
            . ' JOIN offers o ON(o.domain = wo.domain AND o.id = wo.offer_id)'
            . ' JOIN offer_locales l ON(l.domain = o.domain AND l.offer_id = o.id)'
            . " WHERE tb.offer_source = 'inherited' AND NOT(o.deleted = 1 AND o.status = 'approved')"
            . ($mode == "private" || $include_not_started ? '' : ' AND (o.start_date IS NULL OR o.start_date <= UTC_TIMESTAMP())')
            . ' AND (o.end_date IS NULL OR o.end_date > UTC_TIMESTAMP()) AND it.webpage_id = %2$d'
            . ($mode == 'private' ? '' : ' AND l.locale = %3$s') // limited to offers with same locale
            . ' ORDER BY l.locale = %3$s DESC '
            . ' ) AS inherited_pages GROUP BY id) '
            . ') AS pages'

            . ' ORDER BY pages.`order` ASC'
            , $inner_select, $id, $conn->escape($kernel->request['locale']), $conn->escape($mode));

        $statement = $conn->query($sql);

        while($row = $statement->fetch()) {
            $offers[] = array(
                'id' => $row['id'],
                'title' => htmlspecialchars($row['title']),
                'status' => $row['status']
            );
        }

        return $offers;
    }

    public static function getOfferDetails($ids, $locale = "", $platform = "desktop", $mode = "public") {
        /** @var base_module $module */
        $module = kernel::$module;
        /** @var db $conn */
        $conn = $module->conn;
        /** @var kernel $kernel */
        $kernel = kernel::getInstance();

        if(!is_array($ids)) {
            $ids = array($ids);
        }

        $ids = array_unique(array_map('intval', $ids));

        if($locale=='')
            $locale = key($kernel->sets['public_locales']);

        if($mode != "private")
            $mode = "public";

        // get the webpage url for the page offer
        $offer_page_id = intval($kernel->conf['offer_webpage_id']);
        /** @var sitemap $sm */
        $sm = $module->get_sitemap($mode == "public" ? "view" : "edit", $platform);

        /** @var pageNode $node */
        $node = $sm->getRoot()->findById($offer_page_id);

        if($node) {
            $prefix_url = $kernel->sets['paths']['server_url'] . $kernel->sets['paths']['app_from_doc']
                . '/' . $locale . $node->getItem()->getRelativeUrl($platform);
        }

        /*
        $sql = sprintf('SELECT * FROM(SELECT o.id, o.type, o.img_url, ol.title, o.period_from, o.period_to, o.price, ol.short_description, o.video_url'
            . ' , ol.action_text, ol.action_url, o.action_url_target'
            . ' , IFNULL(o.target, \'_self\') AS target, IFNULL(o.alias, ol.url) AS url, ol.content'
            . ' , IF(o.start_date IS NULL OR o.start_date <= UTC_TIMESTAMP(), 1, 0) AS started'
            . ' , IF(o.end_date IS NULL OR o.end_date >= UTC_TIMESTAMP(), 0, 1) AS ended'
            . ' FROM offers o JOIN offer_locales ol ON(o.id = ol.offer_id)'
            . ' WHERE o.domain = %1$s AND o.type = "page" AND o.id IN(%3$s)'
            . ' ORDER BY ol.locale = %2$s DESC) AS tb GROUP BY id'
            , $conn->escape($mode), $conn->escape($locale)
            , implode(', ', $ids));
        */
        $sql = 'SELECT o.id, o.type, o.img_url, o.period_from, o.period_to, o.price, o.video_url, o.action_url_target, IFNULL(o.target, \'_self\') AS target,';
        $sql .= " IFNULL(o.alias, SUBSTRING_INDEX(GROUP_CONCAT(IFNULL(ol.url, '') ORDER BY ol.locale = %2\$s DESC SEPARATOR '\r\n'), '\r\n', 1)) AS url,";
        $sql .= ' IF(o.start_date IS NULL OR o.start_date <= UTC_TIMESTAMP(), 1, 0) AS started,';
        $sql .= ' IF(o.end_date IS NULL OR o.end_date >= UTC_TIMESTAMP(), 0, 1) AS ended,';
        $sql .= " SUBSTRING_INDEX(GROUP_CONCAT(IFNULL(ol.title, '') ORDER BY ol.locale = %2\$s DESC SEPARATOR '\r\n'), '\r\n', 1) AS title,";
        $sql .= " SUBSTRING_INDEX(GROUP_CONCAT(IFNULL(ol.seo_title, '') ORDER BY ol.locale = %2\$s DESC SEPARATOR '\r\n'), '\r\n', 1) AS seo_title,";
        $sql .= " SUBSTRING_INDEX(GROUP_CONCAT(IFNULL(ol.short_description, '') ORDER BY ol.locale = %2\$s DESC SEPARATOR '\r\n---\r\n'), '\r\n---\r\n', 1) AS short_description,";
        $sql .= " SUBSTRING_INDEX(GROUP_CONCAT(IFNULL(ol.action_text, '') ORDER BY ol.locale = %2\$s DESC SEPARATOR '\r\n'), '\r\n', 1) AS action_text,";
        $sql .= " SUBSTRING_INDEX(GROUP_CONCAT(IFNULL(ol.action_url, '') ORDER BY ol.locale = %2\$s DESC SEPARATOR '\r\n'), '\r\n', 1) AS action_url,";
        $sql .= " SUBSTRING_INDEX(GROUP_CONCAT(IFNULL(ol.content, '') ORDER BY ol.locale = %2\$s DESC SEPARATOR '\r\n---\r\n'), '\r\n---\r\n', 1) AS content";
        $sql .= ' FROM offers o JOIN offer_locales ol ON(o.id = ol.offer_id)';
        $sql .= ' WHERE o.domain = %1$s AND o.type = "page" AND o.id IN(%3$s)';
        $sql .= ' GROUP BY o.id';
        $sql = sprintf( $sql, $conn->escape($mode), $conn->escape($locale), implode(', ', $ids) );

        $offers = array();
        $statement = $conn->query($sql);
        while($row = $statement->fetch()) {
            if($node && $row['type'] == "page") {
                $row['url'] = $prefix_url . $row['url'] . '/';
            }
            if(strpos($row['img_url'], '//') === FALSE)
            {
                if($kernel->conf['aws_enabled'])
                {
                    $row['img_url'] = $kernel->conf['cloudfront_domain'] . '/' . $row['img_url'];
                }
                else
                { 
                    $row['img_url'] = $kernel->sets['paths']['server_url'] . $kernel->sets['paths']['app_from_doc'] . '/file/' . $row['img_url'];
                }
            }

            $offers[$row['id']] = $row;
        }

        return $offers;
    }

    function getWebpageOffersInJson() {
        try {
            $id = intval(array_ifnull($_GET, 'id', 0));

            $offers = $this->getWebpageOffers($id);

            $json = array(
                'id' => $id,
                'title' => $this->kernel->dict['LABEL_page_offers'],
                'offers' => $offers
            );

            $this->apply_template = FALSE;
            $this->kernel->response['mimetype'] = 'application/json';
            $this->kernel->response['content'] = json_encode($json);
        } catch(Exception $e) {
            $this->processException($e);
        }
    }

    function getOfferPageTree() {
        try {
            $id = intval(array_ifnull($_GET, 'id', 0));
            $platform = 'desktop';

            $sitemap = $this->get_sitemap('edit');

            // make it desktop by default
            // a temp sitemap for visible distribution
            $sm = new sitemap($platform);

            // tmp page node as wrapper
            $tmp = new staticPage();
            $tmp->setPlatforms(array($platform));
            $tmp->setId(-1);
            $root = new pageNode($tmp, $platform);

            $sm->add($root);

            $webpages = $this->getOfferDistributions($id);
            $webpage_ids = $this->selected_webpages = array_keys($webpages);

            foreach($webpage_ids as $wid) {
                $node2 = $sitemap->getRoot()->findById($wid);
                if($node2) {
                    $sm->copyNodeStruct($node2, false);
                }
                unset($node2);
            }

            // get webpage for offers
            $offer_page = null;
            if($sitemap->getRoot() && $this->kernel->conf['offer_webpage_id']) {
                $offer_page = $sitemap->getRoot()->findById($this->kernel->conf['offer_webpage_id']);
            }

            /** @var pageNode $tmp */
            $tmp = null;
            foreach($sm->getRoot()->getChildren() as $tmp) {
                $this->nodes_status[$tmp->getItem()->getId()] = array();
            }

            // get nodes status (copy from section or full)
            $this->getNodesStatus();

            $sm->getRoot()->reOrder();

            $code = "";
            $sql = sprintf('SELECT token, `type`, initial_id, CONVERT_TZ(created_date, "+00:00", %1$s) AS created_date, CONVERT_TZ(expire_time, "+00:00", %1$s) AS expire_time'
                                . ' FROM webpage_preview_tokens WHERE `type` = "offer" AND initial_id = %2$d AND expire_time > UTC_TIMESTAMP()'
                                , $this->kernel->conf['escaped_timezone'], $id);
            $statement = $this->conn->query($sql);
            if($record = $statement->fetch()) {
                $code = $this->encodePvToken($record['token'], 'offer');
            }

            $this->apply_template = false;
            $this->kernel->response['mimetype'] = 'application/json';
            $this->kernel->response['content'] = json_encode(
                array(
                    'id' => $id,
                    'tree_struct' => $root->hasChild() ? $this->generateDynaTreeOffer($root, 'html', false) : '<div>' . $this->kernel->dict['LABEL_no_records'] . '</div>',
                    //'preview_path' => $code ? $this->kernel->sets['paths']['app_from_doc'] . '/' . $this->kernel->request['locale'] . '/preview/' . '?pvtk=' . $code : "",
                    'preview_path' => $code ? $this->kernel->sets['paths']['app_from_doc'] . '/' . $this->user->getPreferredLocale() . '/preview/' . '?pvtk=' . $code : "",
                    'code_expire_time' => $code ? $record['expire_time'] : ''
                )
            );
        } catch(Exception $e) {
            $this->processException($e);
        }
    }

    private function setStatus($action) {
        $status = $action == "approve" ? "approved" : "pending";

        // Get the requested offer
        $id = intval( array_ifnull($_GET, 'id', 0) );
        $data = array();
        if ( $id )
        {
            $sql = sprintf('SELECT * FROM offers'
                . ' WHERE domain = \'private\' AND id = %d AND status = "pending"'
                , $id);
            $statement = $this->conn->query( $sql );
            if ( $record = $statement->fetch() )
            {
                $data = $record;
            }
        }

        // Need to delete/undelete
        if ( count($data) > 0 )
        {
            if ( !$this->user->hasRights($this->module, Right::APPROVE)
                && $data['status'] == 'approved' )
            {
                $data['status'] = 'pending';
            }

            $sql = sprintf('UPDATE offers SET '
                . ' status = %s WHERE domain = \'private\' AND id = %d'
                , $this->conn->escape($status), $id);
            $this->conn->exec( $sql );
            if ( $status == 'approved' )
            {
                $this->publicize( $id, array_keys($this->kernel->sets['public_locales']) );
            }

            $this->kernel->redirect(
                '?' . http_build_query( array(
                                             'op' => 'dialog',
                                             'type' => 'message',
                                             'code' => 'DESCRIPTION_' . ( $status == "approved" ? 'approved' : 'pending' ),
                                             'redirect_url' => '.'
                                        ) )
            );
        }

        // No need to delete/undelete
        else
        {
            $this->kernel->redirect( $this->kernel->sets['paths']['mod_from_doc'] );
        }
    }


    /**
     * @param int $delete
     */
    private function setDelete($delete) {

        // Get the requested offer
        $id = intval( array_ifnull($_GET, 'id', 0) );
        $data = array();
        if ( $id )
        {
            $sql = sprintf('SELECT * FROM offers'
                                . ' WHERE domain = \'private\' AND id = %d AND deleted <> %d'
                                , $id, $delete);
            $statement = $this->conn->query( $sql );
            if ( $record = $statement->fetch() )
            {
                $data = $record;
            }
        }

        // Need to delete/undelete
        if ( count($data) > 0 )
        {
            if ( !$this->user->hasRights($this->module, Right::APPROVE)
                && $data['status'] == 'approved' )
            {
                $data['status'] = 'pending';
            }

            $sql = sprintf('UPDATE offers SET deleted = %d'
                            . ', status = %s, updated_date=UTC_TIMESTAMP() WHERE domain = \'private\' AND id = %d'
                            , $delete, $this->conn->escape($data['status']), $id);
            $this->conn->exec( $sql );
            if ( $data['status'] == 'approved' )
            {
                $this->publicize( $id, array_keys($this->kernel->sets['public_locales']) );
            } elseif($data['status'] == "pending") {
                $this->send_pending_emails($id, $delete ? "delete" : "undelete");
            }

            $this->kernel->redirect(
                '?' . http_build_query( array(
                                             'op' => 'dialog',
                                             'type' => 'message',
                                             'code' => 'DESCRIPTION_' . ( $delete ? 'deleted' : 'undeleted' ),
                                             'redirect_url' => '.'
                                        ) )
            );
        }

        // No need to delete/undelete
        else
        {
            $this->kernel->redirect( $this->kernel->sets['paths']['mod_from_doc'] );
        }
    }

    private function send_pending_emails($pid, $action = "edit") {
        $success = false;

        /** @var roleNode $ExpApproveRole */
        $ExpApproveRole = $this->roleTree->findById($this->user->getRole()->getId());

        while($ExpApproveRole->getLevel() >= 0 && $ExpApproveRole = $ExpApproveRole->getParent()) {
            if($ExpApproveRole->getItem()->hasRights('offer_admin', array(Right::APPROVE))) {
                break;
            }
        }

        if($ExpApproveRole->getItem()->getId() != $this->user->getRole()->getId()) {
            $id = $ExpApproveRole->getItem()->getId();

            $sql = sprintf('SELECT email, id, first_name FROM users u WHERE role_id = %d AND enabled = 1', $id);
            $statement = $this->conn->query($sql);
            $recipients = $statement->fetchAll();
        }

        $ids = array(0);

        if(count($recipients)) {
            $tmp = $recipients;
            $recipients = array();

            foreach($tmp as $u) {
                $ids[] = $u['id'];
                $recipients[$u['id']] = $u;
            }

            // has same number of requested webpage email sent to avoid sending again
            $sql = sprintf('SELECT target_user'
                . ' FROM approval_requests'
                . ' WHERE `type` = "offer" AND requested_by = %d AND target_id IN(%s)'
                . ' AND target_user IN(%s)'
                , $this->user->getId()
                , implode(', ', array($pid))
                , implode(', ', $ids));
            $statement = $this->conn->query($sql);
            while($row = $statement->fetch()) {
                unset($recipients[$row['target_user']]);
            }
        }

        if(count($recipients)) {
            $sql = sprintf('SELECT * FROM offer_locales WHERE domain = \'private\' AND offer_id = %d ORDER BY locale = %s DESC LIMIT 0, 1'
                , $pid, $this->conn->escape($this->kernel->request['locale']));
            $statement = $this->conn->query($sql);

            $data = array(
                'offers' => array()
            );

            if($record = $statement->fetch()) {
                $data['offers'][] = array(
                    'id' => $record['offer_id'],
                    'title' => $record['title']
                );
            }

            $data['recipients'] = $recipients;

            if(count($recipients)) {
                $this->kernel->smarty->assignByRef('data', $data);
                $this->kernel->smarty->assign('p_action', $action);

                $sub_sqls = array();

                // Try to send email one by one
                $success = FALSE;
                $lines = explode( "\n", $this->kernel->smarty->fetch("module/offer_admin/locale/{$this->kernel->request['locale']}_pending_email.html") );
                $this->kernel->mailer->isHTML( TRUE );
                $this->kernel->mailer->ContentType = 'text/html';
                $this->kernel->mailer->Subject = trim( array_shift($lines) );
                $this->kernel->mailer->Body = implode( "\n", $lines );

                foreach ( $recipients as $recipient )
                {
                    $data['recipient_email'] = $recipient['email'];
                    $data['recipient_name'] = $recipient['first_name'];

                    $this->kernel->mailer->addAddress(
                        $data['recipient_email'],
                        $data['recipient_name']
                    );

                    $sub_sqls[] = sprintf('(%s, %d, %d, %d, UTC_TIMESTAMP())', '"offer"', $pid, $this->user->getId(), $recipient['id']);
                }

                if(count($sub_sqls)) {
                    $sql = sprintf('REPLACE INTO approval_requests(type, target_id, requested_by, target_user, requested_time)'
                        . ' VALUES %s', implode(', ', $sub_sqls));
                    $this->conn->exec($sql);
                }

                try {
                    $success = $this->kernel->mailer->send();
                } catch(Exception $e) {
                    $this->kernel->log('message', sprintf("User %d experienced failure in sending mail: %s\n"
                        , $this->user->getId(), $e->getTraceAsString()), __FILE__, __LINE__);
                }
                $this->kernel->mailer->ClearAllRecipients();
            }
        }

        return $success;
    }


    private function genToken($id = 0) {
        $token_days_expired = 5;

        $token = $this->createPvToken($id, "offer");

        try {
            $this->conn->beginTransaction();

            $sql = sprintf('SELECT * FROM offers WHERE domain = \'private\' AND `type` = "page" AND id = %d AND deleted = 0'
                            , $id);
            $statement = $this->conn->query($sql);
            $target = $statement->fetch();

            if($target) {
                $sql = sprintf('DELETE FROM webpage_preview_tokens WHERE `type` = "offer" AND initial_id = %d', $target['id']);
                $this->conn->exec($sql);

                $sql = sprintf('INSERT INTO webpage_preview_tokens(token, `type`, initial_id, created_date, creator_id, grant_role_id'
                    . ', expire_time) VALUES(%1$s, %2$s, %3$d, UTC_TIMESTAMP(), %4$d, %6$d, DATE_ADD(UTC_TIMESTAMP(), INTERVAL %5$d DAY) )'
                    , $this->conn->escape($token['token']), $this->conn->escape('offer'), $id, $this->user->getId(), $token_days_expired, $this->user->getRole()->getId());
                $this->conn->exec($sql);

                $this->kernel->log('message', sprintf(
                    'User %1$d generated an anonymous preview token for offer %2$s'
                    , $this->user->getId(), $target['id']), __FILE__, __LINE__);

            }

            $this->conn->commit();
        } catch(Exception $e) {
            $this->processException($e);
            return FALSE;
        }

        // continue to process (successfully)
        $redirect = $this->kernel->sets['paths']['mod_from_doc'] . "?" .
            http_build_query(array(
                                  'op' => 'dialog',
                                  'type' => 'message',
                                  'code' => 'DESCRIPTION_preview_link_generated',
                                  'redirect_url' => $this->kernel->sets['paths']['server_url']
                                      . $this->kernel->sets['paths']['mod_from_doc']
                                      . '?id=' . $id
                             ));
        $this->kernel->redirect($redirect);
    }

    private function removeToken($id = 0) {
        try {
            $this->conn->beginTransaction();

            // see if the page exists
            $sql = sprintf('SELECT * FROM offers WHERE domain = \'private\' AND `type` = "page" AND id = %d AND deleted = 0'
                , $id);
            $statement = $this->conn->query($sql);
            $target = $statement->fetch();

            if($target) {
                $sql = sprintf('DELETE FROM webpage_preview_tokens WHERE `type` = "offer" AND initial_id = %d', $target['id']);
                $this->conn->exec($sql);

                $this->kernel->log('message', sprintf(
                    'User %1$d removed an anonymous preview token for offer %2$s'
                    , $this->user->getId(), $target['id']), __FILE__, __LINE__);
            }

            $this->conn->commit();
        } catch(Exception $e) {
            $this->processException($e);
            return FALSE;
        }

        // continue to process (successfully)
        $redirect = $this->kernel->sets['paths']['mod_from_doc'] . "?" .
            http_build_query(array(
                                  'op' => 'dialog',
                                  'type' => 'message',
                                  'code' => 'DESCRIPTION_preview_link_removed',
                                  'redirect_url' => $this->kernel->sets['paths']['server_url']
                                      . $this->kernel->sets['paths']['mod_from_doc']
                                      . '?id=' . $id
                             ));
        $this->kernel->redirect($redirect);
    }
}
