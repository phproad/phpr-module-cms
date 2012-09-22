<?php

class Cms_Service_Actions extends Cms_Action_Base
{
    /*
     * CMS service functions
     */

    public function on_get_currency()
    {    	
		echo format_currency(post('value', 0));
    }

}