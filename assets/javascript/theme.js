function themes_selected()
{
	return $('listCms_Themes_index_list_body').getElements('tr td.checkbox input').some(function(element){return element.checked});
}

function delete_selected()
{
	if (!themes_selected())
	{
		alert('Please select theme(s) to delete.');
		return false;
	}
	
	$('listCms_Themes_index_list_body').getForm().sendPhpr(
		'index_ondelete_selected',
		{
			confirm: 'Do you really want to delete selected theme(s)? This will delete all theme templates, pages, partials and asset files.',
			loadIndicator: {show: false},
			onBeforePost: LightLoadingIndicator.show.pass('Loading...'), 
			onComplete: LightLoadingIndicator.hide,
			update: 'themes_page_content',
			onAfterUpdate: update_scrollable_toolbars
		}
	);
	return false;
}

function duplicate_theme()
{
	var selected = $('listCms_Themes_index_list_body').getElements('tr td.checkbox input').filter(function(element){return element.checked});
	if (!selected.length)
	{
		alert('Please select a theme to duplicate.');
		return false;
	}

	if (selected.length > 1)
	{
		alert('Please select a single theme to duplicate.');
		return false;
	}

	new PopupForm('index_onshow_duplicate_theme_form', {ajaxFields: $('listformCms_Themes_index_list')}); return false;
}

function enable_selected()
{
	if (!themes_selected())
	{
		alert('Please select theme(s) to enable.');
		return false;
	}
	
	$('listCms_Themes_index_list_body').getForm().sendPhpr(
		'index_onenable_selected',
		{
			loadIndicator: {show: false},
			onBeforePost: LightLoadingIndicator.show.pass('Loading...'), 
			onComplete: LightLoadingIndicator.hide,
			update: 'themes_page_content',
			onAfterUpdate: update_scrollable_toolbars
		}
	);
	
	return false;
}

function disable_selected()
{
	if (!themes_selected())
	{
		alert('Please select theme(s) to disable.');
		return false;
	}
	
	$('listCms_Themes_index_list_body').getForm().sendPhpr(
		'index_ondisable_selected',
		{
			loadIndicator: {show: false},
			onBeforePost: LightLoadingIndicator.show.pass('Loading...'), 
			onComplete: LightLoadingIndicator.hide,
			update: 'themes_page_content',
			onAfterUpdate: update_scrollable_toolbars
		}
	);
	
	return false;
}

function refresh_theme_list()
{
	$('listCms_Themes_index_list_body').getForm().sendPhpr('index_on_refresh', {
		loadIndicator: {show: false}, 
		onBeforePost: LightLoadingIndicator.show.pass('Loading...'), 
		onComplete: LightLoadingIndicator.hide,
		update: 'themes_page_content',
		onAfterUpdate: function() {
			update_scrollable_toolbars();
		}
	});
}