function themes_selected() {
	return jQuery('#listCms_Themes_index_list_body').find('tr td.list-checkbox input:checked');
}

function delete_selected() {
	if (!themes_selected().length) {
		alert('You must select the theme(s) to delete.');
		return false;
	}
	
	$('#listCms_Themes_index_list_body').phpr().post('index_ondelete_selected', {
		confirm: 'Do you really want to delete selected theme(s)? This will delete all theme templates, pages, partials and asset files.',
		customIndicator: LightLoadingIndicator,
		update: '#themes_page_content',
		afterUpdate: update_scrollable_toolbars
	}).send();

	return false;
}

function duplicate_theme() {
	var selected = themes_selected();
	if (!selected.length) {
		alert('Please select a theme to duplicate.');
		return false;
	}

	if (selected.length > 1) {
		alert('You can only duplicate one theme at a time.');
		return false;
	}

	new PopupForm('index_onshow_duplicate_theme_form', { ajaxFields: $('#listformCms_Themes_index_list') }); 
	return false;
}

function enable_selected() {
	if (!themes_selected().length) {
		alert('Please select which theme(s) to enable.');
		return false;
	}
	
	$('#listCms_Themes_index_list_body').phpr().post('index_onenable_selected', {
		customIndicator: LightLoadingIndicator,
		update: '#themes_page_content',
		afterUpdate: update_scrollable_toolbars
	}).send();
	
	return false;
}

function disable_selected() {
	if (!themes_selected().length) {
		alert('Please select theme(s) to disable.');
		return false;
	}
	
	$('#listCms_Themes_index_list_body').phpr().post('index_ondisable_selected', {
		customIndicator: LightLoadingIndicator,
		update: '#themes_page_content',
		afterUpdate: update_scrollable_toolbars
	}).send();
	
	return false;
}

function refresh_theme_list() {
	$('#listCms_Themes_index_list_body').phpr().post('index_on_refresh', {
		customIndicator: LightLoadingIndicator,
		update: '#themes_page_content',
		afterUpdate: function() {
			update_scrollable_toolbars();
		}
	}).send();
}