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
	 * @param $title string
	 * @param $contents string
	 * @param $param string
	 * @param $isLocalized boolean
	 * @return Notification object
	 */
	function createTrivialNotification($title, $contents, $assocType = NOTIFICATION_TYPE_SUCCESS, $param = null, $isLocalized = 1) {
		$notification = new Notification();
		$context =& Request::getContext();
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
	 * Send an update to all users on the mailing list
	 * @param $notification object Notification
	 */
	function sendToMailingList($notification) {
		$notificationSettingsDao =& DAORegistry::getDAO('NotificationSettingsDAO');
		$mailList = $notificationSettingsDao->getMailList();
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
