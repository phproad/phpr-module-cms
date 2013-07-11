<?php

class Cms_Theme extends Cms_Base
{
	public $table_name = 'cms_themes';
	public $enabled = 1;

	private static $theme_active = false;
	private static $theme_default = false;
	private static $theme_edit = false;
	private static $themes = array();

	public function define_columns($context = null)
	{
		$this->define_column('name', 'Name')->order('asc')->validation()->fn('trim')->required("Please specify the theme name.");
		$this->define_column('code', 'Code')->validation()->fn('trim')->fn('strtolower')->required("Please specify the theme code.")
			->regexp(',^[a-z0-9_\.-]*$,i', "Theme code should contain only latin characters, numbers and signs _, -, /, and .")
			->unique('Theme with code %s already exists.')
			->method('validate_code');

		$this->define_column('description', 'Description')->validation()->fn('trim');
		$this->define_column('author_name', 'Author')->validation()->fn('trim');
		$this->define_column('author_website', 'Author website')->validation()->fn('trim');
		$this->define_column('is_default', 'Default');
		$this->define_column('enabled', 'Enabled')->validation()->method('validate_enabled');
	}

	public function define_form_fields($context = null)
	{
		$this->add_form_field('enabled')->tab('Theme');
		$this->add_form_field('name', 'left')->tab('Theme');
		$this->add_form_field('code', 'right')->comment('Theme code defines the theme directory name')->tab('Theme');
		$this->add_form_field('description')->size('small')->tab('Theme');
		$this->add_form_field('author_name', 'left')->tab('Theme');
		$this->add_form_field('author_website', 'right')->tab('Theme');
	}

	//
	// Events
	//
	
	public function before_save($session_key = null)
	{
		if (Phpr::$config->get('DEMO_MODE'))
			throw new Phpr_ApplicationException('Sorry you cannot modify themes while site is in demonstration mode.');
	}

	public function before_delete($id = null)
	{
		if (Phpr::$config->get('DEMO_MODE'))
			throw new Phpr_ApplicationException('Sorry you cannot modify themes while site is in demonstration mode.');

		if ($this->is_default)
			throw new Phpr_ApplicationException('Theme '.$this->name.' is set as default. Set a different default theme and try again.');
	}

	public function after_create()
	{
		$required_dirs = array('/', '/pages', '/partials', '/templates', '/content', '/assets', '/meta');

		foreach ($required_dirs as $dir)
		{
			$check_dir = self::get_theme_path($this->code).$dir;
			if (!file_exists($check_dir))
				@mkdir($check_dir);
		}
	}

	public function before_create($session_key = null)
	{
		if (!is_writable(trim(self::get_theme_path(''))))
			throw new Phpr_ApplicationException('Cannot create theme: Directory is not writable ' . self::get_theme_path(''));
	}

	public function before_update($session_key = null)
	{
		if ($this->code != $this->fetched['code'])
		{
			if (!is_writable(trim(self::get_theme_path(''))))
				throw new Phpr_ApplicationException('Cannot update theme: Directory is not writable ' . self::get_theme_path(''));

			if (!is_writable(trim(self::get_theme_path($this->fetched['code']))))
				throw new Phpr_ApplicationException('Cannot update theme: Directory is not writable ' . self::get_theme_path($this->fetched['code']));

			if (file_exists(self::get_theme_path($this->code)))
				throw new Phpr_ApplicationException('Cannot rename theme: Directory '.$this->code.' already exists');
		}
	}

	public function after_update()
	{
		if ($this->code != $this->fetched['code'])
		{
			rename(self::get_theme_path($this->fetched['code']), self::get_theme_path($this->code));

			// Update strings, content blocks, pages, partials and layouts
			//
			$bind = array('code'=>$this->code, 'old_code'=>$this->fetched['code']);
			Db_Helper::query('update cms_strings set theme_id=:code where theme_id=:old_code', $bind);
			Db_Helper::query('update cms_content set theme_id=:code where theme_id=:old_code', $bind);
			Db_Helper::query('update cms_pages set theme_id=:code where theme_id=:old_code', $bind);
			Db_Helper::query('update cms_partials set theme_id=:code where theme_id=:old_code', $bind);
			Db_Helper::query('update cms_templates set theme_id=:code where theme_id=:old_code', $bind);
		}
	}

	public function after_delete()
	{
		if (strlen($this->code))
		{
			// Delete assets
			// 
			$theme_path = self::get_theme_path($this->code);
			if (file_exists($theme_path))
				File_Directory::delete_recursive($theme_path);

			// Delete strings, content blocks, pages, partials and layouts
			//
			$bind = array('code'=>$this->code);
			Db_Helper::query('delete from cms_strings where theme_id=:code', $bind);
			Db_Helper::query('delete from cms_content where theme_id=:code', $bind);
			Db_Helper::query('delete from cms_pages where theme_id=:code', $bind);
			Db_Helper::query('delete from cms_partials where theme_id=:code', $bind);
			Db_Helper::query('delete from cms_templates where theme_id=:code', $bind);
		}
	}

