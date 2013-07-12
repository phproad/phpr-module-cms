<?php

/*
 * Base class for CMS objects
 */

class Cms_Base extends Db_ActiveRecord
{
	
	public $cms_fields_to_save = array();
	public $cms_relations_to_save = array();

	public $cms_folder_name = null;

	/**
	 * Returns the working path, for example, the theme directory (/themes/default) 
	 * or module theme directory (/modules/cms/theme).
	 */
	public function get_working_root_path()
	{
		if ($this->is_module_theme)
			return Phpr_Module_Manager::get_module_path($this->module_id).'/theme';
		else
			return Cms_Theme::get_theme_path($this->theme_id);
	}

	public function get_file_path($file_name, $ext = 'php')
	{
		if (!$file_name)
			return null;
			
		$file_name = pathinfo($file_name, PATHINFO_FILENAME);
		return $this->get_working_root_path().'/'.$this->cms_folder_name.'/'.$file_name.'.'.$ext;
	}	

	public function config_unique_validator($checker, $obj, $session_key)
	{
		if  (strlen($obj->theme_id))
			$checker->where('theme_id=?', $obj->theme_id);
		else
			$checker->where('theme_id=?', Cms_Theme::get_edit_theme()->code);
	}

	public function load_file_content()
	{
		$path = $this->get_file_path($this->file_name);
		if ($path && file_exists($path))
		{
			$this->content = file_get_contents($path);
		}
	}

	public function copy_to_file()
	{
		if ($this->ignore_file_copy)
		{
			if ($this->file_name)
			{
				$this->save_file_name_to_db($this->table_name, $this->file_name);
			}
			return;
		}
				
		try
		{
			$this->save_to_file($this->content, $this->get_file_path($this->file_name));
		}
		catch (exception $ex)
		{
			throw new Phpr_ApplicationException('Error saving '.$this->name.' to file. '.$ex->getMessage());
		}
	}	

	protected function save_to_file($data, $path)
	{
		$file_exists = file_exists($path);

		if ($file_exists && !is_writable($path))
			throw new Phpr_ApplicationException('File is not writable: ' . $path);

		if (strlen($data) || $file_exists)
		{
			if (@file_put_contents($path, $data) === false)
				throw new Phpr_ApplicationException('Error writing file ' . $path);
		}
		else
		{
			if (!@touch($path))
				throw new Phpr_ApplicationException('Error creating file ' . $path);
		}

		try
		{
			@chmod($path, File::get_permissions());
		}
		catch (exception $ex)
		{
			// Do nothing
		}
	}

	protected function delete_file($file_name)
	{
		$path = $this->get_file_path($file_name);
		if (file_exists($path))
			@unlink($path);

		$this->delete_settings($file_name);
	}	

	protected static function is_valid_file_name($file_name)
	{
		if (substr($file_name, 0, 1) == '.')
			return false;

		$info = pathinfo($file_name);
		if (!preg_match('/^[a-z_0-9-;]*$/i', $info['filename']))
			return false;

		if (!isset($info['extension']) || mb_strtolower($info['extension']) != 'php')
			return false;

		return true;
	}

	protected function save_file_name_to_db($table, $file_name)
	{
		$file_name = pathinfo($file_name, PATHINFO_FILENAME);
		Db_Helper::query('update '.$table.' set file_name=:file_name where id=:id', array('file_name'=>$file_name, 'id'=>$this->id));
	}

	/**
	 * Record model object information in data.xml
	 */

	public function get_settings_path($file_name)
	{
		if (!$file_name)
			return null;
			
		$file_name = pathinfo($file_name, PATHINFO_FILENAME);
		return $this->get_working_root_path().'/meta/'.$this->cms_folder_name.'/'.$file_name.'.xml';
	}

