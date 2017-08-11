<?php

	require_once('lib/WLM/WLM.php');

	class WLMBot {
	
		private $connection_success = false;
		private $username;
		private $password;
		private $connection;
	
		public function __construct($username, $password) {
			$this->username = $username;
			$this->password = $password;
		}
		
		public function connect() {
			$this->connection = new MSN();
			$this->connection_success = $this->connection->connect($this->username, $this->password);
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
				$message_sent = $this->connection->sendMessage($message, array($email));
			}
			else {
				$message_sent = false;	
			}
			return $message_sent;
		}
		
		public function addContact($email) {
			$contact_added = true;
			if($this->connection_success) {
				$contact_added = $this->connection->addContact($email);
			}
			else {
				$contact_added = false;	
			}
			return $contact_added;
		}
				
	}
	
?>