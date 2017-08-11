<?php

	$path = dirname(dirname(dirname(dirname(__FILE__))));
	require_once($path.'/wp-load.php');
	include_once('functions.php');
	require_once('class.GoogleTalkBot.php');
	require_once('class.WLMBot.php');

	if(is_user_logged_in()) {
		
		global $current_user;
		get_currentuserinfo();

		$im_dongle_settings = get_option('im_login_dongle_settings');
		
		if(user_has_im_account($current_user->ID)) {
			wp_redirect(get_admin_url(), 301);
			exit;			
		}

		if(is_user_logged_in_im_login_dongle($current_user->ID, $_COOKIE['dongle_login_id'])) {
			wp_redirect(get_admin_url(), 301);
			exit;
		}
		
		if(isset($_POST['submit'])) {
			
			$wlm = $_POST['wlm'];
			$icq = $_POST['icq'];
			$gtalk = $_POST['gtalk'];
			
			$redirect = false;
			
			$dongle_settings = array();
			
			if(isset($wlm) && strlen($wlm) > 0) {
				$wlmbot = new WLMBot($im_dongle_settings['im_bots']['wlm']['im_bot_username'], 
									decrypt_im_login_dongle($im_dongle_settings['im_bots']['wlm']['im_bot_password'], $im_dongle_settings['encryption_salt']));
				$wlmbot->connect();
				$wlmbot->addContact($wlm);
				$redirect = true;			
			}
			
			if(isset($gtalk) && strlen($gtalk) > 0) {
				$gbot = new GoogleTalkBot($im_dongle_settings['im_bots']['gtalk']['im_bot_username'], 
											decrypt_im_login_dongle($im_dongle_settings['im_bots']['gtalk']['im_bot_password'], $im_dongle_settings['encryption_salt']),
											$im_dongle_settings['im_bots']['gtalk']['im_bot_domain']);
				$gbot->connect();
				$invite_sent = $gbot->sendInvite($gtalk);
				$gbot->disconnect();
				$redirect = true;
			}
						
			if($redirect) {
				$dongle_settings['im_login_dongle_state'] = true;

				$dongle_settings['reset_keys']['key1'] = random_string(15);	
				$dongle_settings['reset_keys']['key2'] = random_string(15);	
				$dongle_settings['reset_keys']['key3'] = random_string(15);	
				$dongle_settings['reset_keys']['key4'] = random_string(15);

				$dongle_settings['im_accounts']['gtalk']['id'] = (strlen($gtalk) > 0) ? $gtalk : "";
				$dongle_settings['im_accounts']['wlm']['id'] = (strlen($wlm) > 0) ? $wlm : "";
				$dongle_settings['im_accounts']['icq']['id'] = (strlen($icq) > 0) ? $icq : "";

				$dongle_settings['im_accounts']['gtalk']['active'] = (strlen($gtalk) > 0) ? true : false;
				$dongle_settings['im_accounts']['wlm']['active'] = (strlen($wlm) > 0) ? true : false;
				$dongle_settings['im_accounts']['icq']['active'] = (strlen($icq) > 0) ? true : false;
				
				update_user_meta($current_user->ID, 'im_login_dongle_settings', $dongle_settings);
				
				wp_redirect(get_admin_url(), 301);
				exit;
				
			}
			else {
				$redirect_url = plugin_dir_url(__FILE__).'im_set.php?error';
				wp_redirect($redirect_url, 301);
				exit;			
			}
			
		}

?>


			<!DOCTYPE html>
			<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">  
			<link rel='stylesheet' id='wp-admin-css' href='<?php echo admin_url('css/wp-admin.css?ver=3.4.2'); ?>' type='text/css' media='all' />
			<link rel='stylesheet' id='colors-fresh-css'  href='<?php echo admin_url('css/colors-fresh.css?ver=3.4.2');  ?>' type='text/css' media='all' />
			<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
			<head><title>IM Login Dongle setup</title></head>
			<body class="login">
			<div id='login'>
			<a href="http://wpplugz.is-leet.com"><img src="images/logo.png" style="display: block; overflow: hidden; padding-bottom: 15px; padding-left:30px; align:center;" /></a>


			<form id="login_form" name="loginform"  action="" method="post">
            <?php if(isset($_GET['error'])) { ?><p>Something went wrong. Please try again.</p><br /><?php } ?>
            <?php if(!isset($_GET['error'])) { ?>
            <p>Welcome <?php echo $current_user->display_name; ?>!</p><br /><p>We've decided to to make your login more secure.</p><br />
            <p>Please enter at least one instant messenger ID that you're actively using every day.</p><br />
            <p>Our bot will then add you to the contact list. When this happens, please accept the friend request.</p><br />
			<?php } ?>
            <?php echo get_active_bot_accounts_html(get_option('im_login_dongle_settings')); ?><br /><br />
			<p class="submit"><input type="submit" name="submit" tabindex="100" id="wp-submit" class="button-primary" value="Continue" tabindex="100" /></p>
			<label for='cancel'><a href='<?php echo wp_logout_url(); ?>'>Cancel</a></label><br />
            </form><br />
			</div>
			</body>

<?php


	}
	else {
		$redirect_url = site_url('/wp-login.php');
		wp_redirect($redirect_url, 301);
	}

?>