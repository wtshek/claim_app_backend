<?php

// Create instance
require_once( dirname(__FILE__) . '/kernel.php' );
$instance = new kernel( 'admin_site', 'en/' );

// Get the requested URLs
$urls = $paths = array();
$offer_path = '';
$max_level = 0;

// Get the requested URLs from webpages
$sql = <<<EOT
SELECT w.id, wp.path, MAX(wl.updated_date) AS updated_date,
GROUP_CONCAT(wl.locale ORDER BY l.default DESC, l.order_index ASC) AS locales,
IF(SUM(wlc.content IS NULL OR wlc.content = '') = COUNT(wl.locale), 0, 1) AS has_contents
FROM webpages AS w
JOIN locales AS l ON (l.site = 'public_site' AND l.enabled = 1)
JOIN webpage_locales AS wl ON (w.domain = wl.domain AND w.id = wl.webpage_id AND l.alias = wl.locale)
JOIN webpage_locale_contents AS wlc ON (wl.domain = wlc.domain AND wl.webpage_id = wlc.webpage_id AND wl.locale = wlc.locale)
JOIN webpage_platforms AS wp ON (w.domain = wp.domain AND w.id = wp.webpage_id AND wp.platform = 'desktop')
LEFT OUTER JOIN webpage_permissions AS wr ON (w.domain = wr.domain AND w.id = wr.webpage_id AND wr.role_id <> 1)
WHERE w.domain = 'public'
AND w.type IN('static', 'structured_page')
AND w.deleted = 0
AND UTC_TIMESTAMP() BETWEEN IFNULL(wl.publish_date, UTC_TIMESTAMP()) AND IFNULL(wl.removal_date, UTC_TIMESTAMP())
AND wp.shown_in_sitemap = 1
AND wr.role_id IS NULL
GROUP BY w.id
ORDER BY wp.path
EOT;
foreach ( $instance->db->query($sql) as $url )
{
    $parent_path = rtrim( dirname($url['path']), '/' ) . '/';
    if ( $url['path'] == '/' || array_key_exists($parent_path, $urls) )
    {
        $url['locales'] = explode( ',', $url['locales'] );
        $url['has_children'] = FALSE;
        $urls[$url['path']] = $url;

        if ( $url['path'] !== '/' )
        {
            $urls[$parent_path]['has_children'] = TRUE;
        }

        if ( $url['id'] == $instance->conf['offer_webpage_id'] )
        {
            $offer_path = $url['path'];
        }

        $max_level = max( $max_level, substr_count($url['path'], '/') - 1 );
    }
    $paths[$url['id']] = $url['path'];
}

// Get the requested URLs from offers
if ( $offer_path !== '' )
{
    $sql = <<<EOT
    SELECT o.alias, IFNULL(o.updated_date, o.created_date) AS updated_date,
    GROUP_CONCAT(ol.locale ORDER BY l.default DESC, l.order_index ASC) AS locales,
    IF(SUM(ol.content IS NULL OR ol.content = '') = COUNT(ol.locale), 0, 1) AS has_contents
    FROM offers AS o
    JOIN locales AS l ON (l.site = 'public_site' AND l.enabled = 1)
    JOIN offer_locales AS ol ON (o.domain = ol.domain AND o.id = ol.offer_id AND l.alias = ol.locale)
    WHERE o.domain = 'public'
    AND o.type = 'page'
    AND o.deleted = 0
    AND UTC_TIMESTAMP() BETWEEN IFNULL(o.start_date, UTC_TIMESTAMP()) AND IFNULL(o.end_date, UTC_TIMESTAMP())
    GROUP BY o.id
    ORDER BY o.order_index;
    EOT;
    foreach ( $instance->db->query($sql) as $url )
    {
        $path = $offer_path . $url['alias'] . '/';
        $url['locales'] = explode( ',', $url['locales'] );
        $url['has_children'] = FALSE;
        $urls[$path] = $url;
    }
    $max_level = max( $max_level, substr_count($offer_path, '/') );
}

