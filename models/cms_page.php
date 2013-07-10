<?php

class Cms_Page extends Cms_Base
{
	const action_custom = 'Custom';

	public $implement = 'Db_AutoFootprints, Db_Act_As_Tree';

	public $act_as_tree_parent_key = 'parent_id';
	public $act_as_tree_sql_filter = null;

	public $cms_folder_name = "pages";
	public $cms_fields_to_save = array('name', 'url', 'title', 'description', 'keywords', 'sort_order', 'published', 'action_code', 'security_id', 'unique_id');
	public $cms_relations_to_save = array(
		'template'=>array('foreign_key' =>'template_id', 'linked_key' => 'unique_id'),
		'parent'=>array('foreign_key' =>'parent_id', 'linked_key' => 'unique_id'),
		'security_redirect'=>array('foreign_key' =>'security_page_id', 'linked_key' => 'unique_id')
	);

	public $ignore_file_copy = false;
	public $url = null;
	public $published = 1;
	protected static $cache_by_action_code = null;
	protected static $cache_by_url = null;
	protected static $cache_by_id = null;
	protected static $cache_by_name = null;
	protected $content_blocks = null;

	public $belongs_to = array(
		'template' => array('class_name'=>'Cms_Template', 'foreign_key'=>'template_id'),
		'security_mode' => array('class_name'=>'Cms_Security_Group', 'foreign_key'=>'security_id'),
		'security_redirect' => array('class_name'=>'Cms_Page', 'foreign_key'=>'security_page_id'),
		'parent' => array('class_name'=>'Cms_Page', 'foreign_key'=>'parent_id')
	);

	public $calculated_columns = array(
		'title_name' => array('sql'=>"IF(cms_pages.title is null,cms_pages.name,cms_pages.title)", 'type'=>db_varchar),
	);

	public $custom_columns = array(
		'page_code' => db_varchar,
		'template_code' => db_varchar,
		'is_module_theme' => db_bool,
	);

	public function define_columns($context = null)
	{
		$this->define_column('unique_id', 'Unique ID')->invisible();
		$this->define_column('name', 'Name')->order('asc')->validation()->fn('trim')->required('Please specify the page name.');
		$this->define_column('title', 'Title');
		$this->define_column('published', 'Published');
		$this->define_column('sitemap_visible', 'Show on site map')->default_invisible()->list_title('Sitemap Visible');
		$this->define_column('url', 'Page URL')->validation()->fn('trim')->fn('mb_strtolower')->
				required('Please provide the page URL.')->unique('Url "%s" already in use.', array($this, 'config_unique_validator'))->
				regexp(',^[/a-z0-9_\.-]*$,i', "Page URL can only contain letters, numbers, underscore (_), dash (-), forward slash (/) and dot (.)")->
				regexp(',^/,i', "The first character in the URL must be the forward slash")->method('validate_url');

		$this->define_column('file_name', 'File Name')->default_invisible()->validation()->fn('trim')->required('Please specify a file name.')
			->regexp('/^[a-z_0-9-;]*$/i', 'File name can only contain letters, numbers, underscore (_), dash (-), forward slash (/), dot (.) and semi-colon (;)')
			->fn('strtolower')->unique('File name (%s) is already in use', array($this, 'config_unique_validator'));

		$this->define_column('content', 'Content')->invisible()->validation()->required();

		$this->define_column('description', 'Description')->default_invisible()->validation()->fn('trim');
		$this->define_column('keywords', 'Keywords')->default_invisible()->validation()->fn('trim');
		$this->define_column('head', 'Head Extras')->default_invisible()->validation()->fn('trim');

		$this->define_relation_column('parent', 'parent', 'Parent Page', db_varchar, 'if(@name is not null and length(@name) > 0, @name, @title)')->default_invisible()->list_title('Menu Parent');
		$this->define_relation_column('template', 'template', 'Page Template', db_varchar, '@name')->validation();
		$this->define_relation_column('security_mode', 'security_mode', 'Security', db_varchar, '@name')->default_invisible();
		$this->define_relation_column('security_redirect', 'security_redirect', 'Redirect', db_varchar, '@name')->default_invisible()->validation()->method('validate_redirect');

		$this->define_column('action_code', 'Page Action')->default_invisible();
		$this->define_column('code_pre', 'Pre Load Code')->invisible();
		$this->define_column('code_post', 'Post Load Code')->invisible();
		$this->define_column('code_ajax', 'Ajax Events')->invisible();

		$content_blocks = $this->get_content_blocks();
		foreach ($content_blocks as $block)
		{
			$this->define_custom_column('content_block_'.$block->code, 'Content Block: '.$block->name, db_text);
		}

		// Extensibility
		$this->defined_column_list = array();
		Phpr::$events->fire_event('cms:on_extend_page_model', $this, $context);
		$this->api_added_columns = array_keys($this->defined_column_list);        
	}

