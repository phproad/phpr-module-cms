<?php

class Cms_Controller extends Cms_Parser
{
	protected static $self;
	public $params;
	public $page;
	public $page_content;
	public $template;
	public $template_content;
	public $partial;
	public $yield_content = array();

	public $data = array();
	public $ajax_mode = false;
	public static $ajax_handlers_loaded = false;

	protected $admin_auth = false;

	public $user = null;

	public static function create()
	{
		return self::$self = new self();
	}

	public static function get_instance()
	{
		return self::$self;
	}

	public function __construct()
	{
		if (Phpr_Module_Manager::module_exists('user'))
			$this->user = Phpr::$frontend_security->authorize_user();
	}

	public function request_param($index, $default = null)
	{
		if ($index < 0) {
			$length = count($this->params);
			$index = $length+$index;
		}

		if (array_key_exists($index, $this->params))
			return $this->params[$index];

		return $default;
	}

	public function open($page, &$params)
	{
		$this->page = $page;
		$this->params = $params;
		$this->template = $page->template;

		Cms_Statistics::log_visit($page, Phpr::$request->get_current_uri());

		$this->process_special_requests();
		$this->enforce_security($params);
		$this->eval_content();

		$this->template_content = ($this->template) ? $this->template->get_content() : $this->page_content;

		ob_start();

		if ($this->template)
			$this->parse('?>'.$this->template_content, 'CMS template', $this->template->name);
		else
			echo $this->template_content;

		$this->page_content = ob_get_clean();

		Phpr::$events->fire_event('cms:on_before_display_page', $page);

		echo $this->display_page();
				
		Phpr::$events->fire_event('cms:on_after_display_page', $page);
	}

	public function action()
	{
		if ($this->page->get_code('pre'))
			eval($this->page->get_code('pre'));
		
		if ($this->page->action_code != Cms_Page::action_custom)
			Cms_Action_Manager::exec_action($this->page->action_code, $this);

		if ($this->page->get_code('post'))
			eval($this->page->get_code('post'));
	}

	public function handle_ajax_request($page, $handler_name, $update_elements, &$params)
	{
		$this->page = $page;
		$this->enforce_security($params);

		try
		{
			$this->page = $page;
			$this->params = $params;
			$this->data['cms_fatal_error_message'] = null;
			$this->data['cms_error_message'] = null;
			$this->ajax_mode = true;

			$handler_name_parts = explode(':', $handler_name);
			if (count($handler_name_parts) == 1)
			{
				if ($handler_name == 'on_action')
				{
					$this->action();
				}
				else
				{
					try
					{
						if (!self::$ajax_handlers_loaded)
						{
							self::$ajax_handlers_loaded = true;
							$this->parse($this->page->get_code('ajax'), 'Page AJAX handlers', $this->page->name ? $this->page->name : $this->page->title);
						}
					}
					catch (Exception $ex)
					{
						$this->parse_handler_exception('Error executing page AJAX handlers code: ', $ex);
					}

					if (!function_exists($handler_name))
						throw new Phpr_ApplicationException('AJAX handler not found: ' . $handler_name);

					call_user_func($handler_name, $this, $this->page, $this->params);
				}
			}
			else
			{
				Cms_Action_Manager::exec_ajax_handler($handler_name, $this);
			}

			ob_start();
			foreach ($update_elements as $element => $partial)
			{
				if (!$element)
					continue;

				echo '>>#'.$element.'<<';
				$this->display_partial($partial);
			}
			ob_end_flush();

		}
		catch (Exception $ex)
		{
			Phpr::$response->ajax_report_exception($ex, true, true);
		}
	}

	protected function eval_content()
	{
		ob_start();

		$this->parse($this->page->get_code('pre'), 'Error executing CMS page Pre Load Code (Advanced tab)', $this->page->name);

		$action_success = true;
		if (strlen($this->page->action_code) && $this->page->action_code != Cms_Page::action_custom)
		{
			try
			{
				Cms_Action_Manager::exec_action($this->page->action_code, $this);
			}
			catch (Phpr_ValidationException $ex)
			{
				$action_success = false;
				Phpr::$session->flash['error'] = $ex->getMessage();
			}
			catch (Phpr_ApplicationException $ex)
			{
				$action_success = false;

				Phpr::$session->flash['error'] = $ex->getMessage();
			}
			catch (Cms_Exception $ex)
			{
				$action_success = false;
				Phpr::$session->flash['error'] = $ex->getMessage();
			}

		}
		
		if ($action_success)
		{
			$this->parse_handler($this->page->get_code('post'), 'Error executing CMS page Post Load Code (Advanced tab)', $this->page->name);
		}


		$this->parse('?>'.$this->page->get_content(), 'CMS page', $this->page->name);
		$this->page_content = ob_get_clean();
	}

