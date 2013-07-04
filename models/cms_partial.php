<?php

class Cms_Partial extends Cms_Base
{
	public $implement = 'Db_AutoFootprints';
	public $auto_footprints_visible = true;

	public $cms_folder_name = "partials";
	public $cms_fields_to_save = array('name');

	public $ignore_file_copy = false;

	protected static $cache = null;

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
		if (!strlen($this->theme_id))
			$this->theme_id = Cms_Theme::get_edit_theme()->code;

		if (!strlen($this->file_name))
			$this->file_name = self::name_to_file($this->name);
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

	//
	// Getters
	//

	// This is designed to take a stdObject or itself
	public static function get_content($partial=null)
	{
		try
		{
			$name = $partial->name;

			if (!$name)
				throw new Phpr_ApplicationException('Partial file is not specified');

			// Same as get_file_path but static
			$theme = Cms_Theme::get_active_theme();
			$file_name = self::name_to_file($name);
			$path = Cms_Theme::get_theme_dir($theme->code).'/partials/'.$file_name.'.php';

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

		// Same as get_file_path but static
		$file_name = self::name_to_file($name);
		$path = Cms_Theme::get_theme_dir($theme->code).'/partials/'.$file_name.'.php';

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
		$obj->file_name = pathinfo($file_path, PATHINFO_FILENAME);
		$obj->load_settings();
		$obj->ignore_file_copy = true;
		$obj->content = file_get_contents($file_path);
		$obj->name = self::file_to_name($obj->file_name);
		$obj->save();

		return $obj;
	}

	public static function auto_create_from_files()
	{

		$dir = Cms_Theme::get_theme_dir(false) . '/partials';

		if (file_exists($dir) && is_dir($dir))
		{
			$edit_theme = Cms_Theme::get_edit_theme()->code;
			$existing_partials = Db_Helper::object_array("select file_name from cms_partials where theme_id = '".$edit_theme."'");
			$existing_files = array();
			foreach ($existing_partials as $partial)
				$existing_files[] = $partial->file_name.'.php';

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
}
