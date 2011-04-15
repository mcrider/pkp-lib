{**
 * templates/form/autocompleteInput.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * an autocomplete input 
 *}
<script type="text/javascript"> 
	$(function() {ldelim}
		$('#{$FBV_id}-div').pkpHandler('$.pkp.controllers.AutocompleteHandler',
			{ldelim}
				source: "{$FBV_autocompleteUrl|escape:javascript}"
			{rdelim});		
	{rdelim});
</script>

<div id="{$FBV_id}-div">
	{$FBV_textInput}
	{** remove the -value when select implemented properly **}
	<input type="hidden" id="{$FBV_id}-value" />
</div>