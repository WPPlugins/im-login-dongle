<?php

	$path = dirname(dirname(dirname(dirname (__FILE__))));
	require($path.'/wp-load.php');
	include_once('functions.php');
	require_once('class.GoogleTalkBot.php');
	require_once('class.WLMBot.php');
	require_once('class.ICQBot.php');

	if(is_user_logged_in()) {

		global $current_user;
		get_currentuserinfo();
		
		$options = get_option('im_login_dongle_settings');
		$sessions_in_db = get_user_meta($current_user->ID, 'im_login_dongle_data', true);
		if(is_array($sessions_in_db)) {
			foreach($sessions_in_db as $session_id => $data) {
				if(time() - $data['timestamp'] > $options['session_time'] * 60) {
					unset($sessions_in_db[$session_id]);
				}
			}
			update_user_meta($current_user->ID, 'im_login_dongle_data', $sessions_in_db);
		}
		

		if(is_user_logged_in_im_login_dongle($current_user->ID, $_COOKIE['dongle_login_id'])) {
			wp_redirect(get_admin_url(), 301);
		}
		
		if(isset($_GET['error2'])) {
			$dongle_data = get_user_meta($current_user->ID, 'im_login_dongle_settings', true);
			$cookie_id = $_COOKIE['dongle_login_id'];
			unset($dongle_data[$cookie_id]);
			update_user_meta($current_user->ID, 'im_login_dongle_data', $dongle_data);	
		}
		
		$type = $_GET['type'];
		
		if(isset($type)) {
		
			$im_dongle_settings = get_option('im_login_dongle_settings');
			$user_settings = get_user_meta($current_user->ID, 'im_login_dongle_settings', true);
			
			if($im_dongle_settings['im_bots'][$type]['activated']) {
			
				switch($type) {
			
					case "gtalk": {
						
						$dongle_code = random_string($im_dongle_settings['code_length']);
						$gbot = new GoogleTalkBot($im_dongle_settings['im_bots']['gtalk']['im_bot_username'], 
													decrypt_im_login_dongle($im_dongle_settings['im_bots']['gtalk']['im_bot_password'], $im_dongle_settings['encryption_salt']),
													$im_dongle_settings['im_bots']['gtalk']['im_bot_domain']
													);
						$gbot->connect();
						$msg_sent = $gbot->sendMessage($dongle_code, 
														$_SERVER['REMOTE_ADDR'], 
														$im_dongle_settings['custom_im_msg'], 
														$im_dongle_settings['show_message'], 
														$user_settings['im_accounts']['gtalk']['id']
														);
						$id = insert_dongle_code($current_user->ID, $dongle_code);
						if($msg_sent) {
							$redirect_url = plugin_dir_url(__FILE__).'cookie.php?dongle_id='.$id.'&set=true';
							wp_redirect($redirect_url, 301);						
						}
						else {
							delete_session($current_user->ID, $id);
							$redirect_url = plugin_dir_url(__FILE__).'disable.php?error';
							wp_redirect($redirect_url, 301);
						}
					
					} break;
				
					case "icq": {

						$options = get_option('im_login_dongle_settings');
						$dongle_code = random_string($im_dongle_settings['code_length']);
						$icqbot = new ICQBot($im_dongle_settings['im_bots']['icq']['im_bot_username'], 
												decrypt_im_login_dongle($im_dongle_settings['im_bots']['icq']['im_bot_password'], $im_dongle_settings['encryption_salt']),
												isPIDRunning($options['bot_pid'])
											);
						$icqbot->connect();
						$msg_sent = $icqbot->sendMessage($dongle_code, 
												$_SERVER['REMOTE_ADDR'], 
												$im_dongle_settings['custom_im_msg'], 
												$im_dongle_settings['show_message'], 
												$user_settings['im_accounts']['icq']['id']
											);
						$id = insert_dongle_code($current_user->ID, $dongle_code);
						if($msg_sent) {
							$redirect_url = plugin_dir_url(__FILE__).'cookie.php?dongle_id='.$id.'&set=true';
							wp_redirect($redirect_url, 301);						
						}
						else {
							delete_session($current_user->ID, $id);
							$icqbot->killBot();
							$redirect_url = plugin_dir_url(__FILE__).'disable.php?error';
							wp_redirect($redirect_url, 301);
						}
					
					} break;
				
					case "wlm": {
						
						$dongle_code = random_string($im_dongle_settings['code_length']);
						$wlmbot = new WLMBot($im_dongle_settings['im_bots']['wlm']['im_bot_username'], 
													decrypt_im_login_dongle($im_dongle_settings['im_bots']['wlm']['im_bot_password'], $im_dongle_settings['encryption_salt']));
						$wlmbot->connect();
						$msg_sent = $wlmbot->sendMessage($dongle_code, 
														$_SERVER['REMOTE_ADDR'], 
														$im_dongle_settings['custom_im_msg'], 
														$im_dongle_settings['show_message'], 
														$user_settings['im_accounts']['wlm']['id']
														);
						$id = insert_dongle_code($current_user->ID, $dongle_code);
						if($msg_sent) {
							$redirect_url = plugin_dir_url(__FILE__).'cookie.php?dongle_id='.$id.'&set=true';
							wp_redirect($redirect_url, 301);						
						}
						else {
							delete_session($current_user->ID, $id);
							$redirect_url = plugin_dir_url(__FILE__).'disable.php?error';
							wp_redirect($redirect_url, 301);
						}

					} break;
					
					case "gauth": {

						$dongle_code = random_string($im_dongle_settings['code_length']);
						$id = insert_dongle_code($current_user->ID, $dongle_code);
						$redirect_url = plugin_dir_url(__FILE__).'cookie.php?dongle_id='.$id.'&set=true&type=gauth';
						wp_redirect($redirect_url, 301);						

					}
					
				}
				
			}
			else {
				$redirect_url = plugin_dir_url(__FILE__).'auth.php?error';
				wp_redirect($redirect_url, 301);				
			}
			
		}
			
		else {

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
			<?php 
				$error = $_GET['error'];
				$error1 = $_GET['error1'];
				$error2 = $_GET['error2'];
				if(!isset($error) && !isset($error1) && !isset($error2)) {
			?>	
				<p>Welcome <?php echo $current_user->display_name; ?>!</p><br /><p>No worries, you've logged in, just one more step and we're done!</p> <br /><p>Please choose a method to finalize your login.</p><br /></label>
			<?php 
			
				}
				
				else {
					
					if(isset($error)) {
			
			?>
            
					<p>The method you have selected is currently unavailable. Please choose another one.</p><br />
            
            <?php
			
					}
					else if(isset($error1)) {

			?>
            
            		<p>The code you have entered was incorrect. Please try again.</p><br />
            
            <?php
						
					}
					else if(isset($error2)) {
			?>
            
            		<p>You have failed to enter the code in the time given (30 seconds). Please try again.</p><br />
                    
            <?php			
					}
						
				}
				
			?>
				
				<?php echo get_available_accounts($current_user->ID); ?><br /><br />
            <label for='cancel'><a href='<?php echo wp_logout_url(); ?>'>Cancel</a></label><br />
<?php 		if(current_user_can('manage_options')) {
	
?>				
			<?php 
				$options = get_option('im_login_dongle_settings');
				if(!$options['mandatory']) { ?>
            <br /><br /><label for='shutdown'><a href='<?php echo plugin_dir_url(__FILE__).'disable.php'; ?>'>Disable IM login?</a></label>
            <?php } ?>
			<br /><br /><label for='shutdown'><a href='<?php echo plugin_dir_url(__FILE__).'shutdown.php'; ?>'>Disable IM login for all users?</a></label>

<?php			
			}
			else {
?>
			<?php if(!$options['mandatory']) { ?>
            <br /><br /><label for='shutdown'><a href='<?php echo plugin_dir_url(__FILE__).'disable.php'; ?>'>Disable IM login?</a></label>
            <?php } ?>
<?php
			}		
?>
			<br /><br />
			</form>
			</div>
			</body>

<?php

		}

	}
	
	else {
		$redirect_url = site_url('/wp-login.php');
		wp_redirect($redirect_url, 301);
	}

?>