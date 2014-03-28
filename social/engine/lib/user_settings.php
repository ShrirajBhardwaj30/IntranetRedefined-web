<?php
/**
 * Elgg user settings functions.
 * Functions for adding and manipulating options on the user settings panel.
 *
 * @package Elgg.Core
 * @subpackage Settings.User
 */

/**
 * Saves user settings.
 *
 * @todo all the functions called by this function assume settings are coming
 * in on a GET/POST request
 *
 * @note This is a handler for the 'usersettings:save', 'user' plugin hook
 *
 * @return void
 * @access private
 */
function _elgg_user_settings_save() {
	_elgg_set_user_language();
	_elgg_set_user_password();
	_elgg_set_user_default_access();
	_elgg_set_user_name();
	_elgg_set_user_email();
}

/**
 * Set a user's password
 * 
 * @return bool
 * @since 1.8.0
 * @access private
 */
function _elgg_set_user_password() {
	$current_password = get_input('current_password', null, false);
	$password = get_input('password', null, false);
	$password2 = get_input('password2', null, false);
	$user_guid = get_input('guid');

	if ($user_guid) {
		$user = get_user($user_guid);
	} else {
		$user = elgg_get_logged_in_user_entity();
	}

	if ($user && $password) {
		// let admin user change anyone's password without knowing it except his own.
		if (!elgg_is_admin_logged_in() || elgg_is_admin_logged_in() && $user->guid == elgg_get_logged_in_user_guid()) {
			$credentials = array(
				'username' => $user->username,
				'password' => $current_password
			);

			try {
				pam_auth_userpass($credentials);
			} catch (LoginException $e) {
				register_error(elgg_echo('LoginException:ChangePasswordFailure'));
				return false;
			}
		}

		try {
			$result = validate_password($password);
		} catch (RegistrationException $e) {
			register_error($e->getMessage());
			return false;
		}

		if ($result) {
			if ($password == $password2) {
				$user->salt = _elgg_generate_password_salt();
				$user->password = generate_user_password($user, $password);

				// don't allow others to authenticate via tokens
				_elgg_delete_users_remember_me_hashes($user);

				$cookie_token = _elgg_get_remember_me_token_from_cookie();
				if ($user->guid == elgg_get_logged_in_user_guid() && $cookie_token) {
					// user has a persistent cookie. set this back up
					$token = _elgg_generate_remember_me_token();
					$hash = md5($token);
					elgg_get_session()->set('code', $token);
					_elgg_add_remember_me_cookie($user, $hash);
					_elgg_set_remember_me_cookie($token);
				}
				if ($user->save()) {
					system_message(elgg_echo('user:password:success'));
					return true;
				} else {
					register_error(elgg_echo('user:password:fail'));
				}
			} else {
				register_error(elgg_echo('user:password:fail:notsame'));
			}
		} else {
			register_error(elgg_echo('user:password:fail:tooshort'));
		}
	} else {
		// no change
		return null;
	}

	return false;
}

/**
 * Set a user's display name
 * 
 * @return bool
 * @since 1.8.0
 * @access private
 */
function _elgg_set_user_name() {
	$name = strip_tags(get_input('name'));
	$user_guid = get_input('guid');

	if ($user_guid) {
		$user = get_user($user_guid);
	} else {
		$user = elgg_get_logged_in_user_entity();
	}

	if (elgg_strlen($name) > 50) {
		register_error(elgg_echo('user:name:fail'));
		return false;
	}

	if ($user && $user->canEdit() && $name) {
		if ($name != $user->name) {
			$user->name = $name;
			if ($user->save()) {
				system_message(elgg_echo('user:name:success'));
				return true;
			} else {
				register_error(elgg_echo('user:name:fail'));
			}
		} else {
			// no change
			return null;
		}
	} else {
		register_error(elgg_echo('user:name:fail'));
	}
	return false;
}

/**
 * Set a user's language
 * 
 * @return bool
 * @since 1.8.0
 * @access private
 */
function _elgg_set_user_language() {
	$language = get_input('language');
	$user_guid = get_input('guid');

	if ($user_guid) {
		$user = get_user($user_guid);
	} else {
		$user = elgg_get_logged_in_user_entity();
	}

	if ($user && $language) {
		if (strcmp($language, $user->language) != 0) {
			$user->language = $language;
			if ($user->save()) {
				system_message(elgg_echo('user:language:success'));
				return true;
			} else {
				register_error(elgg_echo('user:language:fail'));
			}
		} else {
			// no change
			return null;
		}
	} else {
		register_error(elgg_echo('user:language:fail'));
	}
	return false;
}

/**
 * Set a user's email address
 *
 * @return bool
 * @since 1.8.0
 * @access private
 */
