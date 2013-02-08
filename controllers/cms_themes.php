<?php

class Cms_Themes extends Admin_Controller
{
	public $implement = 'Db_ListBehavior, Db_FormBehavior';
	public $list_model_class = 'Cms_Theme';
	public $list_no_data_message = 'No themes found.';
	public $list_record_url = null;
	
	public $form_preview_title = 'Theme';
	public $form_create_title = 'New Theme';
	public $form_edit_title = 'Edit Theme';
	public $form_model_class = 'Cms_Theme';
	public $form_not_found_message = 'Theme not found';
	public $form_redirect = null;
	public $form_edit_save_auto_timestamp = true;

	public $form_edit_save_flash = 'The theme has been successfully saved';
	public $form_create_save_flash = 'The theme has been successfully added';
	public $form_edit_delete_flash = 'The theme has been successfully deleted';

	protected $required_permissions = array('cms:manage_themes');

	public function __construct()
	{
		parent::__construct();
		$this->app_menu = 'cms';
		$this->app_module_name = 'CMS';

		$this->list_record_url = url('/cms/themes/edit/');
		$this->form_redirect = url('/cms/themes');
		$this->form_create_save_redirect = url('/cms/themes/edit/%s/');
		$this->app_page = 'themes';
	}
	
	public function index()
	{
		$this->app_page_title = 'Themes';
	}
	
	protected function index_on_refresh()
	{
		$this->display_partial('themes_page_content');
	}
	
	public function list_get_row_class($model)
	{
		if ($model->default_theme)
			return 'important';
			
		if (!$model->enabled)
			return 'deleted';
	}
		
	protected function index_onshow_set_default_theme_form()
	{
		try
		{
			$ids = post('list_ids', array());
			$this->view_data['theme_id'] = count($ids) ? $ids[0] : null;

			$this->view_data['themes'] = Cms_Theme::create()->where('default_theme is null')->order('name')->find_all();
		} 
		catch (Exception $ex)
		{
			$this->handle_page_error($ex);
		}

		$this->display_partial('set_default_theme_form');
	}
	
	protected function index_onset_default_theme()
	{
		try
		{
			$theme_id = post('theme_id');
			
			if (!$theme_id)
				throw new Phpr_ApplicationException("Please select a default theme");
				
			$theme = Cms_Theme::create()->find($theme_id);
			if (!$theme)
				throw new Phpr_ApplicationException("Theme not found");

			$theme->make_default();
			Phpr::$session->flash['success'] = sprintf('Theme "%s" is now the default theme', h($theme->name));
			$this->display_partial('themes_page_content');

			Cms_Theme::auto_create_all_from_files();
		}
		catch (Exception $ex)
		{
			Phpr::$response->ajax_report_exception($ex, true, true);
		}
	}
	
	protected function index_ondelete_selected()
	{
		$items_processed = 0;
		$items_deleted = 0;

		$item_ids = post('list_ids', array());
		$this->view_data['list_checked_records'] = $item_ids;

		foreach ($item_ids as $item_id)
		{
			$item = null;
			try
			{
				$item = Cms_Theme::create()->find($item_id);
				if (!$item)
					throw new Phpr_ApplicationException('Theme with identifier '.$item_id.' not found');

				$item->delete();
				$items_deleted++;
				$items_processed++;
			}
			catch (Exception $ex)
			{
				if (!$item)
					Phpr::$session->flash['error'] = $ex->getMessage();
				else
					Phpr::$session->flash['error'] = 'Error deleting theme "'.$item->name.'": '.$ex->getMessage();

				break;
			}
		}

		if ($items_processed)
		{
			$message = null;
			
			if ($items_deleted)
				$message = 'Themes deleted: '.$items_deleted;

			Phpr::$session->flash['success'] = $message;
		}

		$this->display_partial('themes_page_content');
	}
	
	protected function index_onenable_selected()
	{
		$items_processed = 0;
		$items_enabled = 0;

		$item_ids = post('list_ids', array());
		$this->view_data['list_checked_records'] = $item_ids;

		foreach ($item_ids as $item_id)
		{
			$item = null;
			try
			{
				$item = Cms_Theme::create()->find($item_id);
				if (!$item)
					throw new Phpr_ApplicationException('Theme with identifier '.$item_id.' not found');

				$item->enable_theme();
				$items_enabled++;
				$items_processed++;
			}
			catch (Exception $ex)
			{
				if (!$item)
					Phpr::$session->flash['error'] = $ex->getMessage();
				else
					Phpr::$session->flash['error'] = 'Error enabling theme "'.$item->name.'": '.$ex->getMessage();

				break;
			}
		}

		if ($items_processed)
		{
			$message = null;
			
			if ($items_enabled)
				$message = 'Themes enabled: '.$items_enabled;

			Phpr::$session->flash['success'] = $message;
		}

		$this->display_partial('themes_page_content');
	}
	
