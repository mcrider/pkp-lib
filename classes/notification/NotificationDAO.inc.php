<?php

/**
 * @file classes/notification/NotificationDAO.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NotificationDAO
 * @ingroup notification
 * @see Notification
 *
 * @brief Operations for retrieving and modifying Notification objects.
 */


import('classes.notification.Notification');

class NotificationDAO extends DAO {
	/**
	 * Constructor.
	 */
	function NotificationDAO() {
		parent::DAO();
	}

	/**
	 * Retrieve Notification by notification id
	 * @param $notificationId int
	 * @return Notification object
	 */
	function &getNotificationById($notificationId) {
		$result =& $this->retrieve(
			'SELECT * FROM notifications WHERE notification_id = ?', (int) $notificationId
		);

		$notification =& $this->_returnNotificationFromRow($result->GetRowAssoc(false));

		$result->Close();
		unset($result);

		return $notification;
	}

	/**
	 * Retrieve Notifications by user id
	 * @param $userId int
	 * @return object DAOResultFactory containing matching Notification objects
	 */
	function &getNotificationsByUserId($contextId = null, $userId, $level = NOTIFICATION_LEVEL_NORMAL, $rangeInfo = null) {
		$result =& $this->retrieveRange(
			'SELECT * FROM notifications WHERE user_id = ? AND context_id = ? AND level = ? ORDER BY date_created DESC',
			array((int) $userId, (int) $contextId, (int) $level), $rangeInfo
		);

		$returner = new DAOResultFactory($result, $this, '_returnNotificationFromRow');

		return $returner;
	}

	/**
	 * Retrieve Notifications by notification id
	 * @param $notificationId int
	 * @return boolean
	 */
	function setDateRead($notificationId) {
		$returner = $this->update(
			sprintf('UPDATE notifications
				SET date_read = %s
				WHERE notification_id = ?',
				$this->datetimeToDB(Core::getCurrentDate())),
			(int) $notificationId
		);

		return $returner;
	}

	/**
	 * Creates and returns an notification object from a row
	 * @param $row array
	 * @return Notification object
	 */
	function &_returnNotificationFromRow($row) {
		$notification = new Notification();
		$notification->setId($row['notification_id']);
		$notification->setUserId($row['user_id']);
		$notification->setLevel($row['level']);
		$notification->setDateCreated($row['date_created']);
		$notification->setDateRead($row['date_read']);
		$notification->setTitle($row['title']);
		$notification->setContents($row['contents']);
		$notification->setContextId($row['context_id']);
		$notification->setType($row['row']);
		$notification->setAssocType($row['assoc_type']);
		$notification->setAssocId($row['assoc_id']);

		HookRegistry::call('NotificationDAO::_returnNotificationFromRow', array(&$notification, &$row));

		return $notification;
	}

	/**
	 * Inserts a new notification into notifications table
	 * @param Notification object
	 * @return int Notification Id
	 */
	function insertNotification(&$notification) {
		$notificationSettingsDao =& DAORegistry::getDAO('NotificationSettingsDAO');
		if ($notification->getLevel() != NOTIFICATION_LEVEL_TRIVIAL) {
			$notificationSettings = $notificationSettingsDao->getNotificationSettings($notification->getUserId());
			$notificationEmailSettings = $notificationSettingsDao->getNotificationEmailSettings($notification->getUserId());

			// FIXME #6792: this code should not be here. Send the notifications from the calling context
			// or from the NotificationManager (have the notification Manager call the DAO to insert).
			// insert should just insert.
			if(in_array($notification->getType(), $notificationEmailSettings)) {
				$this->sendNotificationEmail($notification);
			}
		}

		if($notification->getLevel() == NOTIFICATION_LEVEL_TRIVIAL || !in_array($notification->getType(), $notificationSettings)) {
			$this->update(
				sprintf('INSERT INTO notifications
						(user_id, level, date_created, title, contents, context_id, type, assoc_type, assoc_id)
					VALUES
						(?, ?, %s, ?, ?, ?, ?, ?, ?)',
					$this->datetimeToDB(Core::getCurrentDate())),
				array(
					(int) $notification->getUserId(),
					(int) $notification->getLevel(),
					$notification->getTitle(),
					$notification->getContents(),
					(int) $notification->getContextId(),
					(int) $notification->getType(),
					(int) $notification->getAssocType(),
					(int) $notification->getAssocId()
				)
			);

			$notification->setId($this->getInsertNotificationId());
			return $notification->getId();
		} else {
			return false;
		}
	}

