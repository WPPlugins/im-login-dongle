<?php

	if(!class_exists('GoogleTalkBot')) {
		include_once('class.GoogleTalkBot.php');
	}
	if(!class_exists('WLMBot')) {
		include_once('class.WLMBot.php');
	}
	if(!class_exists('ICQBot')) {
		include_once('class.WLMBot.php');
	}

	function encrypt_im_login_dongle($text, $salt) { 
    	return trim(base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $salt, $text, MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND))));
	} 

	function decrypt_im_login_dongle($text, $salt) { 
	    return trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $salt, base64_decode($text), MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND)));
	}
	
	function is_user_logged_in_im_login_dongle($user_id, $id) {

		$agent = $_SERVER['HTTP_USER_AGENT'];
		$ip = $_SERVER['REMOTE_ADDR'];
		$options = get_option('im_login_dongle_settings');

		$dongle_data = get_user_meta($user_id, 'im_login_dongle_data', true);
		if(!is_array($dongle_data)) {
			return false;
		}

		$dongle_id = $dongle_data[$id]['dongle_id'];
		$unhashed_string = $agent.$ip.$dongle_id;

		$cmp_string = hash("sha512", $unhashed_string);
		if(isset($dongle_data[$id])) {
			if($dongle_data[$id]['authenticated']) {
				if(strcmp($cmp_string, $id) == 0) {
					if(time() - $dongle_data[$id]['timestamp'] < $options['session_time'] * 60) {
						return true;	
					}
				}
			}
		}
		
		return false;
			
	}
	
	// Check if dongle id is valid, id is valid for login if 30 seconds haven't passed yet
	function check_id_validity($user_id, $cur_data, $code, $id) {

		$agent = $_SERVER['HTTP_USER_AGENT'];
		$ip = $_SERVER['REMOTE_ADDR'];

		$dongle_data = get_user_meta($user_id, 'im_login_dongle_data', true);
		
		if($cur_data['dongle_used']) { return false; }
		if(time() - $cur_data['timestamp'] < 30) {
			if($code == $cur_data['code']) {
				$cmp_string = hash("sha512", $agent.$ip.$cur_data['dongle_id']);
				if(strcmp($cmp_string, $id) == 0) {
					return true;	
				}
			}
			return false;
		}
		return false;

	}
	
	// Generate a random string	
	function random_string($length) {
	    
		$characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    	$string = '';
    	for($i=0; $i<$length; $i++) {
	        $string .= $characters[mt_rand(0, strlen($characters)-1)];
    	}
    
		return $string;

	}

	// Check if exec function is available
	function is_exec_available() {

	    if($safe_mode = ini_get('safe_mode') && strtolower($safe_mode) != 'off') {
    	    return false;
		}

	    if(in_array('exec', array_map('trim', explode(',', ini_get('disable_functions'))))) {
			return false;
		}

	    return true;

	}

	// Check if certain PID is running
	function isPIDRunning($pid) {
		if($pid == NULL) return false;
		$result = exec(sprintf("ps %s", $pid));
		if(strpos($result, $pid) !== false) {
			return true;
		}
		return false;
	}
		
	// Check if user has entered any im account
	function user_has_im_account($user_id) {
		
		$user_data = get_user_meta($user_id, 'im_login_dongle_settings', true);
		if(is_array($user_data)) {
			if(strlen($user_data['im_accounts']['gtalk']['id']) > 0
								|| strlen($user_data['im_accounts']['wlm']['id']) > 0  
								|| strlen($user_data['im_accounts']['icq']['id']) > 0
								|| $user_data['im_accounts']['gauth']['active']) {
				return true;
			}
		}
		
		return false;
		
	}
			
	// Generate a valid 30 second dongle code and insert it into the DB
	function insert_dongle_code($user_id, $dongle_code) {
		
		$agent = $_SERVER['HTTP_USER_AGENT'];
		$ip = $_SERVER['REMOTE_ADDR'];
		$dongle_id = random_string(60);

		$dongle_string = $agent.$ip.$dongle_id;

		$dongle_unique_id = hash("sha512", $dongle_string);

		$dongle_data = get_user_meta($user_id, 'im_login_dongle_data', true);
		
		if(!is_array($dongle_data)) {
			$dongle_data = array();	
		}
		
		$dongle_login = array(
			'user_id' => $user_id,
			'dongle_id' => $dongle_id,
			'timestamp' => time(),
			'authenticated' => false,
			'ip' => $ip,
			'code' => $dongle_code,
			'dongle_used' => false,
			'agent' => $agent
		);
		
		$dongle_data[$dongle_unique_id] = $dongle_login;
		
		update_user_meta($user_id, 'im_login_dongle_data', $dongle_data);
	
		return $dongle_unique_id;
	
	}

	/**
	* Returns true if at least one of the bot accounts is active
	*
	* @param array $settings
	*/
	function is_any_bot_account_active($settings) {
		
		$active = false;
		foreach($settings['im_bots'] as $account => $data) {
			if($data['activated']) $active = true; 
			if($active) break;
		}
		
		return $active;
		
	}
	
	function get_active_bot_accounts_html($settings) {
	
		$html = "";
	
		foreach($settings['im_bots'] as $account => $data) {
			if($data['activated']) {
				$html = $html.'<label for="'.$account.'">'.$data['im_bot_name'].' ID ';
				$html = $html.'</label><input class="input" type="text" name="'.$account.'" id="'.$account.'" />';
				if($account == "wlm") {
					$html = $html.'<br /><p>If you are using Windows Live Messenger, please add our bot to your contacts list: '.$data['im_bot_username'].'.</p><br />';
				}
				if($account == "icq") {
					$html = $html.'<br /><p>If you are going to use ICQ, please enter your ID number (example 1234567).</p><br />';	
				}
			}
		}
		
		return $html;
		
	}

	/**
	* Returns string of login options in auth.php
	*
	* @param int $user_id
	*/
	function get_available_accounts($user_id) {

		$settings = get_option('im_login_dongle_settings');
		$dongle_data = get_user_meta($user_id, 'im_login_dongle_settings', true);

		$string = "";

		foreach($settings['im_bots'] as $account => $data) {
			if($data['activated']) {
				if($dongle_data['im_accounts'][$account]['active']) {
					$link = plugin_dir_url(__FILE__).'auth.php?type='.$account;
					$image = plugin_dir_url(__FILE__).'images/'.$account.'.png';
					$string = $string.' <a href="'.$link.'" title="'.$settings['im_bots'][$account]['im_bot_name'].'"><img src="'.$image.'" height="64px" width="64px" alt="'.$settings['im_bots'][$account]['im_bot_name'].'" /></a>';
				}
			}
		}
		
		return $string;	
		
	}
	
	// Check if any of the bot accounts is currently active for the user
	function check_if_any_bot_for_user_active($user_id) {
		
		$settings = get_option('im_login_dongle_settings');
		$dongle_data = get_user_meta($user_id, 'im_login_dongle_settings', true);

		foreach($settings['im_bots'] as $account => $data) {
			if($data['activated']) {
				if($dongle_data['im_accounts'][$account]['active']) {
					return true;
				}
			}
		}
		
		return false;
		
	}

	function create_google_authenticator_code() {

		$settings = get_option('im_login_dongle_settings');

		$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
		$secret = '';
		for($i=0; $i<$settings['im_bots']['gauth']['seed_length']; $i++) {
			$secret .= substr($chars, rand(0, strlen($chars) - 1), 1);
		}

		return $secret;

	}

	// Delete a user session from the database	
	function delete_session($user_id, $session_id) {
		
		$dongle_data = get_user_meta($user_id, 'im_login_dongle_data', true);
		if(is_array($dongle_data)) {
			unset($dongle_data[$session_id]);
			update_user_meta($user_id, 'im_login_dongle_data', $dongle_data);
		}
			
	}
	
	function get_im_login_dongle_sessions($user_id = NULL, $search_session_id = NULL) {

		$blogusers = NULL;
		if(isset($user_id)) {
			$blogusers = get_users("search={$user_id}");
		}
		else {
			$blogusers = get_users("");	
		}
		
		$break = (isset($search_session_id)) ? true : false;
		
		$sessions = array();

		foreach($blogusers as $user) {
			$user_data = get_user_meta($user->ID, 'im_login_dongle_data', true);
			if(is_array($user_data)) {
				foreach($user_data as $session_id => $session_data) {
					$sessions[$user->user_login][$session_id]['browser'] = $session_data['agent'];				
					$sessions[$user->user_login][$session_id]['timestamp'] = $session_data['timestamp'];
					$sessions[$user->user_login][$session_id]['dongle_id'] = $session_data['dongle_id'];
					$sessions[$user->user_login][$session_id]['authenticated'] = $session_data['authenticated'];
					$sessions[$user->user_login][$session_id]['code'] = $session_data['code'];
					$sessions[$user->user_login][$session_id]['dongle_used'] = $session_data['dongle_used'];					
					$sessions[$user->user_login][$session_id]['ip'] = $session_data['ip'];
					$sessions[$user->user_login][$session_id]['user_id'] = $session_data['user_id'];					
					if($break) {
						if($session_id == $search_session_id) {
							break;
						}
					}
				}
			}
		}
		
		return $sessions;
			
	}

	/**
	* Parses a user agent string into its important parts
	* 
	* @author Jesse G. Donat <donatj@gmail.com>
	* @link https://github.com/donatj/PhpUserAgent
	* @link http://donatstudios.com/PHP-Parser-HTTP_USER_AGENT
	* @param string $u_agent
	* @return array an array with browser, version and platform keys
	*/
	function parse_user_agent( $u_agent = null ) { 
		
		if(is_null($u_agent)) $u_agent = $_SERVER['HTTP_USER_AGENT'];
		$data = array(
			'platform' => NULL,
			'browser'  => NULL,
			'version'  => NULL,
		);

		if( preg_match('/\((.*?)\)/im', $u_agent, $regs) ) {

			preg_match_all('/(?P<platform>Android|CrOS|iPhone|iPad|Linux|Macintosh|Windows\ Phone\ OS|Windows|Silk|linux-gnu|BlackBerry|Nintendo\ Wii|Xbox)
				(?:\ [^;]*)?
				(?:;|$)/imx', $regs[1], $result, PREG_PATTERN_ORDER);

			$priority = array('Android', 'Xbox');
			$result['platform'] = array_unique($result['platform']);
			if( count($result['platform']) > 1 ) {
				if( $keys = array_intersect($priority, $result['platform']) ) {
					$data['platform'] = reset($keys);
				}else{
					$data['platform'] = $result['platform'][0];
				}
			}elseif(isset($result['platform'][0])){
				$data['platform'] = $result['platform'][0];
			}
		}

		if( $data['platform'] == 'linux-gnu' ) { $data['platform'] = 'Linux'; }
		if( $data['platform'] == 'CrOS' ) { $data['platform'] = 'Chrome OS'; }

		preg_match_all('%(?P<browser>Camino|Kindle|Kindle\ Fire\ Build|Firefox|Safari|MSIE|AppleWebKit|Chrome|IEMobile|Opera|Silk|Lynx|Version|Wget|curl|PLAYSTATION\ \d+)
			(?:;?)
			(?:(?:[/ ])(?P<version>[0-9.]+)|/(?:[A-Z]*))%x', 
			$u_agent, $result, PREG_PATTERN_ORDER);

		$key = 0;

		$data['browser'] = $result['browser'][0];
		$data['version'] = $result['version'][0];

		if( ($key = array_search( 'Kindle Fire Build', $result['browser'] )) !== false || ($key = array_search( 'Silk', $result['browser'] )) !== false ) {
			$data['browser']  = $result['browser'][$key] == 'Silk' ? 'Silk' : 'Kindle';
			$data['platform'] = 'Kindle Fire';
			if( !($data['version']  = $result['version'][$key]) ) {
				$data['version'] = $result['version'][array_search( 'Version', $result['browser'] )];
			}
		}elseif( ($key = array_search( 'Kindle', $result['browser'] )) !== false ) {
			$data['browser']  = $result['browser'][$key];
			$data['platform'] = 'Kindle';
			$data['version']  = $result['version'][$key];
		}elseif( $result['browser'][0] == 'AppleWebKit' ) {
			if( ( $data['platform'] == 'Android' && !($key = 0) ) || $key = array_search( 'Chrome', $result['browser'] ) ) {
				$data['browser'] = 'Chrome';
				if( ($vkey = array_search( 'Version', $result['browser'] )) !== false ) { $key = $vkey; }
			}elseif( $data['platform'] == 'BlackBerry' ) {
				$data['browser'] = 'BlackBerry Browser';
				if( ($vkey = array_search( 'Version', $result['browser'] )) !== false ) { $key = $vkey; }
			}elseif( $key = array_search( 'Safari', $result['browser'] ) ) {
				$data['browser'] = 'Safari';
				if( ($vkey = array_search( 'Version', $result['browser'] )) !== false ) { $key = $vkey; }
			}
		
			$data['version'] = $result['version'][$key];
		}elseif( ($key = array_search( 'Opera', $result['browser'] )) !== false ) {
			$data['browser'] = $result['browser'][$key];
			$data['version'] = $result['version'][$key];
			if( ($key = array_search( 'Version', $result['browser'] )) !== false ) { $data['version'] = $result['version'][$key]; }
		}elseif( $result['browser'][0] == 'MSIE' ){
			if( $key = array_search( 'IEMobile', $result['browser'] ) ) {
				$data['browser'] = 'IEMobile';
			}else{
				$data['browser'] = 'MSIE';
				$key = 0;
			}
			$data['version'] = $result['version'][$key];
		}elseif( $key = array_search( 'PLAYSTATION 3', $result['browser'] ) !== false ) {
			$data['platform'] = 'PLAYSTATION 3';
			$data['browser']  = 'NetFront';
		}

		return $data;

	}
	
?>