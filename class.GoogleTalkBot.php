<?php

	require_once('lib/XMPPHP/XMPP.php');

	class GoogleTalkBot {
	
		private $connection_success = false;
		private $username;
		private $password;
		private $domain;
		private $connection;
	
		public function __construct($username, $password, $domain) {
			$this->username = $username;
			$this->password = $password;
			$this->domain = $domain;
		}
		
		public function connect() {
			$this->connection = new XMPPHP_XMPP('talk.google.com', 5222, $this->username, $this->password, 'xmpphp', $this->domain, $printlog=true, $loglevel=XMPPHP_Log::LEVEL_INFO);	
			$this->connection->useEncryption(true);
			try {
			    $this->connection->connect();
			    $this->connection->processUntil('session_start');
	    		$this->connection->presence();
				$this->connection_success = true;
			} catch(XMPPHP_Exception $e) {
				$this->connection_success = false;
			}			
		}
		
		public function sendMessage($code, $ip, $custom_msg, $show_powered, $email) {
			$message_sent = true;
			if($this->connection_success) {
				$message = "WP Login code \n\n".$code."\n \n"."This code was requested from ".$ip." and is valid for the next 30 seconds.";
				if(strlen($custom_msg) > 0) {
					$message = $message."\n\n".$custom_msg;	
				}
				if($show_powered) {
					$message = $message."\n\n.: Powered by IM Login Dongle (http://wpplugz.is-leet.com) :.";
				}
				try {
					$this->connection->message($email, $message);
				} catch(XMPPHP_Exception $e) {
					$message_sent = false;	
				}
			}
			else {
				$message_sent = false;	
			}
			return $message_sent;
		}
		
		public function sendInvite($email) {
			$invite_sent = true;
			if($this->connection_success) {
				try {
					$this->connection->addRosterContact($email, '');
					$this->connection->subscribe($email);	
				} catch(XMPPHP_Exception $e) {
					$this->invite_sent = false;	
				}
			}
			else {
				$invite_sent = false;	
			}
			return $invite_sent;
		}
		
		public function disconnect() {
			if($this->connection_success) {
				$this->connection->disconnect();
			}
		}
		
	}
	
?>