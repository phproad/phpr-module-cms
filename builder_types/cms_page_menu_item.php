<?php

class Cms_Page_Menu_Item extends Builder_Menu_Item_Base
{
    public function get_info()
    {
        return array(
            'name'=>'Page Link',
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

        // Page has not changed, leave it alone
        if (isset($host->fetched['page_id']) && $host->page_id == $host->fetched['page_id'])
            return;

        $category = Cms_Page::create()->find($host->page_id);
        
        if (!$category)
            throw new Phpr_ApplicationException('Page not found: '. $host->page_id);

        $host->url = $category->url;

        // Navigation label has changed, leave it alone
        if (isset($host->fetched['label']) && $host->label != $host->fetched['label'])
            return;

        // Navigation label has been set manually, no touchy
        if (strlen($host->label))
            return;

        $host->label = $category->name;
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


