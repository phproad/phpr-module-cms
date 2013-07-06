<?php

class Cms_Template extends Cms_Base
{
	public $table_name = 'cms_templates';
	
	public $implement = 'Db_AutoFootprints';
	public $auto_footprints_visible = true;

	public $cms_folder_name = "templates";
	public $cms_fields_to_save = array('name','is_default','unique_id');
	
	public $ignore_file_copy = false;
	
	public function define_columns($context = null)
	{
		$this->define_column('unique_id', 'Unique ID')->invisible();
		$this->define_column('name', 'Name')->order('asc')->validation()->fn('trim')->required("You must specify a template name.");
		$this->define_column('file_name', 'File Name')->validation()->fn('trim')->required("Please specify a file name.")
			->regexp('/^[a-z_0-9-;]*$/i', 'File name can only contain letters, numbers, underscore (_), dash (-), forward slash (/), dot (.) and semi-colon (;)')
			->fn('strtolower')->unique('File name (%s) is already in use', array($this, 'config_unique_validator'));

		$this->define_column('is_default', 'Default');
		$this->define_column('content', 'Content')->invisible()->validation()->required('You must specify the template content.');
	}
	
	public function define_form_fields($context = null)
	{
		$this->add_form_field('name','left')->collapsible();
		$this->add_form_field('file_name','right')->collapsible();
		$this->add_form_field('content')->size('giant')->css_classes('code')->display_as(frm_code_editor)->language('php')->save_callback('save_code');
	}
	
	//
	// Events
	// 

	public function before_save($session_key = null) 
	{
		if (Phpr::$config->get('DEMO_MODE') && !$this->ignore_file_copy)
			throw new Phpr_ApplicationException('Sorry you cannot modify templates while site is in demonstration mode.');
	}

	public function before_delete($id = null) 
	{
		if (Phpr::$config->get('DEMO_MODE') && !$this->ignore_file_copy)
			throw new Phpr_ApplicationException('Sorry you cannot modify templates while site is in demonstration mode.');

		$in_use = Db_Helper::scalar('select count(*) from cms_pages where template_id=:id', array('id'=>$this->id));
		
		if ($in_use)
			throw new Phpr_ApplicationException("Unable to delete template because there are pages (".$in_use.") which use it.");

		if ($this->is_default)
			throw new Phpr_ApplicationException('Unable to delete template because '.$this->name.' is set as default. Set a different default template and try again.');
	}

	public function after_delete()
	{
		if (Cms_Theme::theme_dir_is_writable($this->theme_id) && $this->file_name)
			$this->delete_file($this->file_name);
	}	

	public function after_save()
	{
		$this->copy_to_file();
		$this->save_settings();
	}

	public function after_update()
	{
		if ($this->file_name != $this->fetched['file_name'])
		{
			$this->delete_file($this->fetched['file_name']);
		}
	}

	public function before_create($session_key = null) 
	{
		if (!strlen($this->unique_id))
			$this->unique_id = uniqid("", true);

		if (!strlen($this->theme_id))
			$this->theme_id = Cms_Theme::get_edit_theme()->code;
	}

	//
	// Filters
	// 

	public function apply_edit_theme()
	{
		$theme = Cms_Theme::get_edit_theme();

		if ($theme)
			$this->where('theme_id=?', $theme->code);

		return $this;
	}

	//
	// Service methods
	// 

	public function make_default()
	{
		$bind = array('id' => $this->id);
		Db_Helper::query('update cms_templates set is_default=1 where id=:id', $bind);
		Db_Helper::query('update cms_templates set is_default=null where id!=:id', $bind);
	}

	public function get_content()
	{
		try
		{
			if (!$this->file_name)
				throw new Phpr_ApplicationException('Template file is not specified');
				
			$path = $this->get_file_path($this->file_name);
				
			if (!file_exists($path))
				throw new Phpr_ApplicationException('Template file not found: '.$path);
			
			return file_get_contents($path);
			
		} 
		catch (exception $ex)
		{
			throw new Phpr_ApplicationException('Error rendering CMS template '.$this->name.'. '.$ex->getMessage());
		}		
	}

	//
	// File based methods
	//

	public function get_file_path($file_name, $ext = 'php')
	{
		if (!$file_name)
			return null;
			
		$file_name = pathinfo($file_name, PATHINFO_FILENAME);

		return Cms_Theme::get_theme_dir($this->theme_id).'/templates/'.$file_name.'.'.$ext;
	}

	protected static function create_from_file($file_path)
	{
		$obj = self::create();		
		$obj->file_name = pathinfo($file_path, PATHINFO_FILENAME);
		$obj->load_settings();
		$obj->ignore_file_copy = true;
		$obj->content = file_get_contents($file_path);
		$obj->theme_id = Cms_Theme::get_edit_theme()->code;
		$obj->save();

		return $obj;
	}

	public static function auto_create_from_files()
	{

		$dir = Cms_Theme::get_theme_dir(false) . '/templates';

		if (file_exists($dir) && is_dir($dir))
		{

			$edit_theme = Cms_Theme::get_edit_theme()->code;
			$existing_templates = Db_Helper::object_array("select file_name from cms_templates where theme_id = '".$edit_theme."'");
			$existing_files = array();
			foreach ($existing_templates as $template)
				$existing_files[] = $template->file_name.'.php';

			$files = scandir($dir);
			foreach ($files as $file)
			{
				if (!self::is_valid_file_name($file))
					continue;

				if (!in_array($file, $existing_files))
				{
					try
					{
						self::create_from_file($dir.'/'.$file);
					}
					catch (exception $ex) 
					{
						// Do nothing
					}
				}
			}
		}		
	}	

}

