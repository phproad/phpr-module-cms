<?php

class Cms_File_Manager extends Admin_Controller
{
	protected $global_handlers = array();

	protected $required_permissions = array('cms:manage_files');

	public function __construct()
	{
		parent::__construct();
		$this->app_menu = 'cms';
		$this->app_module_name = 'CMS';
		$this->app_page = 'file_manager';
	}

	public function index()
	{
		$this->app_page_title = 'File Manager';
	}

	public function access_cache($filename=null)
	{
		$disposition = 'inline';
		$file_path = PATH_APP.'/temp/.cms_thumbs/'.$filename;
		$file = Db_File::create()->from_file($file_path);
		$file->name = $filename;
		$file->output();

		$dest_path = $file->get_file_save_path($file->disk_name);

		if (file_exists($dest_path))
			@unlink($dest_path);

		$this->suppress_view();
	}

	public function connector()
	{
		$this->suppress_view();
		include_once PATH_APP.'/modules/cms/vendor/elfinder/php/elFinderConnector.class.php';
		include_once PATH_APP.'/modules/cms/vendor/elfinder/php/elFinder.class.php';
		include_once PATH_APP.'/modules/cms/vendor/elfinder/php/elFinderVolumeDriver.class.php';
		include_once PATH_APP.'/modules/cms/vendor/elfinder/php/elFinderVolumeLocalFileSystem.class.php';

		$disabled = array('mkdir', 'mkfile', 'rename', 'resize', 'duplicate', 'upload', 'rm', 'paste', 'archive', 'extract', 'put', 'get', 'edit');
		$theme = Cms_Theme::get_edit_theme();

		if (!$theme)
			die(json_encode(array('error'=>'Nothing to browse yet. Please set up a theme first.')));

		$theme_id = $theme->code;

		$opts = array(
			'roots' => array(
				array(
					'disabled'      => Phpr::$config->get('DEMO_MODE') ? $disabled : array(),
					'driver'        => 'LocalFileSystem',
					'quarantine'    => '../../../temp/.cms_quarantine',
					'tmbURL'        => 'index.php?q='.Phpr::$config->get('ADMIN_URL', 'admin').'/cms/file_manager/access_cache/',
					'tmbPath'       => (DIRECTORY_SEPARATOR == '\\') ? '../../../temp/.cms_thumbs' : PATH_APP.'/temp/.cms_thumbs',
					'path'          => PATH_APP.'/themes/'.$theme_id.'/assets/',
					'URL'           => root_url('/themes/'.$theme_id.'/assets/')
				)
			)
		);
		$connector = new elFinderConnector(new elFinder($opts));
		$connector->run();
	}
}