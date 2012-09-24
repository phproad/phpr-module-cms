<?php

class Cms_Menu extends Db_ActiveRecord
{
	public $table_name = 'cms_menus';

	public $implement = 'Db_AutoFootprints';
	public $auto_footprints_visible = true;

	public $has_many = array(
		'items'=>array('class_name'=>'Cms_Menu_Item', 'foreign_key'=>'menu_id', 'order'=>'sort_order, id', 'delete'=>true)
	);

	public $calculated_columns = array(
		'item_count'=>array('sql'=>"(select count(*) from cms_menu_items WHERE menu_id=cms_menus.id)", 'type'=>db_number),
	);

	public static function create()
	{
		return new self();
	}

	public function define_columns($context = null)
	{
		$this->define_column('name', 'Name')->order('asc')->validation()->fn('trim')->required("Please specify the menu name.")->unique();
		$this->define_column('short_description', 'Short Description')->validation()->fn('trim');

		$this->define_multi_relation_column('items', 'items', 'Items', '@title')->invisible();
		$this->define_column('item_count', 'Items');
	}

	public function define_form_fields($context = null)
	{
		$this->add_form_field('name', 'full')->tab('Details')->validation()->required();
		$this->add_form_field('short_description', 'full')->tab('Details');
		$this->add_form_field('items')->tab('Items')->renderAs('items')->comment('Drag and drop the menu items below to sort or nest them.', 'above')->noLabel();
	}

	public function list_root_items($session_key=null)
	{
		$all_items = $this->list_related_records_deferred('items', $session_key);
		$items = array();
		
		foreach ($all_items->objectArray as $item)
		{
			if ($item->parent_id == null)
				$items[] = $item;
		}

		return new Db_DataCollection($items);
	}

	public function render_frontend($options = array())
	{
		if ( is_array($options) && sizeof($options) )
			extract($options, EXTR_SKIP);

		$form_model = $this;
		require dirname(__FILE__)."/../partials/frontend/_menu.php";
	}
}
