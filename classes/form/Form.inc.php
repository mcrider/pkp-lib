<?php

/**
 * @defgroup form
 */

/**
 * @file classes/form/Form.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Form
 * @ingroup core
 *
 * @brief Class defining basic operations for handling HTML forms.
 */

import('lib.pkp.classes.form.FormError');
import('lib.pkp.classes.form.FormBuilderVocabulary');

// Import all form validators for convenient use in sub-classes
import('lib.pkp.classes.form.validation.FormValidatorAlphaNum');
import('lib.pkp.classes.form.validation.FormValidatorArray');
import('lib.pkp.classes.form.validation.FormValidatorArrayCustom');
import('lib.pkp.classes.form.validation.FormValidatorControlledVocab');
import('lib.pkp.classes.form.validation.FormValidatorCustom');
import('lib.pkp.classes.form.validation.FormValidatorCaptcha');
import('lib.pkp.classes.form.validation.FormValidatorReCaptcha');
import('lib.pkp.classes.form.validation.FormValidatorEmail');
import('lib.pkp.classes.form.validation.FormValidatorInSet');
import('lib.pkp.classes.form.validation.FormValidatorLength');
import('lib.pkp.classes.form.validation.FormValidatorLocale');
import('lib.pkp.classes.form.validation.FormValidatorLocaleEmail');
import('lib.pkp.classes.form.validation.FormValidatorPost');
import('lib.pkp.classes.form.validation.FormValidatorRegExp');
import('lib.pkp.classes.form.validation.FormValidatorUri');
import('lib.pkp.classes.form.validation.FormValidatorUrl');

class Form {

	/** The template file containing the HTML form */
	var $_template;

	/** Associative array containing form data */
	var $_data;

	/** Validation checks for this form */
	var $_checks;

	/** Errors occurring in form validation */
	var $_errors;

	/** Array of field names where an error occurred and the associated error message */
	var $errorsArray;

	/** Array of field names where an error occurred */
	var $errorFields;

	/** Array of errors for the form section currently being processed */
	var $formSectionErrors;

	/** Styles organized by parameter name */
	var $fbvStyles;

	/** Client-side validation rules **/
	var $cssValidation;

	/** @var $requiredLocale string Symbolic name of required locale */
	var $requiredLocale;

	/** @var $supportedLocales array Set of supported locales */
	var $supportedLocales;

	/**
	 * Constructor.
	 * @param $template string the path to the form template file
	 */
	function Form($template = null, $callHooks = true, $requiredLocale = null, $supportedLocales = null) {

		if ($requiredLocale === null) $requiredLocale = Locale::getPrimaryLocale();
		$this->requiredLocale = $requiredLocale;
		if ($supportedLocales === null) $supportedLocales = Locale::getSupportedFormLocales();
		$this->supportedLocales = $supportedLocales;

		$this->_template = $template;
		$this->_data = array();
		$this->_checks = array();
		$this->_errors = array();
		$this->errorsArray = array();
		$this->errorFields = array();
		$this->formSectionErrors = array();
		$this->fbvStyles = array(
			'size' => array('SMALL' => 'SMALL', 'MEDIUM' => 'MEDIUM'),
		);

		if ($callHooks === true) {
			// Call hooks based on the calling entity, assuming
			// this method is only called by a subclass. Results
			// in hook calls named e.g. "papergalleyform::Constructor"
			// Note that class names are always lower case.
			HookRegistry::call(strtolower(get_class($this)) . '::Constructor', array(&$this, &$template));
		}
	}


	//
	// Setters and Getters
	//
	/**
	 * Set the template
	 * @param $template string
	 */
	function setTemplate($template) {
		$this->_template = $template;
	}

	/**
	 * Get the template
	 * @return string
	 */
	function getTemplate() {
		return $this->_template;
	}

	/**
	 * Get the required locale for this form (i.e. the locale for which
	 * required fields must be set, all others being optional)
	 * @return string
	 */
	function getRequiredLocale() {
		return $this->requiredLocale;
	}

	//
	// Public Methods
	//
	/**
	 * Display the form.
	 * @param $request PKPRequest
	 * @param $template string the template to be rendered, mandatory
	 *  if no template has been specified on class instantiation.
	 */
	function display($request = null, $template = null) {
		$this->fetch($request, $template, true);
	}

