<?php

class Cms_Security extends Phpr_Security
{
	public $user_class_name = "User";
	protected static $user_cache = array();
	
	public $cookie_name = "Website";
	protected $cookie_lifetime_name = 'CMS_AUTH_COOKIE_LIFETIME';
	protected $cookie_updated = false;

	public function login(Phpr_Validation $validation = null, $redirect = null, $login = null, $password = null, $default_field = 'login')
	{
		return parent::login($validation, $redirect, $login, $password, $default_field);
	}

	public function get_user()
	{
		if ($this->user !== null)
			return $this->user;

		$cookie_name = Phpr::$config->get('CMS_AUTH_COOKIE_NAME', $this->cookie_name);
		$ticket = Phpr::$request->cookie($cookie_name);

		$session_param = Phpr::$config->get('CMS_SESSION_PARAM_NAME', 'cms_ticket_parameter');

		if ($ticket === null)
		{
			$ticket = $this->restore_ticket(Phpr::$request->get_field($session_param));
		}
		else
		{
			$this->remove_ticket(Phpr::$request->get_field($session_param));
		}

		if (!$ticket)
			return null;

		$ticket = $this->validate_ticket($ticket);
		if ($ticket === null)
			return null;

		$user_id = trim(base64_decode($ticket['user']));
		if (!strlen($user_id))
			return null;

		return $this->find_user($user_id);
	}

	public function authorize_user()
	{
		if (!$this->check_session_host())
			return null;

		$user = $this->get_user();

		if (!$user)
			return null;

		if (!$this->cookie_updated)
		{
			$this->update_cookie($user->id);
			$this->cookie_updated = true;
		}

		return $user;
	}

	protected function update_cookie($id)
	{
		$ticket = $this->get_ticket($id);

		$cookie_name = Phpr::$config->get('CMS_AUTH_COOKIE_NAME', $this->cookie_name);
		$cookie_lifetime = Phpr::$config->get($this->cookie_lifetime_name, $this->cookie_lifetime);

		$cookie_path = Phpr::$config->get('CMS_AUTH_COOKIE_PATH', $this->cookie_path);
		$cookie_domain = Phpr::$config->get('CMS_AUTH_COOKIE_DOMAIN', $this->cookie_domain);

		Phpr::$response->cookie($cookie_name, $ticket, $cookie_lifetime, $cookie_path, $cookie_domain);
	}

	public function user_login($user_id)
	{
		$this->update_cookie($user_id);
		Phpr::$events->fire_event('cms:on_front_end_login');
	}

	public function logout($redirect = null)
	{
		$cookie_name = Phpr::$config->get('CMS_AUTH_COOKIE_NAME', $this->cookie_name);
		$cookie_path = Phpr::$config->get('CMS_AUTH_COOKIE_PATH', $this->cookie_path);
		$cookie_domain = Phpr::$config->get('CMS_AUTH_COOKIE_DOMAIN', $this->cookie_domain);

		Phpr::$response->delete_cookie($cookie_name, $cookie_path, $cookie_domain);

		$this->user = null;

		Phpr::$session->destroy();

		if ($redirect !== null)
			Phpr::$response->redirect($redirect);
	}

	protected function before_login_session_destroy($user)
	{
		Phpr::$events->fire_event('cms:on_front_end_login');
	}

	protected function keep_session_data()
	{
		return false;
	}

	public function find_user($user_id)
	{
		if (isset(self::$user_cache[$user_id]))
			return self::$user_cache[$user_id];

		$user_class = $this->user_class_name;
		$user_obj = new $user_class();

		return self::$user_cache[$user_id] = $user_obj->where('deleted_at is null')->where('users.id=?', $user_id)->find();
	}
}