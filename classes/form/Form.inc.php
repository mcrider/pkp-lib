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
		$templateMgr->register_function('fieldLabel', array(&$this, 'smartyFieldLabel'));
		$templateMgr->register_function('form_language_chooser', array(&$this, 'smartyFormLanguageChooser'));
		$templateMgr->register_function('modal_language_chooser', array(&$this, 'smartyModalLanguageChooser'));
		$templateMgr->register_block('form_locale_iterator', array(&$this, 'formLocaleIterator'));

		// modifier vocabulary for creating forms
		$templateMgr->register_block('fbvFormSection', array(&$this, 'smartyFBVFormSection'));
		$templateMgr->register_block('fbvFormArea', array(&$this, 'smartyFBVFormArea'));
		$templateMgr->register_function('fbvElement', array(&$this, 'smartyFBVElement'));
		$templateMgr->assign('fbvStyles', $this->fbvStyles);

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

	/**
	 * Custom Smarty function for labelling/highlighting of form fields.
	 * @param $params array can contain 'name' (field name/ID), 'required' (required field), 'key' (localization key), 'label' (non-localized label string), 'suppressId' (boolean)
	 * @param $smarty Smarty
	 */
	function smartyFieldLabel($params, &$smarty) {
		$returner = '';
		if (isset($params) && !empty($params)) {
			if (isset($params['key'])) {
				$params['label'] = Locale::translate($params['key'], $params);
			}

			if (isset($this->errorFields[$params['name']])) {
				$smarty->assign('class', 'error ' . $params['class']);
			} else {
				$smarty->assign('class', $params['class']);
			}

			foreach ($params as $key => $value) {
				switch ($key) {
					case 'label': $smarty->assign('label', $value); break;
					case 'required': $smarty->assign('required', $value); break;
					case 'suppressId': $smarty->assign('suppressId', true); break;
					case 'required': $smarty->assign('required', true); break;
					case 'disabled': $smarty->assign('disabled', $value); break;
					case 'name': $smarty->assign('name', $value); break;
				}
			}

			$returner = $smarty->fetch('form/fieldLabel.tpl');
		}
		return $returner;
	}

	/**
	 * Add hidden form parameters for the localized fields for this form
	 * and display the language chooser field
	 * @param $params array
	 * @param $smarty object
	 */
	function smartyFormLanguageChooser($params, &$smarty) {
		$returner = '';

		// Print back all non-current language field values so that they
		// are not lost.
		$formLocale = $this->getFormLocale();
		foreach ($this->getLocaleFieldNames() as $field) {
			$values = $this->getData($field);
			if (!is_array($values)) continue;
			foreach ($values as $locale => $value) {
				if ($locale != $formLocale) $returner .= $this->_decomposeArray($field, $value, array($locale));
			}
		}

		// Display the language selector widget.
		$returner .= '<div id="languageSelector"><select size="1" name="formLocale" id="formLocale" onchange="changeFormAction(\'' . htmlentities($params['form'], ENT_COMPAT, LOCALE_ENCODING) . '\', \'' . htmlentities($params['url'], ENT_QUOTES, LOCALE_ENCODING) . '\')" class="selectMenu">';
		foreach ($this->supportedLocales as $locale => $name) {
			$returner .= '<option ' . ($locale == $formLocale?'selected="selected" ':'') . 'value="' . htmlentities($locale, ENT_COMPAT, LOCALE_ENCODING) . '">' . htmlentities($name, ENT_COMPAT, LOCALE_ENCODING) . '</option>';
		}
		$returner .= '</select></div>';
		return $returner;
	}

	/**
	 * Form Builder Vocabulary - FBV
	 * Generates form code in templates using {fbvX} calls.
	 * Group form areas with the {fbvFormArea} call.  These sections mark off groups of semantically
	 *  related form sections.
	 *  Parameters:
	 *   id: The form area ID
	 *   class (optional): Any additional classes
	 *   title (optional): Title of the area
	 * Group form sections with the {fbvFormSection} call.  These sections organize directly related form elements.
	 *  Parameters:
	 *   id: The section ID
	 *   class (optional): Any additional classes
	 *   title (optional): Title of the area
	 * Form elements are created with {fbvElement type="type"} plus any additional parameters.
	 * Each specific element type may have other additional attributes (see their method comments)
	 *  Parameters:
	 *   type: The form element type (one of the cases in the smartyFBVElement method)
	 *   id: The element ID
	 *   class (optional): Any additional classes
	 *   required (optional) whether the section should have a 'required' label (adds span.required)
	 *   for (optional): What the section's label is for
	 *   inline: Adds .inline to the element's parent container and causes it to display inline with other elements
	 *   size: One of $fbvStyles.size.SMALL (adds .quarter to element's parent container) or $fbvStyles.size.MEDIUM (adds
	 *    .half to element's parentcontainer)
	 *   required: Adds an asterisk and a .required class to the element's label
	 */

	/**
	 * A form area that contains form sections.
	 * @param $params array
	 * @param $content string
	 * @param $smarty object
	 * @param $repeat
	 */
	function smartyFBVFormArea($params, $content, &$smarty, &$repeat) {
		if (!isset($params['id'])) {
			$smarty->trigger_error('FBV: form area \'id\' not set.');
		}

 		if (!$repeat) {
			$smarty->assign('FBV_class', $params['class']);
			$smarty->assign('FBV_id', $params['id']);
			$smarty->assign('FBV_content', $content);
			$smarty->assign('FBV_title', $params['title']);
			return $smarty->fetch('form/formArea.tpl');
		}
		return '';
	}

	/**
	 * A form section that contains controls in a variety of layout possibilities.
	 * @param $params array
	 * @param $content string
	 * @param $smarty object
	 * @param $repeat
	 */
	function smartyFBVFormSection($params, $content, &$smarty, &$repeat) {
		if (!$repeat) {
			$smarty->assign('FBV_required', isset($params['required']) ? $params['required'] : false);
			$smarty->assign('FBV_labelFor', empty($params['for']) ? null : $params['for']);

			$smarty->assign('FBV_title', $params['title']);
			$smarty->assign('FBV_content', $content);

			$class = $params['class'];
			if (!empty($this->formSectionErrors)) {
				$class = $class . (empty($class) ? '' : ' ') . 'error';
			}

			// If we are displaying checkboxes or radio options, we'll need to use a
			//  list to organize our elements -- Otherwise we use divs and spans
			if (isset($params['list']) && $params['list'] != false) {
				$sectionTemplate = 'form/formSectionList.tpl';
			} else {
				// Double check that we don't have lists in the content.
				//  This is a kludge but the only way to make sure we've
				//  set the list parameter when we're using lists
				if (substr(trim($content), 0, 4) == "<li>") {
					 $smarty->trigger_error('FBV: list attribute not set on form section containing lists');
				}

				$sectionTemplate = 'form/formSection.tpl';
			}

			$smarty->assign('FBV_sectionErrors', $this->formSectionErrors);
			$smarty->assign('FBV_class', $class);

			$smarty->assign('FBV_layoutColumns', empty($params['layout']) ? false : true);
			$this->formSectionErrors = array();

			return $smarty->fetch($sectionTemplate);

		} else {
			$this->formSectionErrors = array();
		}
		return '';
	}

	/**
	 * Base form element.
	 * @param $params array
	 * @param $smarty object
	 */
	function smartyFBVElement($params, &$smarty, $content = null) {
		if (!isset($params['type'])) $smarty->trigger_error('FBV: Element type not set');
		if (!isset($params['id'])) $smarty->trigger_error('FBV: Element ID not set');

		// Set up the element template
		$smarty->assign('FBV_id', $params['id']);
		$smarty->assign('FBV_class', empty($params['class']) ? null : $params['class']);
		$smarty->assign('FBV_layoutInfo', $this->_getLayoutInfo($params));
		$smarty->assign('FBV_required', isset($params['required']) ? $params['required'] : false);
		$smarty->assign('FBV_label', empty($params['label']) ? null : $params['label']);
		$smarty->assign('FBV_label_content', empty($params['label']) ? null : $smarty->fetch('form/label.tpl'));

		// Set up the specific field's template
		switch (strtolower($params['type'])) {
			case 'autocomplete':
				$content = $this->_smartyFBVAutocompleteInput($params, $smarty);
				break;
			case 'button':
			case 'submit':
				$content = $this->_smartyFBVButton($params, $smarty);
				break;
			case 'checkbox':
				$content = $this->_smartyFBVCheckbox($params, $smarty);
				unset($params['label']);
				break;
			case 'file':
				$content = $this->_smartyFBVFileInput($params, $smarty);
				break;
			case 'hidden':
				$content = $this->_smartyFBVHiddenInput($params, $smarty);
				break;
			case 'keyword':
				$content = $this->_smartyFBVKeywordInput($params, $smarty);
				break;
			case 'link':
				$content = $this->_smartyFBVLink($params, $smarty);
				break;
			case 'radio':
				$content = $this->_smartyFBVRadioButton($params, $smarty);
				unset($params['label']);
				break;
			case 'rangeslider':
				$content = $this->_smartyFBVRangeSlider($params, $smarty);
				break;
			case 'select':
				$content = $this->_smartyFBVSelect($params, $smarty);
				break;
			case 'text':
				$content = $this->_smartyFBVTextInput($params, $smarty);
				break;
			case 'textarea':
				$content = $this->_smartyFBVTextArea($params, $smarty);
				break;
			default: $content = null;
		}

		if (!$content) $smarty->trigger_error('FBV: Invalid element type "' . $params['type'] . '"');

		unset($params['type']);

		$parent = $smarty->_tag_stack[count($smarty->_tag_stack)-1];
		$group = false;

		if ($parent) {
			if (isset($this->errorFields[$params['id']])) {
				array_push($this->formSectionErrors, $this->errorsArray[$params['id']]);
			}

			if (isset($parent[1]['group']) && $parent[1]['group']) {
				$group = true;
			}
		}

		return $content;
		// Set up the element template
		$smarty->assign('FBV_content', $content);
		return $smarty->fetch('form/element.tpl');
	}

	/**
	 * Form button.
	 * parameters: label (or value), disabled (optional), type (optional)
	 * @param $params array
	 * @param $smarty object
	 */
	function _smartyFBVButton($params, &$smarty) {
		// accept 'value' param, but the 'label' param is preferred
		if (isset($params['value'])) {
			$value = $params['value'];
			$params['label'] = isset($params['label']) ? $params['label'] : $value;
			unset($params['value']);
		}

		// the type of this button. the default value is 'button' (but could be 'submit')
		$params['type'] = isset($params['type']) ? strtolower($params['type']) : 'button';
		$params['disabled'] = isset($params['disabled']) ? $params['disabled'] : false;

		$buttonParams = '';
		foreach ($params as $key => $value) {
			switch ($key) {
				case 'label': $smarty->assign('FBV_label', $value); break;
				case 'type': $smarty->assign('FBV_type', $value); break;
				case 'class': break;
				case 'disabled': $smarty->assign('FBV_disabled', $params['disabled']); break;
				default: $buttonParams .= htmlspecialchars($key, ENT_QUOTES, LOCALE_ENCODING) . '="' . htmlspecialchars($value, ENT_QUOTES, LOCALE_ENCODING) . '" ';
			}
		}

		$smarty->assign('FBV_buttonParams', $buttonParams);

		return $smarty->fetch('form/button.tpl');
	}

	/**
	 * Text link.
	 * parameters: label (or value), disabled (optional)
	 * @param $params array
	 * @param $smarty object
	 */
	function _smartyFBVLink($params, &$smarty) {
		if (!isset($params['id'])) {
			$smarty->trigger_error('FBV: link form element \'id\' not set.');
		}

		// accept 'value' param, but the 'label' param is preferred
		if (isset($params['value'])) {
			$value = $params['value'];
			$params['label'] = isset($params['label']) ? $params['label'] : $value;
			unset($params['value']);
		}

		// Set the URL if there is one (defaults to '#' e.g. when the link should activate javascript)
		if (isset($params['href'])) {
			$smarty->assign('FBV_href', $params['href']);
		} else {
			$smarty->assign('FBV_href', '#');
		}

		foreach ($params as $key => $value) {
			switch ($key) {
				case 'type': break;
				case 'class': break;
				case 'label': $smarty->assign('FBV_label', $value); break;
				case 'type': $smarty->assign('FBV_type', $value); break;
				case 'disabled': $smarty->assign('FBV_disabled', $params['disabled']); break;
				default: $buttonParams .= htmlspecialchars($key, ENT_QUOTES, LOCALE_ENCODING) . '="' . htmlspecialchars($value, ENT_QUOTES, LOCALE_ENCODING) . '" ';
			}
		}

		$smarty->assign('FBV_buttonParams', $buttonParams);

		return $smarty->fetch('form/link.tpl');
	}

	/**
	 * Form Autocomplete text input. (actually two inputs, label and value)
	 * parameters: disabled (optional), name (optional - assigned value of 'id' by default)
	 * @param $params array
	 * @param $smarty object
	 */
	function _smartyFBVAutocompleteInput($params, &$smarty) {
		if ( !isset($params['autocompleteUrl']) ) {
			$smarty->trigger_error('FBV: url for autocompletion not specified.');
		}

		$params = $this->_addClientSideValidation($params);
		$smarty->assign('FBV_validation', $params['validation']);

		// This id will be used for the hidden input that should be read by the Form.
		$autocompleteId = $params['id'];

		// We then override the id parameter to differentiate it from the hidden element
		//  and make sure that the text input is not read by the Form class.
		$params['id'] = $autocompleteId . '_input';
		$smarty->assign('FBV_textInput', $this->smartyFBVTextInput($params, $smarty));

		$smarty->assign('FBV_id', $autocompleteId);
		$smarty->assign('FBV_autocompleteUrl', $params['autocompleteUrl']);
		return $smarty->fetch('form/autocompleteInput.tpl');
	}

	/**
	 * Range slider input.
	 * parameters: min, max
	 * @param $params array
	 * @param $smarty object
	 */
	function _smartyFBVRangeSlider($params, &$smarty) {
		// Make sure our required fields are included
		if (!isset($params['min']) || !isset($params['max'])) {
			$smarty->trigger_error('FBV: Min and/or max value for range slider not specified.');
		}

		$params = $this->_addClientSideValidation($params);
		$smarty->assign('FBV_validation', $params['validation']);

		// Assign the min and max values to the handler
		$smarty->assign('FBV_min', $params['min']);
		$smarty->assign('FBV_max', $params['max']);

		return $smarty->fetch('form/rangeSlider.tpl');
	}

	/**
	 * Form text input.
	 * parameters: disabled (optional), name (optional - assigned value of 'id' by default), multilingual (optional)
	 * @param $params array
	 * @param $smarty object
	 */
	function _smartyFBVTextInput($params, &$smarty) {
		$params['name'] = isset($params['name']) ? $params['name'] : $params['id'];
		$params['disabled'] = isset($params['disabled']) ? $params['disabled'] : false;
		$params['multilingual'] = isset($params['multilingual']) ? $params['multilingual'] : false;
		$params['value'] = isset($params['value']) ? $params['value'] : '';
		$params = $this->_addClientSideValidation($params);
		$smarty->assign('FBV_validation', null); // Reset form validation fields in memory
		$smarty->assign('FBV_isPassword', isset($params['password']) ? true : false);

		$textInputParams = '';
		foreach ($params as $key => $value) {
			switch ($key) {
				case 'label': break;
				case 'type': break;
				case 'class': break;
				case 'size': break;
				case 'validation': $smarty->assign('FBV_validation', $params['validation']); break;
				case 'required': break; //ignore required field (define required fields in form class)
				case 'disabled': $smarty->assign('FBV_disabled', $params['disabled']); break;
				case 'multilingual': $smarty->assign('FBV_multilingual', $params['multilingual']); break;
				case 'name': $smarty->assign('FBV_name', $params['name']); break;
				case 'id': $smarty->assign('FBV_id', $params['id']); break;
				case 'value': $smarty->assign('FBV_value', $params['value']); break;
				default: $textInputParams .= htmlspecialchars($key, ENT_QUOTES, LOCALE_ENCODING) . '="' . htmlspecialchars($value, ENT_QUOTES, LOCALE_ENCODING). '" ';
			}
		}

		$smarty->assign('FBV_textInputParams', $textInputParams);

		return $smarty->fetch('form/textInput.tpl');
	}

	/**
	 * Form text area.
	 * parameters: value, id, name (optional - assigned value of 'id' by default), disabled (optional), multilingual (optional)
	 * @param $params array
	 * @param $smarty object
	 */
	function _smartyFBVTextArea($params, &$smarty) {
		$params = $this->_addClientSideValidation($params);

		$params['name'] = isset($params['name']) ? $params['name'] : $params['id'];
		$params['disabled'] = isset($params['disabled']) ? $params['disabled'] : false;
		$params['rich'] = isset($params['rich']) ? $params['rich'] : false;
		$params['multilingual'] = isset($params['multilingual']) ? $params['multilingual'] : false;
		$params['value'] = isset($params['value']) ? $params['value'] : '';
		$smarty->assign('FBV_validation', null); // Reset form validation fields in memory

		$textAreaParams = '';
		foreach ($params as $key => $value) {
			switch ($key) {
				case 'name': $smarty->assign('FBV_name', $params['name']); break;
				case 'id': $smarty->assign('FBV_id', $params['id']); break;
				case 'value': $smarty->assign('FBV_value', $value); break;
				case 'label': break;
				case 'type': break;
				case 'size': break;
				case 'rich': break;
				case 'class': break;
				case 'required': break; //ignore required field (define required fields in form class)
				case 'disabled': $smarty->assign('FBV_disabled', $params['disabled']); break;
				case 'multilingual': $smarty->assign('FBV_multilingual', $params['multilingual']); break;
				default: $textAreaParams .= htmlspecialchars($key, ENT_QUOTES, LOCALE_ENCODING) . '="' . htmlspecialchars($value, ENT_QUOTES, LOCALE_ENCODING) . '" ';
			}
		}

		$smarty->assign('FBV_textAreaParams', $textAreaParams);

		return $smarty->fetch('form/textarea.tpl');
	}

	/**
	 * Hidden input element.
	 * parameters: value, id, name (optional - assigned value of 'id' by default), disabled (optional), multilingual (optional)
	 * @param $params array
	 * @param $smarty object
	 */
	function _smartyFBVHiddenInput($params, &$smarty) {
		$params = $this->_addClientSideValidation($params);

		$params['name'] = isset($params['name']) ? $params['name'] : $params['id'];
		$params['value'] = isset($params['value']) ? $params['value'] : '';
		$smarty->assign('FBV_validation', null); // Reset form validation fields in memory

		$hiddenInputParams = '';
		foreach ($params as $key => $value) {
			switch ($key) {
				case 'name': $smarty->assign('FBV_name', $value); break;
				case 'id': $smarty->assign('FBV_id', $value); break;
				case 'value': $smarty->assign('FBV_value', $value); break;
				case 'validation': $smarty->assign('FBV_validation', $params['validation']); break;
				case 'label': break;
				case 'type': break;
				case 'class': break; //ignore class attributes
				case 'required': break; //ignore required field (define required fields in form class)
				default: $hiddenInputParams .= htmlspecialchars($key, ENT_QUOTES, LOCALE_ENCODING) . '="' . htmlspecialchars($value, ENT_QUOTES, LOCALE_ENCODING) . '" ';
			}
		}

		$smarty->assign('FBV_hiddenInputParams', $hiddenInputParams);

		return $smarty->fetch('form/hiddenInput.tpl');
	}

	/**
	 * Form select control.
	 * parameters: from [array], selected [array index], defaultLabel (optional), defaultValue (optional), disabled (optional),
	 * 	translate (optional), name (optional - value of 'id' by default)
	 * @param $params array
	 * @param $smarty object
	 */
	function _smartyFBVSelect($params, &$smarty) {
		$params['name'] = isset($params['name']) ? $params['name'] : $params['id'];
		$params['translate'] = isset($params['translate']) ? $params['translate'] : true;
		$params['disabled'] = isset($params['disabled']) ? $params['disabled'] : false;

		$selectParams = '';
		if (!$params['defaultValue'] || !$params['defaultLabel']) {
			if (isset($params['defaultValue'])) unset($params['defaultValue']);
			if (isset($params['defaultLabel'])) unset($params['defaultLabel']);
			$smarty->assign('FBV_defaultValue', null);
			$smarty->assign('FBV_defaultLabel', null);
		}

		foreach ($params as $key => $value) {
			switch ($key) {
				case 'from': $smarty->assign('FBV_from', $value); break;
				case 'selected': $smarty->assign('FBV_selected', $value); break;
				case 'translate': $smarty->assign('FBV_translate', $value); break;
				case 'defaultValue': $smarty->assign('FBV_defaultValue', $value); break;
				case 'defaultLabel': $smarty->assign('FBV_defaultLabel', $value); break;
				case 'class': break;
				case 'type': break;
				case 'disabled': $smarty->assign('FBV_disabled', $params['disabled']); break;
				default: $selectParams .= htmlspecialchars($key, ENT_QUOTES, LOCALE_ENCODING) . '="' . htmlspecialchars($value, ENT_QUOTES, LOCALE_ENCODING) . '" ';
			}
		}

		$smarty->assign('FBV_selectParams', $selectParams);

		return $smarty->fetch('form/select.tpl');
	}

	/**
	 * Checkbox input control.
	 * parameters: label, disabled (optional), translate (optional), name (optional - value of 'id' by default)
	 * @param $params array
	 * @param $smarty object
	 */
	function _smartyFBVCheckbox($params, &$smarty) {
		$params = $this->_addClientSideValidation($params);

		$params['name'] = isset($params['name']) ? $params['name'] : $params['id'];
		$params['translate'] = isset($params['translate']) ? $params['translate'] : true;
		$params['checked'] = isset($params['checked']) ? $params['checked'] : false;
		$params['disabled'] = isset($params['disabled']) ? $params['disabled'] : false;
		$params['required'] = isset($params['required']) ? $params['required'] : false;
		$smarty->assign('FBV_validation', null); // Reset form validation fields in memory

		$checkboxParams = '';
		foreach ($params as $key => $value) {
			switch ($key) {
				case 'class': break;
				case 'type': break;
				case 'id': $smarty->assign('FBV_id', $params['id']); break;
				case 'label': $smarty->assign('FBV_label', $params['label']); break;
				case 'validation': $smarty->assign('FBV_validation', $params['validation']); break;
				case 'required': $smarty->assign('FBV_required', $params['required']); break;
				case 'translate': $smarty->assign('FBV_translate', $params['translate']); break;
				case 'checked': $smarty->assign('FBV_checked', $params['checked']); break;
				case 'disabled': $smarty->assign('FBV_disabled', $params['disabled']); break;
				default: $checkboxParams .= htmlspecialchars($key, ENT_QUOTES, LOCALE_ENCODING) . '="' . htmlspecialchars($value, ENT_QUOTES, LOCALE_ENCODING) . '" ';
			}
		}

		$smarty->assign('FBV_checkboxParams', $checkboxParams);

		return $smarty->fetch('form/checkbox.tpl');
	}

	/**
	 * Radio input control.
	 * parameters: label, disabled (optional), translate (optional), name (optional - value of 'id' by default)
	 * @param $params array
	 * @param $smarty object
	 */
	function _smartyFBVRadioButton($params, &$smarty) {
		$params['name'] = isset($params['name']) ? $params['name'] : $params['id'];
		$params['translate'] = isset($params['translate']) ? $params['translate'] : true;
		$params['checked'] = isset($params['checked']) ? $params['checked'] : false;
		$params['disabled'] = isset($params['disabled']) ? $params['disabled'] : false;

		$radioParams = '';
		foreach ($params as $key => $value) {
			switch ($key) {
				case 'type': break;
				case 'class': break;
				case 'id': $smarty->assign('FBV_id', $params['id']); break;
				case 'label': $smarty->assign('FBV_label', $params['label']); break;
				case 'translate': $smarty->assign('FBV_translate', $params['translate']); break;
				case 'checked': $smarty->assign('FBV_checked', $params['checked']); break;
				case 'disabled': $smarty->assign('FBV_disabled', $params['disabled']); break;
				default: $radioParams .= htmlspecialchars($key, ENT_QUOTES, LOCALE_ENCODING) . '="' . htmlspecialchars($value, ENT_QUOTES, LOCALE_ENCODING) . '" ';
			}
		}

		$smarty->assign('FBV_radioParams', $radioParams);

		return $smarty->fetch('form/radioButton.tpl');
	}

	/**
	 * File upload input.
	 * parameters: submit (optional - name of submit button to include), disabled (optional), name (optional - value of 'id' by default)
	 * @param $params array
	 * @param $smarty object
	 */
	function _smartyFBVFileInput($params, &$smarty) {
		$params['name'] = isset($params['name']) ? $params['name'] : $params['id'];
		$params['translate'] = isset($params['translate']) ? $params['translate'] : true;
		$params['checked'] = isset($params['checked']) ? $params['checked'] : false;
		$params['disabled'] = isset($params['disabled']) ? $params['disabled'] : false;
		$params['submit'] = isset($params['submit']) ? $params['submit'] : false;

		$radioParams = '';
		foreach ($params as $key => $value) {
			switch ($key) {
				case 'type': break;
				case 'class': break;
				case 'id': $smarty->assign('FBV_id', $params['id']); break;
				case 'submit': $smarty->assign('FBV_submit', $params['submit']); break;
				case 'name': $smarty->assign('FBV_name', $params['name']); break;
				case 'label': $smarty->assign('FBV_label', $params['label']); break;
				case 'disabled': $smarty->assign('FBV_disabled', $params['disabled']); break;
				default: $radioParams .= htmlspecialchars($key, ENT_QUOTES, LOCALE_ENCODING) . '="' . htmlspecialchars($value, ENT_QUOTES, LOCALE_ENCODING) . '" ';
			}
		}

		$smarty->assign('FBV_radioParams', $radioParams);

		return $smarty->fetch('form/fileInput.tpl');
	}

	/**
	 * Keyword input.
	 * parameters: available - all available keywords (for autosuggest); current - user's current keywords
	 * @param $params array
	 * @param $smarty object
	 */
	function _smartyFBVKeywordInput($params, &$smarty) {
		foreach ($params as $key => $value) {
			switch ($key) {
				case 'type': break;
				case 'class': break;
				case 'id': $smarty->assign('FBV_id', $params['id']); break;
				case 'label': $smarty->assign('FBV_label', $params['label']); break;
				case 'available': $smarty->assign('FBV_availableKeywords', $params['available']); break;
				case 'current': $smarty->assign('FBV_currentKeywords', $params['current']); break;
				default: $keywordParams .= htmlspecialchars($key, ENT_QUOTES, LOCALE_ENCODING) . '="' . htmlspecialchars($value, ENT_QUOTES, LOCALE_ENCODING) . '" ';
			}
		}

		$smarty->assign('FBV_keywordParams', $keywordParams);

		return $smarty->fetch('form/keywordInput.tpl');
	}


	/**
	 * Assign the appropriate class name to the element for client-side validation
	 * @param $params array
	 * return array
	 */
	function _addClientSideValidation($params) {
		// Assign the appropriate class name to the element for client-side validation
		$fieldId = $params['id'];
		if (isset($this->cssValidation[$fieldId])) {
			$params['validation'] = implode(' ', $this->cssValidation[$fieldId]);
		}

		return $params;
	}


	/**
	 * Cycle through layout parameters to add the appropriate classes to the element's parent container
	 * @param $params array
	 * @return string
	 */
	function _getLayoutInfo($params) {
		$classes = array();
		foreach ($params as $key => $value) {
			switch ($key) {
				case 'size':
					switch($value) {
						case 'SMALL': $classes[] = 'quarter'; break;
						case 'MEDIUM': $classes[] = 'half'; break;
					}
					break;
				case 'inline':
					if($value) $classes[] = 'inline'; break;
			}
		}
		if(!empty($classes)) {
			return implode(' ', $classes);
		} else return null;
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