	public function define_form_fields($context = null)
	{

		$user = Phpr::$security->get_user();
		$can_edit_pages = $user->get_permission('cms', 'manage_pages');

		$this->add_form_field('published', 'left')->tab('Page')->collapsible();
		$this->add_form_field('name','left')->tab('Page')->collapsible();
		$this->add_form_field('url', 'right')->tab('Page')->collapsible();
		$this->add_form_field('template', 'left')->tab('Page')->empty_option('<please select a template>')->collapsible();
		$this->add_form_field('file_name', 'right')->tab('Page')->collapsible();

		foreach ($this->content_blocks as $block)
		{
			$field_name = 'content_block_'.$block->code;
			$content_field = $this->add_form_field($field_name);

			if ($block->type == 'html')
			{
				$content_field->display_as(frm_html);
				$content_field->html_plugins .= ',save,fullscreen,inlinepopups';
				$content_field->html_buttons1 = 'save,separator,'.$content_field->html_buttons1.',separator,fullscreen';
				$content_field->save_callback('save_code');
				$content_field->html_full_width = true;
			}

			if ($can_edit_pages)
				$content_field->tab('Content');
			else
				$content_field->tab('Page');

			$content_field->no_label();
		}

		if ($can_edit_pages)
			$this->add_form_field('content')->tab('Page')->size('giant')->css_classes('code')->language('php')->display_as(frm_code_editor)->save_callback('save_code');

		$this->add_form_field('title')->tab('Meta');
		$this->add_form_field('description')->tab('Meta')->size('small');
		$this->add_form_field('keywords')->tab('Meta')->size('small');
		$this->add_form_field('head')->tab('Meta')->size('large')->css_classes('code')->comment('Extra HTML code to be included in the HEAD section of the page', 'above', true)->display_as(frm_code_editor)->language('php')->save_callback('save_code');

		$this->add_form_field('parent')->tab('Menu')->empty_option('<none>')->options_html_encode(false)->comment('Select a parent page for this page. The parent page information will be used for the navigation menus generating only', 'above');
		$this->add_form_field('sitemap_visible', 'left')->tab('Menu')->comment('Display this page in the public XML sitemap');
		
		if (Phpr_Module_Manager::module_exists('user'))
		{
			$this->add_form_field('security_mode', 'left')->reference_description_field('@description')->comment('Select access level for this page', 'above')->tab('Access')->display_as(frm_radio);
			$this->add_form_field('security_redirect', 'right')->reference_sort('title')->comment('Select a page to redirect from this page in case if a visitor has no rights to access this page', 'above')->empty_option('<select>')->tab('Access');
		}

		if ($can_edit_pages)
		{
			$this->add_form_field('action_code')->tab('Advanced')->display_as(frm_dropdown);
			$this->add_form_field('code_pre')->tab('Advanced')->size('large')->css_classes('code')->comment('PHP code to execute <strong>before</strong> the page function loads', 'above', true)->display_as(frm_code_editor)->language('php')->save_callback('save_code');
			$this->add_form_field('code_post')->tab('Advanced')->size('large')->css_classes('code')->comment('PHP code to execute <strong>after</strong> the page function loads', 'above', true)->display_as(frm_code_editor)->language('php')->save_callback('save_code');
			$this->add_form_field('code_ajax')->tab('Advanced')->size('large')->css_classes('code')->comment('Define Ajax event handlers accessible to this page only', 'above')->display_as(frm_code_editor)->language('php')->save_callback('save_code');
		}

		// Extensibility
		Phpr::$events->fire_event('cms:on_extend_page_form', $this, $context);
		foreach ($this->api_added_columns as $column_name)
		{
			$form_field = $this->find_form_field($column_name);
			if ($form_field)
				$form_field->options_method('get_added_field_options');
		}

		// Disable all fields
		if ($this->is_module_theme) {
			$fields = $this->get_form_fields();
			foreach ($fields as $field) {
				$field->disabled();
			}
		}		
	}

