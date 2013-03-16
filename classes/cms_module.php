<?php

class Cms_Module extends Core_Module_Base
{

	protected function set_module_info()
	{
		return new Core_Module_Detail(
			"Website",
			"Content Management System",
			"PHP Road",
			"http://phproad.com/"
		);
	}

	public function build_admin_menu($menu)
	{
		$top = $menu->add('cms', 'Website', 'cms/pages')->icon('font')->permission(array('manage_files', 'manage_themes', 'manage_templates', 'manage_pages', 'manage_partials', 'manage_content'));
		$top->add_child('file_manager', 'File Manager', 'cms/file_manager', 50)->permission('manage_files');
		$top->add_child('themes', 'Themes', 'cms/themes', 100)->permission('manage_themes');
		$top->add_child('templates', 'Templates', 'cms/templates', 200)->permission('manage_templates');
		$top->add_child('pages', 'Pages', 'cms/pages', 300)->permission(array('manage_pages', 'manage_content'));
		$top->add_child('partials', 'Partials', 'cms/partials', 400)->permission('manage_partials');
		$top->add_child('content', 'Content', 'cms/content', 500)->permission('manage_content');
		$top->add_child('strings', 'Language', 'cms/strings', 600)->permission('manage_content');
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
