<?php

class Cms_Action_Manager
{
	protected static $action_list = null;

	public static function list_actions()
	{
		if (self::$action_list !== null)
			return self::$action_list;

		self::$action_list = array();
		$modules = Core_Module_Manager::get_modules();

		foreach ($modules as $module_id=>$module)
		{
			$actions_path = $module->dir_path."/classes/".$module_id."_actions.php";

			if (!file_exists($actions_path))
				continue;

			$class_name = $module_id."_Actions";
			if (Phpr::$class_loader->load($class_name))
			{
				self::load_scope_actions($module_id, $class_name);
			}
		}

		sort(self::$action_list);
		return self::$action_list;
	}

	protected static function load_scope_actions($module_id, $class_name)
	{
		$class_info = new ReflectionClass($class_name);
		$methods = $class_info->getMethods();

		foreach ($methods as $method)
		{
			$method_name = $method->getName();
			$declaring_class = $method->getDeclaringClass();
			$is_hidden = substr($method_name, 0, 1) == '_';
			$is_event_handler = preg_match('/^on_/', $method_name);

			if ($method->isPublic() && $declaring_class->name != 'Cms_Controller' && $declaring_class->name != 'Cms_Parser' && !$is_hidden && !$is_event_handler)
				self::$action_list[] = $module_id.':'.$method_name;
		}
	}

	public static function exec_action($name, $controller)
	{
		$parts = explode(':', $name);

		if (count($parts) != 2)
			throw new Phpr_ApplicationException("Invalid action identifier: " . $name);

		$class_name = ucfirst($parts[0]).'_Actions';

		if (!Phpr::$class_loader->load($class_name))
			throw new Phpr_ApplicationException("Actions scope class is not found: " . $class_name);

		$obj = new $class_name(false);
		$obj->copy_context_from($controller);
		$method = $parts[1];

		try
		{
			$obj->$method();
			$controller->copy_context_from($obj);
		}
		catch (Exception $ex)
		{
			$controller->copy_context_from($obj);
			throw $ex;
		}
	}

	public static function exec_ajax_handler($name, $controller)
	{
		$parts = explode(':', $name);

		if (count($parts) != 2)
			throw new Phpr_ApplicationException("Invalid event handler identifier: " . $name);

		$class_name = ucfirst($parts[0]).'_Actions';

		if (!Phpr::$class_loader->load($class_name))
			throw new Phpr_ApplicationException("Actions scope class is not found: " . $class_name);

		$method = $parts[1];
		$is_event_handler = preg_match('/^on_/', $method);

		if (!$is_event_handler)
			throw new Phpr_ApplicationException("Specified method is not Ajax event handler: " . $method);

		$obj = new $class_name();

		if (!method_exists($obj, $method))
			throw new Phpr_ApplicationException("Ajax event handler not found: " . $name);

		$obj->copy_context_from($controller);

		try
		{
			$result = $obj->$method();
			$controller->copy_context_from($obj);
			return $result;
		}
		catch (Exception $ex)
		{
			$controller->copy_context_from($obj);
			throw $ex;
		}
	}
}