function _elgg_set_user_email() {
	$email = get_input('email');
	$user_guid = get_input('guid');

	if ($user_guid) {
		$user = get_user($user_guid);
	} else {
		$user = elgg_get_logged_in_user_entity();
	}

	if (!is_email_address($email)) {
		register_error(elgg_echo('email:save:fail'));
		return false;
	}

	if ($user) {
		if (strcmp($email, $user->email) != 0) {
			if (!get_user_by_email($email)) {
				if ($user->email != $email) {

					$user->email = $email;
					if ($user->save()) {
						system_message(elgg_echo('email:save:success'));
						return true;
					} else {
						register_error(elgg_echo('email:save:fail'));
					}
				}
			} else {
				register_error(elgg_echo('registration:dupeemail'));
			}
		} else {
			// no change
			return null;
		}
	} else {
		register_error(elgg_echo('email:save:fail'));
	}
	return false;
}

/**
 * Set a user's default access level
 *
 * @return bool
 * @since 1.8.0
 * @access private
 */
function _elgg_set_user_default_access() {

	if (!elgg_get_config('allow_user_default_access')) {
		return false;
	}

	$default_access = get_input('default_access');
	$user_guid = get_input('guid');

	if ($user_guid) {
		$user = get_user($user_guid);
	} else {
		$user = elgg_get_logged_in_user_entity();
	}

	if ($user) {
		$current_default_access = $user->getPrivateSetting('elgg_default_access');
		if ($default_access !== $current_default_access) {
			if ($user->setPrivateSetting('elgg_default_access', $default_access)) {
				system_message(elgg_echo('user:default_access:success'));
				return true;
			} else {
				register_error(elgg_echo('user:default_access:failure'));
			}
		} else {
			// no change
			return null;
		}
	} else {
		register_error(elgg_echo('user:default_access:failure'));
	}

	return false;
}

/**
 * Set up the menu for user settings
 *
 * @return void
 * @access private
 */
function _elgg_user_settings_menu_setup() {
	$user = elgg_get_page_owner_entity();

	if (!$user) {
		return;
	}

	$params = array(
		'name' => '1_account',
		'text' => elgg_echo('usersettings:user:opt:linktext'),
		'href' => "settings/user/{$user->username}",
		'section' => 'configure',
		'contexts' => array('settings'),
	);
	elgg_register_menu_item('page', $params);
	$params = array(
		'name' => '1_plugins',
		'text' => elgg_echo('usersettings:plugins:opt:linktext'),
		'href' => "settings/plugins/{$user->username}",
		'section' => 'configure',
		'contexts' => array('settings'),
	);
	elgg_register_menu_item('page', $params);
	$params = array(
		'name' => '1_statistics',
		'text' => elgg_echo('usersettings:statistics:opt:linktext'),
		'href' => "settings/statistics/{$user->username}",
		'section' => 'configure',
		'contexts' => array('settings'),
	);
	elgg_register_menu_item('page', $params);
}

/**
 * Page handler for user settings
 *
 * @param array $page Pages array
 *
 * @return bool
 * @access private
 */
function _elgg_user_settings_page_handler($page) {
	global $CONFIG;

	if (!isset($page[0])) {
		$page[0] = 'user';
	}

	if (isset($page[1])) {
		$user = get_user_by_username($page[1]);
		elgg_set_page_owner_guid($user->guid);
	} else {
		$user = elgg_get_logged_in_user_entity();
		elgg_set_page_owner_guid($user->guid);
	}

	elgg_push_breadcrumb(elgg_echo('settings'), "settings/user/$user->username");

	switch ($page[0]) {
		case 'statistics':
			elgg_push_breadcrumb(elgg_echo('usersettings:statistics:opt:linktext'));
			$path = $CONFIG->path . "pages/settings/statistics.php";
			break;
		case 'plugins':
			elgg_push_breadcrumb(elgg_echo('usersettings:plugins:opt:linktext'));
			$path = $CONFIG->path . "pages/settings/tools.php";
			break;
		case 'user':
			$path = $CONFIG->path . "pages/settings/account.php";
			break;
	}

	if (isset($path)) {
		require $path;
		return true;
	}
	return false;
}

/**
 * Initialize the user settings library
 *
 * @return void
 * @access private
 */
function _elgg_user_settings_init() {
	elgg_register_page_handler('settings', '_elgg_user_settings_page_handler');

	elgg_register_event_handler('pagesetup', 'system', '_elgg_user_settings_menu_setup');

	elgg_register_plugin_hook_handler('usersettings:save', 'user', '_elgg_user_settings_save');

	elgg_register_action("usersettings/save");

	// extend the account settings form
	elgg_extend_view('forms/account/settings', 'core/settings/account/name', 100);
	elgg_extend_view('forms/account/settings', 'core/settings/account/password', 100);
	elgg_extend_view('forms/account/settings', 'core/settings/account/email', 100);
	elgg_extend_view('forms/account/settings', 'core/settings/account/language', 100);
	elgg_extend_view('forms/account/settings', 'core/settings/account/default_access', 100);
}

elgg_register_event_handler('init', 'system', '_elgg_user_settings_init');
