<?php 

/**
 * Export data from Hong Kong
 * @author  Martin Ng <martin@avalade.com>
 * @since   2020-11-27
 */

require __DIR__."/../conf/db.php";

class export_hk
{
    /**
     * Constructor
     *
     * @since   2020-11-27
     */

    public function __construct()
    {
        $db = new DB();
        $this->db = $db->getConnection();
    }

    /**
     * Generate routes in JSON format
     *
     * @since   2020-11-27
     */
    public function generate_all()
    {
        // Use try and catch to capture SQL errors during development
        try
        {
            // Get the railway lines
            $railway_lines = array();
            $sql = "SELECT l.id, GROUP_CONCAT(ll.locale ORDER BY ll.locale <> 'en' SEPARATOR '\r\n') AS locales,";
            $sql .= " GROUP_CONCAT(ll.name ORDER BY ll.locale <> 'en' SEPARATOR '\r\n') AS names, l.type, l.deleted";
            $sql .= ' FROM railway_lines AS l';
            $sql .= ' JOIN railway_line_locales AS ll ON (l.id = ll.railway_line_id)';
            $sql .= ' LEFT OUTER JOIN railway_line_stops AS ls ON (l.id = ls.railway_line_id)';
            $sql .= " WHERE l.city = 'hk'";
            $sql .= ' GROUP BY l.id';
            $sql .= ' ORDER BY l.type, names, l.code';
            foreach ( $this->db->query($sql) as $line )
            {
                $id = array_shift( $line );
                $locales = explode( "\r\n", array_shift($line) );
                $names = explode( "\r\n", array_shift($line) );
                foreach ( $locales as $i => $locale )
                {
                    $name = $names[$i];
                    $line['locales'][$locale] = compact( 'name' );
                }
                $line['deleted'] = !!$line['deleted'];
                $line['stops'] = array();
                $railway_lines[$id] = $line;
            }

            // Get the railway line stops
            $sql = 'SELECT l.id, ls.stop_id FROM railway_lines AS l';
            $sql .= ' JOIN railway_line_stops AS ls ON (l.id = ls.railway_line_id)';
            $sql .= " WHERE l.city = 'hk'";
            foreach ( $this->db->query($sql) as $stop )
            {
                extract( $stop );
                $railway_lines[$id]['stops'][] = intval( $stop_id );
            }

            // Get the route chart fares
            $railway_charts = array();
            $sql = "SELECT c.type, c.id, CONVERT_TZ(c.effective_date, 'GMT', 'Asia/Hong_Kong') AS effective_date,";
            $sql .= ' c.deleted, cf.on_stop_id, cf.off_stop_id, cf.price';
            $sql .= ' FROM railway_charts AS c';
            $sql .= ' LEFT OUTER JOIN railway_chart_fares AS cf ON (c.id = cf.railway_chart_id)';
            $sql .= " WHERE c.city = 'hk'";
            $sql .= ' ORDER BY c.effective_date, cf.on_stop_id, cf.off_stop_id';
            foreach ( $this->db->query($sql) as $fare )
            {
                extract( $fare );
                $railway_charts[$type][$id]['effective_date'] = $effective_date;
                $railway_charts[$type][$id]['deleted'] = !!$deleted;
                if ( is_null($price) )
                {
                    $railway_charts[$type][$id]['fares'] = array();
                }
                else
                {
                    $railway_charts[$type][$id]['fares'][$on_stop_id][$off_stop_id] = floatval( $price );
                }
            }

            // Get the routes
            $routes = array();
            $sql = "SELECT * FROM (SELECT r.id, GROUP_CONCAT(rl.locale ORDER BY rl.locale <> 'en' SEPARATOR '\r\n') AS locales,";
            $sql .= " GROUP_CONCAT(rl.name ORDER BY rl.locale <> 'en' SEPARATOR '\r\n') AS names,";
            $sql .= " GROUP_CONCAT(rl.origin_name ORDER BY rl.locale <> 'en' SEPARATOR '\r\n') AS origin_names,";
            $sql .= " GROUP_CONCAT(rl.destination_name ORDER BY rl.locale <> 'en' SEPARATOR '\r\n') AS destination_names,";
            $sql .= ' r.type, r.district, r.company, r.direction, r.operating_hours, r.special_departures, r.code, r.deleted';
            $sql .= ' FROM routes AS r';
            $sql .= ' JOIN route_locales AS rl ON (r.id = rl.route_id)';
            $sql .= " WHERE r.city = 'hk'";
            $sql .= ' GROUP BY r.id) AS t';
            $sql .= " ORDER BY type, district, company, names NOT REGEXP '^[0-9]', names + 0, names,";
            $sql .= " FIND_IN_SET(direction, 'two-way,circular,one-way'), ";
            $sql .= " FIND_IN_SET(operating_hours, '24-hour,day,night'), special_departures, code";
            foreach ( $this->db->query($sql) as $route )
            {
                unset( $route['code'] );
                $id = array_shift( $route );
                $locales = explode( "\r\n", array_shift($route) );
                $names = explode( "\r\n", array_shift($route) );
                $origin_names = explode( "\r\n", array_shift($route) );
                $destination_names = explode( "\r\n", array_shift($route) );
                foreach ( $locales as $i => $locale )
                {
                    $name = $names[$i];
                    $origin_name = $origin_names[$i];
                    $destination_name = $destination_names[$i];
                    $route['locales'][$locale] = compact( 'name', 'origin_name', 'destination_name' );
                }
                $route['deleted'] = !!$route['deleted'];
                $routes[$id] = $route;
            }

            // Get the route chart fares
            $sql = "SELECT r.id, rc.id AS chart_id, CONVERT_TZ(rc.effective_date, 'GMT', 'Asia/Hong_Kong') AS effective_date,";
            $sql .= ' rc.deleted, rcf.bound, rcf.on_stop_id, rcf.off_stop_id, rcf.on_seq, rcf.off_seq, rcf.price';
            $sql .= ' FROM routes AS r';
            $sql .= ' JOIN route_charts AS rc ON (r.id = rc.route_id)';
            $sql .= ' LEFT OUTER JOIN route_chart_fares AS rcf ON (rc.id = rcf.route_chart_id)';
            $sql .= " WHERE r.city = 'hk'";
            $sql .= ' ORDER BY rc.effective_date, rcf.bound, rcf.on_seq, rcf.off_seq';
            foreach ( $this->db->query($sql) as $fare )
            {
                extract( $fare );
                $routes[$id]['charts'][$chart_id]['effective_date'] = $effective_date;
                $routes[$id]['charts'][$chart_id]['deleted'] = !!$deleted;
                if ( is_null($price) )
                {
                    $routes[$id]['charts'][$chart_id]['bounds'] = array();
                }
                else
                {
                    $routes[$id]['charts'][$chart_id]['bounds'][$bound]["$on_seq:$on_stop_id"]["$off_seq:$off_stop_id"] = floatval( $price );
                }
            }

            // Get the stops
            $stops = array();
            $sql = "SELECT s.id, GROUP_CONCAT(sl.locale ORDER BY sl.locale <> 'en' SEPARATOR '\r\n') AS locales,";
            $sql .= " GROUP_CONCAT(sl.name ORDER BY sl.locale <> 'en' SEPARATOR '\r\n') AS names, s.type, s.deleted";
            $sql .= ' FROM stops AS s';
            $sql .= ' JOIN stop_locales AS sl ON (s.id = sl.stop_id)';
            $sql .= " WHERE s.city = 'hk'";
            $sql .= ' GROUP BY s.id';
            $sql .= ' ORDER BY s.type, names, s.code';
            foreach ( $this->db->query($sql) as $stop )
            {
                $id = array_shift( $stop );
                $locales = explode( "\r\n", array_shift($stop) );
                $names = explode( "\r\n", array_shift($stop) );
                foreach ( $locales as $i => $locale )
                {
                    $name = $names[$i];
                    $stop['locales'][$locale] = compact( 'name' );
                }
                $stop['deleted'] = !!$route['deleted'];
                $stops[$id] = $stop;
            }


            http_response_code(200);
            echo json_encode(
                array(
                'data'=> compact('railway_lines', 'railway_charts', 'routes', 'stops')
                )
            );
        }
        catch ( PDOException $e )
        {
            echo 'DB Error: ';
            print_r( $e->getTrace() );

            http_response_code(404);
            exit();
        }
    }

    public function generate_update($date){
        //TODO: select update base on date

        echo json_encode($date);
    }

}

?>