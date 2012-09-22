<?php

class Cms_Module extends Core_Module_Base
{

    protected function set_module_info()
    {
        return new Core_Module_Detail(
            "CMS",
            "Content Management System",
            "Scripts Ahoy!",
            "http://scriptsahoy.com/"
        );
    }

    public function build_admin_menu($menu)
    {
        $top = $menu->add('cms', 'Website', 'cms/pages');
        $file_manager = $top->add_child('file_manager', 'File Manager', 'cms/file_manager', 50);
        $themes = $top->add_child('themes', 'Themes', 'cms/themes', 100);
        $templates = $top->add_child('templates', 'Templates', 'cms/templates', 200);
        $pages = $top->add_child('pages', 'Pages', 'cms/pages', 300);
        $partials = $top->add_child('partials', 'Partials', 'cms/partials', 400);
        $content = $top->add_child('content', 'Content', 'cms/content', 500);
        $strings = $top->add_child('strings', 'Language', 'cms/strings', 600);
    }

    public function build_admin_settings($settings)
    {
        $settings->add('/cms/setup', 'Website Settings', 'Site name and logo', '/modules/cms/assets/images/cms_config.png', 10);
        $settings->add('/cms/sitemaps', 'Sitemap Settings', 'How sitemap.xml is generated', '/modules/cms/assets/images/sitemap_config.png', 20);
    }

    public function build_admin_permissions($host)
    {
        $host->add_permission_field($this, 'manage_pages', 'Manage pages')->renderAs(frm_checkbox)->comment('Modify page code and content');
        $host->add_permission_field($this, 'manage_content', 'Manage page content')->renderAs(frm_checkbox)->comment('Modify page content');
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