	protected function index_ondisable_selected()
	{
		$items_processed = 0;
		$items_disabled = 0;

		$item_ids = post('list_ids', array());
		$this->view_data['list_checked_records'] = $item_ids;

		foreach ($item_ids as $item_id)
		{
			$item = null;
			try
			{
				$item = Cms_Theme::create()->find($item_id);
				if (!$item)
					throw new Phpr_ApplicationException('Theme with identifier '.$item_id.' not found');

				$item->disable_theme();
				$items_disabled++;
				$items_processed++;
			}
			catch (Exception $ex)
			{
				if (!$item)
					Phpr::$session->flash['error'] = $ex->getMessage();
				else
					Phpr::$session->flash['error'] = 'Error disabling theme "'.$item->name.'": '.$ex->getMessage();

				break;
			}
		}

		if ($items_processed)
		{
			$message = null;
			
			if ($items_disabled)
				$message = 'Themes disabled: '.$items_disabled;

			Phpr::$session->flash['success'] = $message;
		}

		$this->display_partial('themes_page_content');
	}

	// Duplicate
	// 
	
	protected function index_onshow_duplicate_theme_form()
	{
		try
		{
			$ids = post('list_ids', array());
			if (count($ids) != 1)
				throw new Phpr_ApplicationException('Please choose a theme to duplicate');

			$existing_theme = $this->view_data['existing_theme'] = Cms_Theme::create()->where('id=?', $ids[0])->find();
			if (!$existing_theme)
				throw new Phpr_ApplicationException('That theme was not found');
				
			$theme = $this->view_data['theme'] = Cms_Theme::create();
			$existing_theme->init_copy($theme);
			$theme->define_form_fields('duplicate');
		} catch (Exception $ex)
		{
			$this->handle_page_error($ex);
		}

		$this->display_partial('duplicate_theme_form');
	}
	
	protected function index_onduplicate_theme()
	{
		try
		{
			$theme_id = post('theme_id');
			
			if (!$theme_id)
				throw new Phpr_ApplicationException("Original theme was not found");
				
			$theme = Cms_Theme::create()->find($theme_id);
			if (!$theme)
				throw new Phpr_ApplicationException("Original theme was not found");

			$theme->duplicate_theme(post('Cms_Theme', array()));

			Phpr::$session->flash['success'] = 'Theme has been successfully duplicated';
			$this->display_partial('themes_page_content');
		}
		catch (Exception $ex)
		{
			Phpr::$response->ajax_report_exception($ex, true, true);
		}
	}
	
	// Export
	// 

	protected function index_onshow_export_theme_form()
	{
		try
		{
			$ids = post('list_ids', array());

			$model = new Cms_Theme_Export();
			
			$model->theme_id = count($ids) ? $ids[0] : null;
			$model->define_form_fields();
			$this->view_data['model'] = $model;
		} catch (Exception $ex)
		{
			$this->handle_page_error($ex);
		}

		$this->display_partial('export_theme_form');
	}

	protected function index_onexport_theme()
	{
		try
		{
			$model = new Cms_Theme_Export();
			$file = $model->export(post('Cms_Theme_Export', array()));
			
			$theme = Cms_Theme::create()->find($model->theme_id);
			if (!$theme)
				throw new Phpr_ApplicationException("Theme not found");
			
			Phpr::$response->redirect(url('/cms/themes/get/'.$file.'/'.$theme->code.'.zip'));
		}
		catch (Exception $ex)
		{
			Phpr::$response->ajax_report_exception($ex, true, true);
		}
	}

	public function get($name, $output_name = null)
	{
		try
		{
			$this->app_page_title = 'Download CMS Export Archive';
			
			if (!preg_match('/^ahoy[0-9a-z]*$/i', $name))
				throw new Phpr_ApplicationException('File not found');

			$zip_path = PATH_APP.'/temp/'.$name;
			if (!file_exists($zip_path))
				throw new Phpr_ApplicationException('File not found');
				
			$output_name = $output_name ? $output_name : 'theme.zip';
			$file = Db_File::create()->fromFile($zip_path);
			$file->name = $output_name;
			$file->output();
			@unlink($zip_path);
		
			$this->suppress_view();
		}
		catch (Exception $ex)
		{
			$this->handle_page_error($ex);
		}
	}

	// Import
	// 

	protected function index_onshow_import_theme_form()
	{
		try
		{
			$model = new Cms_Theme_Import();
			$model->define_form_fields();
			$this->view_data['model'] = $model;
		} 
		catch (Exception $ex)
		{
			$this->handle_page_error($ex);
		}

		$this->display_partial('import_theme_form');
	}
	
	protected function index_onimport_theme()
	{
		try
		{
			$model = new Cms_Theme_Import();
			$import_manager = $model->import(post('Cms_Theme_Import', array()), $this->form_get_edit_session_key());			
			Phpr::$session->flash['success'] = 'Theme has been successfully imported';
			$this->display_partial('themes_page_content');
		}
		catch (Exception $ex)
		{
			Phpr::$response->ajax_report_exception($ex, true, true);
		}
	}	
}

