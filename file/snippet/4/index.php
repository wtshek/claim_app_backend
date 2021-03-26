<?php


/**
 * Sitemap Snippet
 * @author Patrick Yeung
 * @email <patrick[at]avalade[dot]com>
 *
 * @param     $module
 * @param     $snippet
 * @param     $parameters
 * @param int $shown_count
 * @param int $depth
 * @return string
 */
function site_map_snippet( &$module, &$snippet, $parameters, &$shown_count = 0, $depth = 0 )
{
    if($module->sitemap && $module->sitemap->getRoot()) {
        /** @var sitemap $sitemap */
        $sitemap = $module->sitemap;

        $max_row = intval(array_ifnull($parameters, 'max_column_rows', 20));
        if($max_row < 1)
            $max_row = 20;

        $conn = db::Instance();

        $tb_prefix = get_class($module) == "preview_module" ? "private" : "public";
        $sql = sprintf('SELECT pw.id FROM webpages pw '
                        // Get the latest version only
                        . ' JOIN ( SELECT * FROM(SELECT * FROM webpages pw2 WHERE pw2.domain = %1$s ORDER BY pw2.id, pw2.major_version DESC, pw2.minor_version DESC'
                        . ' ) AS tb GROUP BY tb.id ) AS pw2 ON(pw.id = pw2.id AND pw.major_version = pw2.major_version AND pw.minor_version = pw2.minor_version AND pw2.type = "static" AND pw2.deleted = 0)'

                        . ' WHERE pw.domain = %1$s AND pw.type = "static" AND NOT EXISTS(SELECT * FROM(SELECT tb.* FROM(SELECT w1.* FROM webpages w1 JOIN webpage_platforms p1 ON(w1.domain = p1.domain AND w1.id = p1.webpage_id'
                        . ' AND w1.major_version = p1.major_version AND w1.minor_version = p1.minor_version AND p1.platform = %2$s)'
                        . ' WHERE w1.domain = %1$s'
                        . ' ORDER BY p1.level, p1.webpage_id, p1.major_version DESC, p1.minor_version DESC) AS tb GROUP BY tb.domain, tb.id) AS tb2'
                        . ' JOIN webpage_locale_contents c ON(tb2.domain = c.domain AND tb2.id = c.webpage_id AND tb2.major_version = c.major_version AND tb2.minor_version = c.minor_version'
                        . ' AND c.platform = %2$s) WHERE pw.id = c.webpage_id AND pw.major_version = c.major_version AND pw.minor_version = c.minor_version'
                        . ' AND c.content <> "" AND c.content <> "<div></div>" GROUP BY c.webpage_id)'
                        , $conn->escape($tb_prefix)
                        , $conn->escape($module->platform));
        $statement = $conn->query($sql);
        $ids_exclude_url = array();
        while($row = $statement->fetch()) {
            $target = $sitemap->getRoot()->findById($row['id']);
            if($target && $target->hasChild())
                $ids_exclude_url[] = $row['id'];
        }

        $content = renderSitemap($module, $sitemap->getRoot(), $max_row, $ids_exclude_url);

        return $content;
    }
    else
    {
        return '';
    }
}

function renderSitemap(&$module, pageNode $pageNode, $max_row = 20, $no_link_ids = array(), $level = 0, &$count = 0) {
    $direct_children = $pageNode->getChildren(0);
    $num = count($direct_children);

    $items = array();
    $inner_content = array();

    for($i = 0; $i < $num; $i++) {
        /** @var pageNode $child */
        $child = $direct_children[$i];

        if($level == 0) {
            $count = 0;
        }

        $count++;

        $page = $child->getItem();
        if($child->getItem()->hasLocale($module->kernel->request['locale'])
            && !$child->getDeleted() && $child->getEnabled() && $child->available($module->kernel->request['locale'])
            && $child->accessible($module->user->getRole()->getId())
            && $page->getShownInSitemap()){
            $hasChild = false;
            if($child->hasChild()) {
                $children = $child->getChildren();
                /** @var pageNode $inner_child */
                $inner_child = null;
                foreach($children as $inner_child) {
                    if($inner_child->getItem()->getShownInSitemap()){
                        $hasChild = true;
                        break;
                    }
                }
            }

            $new_item = sprintf('<li class="%1$s">%2$s%3$s</li>'
                , $hasChild ? "hasChild" : ""
                , in_array($child->getItem()->getId(), $no_link_ids)
                    ? sprintf('<span>%1$s</span>', htmlspecialchars($child->getItem()->getTitle()))
                    : sprintf('<a href="%2$s" title="%1$s" target="%3$s">%1$s</a>'
                        , htmlspecialchars($child->getItem()->getTitle())
                        , htmlspecialchars($module->kernel->sets['paths']['app_from_doc']
                        . '/' . $module->kernel->request['locale']
                        . $child->getItem()->getRelativeUrl())
                        , in_array($child->getItem()->getType(), array('static', 'structured_page')) ? "_top" : $child->getItem()->getTarget($module->platform))
                , $child->hasChild() ? renderSitemap($module, $child, $max_row, $no_link_ids, $level+1, $count) : '');

            if($level == 0) {
                $inner_content[] = array(
                    'list' => $new_item,
                    'count' => $count
                );
                unset($count);
            } else {
                $inner_content[] = $new_item;
            }
        }
    }

    $output_list = array();
    $output_html = "";

    if($level == 0) {
        $row_count = 0;
        $current_html = "";

        foreach($inner_content as $html) {
            if($row_count && $html['count'] + $row_count > $max_row) {
                $output_list[] = $current_html;
                $current_html = "";
                $row_count = 0;
            }

            $current_html .= $html['list'];
            $row_count += $html['count'];
        }

        $output_list[] = $current_html;

    } else {
        $output_list[] = implode("\n", $inner_content);
    }

    foreach($output_list as $i => $html) {
        $output_html .= sprintf('<ul class="%s%s%s">%s</ul>'
            , $level == 0 ? "sitemap" : ""
            , " level_" . $level
            , " column_" . ($i + 1)
            , $html);
    }
    return $output_html;
}

?>