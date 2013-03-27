<?php

class Cms_Config extends Core_Settings_Base
{
	public $record_code = 'cms_config';

	public static function create()
	{
		$config = new self();
		return $config->load();
	}
	
	protected function build_form()
	{
		$this->add_field('site_name', 'Site Name', 'full', db_varchar)->tab('General');

		// Add logo to model
		$this->add_relation('has_many', 'logo', array('class_name'=>'Db_File', 'foreign_key'=>'master_object_id', 'conditions'=>"master_object_class='Cms_Config' and field='logo'", 'order'=>'id', 'delete'=>true));
		$this->define_multi_relation_column('logo', 'logo', 'Logo', '@name')->invisible();
		$this->add_form_field('logo', 'left')->display_as(frm_file_attachments)
			->display_files_as('single_image')
			->add_document_label('Upload logo')            
			->no_attachments_label('Logo is not uploaded')
			->image_thumb_size(170)
			->no_label()            
			->tab('General');

		$this->add_field('development_mode', 'Development Mode', 'full', db_bool)->display_as(frm_onoffswitcher)->tab('General')->comment('Enable development mode if you are working on the site, this will disable caching of front end files.', 'above');
	}
	
	public function before_save($session_key = null)
	{
		parent::before_save($session_key);
	}

	protected function init_config_data()
	{
		$this->site_name = Phpr::$config->get('APP_NAME');
		$this->development_mode = true;
	}

	public static function is_dev_mode()
	{
		$obj = self::create();
		return $obj->development_mode;
	}

	public function is_configured()
	{
		$config = self::create();
		if (!$config)
			return false;

		return true;
	}

	public static function get_logo()
	{
		$settings = self::create();
		if ($settings->logo->count > 0)
			return root_url($settings->logo->first()->getPath());
		else
			return null;
	}
}