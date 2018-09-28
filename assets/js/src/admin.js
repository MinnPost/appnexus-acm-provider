var $ = window.jQuery;

function showEmbedOptions(field, multiple) {
	if ( multiple == '0' ) {
		$(field).parents('form').find('h2:nth-of-type(2)').hide();
		$(field).parents('form').find('h2:nth-of-type(3)').show();
		$(field).parents('form').find('table:nth-of-type(2)').hide();
		$(field).parents('form').find('table:nth-of-type(3)').show();
	} else if ( multiple == '1' ) {
		$(field).parents('form').find('h2:nth-of-type(2)').show();
		$(field).parents('form').find('h2:nth-of-type(3)').hide();
		$(field).parents('form').find('table:nth-of-type(2)').show();
		$(field).parents('form').find('table:nth-of-type(3)').hide();
	}
}

function setEmbedTags(value) {
	console.log('value is ' + value);
}

// as the drupal plugin does, we only allow one field to be a prematch or key
$(document).on('click', 'input[name="appnexus_acm_provider_multiple_embeds[]"]', function() {
	var multiple = $(this).val();
	showEmbedOptions($(this), multiple);
});

$(document).on('change', 'input[name="appnexus_acm_provider_embed_prefix"]', function() {
	var prefix = $(this).val();
	setEmbedTags(prefix);
});

$(document).ready(function() {
	var embedFieldname = 'input[name="appnexus_acm_provider_multiple_embeds[]"]';
	if ( $(embedFieldname).length) {
		var embedField = $(embedFieldname);
		var multipleEmbed = $(embedFieldname + ':checked').val();
		showEmbedOptions(embedField, multipleEmbed);
	}
});
