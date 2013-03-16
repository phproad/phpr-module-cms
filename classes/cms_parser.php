<?php
/**
 * Parser class will execute PHP within a string block
 */
class Cms_Parser {
		
	protected $call_stack = array();

	/**
	 * Parse function
	 * Important: Make sure you merge $this->data with $params
	 */	
	public function parse($code, $call_stack_object_type, $call_stack_object_name, $params = array(), $eval_as_handler = false)
	{
		$parse_result = null;
		
		try
		{
			$call_stack_obj = new Cms_CallStackItem($call_stack_object_name, $call_stack_object_type, $code);
			array_push($this->call_stack, $call_stack_obj);
			
			extract($params);
			extract($this->data);

			ob_start();
			$display_errors = ini_set('display_errors', 1);

			$parse_result = eval($code);

			ini_set('display_errors', $display_errors);

			$result = ob_get_clean();
			$matches = array();

			$error_types = array('Warning', 'Parse error', 'Fatal error');
			$error = false;

			foreach ($error_types as $type)
			{
				if ($error = preg_match(',^\<br\s*/\>\s*\<b\>'.$type.'\</b\>:(.*),m', $result, $matches))
					break;
			}

			if ($error)
			{
				$error_msg = $matches[1];
				$error_msg_text = null;
				$error_line = null;
				$pos = strpos($error_msg, 'in <b>');

				if ($pos !== false)
				{
					$found_line = preg_match(',on\s*line\s*\<b\>([0-9]*)\</b\>,', $error_msg, $matches);
					$error_msg_text = substr($error_msg, 0, $pos);
					if ($found_line)
						$error_line = $matches[1];

					throw new Cms_ExecutionException($error_msg_text, $this->call_stack, $error_line);
				} else
					throw new Cms_ExecutionException($error_msg, $this->call_stack, null);
			}

			array_pop($this->call_stack);
			
			echo $result;
		}
		catch (Exception $ex)
		{
			$forward_exception_classes = array(
				'Cms_ExecutionException',
				'Phpr_ValidationException',
				'Phpr_ApplicationException',
				'Cms_Exception'
			);

			if (in_array(get_class($ex), $forward_exception_classes))
				throw $ex;

			if ($this->call_stack && strpos($ex->getFile(), "eval()") !== false)
				throw new Cms_ExecutionException($ex->getMessage(), $this->call_stack, $ex->getLine());

			throw $ex;
		}

		return $parse_result;
	}

	public function parse_handler($code, $call_stack_object_type, $call_stack_object_name, $params = array())
	{
		try
		{
			return $this->parse($code, $call_stack_object_type, $call_stack_object_name, $params, true);
		}
		catch (Phpr_ValidationException $ex)
		{
			Phpr::$session->flash['error'] = $ex->getMessage();
		}
		catch (Phpr_ApplicationException $ex)
		{		
			Phpr::$session->flash['error'] = $ex->getMessage();
		}
		catch (Cms_Exception $ex)
		{
			Phpr::$session->flash['error'] = $ex->getMessage();
		}
	}

	protected function parse_handler_exception($message, $ex)
	{
		$exception_text = $message . Phpr_String::finalize($ex->getMessage());

		if ($ex instanceof Phpr_PhpException)
			$exception_text .= ' Line ' . $ex->getLine() . '.';

		throw new Exception($exception_text);
	}
}

class Cms_ExecutionException extends Phpr_SystemException
{
	public $call_stack;
	public $code_line;
	public $location_desc;
	
	public function __construct($message, $call_stack, $line, $load_line_from_stack = false)
	{
		$call_stack = array_reverse($call_stack);
		$this->call_stack = $call_stack;
		$this->code_line = $line;
		$this->location_desc = '"'.$this->document_name().'" ('.$this->document_type().')';
		
		if ($load_line_from_stack)
		{
			$trace = $this->getTrace();
			$count = count($trace);
			if ($count)
			{
				$this->code_line = $trace[0]['line'];
			}
		}
		
		parent::__construct($message);
	}
	
	public function stack_top()
	{
		return $this->call_stack[0];
	}
	
	public function document_type()
	{
		return $this->stack_top()->type;
	}

	public function document_name()
	{
		return $this->stack_top()->name;
	}

	public function document_code()
	{
		return $this->stack_top()->code;
	}
}

class Cms_CallStackItem
{
	public $name;
	public $type;
	public $code;
	
	public function __construct($name, $type, $code)
	{
		$this->name = $name;
		$this->type = $type;
		$this->code = $code;
		
		if (mb_substr($this->code, 0, 2) == '?>')
			$this->code = mb_substr($this->code, 2);
	}
}

class Cms_Exception extends Phpr_ApplicationException
{

// Do nothing

}