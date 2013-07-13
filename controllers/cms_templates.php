<?php

class Cms_Templates extends Admin_Controller
{
	public $implement = 'Db_List_Behavior, Db_Form_Behavior';
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
		$this->app_menu = 'cms_editor';
		$this->app_module_name = 'CMS';

		$this->list_record_url = url('cms/templates/edit/');
		$this->form_redirect = url('cms/templates');
		$this->form_create_save_redirect = url('cms/templates/edit/%s');
		$this->app_page = 'templates';

	}

	public function index()
	{
		Cms_Template::auto_create_from_files();
		$this->app_page_title = 'Templates';
	}
	
	public function list_get_row_class($model)
	{
		if ($model->is_default)
			return 'important';
	}
		
	public function list_prepare_data()
	{
		$obj = Cms_Template::create()->apply_edit_theme();
		return $obj;
	}

	public function edit_form_before_display($model)
	{
		$model->load_file_content();
	}

	public function form_before_create_save($model, $session_key)
	{
		$theme = Cms_Theme::get_edit_theme()->code;
		if ($theme)
			$model->theme_id = $theme;
	}

	public function form_after_create_save($page, $session_key)
	{
		if (post('create_close'))
			$this->form_create_save_redirect = url('cms/templates');
	}

	//
	// Set Default
	// 

	protected function index_onshow_set_default_template_form()
	{
		try
		{
			$ids = post('list_ids', array());
			$this->view_data['template_id'] = count($ids) ? $ids[0] : null;

			$this->view_data['templates'] = Cms_Template::create()->where('is_default is null')->order('name')->find_all();
		} 
		catch (Exception $ex)
		{
			$this->handle_page_error($ex);
		}

		$this->display_partial('set_default_template_form');
	}
	
	protected function index_onset_default_template()
	{
		try
		{
			$template_id = post('template_id');
			
			if (!$template_id)
				throw new Phpr_ApplicationException("Please select a default template");
				
			$template = Cms_Template::create()->find($template_id);
			if (!$template)
				throw new Phpr_ApplicationException("Template not found");

			$template->make_default();

			Phpr::$session->flash['success'] = 'Template "'.h($template->name).'" is now the default template';
			$this->display_partial('templates_page_content');
		}
		catch (Exception $ex)
		{
			Phpr::$response->ajax_report_exception($ex, true, true);
		}
	}	
}