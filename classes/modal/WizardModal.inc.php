<?php
/**
 * @file classes/modal/WizardModal.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class WizardModal
 * @ingroup modal
 *
 * @brief A modal that contains a wizard retrieved via AJAX.
 */


import('lib.pkp.classes.modal.AjaxModal');

class WizardModal extends AjaxModal {
	/**
	 * Constructor
	 * @param $url PKPUrl
	 * @param $title string (optional)
	 * @param $titleIcon string (optional)
	 * @param $canClose boolean (optional)
	 */
	function WizardModal($url, $title = null, $titleIcon = null, $canClose = true) {
		parent::AjaxModal($url->getUrlString(), $title, $titleIcon, $canClose);
	}


	//
	// Overridden public methods
	//
	/**
	 * @see CancellableModal::getJSHandler()
	 */
	function getJSHandler() {
		return '$.pkp.controllers.modal.WizardModalHandler';
	}
}

?>
