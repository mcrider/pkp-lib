/**
 * @file js/controllers/ReviewRoundStatusHandler.js
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ReviewRoundStatusHandler
 * @ingroup js_controllers
 *
 * @brief Handle the review round status widget.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQuery} $page the wrapped page element.
	 * @param {Object} options handler options.
	 */
	$.pkp.controllers.ReviewRoundStatusHandler = function($container, options) {
		this.parent($container, options);

		this.bind('dataChanged', this.getReviewRoundStatus);
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.ReviewRoundStatusHandler, $.pkp.classes.Handler);


	//
	// Public methods
	//
	/**
	 * Callback that is triggered when the page should redirect.
	 *
	 * @param {HTMLElement} sourceElement The element that issued the
	 *  "dataChanged" event.
	 * @param {Event} event The "redirect requested" event.
	 * @param {string} url The URL to redirect to.
	 */
	$.pkp.controllers.ReviewRoundStatusHandler.prototype.getReviewRoundStatus =
			function(sourceElement, event, status) {

		// Fetch the submission's status via the submission API
		//$.get(this.reviewRoundStatusApiUrll_, {monographId: elementId},
		//	this.callbackWrapper(this.replaceStatusResponseHandler_), 'json');
		alert('in ReviewRoundStatusHandler::getReviewRoundStatus');
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);