	//
	// Extensibility
	// 
	
	public function get_added_field_options($db_name, $current_key_value = -1)
	{
		$result = Phpr::$events->fire_event('cms:on_get_page_field_options', $db_name, $current_key_value);
		foreach ($result as $options)
		{
			if (is_array($options) || (strlen($options && $current_key_value != -1)))
				return $options;
		}
		
		return false;
	}

	//
	// Events
	//

	public function before_save($session_key = null)
	{
		if (Phpr::$config->get('DEMO_MODE') && !$this->ignore_file_copy)
			throw new Phpr_ApplicationException('Sorry you cannot modify pages while site is in demonstration mode.');

		if (isset($this->fetched['file_name']) && $this->fetched['file_name'] != $this->file_name)
		{
			$new_dir_path = $this->get_directory_path($this->file_name);
			if (file_exists($new_dir_path) && is_dir($new_dir_path))
			{
				throw new Phpr_ApplicationException('A directory with the name '.$this->file_name.' already exists.');
			}

			if (!@rename($this->get_directory_path($this->fetched['file_name']), $new_dir_path))
				throw new Phpr_ApplicationException('Error renaming the page directory.');
		}
	}

	public function before_delete($session_key = null)
	{
		if (Phpr::$config->get('DEMO_MODE') && !$this->ignore_file_copy)
			throw new Phpr_ApplicationException('Sorry you cannot modify pages while site is in demonstration mode.');

		$in_use = Db_Helper::scalar('select count(*) from cms_pages where security_page_id=:id', array('id'=>$this->id));

		if ($in_use)
			throw new Phpr_ApplicationException("Unable to delete the page: it is used as a security redirect page for another page.");

		$in_use = Db_Helper::scalar('select count(*) from cms_pages where parent_id=:id', array('id'=>$this->id));

		if ($in_use)
			throw new Phpr_ApplicationException("Unable to delete the page because it has child pages.");
	}

	public function after_save()
	{
		$this->update_content_blocks();
		$this->copy_to_file();
		$this->save_settings();
	}

	public function before_create($session_key = null)
	{
		if (!strlen($this->unique_id))
			$this->unique_id = uniqid("", true);

		if (!strlen($this->theme_id))
			$this->theme_id = Cms_Theme::get_edit_theme()->code;
	}

	public function after_delete()
	{
		if (Cms_Theme::theme_path_is_writable($this->theme_id) && $this->file_name)
			$this->delete_page_dir();
	}

	//
	// Custom columns
	// 

	public function eval_page_code()
	{
		return str_replace('_', '-', $this->file_name).'-page';
	}

	public function eval_template_code()
	{
		if (!$this->template)
			return '';

		return str_replace('_', '-', $this->template->file_name).'-template';
	}

