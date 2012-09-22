<?php

class Cms_String extends Db_ActiveRecord
{
	public $implement = 'Db_CsvModel';

	public static $language_strings = array();
	public static $default_strings = array();
	public $cms_folder_name = 'strings';
	public $csv_file_name = 'cms_string_export.csv';

    public $belongs_to = array(
        'page' => array('class_name'=>'Cms_Page', 'foreign_key'=>'page_id'),
    );

	public static function create()
	{
		return new self();
	}
	
	public function define_columns($context = null)
	{
		$this->define_column('global', 'Global')->defaultInvisible();
		$this->define_relation_column('page', 'page', 'Page', db_varchar, '@name')->validation();
		$this->define_column('code', 'Code')->defaultInvisible();
		$this->define_column('content', 'Content')->validation()->required('You must specify the template content.');
		$this->define_column('original', 'Default Content')->invisible();
	}
	
	public function define_form_fields($context = null)
	{
		$this->add_form_field('code');
		$this->add_form_field('content')->renderAs(frm_text);
		$this->add_form_field('original')->renderAs(frm_text)->disabled();
	}

	// Events
	//

	public function before_create($session_key = null)
	{
		if (!strlen($this->theme_id))
			$this->theme_id = Cms_Theme::get_edit_theme()->code;
	}

	// Getters
	//

	public static function get_string($default, $code, $page_id=null, $count=null)
	{
		$new_code = ($count) ? $code . "_" . $count : $code;
		$count = ($count) ? $count + 1 : 1;

		$string = self::get_string_content($new_code, $page_id);

		if (!$string)
			$string = self::create_string($new_code, $default, $page_id);

		if ($string->original != $default)
			return self::get_string($default, $code, $page_id, $count);

		return $string->content;
	}

	public static function create_string($code, $content, $page_id=null)
	{
		$language = self::create();
		if (!$page_id) $language->global = true;
		$language->code = $code;
		$language->page_id = $page_id;
		$string = $language->content = $language->original = $content;
		$language->save();

		// add to cache
		$id = ($page_id) ? $page_id : 0;
		self::$language_strings[$id][$code] = $language;

		return $language;
	}

	public static function get_string_content($code, $page_id=null)
	{

		$strings = self::get_string_group($page_id);
				
		if (array_key_exists($code, $strings))
		{
			$language = $strings[$code];			
			return $language;
		}

		return null;		
	}	

	public static function get_string_group($page_id=null)
	{
		if (!$page_id)
			$page_id = 0;

		if (!array_key_exists($page_id, self::$language_strings))
		{
			self::$language_strings[$page_id] = ($page_id==0) ? self::create()->where('page_id is null')->find_all()->as_array(null, 'code')
															  : self::create()->find_all_by_page_id($page_id)->as_array(null, 'code');
		}
		return self::$language_strings[$page_id];
	}

	public static function reset_string_cache($page_id=null)
	{
		if (!$page_id)
			$page_id = 0;

		self::$language_strings[$page_id] = array();
	}

}
