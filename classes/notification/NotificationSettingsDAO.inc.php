<?php

/**
 * @file classes/notification/NotificationSettingsDAO.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NotificationSettingsDAO
 * @ingroup notification
 * @see Notification
 *
 * @brief Operations for retrieving and modifying user's notification settings.
 *  This class stores user settings that determine what notifications should appear
 *  to each user either in their notification feed or via email.
 *  This class also stores metadata for notifications (URL, title, contents) which
 *  we do not want automatically constructed from the notification's associated object.
 */


class NotificationSettingsDAO extends DAO {
	/**
	 * Constructor.
	 */
	function NotificationSettingsDAO() {
		parent::DAO();
	}

	/**
	 * Get an array of notification settings for a notification ID
	 * @param $notificationId int
	 * @return array
	 */
	function getNotificationSettings($notificationId) {
		$notificationSettings = array();

		$result =& $this->retrieve(
			'SELECT setting_name, setting_value FROM notification_settings WHERE notification_id = ?', (int) $notificationId
		);

		while (!$result->EOF) {
			$row =& $result->getRowAssoc(false);
			$notificationSettings[$row['setting_name']] = $row['setting_value'];
			$result->MoveNext();
		}
		$result->Close();
		unset($result);

		return $notificationSettings;
	}

	/**
	 * Update a notification setting
	 * @param $notification object
	 * @param $settingName string
	 * @param $settingValue string
	 */
	function updateNotificationSetting(&$notification, $settingName, $settingValue) {
		$this->Replace('notification_settings',
				array('notification_id' => (int) $notification->getId(),
					  'context' => (int) $notification->getContextId(),
					  'user_id' => (int) $notification->getUserId(),
					  'setting_name' => $settingName,
					  'setting_value' => $settingValue),
				array('notification_id', 'setting_name'));
	}

	/**
	 * Delete a notification setting by setting name
	 * @param $notificationId int
	 * @param $settingName string optional
	 */
	function deleteNotificationSettings($notificationId, $settingName = null) {
		$params = array((int) $notificationId);
		if ($settingName) $params[] = $settingName;

		return $this->update(
			'DELETE FROM notification_settings
			WHERE notification_id= ?' . isset($settingName) ? '  AND setting_name = ?' : '',
			$params
		);
	}

	/**
	 * Retrieve Notifications settings by user id
	 * Returns an array of notification types that the user
	 * does NOT want to be notified of
	 * @param $userId int
	 * @param $contextId int
	 * @return array
	 */
	function &getBlockedNotificationTypes($userId, $contextId) {
		$blockedNotifications = array();

		$result =& $this->retrieve(
			'SELECT setting_value FROM notification_settings WHERE user_id = ? AND setting_name = ? AND context = ?',
				array((int) $userId, 'notify', (int) $contextId)
		);

		while (!$result->EOF) {
			$row = $result->GetRowAssoc(false);
			$blockedNotifications[] = (int) $row['setting_value'];
			$result->moveNext();
		}

		$result->Close();
		unset($result);

		return $blockedNotifications;
	}

	/**
	 * Retrieve Notifications email settings by user id
	 * Returns an array of notification types that the user
	 * DOES want to be emailed about
	 * @param $userId int
	 * @param $contextId int
	 * @return array
	 */
	function &getNotificationEmailSettings($userId, $contextId) {
		$emailSettings = array();

		$result =& $this->retrieve(
			'SELECT setting_value FROM notification_settings WHERE user_id = ? AND setting_name = ? AND context = ?',
				array((int) $userId, 'email', (int) $contextId)
		);

		while (!$result->EOF) {
			$row = $result->GetRowAssoc(false);
			$emailSettings[] = (int) $row['setting_value'];
			$result->moveNext();
		}

		$result->Close();
		unset($result);

		return $emailSettings;
	}

	/**
	 * Update a user's notification settings
	 * @param $blockedNotifications array
	 * @param $userId int
	 * @param $contextId int
	 */
	function updateBlockedNotificationTypes($blockedNotifications, $userId, $contextId) {
		// Delete old settings first, then insert new settings
		$this->update('DELETE FROM notification_settings WHERE user_id = ? AND setting_name = ? AND context = ?',
			array((int) $userId, 'notify', (int) $contextId));

		for ($i=0; $i<count($blockedNotifications); $i++) {
			$this->update(
				'INSERT INTO notification_settings
					(setting_name, setting_value, user_id, context)
					VALUES
					(?, ?, ?, ?)',
				array(
					"notify",
					$blockedNotifications[$i],
					(int) $userId,
					(int) $contextId
				)
			);
		}
	}

