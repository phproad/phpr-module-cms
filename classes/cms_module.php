<?php

class Cms_Module extends Core_Module_Base
{

	protected function set_module_info()
	{
		return new Core_Module_Detail(
			"CMS",
			"Content Management System",
			"PHPRoad",
			"http://phproad.com/"
		);
	}

	public function build_admin_menu($menu)
	{
		$content = $menu->add('cms_content', 'Content', 'cms/content', 300)->icon('font')->permission(array('manage_files', 'manage_content'));
		$content->add_child('content', 'Content', 'cms/content', 500)->permission('manage_content');
		$content->add_child('strings', 'Language', 'cms/strings', 600)->permission('manage_content');
		$content->add_child('file_manager', 'File Manager', 'cms/file_manager', 700)->permission('manage_files');
		
		$editor = $menu->add('cms_editor', 'Editor', 'cms/pages', 400)->icon('code')->permission(array('manage_themes', 'manage_templates', 'manage_pages', 'manage_partials'));
		$editor->add_child('pages', 'Pages', 'cms/pages', 100)->permission(array('manage_pages', 'manage_content'));
		$editor->add_child('partials', 'Partials', 'cms/partials', 200)->permission('manage_partials');
		$editor->add_child('templates', 'Templates', 'cms/templates', 300)->permission('manage_templates');
		$editor->add_child('themes', 'Themes', 'cms/themes', 400)->permission('manage_themes');
	}

	public function build_admin_settings($settings)
	{
		$settings->add('/cms/setup', 'Website Settings', 'Site name and logo', '/modules/cms/assets/images/cms_config.png', 10);
		$settings->add('/cms/sitemaps', 'Sitemap Settings', 'Control how sitemap.xml is generated', '/modules/cms/assets/images/sitemap_config.png', 20);
	}

	public function build_admin_permissions($host)
	{
		$host->add_permission_field($this, 'manage_content', 'Manage Content', 'full')->display_as(frm_checkbox)->comment('Modify dedicated content areas and language strings');
		$host->add_permission_field($this, 'manage_themes', 'Manage Themes', 'left')->display_as(frm_checkbox)->comment('Modify and install site themes');
		$host->add_permission_field($this, 'manage_files', 'Access File Manager', 'right')->display_as(frm_checkbox)->comment('Allowed to view and modify theme files');
		$host->add_permission_field($this, 'manage_templates', 'Manage Templates', 'left')->display_as(frm_checkbox)->comment('Modify page template code');
		$host->add_permission_field($this, 'manage_pages', 'Manage Pages', 'right')->display_as(frm_checkbox)->comment('Modify page code and content');
		$host->add_permission_field($this, 'manage_partials', 'Manage Partials', 'left')->display_as(frm_checkbox)->comment('Modify page partial code');
	}

	public function build_quicksearch_feed($feed, $query)
	{
		$feed->add('pages', Cms_Page::create(), array(
			'item_name' => 'Page', 
			'icon' => 'code',
			'label_field' => 'name',
			'search_fields' => array('name'),
			'link' => url('cms/pages/edit/%s')
		));

		$feed->add('partials', Cms_Partial::create(), array(
			'item_name' => 'Partial', 
			'icon' => 'code',
			'label_field' => 'name',
			'search_fields' => array('name', 'file_name'),
			'link' => url('cms/partials/edit/%s')
		));		
	}

	public function subscribe_access_points()
	{
		return array('sitemap.xml'=>'get_sitemap');
	}

	public function get_sitemap($params)
	{
		$sitemap = Cms_Sitemap::create();
		echo $sitemap->generate_sitemap();
	}

}
