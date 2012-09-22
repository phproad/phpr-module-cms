// Set these vars on the controller pages
var cms_name_field = null;
var cms_file_name_field = null;
var cms_file_name_modified = false;
var cms_page = false;

jQuery(document).ready(function($){
	if (
		cms_name_field 
		&& ($(cms_name_field).length > 0) 
		&& ($('#new_flag').length > 0) 
		&& ($(cms_file_name_field).length > 0)
	)
	{
		var element = $(cms_name_field);
		$(cms_file_name_field).bind('change', function(){ cms_file_name_modified = true; });
		element.bind('keyup', function() { update_file_name(element); });
		element.bind('change', function() { update_file_name(element); });	
		element.bind('modified', function() { update_file_name(element); });
	}
});

function update_file_name(name_field)
{
	if (cms_file_name_modified)
		return;

	var text = name_field.val();
	
	text = text.replace(/[^a-z0-9:_]/gi, '_');
	text = text.replace(/:/g, ';');
	text = text.replace(/__/g, '_');
	text = text.replace(/__/g, '_');
	text = text.replace(/^_/g, '');
	
	if (text.match(/_$/))
		text = text.substr(0, text.length-1);
		
	if (!text.length && cms_page)
		text = 'home';
	
	jQuery(cms_file_name_field).val(text.toLowerCase());
}