	/**
	 * Update a user's notification email settings
	 * @param $notificationEmailSettings array
	 * @param $userId int
	 * @param $contextId int
	 */
	function updateNotificationEmailSettings($emailSettings, $userId, $contextId) {
		$application =& PKPApplication::getApplication();
		$productName = $application->getName();
		$context =& Request::getContext();
		$contextId = $context?$context->getId():0;

		// Delete old settings first, then insert new settings
		$this->update('DELETE FROM notification_settings WHERE user_id = ? AND setting_name = ? AND context = ?',
			array((int) $userId, 'email', (int) $contextId));

		for ($i=0; $i<count($emailSettings); $i++) {
			$this->update(
				'INSERT INTO notification_settings
					(setting_name, setting_value, user_id, context)
					VALUES
					(?, ?, ?, ?)',
				array(
					"email",
					$emailSettings[$i],
					(int) $userId,
					(int) $contextId
				)
			);
		}
	}

	/**
	 * Gets a user id by an RSS token value
	 * @param $token int
	 * @param $contextId
	 * @return int
	 */
	function getUserIdByRSSToken($token, $contextId) {
		$result =& $this->retrieve(
			'SELECT user_id FROM notification_settings WHERE setting_value = ? AND setting_name = ? AND context = ?',
				array($token, 'token', (int) $contextId)
		);

		$row = $result->GetRowAssoc(false);
		$userId = $row['user_id'];

		$result->Close();
		unset($result);

		return $userId;
	}

	/**
	 * Gets an RSS token for a user id
	 * @param $userId int
	 * @param $contextId int
	 * @return int
	 */
	function getRSSTokenByUserId($userId, $contextId) {
		$result =& $this->retrieve(
			'SELECT setting_value FROM notification_settings WHERE user_id = ? AND setting_name = ? AND context = ?',
				array((int) $userId, 'token', (int) $contextId)
		);

		$row = $result->GetRowAssoc(false);
		$tokenId = $row['setting_value'];

		$result->Close();
		unset($result);

		return $tokenId;
	}

	/**
	 * Generates and inserts a new token for a user's RSS feed
	 * @param $userId int
	 * @param $contextId int
	 * @return int
	 */
	function insertNewRSSToken($userId, $contextId) {
		$token = uniqid(rand());

		$this->update(
			'INSERT INTO notification_settings
				(setting_name, setting_value, user_id, context)
				VALUES
				(?, ?, ?, ?)',
			array(
				'token',
				$token,
				(int) $userId,
				(int) $contextId
			)
		);

		return $token;
	}

	/**
	 * Generates an access key for the guest user and adds them to the settings table
	 * @param $userId int
	 * @param $contextId int
	 * @return int
	 */
	function subscribeGuest($email, $contextId) {
		// Check that the email doesn't already exist
		$result =& $this->retrieve(
			'SELECT * FROM notification_settings WHERE setting_name = ? AND setting_value = ? AND context = ?',
			array(
				'mailList',
				$email,
				(int) $contextId
			)
		);

		if ($result->RecordCount() != 0) {
			return false;
		} else {
			$this->update(
				'DELETE FROM notification_settings WHERE setting_name = ? AND setting_value = ? AND user_id = ? AND context = ?',
				array(
					'mailListUnconfirmed',
					$email,
					0,
					(int) $contextId
				)
			);
			$this->update(
				'INSERT INTO notification_settings
					(setting_name, setting_value, user_id, context)
					VALUES
					(?, ?, ?, ?)',
				array(
					'mailListUnconfirmed',
					$email,
					0,
					(int) $contextId
				)
			);
		}

		// Get assoc_id into notification_settings table, also used as user_id for access key
		$assocId = $this->getInsertNotificationSettingId();

		import('lib.pkp.classes.security.AccessKeyManager');
		$accessKeyManager = new AccessKeyManager();

		$password = $accessKeyManager->createKey('MailListContext', $assocId, $assocId, 60); // 60 days
		return $password;
	}

