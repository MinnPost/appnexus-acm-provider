'use strict';

var $ = window.jQuery;

$(document).ready(function () {
	$('.appnexus-ad').on('scrollin', function(event) {
		var ad_tag_id = $(this).find('.load-ad').data('oas-tag-id');
		$('.load-ad', $(this)).replaceWith('<div id="oas_' + ad_tag_id + '"></div>');
		oas_tag.loadAd(ad_tag_id);
	});
});