	/**
	 * Delete Notification by notification id
	 * @param $notificationId int
	 * @return boolean
	 */
	function deleteNotificationById($notificationId, $userId = null) {
		$params = array($notificationId);
		if (isset($userId)) $params[] = $userId;

		return $this->update('DELETE FROM notifications WHERE notification_id = ?' . (isset($userId) ? ' AND user_id = ?' : ''),
			$params
		);
	}

	/**
	 * Check if the same notification was added in the last hour
	 * Will prevent multiple notifications to show up in a user's feed e.g.
	 * if a user edits a submission multiple times in a short time span
	 * @param notification object
	 * @return boolean
	 */
	function notificationAlreadyExists(&$notification) {
		$result =& $this->retrieve(
			'SELECT date_created FROM notifications WHERE user_id = ? AND title = ? AND contents = ? AND type = ? AND context_id = ? AND level = ?',
			array(
					(int) $notification->getUserId(),
					$notification->getTitle(),
					$notification->getContents(),
					(int) $notification->getType(),
					(int) $notification->getContextId(),
					(int) $notification->getLevel()
				)
		);

		$date = isset($result->fields[0]) ? $result->fields[0] : 0;

		if ($date == 0) {
			return false;
		} else {
			$timeDiff = strtotime($date) - time();
			if ($timeDiff < 3600) { // 1 hour (in seconds)
				return true;
			} else return false;
		}
	}

	/**
	 * Get the ID of the last inserted notification
	 * @return int
	 */
	function getInsertNotificationId() {
		return $this->getInsertId('notifications', 'notification_id');
	}

	/**
	 * Get the number of unread messages for a user
	 * @param $userId int
	 * @return int
	 */
	function getUnreadNotificationCount($contextId = null, $userId, $level = NOTIFICATION_LEVEL_NORMAL) {
		$result =& $this->retrieve(
			'SELECT count(*) FROM notifications WHERE user_id = ? AND date_read IS NULL AND context_id = ? AND level = ?',
			array((int) $userId, (int) $contextId, (int) $level)
		);

		$returner = $result->fields[0];

		$result->Close();
		unset($result);

		return $returner;
	}

	/**
	 * Get the number of read messages for a user
	 * @param $userId int
	 * @return int
	 */
	function getReadNotificationCount($contextId = null, $userId, $level = NOTIFICATION_LEVEL_NORMAL) {
		$result =& $this->retrieve(
			'SELECT count(*) FROM notifications WHERE user_id = ? AND date_read IS NOT NULL AND context_id = ? AND level = ?',
			array((int) $userId, (int) $contextId, (int) $level)
		);

		$returner = $result->fields[0];

		$result->Close();
		unset($result);

		return $returner;
	}

	/**
	 * Send an email to a user regarding the notification
	 * @param $notification object Notification
	 */
	function sendNotificationEmail($notification) {
		$userId = $notification->getUserId();
		$userDao =& DAORegistry::getDAO('UserDAO');
		$user = $userDao->getUser($userId);
		Locale::requireComponents(array(LOCALE_COMPONENT_APPLICATION_COMMON));

		// FIXME #6792: title and contents should only be for custom titles/content.
		// should default to predefined text by notification type and assocType/Id
		$notificationTitle = $notification->getTitle();
		$notificationContents = $notification->getContents();

		import('classes.mail.MailTemplate');
		$site =& Request::getSite();
		$mail = new MailTemplate('NOTIFICATION');
		$mail->setFrom($site->getLocalizedContactEmail(), $site->getLocalizedContactName());
		$mail->assignParams(array(
			'notificationTitle' => $notificationTitle,
			'notificationContents' => $notificationContents,
			'url' => $notification->getUrl(),
			'siteTitle' => $site->getLocalizedTitle()
		));
		$mail->addRecipient($user->getEmail(), $user->getFullName());
		$mail->send();
	}
}

?>
