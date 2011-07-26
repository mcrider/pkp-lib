<?php

/**
 * @file classes/notification/Notification.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Notification
 * @ingroup notification
 * @see NotificationDAO
 * @brief Class for Notification.
 */


import('lib.pkp.classes.notification.NotificationDAO');

define('NOTIFICATION_LEVEL_TRIVIAL',				0x0000001);
define('NOTIFICATION_LEVEL_NORMAL',					0x0000002);
define('NOTIFICATION_LEVEL_TASK', 					0x0000003);

/** Notification associative types. */
define('NOTIFICATION_TYPE_SUCCESS', 				0x0000001);
define('NOTIFICATION_TYPE_WARNING', 				0x0000002);
define('NOTIFICATION_TYPE_ERROR', 				0x0000003);
define('NOTIFICATION_TYPE_FORBIDDEN', 				0x0000004);
define('NOTIFICATION_TYPE_INFORMATION',				0x0000005);
define('NOTIFICATION_TYPE_HELP', 				0x0000006);

class PKPNotification extends DataObject {
	/* @var $_initialized bool */
	var $_initialized = false;

	/**
	 * Constructor.
	 */
	function PKPNotification() {
		parent::DataObject();
	}

	/**
	 * get notification id
	 * @return int
	 */
	function getNotificationId() {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->getId();
	}