	public function eval_is_module_theme() 
	{
		return ($this->module_id && !$this->theme_id);
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

	public function apply_edit_and_module_theme()
	{
		// Filter by edit theme and module theme
		$theme_code = Cms_Theme::get_edit_theme()->code;
		$this->where('cms_pages.theme_id=? or cms_pages.theme_id is null', $theme_code);

		// Join all pages with their pairs (edit theme / module theme)
		$this->join('cms_pages as cms_module_pages', 'cms_module_pages.url = cms_pages.url and cms_module_pages.id != cms_pages.id');

		// If a join is found, return the record joining to the module theme, i.e the edit theme
		$this->where('cms_module_pages.theme_id is null');

		return $this;
	}

	//
	// Getters
	//

	public function get_content()
	{
		try
		{
			$this->load_file_content();
		}
		catch (Exception $ex)
		{
			// Do nothing
		}

		return $this->content;
	}

	public function get_code($type)
	{
		if ($type!=="pre"&&$type!="post"&&$type!="ajax"&&$type!="head")
			return;

		try
		{
			$this->load_file_content();
		}
		catch (Exception $ex)
		{
			// Do nothing
		}

		return ($type=="head") ? $this->head : $this->{'code_'.$type};
	}

	public function get_content_block($code)
	{
		$content = '';

		$block = Cms_Content_Block::get_by_page_and_code($this->id, $code);

		if($block)
			$content = $block->get_content();

		return $content;
	}

	public static function get_object_list($default = -1)
	{
		if (self::$cache_by_id && !$default)
			return self::$cache_by_id;

		$theme = Cms_Theme::get_active_theme();
		$records = Db_Helper::object_array('select id,name,title,url,action_code from cms_pages where theme_id=:theme_id', array('theme_id'=>$theme->code));
		$result = array();
		foreach ($records as $page)
			$result[$page->id] = $page;

		if (!$default)
			return self::$cache_by_id = $result;
		else 
			return $result;
	}

	// Use $action_code as a filter. Eg: $action_code = 'payment:pay'
	public static function get_name_list($action_code = null)
	{
		if (self::$cache_by_name)
			return self::$cache_by_name;
		
		$pages = self::get_object_list();
		$result = array();
		foreach ($pages as $id=>$page)
		{
			if ($action_code && $page->action_code != $action_code)
				continue;
			
			$result[$id] = $page->name.' ['.$page->url.']';
		}
			
		return self::$cache_by_name = $result;
	}

	// Returns a page based on supplied url
	public static function get_url($url, &$params)
	{
		if (!self::$cache_by_url)
		{
			// Build a cache of page urls and ids
			self::$cache_by_url = array();

			$theme = Cms_Theme::get_active_theme();
			$all_pages = Db_Helper::object_array('select id,url from cms_pages where theme_id=:theme_id', array('theme_id'=>$theme->code));
			foreach ($all_pages as $page)
			{
				self::$cache_by_url[$page->url] = $page;
			}

			// Sort cache
			uasort(self::$cache_by_url, array('Cms_Page', 'sort_page_urls'));
		}

		// Parse request
		$segments_request = self::split_url($url);
		$segments_request_total = count($segments_request);

		foreach (self::$cache_by_url as $page)
		{
			// Parse page
			$segments_page = self::split_url($page->url);
			$segments_page_total = count($segments_page);

			// Filter pages with excess segments
			if ($segments_page_total > $segments_request_total)
				continue;

			// Find a match
			if (self::get_url_match($segments_request, $segments_page, $page->url, $params))
				return Cms_Page::create()->where('cms_pages.id = ?', $page->id)->find();

		}

		return null;
	}

	// Returns a page based on it's action
	public static function get_url_from_action($action_code)
	{
		if (!self::$cache_by_action_code)
		{
			$pages = self::get_object_list();
			$result = array();
			foreach ($pages as $page)
			{
				$result[$page->action_code] = $page->url;
			}
			self::$cache_by_action_code = $result;
		}

		if (array_key_exists($action_code, self::$cache_by_action_code))
			return self::$cache_by_action_code[$action_code];

		return false;
	}

	// Breaks open supplied url into an array
	//

	protected static function split_url($url)
	{
		$values = array();
		$parts = explode('/', $url);
		foreach ($parts as $part)
		{
			if (strlen($part))
			{
				$values[] = $part;
			}
		}
		return $values;
	}

	protected static function get_url_match($segments_request, $segments_page, $url, &$params)
	{
		$params = array();
		$segments_page_total = count($segments_page);
		$segments_request_total = count($segments_request);

		// Page has no segments, request does
		if (($segments_page_total == 0) && ($segments_request_total > 0))
			return false;
		while (count($segments_request) >= $segments_page_total)
		{
			// Find a perfect match
			$current_url = "/" . implode("/", $segments_request);
			if ($url == $current_url)
			{
				$params = array_reverse($params);
				return true;
			}

			// Buffer any unmatched segments as parameters
			$params[] = array_pop($segments_request);
		}
	}

	
	// Sorts urls from shortest to longest
	//

	protected static function sort_page_urls($a, $b)
	{
		$length_a = strlen($a->url);
		$length_b = strlen($b->url);

		if ($length_a > $length_b)
			return -1;
		else if ($length_a < $length_b)
			return 1;
		else
			return 0;
	}


	//
	// Options
	//

	public function get_template_options($key_value = -1)
	{
		$templates = Cms_Template::create();
		$templates->order('name');
		$templates->where('theme_id=?', Cms_Theme::get_edit_theme()->code);
		return $templates->find_all()->as_array('name', 'id');
	}

	public function get_security_redirect_options()
	{
		$pages = self::create()->order('name');
		if ($this->id)
			$pages->where('id <> ?', $this->id);


		$theme = Cms_Theme::get_edit_theme();
		$pages->where('theme_id=?', $theme->code);
		$pages = $pages->find_all();

		$result = array();
		foreach ($pages as $page)
			$result[$page->id] = $page->name.' ['.$page->url.']';

		return $result;
	}

	private function list_parent_options($items, &$result, $level, $ignore, $maxLevel, $url_key = false)
	{
		if ($maxLevel !== null && $level > $maxLevel)
			return;

		foreach ($items as $item)
		{
			if ($ignore !== null && $item->id == $ignore)
				continue;

			$key = $url_key ? $item->url_title : $item->id;

			$result[$key] = str_repeat("&nbsp;", $level*3).h($item->name).' ['.h($item->url).']';
			$this->list_parent_options($item->list_children('cms_pages.sort_order'), $result, $level+1, $ignore, $maxLevel, $url_key);
		}
	}

	public function get_page_tree_options($key_value, $max_level = 100)
	{
		$obj = new self();
		$result = array();
		
		if ($key_value == -1) {
			
			$theme = Cms_Theme::get_edit_theme();
			if ($theme)
				$obj->act_as_tree_sql_filter = strtr("theme_id='{theme_code}'", array('{theme_code}'=>$theme->code));

			$this->list_parent_options($obj->list_root_children('cms_pages.sort_order'), $result, 0, $this->id, $max_level);
		}
		else {
			
			if ($key_value == null)
				return $result;

			$obj = Cms_Page::create();
			$obj = $obj->find($key_value);

			if ($obj)
				return h($obj->name);
		}
		return $result;
	}

	public function get_parent_options($key_value = -1, $max_level = 100)
	{
		return $this->get_page_tree_options($key_value, $max_level);
	}

	public function get_action_code_options($key_value = -1)
	{
		$result = array();
		$result['Custom'] = self::action_custom;

		$actions = Cms_Action_Manager::list_actions();
		foreach ($actions as $action)
		{
			$result[$action] = $action;
		}

		return $result;
	}

	//
	// Validation methods
	//

	public function after_validation($session_key = null)
	{
		$this->url = strtolower($this->url);
		if ($this->url != '/' && substr($this->url, -1) == '/')
		{
			$this->url = substr($this->url, 0, -1);
		}
	}

	public function validate_url($name, $value)
	{
		if (preg_match(',//,i', $value))
			$this->validation->set_error('Page URL cannot contain two forward slashes (//) in a row.', $name, true);

		return true;
	}

	public function validate_redirect($name, $value)
	{
		if ($this->security_mode && $this->security_mode->id != Cms_Security_Group::everyone && !$value && !$this->ignore_file_copy)
			$this->validation->set_error('Please select security redirect page.', $name, true);

		return true;
	}

	//
	// Content blocks
	//

	public function load_content_blocks()
	{
		if (!$this->content_blocks)
			$this->get_content_blocks();

		foreach ($this->content_blocks as $block)
		{
			$field_name = 'content_block_'.$block->code;
			$content = Cms_Content_Block::get_by_page_and_code($this->id, $block->code);
			$this->$field_name = ($content) ? $content->get_content() : "";
		}
	}

	protected function update_content_blocks()
	{
		// This is to step refresh_from_meta from trashing the blocks
		if ($this->ignore_file_copy)
			return;

		$found_blocks = array();
		foreach ($this->content_blocks as $content_block)
		{
			$found_blocks[$content_block->code] = $content_block->code;
			$block = Cms_Content_Block::create()->get_by_page_and_code($this->id, $content_block->code);
			if (!$block)
				$block = Cms_Content_Block::create();

			$block->init_columns();

			$block->page_id = $this->id;
			$block->name = $content_block->name;
			$block->type = $content_block->type;
			$code = $block->code = $block->file_name = $content_block->code;

			$data = post('Cms_Page');
			$field_name = 'content_block_'.$code;
			$block->content = (isset($data[$field_name])) ? $data[$field_name] : null;
			$block->save();
		}

		// Clean up unused blocks
		foreach (Cms_Content_Block::get_by_page($this->id) as $block)
		{
			if (!array_key_exists($block->code, $found_blocks))
				$block->delete();
		}
	}

	public function get_content_blocks($content = null)
	{
		if ($content === null)
			$content = $this->get_content();

		$result = array();
		$result = array_merge($result, $this->lookup_content_block('content_block', 'html', $content));
		$result = array_merge($result, $this->lookup_content_block('text_block', 'text', $content));

		return $this->content_blocks = $result;
	}

	private function lookup_content_block($function_name, $type, $content)
	{

		$matches = array();
		preg_match_all('/'.$function_name.'\s*\([\'"]([_a-z0-9]*)[\'"]\s*,\s*[\'"]([^)]*)[\'"\)]\)/i', $content, $matches);

		if (!$matches)
			return array();

		$result = array();
		foreach ($matches[0] as $index=>$block)
		{

			$text = $matches[0][$index];
			$code = $matches[1][$index];
			$name = $matches[2][$index];

			$submatch = array();
			preg_match_all('/'.$function_name.'\s*\([\'"]([_a-z0-9]*)[\'"]\s*,\s*[\'"]([^)]*)[\'"]\s*,\s*array\(.*[\'"]/i', $text, $submatch);

			if (isset($submatch[2][0]))
				$name = $submatch[2][0];

			$obj = array('code'=>$code, 'name'=>$name, 'type'=>$type);
			$result[] = (object)$obj;
		}

		return $result;
	}

	//
	// File based methods
	//

	public function get_directory_path($file_name)
	{
		if (!$file_name)
			return null;

		$file_name = pathinfo($file_name, PATHINFO_FILENAME);

		if ($this->is_module_theme)
			return Phpr_Module_Manager::get_module_path($this->module_id).'/theme/'.$this->cms_folder_name.'/'.$file_name;
		else
			return Cms_Theme::get_theme_path($this->theme_id).'/'.$this->cms_folder_name.'/'.$file_name;
	}

	protected function get_page_file_path($file_name)
	{
		return $this->get_directory_path($this->file_name).'/'.$file_name;
	}

	protected function get_page_file_name($path)
	{
		if (!file_exists($path))
			return false;

		$files = scandir($path);
		foreach ($files as $file)
		{
			if (substr($file, -4) != '.php')
				continue;

			if (substr($file, 0, 5) != 'page_')
				continue;

			return $file;
		}

		return 'page_'.$this->file_name.'.php';
	}

	protected function get_file_content($file_name)
	{
		if (!$file_name)
			return false;

		$path = $this->get_page_file_path($file_name);

		if (!file_exists($path))
			return false;

		$content = file_get_contents($path);
		return trim($content);
	}

	public function copy_to_file($templates_dir = null)
	{
		if ($this->ignore_file_copy)
		{
			if ($this->file_name)
				$this->save_file_name_to_db($this->table_name, $this->file_name);

			return;
		}

		try
		{
			$this->save_to_files($this->get_directory_path($this->file_name));
		}
		catch (exception $ex)
		{
			throw new Phpr_ApplicationException('Error saving page '.$this->name.' to file. '.$ex->getMessage());
		}
	}

	protected function save_to_files($dest_path)
	{
		if (file_exists($dest_path) && !is_writable($dest_path))
			throw new Phpr_ApplicationException('Directory is not writable: '.$dest_path);

		if (!file_exists($dest_path))
		{
			if (!@mkdir($dest_path))
				throw new Phpr_ApplicationException('Error creating page directory: '.$dest_path);

			$folder_permissions = File_Directory::get_permissions();
			@chmod($dest_path, $folder_permissions);
		}

		$this->save_to_file($this->content, $dest_path.'/'.$this->get_page_file_name($dest_path));
		$this->save_to_file($this->add_php_tags($this->code_pre), $dest_path.'/code_pre.php');
		$this->save_to_file($this->add_php_tags($this->code_post), $dest_path.'/code_post.php');
		$this->save_to_file($this->head, $dest_path.'/head_declarations.php');
		$this->save_to_file($this->add_php_tags($this->code_ajax), $dest_path.'/code_ajax.php');
	}

	public function load_file_content()
	{
		$path = $this->get_directory_path($this->file_name);
		if (!file_exists($path))
			return;

		$content = $this->get_file_content($this->get_page_file_name($path));
		if ($content !== false)
			$this->content = $content;

		$content = $this->get_file_content('code_pre.php');
		if ($content !== false)
			$this->code_pre = $this->remove_php_tags($content);

		$content = $this->get_file_content('code_post.php');
		if ($content !== false)
			$this->code_post = $this->remove_php_tags($content);

		$content = $this->get_file_content('code_ajax.php');
		if ($content !== false)
			$this->code_ajax = $this->remove_php_tags($content);

		$content = $this->get_file_content('head_declarations.php');
		if ($content !== false)
			$this->head = $content;
	}

	protected static function create_from_directory($dir_name)
	{
		$page = self::create();

		$page->init_columns();
		$page->file_name = $dir_name;
		$page->load_settings();
		$page->load_file_content();
		$page->ignore_file_copy = true;

		if (!$page->url)
			$page->url = '/'.$dir_name;

		$page->save();

		return $page;
	}

	public static function auto_create_from_files()
	{
		$objects = array();
		$dirs = self::list_orphan_directories();
		foreach ($dirs as $dir_name) {
			$objects[] = self::create_from_directory($dir_name);
		}

		foreach ($objects as $obj) {
			$obj->load_relation_settings()->save();
		}
	}

	public static function list_orphan_directories()
	{
		$path = Cms_Theme::get_theme_path(false) . '/pages';
		$result = array();

		$edit_theme = Cms_Theme::get_edit_theme()->code;
		$existing_files = Db_Helper::scalar_array("select file_name from cms_pages where theme_id='".$edit_theme."'");
		$files = scandir($path);

		foreach ($files as $file)
		{
			$file_path = $path.'/'.$file;
			if (!is_dir($file_path) || substr($file, 0, 1) == '.' || !preg_match('/^[a-z_0-9-]*$/', $file))
				continue;

			if (!in_array($file, $existing_files))
				$result[] = $file;
		}

		return $result;
	}

	public static function refresh_from_meta()
	{
		$all_pages = self::create()->apply_edit_theme()->find_all();

		foreach ($all_pages as $page) {
			$page->load_settings();
			$page->load_file_content();
			$page->ignore_file_copy = true;
			$page->save();
		}
	}

	protected function delete_page_dir()
	{
		if (!strlen($this->file_name))
			return;

		$path = $this->get_directory_path($this->file_name);

		if (!file_exists($path) || !is_dir($path))
			return;

		$files = scandir($path);
		foreach ($files as $file)
		{
			if (!is_dir($path.'/'.$file))
				@unlink($path.'/'.$file);
		}

		@rmdir($path);
	}

	protected function add_php_tags($string)
	{
		if (!strlen($string))
			$string = "\n\n";

		return "<?\n".$string."\n?>";
	}

	protected function remove_php_tags($string)
	{
		$string = preg_replace('/^\s*\<\?php\s*/', '', $string);
		$string = preg_replace('/^\s*\<\?\s*/', '', $string);
		$string = preg_replace('/\?\>\s*$/', '', $string);
		return $string;
	}

	// 
	// Module theme
	// 

	public function convert_to_theme_object()
	{
		$obj = $this->duplicate();
		$obj->is_module_theme = false;
		$obj->module_id = null;
		$obj->theme_id = Cms_Theme::get_edit_theme()->code;
		$obj->save();
		return $obj;
	}	
}