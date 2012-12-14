<?php

class Cms_Security extends Phpr_Security
{

    public $userClassName = "User";
    public $cookieName = "Website";
    protected $cookieLifetimeVar = 'FRONTEND_AUTH_COOKIE_LIFETIME';
    protected static $user_cache = array();
    protected $cookie_updated = false;

    public function login(Phpr_Validation $Validation = null, $redirect = null, $Login = null, $Password = null, $DefaultField = 'login')
    {
        return parent::login($Validation, $redirect, $Login, $Password, $DefaultField);
    }

    public function getUser()
    {
        if ($this->user !== null)
            return $this->user;

        $cookie_name = Phpr::$config->get('FRONTEND_AUTH_COOKIE_NAME', $this->cookieName);
        $ticket = Phpr::$request->cookie($cookie_name);

        $frontend_ticket_param = Phpr::$config->get('TICKET_PARAM_NAME', 'frontend_ticket');

        if ($ticket === null)
        {
            $ticket = $this->restoreTicket(Phpr::$request->get_field($frontend_ticket_param));
        }
        else
        {
            $this->removeTicket(Phpr::$request->get_field($frontend_ticket_param));
        }

        if (!$ticket)
            return null;

        $ticket = $this->validateTicket($ticket);
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

        $user = $this->getUser();

        if (!$user)
            return null;

        if (!$this->cookie_updated)
        {
            $this->updateCookie($user->id);
            $this->cookie_updated = true;
        }

        return $user;
    }

    protected function updateCookie($id)
    {
        $ticket = $this->getTicket($id);

        $cookie_name = Phpr::$config->get('FRONTEND_AUTH_COOKIE_NAME', $this->cookieName);
        $cookie_lifetime = Phpr::$config->get($this->cookieLifetimeVar, $this->cookieLifetime);

        $cookie_path = Phpr::$config->get('FRONTEND_AUTH_COOKIE_PATH', $this->cookiePath);
        $cookie_domain = Phpr::$config->get('FRONTEND_AUTH_COOKIE_DOMAIN', $this->cookieDomain);

        Phpr::$response->cookie($cookie_name, $ticket, $cookie_lifetime, $cookie_path, $cookie_domain);
    }

    public function user_login($user_id)
    {
        $this->updateCookie($user_id);
        Phpr::$events->fire_event('on_front_end_login');
    }

    public function logout($redirect = null)
    {
        $cookie_name = Phpr::$config->get('FRONTEND_AUTH_COOKIE_NAME', $this->cookieName);
        $cookie_path = Phpr::$config->get('FRONTEND_AUTH_COOKIE_PATH', $this->cookiePath);
        $cookie_domain = Phpr::$config->get('FRONTEND_AUTH_COOKIE_DOMAIN', $this->cookieDomain);

        Phpr::$response->delete_cookie($cookie_name, $cookie_path, $cookie_domain);

        $this->user = null;

        Phpr::$session->destroy();

        if ($redirect !== null)
            Phpr::$response->redirect($redirect);
    }

    protected function beforeLoginSessionDestroy($user)
    {
        Phpr::$events->fire_event('on_front_end_login');
    }

    protected function keepSessionData()
    {
		return false;
    }

    public function find_user($user_id)
    {
        if (isset(self::$user_cache[$user_id]))
            return self::$user_cache[$user_id];

        return self::$user_cache[$user_id] = User::create()->where('deleted_at is null')->where('users.id=?', $user_id)->find();
    }

}

