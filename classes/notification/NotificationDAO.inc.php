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
	 * @return object Notification
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
	 * Note that this method will not return fully-fledged notification objects.  Use
	 *  NotificationManager::getNotificationsForUser() to get notifications with URL, and contents
	 * @param $userId int
	 * @param $level int
	 * @param $type int
	 * @param $contextId int
	 * @param $rangeInfo Object
	 * @return object DAOResultFactory containing matching Notification objects
	 */
	function &getNotificationsByUserId($userId, $level = NOTIFICATION_LEVEL_NORMAL, $type = null, $contextId = null, $rangeInfo = null) {
		$params = array((int) $userId, (int) $level);
		if ($type) $params[] = (int) $type;
		if ($contextId) $params[] = (int) $contextId;

		$result =& $this->retrieveRange(
			'SELECT * FROM notifications WHERE user_id = ? AND level = ?' . (isset($type) ?' AND type = ?' : '') . (isset($contextId) ?' AND context_id = ?' : '') . ' ORDER BY date_created DESC',
			$params, $rangeInfo
		);

		$returner = new DAOResultFactory($result, $this, '_returnNotificationFromRow');

		return $returner;
	}

	/**
	 * Retrieve Notifications by assoc.
	 * Note that this method will not return fully-fledged notification objects.  Use
	 *  NotificationManager::getNotificationsForUser() to get notifications with URL, and contents
	 * @param $assocType int
	 * @param $assocId int
	 * @param $type int
	 * @param $contextId int
	 * @return object DAOResultFactory containing matching Notification objects
	 */
	function &getNotificationsByAssoc($assocType, $assocId, $type = null, $contextId = null) {
		$params = array((int) $assocType, (int) $assocId);
		if ($contextId) $params[] = (int) $contextId;
		if ($type) $params[] = (int) $type;

		$result =& $this->retrieveRange(
			'SELECT * FROM notifications WHERE assoc_type = ? AND assoc_id = ?' . (isset($contextId) ?' AND context_id = ?' : '') . (isset($type) ?' AND type = ?' : '') . ' ORDER BY date_created DESC',
			$params
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

		return $dateRead;
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
		$notification->setDateCreated($this->datetimeFromDB($row['date_created']));
		$notification->setDateRead($this->datetimeFromDB($row['date_read']));
		$notification->setContextId($row['context_id']);
		$notification->setType($row['type']);
		$notification->setAssocType($row['assoc_type']);
		$notification->setAssocId($row['assoc_id']);

		HookRegistry::call('NotificationDAO::_returnNotificationFromRow', array(&$notification, &$row));

		return $notification;
	}

	/**
	 * Inserts a new notification into notifications table
	 * @param $notification object
	 * @return int Notification Id
	 */
	function insertNotification(&$notification) {
		$this->update(
			sprintf('INSERT INTO notifications
					(user_id, level, date_created, context_id, type, assoc_type, assoc_id)
				VALUES
					(?, ?, %s, ?, ?, ?, ?)',
				$this->datetimeToDB(Core::getCurrentDate())),
			array(
				(int) $notification->getUserId(),
				(int) $notification->getLevel(),
				(int) $notification->getContextId(),
				(int) $notification->getType(),
				(int) $notification->getAssocType(),
				(int) $notification->getAssocId()
			)
		);
		$notification->setId($this->getInsertNotificationId());

		return $notification->getId();
	}

	/**
	 * Delete Notification by notification id
	 * @param $notificationId int
	 * @param $userId int
	 * @return boolean
	 */
	function deleteNotificationById($notificationId, $userId = null) {
		$notificationSettingsDao =& DAORegistry::getDAO('NotificationSettingsDAO'); /* @var $notificationSettingsDaoDao NotificationSettingsDAO */
		$notificationSettingsDao->deleteSettingsByNotificationId($notificationId);

		return $this->update('DELETE FROM notifications WHERE notification_id = ? AND user_id = ?',
			array((int) $notificationId, (int) $userId)
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
	 * @param $read boolean Whether to check for read (true) or unread (false) notifications
	 * @param $contextId int
	 * @param $userId int
	 * @param $level int
	 * @return int
	 */
	function getNotificationCount($read = true, $userId, $contextId = null, $level = NOTIFICATION_LEVEL_NORMAL) {
		$params = array((int) $userId, (int) $level);
		if ($contextId) $params[] = (int) $contextId;

		$result =& $this->retrieve(
			'SELECT count(*) FROM notifications WHERE user_id = ? AND date_read IS' . ($read ? ' NOT' : '') . ' NULL AND level = ?'
			. (isset($contextId) ? ' AND context_id = ?' : ''),
			$params
		);

		$returner = $result->fields[0];

		$result->Close();
		unset($result);

		return $returner;
	}
}

?>
