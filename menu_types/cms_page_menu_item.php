<?php

class Cms_Page_Menu_Item extends Cms_Menu_Item_Base
{
    public function get_info()
    {
        return array(
            'name'=>'Link to Page',
            'description'=>'Select a Website Page link'
        );
    }

}