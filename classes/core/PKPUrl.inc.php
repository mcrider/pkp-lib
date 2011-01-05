<?php

/**
 * @file classes/core/PKPUrl.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPUrl
 * @ingroup core
 *
 * @brief Defines a URL object used for accessing handler methods.
 */


class PKPUrl {
	/** @var int The request to be routed */
	var $_request;

	/** @var int The short name of the router that should be used to construct the URL */
	var $_shortcut;

	/** @var mixed Optional contextual paths */
	var $_context;

	/** @var string Optional name of the handler to invoke */
	var $_handler;

	/** @var string Optional name of operation to invoke */
	var $_op;

	/** @var mixed Optional string or array of args to pass to handler */
	var $_path;

	/** @var array Optional set of name => value pairs to pass as user parameters */
	var $_params;

	/** @var string Optional name of anchor to add to URL */
	var $_anchor;

	/** @var boolean Whether or not to escape ampersands for this URL; default false. */
	var $_escape;


	/**
	 * Build a URL object.
	 * @param $request PKPRequest the request to be routed
	 * @param $shortcut int The short name of the router that should be used to construct the URL
	 * @param $context mixed Optional contextual paths
	 * @param $handler string Optional name of the handler to invoke
	 * @param $op string Optional name of operation to invoke
	 * @param $path mixed Optional string or array of args to pass to handler
	 * @param $params array Optional set of name => value pairs to pass as user parameters
	 * @param $anchor string Optional name of anchor to add to URL
	 * @param $escape boolean Whether or not to escape ampersands for this URL; default false.
	 * @return string the URL
	 */
	function PKPUrl(&$request, $shortcut, $context = null, $handler = null, $op = null, $path = null,
				$params = null, $anchor = null, $escape = false) {
		$this->setRequest($request);
		$this->setShortcut($shortcut);
		$this->setContext($context);
		$this->setHandler($handler);
		$this->setOp($op);
		$this->setPath($path);
		$this->setParams($params);
		$this->setAnchor($anchor);
		$this->setEscape($escape);
	}

	//
	// Getters / Setters
	//

	/**
	 * Set the request
	 * @param $request PKPRequest
	 */
	function setRequest($request) {
	    $this->_request = $request;
	}

	/**
	 * Get the request
	 * @return PKPRequest
	 */
	function getRequest() {
	    return $this->_request;
	}

	/**
	 * Set the shortcut
	 * @param $shortcut int
	 */
	function setShortcut($shortcut) {
	    $this->_shortcut = $shortcut;
	}

	/**
	 * Get the shortcut
	 * @return int
	 */
	function getShortcut() {
	    return $this->_shortcut;
	}

	/**
	 * Set the context
	 * @param $context mixed
	 */
	function setContext($context) {
	    $this->_context = $context;
	}

	/**
	 * Get the context
	 * @return mixed
	 */
	function getContext() {
	    return $this->_context;
	}

	/**
	 * Set the handler
	 * @param $handler string
	 */
	function setHandler($handler) {
	    $this->_handler = $handler;
	}

	/**
	 * Get the handler
	 * @return string
	 */
	function getHandler() {
	    return $this->_handler;
	}

	/**
	 * Set the op
	 * @param $op string
	 */
	function setOp($op) {
	    $this->_op = $op;
	}

	/**
	 * Get the op
	 * @return string
	 */
	function getOp() {
	    return $this->_op;
	}

	/**
	 * Set the path
	 * @param $path mixed
	 */
	function setPath($path) {
	    $this->_path = $path;
	}

	/**
	 * Get the path
	 * @return mixed
	 */
	function getPath() {
	    return $this->_path;
	}

	/**
	 * Set the params
	 * @param $params array
	 */
	function setParams($params) {
	    $this->_params = $params;
	}

	/**
	 * Get the params
	 * @return array
	 */
	function getParams() {
	    return $this->_params;
	}

	/**
	 * Set the anchor
	 * @param $anchor string
	 */
	function setAnchor($anchor) {
	    $this->_anchor = $anchor;
	}

	/**
	 * Get the anchor
	 * @return string
	 */
	function getAnchor() {
	    return $this->_anchor;
	}

	/**
	 * Set the escape value
	 * @param $escape boolean
	 */
	function setEscape($escape) {
	    $this->_escape = $escape;
	}

	/**
	 * Get the escape value
	 * @return boolean
	 */
	function getEscape() {
	    return $this->_escape;
	}


	//
	// Public methods
	//

	/**
	 * Build a URL string from this object by passing the objects data to the dispatcher
	 * @param PKPRequest $request
	 * @return string
	 */
	function getUrlString() {
		$request =& $this->getRequest();
		$router =& $request->getRouter();
		$dispatcher =& $router->getDispatcher();

		return $dispatcher->url($request, $this->getShortcut(), $this->getContext(), $this->getHandler(),
							$this->getOp(), $this->getPath(), $this->getParams(), $this->getAnchor(),
							$this->getEscape());
	}

}

?>