	/**
	 * Removes an email address and associated access key from email notifications
	 * @param $email string
	 * @param $password string
	 * @param $contextId int
	 * @return boolean
	 */
	function unsubscribeGuest($email, $password, $contextId) {
		$result =& $this->retrieve(
			'SELECT setting_id FROM notification_settings WHERE setting_name = ? AND context = ?',
			array(
				'mailList',
				(int) $contextId
			)
		);

		$row = $result->GetRowAssoc(false);
		$userId = (int) $row['setting_id'];

		$result->Close();
		unset($result);

		import('lib.pkp.classes.security.AccessKeyManager');
		$accessKeyManager = new AccessKeyManager();
		$accessKeyHash = AccessKeyManager::generateKeyHash($password);
		$accessKey = $accessKeyManager->validateKey('MailListContext', $userId, $accessKeyHash);

		if ($accessKey) {
			$this->update(
				'DELETE FROM notification_settings WHERE setting_name = ? AND setting_value = ? AND context = ?',
				array(
					'mailList',
					$email,
					(int) $contextId
				)
			);
			$accessKeyDao =& DAORegistry::getDAO('AccessKeyDAO');
			$accessKeyDao->deleteObject($accessKey);
			return true;
		} else return false;
	}

	/**
	 * Gets the setting id for a maillist member (to access the accompanying access key)
	 * @param $email string
	 * @param $settingName string
	 * @param $contextId int
	 * @return array
	 */
	function getMailListSettingId($email, $settingName = 'mailListUnconfirmed') {
		$application =& PKPApplication::getApplication();
		$productName = $application->getName();
		$context =& Request::getContext();
		$contextId = $context?$context->getId():0;

		$result =& $this->retrieve(
			'SELECT setting_id FROM notification_settings WHERE setting_name = ? AND setting_value = ? AND context = ?',
			array(
				$settingName,
				$email,
				(int) $contextId
			)
		);

		$row = $result->GetRowAssoc(false);
		$settingId = (int) $row['setting_id'];

		return $settingId;
	}

	/**
	 * Update the notification settings table to confirm the mailing list subscription
	 * @param $settingId int
	 * @return boolean
	 */
	function confirmMailListSubscription($settingId) {
		return $this->update(
			'UPDATE notification_settings SET setting_name = ? WHERE setting_id = ?',
			array('mailList', (int) $settingId)
		);
	}

	/**
	 * Gets a list of email addresses of users subscribed to the mailing list
	 * @return array
	 */
	function getMailList($contextId) {
		$mailList = array();

		$result =& $this->retrieve(
			'SELECT setting_value FROM notification_settings WHERE setting_name = ? AND context = ?',
			array(
				'mailList',
				(int) $contextId
			)
		);

		while (!$result->EOF) {
			$row = $result->GetRowAssoc(false);
			$mailList[] = $row['setting_value'];
			$result->moveNext();
		}

		$result->Close();
		unset($result);

		return $mailList;
	}

	/**
	 * Generates and inserts a new password for a mailing list user
	 * @param $email string
	 * @param $contextId int
	 * @return string
	 */
	function resetPassword($email, $contextId) {
		$result =& $this->retrieve(
			'SELECT setting_id FROM notification_settings WHERE setting_name = ? AND setting_value = ? AND context = ?',
			array(
				'mailList',
				$email,
				(int) $contextId
			)
		);

		$row = $result->GetRowAssoc(false);
		$settingId = $row['setting_id'];

		$result->Close();
		unset($result);

		$accessKeyDao =& DAORegistry::getDAO('AccessKeyDAO');
		$accessKey = $accessKeyDao->getAccessKeyByUserId('MailListContext', $settingId);

		if ($accessKey) {
			$key = Validation::generatePassword();
			$accessKey->setKeyHash(md5($key));

			$accessKeyDao =& DAORegistry::getDAO('AccessKeyDAO');
			$accessKeyDao->updateObject($accessKey);
			return $key;
		} else return false;
	}

	/**
	 * Get the ID of the last inserted notification
	 * @return int
	 */
	function getInsertNotificationSettingId() {
		return $this->getInsertId('notification_settings', 'setting_id');
	}

}

?>