	/**
	 * Returns a string of the rendered form
	 * @param $request PKPRequest
	 * @param $template string the template to be rendered, mandatory
	 *  if no template has been specified on class instantiation.
	 * @param $display boolean
	 * @return string the rendered form
	 */
	function fetch(&$request, $template = null, $display = false) {
		// Set custom template.
		if (!is_null($template)) $this->_template = $template;

		$returner = null;
		// Call hooks based on the calling entity, assuming
		// this method is only called by a subclass. Results
		// in hook calls named e.g. "papergalleyform::display"
		// Note that class names are always lower case.
		if (HookRegistry::call(strtolower(get_class($this)) . '::display', array(&$this, &$returner))) {
			return $returner;
		}

		$templateMgr =& TemplateManager::getManager($request);
		$templateMgr->setCacheability(CACHEABILITY_NO_STORE);

		// Modified vocabulary for creating forms
		$fbv = new FormBuilderVocabulary($this);
		$templateMgr->register_block('fbvFormSection', array(&$fbv, 'smartyFBVFormSection'));
		$templateMgr->register_block('fbvFormArea', array(&$fbv, 'smartyFBVFormArea'));
		$templateMgr->register_function('fbvElement', array(&$fbv, 'smartyFBVElement'));
		$templateMgr->assign('fbvStyles', $fbv->getStyles());

		$templateMgr->assign($this->_data);
		$templateMgr->assign('isError', !$this->isValid());
		$templateMgr->assign('errors', $this->getErrorsArray());

		$templateMgr->assign('formLocales', $this->supportedLocales);

		// Determine the current locale to display fields with
		$formLocale = $this->getFormLocale();
		$templateMgr->assign('formLocale', $this->getFormLocale());

		// N.B: We have to call $templateMgr->display instead of ->fetch($display)
		// in order for the TemplateManager::display hook to be called
		return $templateMgr->display($this->_template, null, null, $display);
	}

	/**
	 * Get the value of a form field.
	 * @param $key string
	 * @return mixed
	 */
	function getData($key) {
		return isset($this->_data[$key]) ? $this->_data[$key] : null;
	}

	/**
	 * Set the value of a form field.
	 * @param $key
	 * @param $value
	 */
	function setData($key, $value) {
		if (is_string($value)) $value = Core::cleanVar($value);
		$this->_data[$key] = $value;
	}

	/**
	 * Initialize form data for a new form.
	 */
	function initData() {
		// Call hooks based on the calling entity, assuming
		// this method is only called by a subclass. Results
		// in hook calls named e.g. "papergalleyform::initData"
		// Note that class and function names are always lower
		// case.
		HookRegistry::call(strtolower(get_class($this) . '::initData'), array(&$this));
	}

	/**
	 * Assign form data to user-submitted data.
	 * Can be overridden from subclasses.
	 */
	function readInputData() {
		// Default implementation does nothing.
	}

	/**
	 * Validate form data.
	 */
	function validate($callHooks = true) {
		if (!isset($this->errorsArray)) {
			$this->getErrorsArray();
		}

		foreach ($this->_checks as $check) {
			// WARNING: This line is for PHP4 compatibility when
			// instantiating forms without reference. Should not
			// be removed or otherwise used.
			// See http://pkp.sfu.ca/wiki/index.php/Information_for_Developers#Use_of_.24this_in_the_constructor
			// for an explanation why we have to replace the reference to $this here.
			$check->setForm($this);

			if (!isset($this->errorsArray[$check->getField()]) && !$check->isValid()) {
				if (method_exists($check, 'getErrorFields') && method_exists($check, 'isArray') && call_user_func(array(&$check, 'isArray'))) {
					$errorFields = call_user_func(array(&$check, 'getErrorFields'));
					for ($i=0, $count=count($errorFields); $i < $count; $i++) {
						$this->addError($errorFields[$i], $check->getMessage());
						$this->errorFields[$errorFields[$i]] = 1;
					}
				} else {
					$this->addError($check->getField(), $check->getMessage());
					$this->errorFields[$check->getField()] = 1;
				}
			}
		}

		if ($callHooks === true) {
			// Call hooks based on the calling entity, assuming
			// this method is only called by a subclass. Results
			// in hook calls named e.g. "papergalleyform::validate"
			// Note that class and function names are always lower
			// case.
			$value = null;
			if (HookRegistry::call(strtolower(get_class($this) . '::validate'), array(&$this, &$value))) {
				return $value;
			}
		}

		return $this->isValid();
	}

	/**
	 * Execute the form's action.
	 * (Note that it is assumed that the form has already been validated.)
	 */
	function execute() {
		// Call hooks based on the calling entity, assuming
		// this method is only called by a subclass. Results
		// in hook calls named e.g. "papergalleyform::execute"
		// Note that class and function names are always lower
		// case.
		$value = null;
		HookRegistry::call(strtolower(get_class($this) . '::execute'), array(&$this, &$vars));
	}

