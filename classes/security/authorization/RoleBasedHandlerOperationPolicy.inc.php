<?php
/**
 * @file classes/security/authorization/RoleBasedHandlerOperationPolicy.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class RoleBasedHandlerOperationPolicy
 * @ingroup security_authorization
 *
 * @brief Class to control access to handler operations via role based access
 *  control.
 */

import('lib.pkp.classes.security.authorization.HandlerOperationPolicy');

class RoleBasedHandlerOperationPolicy extends HandlerOperationPolicy {
	/** @var array the target roles */
	var $_roles = array();

	/** @var boolean */
	var $_allRoles;

	/** @var boolean */
	var $_bypassOperationCheck;

	/**
	 * Constructor
	 * @param $request PKPRequest
	 * @param $roles array|integer either a single role ID or an array of role ids
	 * @param $operations array|string either a single operation or a list of operations that
	 *  this policy is targeting.
	 * @param $message string a message to be displayed if the authorization fails
	 * @param $allRoles boolean whether all roles must match ("all of") or whether it is
	 *  enough for only one role to match ("any of").
	 * @param $bypassOperationCheck boolean only for backwards compatibility, don't use.
	 *  FIXME: remove this parameter once we've removed the HandlerValidatorRole
	 *  compatibility class, see #5868.
	 */
	function RoleBasedHandlerOperationPolicy(&$request, $roles, $operations,
			$message = 'user.authorization.roleBasedAccessDenied',
			$allRoles = false, $bypassOperationCheck = false) {
		parent::HandlerOperationPolicy($request, $operations, $message);

		// Make sure a single role doesn't have to be
		// passed in as an array.
		assert(is_integer($roles) || is_array($roles));
		if (!is_array($roles)) {
			$roles = array($roles);
		}
		$this->_roles = $roles;
		$this->_allRoles = $allRoles;
		$this->_bypassOperationCheck = $bypassOperationCheck;
	}


	//
	// Implement template methods from AuthorizationPolicy
	//
	/**
	 * @see AuthorizationPolicy::effect()
	 */
	function effect() {
		// Check whether the user has one of the allowed roles
		// assigned. If that's the case we'll permit access.
		$request =& $this->getRequest();
		$user =& $request->getUser();
		if (!$user) return AUTHORIZATION_DENY;
		if (!$this->_checkUserRoleAssignment($user)) return AUTHORIZATION_DENY;

		// FIXME: Remove the "bypass operation check" code once we've removed the
		// HandlerValidatorRole compatibility class and make the operation
		// check unconditional, see #5868.
		if ($this->_bypassOperationCheck) {
			assert($this->getOperations() === array());
		} else {
			if (!$this->_checkOperationWhitelist()) return AUTHORIZATION_DENY;
		}

		return AUTHORIZATION_PERMIT;
	}


	//
	// Private helper methods
	//
	/**
	 * Check whether the given user has been assigned
	 * to any of the allowed roles. If so then grant
	 * access.
	 * @param $user User
	 * @return boolean
	 */
	function _checkUserRoleAssignment(&$user) {
		// Prepare the method call arguments for a
		// RoleDAO::userHasRole() call, i.e. the context
		// ids plus the user id.
		$application =& PKPApplication::getApplication();
		$contextDepth = $application->getContextDepth();
		$request =& $this->getRequest();
		$router =& $request->getRouter();
		for ($contextLevel = 1; $contextLevel <= $contextDepth; $contextLevel++) {
			$context =& $router->getContext($request, $contextLevel);
			$roleContext[] = ($context)?$context->getId():0;
			unset($context);
		}
		$roleContext[] = $user->getId();

		// Try to find a matching role.
		$foundMatchingRole = false;
		foreach($this->_roles as $roleId) {
			$foundMatchingRole = $this->_checkRoleInDatabase($roleId, $roleContext, $contextDepth);

			if ($this->_allRoles) {
				if (!$foundMatchingRole) {
					// When the "all roles" flag is switched on then
					// one missing role is enough to fail.
					return false;
				}
			} else {
				if ($foundMatchingRole) {
					// When the "all roles" flag is not set then
					// one matching role is enough to succeed.
					return true;
				}
			}
		}

		// Deny if no matching role can be found.
		if ($this->_allRoles) {
			// All roles matched, otherwise we'd have failed before.
			return true;
		} else {
			// None of the roles matched, otherwise we'd have succeeded already.
			return false;
		}
	}

	/**
	 * Checks whether the current user has the given
	 * role assigned.
	 *
	 * @param $roleId integer
	 * @param $roleContext array basic arguments for a
	 *  RoleDAO::userHasRole() call.
	 * @param $contextDepth integer context depth of the
	 *  current application.
	 * @return boolean
	 */
	function _checkRoleInDatabase($roleId, $roleContext, $contextDepth) {
		// Prepare the method arguments for a call to
		// RoleDAO::userHasRole().
		$userHasRoleArguments = $roleContext;
		$userHasRoleArguments[] = $roleId;

		$roleDao =& DAORegistry::getDAO('RoleDAO');
		if ($contextDepth > 0) {
			// Correct context for site level or manager roles.
			if ($roleId == ROLE_ID_SITE_ADMIN) {
				// site level role
				for ($contextLevel = 1; $contextLevel <= $contextDepth; $contextLevel++) {
					$userHasRoleArguments[$contextLevel-1] = 0;
				}
			} elseif ($roleId == $roleDao->getRoleIdFromPath('manager') && $contextDepth == 2) {
				// This is a main context managerial role (i.e. conference-level).
				$userHasRoleArguments[1] = 0;
			}
		}

		// Call the role DAO.
		$response = (boolean)call_user_func_array(array($roleDao, 'userHasRole'), $userHasRoleArguments);
		return $response;
	}
}

?>
