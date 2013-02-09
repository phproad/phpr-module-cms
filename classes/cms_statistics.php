<?php

class Cms_Statistics
{
    private static $visitors_stats = null;

    public static function delete_old_pageviews($number_to_keep = null)
    {
        if  ($number_to_keep === null)
            $number_to_keep = Cms_Stats_Settings::get()->keep_pageviews;

        $count = Db_Helper::scalar('select count(*) from cms_page_visits');
        $offset = $count - $number_to_keep;

        if ($offset <= 0)
            return;
        
        Db_Helper::query('delete from cms_page_visits order by id limit '.$offset);
    }
    
    public static function log_visit($page, $url)
    {
        $ip = Phpr::$request->get_user_ip();

        $bind = array();
        $bind['ip'] = $ip;
        $bind['page_id'] = $page->id;
        $bind['visit_date'] = Phpr_Date::user_date(Phpr_DateTime::now())->get_date();
        $bind['url'] = $url;
        
        Db_Helper::query('insert into cms_page_visits(url, visit_date, ip, page_id) values (:url, :visit_date, :ip, :page_id)', $bind);
    }

    public static function get_visitor_stats($start, $end)
    {
        $start_sql_date = $start->to_sql_date();
        $end_sql_date = $end->to_sql_date();
        
        if (self::$visitors_stats !== null 
            && self::$visitors_stats[0] == $start_sql_date 
            && self::$visitors_stats[1] == $end_sql_date)
            return self::$visitors_stats[2];

        // Calculate the interval and use to get previous term
        $interval = $end->substract_datetime($start);
        $prev_end = $start->add_days(-1);
        $prev_start = $prev_end->substract_interval($interval);

        $data = Db_Helper::object('select
            (select count(distinct ip) from cms_page_visits where visit_date >= :current_start and visit_date <= :current_end) as visitors_current,
            (select count(distinct ip) from cms_page_visits where visit_date >= :prev_start and visit_date <= :prev_end) as visitors_previous,
            
            (select count(*) from cms_page_visits where visit_date >= :current_start and visit_date <= :current_end) as pageviews_current,
            (select count(*) from cms_page_visits where visit_date >= :prev_start and visit_date <= :prev_end) as pageviews_previous
        ', array(
            'current_start'=>$start->to_sql_date(),
            'current_end'=>$end->to_sql_date(),
            'prev_start'=>$prev_start->to_sql_date(),
            'prev_end'=>$prev_end->to_sql_date()
        ));
        
        self::$visitors_stats = array($start_sql_date, $end_sql_date, $data);
        
        return $data;
    }

    public static function get_chart_series($start, $end)
    {
        $data = self::get_chart_data($start->to_sql_date(), $end->to_sql_date());

        $chart_data = array();

        // Build array of last 30 days
        $now = Phpr_DateTime::now();
        $now->set_php_datetime(strtotime("00:00:00"));
        for ($i = 1; $i < 31; $i++)
        {
            $now = $now->add_days(-1);
            $key = $now->to_sql_date();
            $int = $now->get_php_time() * 1000;
            $chart_data[$key] = array($int, 0);
        }

        // Ensure early date comes first
        $chart_data = array_reverse($chart_data);

        // Layer database data on top
        foreach ($data as $d)
        {
            $int = strtotime($d->series_id) * 1000;
            $chart_data[$d->series_id] = array($int, (int)$d->record_value);
        }
         
        // Strip array keys
        $new_chart_data = array();
        foreach ($chart_data as $cdata)
        {
            $new_chart_data[] = $cdata;
        }
        $chart_data = $new_chart_data;

        return $chart_data;
    }

    public static function get_chart_data($start, $end)
    {
        $query = "select 
            date(visit_date) as series_id, 
            count(distinct ip) as record_value
        from
            cms_page_visits
        where
            date(visit_date) between :start and :end
        group by 1";

        return Db_Helper::object_array($query, array('start'=>$start, 'end'=>$end));
    }

    public static function get_top_pages($start, $end)
    {
        $count = 5;
        
        return Db_Helper::object_array("select url, count(*) as count from cms_page_visits
            where visit_date >= :start and visit_date <= :end
            group by url
            order by 2 desc
            limit 0, $count",
            array(
                'start'=>$start->to_sql_date(),
                'end'=>$end->to_sql_date()
            )
        );
    }

}