	protected function display_page()
	{
		return $this->page_content;
	}

	public function display_head()
	{
		$head_extras = array();
		$head_extras[] = "<script>";
		$head_extras[] = "<!--";
		$head_extras[] = "root_dir = '".Phpr::$request->get_subdirectory()."';";
		$head_extras[] = "asset_dir = '".Cms_Theme::get_theme_dir(true,false)."/assets/';";
		$head_extras[] = "// -->";
		$head_extras[] = "</script>";
		echo implode(PHP_EOL, $head_extras);
		
		$this->parse('?>'.$this->page->get_code('head'), 'Error executing CMS page head extras', $this->page->title_name);
	}

	/**
	 * Outputs a partial
	 */
	public function display_partial($name, $params = array(), $options = array('return_output' => false))
	{
		$result = null;

		$return_output = array_key_exists('return_output', $options) && $options['return_output'];
		if ($return_output)
			ob_start();

		$partial = Cms_Partial::create()->get_by_name($name);

		if ($partial)
		{
			$this->partial = $partial;
			$result = $this->parse('?>'.Cms_Partial::get_content($partial), 'CMS partial', $partial->name, $params);
			$this->partial = null;
		}
		else if ($this->call_stack)
			throw new Cms_ExecutionException("Partial " . $name . " not found", $this->call_stack, null, true);
		else
			throw new Phpr_ApplicationException("Partial " . $name . " not found");

		if ($return_output)
		{
			$result = ob_get_contents();
			ob_end_clean();
		}

		return $result;
	}

	public function get_content_for($key)
	{
		if(isset($this->yield_content[$key]))
			return $this->yield_content[$key];
	}

	public function start_content_for($key)
	{
		ob_start();
	}

	public function end_content_for($key)
	{
		$this->yield_content[$key] = ob_get_contents();
		ob_end_clean();
	}

	protected function enforce_security($params)
	{
		$security_redirect = (
			($this->page->security_id == Cms_Security_Group::guests && $this->user) ||
			($this->page->security_id == Cms_Security_Group::users && !$this->user)
		);

		if (!$security_redirect)
			return;

		if ($redirect_page = $this->page->security_redirect)
		{
			$old_url = urlencode(str_replace("/", "|", Phpr::$request->get_current_uri()));
			$redirect = root_url($redirect_page->url, true).'/'.$old_url;
		}
		else
		{
			$redirect_page = Cms_Page::get_url('/404', $params);
			if ($page)
				header("HTTP/1.0 404 Not Found");

			$redirect = root_url($redirect_page->url, true);
		}

		Phpr::$response->redirect($redirect);
	}

	/**
	 * Front end admin work
	 */
	protected function process_special_requests()
	{
		$special_queries = array(
			'context_edit'
		);

		$special_query_found = false;
		foreach ($_REQUEST as $key=>$value)
		{
			if (in_array($key, $special_queries))
			{
				$this->_special_query_flags[] = $key;
				$special_query_found = true;
			}
		}

		if ($special_query_found)
			$this->http_admin_authorize();
	}

	protected function http_admin_authorize()
	{
		if (!isset($_SERVER['PHP_AUTH_USER']))
			$this->send_http_auth_headers();

		$user = new Admin_User();
		$user = $user->find_user($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);

		if (!$user)
			$this->send_http_auth_headers();

		$this->admin_auth = true;
	}

	protected function send_http_auth_headers()
	{
		header('WWW-Authenticate: Basic realm="Website management"');
		header('HTTP/1.0 401 Unauthorized');

		die("You are not authorized to access this page.");
	}

	/**
	 * Copies execution context from another controller instance
	 */
	public function copy_context_from($controller)
	{
		$this->page = $controller->page;
		$this->params = $controller->params;
		$this->data = $controller->data;
		$this->ajax_mode = $controller->ajax_mode;
	}

