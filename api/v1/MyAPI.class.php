<?php 
	require_once 'API.class.php';
	
	function convert_tz( $dt, $from_tz, $to_tz )
	{
		if ( $dt )
		{
			$datetime = new DateTime( $dt, new DateTimeZone($from_tz) );
			$timezone = new DateTimeZone( $to_tz );
			return date( 'Y-m-d H:i:s', $datetime->format('U') + $timezone->getOffset($datetime) );
		}
		return NULL;
	}
	
	class MyAPI extends API
	{
		protected $User;
		protected $db;
		protected $conf;

		public function __construct($request, $origin) {
			parent::__construct($request);

			$APP_ROOT = dirname(dirname(dirname( __FILE__ )));
			require_once "$APP_ROOT/conf/config.php";
			
			// Connect to database
			$this->db = mysqli_connect($CONF['db_host'],$CONF['db_user'],$CONF['db_pass'],$CONF['db_schema']);
			if (mysqli_connect_errno()) {
				return "Failed to connect to MySQL: " . mysqli_connect_error();
			}
			mysqli_query($this->db, 'SET CHARACTER SET utf8' );
			mysqli_query($this->db, "SET NAMES 'utf8'" );
			
			// Abstracted out for example
			$APIKey = $CONF['apikey'];
			$User = $CONF['apiuser'];

			if (!array_key_exists('apiKey', $this->request)) {
				throw new Exception('No API Key provided');
			//} else if (!$APIKey->verifyKey($this->request['apiKey'], $origin)) {
			} else if ($APIKey!=$this->request['apiKey'] || $User != $this->request['apiUser']) {
				throw new Exception('Invalid API Key');
			} /*else if (array_key_exists('token', $this->request) &&
				 !$User->get('token', $this->request['token'])) {

				throw new Exception('Invalid User Token');
			}*/

			$this->User = $User;
			$this->conf = $CONF;
		}

		/**
		 * Example of an Endpoint
		 */
		 protected function example() {
			if ($this->method == 'GET') {
				return "Your name is " . $this->User;
			} else {
				return "Only accepts GET requests";
			}
		 }
		 
		 protected function offer_get(){
			 $offers_data = array(
				'offers' => array(),
				'offer_locales' => array(),
				'offer_categories' => array(),
				'offer_deleted' => array()
			 );
			 $offer_ids = array();
			 
			 $sql = 'SELECT * FROM offers WHERE domain="public"';
			 if($this->request['mode'] == 'last_updated_only')
			 {
				$now = convert_tz( 'now', 'gmt', $this->conf['timezone'] );
				$sql .= ' AND (DATE(CONVERT_TZ(created_date, "gmt", "'.$this->conf['timezone'].'"))>=DATE(DATE_ADD("'.$now.'", INTERVAL -2 DAY)) OR DATE(CONVERT_TZ(updated_date, "gmt", "'.$this->conf['timezone'].'"))>=DATE(DATE_ADD("'.$now.'", INTERVAL -2 DAY)))';
			 }

			 $result = mysqli_query($this->db, $sql);
			 while ( $row = mysqli_fetch_array($result,MYSQLI_ASSOC) ){
				$offers_data['offers'][] = $row;
				$offer_ids[] = $row['id'];
			 }
			 
			 $sql = 'SELECT * FROM offer_locales WHERE offer_id IN ('.implode(',', $offer_ids).') AND domain="public"';
			 $result = mysqli_query($this->db, $sql);
			 while ( $row = mysqli_fetch_array($result,MYSQLI_ASSOC) ){
				$offers_data['offer_locales'][] = $row;
			 }
			 
			 $sql = 'SELECT * FROM offer_categories WHERE offer_id IN ('.implode(',', $offer_ids).') AND domain="public"';
			 $result = mysqli_query($this->db, $sql);
			 while ( $row = mysqli_fetch_array($result,MYSQLI_ASSOC) ){
				$offers_data['offer_categories'][] = $row;
			 }
			 
			 $sql = 'SELECT * FROM offers WHERE domain="private" AND deleted=1 AND status="approved"';
			 if($this->request['mode'] == 'last_updated_only')
			 {
				$now = convert_tz( 'now', 'gmt', $this->conf['timezone'] );
				$sql .= ' AND (DATE(CONVERT_TZ(created_date, "gmt", "'.$this->conf['timezone'].'"))>=DATE(DATE_ADD("'.$now.'", INTERVAL -2 DAY)) OR DATE(CONVERT_TZ(updated_date, "gmt", "'.$this->conf['timezone'].'"))>=DATE(DATE_ADD("'.$now.'", INTERVAL -2 DAY)))';
			 }

			 $result = mysqli_query($this->db, $sql);
			 while ( $row = mysqli_fetch_array($result,MYSQLI_ASSOC) ){
				$offers_data['offer_deleted'][] = $row['id'];
			 }
			 
			 return $offers_data;
		 }
	 }
?>