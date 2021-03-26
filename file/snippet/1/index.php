<?php

/**
 * The gallery snippet.
 *
 * The filterabled gallery
 *
 * @since   2019-09-11 09:45:00
 * @param   module      The module
 * @param   snippet     The snippet
 * @param   parameters  The parameters
 * @return  HTML content
 */
function gallery_snippet( &$module, &$snippet, $parameters )
{
    $getimagesize = $module->kernel->conf['aws_enabled'] ? 's3_getimagesize' : 'getimagesize';
    $params = array_map( array($module->kernel->db, 'escape'), array(
        ':domain' => $module->pg_type,
        ':locale' => $module->kernel->request['locale'],
        ':id' => intval( $parameters['id'] )
    ) );

    // Data container
    $snippet_data = $parameters;
    $snippet_data['snippet_id'] = uniqid();
    $module->kernel->smarty->assignByRef( 'snippet_data', $snippet_data );

    // Query condition
    $from = 'gallery_containers AS c';
    $from .= ' JOIN gallery_container_tags AS ct ON (c.domain = ct.domain AND c.id = ct.gallery_container_id)';
    $from .= ' JOIN gallery_tags AS t ON (ct.domain = t.domain AND ct.gallery_tag_id = t.id AND t.deleted = 0)';
    $from .= ' JOIN gallery_tag_locales AS tl ON (t.domain = tl.domain AND t.id = tl.gallery_tag_id AND tl.locale = :locale)';
    $from .= ' JOIN gallery_image_tags AS it ON (t.domain = it.domain AND t.id = it.gallery_tag_id)';
    $from .= ' JOIN gallery_images AS i ON (it.domain = i.domain AND it.gallery_image_id = i.id AND i.deleted = 0)';
    $where = array(
        'c.domain = :domain',
        'c.deleted = 0',
        'c.id = :id'
    );

    // Get the requested tags
    $sql = "SELECT CONCAT('gallery-tag-', GROUP_CONCAT(DISTINCT t.id ORDER BY t.order_index, t.id SEPARATOR '-')) AS id, tl.title";
    $sql .= " FROM $from WHERE " . implode( ' AND ', $where );
    $sql .= ' GROUP BY tl.title ORDER BY t.order_index, t.id';
    $sql = strtr( $sql, $params );
    $snippet_data['tags'] = $module->kernel->get_set_from_db( $sql );
    if ( count($snippet_data['tags']) == 0 )
    {
        return '';
    }
    $snippet_data['default_tag'] = isset( $snippet_data['default_tag'] ) ? array_search( $snippet_data['default_tag'], $snippet_data['tags'] ) : FALSE;

    // Get the requested images
    $sql = "SELECT i.id, i.image, il.caption, il.alternative_text, GROUP_CONCAT(DISTINCT tl.title SEPARATOR '\r\n') AS tags";
    $sql .= " FROM $from";
    $sql .= ' JOIN gallery_image_locales AS il ON (i.domain = il.domain AND i.id = il.gallery_image_id AND il.locale = :locale)';
    $sql .= ' LEFT OUTER JOIN gallery_container_images AS ci ON (i.domain = ci.domain AND c.id = ci.gallery_container_id AND i.id = ci.gallery_image_id)';
    $sql .= ' WHERE ' . implode( ' AND ', $where );
    $sql .= ' GROUP BY i.id ORDER BY ci.order_index IS NULL, ci.order_index, i.id';
    $sql = strtr( $sql, $params );
    $snippet_data['images'] = array();
    $statement = $module->kernel->db->query( $sql );
    if ( !$statement )
    {
        $module->kernel->quit( 'DB Error: ' . array_pop($module->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
    }
    while ( $record = $statement->fetch() )
    {
        // Get image tags
        $record['tags'] = explode( "\r\n", $record['tags'] );
        foreach ( $record['tags'] as $i => $tag )
        {
            $record['tags'][$i] = array_search( $tag, $snippet_data['tags'] );
        }

        // Get image size
        $image_src = urldecode( $record['image'] );
        if ( !$module->kernel->conf['aws_enabled'] && strpos($image_src, ':') === FALSE )
        {
            $image_src = 'file/' . $image_src;
        }
        $image_size = $getimagesize( $image_src );
        $record['width'] = array_ifnull( $image_size, 0, 0 );
        $record['height'] = array_ifnull( $image_size, 1, 0 );

        $snippet_data['images'][$record['id']] = $record;
    }

    // Add "All" to the requested tags
    $snippet_data['tags'][FALSE] = $module->kernel->dict['LABEL_all'];

    return $module->kernel->smarty->fetch( "file/snippet/{$snippet['id']}/index.html" );
}

/*************
Sample snippet code:

<div>{{gallery</div>
<div>id=1</div>
<div>}}</div>

***************/

?>