<?php

	$path = dirname(dirname(dirname(dirname (__FILE__))));
	require($path.'/wp-load.php');
	include_once('functions.php');

	if(is_user_logged_in()) {

		global $current_user;
		get_currentuserinfo();

		if(is_user_logged_in_im_login_dongle($current_user->ID, $_COOKIE['dongle_login_id'])) {
			wp_redirect(get_admin_url(), 301);
			exit;
		}

		$dongle_id = $_GET['dongle_id'];
		$set = $_GET['set'];
		$plugin_options = get_option('im_login_dongle_settings');
	
		if(isset($dongle_id) && isset($set)) {
			$redirect_url = plugin_dir_url(__FILE__).'dongle.php';
			if($_GET['type'] == "gauth") {
				$redirect_url = plugin_dir_url(__FILE__).'gauth.php';
			}
			header("Location: $redirect_url");
			setcookie("dongle_login_id", $dongle_id, time()+($plugin_options['session_time']*60), "/");
		    exit();
		}
		else {
			wp_redirect(home_url('/wp-login.php'), 301);
			exit();
		}
		
	}

?>