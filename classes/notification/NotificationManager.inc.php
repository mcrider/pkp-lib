<?php

/**
 * @file classes/notification/NotificationManager.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NotificationManager
 * @ingroup notification
 * @see NotificationDAO
 * @see Notification
 * @brief Class for Notification Manager.
 */


import('classes.notification.Notification');

class NotificationManager {
	/**
	 * Constructor.
	 */
	function NotificationManager() {
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
	 * @return Notification object
	 */
	function createNotification($userId, $notificationType, $contextId = null, $assocType, $assocId, $level = NOTIFICATION_LEVEL_NORMAL) {
		$contextId = $contextId? (int) $contextId: 0;

		$notification = new Notification();
		$notification->setUserId((int) $userId);
		$notification->setType((int) $notificationType);
		$notification->setContextId((int) $contextId);
		$notification->setAssocType((int) $assocType);
		$notification->setAssocId((int) $assocId);
		$notification->setLevel((int) $level);

		$notificationDao =& DAORegistry::getDAO('NotificationDAO');
		$notificationDao->insertNotification($notification);

		// Send notification emails
		if ($notification->getLevel() != NOTIFICATION_LEVEL_TRIVIAL) {
			$notificationSettingsDao =& DAORegistry::getDAO('NotificationSettingsDAO');
			$notificationEmailSettings = $notificationSettingsDao->getNotificationEmailSettings($userId);

			if(in_array($notificationType, $notificationEmailSettings)) {
				$this->sendNotificationEmail($notification);
			}
		}

		return $notification;
	}

	/**
	 * Create a new notification with the specified arguments and insert into DB
	 * This is a static method
	 * @param $title string
	 * @param $contents string
	 * @param $param string
	 * @param $isLocalized boolean
	 * @return Notification object
	 */
	function createTrivialNotification($title, $contents, $assocType = NOTIFICATION_TYPE_SUCCESS, $param = null, $isLocalized = 1) {
		$contextId = $context?$context->getId():0;

		$user =& Request::getUser();
		$notification->setUserId($user->getId());
		$notification->setTitle($title);
		$notification->setContents($contents);
		$notification->setParam($param);
		$notification->setIsLocalized($isLocalized);
		$notification->setContext($contextId);
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
}

?>