	/**
	 * Get the list of field names that need to support multiple locales
	 * @return array
	 */
	function getLocaleFieldNames() {
		$returner = array();
		// Call hooks based on the calling entity, assuming
		// this method is only called by a subclass. Results
		// in hook calls named e.g. "papergalleyform::getLocaleFieldNames"
		// Note that class and function names are always lower
		// case.
		$value = null;
		HookRegistry::call(strtolower(get_class($this) . '::getLocaleFieldNames'), array(&$this, &$returner));

		return $returner;
	}

	/**
	 * Determine whether or not the current request results from a resubmit
	 * of locale data resulting from a form language change.
	 * @return boolean
	 */
	function isLocaleResubmit() {
		$formLocale = Request::getUserVar('formLocale');
		return (!empty($formLocale));
	}

	/**
	 * Get the default form locale.
	 * @return string
	 */
	function getDefaultFormLocale() {
		if (empty($formLocale)) $formLocale = Locale::getLocale();
		if (!isset($this->supportedLocales[$formLocale])) $formLocale = $this->requiredLocale;
		return $formLocale;
	}

	/**
	 * Get the current form locale.
	 * @return string
	 */
	function getFormLocale() {
		$formLocale = Request::getUserVar('formLocale');
		if (!$formLocale || !isset($this->supportedLocales[$formLocale])) {
			$formLocale = $this->getDefaultFormLocale();
		}
		return $formLocale;
	}

	/**
	 * Adds specified user variables to input data.
	 * @param $vars array the names of the variables to read
	 */
	function readUserVars($vars) {
		// Call hooks based on the calling entity, assuming
		// this method is only called by a subclass. Results
		// in hook calls named e.g. "papergalleyform::readUserVars"
		// Note that class and function names are always lower
		// case.
		$value = null;
		HookRegistry::call(strtolower(get_class($this) . '::readUserVars'), array(&$this, &$vars));

		foreach ($vars as $k) {
			$this->setData($k, Request::getUserVar($k));
		}
	}

	/**
	 * Adds specified user date variables to input data.
	 * @param $vars array the names of the date variables to read
	 */
	function readUserDateVars($vars) {
		// Call hooks based on the calling entity, assuming
		// this method is only called by a subclass. Results
		// in hook calls named e.g. "papergalleyform::readUserDateVars"
		// Note that class and function names are always lower
		// case.
		$value = null;
		HookRegistry::call(strtolower(get_class($this) . '::readUserDateVars'), array(&$this, &$vars));

		foreach ($vars as $k) {
			$this->setData($k, Request::getUserDateVar($k));
		}
	}

	/**
	 * Add a validation check to the form.
	 * @param $formValidator FormValidator
	 */
	function addCheck($formValidator) {
		$this->_checks[] =& $formValidator;
	}

	/**
	 * Add an error to the form.
	 * Errors are typically assigned as the form is validated.
	 * @param $field string the name of the field where the error occurred
	 */
	function addError($field, $message) {
		$this->_errors[] = new FormError($field, $message);
	}

	/**
	 * Add an error field for highlighting on form
	 * @param $field string the name of the field where the error occurred
	 */
	function addErrorField($field) {
		$this->errorFields[$field] = 1;
	}

	/**
	 * Check if form passes all validation checks.
	 * @return boolean
	 */
	function isValid() {
		return empty($this->_errors);
	}

	/**
	 * Return set of errors that occurred in form validation.
	 * If multiple errors occurred processing a single field, only the first error is included.
	 * @return array erroneous fields and associated error messages
	 */
	function getErrorsArray() {
		$this->errorsArray = array();
		foreach ($this->_errors as $error) {
			if (!isset($this->errorsArray[$error->getField()])) {
				$this->errorsArray[$error->getField()] = $error->getMessage();
			}
		}
		return $this->errorsArray;
	}


	//
	// Private helper methods
	//
	/**
	 * FIXME: document
	 * @param $name
	 * @param $value
	 * @param $stack
	 */
	function _decomposeArray($name, $value, $stack) {
		$returner = '';
		if (is_array($value)) {
			foreach ($value as $key => $subValue) {
				$newStack = $stack;
				$newStack[] = $key;
				$returner .= $this->_decomposeArray($name, $subValue, $newStack);
			}
		} else {
			$name = htmlentities($name, ENT_COMPAT, LOCALE_ENCODING);
			$value = htmlentities($value, ENT_COMPAT, LOCALE_ENCODING);
			$returner .= '<input type="hidden" name="' . $name;
			while (($item = array_shift($stack)) !== null) {
				$item = htmlentities($item, ENT_COMPAT, LOCALE_ENCODING);
				$returner .= '[' . $item . ']';
			}
			$returner .= '" value="' . $value . "\" />\n";
		}
		return $returner;
	}
}

?>
