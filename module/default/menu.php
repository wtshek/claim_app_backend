<?php

// Include required files
require_once( dirname(dirname(__FILE__)) . '/base/index.php' );

/**
 * The menu module.
 *
 * This module generates the menu.
 *
 * @author  Martin Ng <martin@avalade.com>
 * @since   2020-03-09
 */
class menu_module extends base_module
{
    /**
     * Get menu.
     *
     * @since   2020-03-09
     * @param   root_page   The root page
     * @param   depth       The depth
     */
    function get_menu( pageNode $root_page, $depth = -1 )
    {
        $current_label = htmlspecialchars( array_ifnull($this->kernel->dict, 'LABEL_current', 'Current') );

        /** @var pageNode $child */
        $child = null;
        $content = '';

        if($root_page->hasChild()) {
            $children = $root_page->getChildren(0);

            foreach($children as $i => $child) {
                $child_content = "";
                $page = $child->getItem();

                if( $page->hasLocale($this->kernel->request['locale'])
                    && $page->getShownInMenu() && !$child->getDeleted() && $child->getEnabled()
                    && $child->accessible($this->user->getRole()->getId())
                    && $page->available($this->kernel->request['locale'])
                    && $page->getId() != $this->kernel->conf['footer_webpage_id'] // footer menu
                ) {
                    if($child->hasChild()) {
                        $child_content = $this->get_menu( $child, $depth - 1 );
                    }

                    $url = $page->getRelativeUrl( $this->platform );
                    $text = $page->getTitle( $this->kernel->request['locale'] );
                    $title = method_exists( $page, 'getHeadlineTitle' ) ? $page->getHeadlineTitle() : '';
                    $target = in_array( $page->getType(), array('static', 'structured_page') ) ? '' : $page->getTarget( $this->platform );
                    $params = array_map( 'htmlspecialchars', array(
                        ':href' => $this->kernel->sets['paths']['mod_from_doc'] . $url,
                        ':text' => $text,
                        ':title' => $title ? $title : $text,
                        ':class' => strpos( $this->data['webpage']['path'], $url ) === 0 ? 'active' : '',
                        ':target' => $target ? " target=" . $target : ""
                    ) );
                    $params[':sr_content'] = $this->data['webpage']['path'] == $url ? ( ' <span class="sr-only">(' . $current_label . ')</span>' ) : '';
                    $params[':child_content'] = $child_content;
                    if ( !in_array(get_class($page), array('staticPage', 'structuredPagePage')) || $page->hasContent() )
                    {
                        $params[':class'] = trim( "{$params[':class']} has-content" );
                    }

                    // List item
                    if ( $depth == 0 )
                    {
                        $content .= strtr( '<li class=":class"><a href=":href" title=":title":target>:text:sr_content</a></li>', $params );
                    }

                    // Tree item of first level
                    else if ( $depth == -1 )
                    {
                        if ( $child_content )
                        {
                            $params[':class'] = trim( "nav-item dropdown {$params[':class']}" );
                            $params[':alias'] = htmlspecialchars( $page->getAlias() );
                            $content .= strtr( '<li class=":class"><a href=":href" title=":title" class="nav-link":target>:text</a>'
                                . '<button class="btn dropdown-toggle dropdown-toggle-split" id=":alias-menu"'
                                . ' role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><span class="sr-only">'
                                . htmlspecialchars( array_ifnull($this->kernel->dict, 'LABEL_toggle_dropdown', 'Toggle dropdown') )
                                . '</span></button>:child_content</li>', $params );
                        }
                        else
                        {
                            $params[':class'] = trim( "nav-item {$params[':class']}" );
                            $content .= strtr( '<li class=":class"><a href=":href" title=":title" class="nav-link":target>:text:sr_content</a></li>', $params );
                        }
                    }

                    // Tree item of other levels
                    else
                    {
                        if ( $child_content )
                        {
                            $params[':class'] = trim( "dropdown-item dropdown-toggle {$params[':class']}" );
                            $params[':alias'] = htmlspecialchars( $page->getAlias() );
                            $content .= strtr( '<li class="dropdown-submenu"><a href=":href" title=":title" class=":class":target id=":alias-menu"'
                                . ' role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">:text:sr_content</a>:child_content</li>', $params );
                        }
                        else
                        {
                            $params[':class'] = trim( "dropdown-item {$params[':class']}" );
                            $content .= strtr( '<li><a href=":href" title=":title" class=":class":target>:text:sr_content</a></li>', $params );
                        }
                    }
                }
            }
        }

        // Wrap only if one or more child webpages is shown
        if ( $content )
        {
            $page = $root_page->getItem();

            // List
            if ( $depth == 0 )
            {
                $content = sprintf(
                    '<ul class="links list-unstyled">%s</ul>',
                    $content
                );
            }

            // Tree of first level
            else if ( $depth == -1 )
            {
                $text = $page->getTitle( $this->kernel->request['locale'] );
                $title = method_exists( $page, 'getHeadlineTitle' ) ? $page->getHeadlineTitle() : '';
                $params = array_map( 'htmlspecialchars', array(
                    ':href' => $this->kernel->sets['paths']['mod_from_doc'] . $page->getRelativeUrl( $this->platform ),
                    ':text' => $text,
                    ':title' => $title ? $title : $text,
                    ':class' => trim( 'nav-item ' . ($this->data['webpage']['path'] == '/' ? 'active' : '') )
                ) );
                $params[':sr_content'] = $this->data['webpage']['path'] == '/' ? ( ' <span class="sr-only">(' . $current_label . ')</span>' ) : '';
                $content = strtr( '<li class=":class"><a href=":href" title=":title" class="nav-link">:text:sr_content</a></li>', $params ) . $content;
                $content = sprintf(
                    '<ul class="navbar-nav">%s</ul>',
                    $content
                );
            }

            // Tree of other levels
            else
            {
                $content = sprintf(
                    '<ul class="dropdown-menu" aria-labelledby="%s-menu">%s</ul>',
                    htmlspecialchars( $page->getAlias() ),
                    $content
                );
            }
        }
        
        return $content;
    }
}