	/**
	 * set notification id
	 * @param $commentId int
	 */
	function setNotificationId($notificationId) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->setId($notificationId);
	}

	/**
	 * get user id associated with this notification
	 * @return int
	 */
	function getUserId() {
		return $this->getData('userId');
	}

	/**
	 * set user id associated with this notification
	 * @param $userId int
	 */
	function setUserId($userId) {
		return $this->setData('userId', $userId);
	}

	/**
	 * Get the level (NOTIFICATION_LEVEL_...) for this notification
	 * @return int
	 */
	function getLevel() {
		return $this->getData('level');
	}

	/**
	 * Set the level (NOTIFICATION_LEVEL_...) for this notification
	 * @param $level int
	 */
	function setLevel($level) {
		return $this->setData('level', $level);
	}

	/**
	 * get date notification was created
	 * @return date (YYYY-MM-DD HH:MM:SS)
	 */
	function getDateCreated() {
		return $this->getData('dateCreated');
	}

	/**
	 * set date notification was created
	 * @param $dateCreated date (YYYY-MM-DD HH:MM:SS)
	 */
	function setDateCreated($dateCreated) {
		return $this->setData('dateCreated', $dateCreated);
	}

	/**
	 * get date notification is read by user
	 * @return date (YYYY-MM-DD HH:MM:SS)
	 */
	function getDateRead() {
		return $this->getData('dateRead');
	}

	/**
	 * set date notification is read by user
	 * Also sets setisUnread() if $dateRead is null
	 * @param $dateRead date (YYYY-MM-DD HH:MM:SS)
	 */
	function setDateRead($dateRead) {
		return $this->setData('dateRead', $dateRead);
	}

	/**
	 * return true if reading for the first time
	 * @return bool
	 */
	function getIsUnread() {
		$dateRead = $this->getDateRead();
		return !empty($dateRead);
	}

	/**
	 * Get notification title.
	 * @return string
	 */
	function getTitle() {
		if (!$this->getData('title')) $this->_initialize();
		return $this->getData('title');
	}

	/**
	 * Set notification title. Allow for an override param so that initialization does not erase user values.
	 * @param $title int
	 * @param $override bool
	 */
	function setTitle($title, $override = true) {
		if ($override) $this->setData('title', $title);
	}

	/**
	 * Get notification contents
	 * @return string
	 */
	function getContents() {
		if (!$this->getData('title')) $this->_initialize();
		return $this->getData('contents');
	}

	/**
	 * Set notification contents. Allow for an override param so that initialization does not erase user values.
	 * @param $contents int
	 * @param $override bool
	 */
	function setContents($contents, $override = true) {
		if ($override) $this->setData('contents', $contents);
	}

	/**
	 * get URL that notification refers to
	 * @param $request Request
	 * @return int
	 */
	function getUrl($request) {
		$baseUrl = $request->getBaseUrl();
		$assocType = $this->getAssocType();
		switch ($assocType) {
			case ASSOC_TYPE_USER:
			case ASSOC_TYPE_USER_GROUP:
			case ASSOC_TYPE_CITATION:
			case ASSOC_TYPE_AUTHOR:
			case ASSOC_TYPE_EDITOR:
			default:
				return $baseUrl;
		}
	}

	/**
	 * get notification type
	 * @return int
	 */
	function getType() {
		return $this->getData('type');
	}

	/**
	 * set notification type
	 * @param $type int
	 */
	function setType($type) {
		return $this->setData('type', $type);
	}

	/**
	 * get notification type
	 * @return int
	 */
	function getAssocType() {
		return $this->getData('assocType');
	}

	/**
	 * set notification type
	 * @param $assocType int
	 */
	function setAssocType($assocType) {
		return $this->setData('assocType', $assocType);
	}

	/**
	 * get notification assoc id
	 * @return int
	 */
	function getAssocId() {
		return $this->getData('assocId');
	}

	/**
	 * set notification assoc id
	 * @param $assocId int
	 */
	function setAssocId($assocId) {
		return $this->setData('assocId', $assocId);
	}

	/**
	 * get context id
	 * @return int
	 */
	function getContextId() {
		return $this->getData('context_id');
	}

	/**
	 * set context id
	 * @param $context int
	 */
	function setContextId($contextId) {
		return $this->setData('context_id', $contextId);
	}

	/**
	 * FIXME #6792 move these to CSS. maybe leave a getStyleClass, but no icons in the PHP side.
	 * get notification style class
	 * @return string
	 */
	function getStyleClass() {
		switch ($this->getAssocType()) {
			case NOTIFICATION_TYPE_SUCCESS: return 'notifySuccess';
			case NOTIFICATION_TYPE_WARNING: return 'notifyWarning';
			case NOTIFICATION_TYPE_ERROR: return 'notifyError';
			case NOTIFICATION_TYPE_INFO: return 'notifyInfo';
			case NOTIFICATION_TYPE_FORBIDDEN: return 'notifyForbidden';
			case NOTIFICATION_TYPE_HELP: return 'notifyHelp';
		}
	}

	/**
	 * FIXME #6792 see above fixme.
	 * get notification icon style class
	 * @return string
	 */
	function getIconClass() {
		switch ($this->getAssocType()) {
			case NOTIFICATION_TYPE_SUCCESS: return 'notifyIconSuccess';
			case NOTIFICATION_TYPE_WARNING: return 'notifyIconWarning';
			case NOTIFICATION_TYPE_ERROR: return 'notifyIconError';
			case NOTIFICATION_TYPE_INFO: return 'notifyIconInfo';
			case NOTIFICATION_TYPE_FORBIDDEN: return 'notifyIconForbidden';
			case NOTIFICATION_TYPE_HELP: return 'notifyIconHelp';
		}
	}

	/**
	 * FIXME: #6792 see above.
	 * return the path to the icon for this type
	 * @return string
	 */
	function getIconLocation() {
		die ('ABSTRACT CLASS');
	}

	/**
	 * Initialize the members that are type dependent
	 * @return void
	 */
	function _initialize() {
		if ($this->_initialized) return true;
		$type = $this->getType();
		assert(isset($type));
		switch ($type) {
			case NOTIFICATION_TYPE_SUCCESS:
				$successMessage = __('common.changesSaved');
				$this->setTitle($successMessage, false);
				$this->setContent($successMessage, false);
				break;
			case NOTIFICATION_TYPE_WARNING:
				// FIXME #6792 None of these types are used anywhere and thus have no keys defined
				// We can remove them all perhaps? Until someone needs them? Or add keys for them.
				break;
			case NOTIFICATION_TYPE_ERROR:
				break;
			case NOTIFICATION_TYPE_FORBIDDEN:
				break;
			case NOTIFICATION_TYPE_INFORMATION:
				break;
			case NOTIFICATION_TYPE_HELP:
				break;
		}

		$this->_initialized = true;
	}
}

?>
