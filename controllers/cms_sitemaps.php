<?php

class Cms_Sitemaps extends Admin_Settings_Controller
{
	public $implement = 'Db_Form_Behavior';

	public $form_edit_title = 'Sitemap Settings';
	public $form_model_class = 'Cms_Sitemap';
	public $form_flash_id = 'form_flash';

	public $form_redirect = null;
	public $form_edit_save_flash = 'Sitemap configuration has been saved.';

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
			$active_theme = Cms_Theme::get_active_theme();
			$record = Cms_Sitemap::create();
			$pages = Cms_Page::create()->where('theme_id=?', $active_theme->code)->order('theme_id asc')->order('sort_order asc')->find_all();
			$this->view_data['pages'] = $pages;
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
			// Save config
			$config = Cms_Sitemap::create();
			$config->save(post($this->form_model_class, array()), $this->form_get_edit_session_key());

			// Save page selections
			$page_list = Cms_Page::create()->find_all();
			foreach ($page_list as $page) 
			{
				$visible = post_array('pages', $page->id) ? 1 : 0;
				$bind = array(
					'visible' => $visible, 
					'id' => $page->id
				);
				Db_Helper::query('update cms_pages set sitemap_visible=:visible where id=:id', $bind);
			}

			// Redirect
			Phpr::$session->flash['success'] = 'Sitemap configuration has been successfully saved';
			Phpr::$response->redirect(url('admin/settings'));
		}
		catch (Exception $ex)
		{
			Phpr::$response->ajax_report_exception($ex, true, true);
		}
	}

}
