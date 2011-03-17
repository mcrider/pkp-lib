/**
 * @file js/controllers/modal/RemoteActionConfirmationModalHandler.js
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class RemoteActionConfirmationModalHandler
 * @ingroup js_controllers_modal
 *
 * @brief A confirmation modal that executes a remote action on confirmation.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.modal.ConfirmationModalHandler
	 *
	 * @param {jQuery} $handledElement The clickable element
	 *  the modal will be attached to.
	 * @param {Object} options Non-default options to configure
	 *  the modal.
	 *
	 *  Options are:
	 *  - remoteAction string An action to be executed when the confirmation
	 *    button has been clicked.
	 *  - All options from the ConfirmationModalHandler and ModalHandler
	 *    widgets.
	 *  - All options documented for the jQueryUI dialog widget,
	 *    except for the buttons parameter which is not supported.
	 */
	$.pkp.controllers.modal.RemoteActionConfirmationModalHandler =
			function($handledElement, options) {

		this.parent($handledElement, options);

		// Configure the remote action (URL) to be called when
		// the modal closes.
		this.remoteAction_ = options.remoteAction;

		// Get the hide action and whether the dialog has already
		//  been hidden from the options
		this.hideAction_ = options.hideAction || null;
		this.isHidden_ = options.isHidden || false;

		// If a hide action is supplied and we haven't already
		//  hidden the dialog, add the ability to hide the dialog
		if(this.hideAction_ && !this.isHidden_) {
			this.addHideMessage_($handledElement, options);
		}

		// Else if the dialog has already been hidden, force the
		//  dialog to submit itself when it is opened
		if(this.isHidden_) {
			this.unbind('dialogopen');
			this.bind('dialogopen', this.modalConfirm);
			$handledElement.dialog('open');
		}

	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.modal.RemoteActionConfirmationModalHandler,
			$.pkp.controllers.modal.ConfirmationModalHandler);


	//
	// Private properties
	//
	/**
	 * A remote action to be executed when the confirmation button
	 * has been clicked.
	 * @private
	 * @type {?string}
	 */
	$.pkp.controllers.modal.RemoteActionConfirmationModalHandler.prototype.
			remoteAction_ = null;

	/**
	 * A remote action to be executed when the 'hide' checkbox is clicked
	 * @private
	 * @type {?string}
	 */
	$.pkp.controllers.modal.RemoteActionConfirmationModalHandler.prototype.
			hideAction_ = null;

	/**
	 * Whether the confirmation has already been user-suppressed
	 * @private
	 * @type {?boolean}
	 */
	$.pkp.controllers.modal.RemoteActionConfirmationModalHandler.prototype.
			isHidden_ = null;


	//
	// Protected methods
	//
	/** @inheritDoc */
	$.pkp.controllers.modal.RemoteActionConfirmationModalHandler.prototype.
			checkOptions = function(options) {

		// Check the mandatory options of the ModalHandler handler.
		if (!this.parent('checkOptions', options)) {
			return false;
		}

		// Check for our own mandatory options.
		return typeof options.remoteAction === 'string';
	};


	//
	// Public methods
	//
	/**
	 * Callback that will be activated when the modal's
	 * confirm button is clicked.
	 *
	 * @param {HTMLElement} dialogElement The element the
	 *  dialog was created on.
	 */
	$.pkp.controllers.modal.RemoteActionConfirmationModalHandler.prototype.
			modalConfirm = function(dialogElement) {

		$.post(this.remoteAction_,
				this.callbackWrapper(this.remoteResponse), 'json');
	};

	/**
	 * Callback that will be activated when the 'hide message' checkbox is clicked.
	 *
	 * @param {Object} callingContext The calling element or object.
	 * @param {Event=} event The triggering event (e.g. a click on
	 *  a close button. Not set if called via callback.
	 * @return {boolean} Should return false to stop event processing.
	 */
	$.pkp.controllers.modal.RemoteActionConfirmationModalHandler.prototype.hideConfirm =
			function(callingContext, event) {

		var isChecked = $(callingContext).attr('checked')===true ? 'true' : 'false';
		$.post(this.hideAction_, { "setting-value": isChecked},
				this.callbackWrapper(this.hideResponse), 'json');
	};


	//
	// Private methods
	//
	/**
	 * Add the hide message to the dialog text.
	 *
	 * @private
	 * @param {jQuery} $handledElement The element the
	 *  dialog was created on.
	 * @param {Object} options The dialog options.
	 */
	$.pkp.controllers.modal.RemoteActionConfirmationModalHandler.prototype.addHideMessage_ =
			function($handledElement, options) {

		var hideMessage = options.hideMessage || null;
		if(hideMessage) {
			var $spacing = $('<br /><br /><br />');
			var $checkbox =	$('<input id="hideMessage" class="ui-dialog-hide-message" type="checkbox" />');
			var $message = $('<label for="hideMessage">'
							 + hideMessage
							 + '</label>');
			$checkbox.click(this.callbackWrapper(this.hideConfirm));
			$handledElement.append($spacing, $checkbox, $message);
		}
	};


	//
	// Protected methods
	//
	/**
	 * @inheritDoc
	 */
	$.pkp.controllers.modal.RemoteActionConfirmationModalHandler.prototype.
			remoteResponse = function(ajaxOptions, jsonData) {

		jsonData = this.parent('remoteResponse', ajaxOptions, jsonData);
		if (jsonData !== false) {
			this.modalClose(ajaxOptions);
		}
	};

	/**
	 * Handle the response from the server after clicking
	 *  the 'hide dialog' checkbox
	 * @param {Object} ajaxOptions AJAX options.
	 * @param {Object} jsonData A JSON object.
	 * @return {Object|boolean} The parsed JSON data if
	 *  no error occurred, otherwise false.
	 */
	$.pkp.controllers.modal.RemoteActionConfirmationModalHandler.prototype.hideResponse =
			function(ajaxOptions, jsonData) {

		jsonData = this.handleJson(jsonData);

		// If the checkbox is checked, bind the close to the actionRebind event
		var $handledElement = this.getHtmlElement();
		var isChecked = $handledElement.find('#hideMessage').attr('checked')===true ? true : false;
		if(isChecked) {
			/* FIXME: Method to prevent this dialog from opening again*/
			// this.bind('dialogclose', this.method);
		} else {
			this.unbind('dialogclose', this.method);
		}
	}


/** @param {jQuery} $ jQuery closure. */
})(jQuery);
