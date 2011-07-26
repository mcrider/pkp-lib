<?php

/**
 * @file classes/notification/PKPNotificationManager.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPNotificationManager
 * @ingroup notification
 * @see NotificationDAO
 * @see Notification
 * @brief Class for Notification Manager.
 */


import('classes.notification.Notification');

class PKPNotificationManager {
	/**
	 * Constructor.
	 */
	function PKPNotificationManager() {
	}

	/**
	 * @param $request PKPRequest
	 * @param $userId int
	 * @param $level int
	 * @param $contextId int
	 * @return object DAOResultFactory containing matching Notification objects
	 */
	function getNotificationsForUser(&$request, $userId, $level = NOTIFICATION_LEVEL_NORMAL, $contextId = null, $rangeInfo = null) {
		$notificationDao =& DAORegistry::getDAO('NotificationDAO');
		$notifications = $notificationDao->getNotificationsByUserId($contextId, $userId, $level, $rangeInfo);

		// Build out the notification based on the associated object
		$notifications =& $notifications->toArray(); // Cast to array so we can manipulate the notification objects
		foreach($notifications as $notification) {
			// First, check if the notification already has its URL or
			//  contents set (via the settings table).  If it does, skip automatically
			//  populating those values.
			if(!$notification->getUrl()) $notification->setUrl($this->getNotificationUrl($request, $notification));
			if(!$notification->getContents()) $notification->setContents($this->getNotificationContents($request, $notification));
		}

		// Return the notifications as an ItemIterator so we can have pagination
		import('lib.pkp.classes.core.ArrayItemIterator');
		return ArrayItemIterator::fromRangeInfo($notifications, $rangeInfo);
	}

	/**
	 * Construct a URL for the notification based on its type and associated object
	 * @param $request PKPRequest
	 * @param $notification Notification
	 * @return string
	 */
	function getNotificationUrl(&$request, &$notification) {
		return null;
	}

	/**
	 * Construct the contents for the notification based on its type and associated object
	 * @param $request PKPRequest
	 * @param $notification Notification
	 * @return string
	 */
	function getNotificationContents(&$request, &$notification) {
		$type = $notification->getType();
		assert(isset($type));

		switch ($type) {
			case NOTIFICATION_TYPE_SUCCESS:
				$contents = __('common.changesSaved');
				break;
			default:
				$contents = null;
		}

		return $contents;
	}

	/**
	 * Create a new notification with the specified arguments and insert into DB
	 * This is a static method
	 * @param $userId int
	 * @param $notificationType int
	 * @param $contextId int
	 * @param $assocType int
	 * @param $assocId int
	 * @param $level int
	 * @param $contents string Override the notification's default contents
	 * @param $url string Override the notification's default URL
	 * @return Notification object
	 */
	function createNotification($userId, $notificationType, $contextId = null, $assocType, $assocId, $level = NOTIFICATION_LEVEL_NORMAL, $contents = null, $url = null) {
		$contextId = $contextId? (int) $contextId: 0;

		// Get set of notifications user does not want to be notified of
		$notificationSettingsDao =& DAORegistry::getDAO('NotificationSettingsDAO');
		$blockedNotifications = $notificationSettingsDao->getBlockedNotificationTypes($userId, $contextId);

		if($level == NOTIFICATION_LEVEL_TRIVIAL || !in_array($notificationType, $blockedNotifications)) {
			$notification = new Notification();
			$notification->setUserId((int) $userId);
			$notification->setType((int) $notificationType);
			$notification->setContextId((int) $contextId);
			$notification->setAssocType((int) $assocType);
			$notification->setAssocId((int) $assocId);
			$notification->setLevel((int) $level);

			// If we have custom values for contents, or url, set them so we can store the values in the settings table
			if ($contents) $notification->setContents($contents);
			if ($url) $notification->setUrl($url);

			$notificationDao =& DAORegistry::getDAO('NotificationDAO');
			$notificationDao->insertNotification($notification);

			// Send notification emails
			if ($notification->getLevel() != NOTIFICATION_LEVEL_TRIVIAL) {
				$notificationSettingsDao =& DAORegistry::getDAO('NotificationSettingsDAO');
				$notificationEmailSettings = $notificationSettingsDao->getNotificationEmailSettings($userId, $contextId);

				if(in_array($notificationType, $notificationEmailSettings)) {
					$this->sendNotificationEmail($notification);
				}
			}

			return $notification;
		}
	}

