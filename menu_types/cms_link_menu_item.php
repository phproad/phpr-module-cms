<?php

class Cms_Link_Menu_Item extends Cms_Menu_Item_Base
{
    public function get_info()
    {
        return array(
            'name'=>'Manual Link',
            'description'=>'Define a menu link manually'
        );
    }

    public function build_config_form($host)
    {
        $host->add_field('discount_amount', 'Discount amount', 'full', db_float, 'Action')
            ->comment('Please specify a percentage value to invoice this user', 'above')
            ->validation()->required('Please specify an amount');    	
    }

}