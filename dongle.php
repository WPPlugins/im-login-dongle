<?php

	$path = dirname(dirname(dirname(dirname(__FILE__))));
	require_once($path.'/wp-load.php');
	include_once('functions.php');

	if(is_user_logged_in()) {

		global $current_user;
		get_currentuserinfo();

		$dongle_data = get_user_meta($current_user->ID, 'im_login_dongle_data', true);
		$cookie_id = $_COOKIE['dongle_login_id'];

		if(is_user_logged_in_im_login_dongle($current_user->ID, $_COOKIE['dongle_login_id'])) {
			wp_redirect(get_admin_url(), 301);
		}
		
		if(isset($_GET['cancel'])) {
			unset($dongle_data[$cookie_id]);
			update_usermeta($current_user->ID, 'im_login_dongle_data', $dongle_data);
			$redirect = plugin_dir_url(__FILE__).'auth.php';
			wp_redirect($redirect, 301);
			exit;
		}
		
		if(isset($_GET['logout'])) {
			unset($dongle_data[$cookie_id]);
			update_usermeta($current_user->ID, 'im_login_dongle_data', $dongle_data);
			wp_logout();
			$redirect_url = site_url('/wp-login.php');
			wp_redirect($redirect_url, 301);
			exit;
		}

		if(isset($_POST['submitted'])) {
			$code = $_POST['code'];
			if(isset($code)) {
			
				$cur_data = $dongle_data[$cookie_id];

				$check_validity = check_id_validity($current_user->ID, $cur_data, $code, $cookie_id);

				if($check_validity) {
					$cur_data['authenticated'] = true;
					$cur_data['dongle_used'] = true;
					$dongle_data[$cookie_id] = $cur_data;
					update_user_meta($current_user->ID, 'im_login_dongle_data', $dongle_data);
					wp_redirect(get_admin_url(), 301);
					exit;
				}
				else {
					unset($dongle_data[$cookie_id]);
					update_usermeta($current_user->ID, 'im_login_dongle_data', $dongle_data);				
					$redirect = plugin_dir_url(__FILE__).'auth.php?error1';
					setcookie("dongle_login_id", "", time()-3600*24, "/");			
					wp_redirect($redirect, 301);
					exit;
				}
			}
		}

?>


			<!DOCTYPE html>
			<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">  
			<link rel='stylesheet' id='wp-admin-css' href='<?php echo admin_url('css/wp-admin.css'); ?>' type='text/css' media='all' />
			<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
			<link rel='stylesheet' id='colors-fresh-css'  href='<?php echo admin_url('css/colors-fresh.css');  ?>' type='text/css' media='all' />
			<link rel='stylesheet' id='buttons-css'  href='<?php echo get_site_url().'/wp-includes/css/buttons.min.css'; ?>' type='text/css' media='all' />
			<head><title>Login authorization</title></head>
			<body class="login login-action-login wp-core-ui">
			<div id='login'>
			<a href="http://wpplugz.is-leet.com"><img src="images/logo.png" style="display: block; overflow: hidden; padding-bottom: 15px; padding-left:30px; align:center;" /></a>


			<form id="login_form" name="loginform"  action="" method="post">
			<label for="user_login"><p>Please enter the authentication code that was sent to you. If you did not receive your code yet, maybe use <a href="<?php echo plugin_dir_url(__FILE__).'dongle.php?cancel'; ?>">another method</a>?</p><br /><input class="input" type="text" name="code" />
            <input type="hidden" value="submitted" name="submitted" />
			<p class="submit"><input type="submit" name="submit" tabindex="100" id="wp-submit" class="button-primary" value="Authorize" tabindex="100" /></p>
			<label for='cancel'><a href='<?php echo plugin_dir_url(__FILE__).'dongle.php?cancel'; ?>'>Cancel</a></label><br />
			<br /><br /><label for='shutdown'><a href='<?php echo plugin_dir_url(__FILE__).'dongle.php?logout'; ?>'>Logout?</a></label>
			<br /><br />
			</form><br />
            <meta http-equiv="refresh" content="30;URL='<?php echo plugin_dir_url(__FILE__).'auth.php?error2'; ?>'">
			</div>
			</body>

<?php


	}
	else {
		$redirect_url = site_url('/wp-login.php');
		wp_redirect($redirect_url, 301);
	}

?>