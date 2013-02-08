<?php

class Cms_Setup extends Admin_Settings_Controller
{
	public $implement = 'Db_FormBehavior';

	public $form_edit_title = 'Website Settings';
	public $form_model_class = 'Cms_Config';
	public $form_flash_id = 'form_flash';

	public $form_redirect = null;
	public $form_edit_save_flash = 'Website configuration has been saved.';

	public function __construct()
	{
		parent::__construct();
		$this->app_menu = 'system';
		$this->form_redirect = url('admin/settings/');
	}

	public function index()
	{
		$this->app_page_title = $this->form_edit_title;

		try
		{
			$record = Cms_Config::create();
			$this->view_data['form_model'] = $record;
		}
		catch (exception $ex)
		{
			$this->handle_page_error($ex);
		}
	}

	protected function index_on_save()
	{		
		try
		{
			$config = Cms_Config::create();
			$config->save(post($this->form_model_class, array()), $this->form_get_edit_session_key());
			Phpr::$session->flash['success'] = 'Website configuration has been successfully saved.';
			Phpr::$response->redirect(url('admin/settings/'));
		}
		catch (Exception $ex)
		{
			Phpr::$response->ajax_report_exception($ex, true, true);
		}
	}

}
