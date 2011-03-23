{**
 * keywordInput.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Keyword input control
 *}

<script type="text/javascript">
	<!--
	$(document).ready(function(){ldelim}
		$("#{$FBV_id}").tagit({ldelim}
			{if $FBV_locale}
				locale: '{$FBV_locale}'
			{/if}
			{if $FBV_availableKeywords}
				// This is the list of keywords in the system used to populate the autocomplete
				availableTags: [{foreach name=existingInterests from=$FBV_availableKeywords item=interest}"{$interest|escape|escape:'javascript'}"{if !$smarty.foreach.existingInterests.last}, {/if}{/foreach}],
			{/if}
			{if $FBV_currentKeywords}
				// This is the list of the user's keywords that have already been saved
				currentTags: [{foreach name=currentInterests from=$FBV_currentKeywords item=interest}"{$interest|escape|escape:'javascript'}"{if !$smarty.foreach.currentInterests.last}, {/if}{/foreach}]
			{/if}
		{rdelim});
	{rdelim});
	// -->
</script>

<div class="keywordInputContainer">
	<ul id="{$FBV_id}"><li></li></ul>
	{if $FBV_label}{if $FBV_required}{fieldLabel name=$FBV_id key=$FBV_label required="true"}{else}{fieldLabel name=$FBV_id key=$FBV_label}{/if}{/if}
</div>
