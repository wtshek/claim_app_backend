<?php

/**
 *
 * An image slideshow snippet for Marina.
 *
 * jQurey plugin: http://www.jqueryrain.com/?vQrlYWlL
 *
 * @since   2014-11-28 06:53:00
 * @param   module      The module
 * @param   snippet     The snippet
 * @param   parameters  The parameters
 *
 *
 * @return  HTML content
 */
function pgwSlider_snippet( &$module, &$snippet, $parameters )
{
    // Data container
    $snippet_data = array();

    foreach($parameters as $para => $value)
    {
        $type_order = 0;
        if(preg_match('/^image([\d]+)$/i', $para, $matches))
        {
            $snippet_data['images'][] = $value;
        }
        elseif(preg_match('/^caption([\d]+)$/i', $para, $matches))
        {
            $snippet_data['captions'][] = $value;
        }
        else {
            switch ($para) {
                case 'interval':
                case 'duration':
                    $snippet_data[$para] = intval($value);
                    break;

                default:
                    $snippet_data[$para] = trim($value);
                    break;
            }
        }
    }

    // Assign data to view
    $module->kernel->smarty->assignByRef( 'snippet_data', $snippet_data );

    return $module->kernel->smarty->fetch( "file/snippet/{$snippet['id']}/index.html" );
}

?>