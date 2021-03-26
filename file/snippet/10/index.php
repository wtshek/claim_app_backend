<?php

/**
 *
 * An reservation form snippet.
 * 
 * @since   2015-08-18 06:53:00
 * @param   module      The module
 * @param   snippet     The snippet
 * @param   parameters  The parameters
 * 
 * 
 * @return  HTML content
 */
function reservation_widget_snippet( &$module, &$snippet, $parameters )
{
    // Data container
    $snippet_data = array();
    $page = $module->getPageNode()->getItem();

    // Assign data to view
    $module->kernel->smarty->assign( 'snippet_data', $snippet_data );
    $module->kernel->smarty->assign( 'pg', $page );

    return $module->kernel->smarty->fetch( "file/snippet/{$snippet['id']}/index.html" );
}

?>