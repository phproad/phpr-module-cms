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
        $host->add_field('manual_label', 'Link Label', 'full', db_varchar, 'Link')
            ->comment('Please specify a label for this link', 'above')
            ->validation()->required('Please a label for this link');

        $host->add_field('manual_url', 'Link URL', 'full', db_varchar, 'Link')
            ->comment('Please specify a URL for this link', 'above')
            ->validation()->required('Please a URL for this link');            
    }

    public function build_menu_item($host)
    {
        $host->label = $host->manual_label;
        $host->url = $host->manual_url;
    }

}