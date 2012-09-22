<?php

class Cms_Menus extends Admin_Controller
{
	public $implement = 'Db_ListBehavior, Db_FormBehavior';
	public $list_model_class = 'Cms_Menu';
	public $list_record_url = null;

	public $form_preview_title = 'Menu';
	public $form_create_title = 'New Menu';
	public $form_edit_title = 'Edit Menu';
	public $form_model_class = 'Cms_Menu';
	public $form_not_found_message = 'Menu not found';
	public $form_redirect = null;
	public $form_edit_save_auto_timestamp = true;
	public $form_create_save_redirect = null;
	public $form_flash_id = 'form_flash';

	public $form_edit_save_flash = 'The menu has been successfully saved';
	public $form_create_save_flash = 'The menu has been successfully added';
	public $form_edit_delete_flash = 'The menu has been successfully deleted';

	public $list_search_enabled = true;
	public $list_columns = array();
	public $list_search_fields = array('name', 'description');
	public $list_search_prompt = 'find menus by name or description';
	public $list_custom_partial = '';
	public $list_custom_prepare_func = 'listCustomPrepare';
	public $list_custom_body_cells = '';
	public $list_custom_head_cells = '';
	public $list_items_per_page = 20;
	public $list_name = 'Cms_Menus_index_list';

	protected $required_permissions = array('cms:manage_menus');
	public $enable_concurrency_locking = true;

	protected $globalHandlers = array(
		'onLoadItemForm',
		'onUpdateItemList',
		'onEditItem',
		'onAddItem',
		'onDeleteItem',
		'onSetItemOrders',
		'onSave',
	);

	public function __construct()
	{
		Phpr::$events->fireEvent('cms:onConfigureMenusPage', $this);

		parent::__construct();
		$this->app_menu = 'cms';
		$this->app_module_name = 'Menus';

		$this->list_record_url = url('/cms/menus/edit/');
		$this->form_redirect = url('/cms/menus');
		$this->form_create_save_redirect = url('/cms/menus/edit/%s/'.uniqid());
		$this->app_page = 'menus';
	}


	//public function listPrepareData()
	//{
	//	$updated_data = Phpr::$events->fireEvent('cms:onPrepareListData', $this);
	//	foreach ($updated_data as $updated)
	//	{
	//		if ($updated)
	//			return $updated;
	//	}
	//
	//	$obj = Cms_Menu::create();
	//
	//	return $obj;
	//}
	public function listCustomPrepare($model, $options)
	{
		$updated_data = Phpr::$events->fireEvent('cms:onPrepareListCustomData', $model, $options);
		foreach ($updated_data as $updated)
		{
			if ($updated)
				return $updated;
		}

		return $model;
	}

	public function index()
	{
		$this->app_page_title = 'Menus';
	}

	protected function index_onDeleteSelected()
	{
		$items_processed = 0;
		$items_deleted = 0;

		$item_ids = post('list_ids', array());
		$this->viewData['list_checked_records'] = $item_ids;

		foreach ($item_ids as $item_id)
		{
			$item = null;
			try
			{
				$item = Cms_Menu::create()->find($item_id);
				if (!$item)
					throw new Phpr_ApplicationException('Menu with identifier '.$item_id.' not found.');

				$item->delete();
				$items_deleted++;
				$items_processed++;
			}
			catch (Exception $ex)
			{
				if (!$item)
					Phpr::$session->flash['error'] = $ex->getMessage();
				else
					Phpr::$session->flash['error'] = 'Error deleting menu "'.$item->name.'": '.$ex->getMessage();

				break;
			}
		}

		if ($items_processed)
		{
			$message = null;

			if ($items_deleted)
				$message = 'Menus deleted: '.$items_deleted;

			Phpr::$session->flash['success'] = $message;
		}

		$this->renderPartial('templates_page_content');
	}

	protected function index_onRefresh()
	{
		$this->renderPartial('templates_page_content');
	}

	protected function onSave($id)
	{
		Phpr::$router->action == 'create' ? $this->create_onSave() : $this->edit_onSave($id);
	}

	public function formAfterCreateSave($page, $session_key)
	{
		if (post('create_close'))
			$this->form_create_save_redirect = url('/cms/menus').'?'.uniqid();
	}

	public function listGetRowClass($model)
	{
		if (!($model instanceof Cms_Menu))
			return null;
	}

	/*
	 * Menu Items
	 */

