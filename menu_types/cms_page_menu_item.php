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

    public function build_config_form($host)
    {
        $host->add_field('page_id', 'Page', 'full', db_number, 'Link')
            ->comment('Please select the Page to link to', 'above')
            ->renderAs(frm_dropdown)
            ->validation()->required('Please select the Page to link to');
    }

    public function build_menu_item($host)
    {
        $category = Cms_Page::create()->find($host->page_id);
        
        if (!$category)
            throw new Phpr_ApplicationException('Page not found: '. $host->page_id);

        $host->label = $category->name;
        $host->url = $category->url;
    }

    public function get_page_id_options($key_value= -1)
    {
        return Cms_Page::get_name_list();
    }

    public function get_page_id_option_state($key_value= -1)
    {
        return false;
    }

}


