<?php

/**
 * Usage:
 * 
 * <?=content_block('code','Name')?>
 */

class Cms_Content_Block extends Cms_Base
{
	public $table_name = "cms_content";

	public $implement = 'Db_AutoFootprints';
	public $auto_footprints_visible = true;

	public $type = 'html';

	public $cms_folder_name = "content";
	public $cms_fields_to_save = array('name', 'code', 'type');
    public $cms_relations_to_save = array(
        'page'=>array('foreign_key' =>'page_id', 'linked_key' => 'unique_id')
    );

	public $ignore_file_copy = false;
	public static $content_blocks = array();

    public $belongs_to = array(
        'page' => array('class_name'=>'Cms_Page', 'foreign_key'=>'page_id'),
    );

    public $calculated_columns = array(
    	'file_name'=>array('sql'=>'code')
    );
	
	public static function create()
	{
		return new self();
	}
	
	public function define_columns($context = null)
	{
		$this->define_relation_column('page', 'page', 'Page', db_varchar, '@name')->validation();
		$this->define_column('name', 'Name')->order('asc')->validation()->fn('trim')->required("You must specify a content block name.");
		$this->define_column('type', 'Render Type')->invisible();
		$this->define_column('code', 'Code')->validation()->fn('trim')->fn('strtolower')->required("Please specify the block code.")
		->regexp(',^[/a-z0-9_\.:-]*$,i', "File name can only contain letters, numbers, underscore (_), dash (-), forward slash (/) and dot (.)")
		->unique('Content block with code %s already exists.', array($this, 'config_unique_validator'));

		$this->define_column('file_name', 'File Name')->invisible();
		$this->define_column('is_global', 'Global Block');
		$this->define_column('content', 'Content')->invisible()->validation()->required('You must specify the template content.');
	}
	
	public function define_form_fields($context = null)
	{
		$this->add_form_field('name','left')->collapsible();
		$this->add_form_field('code','right')->collapsible();

		$content_field = $this->add_form_field('content');

		if ($this->type == 'html')
		{
			$content_field->display_as(frm_html)->size('huge');
			$content_field->html_plugins .= ',save,fullscreen,inlinepopups';
			$content_field->html_buttons1 = 'save,separator,'.$content_field->html_buttons1.',separator,fullscreen';
			$content_field->save_callback('save_code');
			$content_field->html_full_width = true;		
		}
		else
		{
			$content_field->display_as(frm_textarea)->size('huge');
		}
	}

	/**
	 * Events
	 */
    
    public function before_save($session_key = null)
    {
        if (Phpr::$config->get('DEMO_MODE') && !$this->ignore_file_copy)
            throw new Phpr_ApplicationException('Sorry you cannot modify content while site is in demonstration mode.');
	}

    public function before_delete($session_key = null)
    {
        if (Phpr::$config->get('DEMO_MODE') && !$this->ignore_file_copy)
            throw new Phpr_ApplicationException('Sorry you cannot modify content while site is in demonstration mode.');
	}
	
	public function before_create($session_key = null)
	{
		if (!strlen($this->theme_id))
			$this->theme_id = Cms_Theme::get_edit_theme()->code;

		$this->file_name = $this->code;
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
		if (isset($this->fetched['file_name']) && $this->file_name != $this->fetched['file_name'])
		{
			$this->delete_file($this->fetched['file_name']);
		}
	}

	/**
	 * Getters
	 */

	public static function get_by_page_and_code($id, $code)
	{
		$blocks = self::get_by_page($id);
			
		if (array_key_exists($code, $blocks))
			return $blocks[$code];

		return null;
	}

	public static function get_by_page($id)
	{
		if (!array_key_exists($id, self::$content_blocks))
			self::$content_blocks[$id] = self::create()->find_all_by_page_id($id)->as_array(null, 'code');

		return self::$content_blocks[$id];
	}

	public function get_content()
	{
		try
		{
			if (!$this->file_name)
				throw new Phpr_ApplicationException('Partial file is not specified');
				
			$path = $this->get_file_path($this->file_name);
				
			if (!file_exists($path))
				throw new Phpr_ApplicationException('Partial file not found: '.$path);
			
			return file_get_contents($path);
			
		} 
		catch (exception $ex)
		{
			throw new Phpr_ApplicationException('Error rendering CMS content '.$this->name.'. '.$ex->getMessage());
		}		
	}

	public static function get_global_content($code, $name, $type='html')
	{
		$block = self::create()->where('page_id is null')->find_by_code($code);

		if (!$block)
		{
			$block = self::create();
			$block->ignore_file_copy = true;
			$block->is_global = true;
			$block->code = $code;
			$block->name = $name;
			$block->type = $type;
			$block->save();
		}

		return $block->get_content();
	}

	/**
	 * File based methods
	 */

	protected static function create_from_file($file_path)
	{
		$obj = self::create();		
		$obj->file_name = pathinfo($file_path, PATHINFO_FILENAME);
		$obj->load_settings();
		$obj->ignore_file_copy = true;
		$obj->content = file_get_contents($file_path);

		if (!$obj->code)
			$obj->code = $obj->file_name;

		$obj->save();

		return $obj;
	}

	public function copy_to_file()
	{
		if ($this->ignore_file_copy)
			return;
				
		try
		{
			$this->save_to_file($this->content, $this->get_file_path($this->file_name));
		}
		catch (exception $ex)
		{
			throw new Phpr_ApplicationException('Error saving '.$this->name.' to file. '.$ex->getMessage());
		}
	}	

	public static function auto_create_from_files()
	{
		$dir = Cms_Theme::get_theme_dir(false) . '/content';

		if (file_exists($dir) && is_dir($dir))
		{

			$edit_theme = Cms_Theme::get_edit_theme()->code;
			$existing_content = Db_Helper::object_array("select code as file_name from cms_content where theme_id = '".$edit_theme."'");
			$existing_files = array();
			foreach ($existing_content as $content)
				$existing_files[] = $content->file_name.'.php';

			$objects = array();
			$files = scandir($dir);
			foreach ($files as $file)
			{
				if (!self::is_valid_file_name($file))
					continue;				

				if (!in_array($file, $existing_files))
				{
					try
					{
						$objects[] = self::create_from_file($dir.'/'.$file);
					}
					catch (exception $ex) 
					{
						// Do nothing
					}
				}
			}

	        foreach ($objects as $obj)
	            $obj->load_relation_settings()->save();

		}
	}	

}


