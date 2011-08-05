<?php

/**
 * @file NotificationHandler.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NotificationHandler
 * @ingroup pages_help
 *
 * @brief Handle requests for viewing notifications.
 */

import('classes.handler.Handler');
import('classes.notification.Notification');

class NotificationHandler extends Handler {

	/**
	 * Display help table of contents.
	 * @param $args array
	 * @param $request Request
	 */
	function index($args, &$request) {
		$this->validate();
		$this->setupTemplate();
		$templateMgr =& TemplateManager::getManager();
		$router =& $request->getRouter();

		$user = $request->getUser();
		if(isset($user)) {
			$userId = $user->getId();
			$templateMgr->assign('isUserLoggedIn', true);
		} else {
			$userId = 0;

			$templateMgr->assign('emailUrl', $router->url($request, null, 'notification', 'subscribeMailList'));
			$templateMgr->assign('isUserLoggedIn', false);
		}
		$context =& $request->getContext();
		$contextId = isset($context)?$context->getId():null;

		import('classes.notification.NotificationManager');
		$notificationManager = new NotificationManager();

		$rangeInfo =& Handler::getRangeInfo('notifications');
		$notifications = $notificationManager->getNotificationsForUser($request, $userId, NOTIFICATION_LEVEL_NORMAL, $contextId, $rangeInfo);

		$notificationDao =& DAORegistry::getDAO('NotificationDAO');
		$templateMgr->assign('notifications', $notifications);
		$templateMgr->assign('request', $request);
		$templateMgr->assign('unread', $notificationDao->getUnreadNotificationCount($contextId, $userId));
		$templateMgr->assign('read', $notificationDao->getReadNotificationCount($contextId, $userId));
		$templateMgr->assign('url', $router->url($request, null, 'notification', 'settings'));
		$templateMgr->display('notification/index.tpl');
	}

	/**
	 * Delete a notification
	 * @param $args array
	 * @param $request Request
	 */
	function delete($args, &$request) {
		$this->validate();

		$notificationId = array_shift($args);
		if (array_shift($args) == 'ajax') {
			$isAjax = true;
		} else $isAjax = false;

		$user = $request->getUser();
		if(isset($user)) {
			$userId = $user->getId();
			$context =& $request->getContext();
			$contextId = isset($context)?$context->getId():null;

			$notificationDao =& DAORegistry::getDAO('NotificationDAO');
			$notificationDao->deleteNotificationById((int) $notificationId, $userId);
		}

		if (!$isAjax) {
			$router =& $request->getRouter();
			$request->redirectUrl($router->url($request, null, 'notification'));
		}
	}

	/**
	 * View and modify notification settings
	 * @param $args array
	 * @param $request Request
	 */
	function settings($args, &$request) {
		$this->validate();
		$this->setupTemplate();


		$user = $request->getUser();
		if(isset($user)) {
			import('classes.notification.form.NotificationSettingsForm');
			$notificationSettingsForm = new NotificationSettingsForm();
			$notificationSettingsForm->display($request);
		} else {
			$router =& $request->getRouter();
			$request->redirectUrl($router->url($request, null, 'notification'));
		}
	}

	/**
	 * Save user notification settings
	 * @param $args array
	 * @param $request Request
	 */
	function saveSettings($args, &$request) {
		$this->validate();
		$this->setupTemplate(true);

		import('classes.notification.form.NotificationSettingsForm');

		$notificationSettingsForm = new NotificationSettingsForm();
		$notificationSettingsForm->readInputData();

		if ($notificationSettingsForm->validate()) {
			$notificationSettingsForm->execute($request);
			$router =& $request->getRouter();
			$request->redirectUrl($router->url($request, null, 'notification', 'settings'));
		} else {
			$notificationSettingsForm->display($request);
		}
	}

	/**
	 * Fetch the existing or create a new URL for the user's RSS feed
	 * @param $args array
	 * @param $request Request
	 */
	function getNotificationFeedUrl($args, &$request) {
		$user =& $request->getUser();
		$router =& $request->getRouter();
		$context =& $router->getContext($request);

		if(isset($user)) {
			$userId = $user->getId();
		} else {
			$userId = 0;
		}

		$notificationSettingsDao =& DAORegistry::getDAO('NotificationSettingsDAO');
		$feedType = array_shift($args);

		$token = $notificationSettingsDao->getRSSTokenByUserId($userId, $context->getId());

		if ($token) {
			$request->redirectUrl($router->url($request, null, 'notification', 'notificationFeed', array($feedType, $token)));
		} else {
			$token = $notificationSettingsDao->insertNewRSSToken($userId, $context->getId());
			$request->redirectUrl($router->url($request, null, 'notification', 'notificationFeed', array($feedType, $token)));
		}
	}

