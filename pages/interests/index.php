<?php

/**
 * @defgroup pages_interests
 */

/**
 * @file pages/interests/index.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @ingroup pages_notification
 * @brief Handle requests for fetching reviewing interests.
 *
 */

switch ($op) {
	case 'getInterests':
		define('HANDLER_CLASS', 'InterestHandler');
		import('lib.pkp.pages.interests.InterestHandler');
		break;
}

?>