	protected function save_settings()
	{
		if ($this->ignore_file_copy)
			return;

		$xml_obj = $this->get_settings_xml();

		if ($node = $this->find_setting($xml_obj))
		{
			$node->file_name = $this->file_name;   	
			foreach ($this->cms_fields_to_save as $field)
			{
				$value = ($this->$field) ? htmlspecialchars($this->$field) : "null";
				$node->$field = $value;
			}
			foreach ($this->cms_relations_to_save as $relation=>$link)
			{
				$value = "relation_".$relation;

				if (!$this->$relation)
				{
					unset($node->$value);
					continue;
				}
				
				$link = $link['linked_key'];
				$node->$value = $this->$relation->$link;
			}
		}
		else
		{
			$node = $xml_obj->addChild('object');
			//$node->addChild('id', $this->id);
			$node->addChild('class', get_class($this));
			$node->addChild('file_name', $this->file_name);

			foreach ($this->cms_fields_to_save as $field)
			{
				$value = ($this->$field) ? htmlspecialchars($this->$field) : "null";
				$node->addChild($field, $value);
			}
			foreach ($this->cms_relations_to_save as $relation=>$link)
			{
				$value = "relation_".$relation;
				
				if (!$this->$relation)
					continue;

				$link = $link['linked_key'];
				$node->addChild($value, $this->$relation->$link);
			}
		}
	 
		$this->save_settings_xml($xml_obj);
	}

	protected function get_settings_xml()
	{
		$path = $this->get_settings_path($this->file_name);
		$file_exists = file_exists($path);

		if ($file_exists && !is_writable($path))
			throw new Phpr_ApplicationException('File is not writable: ' . $path);

		if ($file_exists) 
			$data = file_get_contents($path);
		else
			$data = '<data></data>';

		return new SimpleXMLElement($data);
	}

	protected function save_settings_xml($xml_obj)
	{
		$path = $this->get_settings_path($this->file_name);
		$data = Phpr_Xml::beautify_xml($xml_obj);

		if (!is_writable($this->get_working_root_path()))
			throw new Phpr_ApplicationException('Directory is not writable: ' . $this->get_working_root_path());
 
		if (!is_writable(dirname(dirname($path))))
			throw new Phpr_ApplicationException('Directory is not writable: ' . dirname(dirname($path)));

		if (!file_exists(dirname($path)))
			@mkdir(dirname($path));

		if (!is_writable(dirname($path)))
			throw new Phpr_ApplicationException('File is not writable: ' . dirname($path));

		if (@file_put_contents($path, $data) === false)
			throw new Phpr_ApplicationException('Error writing file ' . $path);
	}

	protected function find_setting(&$xml_obj, $match_field='file_name')
	{
		foreach ($xml_obj->children() as $child)
		{
			if ($child->class == get_class($this) && $child->$match_field == $this->$match_field)
			{
				return $child;
			}
		}
		return null;
	}

	protected function delete_settings($file_name)
	{
		$path = $this->get_settings_path($file_name);
		if (file_exists($path))
			@unlink($path);
	}

	protected function load_settings()
	{
		if (!$this->file_name)
			return;

		$xml_obj = $this->get_settings_xml();
		$child = $this->find_setting($xml_obj);

		foreach ($this->cms_fields_to_save as $field)
		{
			if (!isset($child->$field))
				continue;

			$value = ($child->$field=="null") ? null : html_entity_decode($child->$field);
			$this->$field = $value;
		}

		if (!$this->name)
			$this->name = $this->file_name;

		if (!$this->theme_id && !$this->is_module_theme)
			$this->theme_id = Cms_Theme::get_edit_theme()->code;
	}

	protected function load_relation_settings()
	{
		if (!$this->file_name)
			return $this;

		$xml_obj = $this->get_settings_xml();
		$child = $this->find_setting($xml_obj);

		foreach ($this->cms_relations_to_save as $relation=>$link)
		{
			$field = "relation_".$relation;
			if (!isset($child->$field))
				continue;
			
			$this->{$link['foreign_key']} = $this->load_relation_setting($relation, $link['linked_key'], $child->$field);
		}
		return $this;
	}

	protected function load_relation_setting($relation, $linked_key, $value)
	{
		if (!isset($this->belongs_to[$relation]))
			return;

		$edit_theme = Cms_Theme::get_edit_theme()->code;
		$class_name = $this->belongs_to[$relation]['class_name'];
		$obj = new $class_name();
		$obj = $obj->where($linked_key.'=?',$value)->where('theme_id=?', $edit_theme)->find();

		if (!$obj)
			return;
		return $obj->id;
	}
}