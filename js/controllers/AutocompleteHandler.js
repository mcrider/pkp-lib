/**
 * @file js/controllers/AutocompleteHandler.js
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AutocompleteHandler
 * @ingroup js_controllers
 *
 * @brief PKP autocomplete handler (extends the functionality of the jqueryUI autocomplete)
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQuery} $autocompleteField the wrapped HTML input element element.
	 * @param {Object} options options to be passed
	 *  into the jqueryUI autocomplete plugin
	 */
	$.pkp.controllers.AutocompleteHandler = function($autocompleteField, options) {
		this.parent($autocompleteField, options);

		// Get the text input inside of this Div.
		this.textInput_ = $autocompleteField.children(':text');

		// Get the text input inside of this Div.
		this.hiddenInput = $autocompleteField.children(':hidden');
		
		// Create autocomplete settings.
		var autocompleteOptions = $.extend(
			{ },
			// Default settings.
			this.self('DEFAULT_PROPERTIES_'),
			// Non-default settings.
			{
				source: options.source
			});

		// Create the autocomplete field with the jqueryUI plug-in.
		this.textInput_.autocomplete(autocompleteOptions);
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.AutocompleteHandler, $.pkp.classes.Handler);


	//
	// Private static properties
	//
	/**
	 * The text input inside the autocomplete div that holds the label.
	 * @type {HTML element}
	 */
	$.pkp.controllers.AutocompleteHandler.textInput_ = null;	

	/**
	 * The hidden input inside the autocomplete div that holds the value.
	 * @type {HTML element}
	 */
	$.pkp.controllers.AutocompleteHandler.hiddenInput_ = null;
	
	/**
	 * Default options
	 * @private
	 * @type {Object}
	 * @const
	 */
	$.pkp.controllers.AutocompleteHandler.DEFAULT_PROPERTIES_ = {
		// General settings
		minLength: 2		
	};	
	
	//
	// Public Methods
	// 
	// FIXME: I could not get this to work. this points to the HTML intput, 
	// but I need to grab the  
	$.pkp.controllers.AutocompleteHandler.prototype.itemSelected = 
		function(event, ui) {	
			return false;
	};
	
/** @param {jQuery} $ jQuery closure. */
})(jQuery);
