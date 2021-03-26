<?php

/**
 * The Press Release snippet.
 *
 * The press release listing
 *
 * @since   2015-11-25
 * @param   module      The module
 * @param   snippet     The snippet
 * @param   parameters  The parameters
 * @return  HTML content
 */
function press_release_snippet( &$module, &$snippet, $parameters )
{
    $params = array_map( array($module->kernel->db, 'escape'), array(
        ':domain' => $module->pg_type,
        ':locale' => $module->kernel->request['locale']
    ) );

    // Data container
    $snippet_data = $parameters;
    $module->kernel->smarty->assignByRef( 'snippet_data', $snippet_data );

    // Base query
    $sql = "SELECT *, CONVERT_TZ(p.release_date, 'gmt', {$module->kernel->conf['escaped_timezone']}) AS release_date, YEAR(CONVERT_TZ(p.release_date, 'gmt',";
    $sql .= " {$module->kernel->conf['escaped_timezone']})) AS start_year FROM press_releases AS p";
    $sql .= ' JOIN press_release_locales AS pl ON (p.domain = pl.domain AND p.id = pl.press_release_id AND pl.locale = :locale)';
    $sql .= ' WHERE p.domain = :domain AND deleted = 0';
    if ( $module->pg_type == 'public' )
    {
        $sql .= ' AND UTC_TIMESTAMP() BETWEEN p.start_date AND IFNULL(p.end_date, UTC_TIMESTAMP())';
    }
    $sql = strtr( $sql, $params );

    // Select based on query string
    $path = substr( $module->data['webpage']['path'], strlen($module->data['current_url']) );
    $path_segments = array_diff(explode('/', $path), array(''));
    switch ( count($path_segments) )
    {
        // Get the requested press releases
        case 0:
            $snippet_data['list'] = array();
            $sql .= ' ORDER BY p.release_date DESC, p.start_date DESC, p.end_date DESC, p.id DESC';
            $statement = $module->kernel->db->query( $sql );
            if ( !$statement )
            {
                $module->kernel->quit( 'DB Error: ' . array_pop($module->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
            }
            while ( $record = $statement->fetch() )
            {
                $snippet_data['list'][$record['start_year']][$record['id']] = $record;
            }
            return $module->kernel->smarty->fetch( "file/snippet/{$snippet['id']}/index.html" );

        // Get the requested press release
        case 1:
            $id = intval( current($path_segments) );
            $sql = str_replace(
                array( '*', 'press_releases AS p' ),
                array( '*, GROUP_CONCAT(l.locale) AS locales', "press_releases AS p JOIN press_release_locales AS l ON (p.domain = l.domain AND p.id = l.press_release_id AND (l.domain = 'private' OR l.status = 'approved'))" ),
                $sql
            );
            $sql .= " AND p.id = $id";
            $sql .= ' GROUP BY p.id';
            $statement = $module->kernel->db->query( $sql );
            if ( !$statement )
            {
                $module->kernel->quit( 'DB Error: ' . array_pop($module->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
            }
            if ( $record = $statement->fetch() )
            {
                extract( $record );
                $locales = explode( ',', $locales );
                $module->page_found = TRUE;
                $page_node = $module->getPageNode();
                $page = $page_node->getItem();
                $page->setLocales( $locales );
                foreach ( $locales as $l )
                {
                    $page->setLocaleUrl($l, $page->getRelativeUrl($module->platform) . $id . '/');
                }
                $page->setHeadlineTitle( array($locale => $title) );
                $snippet_data['record'] = $record;

                // Get banners
                $sql = "SELECT * FROM press_release_banners WHERE domain = {$params[':domain']} AND press_release_id = $id";
                $statement = $module->kernel->db->query( $sql );
                if ( !$statement )
                {
                    $module->kernel->quit( 'DB Error: ' . array_pop($module->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
                }
                $page->setBanners( array(
                    $module->kernel->request['locale'] => $statement->fetchAll()
                ) );

                // Get dining and room sets
                $structured_pages = array( 'dinings' => 'press_release_dinings', 'rooms' => 'press_release_rooms' );
                foreach ( $structured_pages as $set => $table )
                {
                    $snippet_data[$set] = array();
                    $sql = "SELECT webpage_id FROM $table WHERE domain = {$params[':domain']} AND press_release_id = $id";
                    $statement = $module->kernel->db->query( $sql );
                    if ( !$statement )
                    {
                        $module->kernel->quit( 'DB Error: ' . array_pop($module->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
                    }
                    while ( $record = $statement->fetch() )
                    {
                        $p = $module->sitemap->getRoot()->findById( $record['webpage_id'] );
                        if ( $p )
                        {
                            $i = $p->getItem();
                            $i->retrieveData( $module->kernel->request['locale'], $module->pg_type );
                            $d = json_decode( $i->getPlatformHtml($module->platform, $module->kernel->request['locale'])['content'], TRUE );
                            if ( $set == 'dinings' )
                            {
                                $menus = array();
                                for ( $i = 1; array_key_exists("menu_name$i", $d[12]); $i++ )
                                {
                                    $menus[] = array(
                                        'name' => $d[12]["menu_name$i"],
                                        'file' => $d[12]["menu_file$i"]
                                    );
                                }
                                $snippet_data[$set][] = array(
                                    'logo' => $d[11]['logo1'],
                                    'title' => $d[11]['section_heading'],
                                    'opening_hours_title' => $d[12]['opening_hours_title'],
                                    'opening_hours_details' => $d[12]['opening_hours_details'],
                                    'location_title' => $d[12]['location_title'],
                                    'location_details' => $d[12]['location_details'],
                                    'attire_title' => $d[12]['attire_title'],
                                    'attire_details' => $d[12]['attire_details'],
                                    'menu_title' => $d[12]['menu_title'],
                                    'menus' => $menus
                                );
                            }
                            else
                            {
                                $snippet_data[$set][] = array(
                                    'title' => $d[15]['section_heading'],
                                    'short_description' => $d[15]['short_description1'],
                                    'image' => $d[15]['image1']
                                );
                            }
                        }
                        $result->moveNext();
                    }
                }

                return $module->kernel->smarty->fetch( "file/snippet/{$snippet['id']}/detail.html" );
            }
    }

    return '';
}

/*************
Sample snippet code:

<div>{{press_release</div>
<div>LABEL_year_filter=Show the press releases of</div>
<div>}}</div>

***************/

?>