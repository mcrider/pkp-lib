{**
 * interestsInput.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Keyword input control
 *}

<script type="text/javascript">
	$(document).ready(function(){ldelim}
		$("#interestsTextOnly").html(null).hide();
		$(".interestDescription").show();
		$("#interests").tagit({ldelim}
			itemName: "keywords",
			fieldName: "interests",
			allowSpaces: true,
			tagSource: function(search, showChoices) {ldelim}
				$.ajax({ldelim}
					url: "{url|escape:'javascript' page='user' op='getInterests' escape=false}",
					data: search,
					dataType: 'json',
					success: function(jsonData) {ldelim}
						if (jsonData.status == true) {ldelim}
							// Must explicitly escape
							// WARNING: jquery-UI > 1.8.3 supposedly auto-escapes these values.  Reinvestigate when we upgrade.
							var results = $.map(jsonData.content, function(item) {ldelim}
								return escapeHTML(item);
							{rdelim});
							showChoices(results);
						{rdelim}
					{rdelim}
				{rdelim});
			{rdelim}
		{rdelim});
	{rdelim});
</script>


<!-- The container which will be processed by tag-it.js as the interests widget -->
<ul id="interests">
	{if $interestsKeywords}{foreach from=$interestsKeywords item=interest}<li class="hidden">{$interest|escape}</li>{/foreach}{/if}
</ul><span class="interestDescription hidden">{fieldLabel for="interests" key="user.interests.description"}</span><br />
<!-- If Javascript is disabled, this field will be visible -->
<textarea name="interestsTextOnly" id="interestsTextOnly" rows="5" cols="40" class="textArea">{if $interestsTextOnly}{$interestsTextOnly|escape}{/if}</textarea>