	protected function resource_combine($type, $files, $options, $show_tag = true)
	{
		$aliases = array(
			'jquery'            => '/modules/cms/assets/scripts/js/jquery.js',
			'jquery-noconflict' => '/modules/cms/assets/scripts/js/jquery.noconflict.js',
			'jquery-helper'     => '/modules/cms/assets/scripts/js/jquery.helper.js',
			'jquery-validate'   => '/framework/assets/scripts/js/jquery.validate.js',
			
			// PHPR Libs
			'phpr'           => '/framework/assets/scripts/js/phpr.js',
			'phpr-post'      => '/framework/assets/scripts/js/phpr.post.js',
			'phpr-request'   => '/framework/assets/scripts/js/phpr.request.js',
			'phpr-indicator' => '/framework/assets/scripts/js/phpr.indicator.js',
			'phpr-form'      => '/framework/assets/scripts/js/phpr.form.js',
			'phpr-validate'  => '/framework/assets/scripts/js/phpr.validate.js',

			// Combined core
			'phpr-core' => array('phpr', 'phpr-post', 'phpr-request', 'phpr-indicator', 'phpr-form', 'jquery-validate', 'phpr-validate'),

			// @deprecated
			'jquery_noconflict' => '/modules/cms/assets/scripts/js/jquery.noconflict.js',
			'core_jquery'       => '/modules/cms/assets/scripts/js/jquery.helper.js',
			'frontend_core'     => '/modules/cms/assets/scripts/js/cms.core.js',
		);

		$files = Phpr_Util::splat($files);

		$files_array = array();
		foreach ($files as $file)
		{
			$file = trim($file);

			if (isset($aliases[$file])) {

				// Grouped aliases
				if (is_array($aliases[$file])) {
					foreach ($aliases[$file] as $subalias) {
						$files_array[] = 'file%5B%5D='.urlencode(trim($aliases[$subalias]));
					}
					continue;
				}
				else
					$file = $aliases[$file];
				
			}

			if (substr($file, 0, 1) == '@')
			{
				$file = substr($file, 1);
				if (strpos($file, '/') !== 0)
					$file = '/'.$file;

				$file = '/'.Cms_Theme::get_theme_dir(true,false).'/'.$file;
			}

			$files_array[] = 'file%5B%5D='.urlencode(trim($file));
		}

		if (!is_array($options))
		{
			if (Phpr::$config->get('DEV_MODE'))
				$options = array('src_mode'=>true, 'skip_cache'=>true);
			else
				$options = array();
		}

		$options_str = array();
		foreach ($options as $option=>$value)
		{
			if ($value)
				$options_str[] = $option.'=1';
		}

		$options_str = implode('&amp;', $options_str);
		if ($options_str)
			$options_str = '&amp;'.$options_str;

		if ($type == 'javascript') {
			$url = root_url('javascript_combine/?'.implode('&amp;', $files_array).$options_str);

			return $show_tag ? '<script type="text/javascript" src="'.$url.'"></script>'."\n" : $url;
		}
		else {
			$url = root_url('css_combine/?'.implode('&amp;', $files_array).$options_str);

			return $show_tag ? '<link rel="stylesheet" type="text/css" href="'.$url.'" />' : $url;
		}
	}

	public function js_include($files, $options = null, $show_tag = true)
	{
		return $this->resource_combine('javascript', $files, $options, $show_tag);
	}

	public function css_include($files, $options = null, $show_tag = true)
	{
		return $this->resource_combine('css', $files, $options, $show_tag);
	}

	/**
	 * Exceptions
	 */

	public function display_exception($controller, $exception)
	{
		$controller->layout = null;

		$controller->set_views_path('modules/cms/error_pages');
		if (Phpr::$config->get('SHOW_FRIENDLY_ERRORS'))
		{
			if (!Phpr::$request->is_remote_event())
			{
				if (Phpr::$config->get('DISPLAY_ERROR_LOG_ID') || Phpr::$config->get('DISPLAY_ERROR_LOG_STRING'))
				{
					$controller->view_data['error'] = Phpr_Error_Log::get_exception_details($exception);
					$controller->load_view('exception_friendly');
				}
				else
				{
					$controller->load_view('error', false, true);
				}
			}
			else
			{
				try
				{
					$new_exception = new Phpr_ApplicationException('A general error occurred');
					Phpr::$response->ajax_report_exception($new_exception, true);
				}
				catch (exception $ex)
				{
					die('A general error occurred');
				}
			}
		}
		else
		{
			$handlers = ob_list_handlers();
			foreach ($handlers as $handler)
			{
				if (strpos($handler, 'zlib') === false)
					ob_end_clean();
			}

			if (!Phpr::$request->is_remote_event())
			{
				$controller->view_data['error'] = Phpr_Error_Log::get_exception_details($exception);
				$controller->load_view('exception', false, true);
			}
			else
			{
				Phpr::$response->ajax_report_exception($exception, true);
			}
		}
	}

	public function exec_action($name)
	{
		Cms_Action_Manager::exec_action($name, $this);
	}
	
	public function exec_ajax_handler($name)
	{
		return Cms_Action_Manager::exec_ajax_handler($name, $this);
	}

}