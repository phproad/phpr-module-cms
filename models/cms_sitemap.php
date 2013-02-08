<?php

class Cms_Sitemap extends Core_Settings_Model 
{
    public $record_code = 'cms_sitemap';
    
    private $url_count = 0;
    const max_urls = 50000; // Protocol limit is 50k
    const max_generated = 10000; 

    public static function create()
    {
        $config = new self();
        return $config->load();
    }
    
    protected function build_form() 
    {
        $this->add_form_section('Select which pages you would like to appear in the sitemap')->tab('Pages');
        $this->add_form_custom_area('pages')->tab('Pages');

        $this->add_field('include_blog_posts', 'Generate individual blog post pages', 'full', db_varchar)->tab('Blog Posts')->render_as(frm_checkbox);
        $this->add_field('blog_posts_path', 'Blog posts Root Path', 'full', db_varchar)->tab('Blog Posts');
        $this->add_field('blog_posts_frequency', 'Change frequency', 'left', db_varchar)->tab('Blog Posts')->render_as(frm_dropdown);
        $this->add_field('blog_posts_priority', 'Priority', 'right', db_varchar)->tab('Blog Posts')->validation('The blog posts priority field should contain a number between 0 and 1')->method('priority_validation');
        
    }
    
    protected function init_config_data() 
    {
        $this->include_blog_posts = 0;           
        $this->blog_posts_path = '/blog/post';           
        $this->blog_posts_frequency = 'monthly';            
        $this->blog_posts_priority = 0.2;
    }

    public function get_blog_posts_frequency_options($key_index = -1)
    {
        return array(
            'always'  => 'always',
            'hourly'  => 'hourly',
            'daily'   => 'daily',
            'weekly'  => 'weekly',
            'monthly' => 'monthly',
            'yearly'  => 'yearly',
            'never'   => 'never'
        );
    }
    
    public function priority_validation($name, $value) 
    {
        if ($value < 0 || $value > 1)
            $this->validation->set_error('Priority should be between 0 and 1', $name, true);

        return true;
    }
        
    public function generate_sitemap() 
    {
        header("Content-Type: application/xml");
        $xml = new DOMDocument();
        $xml->encoding = 'UTF-8';
        
        $urlset = $xml->createElement('urlset'); 
        $urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $urlset->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $urlset->setAttribute('xsi:schemaLocation', 'http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd');
        
        $active_theme = Cms_Theme::get_active_theme();

        // Pages
        // 
        $page_list = Cms_Page::create()
            ->where('published=1')
            ->where('sitemap_visible=1')
            ->where("security_id != 'users'")
            ->where('theme_id = ?', $active_theme->code)
            ->find_all();
        
        if ($page_list->count) 
        {
            foreach ($page_list as $page) 
            {
                $page_url = root_url($page->url, true);

                if (substr($page_url, -1) != '/') 
                    $page_url .= '/';

                if ($url = $this->prepare_url_element($xml, $page_url,  date('c', strtotime($page->updated_at ? $page->updated_at : $page->created_at)), 'weekly', '0.7'))
                    $urlset->appendChild($url);
            }
        }

        // Blog Posts
        // 
        if ($this->include_blog_posts) 
        {
            $blog_post_list = Blog_Post::create()
                ->limit(self::max_generated)
                ->order('blog_posts.updated_at desc')
                ->find_all();

            foreach ($blog_post_list as $blog_post) 
            {
                $blog_post_url = root_url($this->blog_posts_path.'/'.$blog_post->url_title, true);
                
                if (substr($blog_post_url, -1) != '/') 
                    $blog_post_url .= '/';

                if ($url = $this->prepare_url_element($xml, $blog_post_url,  date('c', strtotime($blog_post->published_date)), $this->blog_posts_frequency, $this->blog_posts_priority))
                    $urlset->appendChild($url);
            }
        }
                
        $xml->appendChild($urlset);
        
        return $xml->saveXML();
    }
    
    private function prepare_url_element($xml, $page_url, $page_lastmod, $page_frequency, $page_priority) 
    {
        if ($this->url_count < self::max_urls) 
        {
        
            $url = $xml->createElement('url');
            
            $url->appendChild($xml->createElement('loc', $page_url));
            $url->appendChild($xml->createElement('lastmod', $page_lastmod));
            $url->appendChild($xml->createElement('frequency', $page_frequency));
            $url->appendChild($xml->createElement('priority', $page_priority));
                                    
            $this->url_count++;
                                    
            return $url;
        } 
        else return false;
    }

}