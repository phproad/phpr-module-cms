<?php

class Cms_Content extends Admin_Controller
{
	public $implement = 'Db_ListBehavior, Db_FormBehavior';
	public $list_model_class = 'Cms_Content_Block';
	public $list_record_url = null;
	public $list_handle_row_click = false;

	public $form_model_class = 'Cms_Content_Block';
	public $form_preview_title = 'Page';
	public $form_create_title = 'New Content';
	public $form_edit_title = 'Edit Content';
	public $form_not_found_message = 'Content not found';
	public $form_redirect = null;
	public $form_create_save_redirect = null;

	public $form_edit_save_flash = 'The content has been successfully saved';
	public $form_create_save_flash = 'The content has been successfully added';
	public $form_edit_delete_flash = 'The content has been successfully deleted';
	public $form_edit_save_auto_timestamp = true;

	public $list_search_enabled = true;
	public $list_search_fields = array('@name', '@code', '@content');
	public $list_search_prompt = 'find content by name, code or content';

	//public $enable_concurrency_locking = true;

	protected $global_handlers = array();

	protected $required_permissions = array('cms:manage_content');

	public function __construct()
	{
		parent::__construct();
		$this->app_menu = 'cms';
		$this->app_module_name = 'CMS';
		$this->list_record_url = url('/cms/content/edit/');
		$this->form_redirect = url('/cms/content');
		$this->form_create_save_redirect = url('/cms/content/edit/%s');
		$this->app_page = 'content';
	}

	public function index()
	{
		Cms_Theme::auto_create_all_from_files();
		$this->app_page_title = 'Content';
	}

	public function list_prepare_data()
	{
		$obj = Cms_Content_Block::create();
		$theme = Cms_Theme::get_edit_theme();
		if ($theme)
			$obj->where('theme_id=?', $theme->code);

		return $obj;
	}

	public function edit_formBeforeRender($model)
	{
		$model->load_file_content();
	}

	public function formAfterCreateSave($page, $session_key)
	{
		if (post('create_close'))
			$this->form_create_save_redirect = url('/cms/content');
	}
}

