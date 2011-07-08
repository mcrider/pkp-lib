{**
 * linkAction.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Create a link action
 *
 * Parameters:
 *  action: A LinkAction object.
 *  contextId: The name of the context in which the link
 *   action is being placed. This is required to disambiguate
 *   actions with the same id on one page.
 *}

{* Generate the link action's button. *}
{assign var=buttonId value=$contextId|concat:"-":$action->getId():"-button-"|uniqid}
{include file="linkAction/linkActionButton.tpl" action=$action buttonId=$buttonId}

<script type="text/javascript">
	{* Attach the action handler to the button. *}
	$(function() {ldelim}
		$('#{$buttonId}').pkpHandler(
			'$.pkp.controllers.linkAction.LinkActionHandler',
				{include file="linkAction/linkActionOptions.tpl" action=$action}
			);
	{rdelim});
</script>