	protected function onLoadItemForm()
	{
		try
		{
			$id = post('item_id');
			$item = $id ? Cms_Menu_Item::create()->find($id) : Cms_Menu_Item::create();
			if (!$item)
				throw new Phpr_ApplicationException('Menu item not found');

			if ( $item->new_record )
				$item->define_form_fields('add');
			else
			{
				$item->define_form_fields();
				$item->define_columns();
			}

			$this->viewData['item'] = $item;
			$this->viewData['session_key'] = post('edit_session_key');
			$this->viewData['item_id'] = post('item_id');
			$this->viewData['trackTab'] = false;
		}
		catch (Exception $ex)
		{
			throw new Phpr_ApplicationException($ex->getMessage());
			$this->handlePageError($ex);
		}

		if ( $item->new_record )
			$this->renderPartial('add_item_form');
		else
			$this->renderPartial('edit_item_form');
	}

	protected function onAddItem($parent_id = null)
	{
		try
		{
			
			$menu = $this->getModelObj($parent_id);
			$added_count = 0;

			// Add link
			$link_id = post_array('Cms_Menu_Item', 'link_id');
			$link_class = post_array('Cms_Menu_Item', 'link_class');
			$link_label = post_array('Cms_Menu_Item', 'label');
			$link_title = post_array('Cms_Menu_Item', 'title');
			$link_url = post_array('Cms_Menu_Item', 'url');

			if ($link_id || $link_class || $link_label || $link_title || $link_url)
			{
				$model = Cms_Menu_Item::create();
				$model->init_columns_info();
				$model->save(array(
					'label' => $link_label,
					'title' => $link_title,
					'url' => $link_url,
					'item_id' => $link_id,
					'item_class' => $link_class,
				), post('menu_session_key'));
				$menu->items->add($model, post('menu_session_key'));
				$added_count++;
			}

			if (!$added_count)
				throw new Phpr_ApplicationException("You must enter one or more links to add.");

			Phpr::$session->flash['success'] = sprintf("%d items added successfully.", $added_count);

			$this->renderPartial('item_list', array(
				'session_key'=>$this->formGetEditSessionKey(),
				'menu' => $menu,
			));
		}
		catch (Exception $ex)
		{
			Phpr::$response->ajaxReportException($ex, true, true);
		}
	}

	protected function onEditItem($parent_id = null)
	{
		try
		{
			$id = post('item_id');
			$item = $id ? Cms_Menu_Item::create()->find($id) : Cms_Menu_Item::create();
			if (!$item)
				throw new Phpr_ApplicationException('Menu item not found');

			$item->define_form_fields();
			$item->define_columns();
			$item->save(post('Cms_Menu_Item'), $this->formGetEditSessionKey());

			if (!$id)
			{
				$menu = $this->getModelObj($parent_id);
				$menu->items->add($item, post('menu_session_key'));
			}
		}
		catch (Exception $ex)
		{
			Phpr::$response->ajaxReportException($ex, true, true);
		}
	}

	protected function onUpdateItemList($parent_id = null)
	{
		try
		{
			$this->renderPartial('item_list', array(
				'session_key'=>$this->formGetEditSessionKey(),
				'menu' => $this->getModelObj($parent_id),
			));
		}
		catch (Exception $ex)
		{
			Phpr::$response->ajaxReportException($ex, true, true);
		}
	}

	protected function onDeleteItem($parent_id = null)
	{
		try
		{
			$menu = $this->getModelObj($parent_id);

			$id = post('item_id');
			$item = $id ? Cms_Menu_Item::create()->find($id) : Cms_Menu_Item::create();
			if ($item)
			{
				$menu->items->delete($item, $this->formGetEditSessionKey());
				$item->delete();
			}

			$this->renderPartial('item_list', array(
				'session_key'=>$this->formGetEditSessionKey(),
				'menu' => $menu,
			));
		}
		catch (Exception $ex)
		{
			Phpr::$response->ajaxReportException($ex, true, true);
		}
	}

	/*
	 * Set nesting and orders
	 */
	protected function onSetItemOrders($parent_id = null)
	{
		parse_str(post('nesting_order'), $parent_ids);
		$parent_ids = isset($parent_ids['item']) ? $parent_ids['item'] : array();

		//Nesting & Sorting
		Cms_Menu_Item::set_order_and_nesting( post('sort_order'), $parent_ids );
	}






	private function getModelObj($id)
	{
		return strlen($id) ? $this->formFindModelObject($id) : (new $this->form_model_class);
	}
}
