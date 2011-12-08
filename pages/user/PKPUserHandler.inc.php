<?php

/**
 * @file PKPUserHandler.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPUserHandler
 * @ingroup pages_user
 *
 * @brief Handle requests for user functions.
 */

import('classes.handler.Handler');

class PKPUserHandler extends Handler {
	/**
	 * Constructor
	 **/
	function PKPUserHandler() {
		parent::Handler();
	}

	/**
	 * Get keywords for reviewer interests autocomplete.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
	 */
	function getInterests($args, &$request) {
		$filter = $request->getUserVar('term');

		import('lib.pkp.classes.user.InterestManager');
		$interestManager = new InterestManager();

		$interests = $interestManager->getAllInterests($filter);

		import('lib.pkp.classes.core.JSONMessage');
		$json = new JSONMessage(true, $interests);
		return $json->getString();
	}
}

?>
