<?php

class Cms_Templates extends Admin_Controller
{
	public $implement = 'Db_ListBehavior, Db_FormBehavior';
	public $list_model_class = 'Cms_Template';
	public $list_record_url = null;
	public $list_handle_row_click = false;

	public $form_model_class = 'Cms_Template';
	public $form_preview_title = 'Page';
	public $form_create_title = 'New Template';
	public $form_edit_title = 'Edit Template';
	public $form_not_found_message = 'Template not found';
	public $form_redirect = null;
	public $form_create_save_redirect = null;

	public $form_edit_save_flash = 'The template has been successfully saved';
	public $form_create_save_flash = 'The template has been successfully added';
	public $form_edit_delete_flash = 'The template has been successfully deleted';
	public $form_edit_save_auto_timestamp = true;

	public $list_search_enabled = true;
	public $list_search_fields = array('@name');
	public $list_search_prompt = 'find templates by name or URL';

	//public $enable_concurrency_locking = true;

	protected $global_handlers = array();

	protected $required_permissions = array('cms:manage_templates');

	public function __construct()
	{
		parent::__construct();
		$this->app_menu = 'cms';
		$this->app_module_name = 'CMS';

		$this->list_record_url = url('/cms/templates/edit/');
		$this->form_redirect = url('/cms/templates');
		$this->form_create_save_redirect = url('/cms/templates/edit/%s');
		$this->app_page = 'templates';

	}

	public function index()
	{
		Cms_Template::auto_create_from_files();
		$this->app_page_title = 'Templates';
	}

	public function listPrepareData()
	{
		$obj = Cms_Template::create();

		$theme = Cms_Theme::get_edit_theme();
		if ($theme)
			$obj->where('theme_id=?', $theme->code);

		return $obj;
	}

	public function edit_formBeforeRender($model)
	{
		$model->load_file_content();
	}

	public function formBeforeCreateSave($model, $session_key)
	{
		$theme = Cms_Theme::get_edit_theme()->code;
		if ($theme)
			$model->theme_id = $theme;
	}

	public function formAfterCreateSave($page, $session_key)
	{
		if (post('create_close'))
			$this->form_create_save_redirect = url('/cms/templates');
	}

}