	/**
	 * Fetch the actual RSS feed
	 * @param $args array
	 * @param $request Request
	 */
	function notificationFeed($args, &$request) {
		$router =& $request->getRouter();
		$context =& $router->getContext($request);

		if(isset($args[0]) && isset($args[1])) {
			$type = $args[0];
			$token = $args[1];
		} else {
			return false;
		}

		$this->setupTemplate(true);

		$application = PKPApplication::getApplication();
		$appName = $application->getNameKey();

		$site =& $request->getSite();
		$siteTitle = $site->getLocalizedTitle();

		$notificationSettingsDao =& DAORegistry::getDAO('NotificationSettingsDAO');
		$userId = $notificationSettingsDao->getUserIdByRSSToken($token, $context->getId());

		import('classes.notification.NotificationManager');
		$notificationManager = new NotificationManager();
		$notifications = $notificationManager->getNotificationsForUser($request, $userId, NOTIFICATION_LEVEL_NORMAL);

		// Make sure the feed type is specified and valid
		$typeMap = array(
			'rss' => 'rss.tpl',
			'rss2' => 'rss2.tpl',
			'atom' => 'atom.tpl'
		);
		$mimeTypeMap = array(
			'rss' => 'application/rdf+xml',
			'rss2' => 'application/rss+xml',
			'atom' => 'application/atom+xml'
		);
		if (!isset($typeMap[$type])) return false;

		$versionDao =& DAORegistry::getDAO('VersionDAO');
		$version = $versionDao->getCurrentVersion();

		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign('version', $version->getVersionString());
		$templateMgr->assign('selfUrl', $request->getCompleteUrl());
		$templateMgr->assign('locale', Locale::getPrimaryLocale());
		$templateMgr->assign('appName', $appName);
		$templateMgr->assign('siteTitle', $siteTitle);
		$templateMgr->assign_by_ref('notifications', $notifications->toArray());

		$templateMgr->display(Core::getBaseDir() . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR .
			'pkp' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'notification' . DIRECTORY_SEPARATOR . $typeMap[$type], $mimeTypeMap[$type]);

		return true;
	}

	/**
	 * Display the public notification email subscription form
	 * @param $args array
	 * @param $request Request
	 */
	function subscribeMailList($args, &$request) {
		$this->setupTemplate();

		$user = $request->getUser();

		if(!isset($user)) {
			import('lib.pkp.classes.notification.form.NotificationMailingListForm');
			$notificationMailingListForm = new NotificationMailingListForm();
			$notificationMailingListForm->display();
		} else {
			$router =& $request->getRouter();
			$request->redirectUrl($router->url($request, null, 'notification'));
		}
	}

	/**
	 * Save the public notification email subscription form
	 * @param $args array
	 * @param $request Request
	 */
	function saveSubscribeMailList($args, &$request) {
		$this->validate();
		$this->setupTemplate(true);

		import('lib.pkp.classes.notification.form.NotificationMailingListForm');

		$notificationMailingListForm = new NotificationMailingListForm();
		$notificationMailingListForm->readInputData();

		if ($notificationMailingListForm->validate()) {
			$notificationMailingListForm->execute($request);
			$router =& $request->getRouter();
			$request->redirectUrl($router->url($request, null, 'notification', 'mailListSubscribed', array('success')));
		} else {
			$notificationMailingListForm->display();
		}
	}

	/**
	 * Display a success or error message if the user was subscribed
	 * @param $args array
	 * @param $request Request
	 */
	function mailListSubscribed($args, &$request) {
		$this->setupTemplate();
		$status = array_shift($args);
		$templateMgr =& TemplateManager::getManager();

		if ($status == 'success') {
			$templateMgr->assign('status', 'subscribeSuccess');
		} else {
			$templateMgr->assign('status', 'subscribeError');
		}

		$templateMgr->display('notification/maillistSubscribed.tpl');
	}

