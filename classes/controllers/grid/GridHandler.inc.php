<?php

/**
 * @file classes/controllers/grid/GridHandler.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class GridHandler
 * @ingroup classes_controllers_grid
 *
 * @brief Class defining basic operations for handling HTML grids.
 */

// import base class
import('lib.pkp.classes.controllers.ElementListHandler');

// import action class
import('lib.pkp.classes.linkAction.LinkAction');

// grid specific action positions
define('GRID_ACTION_POSITION_DEFAULT', 'default');
define('GRID_ACTION_POSITION_ABOVE', 'above');
define('GRID_ACTION_POSITION_BELOW', 'below');

class GridHandler extends ElementListHandler {
	/**
	 * Constructor.
	 */
	function GridHandler() {
		parent::ElementListHandler();
	}

	//
	// Getters/Setters
	//

	/**
	 * Get the grid template
	 * @return string
	 */
	function getTemplate() {
		if (is_null($this->_template)) {
			$this->setTemplate('controllers/grid/grid.tpl');
		}

		return $this->_template;
	}

	//
	// Public handler methods
	//
	/**
	 * Render the entire grid controller and send
	 * it to the client.
	 * @return string the serialized grid JSON message
	 */
	function fetchGrid($args, &$request) {

		// Prepare the template to render the grid
		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign_by_ref('grid', $this);

		// Add columns to the view
		$columns =& $this->getColumns();
		$templateMgr->assign_by_ref('columns', $columns);
		$templateMgr->assign('numColumns', count($columns));

		// Render the body elements
		$gridBodyParts = $this->_renderGridBodyPartsInternally($request);
		$templateMgr->assign_by_ref('gridBodyParts', $gridBodyParts);

		// Let the view render the grid
		$json = new JSON('true', $templateMgr->fetch($this->getTemplate()));
		return $json->getString();
	}

	/**
	 * Render a row and send it to the client.
	 * @return string the serialized row JSON message
	 */
	function fetchRow(&$args, &$request) {
		// Instantiate the requested row
		$row =& $this->getRequestedRow($request, $args);

		// Render the requested row
		$json = new JSON('true', $this->_renderRowInternally($request, $row));
		return $json->getString();
	}

	/**
	 * Render a cell and send it to the client
	 * @return string the serialized cell JSON message
	 */
	function fetchCell(&$args, &$request) {
		// Check the requested column
		if(!isset($args['columnId'])) fatalError('Missing column id!');
		if(!$this->hasColumn($args['columnId'])) fatalError('Invalid column id!');
		$column =& $this->getColumn($args['columnId']);

		// Instantiate the requested row
		$row =& $this->getRequestedRow($request, $args);

		// Render the cell
		$json = new JSON('true', $this->_renderCellInternally($request, $row, $column));
		return $json->getString();
	}

	//
	// Protected methods to be overridden/used by subclasses
	//
	/**
	 * Get a new instance of a grid row. May be
	 * overridden by subclasses if they want to
	 * provide a custom row definition.
	 * @return GridRow
	 */
	function &getRowInstance() {
		//provide a sensible default row definition
		$row = new GridRow();
		return $row;
	}
}
?>