<?php

	class ICQBot {
	
		private $username;
		private $password;
		private $connected;
		private $running;
	
		public function __construct($username, $password, $running) {
			$this->username = $username;
			$this->password = $password;
			$this->running = $running;
		}
		
		public function connect() {
			if($this->running) {
				$this->connected = true;	
			}
			else {
				$output = exec(sprintf('php console_bot.php %s %s > /dev/null 2> /dev/null &', $this->username, $this->password));
				sleep(5);
				$this->connected = true;				
			}
		}
		
		protected function sendDataToBot($ar_s) {
			$data_sent = true;		
			if($this->connected) {
				if(!($sock = socket_create(AF_INET, SOCK_STREAM, 0))) {
					$errorcode = socket_last_error();
					$errormsg = socket_strerror($errorcode);
					$this->connected = false;
					$data_sent = false; 
				}

				if(!socket_connect($sock , '127.0.0.1' , 10000)) {
					$errorcode = socket_last_error();
					$errormsg = socket_strerror($errorcode);
					$this->connected = false;
					$data_sent = false; 
				}
				if(!socket_send($sock, $ar_s, strlen($ar_s), 0)) {
					$errorcode = socket_last_error();
					$errormsg = socket_strerror($errorcode);
					$data_sent = false;
				}
			}
			return $data_sent;
		}
		
		public function sendMessage($code, $ip, $custom_msg, $show_powered, $email) {
			$message_sent = true;
			if($this->connected) {
				$message = "WP Login code \n\n".$code."\n \n"."This code was requested from ".$ip." and is valid for the next 30 seconds.";
				if(strlen($custom_msg) > 0) {
					$message = $message."\n\n".$custom_msg;	
				}
				if($show_powered) {
					$message = $message."\n\n.: Powered by IM Login Dongle :.";
				}
				
				$icq_send = array();
				$icq_send['email'] = $email;
				$icq_send['message'] = $message;
				$ar_s = serialize($icq_send);
				$message_sent = $this->sendDataToBot($ar_s);
			}
			
			return $message_sent;

		}
		
		public function killBot() {
			$data = serialize(array('kill' => true));
			$sent = $this->sendDataToBot($data);
		}
		
	}
	
?>