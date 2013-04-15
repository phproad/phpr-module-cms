<?php

class Cms_Theme_Import extends Db_ActiveRecord
{
	public $table_name = 'core_settings';

	public $overwrite_files = true;

	public $custom_columns = array(
		'theme_id' => db_number,
		'overwrite_files' => db_bool,
		'components' => db_text
	);
	
	public $has_many = array(
		'file'=>array('class_name'=>'Db_File', 'foreign_key'=>'master_object_id', 'conditions'=>"master_object_class='Cms_Theme_Import'", 'order'=>'id', 'delete'=>true)
	);

	public function define_columns($context = null)
	{
		$this->define_multi_relation_column('file', 'file', 'Theme file', '@name')->validation()->required('Please upload theme archive file');
		$this->define_column('theme_id', 'Theme')->validation()->fn('trim')->required('Please select theme to import the new theme to');
		$this->define_column('overwrite_files', 'Overwrite exisiting files');
		$this->define_column('components', 'Components')->validation()->required('Please select at least one theme component to import');
	}

	public function define_form_fields($context = null)
	{
		$this->add_form_field('file')->display_as(frm_file_attachments)->display_files_as('single_file')->add_document_label('Upload file')->file_download_base_url(url('admin/files/get/'))->no_attachments_label('')->comment('Please upload theme archive file', 'above');
		$this->add_form_field('theme_id')->display_as(frm_dropdown)->empty_option('<please select>')->comment('Please select an exisiting theme to import the archive to', 'above');
		$this->add_form_field('overwrite_files')->comment('Untick this to only import new changes');
		$this->add_form_field('components')->display_as(frm_checkboxlist)->comment('Please select the theme components you would like to import', 'above');
	}

	public function get_theme_id_options($key_value = -1)
	{
		$result = array(
			-1 => '<create new theme>'
		);
		
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
	
	public function get_components_option_state($value)
	{
		return true;
	}

	public function import($data, $session_key)
	{
		Phpr::$events->fire_event('cms:on_import_theme');
		
		if (Phpr::$config->get('DEMO_MODE'))
			throw new Phpr_ApplicationException('Sorry you cannot import themes while site is in demonstration mode.');

		@set_time_limit(3600);

		try
		{
			$this->validate_data($data, $session_key);
			$this->set_data($data);
			
			$file = $this->get_all_deferred('file', $session_key)->first;

			$path_info = pathinfo($file->name);
			$ext = strtolower($path_info['extension']);
			if (!isset($path_info['extension']) || !($ext == 'zip'))
				$this->validation->set_error('Uploaded file is not a valid theme file', 'file', true);
			
			if ($this->theme_id == -1)
			{
				$theme = Cms_Theme::create();
				$theme->code = 'imported_theme';
				$theme->name = 'Imported Theme';
				$theme->save();
			}
			else
				$theme = Cms_Theme::create()->find($this->theme_id);
			
			$file_path = PATH_APP.$file->get_path();
			$theme_path = PATH_APP.'/themes/'.$theme->code;
			$temp_path = PATH_APP.'/temp/'.uniqid('phpr');

			if (!@mkdir($temp_path))
				throw new Phpr_SystemException('Unable to create directory '.$temp_path);

			if (!is_writable(PATH_APP.'/themes/') || !is_writable($theme_path))
				throw new Phpr_SystemException('Insufficient writing permissions to '.PATH_APP.'/themes');

			File_Zip::unzip($temp_path, $file_path);

			foreach ($this->components as $object)
			{
				$options = array('overwrite' => $this->overwrite_files);
				
				if ($object != "assets" && file_exists($temp_path.'/meta/'.$object))
					File_Directory::copy($temp_path.'/meta/'.$object, $theme_path.'/meta/'.$object, $options);
				
				File_Directory::copy($temp_path.'/'.$object, $theme_path.'/'.$object, $options);
			}

			Cms_Theme::auto_create_all_from_files();

			// Clean up
			@unlink($file_path);
			File_Directory::delete_recursive($temp_path);
		}
		catch (Exception $ex)
		{
			if (isset($file_path) && @file_exists($file_path))
				@unlink($file_path);
			
			if (isset($temp_path) && @file_exists($temp_path))
				File_Directory::delete_recursive($temp_path);

			throw $ex;
		}
	}
	
}

