<?php

class Cms_Html
{
	public static function flash_message()
	{
		$result = null;

		foreach (Phpr::$session->flash as $type=>$message)
		{
			if ($type == 'system')
				continue;

			$result .= '<p class="flash '.$type.'">'.h($message).'</p>';
		}

		Phpr::$session->flash->now();

		return $result;
	}
	
	public static function theme_url($path = '/', $root_url = true, $public = false)
	{
		$url = $path;

		if (substr($url, 0, 1) != '/')
			$url = '/'.$url;

		$url = Cms_Theme::get_theme_dir(true,false).$url;

		if ($root_url)
			return root_url($url, $public);

		return $url;
	}

	public static function content_block($code, $name, $params=array(), $type='html')
	{
		$page = Cms_Controller::get_instance()->page;
		if (!$page)
			return;

		$content = $page->get_content_block($code);
		$content = self::content_block_parse_params($content, $params);
		return $content;
	}

	public static function global_content_block($code, $name, $params=array(), $type='html')
	{
		$content = Cms_Content_Block::get_global_content($code, $name, $type);
		$content = self::content_block_parse_params($content, $params);
		return $content;
	}

	public static function content_block_parse_params($content, $params = array())
	{
		$params['site_name'] = c('site_name');
		$params['root_url'] = root_url();
		$params['theme_url'] = theme_url();

		foreach ($params as $param=>$value)
			$content = str_replace("{".$param."}", $value, $content);

		return $content;
	}

	public static function locale_string($phrase, $params=null, $key=null)
	{
		$str = '';

		$global = (is_bool($key) && $key === true)||(is_bool($params) && $params === true);

		if ($key === null || $global)
			$key = Phpr_Inflector::slugify(str_replace('%s','x', $phrase), '_');

		$controller = Cms_Controller::get_instance();
		$page_id = ($global||!$controller->page) ? null : $controller->page->id;
		
		$phrase = Cms_String::get_string($phrase, $key, $page_id);

		if (!is_array($params))
			$params = array($params);

		$num_args = substr_count($phrase, '%s');

		for ($i=0;$i<$num_args;$i++)
		{
			if (!isset($params[$i]))
				$params[$i] = '???';
		}

		if ($num_args > 0)
			$str .= vsprintf($phrase, $params);
		else
			$str .= $phrase;

		//$str .= " ( ".$key." )" . $num_args;
		return $str;
	}
}