	/**
	 * Confirm the subscription (accessed via emailed link)
	 * @param $args array
	 * @param $request Request
	 */
	function confirmMailListSubscription($args, &$request) {
		$this->setupTemplate();
		$keyHash = array_shift($args);
		$email = array_shift($args);

		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign('confirm', true);

		$notificationSettingsDao =& DAORegistry::getDAO('NotificationSettingsDAO');
		$settingId = $notificationSettingsDao->getMailListSettingId($email);

		$accessKeyDao =& DAORegistry::getDAO('AccessKeyDAO');
		$accessKey = $accessKeyDao->getAccessKeyByKeyHash('MailListContext', $settingId, $keyHash);

		if($accessKey) {
			$notificationSettingsDao->confirmMailListSubscription($settingId);
			$templateMgr->assign('status', 'confirmSuccess');
		} else {
			$templateMgr->assign('status', 'confirmError');
		}

		$templateMgr->display('notification/maillistSubscribed.tpl');
	}

	/**
	 * Save the maillist unsubscribe form
	 * @param $args array
	 * @param $request Request
	 */
	function unsubscribeMailList($args, &$request) {
		$router =& $request->getRouter();
		$context =& $router->getContext($request);

		$this->setupTemplate();
		$templateMgr =& TemplateManager::getManager();

		$userEmail = $request->getUserVar('email');
		$userPassword = $request->getUserVar('password');

		if($userEmail != '' && $userPassword != '') {
			$notificationSettingsDao =& DAORegistry::getDAO('NotificationSettingsDAO');
			if($notificationSettingsDao->unsubscribeGuest($userEmail, $userPassword, $context->getId())) {
				$templateMgr->assign('success', "notification.unsubscribeSuccess");
				$templateMgr->display('notification/maillistSettings.tpl');
			} else {
				$templateMgr->assign('error', "notification.unsubscribeError");
				$templateMgr->display('notification/maillistSettings.tpl');
			}
		} else if($userEmail != '' && $userPassword == '') {
			$notificationSettingsDao =& DAORegistry::getDAO('NotificationSettingsDAO');
			if($newPassword = $notificationSettingsDao->resetPassword($userEmail, $context->getId())) {
				import('lib.pkp.classes.notification.NotificationManager');
				$notificationManager = new NotificationManager();
				$notificationManager->sendMailingListEmail($userEmail, $newPassword, 'NOTIFICATION_MAILLIST_PASSWORD');
				$templateMgr->assign('success', "notification.reminderSent");
				$templateMgr->display('notification/maillistSettings.tpl');
			} else {
				$templateMgr->assign('error', "notification.reminderError");
				$templateMgr->display('notification/maillistSettings.tpl');
			}
		} else {
			$templateMgr->assign('remove', true);
			$templateMgr->display('notification/maillistSettings.tpl');
		}
	}

	 /**
	  * Fetch notification data and return using Json.
	  * @param $args array
	  * @param $request Request
	  *
	  * @return JSONMessage
	  */
	 function fetchNotification($args, &$request) {
	 	$user =& $request->getUser();
		if ($user) {
			$context =& $request->getContext();
			$contextId = ($context)?$context->getId():null;
			$notificationDao =& DAORegistry::getDAO('NotificationDAO');

			import('classes.notification.NotificationManager');
			$notificationManager = new NotificationManager();
			$notifications = $notificationManager->getNotificationsForUser($request, $user->getId(), NOTIFICATION_LEVEL_TRIVIAL, $contextId);

			$notificationsArray =& $notifications->toArray();
			unset($notifications);

			// Create an array to pass to pnotify. If we are really going to
			// use pnotify, then we should make this code available in
			// NotificationManager, let TemplateManager get the notification
			// data from it and remove the NotificationHandler options
			// code, in common/header.tpl.
			$notificationsData = array();
			$defaultTitle = Locale::translate('notification.notification');
			foreach ($notificationsArray as $notification) {
				$title = $notification->getTitle();
				$contents = $notification->getContents();
				$notificationsData[] = array(
					'pnotify_title' => (!is_null($title)) ? $title : $defaultTitle,
					'pnotify_text' => $contents,
					'pnotify_addClass' => $notification->getStyleClass(),
					'pnotify_notice_icon' => 'notifyIcon' . $notification->getIconClass()
				);

				$notificationDao->deleteNotificationById($notification->getId(), $user->getId());
			}

			import('lib.pkp.classes.core.JSONMessage');
			$json = new JSONMessage(true);
			$json->setContent($notificationsData);

			return $json->getString();
		}
	 }
}

?>
