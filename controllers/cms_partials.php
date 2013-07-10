<?php

class Cms_Partials extends Admin_Controller
{
	public $implement = 'Db_List_Behavior, Db_Form_Behavior';
	public $list_model_class = 'Cms_Partial';
	public $list_record_url = null;
	public $list_handle_row_click = false;

	public $form_model_class = 'Cms_Partial';
	public $form_preview_title = 'Page';
	public $form_create_title = 'New Partial';
	public $form_edit_title = 'Edit Partial';
	public $form_not_found_message = 'Partial not found';
	public $form_redirect = null;
	public $form_create_save_redirect = null;

	public $form_edit_save_flash = 'The partial has been successfully saved';
	public $form_create_save_flash = 'The partial has been successfully added';
	public $form_edit_delete_flash = 'The partial has been successfully deleted';
	public $form_edit_save_auto_timestamp = true;

	public $list_search_enabled = true;
	public $list_search_fields = array('@name');
	public $list_search_prompt = 'find partials by name or URL';

	//public $enable_concurrency_locking = true;

	protected $global_handlers = array();

	protected $required_permissions = array('cms:manage_partials');

	public function __construct()
	{
		parent::__construct();
		$this->app_menu = 'cms_editor';
		$this->app_module_name = 'CMS';
		$this->list_record_url = url('cms/partials/edit/');
		$this->form_redirect = url('cms/partials');
		$this->form_create_save_redirect = url('cms/partials/edit/%s');
		$this->app_page = 'partials';
	}

	//
	// Index
	// 

	public function index()
	{
		Cms_Partial::auto_create_from_files();
		$this->app_page_title = 'Partials';
	}

	public function list_prepare_data()
	{
		$obj = Cms_Partial::create();
		$obj->apply_edit_and_module_theme();
		return $obj;
	}

	//
	// Edit
	// 

	public function edit_on_convert_to_theme_object($record_id)
	{
		try
		{
			$model = $this->form_find_model_object($record_id);
			$obj = $model->convert_to_theme_object();

			if ($this->form_create_save_flash)
				Phpr::$session->flash['success'] = $this->form_create_save_flash;

			$redirect_id = $obj->id;
			$redirect_url = Phpr_Util::any($this->form_create_save_redirect, $this->form_redirect);

			if (strpos($redirect_url, '%s') !== false)
				$redirect_url = sprintf($redirect_url, $redirect_id);

			if ($redirect_url)
				Phpr::$response->redirect($redirect_url);

		}
		catch (Exception $ex)
		{
			Phpr::$response->ajax_report_exception($ex, true, true);
		}
	}

	//
	// Events
	// 
	
	public function edit_form_before_display($model)
	{
		$model->load_file_content();
	}

	public function form_after_create_save($page, $session_key)
	{
		if (post('create_close'))
			$this->form_create_save_redirect = url('cms/partials');
	}
}

