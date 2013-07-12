<?php

class Cms_Partial extends Cms_Base
{
	public $implement = 'Db_AutoFootprints';
	public $auto_footprints_visible = true;

	public $cms_folder_name = "partials";
	public $cms_fields_to_save = array('name');

	public $ignore_file_copy = false;

	protected static $cache = null;

	public $custom_columns = array(
		'is_module_theme' => db_bool,
	);

	public function define_columns($context = null)
	{
		$this->define_column('name', 'Name')->order('asc')->validation()->fn('trim')->required("You must specify a partial name.");
		$this->define_column('file_name', 'File Name')->validation()->fn('trim')->required("Please specify a file name.")
			->regexp('/^[a-z_0-9-;]*$/i', 'File name can only contain letters, numbers, underscore (_), dash (-), forward slash (/), dot (.) and semi-colon (;)')
			->fn('strtolower')->unique('File name (%s) is already in use', array($this, 'config_unique_validator'));

		$this->define_column('content', 'Content')->invisible()->validation()->required('You must specify the partial content.');
	}

	public function define_form_fields($context = null)
	{
		$this->add_form_field('name','left')->collapsible();
		$this->add_form_field('file_name','right')->collapsible();
		$this->add_form_field('content')->size('giant')->css_classes('code')->display_as(frm_code_editor)->language('php')->save_callback('save_code');

		// Disable all fields
		if ($this->is_module_theme) {
			$fields = $this->get_form_fields();
			foreach ($fields as $field) {
				$field->disabled();
			}
		}
	}

	//
	// Events
	//

	public function before_delete($session_key = null)
	{
		if (Phpr::$config->get('DEMO_MODE') && !$this->ignore_file_copy)
			throw new Phpr_ApplicationException('Sorry you cannot modify partials while site is in demonstration mode.');
	}
	
	public function before_save($session_key = null)
	{
		if (Phpr::$config->get('DEMO_MODE') && !$this->ignore_file_copy)
			throw new Phpr_ApplicationException('Sorry you cannot modify partials while site is in demonstration mode.');
	}

	public function before_create($session_key = null)
	{
		if (!strlen($this->file_name))
			$this->file_name = self::name_to_file($this->name);

		if (!strlen($this->theme_id) && !$this->is_module_theme)
			$this->theme_id = Cms_Theme::get_edit_theme()->code;
	}

