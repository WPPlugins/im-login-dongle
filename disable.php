<?php

	$path = dirname(dirname(dirname(dirname (__FILE__))));
	require($path.'/wp-load.php');

	$settings = get_option('im_login_dongle_settings');

	if(is_user_logged_in() && !$settings['mandatory']) {

		if(isset($_POST['submitted'])) {
			$code1 = $_POST['code1'];
			$code2 = $_POST['code2'];
			$code3 = $_POST['code3'];
			$code4 = $_POST['code4'];
			
			global $current_user;
			get_currentuserinfo();
		
			$user_dongle_settings = get_user_meta($current_user->ID, 'im_login_dongle_settings', true);
			$reset_keys = $user_dongle_settings['reset_keys'];
			$redirect_url = home_url('/wp-login.php');
			
			if(isset($code1) && isset($code2) && isset($code3) && isset($code4)) {
				if($reset_keys['key1'] == $code1 && $reset_keys['key2'] == $code2 && $reset_keys['key3'] == $code3 && $reset_keys['key4'] == $code4) {
					$user_dongle_settings['im_login_dongle_state'] = false;
					update_user_meta($current_user->ID, 'im_login_dongle_settings', $user_dongle_settings);
					wp_redirect(get_admin_url(), 301);
				}
				else {
					wp_redirect($redirect_url, 301);
				}
			}
			else {
					wp_redirect($redirect_url, 301);
			}
				
		}

?>


			<!DOCTYPE html>
			<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">  
			<link rel='stylesheet' id='wp-admin-css' href='<?php echo admin_url('css/wp-admin.css'); ?>' type='text/css' media='all' />
			<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
			<link rel='stylesheet' id='colors-fresh-css'  href='<?php echo admin_url('css/colors-fresh.css');  ?>' type='text/css' media='all' />
			<link rel='stylesheet' id='buttons-css'  href='<?php echo get_site_url().'/wp-includes/css/buttons.min.css'; ?>' type='text/css' media='all' />
			<head><title>IM Login Dongle disable</title></head>
			<body class="login login-action-login wp-core-ui">
			<div id='login'>
			<a href="http://wpplugz.is-leet.com"><img src="images/logo.png" style="display: block; overflow: hidden; padding-bottom: 15px; padding-left:30px; align:center;" /></a>


			<form id="login_form" name="loginform"  action="" method="post">
<?php 

			if(isset($_GET['error'])) { 
?>
            There seems to be something wrong with the IM server. Please enter the reset codes that were given to you or <a href="<?php echo plugin_dir_url(__FILE__).'dongle.php?cancel'; ?>">use another method</a> of authorization.<br /><br />
<?php 
			} 
			else {
?>
            To disable the IM login, please enter the reset codes that were given to you.<br /><br />
            
<?php
			}
?>
			</label>
			<label for="code1">Key 1<input class="input" type="text" name="code1" id="code1" /></label><br />
            <label for="code2">Key 2<input class="input" type="text" name="code2" id="code2" /></label><br />
            <label for="code3">Key 3<input class="input" type="text" name="code3" id="code3" /></label><br />
            <label for="code4">Key 4<input class="input" type="text" name="code4" id="code4" /></label><br />
            <input type="hidden" value="submitted" name="submitted" />
			<p class="submit"><input type="submit" name="submit" tabindex="100" id="wp-submit" class="button-primary" value="Disable" tabindex="100" /></p>
            <label for='cancel'><a href='<?php echo plugin_dir_url(__FILE__).'dongle.php?logout'; ?>'>Logout</a></label><br />
			</form>
			</div>
			</body>

<?php

	}
	
	else {
		$redirect_url = site_url('/wp-login.php');
		wp_redirect($redirect_url, 301);
	}

?>