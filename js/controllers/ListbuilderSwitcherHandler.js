/**
 * @defgroup js_controllers
 */
// Create the controllers namespace.
jQuery.pkp.controllers = jQuery.pkp.controllers || { };

/**
 * @file js/controllers/ListbuilderSwitcherHandler.js
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ListbuilderSwitcherHandler
 * @ingroup js_controllers
 *
 * @brief Handle loading of new listbuilders based on the selection of a drop-down menu.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQuery} $select The clickable element
	 *  the link action will be attached to.
	 * @param {Object} options Options to be passed through to
	 *  the modal.
	 *  
	 *   *  Options are:
	 *  - onChangeUrl string URL to call when option is selected
	 *  - defaultValue string optional Default listbuilder's value 
	 *  	(sets the select menu, loading that listbuider)
	 *  - listbuilderContainer string optional Selector for container to load listbuilder into 
	 *  	(if not set, creates a container under the select input)
	 */
	$.pkp.controllers.ListbuilderSwitcherHandler = function($select, options) {
		this.parent($select);
		
		// Make sure we're dealing with a select menu input
		if (!$select.is('select')) {
			throw Error(['A ListbuilderSwitcherHandler controller can only be bound',
				' to an HTML select input!'].join(''));
		}
		
		// Check the options.
		if (!this.checkOptions(options)) {
			throw Error('Please make sure the onChangeUrl option is set.');
		}
		this.changeAction_ = options.onChangeUrl;

		if(options.listbuilderContainer === undefined) {
			// If no default container is set, create one
			this.listbuilderContainer_ = this.createListbuilderContainer_();
		} else {
			this.listbuilderContainer_ = options.listbuilderContainer;
		}
		
		// Attach the change event handler.
		this.bind('change', this.optionSelected);
		
		// If a default listbuilder is set, load that into the container
		if(options.defaultValue !== undefined) {
			// Set the menu option so loadListbuilder_ has the correct value to pass to the server
			$select.val(options.defaultValue);
			// Load the default listbuilder
			this.getHtmlElement().trigger('change');
		}
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.ListbuilderSwitcherHandler, $.pkp.classes.Handler);
	
	//
	// Private properties
	//
	/**
	 * A remote action to be executed when an option is selected from the menu.
	 * @private
	 * @type {string}
	 */
	$.pkp.controllers.ListbuilderSwitcherHandler.prototype.
		changeAction_ = '';
	
	/**
	 * The container to load the listbuilder into.
	 * @private
	 * @type {string}
	 */
	$.pkp.controllers.ListbuilderSwitcherHandler.prototype.
		listbuilderContainer_ = '';

	
	//
	// Protected methods
	//
	/**
	 * Check whether the correct options have been
	 * given for this handler.
	 * @protected
	 * @param {Object} options Handler options.
	 * @return {boolean} True if options are ok.
	 */
	$.pkp.controllers.ListbuilderSwitcherHandler.prototype.checkOptions =
			function(options) {
		// Check for basic configuration requirements.
		return typeof options === 'object' &&
				options.onChangeUrl !== undefined;
	};
	
	
	//
	// Public static methods
	//
	/**
	 * Handle a selection from the menu
	 * @param {HTMLElement} element The element that
	 *  triggered the event.
	 * @param {Event} event Click event.
	 * @return {boolean} Should return false to stop event propagation.
	 */
	$.pkp.controllers.ListbuilderSwitcherHandler.prototype.optionSelected =
			function(element, event) {
		var $select = this.getHtmlElement();
		
		$.getJSON(this.changeAction_, {elementId: $select.val()}, function(jsonData) {
			if (jsonData.status === true) {
				$('#submissionParticipantsContainer').html(jsonData.content);
			} else {
				// Alert that loading failed
				alert(jsonData.content);
			}
		});
		
		return false;
	};
	
	//
	// Private methods
	//
	/**
	 * Create a default container to load the listbuilder into
	 *
	 * @return {string} Container ID.
	 */
	$.pkp.controllers.ListbuilderSwitcherHandler.prototype.createListbuilderContainer_ =
			function() {
	
		// Create a div element after the select menu to load the listbuilder into
		var $select = this.getHtmlElement();
		var id = $select.attr('id');
		if(!id) id = 'listbuilderSelector'; // If the select menu does not have an ID, assign one
		
		// Generate a unique ID to avoid collisions (i.e. if there are multiple listbuilder switchers).
		var uuid = $.pkp.classes.Helper.uuid();
		
		var listbuilderContainerId = id + uuid + '_listbuilderContainer';
		$select.after('<div id="' + listbuilderContainerId + '"></div>');
		
		return "#" + listbuilderContainerId;
	};

/** @param {jQuery} $ jQuery closure. */
})(jQuery);
