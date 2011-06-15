{**
 * templates/form/formArea.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * form area
 *}

<fieldset {if $FBV_id} id="{$FBV_id}"{/if}{if $FBV_class} class="{$FBV_class|escape}"{/if}>
	{if $FBV_title}
		<legend>{translate key=$FBV_title}</legend>
	{/if}
	{$FBV_content}
</fieldset>
<div class="pkp_helpers_clear"></div>