	/**
	 * Create a new notification with the specified arguments and insert into DB
	 * This is a static method
	 * @param $contents string
	 * @param $param string
	 * @param $isLocalized boolean
	 * @return Notification object
	 */
	function createTrivialNotification($contents, $assocType = NOTIFICATION_TYPE_SUCCESS, $param = null, $isLocalized = 1) {
		$user =& Request::getUser();
		$notification = new Notification();
		$notification->setUserId($user->getId());
		$notification->setContents($contents);
		$notification->setParam($param);
		$notification->setIsLocalized($isLocalized);
		$notification->setContext(0);
		$notification->setAssocType($assocType);
		$notification->setLevel(NOTIFICATION_LEVEL_TRIVIAL);

		$notificationDao =& DAORegistry::getDAO('NotificationDAO');
		$notificationDao->insertNotification($notification);

		return $notification;
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

		$notificationContents = $notification->getContents();

		import('classes.mail.MailTemplate');
		$site =& Request::getSite();
		$mail = new MailTemplate('NOTIFICATION');
		$mail->setFrom($site->getLocalizedContactEmail(), $site->getLocalizedContactName());
		$mail->assignParams(array(
			'notificationContents' => $notificationContents,
			'url' => $notification->getUrl(),
			'siteTitle' => $site->getLocalizedTitle()
		));
		$mail->addRecipient($user->getEmail(), $user->getFullName());
		$mail->send();
	}

	/**
	 * Send an update to all users on the mailing list
	 * @param $notification object Notification
	 */
	function sendToMailingList($notification) {
		$notificationSettingsDao =& DAORegistry::getDAO('NotificationSettingsDAO');
		$mailList = $notificationSettingsDao->getMailList($notification->getContextId());
		Locale::requireComponents(array(LOCALE_COMPONENT_APPLICATION_COMMON));

		foreach ($mailList as $email) {
			if ($notification->getIsLocalized()) {
				$params = array('param' => $notification->getParam());
				$notificationContents = Locale::translate($notification->getContents(), $params);
			} else {
				$notificationContents = $notification->getContents();
			}

			import('classes.mail.MailTemplate');
			$context =& Request::getContext();
			$site =& Request::getSite();

			$mail = new MailTemplate('NOTIFICATION_MAILLIST');
			$mail->setFrom($site->getLocalizedContactEmail(), $site->getLocalizedContactName());
			$mail->assignParams(array(
				'notificationContents' => $notificationContents,
				'url' => $notification->getLocation(),
				'siteTitle' => $context->getLocalizedTitle(),
				'unsubscribeLink' => Request::url(null, 'notification', 'unsubscribeMailList')
			));
			$mail->addRecipient($email);
			$mail->send();
		}
	}

	/**
	 * Static function to send an email to a mailing list user regarding signup or a lost password
	 * @param $email string
	 * @param $password string the user's password
	 * @param $template string The mail template to use
	 */
	function sendMailingListEmail($email, $password, $template) {
		import('classes.mail.MailTemplate');
		$press = Request::getPress();
		$site = Request::getSite();

		$params = array(
			'password' => $password,
			'siteTitle' => $press->getLocalizedTitle(),
			'unsubscribeLink' => Request::url(null, 'notification', 'unsubscribeMailList')
		);

		if ($template == 'NOTIFICATION_MAILLIST_WELCOME') {
			$keyHash = md5($password);
			$confirmLink = Request::url(null, 'notification', 'confirmMailListSubscription', array($keyHash, $email));
			$params["confirmLink"] = $confirmLink;
		}

		$mail = new MailTemplate($template);
		$mail->setFrom($site->getLocalizedContactEmail(), $site->getLocalizedContactName());
		$mail->assignParams($params);
		$mail->addRecipient($email);
		$mail->send();
	}
}

?>