	//
	// Validation
	//

	public function validate_code($name, $value)
	{
		if (in_array($value, array('content', 'strings', 'pages', 'partials', 'templates', 'meta')))
			$this->validation->set_error('Theme code cannot be "content", "strings", "pages", "partials", "templates" or "meta".', $name, true);

		return true;
	}

	public function validate_enabled($name, $value)
	{
		if (!$value && $this->is_default)
			$this->validation->set_error('Default theme cannot be disabled.', $name, true);

		return $value;
	}

	//
	// Service methods
	// 

	public function make_default()
	{
		if (!$this->enabled)
			throw new Phpr_ApplicationException('Theme '.$this->name.' is disabled and cannot be set as default.');

		$bind = array('id' => $this->id);
		Db_Helper::query('update cms_themes set is_default=1 where id=:id', $bind);
		Db_Helper::query('update cms_themes set is_default=null where id!=:id', $bind);
	}

	public function enable_theme()
	{
		$this->enabled = true;
		Db_Helper::query('update cms_themes set enabled=1 where id=:id', array('id'=>$this->id));
	}

	public function disable_theme()
	{
		if ($this->is_default)
			throw new Phpr_ApplicationException('Theme "'.$this->name.'" is is_default and cannot be disabled.');

		$this->enabled = false;
		Db_Helper::query('update cms_themes set enabled=0 where id=:id', array('id'=>$this->id));
	}

	//
	// General
	//

	public static function auto_create_all_from_files()
	{
		Cms_Template::auto_create_from_files();
		Cms_Page::auto_create_from_files();
		Cms_Content_Block::auto_create_from_files();
		Cms_Partial::auto_create_from_files();
	}

	//
	// Getters
	//

	public static function get_theme_by_id($id)
	{
		if (!strlen($id))
			return null;

		if (array_key_exists($id, self::$themes))
			return self::$themes[$id];

		return self::$themes[$id] = self::create()->find($id);
	}

	public static function get_active_theme()
	{
		$theme = self::get_default_theme();

		if (!$theme) {
			$exception = new Phpr_ApplicationException('No theme found to use.');
			$exception->hint_message = 'Try logging in to the admin area to create a theme, or you may need to reinstall.';
			throw $exception;
		}

		return $theme;
	}

	public static function get_default_theme()
	{
		if (self::$theme_default === false)
			self::$theme_default = self::create()->where('is_default=1')->find();

		return self::$theme_default;
	}

	public static function get_edit_theme()
	{
		if (self::$theme_edit !== false)
			return self::$theme_edit;

		if ($theme_id = Phpr_User_Parameters::get('admin_edit_theme'))
		{
			$theme = self::get_theme_by_id($theme_id);
			if ($theme)
				return self::$theme_edit = $theme;
		}

		return self::$theme_edit = self::get_default_theme();
	}

	public static function set_edit_theme($id)
	{
		if (!strlen($id))
			throw new Phpr_ApplicationException('Please select a theme.');

		$theme = self::get_theme_by_id($id);
		if (!$theme)
			throw new Phpr_ApplicationException('Could not find that theme.');

		self::$theme_edit = $theme;

		Phpr_User_Parameters::set('admin_edit_theme', $id);
	}

	// Smart method to find theme name
	//   @param1 can be boolean/string
	//     bool - front end?
	//     string - define theme code
	public static function get_theme_path($param=true, $absolute=true)
	{
		if (is_string($param))
			$result = "/themes/".$param;
		else if (is_bool($param) && $param === false)
			$result = "/themes/".self::get_edit_theme()->code;
		else
			$result = "/themes/".self::get_active_theme()->code;

		return ($absolute) ? PATH_APP . $result : $result;
	}

	public static function theme_path_is_writable($param1=true)
	{
		return is_writable(self::get_theme_path($param1));
	}

	public function get_asset_path($absolute=true)
	{
		return self::get_theme_path($this->code, $absolute).'/assets';
	}

	//
	// Duplicate
	//

	public function init_copy($obj)
	{
		$obj->name = $this->name;
		$obj->code = $this->code;
		$obj->description = $this->description;
		$obj->author_name = $this->author_name;
		$obj->author_website = $this->author_website;
	}

	public function duplicate_theme($data)
	{
		$new_theme = self::create();
		$new_theme->init_columns();
		$new_theme->init_form_fields();
		$new_theme->save($data);

		File_Directory::copy(self::get_theme_path($this->code), self::get_theme_path($new_theme->code));

		return $new_theme;
	}

}
