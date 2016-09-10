<?php
define('LOG4PHP_CONFIGURATION', '../log4php.properties'); 
require('plugin_manager.php');
require('plugin.php');
require('../log4php/LoggerManager.php');


class Archive {

	var $pluginManager = null;
	var $settings = null;
	var $logger;
    
	function Archive(){
        $this->logger =& LoggerManager::getLogger('Archive');
		error_reporting(0);
		//set_error_handler (array (&$this, 'handleError'));
		set_exception_handler(array (&$this, 'exceptionHandler'));
    }
    
	function initialize() {
		$this->settings = parse_ini_file('settings.ini', true);
		$this->logger->debug("Settings ".print_r($this->settings, true));
		$this->initializePlugins();
	}

	function initiliazeClient() {

		$client = curl_init();

		/*$archive_debug_log = TMP."archive".DS."archive-".$this->group_name.".txt";
		$this->debug_log_file = fopen ($archive_debug_log, "w");

		if (!$this->debug_log_file) {
			die("Unable to open ".$archive_debug_log. " for writing.\n");
		}

		curl_setopt ($client, CURLOPT_COOKIEJAR, $archive_cookie_file);
		curl_setopt ($client, CURLOPT_COOKIEFILE, $archive_cookie_file);
		curl_setopt($client, CURLOPT_STDERR, $this->debug_log_file);
		curl_setopt ($client, CURLOPT_FILE, $this->debug_log_file);*/
		// set URL and other appropriate options
		$archive_cookie_file = "cookie.txt";
		curl_setopt ($client, CURLOPT_COOKIEJAR, $archive_cookie_file);
		curl_setopt ($client, CURLOPT_COOKIEFILE, $archive_cookie_file);

		curl_setopt($client, CURLOPT_FAILONERROR, TRUE);
		curl_setopt($client, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($client, CURLOPT_FOLLOWLOCATION, TRUE);
		curl_setopt($client, CURLOPT_HEADER, FALSE);
		curl_setopt($client, CURLOPT_CONNECTTIMEOUT, 30); //30 seconds
		//curl_setopt($client, CURLOPT_VERBOSE, TRUE);

		// define CGI VARIABLE if behind proxy
		//if (array_key_exists('ARC_PROXY_SERVER', $_SERVER)) {
		//	curl_setopt($client, CURLOPT_PROXY, "67.69.254.243:80");
			//curl_setopt($client, CURLOPT_PROXYUSERPWD, "tveerasa:Covisint%8002");
		//}
		curl_setopt($client, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-GB; rv:1.8.1.1) Gecko/20061204 Firefox/2.0.0.1");
		return $client;
	}

	function closeClient($client) {
		// close CURL resource, and free up system resources
		curl_close($client);
	}

	function logon($client) {
		$this->logger->debug("Logging into Yahoo Groups");
		$login_string = "login=ps.archive&passwd=ps12345&.src=ygrp&.done=http://groups.yahoo.com";

		curl_setopt($client, CURLOPT_URL, "https://login.yahoo.com/config/login");
		curl_setopt($client, CURLOPT_POST, TRUE);
		curl_setopt($client, CURLOPT_POSTFIELDS, $login_string);
		// grab URL and pass it to the browser
		$result = curl_exec($client);
		$status = curl_errno($client);
		if ($status != CURLE_OK) {
			$this->logger->fatal("Couldn't login into Yahoo Groups".curl_errno($client)." ".curl_error ($client));
			$this->logger->fatal("Couldn't logon ".curl_errno($client));
			$this->logger->fatal("Couldn't logon ".curl_error ($client));
		} else {
			$this->logger->debug("Successfully logged into Yahoo Groups");
		}
		return ($status == CURLE_OK);
	}

	function logout($client) {
		$this->logger->debug("Logging out from Yahoo Groups");
		curl_setopt ($client, CURLOPT_URL, "http://login.yahoo.com/config/login?logout=1&.partner=&.intl=us&.done=http%3a%2f%2fmy.yahoo.com%2findex.html&.src=my");
		$result = curl_exec($client);
		$status = curl_errno($client);
		if ($status != CURLE_OK) {
			$this->logger->fatal("Couldn't logout ".curl_errno($client));
			$this->logger->fatal("Couldn't logout ".curl_error ($client));
		}
		return ($status == CURLE_OK);
	}

	function getPage($client, $uri) {

		$this->logger->debug("Getting page ".$uri);
		curl_setopt ($client, CURLOPT_URL, $uri);
		$result = curl_exec($client);
		$status = curl_errno($client);
		if ($status != CURLE_OK) {
			$this->logger->error("Couldn't get Uri ".$uri." ==> ".curl_errno($client)." ".curl_error ($client));
			$this->logger->fatal("Couldn't get Uri ".$uri." ==> ".curl_errno($client));
			$this->logger->fatal(curl_error ($client));
			$result = null;
		}
		return $result;
	}

	function getMessage($client, $message_no) {
		$this->logger->debug("Get Message ".$message_no);
		$message_uri = 'http://groups.yahoo.com/group/ponniyinselvan/message/'.$message_no.'/?source=1&var=1&l=1';
		return $this->getPage($client, $message_uri);
	}

	function initializePlugins() {
		$this->pluginManager = new PluginManager();
		$this->logger->debug("Initializing Plugin Manager & Loading Plugins from plugins folder");
		$this->pluginManager->loadPlugins('./plugins/', $this->settings);
	}
	
	function shutdown() {
		$this->logger->debug("Shutting down Plugin Manager & Plugins");
		$this->pluginManager->shutdown();
		unset($this->pluginManager);
		unset($this->settings);
		restore_error_handler();
		restore_exception_handler();
	}
	
	function getMessageNoDetails($sourceHtml) {

		$dom = new DOMDocument();
		@$dom->loadHTML($sourceHtml);
		$xpath = new DOMXPath($dom);
	    $message_no = 0;
	    $prev_message_no = 0;
	    $next_message_no = 0;

	    $msg_td = $xpath->query('//table[@class="footaction"]//td[@align="right"]');
	    if ($msg_td->length > 0) {
		   $td_element = $msg_td->item(0);
	
		   $doc = new DOMDocument();
		   $doc->preserveWhiteSpace = true;
		   $doc->appendChild($doc->importNode($td_element,true));
			
		   preg_match("/[0-9]+/", $td_element->textContent, $matches, PREG_OFFSET_CAPTURE);
		   if (count($matches) > 0) {
	   	       $message_no = intval($matches[0][0]);
	       } else {
			   $this->logger->error("Couldn't find Message No through regular expression");
			   $this->logger->debug("Message No. html ".$doc->saveHTML());
			   $this->logger->debug("Message No. html ".$td_element->textContent);
		   }

		   // Next Prev
		   $nav_hrefs = $td_element->getElementsByTagName("a");
		   for ($j = 0 ; $j < $nav_hrefs->length ; $j++) {
		       preg_match("/[0-9]+/", $nav_hrefs->item($j)->getAttribute("href"), $matches, PREG_OFFSET_CAPTURE);
		       if (strstr($nav_hrefs->item($j)->textContent, "Prev")) {
			       $prev_message_no = intval($matches[0][0]);
		       } else if (strstr($nav_hrefs->item($j)->textContent, "Next")) {
			       $next_message_no = intval($matches[0][0]);
		       }
		   }
	     } else {
		   $this->logger->error("Couldn't get Message No through xpath query");
		   $this->logger->error("The Source HTML ".$sourceHtml);
		 }
		 return array('message_no' => $message_no, 
		                  'prev_message_no' => $prev_message_no, 
						  'next_message_no' => $next_message_no);
	}

	function exceptionHandler($exception) {
		$exceptionString = $exception->getMessage()."\n";
		$exceptionString = $exceptionString.$exception->getTraceAsString();
	    echo $exceptionString;
		$this->logger->error($exceptionString);
		$this->shutdown();
		die("Exception occurred, stopping archive process");
	}	

	function handleError($errno, $errmsg, $filename, $linenum, $vars) {
	    // timestamp for the error entry
	    $dt = date("Y-m-d H:i:s (T)");

	    // define an assoc array of error string
	    // in reality the only entries we should
	    // consider are E_WARNING, E_NOTICE, E_USER_ERROR,
	    // E_USER_WARNING and E_USER_NOTICE
	    $errortype = array (
	                E_ERROR              => 'Error',
	                E_WARNING            => 'Warning',
	                E_PARSE              => 'Parsing Error',
	                E_NOTICE             => 'Notice',
	                E_CORE_ERROR         => 'Core Error',
	                E_CORE_WARNING       => 'Core Warning',
	                E_COMPILE_ERROR      => 'Compile Error',
	                E_COMPILE_WARNING    => 'Compile Warning',
	                E_USER_ERROR         => 'User Error',
	                E_USER_WARNING       => 'User Warning',
	                E_USER_NOTICE        => 'User Notice',
	                E_STRICT             => 'Runtime Notice',
	                E_RECOVERABLE_ERROR  => 'Catchable Fatal Error'
	                );
	    // set of errors for which a var trace will be saved
	    $user_errors = array(E_USER_ERROR, E_USER_WARNING, E_USER_NOTICE);
	    
	    $err = "<errorentry>\n";
	    $err .= "\t<datetime>" . $dt . "</datetime>\n";
	    $err .= "\t<errornum>" . $errno . "</errornum>\n";
	    $err .= "\t<errortype>" . $errortype[$errno] . "</errortype>\n";
	    $err .= "\t<errormsg>" . $errmsg . "</errormsg>\n";
	    $err .= "\t<scriptname>" . $filename . "</scriptname>\n";
	    $err .= "\t<scriptlinenum>" . $linenum . "</scriptlinenum>\n";

	    if (in_array($errno, $user_errors)) {
	        $err .= "\t<vartrace>" . wddx_serialize_value($vars, "Variables") . "</vartrace>\n";
	    }
	    $err .= "</errorentry>\n\n";
	    echo $err;
	    //$this->logger->error($err);

	    // save to the error log, and e-mail me if there is a critical user error
	    //error_log($err, 3, "/usr/local/php4/error.log");
	    if ($errno == E_USER_ERROR) {
			$this->shutdown();
	        die("Fatal error occurred, stopping the script");
	    }
	}
	
	function archiveMessage($sourceHtml) {

		try {

			$messageNoDetails = $this->getMessageNoDetails($sourceHtml);

			$this->logger->debug("Message details ".print_r($messageNoDetails, true));
			if ($messageNoDetails['message_no'] == 0) {
				$this->logger->debug("Could be special characters preventing xpath from working - clean them up and try again");
				$sourceHtml = preg_replace('/[^(\\x0A|\\x0D|\\x20-\\x7F)]*/','', $sourceHtml);
				//$sourceHtml = utf8_strip_ascii_ctrl($sourceHtml);
				$messageNoDetails = $this->getMessageNoDetails($sourceHtml);
				$this->logger->debug("Message details ".print_r($messageNoDetails, true));
			}
			if ($messageNoDetails['message_no'] > 0) {
				$messageDetails['original_source'] = $sourceHtml;
				$messageDetails = array_merge($messageDetails, $messageNoDetails);
				$this->pluginManager->executePlugins($messageDetails);
			} else {
				$this->logger->error("Couldn't get message no, stopping");
			}
		} catch(Exception $exception) {
			$exceptionString = $exception->getMessage()."\n";
			$exceptionString = $exceptionString.$exception->getTraceAsString();
			$exceptionString = "message Details ".print_r($messageDetails, true);
			$this->logger->error("Exception Occrred ".$exceptionString);
		}

		unset($messageNoDetails);
		return $messageDetails;
	}
	
	function testArchive($startMessageNo) {
		$messageDetails = array();

		$sourceHtml = file_get_contents($startMessageNo.".html");
		$this->archiveMessage($sourceHtml);
	}
	
	function startArchive($startMessageNo) {
		$this->initialize();
		$client = $this->initiliazeClient();
		if ($this->logon($client)) {
		    $messageDetails = array();
		    $sourceHtml = $this->getMessage($client, $startMessageNo);
			$messageDetails = $this->archiveMessage($sourceHtml);
			while ($messageDetails['next_message_no'] != 0) {
				$startTime = time();
				$sourceHtml = $this->getMessage($client, $messageDetails['next_message_no']);
				$messageDetails = $this->archiveMessage($sourceHtml);
				$endTime = time();
				$elapsed = $endTime - $startTime;
				$delay = ((8 * 1000) - $elapsed) / 1000; // 8 seconds
				// To avoid eating Yahoo bandwidth, we need to slowdown, 8 seconds would work
				sleep(10); 
			}
			$this->logout($client);
			$this->closeClient($client);
		}
		$this->shutdown();
		$this->logger->debug("Peak Memory Usage ".memory_get_peak_usage(true)/(1024 * 1024));
	}
}
mb_internal_encoding( 'UTF-8' );
$archive = new Archive;
?>
<?php
//$archive->initialize();
//$archive->testArchive(28449);
//$archive->testArchive(28049);
//$archive->testArchive(28140);
//$archive->startArchive(1);
$archive->startArchive(28689);
echo("Peak Memory Usage ".memory_get_peak_usage(true)/(1024 * 1024)."\n");
?>
