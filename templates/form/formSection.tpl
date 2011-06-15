{**
 * templates/form/formSection.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Form section.
 *}

<div{if $FBV_class} class="{$FBV_class|escape}"{/if}>
	{if $FBV_title}<label class="desc"{if $FBV_labelFor} for="{$FBV_labelFor|escape}"{/if}>{translate key=$FBV_title}{if $FBV_required}<span class="req">*</span>{/if}</label>{/if}
		{foreach from=$FBV_sectionErrors item=FBV_error}
			<p class="error">{$FBV_error|escape}</p>
		{/foreach}

		{$FBV_content}
</div>

