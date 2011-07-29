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
	 * @param $contextId int
	 * @param $userId int
	 * @param $level int
	 * @param $rangeInfo Object
	 * @return object DAOResultFactory containing matching Notification objects
	 */
	function &getNotificationsByUserId($contextId = null, $userId, $level = NOTIFICATION_LEVEL_NORMAL, $rangeInfo = null) {
		$params = array((int) $userId, (int) $level);
		if ($contextId) $params[] = (int) $contextId;

		$result =& $this->retrieveRange(
			'SELECT * FROM notifications WHERE user_id = ? AND level = ?' . (isset($contextId) ?' AND context_id = ?' : '') . ' ORDER BY date_created DESC',
			$params, $rangeInfo
		);

		$returner = new DAOResultFactory($result, $this, '_returnNotificationFromRow');

		return $returner;
	}

	/**
	 * Retrieve Notifications by notification id
	 * @param $notificationId int
	 * @param $dateRead date
	 * @return boolean
	 */
	function setDateRead($notificationId, $dateRead = null) {
		$dateRead = isset($dateRead) ? $dateRead : Core::getCurrentDate();

		$returner = $this->update(
			sprintf('UPDATE notifications
				SET date_read = %s
				WHERE notification_id = ?',
				$this->datetimeToDB($dateRead)),
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
		$notification->setType($row['type']);
		$notification->setAssocType($row['assoc_type']);
		$notification->setAssocId($row['assoc_id']);

		// If the notification has not been read, set the date read to now
		if (!$notification->getDateRead()) {
			$this->setDateRead($notification->getId());
		}

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
		$blockedNotifications = $notificationSettingsDao->getBlockedNotificationTypes($notification->getUserId());

		if($notification->getLevel() == NOTIFICATION_LEVEL_TRIVIAL || !in_array($notification->getType(), $blockedNotifications)) {
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
	function deleteNotificationById($notificationId, $userId) {
		$params = array($notificationId);
		if (isset($userId)) $params[] = $userId;

		return $this->update('DELETE FROM notifications WHERE notification_id = ? AND user_id = ?',
			$params
		);
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
}

?>
