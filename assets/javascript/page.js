var url_modified = false;

jQuery(document).ready(function($){

	var page_title_field = $('#Cms_Page_name');
	if (page_title_field.length > 0 && $('#new_flag').length > 0) {
		page_title_field.bind('keyup', function() { update_url_title(page_title_field) });		
		page_title_field.bind('change', function() { update_url_title(page_title_field) });
		page_title_field.bind('paste', function() { update_url_title(page_title_field) });
	}
	
	if ($('#new_flag').length > 0) {
		var url_element = $('#Cms_Page_url');
		url_element.bind('change', function(){ url_modified = true; });
	}

	function update_url_title(field_element) {
		if (!url_modified) {
			$('#Cms_Page_url').val('/' + convert_text_to_url(field_element.val()));
			$('#Cms_Page_url').trigger('modified');
		}
	}

});