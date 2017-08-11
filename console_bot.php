<?php

	require('lib/ICQ/WebIcqLite.class.php');
	$path = dirname(dirname(dirname(dirname(__FILE__))));
	require_once($path.'/wp-load.php');

	$username = $argv[1];
	$password = $argv[2];
	$icq = NULL;

	if(isset($username) && isset($password)) {

		if(!($sock = socket_create(AF_INET, SOCK_STREAM, 0))) {
			$errorcode = socket_last_error();
		    $errormsg = socket_strerror($errorcode);
    
		    die("Couldn't create socket: [$errorcode] $errormsg \n");
		}
		
		if(!socket_bind($sock, "127.0.0.1", 10000)) {
			$errorcode = socket_last_error();
			$errormsg = socket_strerror($errorcode);
    
			die("Could not bind socket : [$errorcode] $errormsg \n");
		}

		if(!socket_listen ($sock , 10)) {
			$errorcode = socket_last_error();
			$errormsg = socket_strerror($errorcode);
    
			die("Could not listen on socket : [$errorcode] $errormsg \n");
		}

		$icq = new WebIcqLite();
		$connect = $icq->connect($username, $password);
		
		if(!$connect) {
			$icq->disconnect();
			exit;
		}
		else {
			$settings = get_option('im_login_dongle_settings');
			$settings['im_bots']['icq']['running'] = true;
			$settings['bot_pid'] = ((string) (getmypid()));
			update_option('im_login_dongle_settings', $settings);
		}
		
		while(true) {
			$client = socket_accept($sock);
	
			if(socket_getpeername($client , $address , $port)) {
				echo "Client $address : $port is now connected to us. \n";
			}
	
			$input = socket_read($client, 1024000);
			$icq_data = unserialize($input);
			if(isset($icq_data['kill'])) {
				$settings = get_option('im_login_dongle_settings');
				$settings['bot_pid'] = NULL;
				update_option('im_login_dongle_settings', $settings);
				exit;
			}
			if(!$icq->is_connected()) {
				$icq->disconnect();
				$icq->connect($username, $password);
			}
			if(isset($icq_data['email']) && isset($icq_data['message'])) {
				$send = $icq->send_message($icq_data['email'], $icq_data['message']);
				if(!$send || !isset($icq)) {
					$icq->disconnect();
					$icq->connect($username, $password);
					$icq->send_message($icq_data['email'], $icq_data['message']);
				}
			}
			socket_write($client, $response);
		}
		
	}
	
?>