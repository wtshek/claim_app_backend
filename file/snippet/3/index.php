<?php

/**
 * Child Pages Snippet
 * @author Patrick Yeung
 * @email <patrick[at]avalade[dot]com>
 *
 * @param  default_module   $module
 * @param     $snippet
 * @param     $parameters
 * @param   page  $page
 * @return string
 */
function child_pages_snippet( &$module, &$snippet, $parameters, $page )
{
    $parameters['level'] = intval(array_ifnull($parameters, 'level', 1));
    $conn = db::Instance();
    $kernel = kernel::getInstance();

    /** @var sitemap $sm */
    $sm = $module->sitemap;

    $url = $page->getRelativeUrl($module->platform);

    if($url) {
        /** @var pageNode $pn */
        $pn = $sm->findPage($url);

        if($pn && !is_null($pn)) {
            $children = $pn->getChildren($parameters['level']);
            /** @var pageNode $child */
            $child = null;

            $list = array();

            foreach($children as $child) {
                if(!$child->getDeleted() && $child->available($kernel->request['locale']) && $child->getEnabled()
                    && $child->getItem()->hasLocale($kernel->request['locale'])
                    && $child->accessible($module->user->getRole()->getId()))
                    $list[$child->getItem()->getId()] = $child;
            }

            $kernel->smarty->assign('child_pages_items', $list);

            return $kernel->smarty->fetch("file/snippet/{$snippet['id']}/index.html");
        }
    }
}