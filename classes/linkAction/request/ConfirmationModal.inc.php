<?php
/**
 * @file classes/linkAction/request/ConfirmationModal.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ConfirmationModal
 * @ingroup linkAction_request
 *
 * @brief Class defining a simple confirmation modal.
 */


import('lib.pkp.classes.linkAction.request.Modal');

class ConfirmationModal extends Modal {
	/** @var string A URL to be called when the confirmation button is clicked. */
	var $_remoteAction;

	/**
	 * @var string A translation key defining the text for the confirmation
	 * button of the modal.
	 */
	var $_okButton;

	/**
	 * @var string a translation key defining the text for the cancel
	 * button of the modal.
	 */
	var $_cancelButton;

	/**
	 * @var string a translation key defining the text for the dialog
	 *  text.
	 */
	var $_dialogText;

	/**
	 * @var string URL to call to hide this dialog
	 */
	var $_hideAction;

	/**
	 * @var boolean Whether the user has suppressed the dialog
	 */
	var $_isHidden;

	/**
	 * Constructor
	 * @param $dialogText string The localized text to appear
	 *  in the dialog modal.
	 * @param $title string (optional) The localized modal title.
	 * @param $remoteAction string (optional) A URL to be
	 *  called when the confirmation button is clicked.
	 * @param $titleIcon string (optional) The icon to be used
	 *  in the modal title bar.
	 * @param $okButton string (optional) The localized text to
	 *  appear on the confirmation button.
	 * @param $cancelButton string (optional) The localized text to
	 *  appear on the cancel button.
	 * @param $canClose boolean (optional) Whether the modal will
	 *  have a close button.
	 * @param $hideAction string (optional) URL to call to hide this dialog
	 */
	function ConfirmationModal($dialogText, $title = null, $remoteAction = null, $titleIcon = null, $okButton = null, $cancelButton = null, $canClose = true, $hideAction = null, $isHidden = false) {
		parent::Modal($title, $titleIcon, $canClose);

		$this->_remoteAction = $remoteAction;
		$this->_okButton = (is_null($okButton) ? __('common.ok') : $okButton);
		$this->_cancelButton = (is_null($cancelButton) ? __('common.cancel') : $cancelButton);
		$this->_dialogText = $dialogText;
		$this->_hideAction = $hideAction;
		$this->_isHidden = $isHidden;
	}


	//
	// Getters and Setters
	//
	/**
	 * Get the remote action.
	 * @return string
	 */
	function getRemoteAction() {
		return $this->_remoteAction;
	}

	/**
	 * Get the translation key for the confirmation
	 * button text.
	 * @return string
	 */
	function getOkButton() {
		return $this->_okButton;
	}

	/**
	 * Get the translation key for the cancel
	 * button text.
	 * @return string
	 */
	function getCancelButton() {
		return $this->_cancelButton;
	}

	/**
	 * Get the translation key for the dialog
	 * text.
	 * @return string
	 */
	function getDialogText() {
		return $this->_dialogText;
	}

	/**
	 * The setting name that determines if this dialog
	 * is hideable
	 * @return string
	 */
	function getHideAction() {
		return $this->_hideAction;
	}

	/**
	 * Get whether the dialog has been hidden by the user
	 * @return boolean
	 */
	function getIsHidden() {
	    return $this->_isHidden;
	}


	//
	// Overridden methods from LinkActionRequest
	//
	/**
	 * @see LinkActionRequest::getLocalizedOptions()
	 */
	function getLocalizedOptions() {
		$localizedOptions = array(
			'modalHandler' => '$.pkp.controllers.modal.RemoteActionConfirmationModalHandler',
			'remoteAction' => $this->getRemoteAction(),
			'okButton' => $this->getOkButton(),
			'cancelButton' => $this->getCancelButton(),
			'dialogText' => $this->getDialogText()
		);

		// If the dialog is hideable, expose the hide action
		//  URL and the translated message to the client side
		if ($this->getHideAction()) {
			$localizedOptions['hideAction'] = $this->getHideAction();
			$localizedOptions['hideMessage'] = __('common.hideMessage');
		// FIXME: For testing, switched 1 and 0
			$localizedOptions['isHidden'] = ($this->getIsHidden() ? '0' : '1');
		}

		return array_merge(parent::getLocalizedOptions(), $localizedOptions);
	}
}

?>
