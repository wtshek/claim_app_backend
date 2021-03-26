<?php

/**
 * The Image Slideshow snippet.
 *
 * An image slideshow snippet for URC.
 *
 * @since   2011-08-10 09:00:00
 * @param   module      The module
 * @param   snippet     The snippet
 * @param   parameters  The parameters
 * @return  HTML content
 */
function image_slideshow_snippet( &$module, &$snippet, $parameters )
{
    // Ensure parameters exists
    //$parameters['interval'] = intval( array_ifnull($parameters, 'interval', '') );
    //$parameters['duration'] = intval( array_ifnull($parameters, 'duration', '') );

    // Ensure parameters are correct
    //$parameters['interval'] = max( 0, $parameters['interval'] );
    //$parameters['duration'] = min( max(0, $parameters['duration']), $parameters['interval'] );

    // Data container
    $snippet_data['is_homepage'] = intval( array_ifnull($parameters, 'is_homepage', 0));
    
    if($snippet_data['is_homepage'])
    {
        // The quotes slider in homepage
        $snippet_data['bg_image'] = trim($parameters['bg_image']);
        $snippet_data['btn_text'] = trim($parameters['btn_text']);
        $snippet_data = array_merge( $parameters, array(
            'titles' => array(),
            'subtitles' => array()
        ) );
        
        foreach($parameters as $parameter => $value) {
            if(preg_match('#^title[0-9]+$#i', $parameter))
            {
                $snippet_data['titles'][] = preg_replace('/:::/is', '<br>', html_entity_decode(trim($value)));
            }
            if(preg_match('#^subtitle[0-9]+$#i', $parameter))
                $snippet_data['subtitles'][] = trim($value);
        }
    }
    else
    {
        //Common silder in inner pages
        $snippet_data = array_merge( $parameters, array(
            'images' => array()
        ) );
    
        foreach($parameters as $parameter => $value) {
            if(preg_match('#^image[0-9]+$#i', $parameter))
                $snippet_data['images'][] = trim($value);
        }
    }
    
    $snippet_data['id_surfix'] = md5(time());
    
    /*
    for ($i = 1; array_key_exists('image'.$i, $parameters); $i++) {
        $snippet_data['images'][] = trim($parameters['image'.$i]);
    }
    */

    // Assign data to view
    $module->kernel->smarty->assignByRef( 'snippet_data', $snippet_data );

    return $module->kernel->smarty->fetch( "file/snippet/{$snippet['id']}/index.html" );
}

?>