// Get the press release webpages
$press_release_webpages = array();
$sql = <<<EOT
SELECT ws.webpage_id AS id, GROUP_CONCAT(ws.webpage_locale) AS locales
FROM snippets AS s
JOIN customize_snippets AS cs ON (s.id = cs.snippet_type_id)
JOIN webpage_snippets AS ws ON (cs.id = ws.snippet_id)
WHERE s.alias = 'press_release'
GROUP BY ws.webpage_id
EOT;
foreach ( $instance->db->query($sql) as $webpage )
{
    if ( array_key_exists($webpage['id'], $paths) )
    {
        $webpage['locales'] = explode( ',', $webpage['locales'] );
        $press_release_webpages[$paths[$webpage['id']]] = $webpage;
    }
}

// Get the requested URLs from press releases
if ( count($press_release_webpages) > 0 )
{
    $sql = <<<EOT
    SELECT p.id, IFNULL(p.updated_date, p.created_date) AS updated_date,
    GROUP_CONCAT(pl.locale ORDER BY l.default DESC, l.order_index ASC) AS locales,
    IF(SUM(pl.content IS NULL OR pl.content = '') = COUNT(pl.locale), 0, 1) AS has_contents
    FROM press_releases AS p
    JOIN locales AS l ON (l.site = 'public_site' AND l.enabled = 1)
    JOIN press_release_locales AS pl ON (p.domain = pl.domain AND p.id = pl.press_release_id AND l.alias = pl.locale)
    WHERE p.domain = 'public'
    AND p.deleted = 0
    AND UTC_TIMESTAMP() BETWEEN IFNULL(p.start_date, UTC_TIMESTAMP()) AND IFNULL(p.end_date, UTC_TIMESTAMP())
    GROUP BY p.release_date DESC, p.start_date DESC, p.end_date DESC, p.id DESC
    EOT;
    foreach ( $instance->db->query($sql) as $url )
    {
        $url['locales'] = explode( ',', $url['locales'] );
        $url['has_children'] = FALSE;
        foreach ( $press_release_webpages as $path => $webpage )
        {
            $common_locales = array_intersect( $url['locales'], $webpage['locales'] );
            if ( count($common_locales) > 0 )
            {
                $path = $path . $url['id'] . '/';
                $common_url = $url;
                $common_url['locales'] = $common_locales;
                $urls[$path] = $common_url;
            }
        }
    }
    foreach ( $press_release_webpages as $path => $webpage )
    {
        $max_level = max( $max_level, substr_count($path, '/') );
    }
}

// Generate XML
$site_url = $instance->sets['paths']['server_url'] . $instance->sets['paths']['app_from_doc'] . '/';
header( 'Content-Type: application/xml; charset=utf-8' );
header( 'Content-Disposition: filename="sitemap.xml"' );
echo <<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
EOT;

foreach ( $urls as $path => $urls )
{
    if ( $url['has_contents'] || !$url['has_children'] )
    {
        echo '<url>';
        echo '<loc>' . htmlspecialchars( $site_url . reset($url['locales']) . $path ). '</loc>';
        echo '<lastmod>' . htmlspecialchars( str_replace(' ', 'T', $url['updated_date']) . 'Z' ) . '</lastmod>';
        if ( $max_level > 0 )
        {
            echo '<priority>' . round( ($max_level - substr_count($path, '/') + 1) / $max_level, 2 ) . '</priority>';
        }
        foreach ( $url['locales'] as $locale )
        {
            $locale_parts = explode( '-', $locale );
            if ( count($locale_parts) > 1 )
            {
                // 2-letter country code
                // https://en.wikipedia.org/wiki/ISO_3166-1_alpha-2
                if ( strlen($locale_parts[1]) == 2 )
                {
                    $locale_parts[1] = strtoupper( $locale_parts[1] );
                }

                // 4-letter script code
                // http://unicode.org/iso15924/iso15924-codes.html
                else
                {
                    $locale_parts[1] = ucfirst( strtolower($locale_parts[1]) );
                }
            }
            printf(
                '<xhtml:link rel="alternate" hreflang="%s" href="%s"/>',
                htmlspecialchars( implode('-', $locale_parts) ),
                htmlspecialchars( $site_url . $locale . $path )
            );
        }
        echo '</url>';
    }
}
echo '</urlset>';

// Close instance
$instance->close();
