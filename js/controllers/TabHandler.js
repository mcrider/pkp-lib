/**
 * @file js/controllers/TabHandler.js
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class TabHandler
 * @ingroup js_controllers
 *
 * @brief A basic handler for a tabbed set of pages.
 *
 * See <http://jqueryui.com/demos/tabs/> for documentation on JQuery tabs.
 * Attach this handler to a div that contains a <ul> with a <li> for each tab
 * to be created.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQuery} $tabs A wrapped HTML element that
	 *  represents the tabbed interface.
	 * @param {Object} options Handler options.
	 */
	$.pkp.controllers.TabHandler = function($tabs, options) {
		this.parent($tabs, options);

		// Attach the tabs event handlers.
		this.bind('tabsselect', this.tabsSelect);
		this.bind('tabsshow', this.tabsShow);
		this.bind('tabsload', this.tabsLoad);

		if (options.emptyLastTab) {
			this.emptyLastTab_ = options.emptyLastTab;
		}

		// Render the tabs as jQueryUI tabs.
		$tabs.tabs({
			// Enable AJAX-driven tabs with JSON messages.
			ajaxOptions: {
				cache: false,
				dataFilter: this.callbackWrapper(this.dataFilter)
			},
			selected: 0
		});
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.TabHandler, $.pkp.classes.Handler);


	//
	// Private properties
	//
	/**
	 * The current tab.
	 * @private
	 * @type {jQuery}
	 */
	$.pkp.controllers.TabHandler.prototype.$currentTab_ = null;


	/**
	 * The current tab index.
	 * @private
	 * @type {number}
	 */
	$.pkp.controllers.TabHandler.prototype.currentTabIndex_ = 0;

	/**
	 * Whether to empty the previous tab when switching to a new one
	 * @private
	 * @type {boolean}
	 */
	$.pkp.controllers.TabHandler.prototype.emptyLastTab_ = false;


	//
	// Public methods
	//
	/**
	 * Event handler that is called when a tab is selected.
	 *
	 * @param {HTMLElement} tabsElement The tab element that triggered
	 *  the event.
	 * @param {Event} event The triggered event.
	 * @param {Object} ui The tabs ui data.
	 * @return {boolean} Should return true to continue tab loading.
	 */
	$.pkp.controllers.TabHandler.prototype.tabsSelect =
			function(tabsElement, event, ui) {

		if(this.emptyLastTab_) {
			this.$currentTab_.empty();
		}
		return true;
	};


	/**
	 * Event handler that is called when a tab is shown.
	 *
	 * @param {HTMLElement} tabsElement The tab element that triggered
	 *  the event.
	 * @param {Event} event The triggered event.
	 * @param {Object} ui The tabs ui data.
	 * @return {boolean} Should return true to continue tab loading.
	 */
	$.pkp.controllers.TabHandler.prototype.tabsShow =
			function(tabsElement, event, ui) {

		// Save a reference to the current tab.
		this.$currentTab_ = (ui.panel.jquery ? ui.panel : $(ui.panel));

		// Save the tab index.
		this.currentTabIndex_ = ui.index;

		return true;
	};


	/**
	 * Event handler that is called after a remote tab was loaded.
	 *
	 * @param {HTMLElement} tabsElement The tab element that triggered
	 *  the event.
	 * @param {Event} event The triggered event.
	 * @param {Object} ui The tabs ui data.
	 * @return {boolean} Should return true to continue tab loading.
	 */
	$.pkp.controllers.TabHandler.prototype.tabsLoad =
			function(tabsElement, event, ui) {

		// The default implementation does nothing.
		return true;
	};


	/**
	 * Callback that processes AJAX data returned by the server before
	 * it is displayed in a tab.
	 *
	 * @param {Object} ajaxOptions The options object from which the
	 *  callback originated.
	 * @param {string} jsonString Unparsed JSON data returned from the server.
	 * @return {string} The tab mark-up.
	 */
	$.pkp.controllers.TabHandler.prototype.dataFilter =
			function(ajaxOptions, jsonString) {

		var jsonData = this.handleJson($.parseJSON(jsonString));
		if (jsonData === false) {
			return '';
		}
		return jsonData.content;
	};


	//
	// Protected methods
	//
	/**
	 * Get the current tab.
	 * @protected
	 * @return {jQuery} The current tab.
	 */
	$.pkp.controllers.TabHandler.prototype.getCurrentTab = function() {
		return this.$currentTab_;
	};


	/**
	 * Get the current tab index.
	 * @protected
	 * @return {number} The current tab index.
	 */
	$.pkp.controllers.TabHandler.prototype.getCurrentTabIndex = function() {
		return this.currentTabIndex_;
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);
