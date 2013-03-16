<?php

class Cms_Theme_Export extends Db_ActiveRecord
{
	public $table_name = 'core_settings';

	public $custom_columns = array(
		'theme_id' => db_number,
		'components' => db_text
	);

	public function define_columns($context = null)
	{
		$this->define_column('theme_id', 'Theme')->validation()->fn('trim')->required('Please select theme to export.');
		$this->define_column('components', 'Components')->validation()->required('Please select at least one theme component to export');
	}

	public function define_form_fields($context = null)
	{
		$this->add_form_field('theme_id')->display_as(frm_dropdown);
		$this->add_form_field('components')->display_as(frm_checkboxlist)->comment('Please select the theme components you would like to export', 'above');
	}

	public function get_theme_id_options($key_value = -1)
	{
		$result = array();
		
		$themes = Cms_Theme::create()->order('name')->find_all();
		foreach ($themes as $theme)
			$result[$theme->id] = $theme->name.' ('.$theme->code.')';
			
		return $result;
	}
	
	public function get_components_options($key_value = -1)
	{
		return array(
			'assets'=>'Assets',
			'pages'=>'Pages',
			'templates'=>'Templates',
			'partials'=>'Partials',
			'content'=>'Content',
		);
	}
	
	public function get_components_optionState($value)
	{
		return true;
	}
	
	public function export($data)
	{
		if (Phpr::$config->get('DEMO_MODE'))
			throw new Phpr_ApplicationException('Sorry you cannot export themes while site is in demonstration mode.');

		try 
		{
			$this->define_form_fields();
			$this->validate_data($data);
			$this->set_data($data);
			
			$theme = Cms_Theme::create()->find($this->theme_id);
			$theme_path = Cms_Theme::get_theme_dir($theme->code, true);
			$temp_path = PATH_APP.'/temp/'.uniqid('ahoy');
			$zip_name = uniqid('ahoy');
			$zip_path = PATH_APP.'/temp/'.$zip_name;
			$options = array('ignore'=>array('.svn', '.gitignore', '.DS_Store'));

			if (!@mkdir($temp_path))
				throw new Phpr_SystemException('Unable to create directory '.$temp_path);

			if (!@mkdir($temp_path . '/meta'))
				throw new Phpr_SystemException('Unable to create directory '.$meta_path);

			foreach ($this->components as $object)
			{
				Phpr_Files::copy_dir($theme_path.'/'.$object, $temp_path.'/'.$object, $options);

				if ($object != "assets" && file_exists($theme_path.'/meta/'.$object))
					Phpr_Files::copy_dir($theme_path.'/meta/'.$object, $temp_path.'/meta/'.$object, $options);
			}

			File_Zip::zip_directory($temp_path, $zip_path);
			Phpr_Files::remove_dir_recursive($temp_path);

		}
		catch (Exception $ex)
		{
			if (strlen($temp_path) && @file_exists($temp_path))
				Phpr_Files::remove_dir_recursive($temp_path);

			if (strlen($zip_path) && @file_exists($zip_path))
				@unlink($zip_path);

			throw $ex;
		}

		return $zip_name;
	}
}

