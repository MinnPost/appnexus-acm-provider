'use strict';

var $ = window.jQuery;

function showEmbedOptions(field, multiple) {
	if (multiple == '0') {
		$(field).parents('form').find('h2:nth-of-type(2)').hide();
		$(field).parents('form').find('h2:nth-of-type(3)').show();
		$(field).parents('form').find('table:nth-of-type(2)').hide();
		$(field).parents('form').find('table:nth-of-type(3)').show();
	} else if (multiple == '1') {
		$(field).parents('form').find('h2:nth-of-type(2)').show();
		$(field).parents('form').find('h2:nth-of-type(3)').hide();
		$(field).parents('form').find('table:nth-of-type(2)').show();
		$(field).parents('form').find('table:nth-of-type(3)').hide();
	}
}

// as the drupal plugin does, we only allow one field to be a prematch or key
$(document).on('click', 'input[name="appnexus_acm_provider_multiple_embeds[]"]', function () {
	var multiple = $(this).val();
	showEmbedOptions($(this), multiple);
});

$(document).ready(function () {
	var fieldname = 'input[name="appnexus_acm_provider_multiple_embeds[]"]';
	var field = $(fieldname);
	var multiple = $(fieldname + ':checked').val();
	showEmbedOptions(field, multiple);
});
//# sourceMappingURL=data:application/json;charset=utf8;base64,eyJ2ZXJzaW9uIjozLCJzb3VyY2VzIjpbImFkbWluLmpzIl0sIm5hbWVzIjpbIiQiLCJ3aW5kb3ciLCJqUXVlcnkiLCJzaG93RW1iZWRPcHRpb25zIiwiZmllbGQiLCJtdWx0aXBsZSIsInBhcmVudHMiLCJmaW5kIiwiaGlkZSIsInNob3ciLCJkb2N1bWVudCIsIm9uIiwidmFsIiwicmVhZHkiLCJmaWVsZG5hbWUiXSwibWFwcGluZ3MiOiI7O0FBQUEsSUFBSUEsSUFBSUMsT0FBT0MsTUFBZjs7QUFFQSxTQUFTQyxnQkFBVCxDQUEwQkMsS0FBMUIsRUFBaUNDLFFBQWpDLEVBQTJDO0FBQzFDLEtBQUtBLFlBQVksR0FBakIsRUFBdUI7QUFDdEJMLElBQUVJLEtBQUYsRUFBU0UsT0FBVCxDQUFpQixNQUFqQixFQUF5QkMsSUFBekIsQ0FBOEIsbUJBQTlCLEVBQW1EQyxJQUFuRDtBQUNBUixJQUFFSSxLQUFGLEVBQVNFLE9BQVQsQ0FBaUIsTUFBakIsRUFBeUJDLElBQXpCLENBQThCLG1CQUE5QixFQUFtREUsSUFBbkQ7QUFDQVQsSUFBRUksS0FBRixFQUFTRSxPQUFULENBQWlCLE1BQWpCLEVBQXlCQyxJQUF6QixDQUE4QixzQkFBOUIsRUFBc0RDLElBQXREO0FBQ0FSLElBQUVJLEtBQUYsRUFBU0UsT0FBVCxDQUFpQixNQUFqQixFQUF5QkMsSUFBekIsQ0FBOEIsc0JBQTlCLEVBQXNERSxJQUF0RDtBQUNBLEVBTEQsTUFLTyxJQUFLSixZQUFZLEdBQWpCLEVBQXVCO0FBQzdCTCxJQUFFSSxLQUFGLEVBQVNFLE9BQVQsQ0FBaUIsTUFBakIsRUFBeUJDLElBQXpCLENBQThCLG1CQUE5QixFQUFtREUsSUFBbkQ7QUFDQVQsSUFBRUksS0FBRixFQUFTRSxPQUFULENBQWlCLE1BQWpCLEVBQXlCQyxJQUF6QixDQUE4QixtQkFBOUIsRUFBbURDLElBQW5EO0FBQ0FSLElBQUVJLEtBQUYsRUFBU0UsT0FBVCxDQUFpQixNQUFqQixFQUF5QkMsSUFBekIsQ0FBOEIsc0JBQTlCLEVBQXNERSxJQUF0RDtBQUNBVCxJQUFFSSxLQUFGLEVBQVNFLE9BQVQsQ0FBaUIsTUFBakIsRUFBeUJDLElBQXpCLENBQThCLHNCQUE5QixFQUFzREMsSUFBdEQ7QUFDQTtBQUNEOztBQUVEO0FBQ0FSLEVBQUVVLFFBQUYsRUFBWUMsRUFBWixDQUFlLE9BQWYsRUFBd0IsdURBQXhCLEVBQWlGLFlBQVc7QUFDM0YsS0FBSU4sV0FBV0wsRUFBRSxJQUFGLEVBQVFZLEdBQVIsRUFBZjtBQUNBVCxrQkFBaUJILEVBQUUsSUFBRixDQUFqQixFQUEwQkssUUFBMUI7QUFDQSxDQUhEOztBQUtBTCxFQUFFVSxRQUFGLEVBQVlHLEtBQVosQ0FBa0IsWUFBVztBQUM1QixLQUFJQyxZQUFZLHVEQUFoQjtBQUNBLEtBQUlWLFFBQVFKLEVBQUVjLFNBQUYsQ0FBWjtBQUNBLEtBQUlULFdBQVdMLEVBQUVjLFlBQVksVUFBZCxFQUEwQkYsR0FBMUIsRUFBZjtBQUNBVCxrQkFBaUJDLEtBQWpCLEVBQXdCQyxRQUF4QjtBQUNBLENBTEQiLCJmaWxlIjoiYXBwbmV4dXMtYWNtLXByb3ZpZGVyLWFkbWluLmpzIiwic291cmNlc0NvbnRlbnQiOlsidmFyICQgPSB3aW5kb3cualF1ZXJ5O1xuXG5mdW5jdGlvbiBzaG93RW1iZWRPcHRpb25zKGZpZWxkLCBtdWx0aXBsZSkge1xuXHRpZiAoIG11bHRpcGxlID09ICcwJyApIHtcblx0XHQkKGZpZWxkKS5wYXJlbnRzKCdmb3JtJykuZmluZCgnaDI6bnRoLW9mLXR5cGUoMiknKS5oaWRlKCk7XG5cdFx0JChmaWVsZCkucGFyZW50cygnZm9ybScpLmZpbmQoJ2gyOm50aC1vZi10eXBlKDMpJykuc2hvdygpO1xuXHRcdCQoZmllbGQpLnBhcmVudHMoJ2Zvcm0nKS5maW5kKCd0YWJsZTpudGgtb2YtdHlwZSgyKScpLmhpZGUoKTtcblx0XHQkKGZpZWxkKS5wYXJlbnRzKCdmb3JtJykuZmluZCgndGFibGU6bnRoLW9mLXR5cGUoMyknKS5zaG93KCk7XG5cdH0gZWxzZSBpZiAoIG11bHRpcGxlID09ICcxJyApIHtcblx0XHQkKGZpZWxkKS5wYXJlbnRzKCdmb3JtJykuZmluZCgnaDI6bnRoLW9mLXR5cGUoMiknKS5zaG93KCk7XG5cdFx0JChmaWVsZCkucGFyZW50cygnZm9ybScpLmZpbmQoJ2gyOm50aC1vZi10eXBlKDMpJykuaGlkZSgpO1xuXHRcdCQoZmllbGQpLnBhcmVudHMoJ2Zvcm0nKS5maW5kKCd0YWJsZTpudGgtb2YtdHlwZSgyKScpLnNob3coKTtcblx0XHQkKGZpZWxkKS5wYXJlbnRzKCdmb3JtJykuZmluZCgndGFibGU6bnRoLW9mLXR5cGUoMyknKS5oaWRlKCk7XG5cdH1cbn1cblxuLy8gYXMgdGhlIGRydXBhbCBwbHVnaW4gZG9lcywgd2Ugb25seSBhbGxvdyBvbmUgZmllbGQgdG8gYmUgYSBwcmVtYXRjaCBvciBrZXlcbiQoZG9jdW1lbnQpLm9uKCdjbGljaycsICdpbnB1dFtuYW1lPVwiYXBwbmV4dXNfYWNtX3Byb3ZpZGVyX211bHRpcGxlX2VtYmVkc1tdXCJdJywgZnVuY3Rpb24oKSB7XG5cdHZhciBtdWx0aXBsZSA9ICQodGhpcykudmFsKCk7XG5cdHNob3dFbWJlZE9wdGlvbnMoJCh0aGlzKSwgbXVsdGlwbGUpO1xufSk7XG5cbiQoZG9jdW1lbnQpLnJlYWR5KGZ1bmN0aW9uKCkge1xuXHR2YXIgZmllbGRuYW1lID0gJ2lucHV0W25hbWU9XCJhcHBuZXh1c19hY21fcHJvdmlkZXJfbXVsdGlwbGVfZW1iZWRzW11cIl0nO1xuXHR2YXIgZmllbGQgPSAkKGZpZWxkbmFtZSk7XG5cdHZhciBtdWx0aXBsZSA9ICQoZmllbGRuYW1lICsgJzpjaGVja2VkJykudmFsKCk7XG5cdHNob3dFbWJlZE9wdGlvbnMoZmllbGQsIG11bHRpcGxlKTtcbn0pO1xuIl19