	public function after_delete()
	{
		if (Cms_Theme::theme_path_is_writable($this->theme_id) && $this->file_name)
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

	// 
	// Filters
	// 

	public function apply_edit_theme()
	{
		$theme_code = Cms_Theme::get_edit_theme()->code;
		$this->where('theme_id=?', $theme_code);
		return $this;
	}

	public function apply_edit_with_module_themes()
	{
		// Filter by edit theme and module theme
		$theme_code = Cms_Theme::get_edit_theme()->code;
		$this->where('cms_partials.theme_id=? or cms_partials.theme_id is null', $theme_code);

		// Join all pages with their pairs (edit theme / module theme)
		// If no join is found, the record is included anyway.
		// If a join is found, return the record joining to the module 
		// theme, i.e the edit theme.
		$this->join('cms_partials as cms_module_partials', 'cms_module_partials.name = cms_partials.name and cms_module_partials.id != cms_partials.id');
		$this->where('cms_module_partials.theme_id is null');

		return $this;
	}

	//
	// Getters
	//

	/**
	 * For caching/lean reasons, this static method can take a populated stdObject or Cms_Partial object
	 */ 
	public static function get_content($partial=null)
	{
		try
		{
			$name = $partial->name;

			if (!$name)
				throw new Phpr_ApplicationException('Partial file is not specified');

			if ($partial->module_id)
			{
				$path = Phpr_Module_Manager::get_module_path($partial->module_id).'/theme/partials/'.$file_name.'.php';
			}
			else
			{
				// Same as Cms_Base::get_file_path but static
				$theme = Cms_Theme::get_active_theme();
				$file_name = self::name_to_file($name);
				$path = Cms_Theme::get_theme_path($theme->code).'/partials/'.$file_name.'.php';
			}

			if (file_exists($path))
				return file_get_contents($path);

			if (isset($partial->content))
				return $partial->content;

			throw new Phpr_ApplicationException('Partial file not found: '.$path);
		}
		catch (exception $ex)
		{
			throw new Phpr_ApplicationException('Error rendering CMS partial '.$name.'. '.$ex->getMessage());
		}
	}

	public static function get_by_name($name)
	{
		$theme = Cms_Theme::get_active_theme();
		if (self::$cache == null)
		{
			self::$cache = array();

			$partials = Db_Helper::object_array("select * from cms_partials where theme_id=:theme_id", array('theme_id'=>$theme->code));

			foreach ($partials as $partial) {
				self::$cache[$partial->name] = $partial;
			}
		}

		if (array_key_exists($name, self::$cache))
			return self::$cache[$name];

		// Same as Cms_Base::get_file_path but static
		$file_name = self::name_to_file($name);
		$path = Cms_Theme::get_theme_path($theme->code).'/partials/'.$file_name.'.php';

		if (!file_exists($path))
			throw new Phpr_ApplicationException('Could not find partial: '.$name);

		$obj = self::create_from_file($path);
		if ($obj)
			return self::$cache[$name] = $obj;

		return null;
	}

	//
	// File based methods
	//

	protected static function create_from_file($file_path)
	{
		$obj = self::create();
		$obj->file_name = File::get_name($file_path);
		$obj->load_settings();
		$obj->ignore_file_copy = true;
		$obj->content = file_get_contents($file_path);
		$obj->name = self::file_to_name($obj->file_name);
		$obj->save();

		return $obj;
	}

	public static function auto_create_from_files()
	{

		$dir = Cms_Theme::get_theme_path(false) . '/partials';

		if (File_Directory::exists($dir))
		{
			$edit_theme = Cms_Theme::get_edit_theme()->code;
			$existing_files = Db_Helper::scalar_array("select file_name from cms_partials where theme_id = '".$edit_theme."'");

			$files = scandir($dir);
			foreach ($files as $file)
			{
				if (!self::is_valid_file_name($file))
					continue;

				$file_name = File::get_name($file);

				if (!in_array($file_name, $existing_files))
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

	protected static function file_to_name($file_name)
	{
		$file_name = pathinfo($file_name, PATHINFO_FILENAME);

		$result = str_replace(';', ':', $file_name);
		$result = preg_replace('/[^a-z_0-9:]/', '_', $result);

		return strtolower($result);
	}

	protected static function name_to_file($name)
	{
		return strtolower(str_replace(':', ';', $name));
	}

	//
	// Custom columns
	// 

	public function eval_is_module_theme() 
	{
		return ($this->module_id && !$this->theme_id);
	}

	// 
	// Module theme
	// 

	public static function refresh_module_theme_files()
	{
		// Cache exisiting files
		$all_exisiting_files = Db_Helper::object_array("select module_id, file_name from cms_partials where theme_id is null and module_id is not null");
		$files_cache = array();
		foreach ($all_exisiting_files as $file_obj)
		{
			$module_id = $file_obj->module_id;
			if (!isset($files_cache[$module_id]))
				$files_cache[$module_id] = array();

			$files_cache[$module_id][] = $file_obj->file_name;
		}

		// Cycle modules
		$all_modules = Phpr_Module_Manager::get_modules();
		foreach ($all_modules as $module_id => $module) 
		{
			$existing_files = isset($files_cache[$module_id]) ? $files_cache[$module_id] : null;
			self::refresh_module_theme_file($module_id, $module->dir_path, $existing_files);
		}
	}

	public static function refresh_module_theme_file($module_id, $module_path = null, $existing_files = null)
	{
		if (!$module_path)
			$module_path = Phpr_Module_Manager::get_module_path($module_id);

		if (!$existing_files)
			$existing_files = Db_Helper::scalar_array("select file_name from cms_partials where module_id='".$module_id."'");

		$path = $module_path . '/theme/partials';
		
		// Halt and/or clean up
		if (!File_Directory::exists($path)) {
			if (count($existing_files))
				Db_Helper::query('delete from cms_partials where theme_id is null and module_id=?', $module_id);

			return;
		}

		// Locate files in the file system
		$files = scandir($path);
		$found_files = array();
		$found_file_paths = array();
		foreach ($files as $file)
		{
			if (!self::is_valid_file_name($file))
				continue;

			$file_name = File::get_name($file);
			$found_files[] = $file_name;
			$found_file_paths[$file_name] = $path . '/' . $file;
		}

		// Determine actions
		$files_to_create = array_diff($found_files, $existing_files);
		$files_to_delete = array_diff($existing_files, $found_files);
		$files_to_update = array_diff($found_files, $files_to_create, $files_to_delete);

		// Create action
		foreach ($files_to_create as $file_name) 
		{
			$file_path = $found_file_paths[$file_name];

			$obj = self::create();
			$obj->module_id = $module_id;
			$obj->is_module_theme = true;
			$obj->file_name = $file_name;
			$obj->load_settings();
			$obj->ignore_file_copy = true;
			$obj->content = file_get_contents($file_path);
			$obj->name = self::file_to_name($obj->file_name);
			$obj->save();
		}

		// Delete action
		foreach ($files_to_delete as $file_name) 
		{
			$bind = array('file_name' => $file_name, 'module_id' => $module_id);
			Db_Helper::query('delete from cms_partials where file_name=:file_name and module_id=:module_id', $bind);
		}

		// Update action
		if (count($files_to_update)) 
		{
			$partials_to_update = self::create()->where('file_name in (?)', array($files_to_update))->where('module_id=?', $module_id)->find_all();
			foreach ($partials_to_update as $partial) 
			{
				$partial->load_settings();
				$partial->load_file_content();
				$partial->ignore_file_copy = true;
				$partial->save();
			}
		}
	}

	public function convert_to_edit_theme()
	{
		if (!$this->is_module_theme)
			return $this;

		$obj = $this->duplicate();
		$obj->is_module_theme = false;
		$obj->module_id = null;
		$obj->theme_id = Cms_Theme::get_edit_theme()->code;
		$obj->save();
		return $obj;
	}

}
