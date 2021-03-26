<?php

// Get the root directory of this application
// e.g. C:\www\avalade_cms\module\media_center_admin\index.php -> C:\www\avalade_cms
$APP_ROOT = dirname( dirname(dirname( __FILE__ )) );

// Include required files
require_once( "$APP_ROOT/module/entity_admin/index.php" );

/**
 * The media center admin module.
 *
 * This module allows user to administrate gallery entities.
 *
 * @author  Martin Ng <martin@avalade.com>
 * @since   2019-09-09
 */
class gallery_admin_module extends entity_admin_module
{
    public $module = 'gallery_admin';

    /**
     * Constructor
     *
     * @param   $kernel
     * @since   2019-09-09
     */
    function __construct( &$kernel )
    {
        parent::__construct( $kernel );

        // Load additional dictionary file
        require( dirname(__FILE__) . "/locale/{$this->kernel->request['locale']}.php" );
        $this->kernel->dict = array_merge_recursive_unique( $this->kernel->dict, $DICT );

        // Load additional definition file
        require( dirname(__FILE__) . "/def.php" );
        $this->def = &$DEF;
    }

    /**
     * Process the request.
     *
     * @since   2019-09-10
     * @return  Processed or not
     */
    function process()
    {
        if ( !parent::process() )
        {
            $op = array_ifnull( $_GET, 'op', 'index' );
            switch ( $op )
            {
                case 'get_gallery_container_images':
                    $this->method = 'get_gallery_container_images';
                    break;
            }
            if ( $this->method )
            {
                $content = call_user_func_array( array($this, $this->method), $this->params );
                if ( !is_null($content) )
                {
                    $this->apply_template = FALSE;
                    $this->kernel->response['mimetype'] = 'application/json';
                    $this->kernel->response['content'] = json_encode( $content );
                }
                return TRUE;
            }
            return FALSE;
        }
    }

    /**
     * Edit an entity based on ID.
     *
     * @since   2019-09-10
     */
    function edit()
    {
        parent::edit();

        // Post processing for gallery container
        if ( $_GET['entity'] == 'gallery_container' )
        {
            // Edit page
            if ( $_SERVER['REQUEST_METHOD'] == 'GET' )
            {
                $this->kernel->response['content'] = $this->kernel->smarty->fetch( 'module/gallery_admin/edit_gallery_container.html' );
            }
        }
    }

    /**
     * Save an entity based on data.
     *
     * @since   2019-09-10
     * @param   data                The data
     * @param   status              The status
     * @param   selected_locale     The selected locale
     * @return  The ID
     */
    function save( $data, $status, $selected_locale = '' )
    {
        parent::save( $data, $status, $selected_locale );

        // Post processing for gallery container
        if ( $_GET['entity'] == 'gallery_container' )
        {
            $gallery_image_ids = array_unique( array_map('intval', array_ifnull($_POST, 'gallery_image_ids', array())) );

            // Delete existing gallery container images
            $sql = "DELETE FROM gallery_container_images WHERE domain = 'private' AND gallery_container_id = {$data['base_table']['id']}";
            $statement = $this->kernel->db->query( $sql );
            if ( !$statement )
            {
                $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
            }

            // Insert new gallery container images
            if ( count($gallery_image_ids) > 0 )
            {
                $values = array();
                foreach ( $gallery_image_ids as $i => $gallery_image_id )
                {
                    $values[] = sprintf(
                        "('private', %u, %u, %u)",
                        $data['base_table']['id'],
                        $gallery_image_id,
                        $i + 1
                    );
                }
                $sql = 'INSERT INTO gallery_container_images(domain, gallery_container_id, gallery_image_id, order_index) VALUES ' . implode( ', ', $values );
                $statement = $this->kernel->db->query( $sql );
                if ( !$statement )
                {
                    $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
                }
            }
        }
    }

    /**
     * Publicize an entity based on ID.
     *
     * @since   2019-09-10
     * @param   id      The ID
     * @param   name                The name
     * @param   selected_locale     The selected locale
     */
    function publicize( $id, $name, $selected_locale = '' )
    {
        parent::publicize( $id, $name, $selected_locale );

        // Post processing for gallery container
        if ( $_GET['entity'] == 'gallery_container' )
        {
            // Delete existing gallery container images
            $sql = "DELETE FROM gallery_container_images WHERE domain = 'public' AND gallery_container_id = $id";
            $statement = $this->kernel->db->query( $sql );
            if ( !$statement )
            {
                $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
            }

            // Insert new gallery container images
            $sql = "INSERT INTO gallery_container_images(domain, gallery_container_id, gallery_image_id, order_index)";
            $sql .= " SELECT 'public', gallery_container_id, gallery_image_id, order_index FROM gallery_container_images";
            $sql .= " WHERE domain = 'private' AND gallery_container_id = $id";
            $statement = $this->kernel->db->query( $sql );
            if ( !$statement )
            {
                $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
            }
        }
    }

    /**
     * Get the images of a gallery container based on ID.
     *
     * @since   2019-09-10
     */
    function get_gallery_container_images()
    {
        // Check authentication
        $this->rights_required[] = Right::VIEW;
        $this->user->checkRights( $this->kernel->response['module'], $this->rights_required );

        // Get the requested images
        $id = intval( array_ifnull($_GET, 'id', '') );
        $gallery_tag_ids = array_map( 'intval', array_ifnull($_GET, 'gallery_tag_ids', array()) );
        $gallery_image_ids = array_map( 'intval', array_ifnull($_GET, 'gallery_image_ids', array()) );
        $sql = 'SELECT DISTINCT i.id, i.image FROM gallery_images AS i';
        $sql .= ' JOIN gallery_image_tags AS it ON (i.domain = it.domain AND i.id = it.gallery_image_id AND it.gallery_tag_id IN (:gallery_tag_ids))';
        $sql .= ' LEFT OUTER JOIN gallery_container_images AS ci ON (i.domain = ci.domain AND i.id = ci.gallery_image_id AND ci.gallery_container_id = :id)';
        $sql .= " WHERE i.domain = 'private' AND i.deleted = 0";
        $sql .= ' ORDER BY ci.order_index IS NULL, ci.order_index, i.id';
        $sql = strtr( $sql, array(
            ':id' => $id,
            ':gallery_tag_ids' => implode( ', ', array_merge($gallery_tag_ids, array(0)) )
        ) );
        $images = $this->kernel->get_set_from_db( $sql );
        $new_images = array();
        foreach ( $gallery_image_ids as $image_id )
        {
            if ( array_key_exists($image_id, $images) )
            {
                $new_images[$image_id . '.0'] = $images[$image_id];
            }
        }
        foreach ( $images as $image_id => $image )
        {
            $new_images[$image_id . '.0'] = $image;
        }
        return $new_images;
    }
}
