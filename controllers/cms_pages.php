<?php

class Cms_Pages extends Admin_Controller
{
	public $implement = 'Db_ListBehavior, Db_FormBehavior';
	public $list_model_class = 'Cms_Page';
	public $list_record_url = null;
	public $list_render_as_tree = true;
	public $list_handle_row_click = false;

	public $form_preview_title = 'Page';
	public $form_create_title = 'New Page';
	public $form_edit_title = 'Edit Page';
	public $form_model_class = 'Cms_Page';
	public $form_not_found_message = 'Page not found';
	public $form_redirect = null;
	public $form_create_save_redirect = null;
	public $form_flash_id = 'form_flash';

	public $form_edit_save_flash = 'The page has been successfully saved';
	public $form_create_save_flash = 'The page has been successfully added';
	public $form_edit_delete_flash = 'The page has been successfully deleted';
	public $form_edit_save_auto_timestamp = true;

	public $list_search_enabled = true;
	public $list_search_fields = array('@title', '@url');
	public $list_search_prompt = 'find pages by title or URL';

	//public $enable_concurrency_locking = true;

	protected $globalHandlers = array();

	protected $required_permissions = array('cms:manage_pages', 'cms:manage_content');

	public function __construct()
	{
		parent::__construct();
		$this->app_menu = 'cms';
		$this->app_module_name = 'CMS';

		$this->list_record_url = url('/cms/pages/edit/');
		$this->form_redirect = url('/cms/pages');
		$this->form_create_save_redirect = url('/cms/pages/edit/%s/'.uniqid());
		$this->app_page = 'pages';
	}

	public function index()
	{
		Cms_Theme::auto_create_all_from_files();
		$this->app_page_title = 'Pages';
	}

	public function listPrepareData()
	{
		$obj = Cms_Page::create();
		$theme = Cms_Theme::get_edit_theme();
		if ($theme)
			$obj->where('theme_id=?', $theme->code);

		return $obj;
	}

	public function edit_formBeforeRender($model)
	{
		$model->load_content_blocks();
		$model->load_file_content();
	}

	public function formAfterCreateSave($page, $session_key)
	{
		if (post('create_close'))
			$this->form_create_save_redirect = url('/cms/pages');
	}
}

