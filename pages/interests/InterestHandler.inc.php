<?php

/**
 * @file InterestHandler.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class InterestHandler
 * @ingroup pages_interests
 *
 * @brief Handle requests for fetching user interests.
 */

import('classes.handler.Handler');

class InterestHandler extends Handler {

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

		import('lib.pkp.classes.core.JSON');
		$json = new JSON(true, $interests);
		return $json->getString();
	}
}

?>
