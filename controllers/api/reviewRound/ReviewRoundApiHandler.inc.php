<?php
/**
 * @defgroup controllers_api_reviewRound
 */

/**
 * @file controllers/api/reviewRound/ReviewRoundApiHandler.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ReviewRoundApiHandler
 * @ingroup controllers_api_reviewRound
 *
 * @brief Class defining the headless AJAX API for accessing review round information.
 */

// import the base Handler
import('lib.pkp.classes.handler.PKPHandler');

// import JSON class for API responses
import('lib.pkp.classes.core.JSONMessage');

class ReviewRoundApiHandler extends PKPHandler {
	/**
	 * Constructor.
	 */
	function ReviewRoundApiHandler() {
		parent::PKPHandler();
	}


	//
	// Implement template methods from PKPHandler
	//
	/**
	 * @see PKPHandler::authorize()
	 */
	function authorize(&$request, $args, $roleAssignments) {
		import('classes.security.authorization.OmpSubmissionAccessPolicy');
		$this->addPolicy(new OmpSubmissionAccessPolicy($request, $args, $roleAssignments));
		return parent::authorize($request, $args, $roleAssignments);
	}


	//
	// Public handler methods
	//
	/**
	 * Get the review round status of a submission in review.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string a JSON message
	 */
	function getCurrentReviewRoundStatus($args, &$request) {
		$reviewRoundDao =& DAORegistry::getDAO('ReviewRoundDAO'); /* @var $reviewRoundDao ReviewRoundDAO */
		$reviewRound =& $reviewRoundDao->getReviewRound($monograph->getId(), $monograph->getCurrentReviewType(), $monograph->getCurrentRound());

		// Get the status message for the round
		$roundStatus =& $reviewRound->getStatusKey();

		// Return a success message.
		$json = new JSONMessage(true, __($roundStatus));
		return $json->getString();

	}
}
?>
