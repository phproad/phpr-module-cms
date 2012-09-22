<?php

class Cms_Menu_Item extends Db_ActiveRecord
{
	public $table_name = 'cms_menu_items';

	public $implement = 'Db_Sortable, Db_Act_As_Tree';

	public $act_as_tree_parent_key = 'menu_item_id';
	public $act_as_tree_sql_filter = null;
	public $act_as_tree_name_field = 'label';
	protected $categories_column;

	protected static $item_list = null;

	public static function create($values = null)
	{
		return new self($values);
	}

	public function define_columns($context = null)
	{
		$this->define_column('label', 'Navigation Label')->validation()->required()->fn('trim');
		$this->define_column('title', 'Title Attribute');
		$this->define_column('url', 'URL')->invisible()->validation()->fn('trim');
		$this->define_column('item_id', 'ID');
		$this->define_column('item_class', 'Class');

		if (Phpr_ModuleManager::module_exists('blog'))
		{
            $this->define_custom_column('blog_category_page_id', 'Blog Category', db_number);
		}
	}

	public function define_form_fields($context = null)
	{
		$this->add_form_section(null, 'Add Link')->tab('Links')->sortOrder(1);
		$this->add_form_field('label', 'left')->tab('Links');
		$this->add_form_field('title', 'right')->tab('Links');
		$this->add_form_field('url', 'full')->tab('Links');
		$this->add_form_field('item_id', 'left')->tab('Links');
		$this->add_form_field('item_class', 'right')->tab('Links');

		if (Phpr_ModuleManager::module_exists('blog'))
		{
			$this->add_form_field('blog_category_page_id')->renderAs(frm_checkboxlist)->tab('Blog');
		}
	}

	public static function list_menu_items()
	{
		if (self::$item_list !== null)
			return self::$item_list;

		self::$item_list = array();
		$modules = Core_Module_Manager::find_modules();

		foreach ($modules as $module_id=>$module)
		{
			$actions_path = $module->dir_path."/classes/".$module_id."_menu_item.php";

			if (!file_exists($actions_path))
				continue;

			$class_name = $module_id."_Menu_Item";
			if (Phpr::$class_loader->load($class_name))
			{
				self::load_scope_actions($module_id, $class_name);
			}
		}

		sort(self::$item_list);
		return self::$item_list;
	}

	// Options
	//

	public function get_blog_category_page_id_options($key_value= -1)
	{
		$categories = Blog_Category::create()->find_all()->as_array('name');
		return $categories;
	}

	public function get_blog_category_page_id_option_state($key_value= -1)
	{
		return false;
		// return is_array($this->agent_list) && in_array($value, $this->agent_list);
	}

	// Events
	//

	public function before_delete($id = null)
	{
		// Re-nest any children before deletion
		$children = $this->list_children('sort_order, label');
		if ($children->count)
		{
			$child_ids = array();
			foreach ($children as $child)
			{
				$child_ids[] = $child->id;
			}

			$bind = array(
				'menu_item_id' => ($this->menu_item_id ? $this->menu_item_id : NULL),
				'menu_id' => ($this->menu_id ? $this->menu_id : NULL),
				'child_ids' => array($child_ids)
			);

			Db_DbHelper::query('update cms_menu_items set menu_item_id=:menu_item_id where menu_id :menu_id and id in (:child_ids)', $bind);
		}
	}

	// Service methods
	//

	public function render_frontend($options = array())
	{
		// Default rendering
		$form_model = $this;
		require dirname(__FILE__).'/../partials/frontend/_link.php';
	}

	public function get_url($recache=false)
	{
		if (!$recache && $this->url)
			return $this->url;

		return $this->url;
	}

	public function get_master_type_name()
	{
		return 'Link';
	}

	// Relations
	//

	public function set_parent($parent_id)
	{
		Db_DbHelper::query('update cms_menu_items set menu_item_id=:parent_id where id=:id', array(
			'parent_id'=> intval($parent_id) ? intval($parent_id) : NULL,
			'id'=>$this->id
		));
	}

	public static function set_order_and_nesting($order_ids, $parent_ids)
	{
		if (is_string($order_ids))
			$order_ids = explode(',', $order_ids);
		if (is_string($parent_ids))
		{
			$parent_ids = explode(',', $parent_ids);
		}

		foreach ($order_ids as $index=>$id)
		{
			//For some reason 'NULL' doesn't work with arguments, so do it manually (with sanitation)
			$parent_id = isset($parent_ids[$id]) && intval($parent_ids[$id]) ? intval($parent_ids[$id]) : 'NULL';

			Db_DbHelper::query("update cms_menu_items set sort_order=:sort_order, menu_item_id=$parent_id where id=:id", array(
				'sort_order'=>$index+1,
				'id'=>$id
			));
		}
	}

}
