<?php


/**
 * @param default_module $module
 * @param $snippet
 * @param $parameters
 */

function setCategories($categories)
{
    $this->_categories = $categories;
}

function getCategories()
{
    return $this->_categories;
}

function offer_snippet( &$module, &$snippet, $parameters )
{
	$path = $module->data['webpage']['path'];

    /** @var staticPage $page */
    $page = $module->getPageNode()->getItem();
	$page_id = intval($page->getId());

    // get the webpage url for the page promotion
	$k = $module->kernel;
	$offer_page_id = intval($k->conf['offer_webpage_id']);
	/** @var sitemap $sm */
	$sm = $module->get_sitemap("view", $module->platform);

	/** @var pageNode $node */
	$node = $sm->getRoot()->findById($offer_page_id);

	if($node) {
		$url = $node->getItem()->getRelativeUrl($module->platform);
	}

    $_this_url = $page->getRelativeUrl($module->platform);
    $module->kernel->smarty->assign('offer_url', $module->kernel->sets['paths']['app_from_doc'] . '/' . $module->kernel->request['locale'].$_this_url);

    $path = substr($path, strlen($_this_url));

    $segments = array_filter(explode('/', $path), 'strlen');

    if(count($segments) === 0 || count($segments) === 1) {
		$is_index = $page_id == $offer_page_id;
		$alias = (count($segments) === 1 ? $segments[0] : '');
        $conn = db::Instance();

        $sql = "SELECT *, CONVERT_TZ(o.period_from, 'gmt', {$module->kernel->conf['escaped_timezone']}) AS period_from,";
        $sql .= " CONVERT_TZ(o.period_to, 'gmt', {$module->kernel->conf['escaped_timezone']}) AS period_to";
        $sql .= ' FROM offers AS o';
        $sql .= ' JOIN offer_locales AS l ON(o.domain = l.domain AND o.id = l.offer_id AND l.locale = :locale)';
        $sql .= ' WHERE o.domain = :domain AND o.deleted = 0';
        $sql .= ' AND (o.end_date IS NULL OR o.end_date > UTC_TIMESTAMP())';
        if ( count($segments) === 1 )
        {
            $sql .= ' AND o.alias = :alias';
        }
        if ( !$is_index )
        {
            $offer_ids = array_merge( $page->getOfferIds(), array(0) );
            $sql .= ' AND o.id IN (' . implode( ', ', $offer_ids ) . ')';
        }
        if ( $module->data['mode'] != 'preview' )
        {
            $sql .= ' AND (o.start_date IS NULL OR o.start_date <= UTC_TIMESTAMP())';
        }
        $sql .= ' ORDER BY order_index';
        if ( !$is_index )
        {
            $sql .= ' LIMIT 0, 3';
        }
        $sql = strtr( $sql, array_map(array($conn, 'escape'), array(
            ':domain' => $module->pg_type,
            ':locale' => $module->kernel->request['locale'],
            ':alias' => $alias
        )) );

        $rows = $conn->getAll($sql);
        if(count($rows) > 0) {
            $module->page_found = true;
            if($module->data['mode'] == 'preview') {
                $module->_wrap = false;
            }

            $category_report_groups = array(
                'rooms' => 'Rooms',
                'dining' => 'Dining',
                'meetings' => 'Meetings & Events',
                'weddings' => 'Weddings',
                'spa' => 'Spa',
                'others' => 'Others'
            );

            if(count($segments) === 0) {
                // Parse category parameter
                $category = trim(array_ifnull($_GET, 'category', ''));

                // offer list
                $offers = array();
                $offer_ids = array();
                $a = 0;
                foreach($rows as $row)
				{
                    //Check category
                    $sql = 'SELECT * FROM offer_categories oc JOIN categories c ON (c.category_id=oc.category_id)
                    WHERE oc.domain='.$conn->escape($module->pg_type).' AND oc.offer_id='.$row['id'].' AND c.locale='.$conn->escape($module->kernel->request['locale']);
                    if($category != '')
                        $sql .= ' AND c.alias='.$conn->escape($category);
                    $results = $conn->getAll($sql);
                    if(count($results)>0)
                    {
                        $offers[$a] = array(
                            'id' => $row['id'],
                            'type' => $row['type'],
                            'title' => $row['title'],
                            'img_url' => $row['img_url'],
                            'url' => $row['url'] == '' ? $module->kernel->sets['paths']['app_from_doc'] . '/' . $module->kernel->request['locale']
                            . $url . $row['alias'] .'/' : $row['url'],
                            'target' => $row['target'],
                            //'featured_text' => $row['featured_text'],
                            'action_text' => $row['action_text'],
                            'action_url' => $row['action_url'],
                            'video_url' => $row['video_url'],
                            'period_from' => $row['period_from'],
                            'period_to' => $row['period_to'],
                            'price' => $row['price'],
                            'description' => $row['short_description'],
                            //'display_text' => $row['display_text'],
                            'action_url_target' => $row['action_url_target'],
                            //'featured_page' => $row['featured_page'],
                            'content' => $row['content'],
                            'track_ctc' => 1,
                            'categories' => array(),
                            'category_str' => array(),
                            'report_group' => ''
                        );

                        if($offers[$a]['period_from'] != '')
                        {
                            $offers[$a]['period_from']= date('Y-m-d', strtotime($offers[$a]['period_from']));
                            //$from_year = date('Y', strtotime($offers[$a]['period_from']));
                        }
                        if($offers[$a]['period_to'] != '')
                        {
                            $offers[$a]['period_to']= date('Y-m-d', strtotime($offers[$a]['period_to']));
                            //$to_year = date('Y', strtotime($offers[$a]['period_to']));
                        }

                        $report_groups = array();
                        foreach($results as $r)
                        {
                            $offers[$a]['categories'][] = $r;
                            $offers[$a]['category_str'][] = $r['name'];
                            $report_groups[] = array_ifnull($category_report_groups, $r['alias'], 'Others');
                        }
                        $offers[$a]['category_str'] = implode(', ', $offers[$a]['category_str']);
                        $offers[$a]['report_group'] = current(array_intersect($category_report_groups, $report_groups));
                    }

					$offer_ids[] = $row['id'];
                    $a++;
				}

                //get category list
                $category = array();
                $sql = 'SELECT DISTINCT categories.category_id, categories.alias, categories.name FROM categories JOIN offer_categories on (offer_categories.category_id = categories.category_id AND categories.locale='.$conn->escape($module->kernel->request['locale']).') WHERE offer_categories.offer_id IN ('.implode(',', $offer_ids).') GROUP by categories.category_id ORDER BY categories.category_id';
                $rows = $conn->getAll($sql);
                if(count($rows)>0){
                    $category[''] = array_ifnull( $k->dict, 'LABEL_all', 'All' );
                }
                foreach($rows as $row) {
                    $category[$row['alias']]=$row['name'];
                }

                $module->kernel->smarty->assign('category', $category);
                $module->kernel->smarty->assign('offer_list', $offers);
                $module->kernel->smarty->assign('is_index', $is_index);
                $module->kernel->smarty->assign('pg', $page);
                return $module->kernel->smarty->fetch("file/snippet/{$snippet['id']}/index.html");

            } else {
				$Is_detail = true;

                $fields = $rows[0];

				if($fields['period_from'] != '')
					$fields['period_from'] = date('Y-m-d', strtotime($fields['period_from']));
				if($fields['period_to'] != '')
					$fields['period_to']= date('Y-m-d', strtotime($fields['period_to']));

                $module->current_url = $module->current_url . $alias . '/';

                //$prev_title = $page->getTitle($module->kernel->request['locale']);

                $page->setHeadlineTitle(array(
                    $module->kernel->request['locale'] => $fields['title']
                ));
                $module->breadcrumb->push(new breadcrumbNode(
                    $fields['seo_title'] ? $fields['seo_title'] : $fields['title'], $module->current_url
                ));

                $page->setKeywords(array(
                    $module->kernel->request['locale'] => $fields['keywords']
                ));
                $page->setDescription(array(
                    $module->kernel->request['locale'] => $fields['description']
                ));

                // get alternate urls for each platform
                $sql = sprintf('SELECT p.* FROM webpage_platforms p WHERE p.domain = %s AND p.webpage_id = %d AND p.platform NOT IN(%s)'
                    . ' AND p.major_version = %d AND p.minor_version = %d AND deleted = 0'
                    , $conn->escape($module->pg_type)
                    , $page->getId()
                    , implode(', ', array_map(array($conn, 'escape'), array($module->platform)))
                    , $page->getMajorVersion(), $page->getMinorVersion());
                $rows = $conn->getAll($sql);
                foreach($rows as $row) {
                    $page->setAlternateUrl($row['platform'], $module->data['webpage']['path']);
                    $module->alternate_urls[$row['platform']] = $module->data['webpage']['path'];
                }

				// get banners
                $sql = 'SELECT * FROM offer_locale_banners WHERE domain = ' . $conn->escape($module->pg_type);
                $sql .= ' AND offer_id = ' . $fields['id'];
                $sql .= ' AND locale = ' . $conn->escape($module->kernel->request['locale']);
                $page->setBanners( array(
                    $module->kernel->request['locale'] => $conn->getAll( $sql )
                ) );

                // get menus
                $sql = 'SELECT * FROM offer_locale_menus WHERE domain = ' . $conn->escape($module->pg_type);
                $sql .= ' AND offer_id = ' . $fields['id'];
                $sql .= ' AND locale = ' . $conn->escape($module->kernel->request['locale']);
                $fields['menus'] = $conn->getAll( $sql );

                // get languages
                $sql = sprintf('SELECT DISTINCT locale FROM offer_locales WHERE domain = %1$s AND offer_id = %2$d', $conn->escape($module->pg_type), $fields['id']);
                $tmp = $conn->getAll($sql);
                $available_locales = array();
                foreach($tmp as $tmp_row) {
                    $available_locales[] = $tmp_row['locale'];
                }

                $pg_locales = $page->getLocales();

                foreach($pg_locales as $i => $locale) {
                    if(!in_array($locale, $available_locales)) {
                        unset($pg_locales[$i]);
                    }
                }

                $page->setLocales($pg_locales);

                // if room offers then track click ==> all offers will be tracked now(Nov 19th, 2015)
                $fields['track_ctc'] = 1;
                //$sql = sprintf('SELECT category_id FROM offer_categories WHERE domain=%1$s AND offer_id=%2$d',
                //$conn->escape($module->pg_type),
                //$rows[0]['id']);
                $fields['category_str'] = '';
                $sql = sprintf('SELECT c.name, c.alias FROM offer_categories oc LEFT JOIN categories c ON (oc.category_id=c.category_id) WHERE oc.domain=%1$s AND oc.offer_id=%2$d AND c.locale=%3$s',
                $conn->escape($module->pg_type),
                $fields['id'],
                $conn->escape('en'));
                $res = $conn->getAll($sql);
                $tmp_cats = array();
                $report_groups = array();
                foreach($res as $re)
                {
                    $tmp_cats[] = $re['name'];
                    $report_groups[] = array_ifnull($category_report_groups, $re['alias'], 'Others');
                }
                $fields['category_str'] = implode(', ', $tmp_cats);
                unset($tmp_cats);
                $fields['report_group'] = current(array_intersect($category_report_groups, $report_groups));
                /*if(count($res) > 0) {
                    foreach($res as $re)
                    {
                        if($re['category_id']==1)
                            $fields['track_ctc'] = 1;
                    }
                }*/

                //return $fields['content'];
				$module->kernel->smarty->assign('offer_detail', $fields);
				$module->kernel->smarty->assign('requested_locale', $module->kernel->request['locale']);
				$module->kernel->smarty->assign('pg', $page);
                return $module->kernel->smarty->fetch("file/snippet/{$snippet['id']}/offer_detail.html");
            }
        }
    }

}

?>