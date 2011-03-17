<?php
/**
 * @defgroup controllers_api_user
 */

/**
 * @file controllers/api/user/UserApiHandler.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UserApiHandler
 * @ingroup controllers_api_user
 *
 * @brief Class defining the headless AJAX API for backend user manipulation.
 */

// import the base Handler
import('lib.pkp.classes.handler.PKPHandler');

// import JSON class for API responses
import('lib.pkp.classes.core.JSON');

class UserApiHandler extends PKPHandler {
	/**
	 * Constructor.
	 */
	function UserApiHandler() {
		parent::PKPHandler();
	}


	//
	// Implement template methods from PKPHandler
	//
	/**
	 * @see PKPHandler::authorize()
	 */
	function authorize(&$request, $args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.PKPSiteAccessPolicy');
		$this->addPolicy(new PKPSiteAccessPolicy($request,
				array('updateUserConfirmMessageVisibility'), SITE_ACCESS_ALL_ROLES));
		return parent::authorize($request, $args, $roleAssignments);
	}


	//
	// Public handler methods
	//
	/**
	 * Update the information whether user messages should be
	 * displayed or not.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string a JSON message
	 * FIXME: Rename this method to make it clear that we do not intend to expose
	 * the user_settings table remotely but only provide a "remote transaction"
	 * with side effects in the database like all other handler operations do. A
	 * better name may be updateUserMessageState() or something like that.
	 */
	function updateUserConfirmMessageVisibility($args, &$request) {
		// Exit with a fatal error if request parameters are missing.
		if (!(isset($args['setting-name'])) && isset($args['setting-value'])) {
			fatalError('Required request parameter "setting-name" or "setting-value" missing!');
		}

		// Retrieve the user from the session.
		$user =& $request->getUser();
		assert(is_a($user, 'User'));

		// Validate the setting.
		$settingName = $args['setting-name'];
		$settingValue = $args['setting-value'];
		$validSetting = $this->_validateSettingName($settingName);
		if($validSetting) {
			if (!($settingValue === 'false' || $settingValue === 'true')) {
				// Exit with a fatal error when the setting value is invalid.
				fatalError('Invalid setting value! Must be "true" or "false".');
			}
			$settingValue = ($settingValue === 'true' ? true : false);
		} else {
			// Exit with a fatal error when an unknown setting is found.
			fatalError('Unknown setting!');
			return $json->getString();
		}

		// Persist the validated setting.
		$userSettingsDAO =& DAORegistry::getDAO('UserSettingsDAO');
		$userSettingsDAO->updateSetting($user->getId(), $settingName, $settingValue, 'bool');

		// Return a success message.
		$json = new JSON(true);
		return $json->getString();

	}

	/**
	 * Checks the requested setting against a whitelist of
	 * settings that can be changed remotely.
	 * @param $settingName string
	 * @return boolean Whether this is a valid setting type.
	 */
	function _validateSettingName($settingName) {
		// Settings whitelist.
		static $allowedSettings = array(
			'citation-editor-hide-intro',
			'citation-editor-hide-raw-editing-warning',
			'prepared-emails-hide-reset-all'
		);

		if (in_array($settingName, $allowedSettings)) {
			return true;
		} else {
			return false;
		}
	}
}
?>