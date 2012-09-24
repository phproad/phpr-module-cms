<?php

class Cms_Menu_Item extends Db_ActiveRecord
{
	public $table_name = 'cms_menu_items';

	public $implement = 'Db_Sortable, Db_Act_As_Tree, Core_Config_Model';

	public $act_as_tree_parent_key = 'parent_id';
	public $act_as_tree_sql_filter = null;
	public $act_as_tree_name_field = 'label';
	protected $categories_column;

    protected $added_fields = array();
    protected $menu_type_obj = null;
    public $class_name = 'Cms_Page_Menu_Item';

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

		$this->define_column('element_id', 'ID');
		$this->define_column('element_class', 'Class');
		// if (Phpr_ModuleManager::module_exists('blog'))
		// {
		//	$this->define_custom_column('blog_category_page_id', 'Blog Category', db_number);
		// }
	}

	public function define_form_fields($context = null)
	{
        $this->get_menu_type_object()->build_config_form($this, $context);
        
		//$this->add_form_field('label', 'left')->tab('Links');
		//$this->add_form_field('url', 'full')->tab('Links');
		// These properties are underlying

		$this->add_form_field('title', 'full')->tab('Properties');
		$this->add_form_field('element_id', 'left')->tab('Properties');
		$this->add_form_field('element_class', 'right')->tab('Properties');


		// if (Phpr_ModuleManager::module_exists('blog'))
		// {
		// 	$this->add_form_field('blog_category_page_id')->renderAs(frm_checkboxlist)->tab('Blog');
		// }
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
				'parent_id' => ($this->parent_id ? $this->parent_id : NULL),
				'menu_id' => ($this->menu_id ? $this->menu_id : NULL),
				'child_ids' => array($child_ids)
			);

			Db_DbHelper::query('update cms_menu_items set parent_id=:parent_id where menu_id =:menu_id and id in (:child_ids)', $bind);
		}
	}


    public function before_validation($session_key=null)
    {
     	$this->get_menu_type_object()->build_menu_item($this);
    }

	// Getters
	//

    public function get_menu_type_object()
    {
        if ($this->menu_type_obj !== null)
            return $this->menu_type_obj;

        if (!Phpr::$class_loader->load($this->class_name))
            throw new Phpr_ApplicationException("Class {$this->class_name} not found.");

        $class_name = $this->class_name;

        return $this->menu_type_obj = new $class_name();
    }


    // Custom fields
    //

    public function add_field($code, $title, $side = 'full', $type = db_text, $tab = 'Event', $hidden = false)
    {
        $this->define_config_column($code, $title, $type)->validation();
        if (!$hidden)
            $form_field = $this->add_config_field($code, $side)->optionsMethod('get_added_field_options')->optionStateMethod('get_added_field_option_state')->tab($tab);
        else
            $form_field = null;

        $this->added_fields[$code] = $form_field;

        return $form_field;
    }    

    public function get_added_field_options($db_name)
    {     
        $obj = $this->get_menu_type_object();
        $method_name = "get_{$db_name}_options";
        if (method_exists($obj, $method_name))
            return $obj->$method_name($this);

        throw new Phpr_SystemException("Method {$method_name} is not defined in {$this->class_name} class.");
    }
    
    public function get_added_field_option_state($db_name, $key_value)
    {       
        $obj = $this->get_menu_type_object();
        $method_name = "get_{$db_name}_option_state";
        if (method_exists($obj, $method_name))
            return $obj->$method_name($key_value);
            
        throw new Phpr_SystemException("Method {$method_name} is not defined in {$this->class_name} class.");
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

	public function get_type_name()
	{
		$info = $this->get_menu_type_object()->get_info();
		return $info['name'];
	}

	// Relations
	//

	public function set_parent($parent_id)
	{
		Db_DbHelper::query('update cms_menu_items set parent_id=:parent_id where id=:id', array(
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

			Db_DbHelper::query("update cms_menu_items set sort_order=:sort_order, parent_id=$parent_id where id=:id", array(
				'sort_order'=>$index+1,
				'id'=>$id
			));
		}
	}

}
