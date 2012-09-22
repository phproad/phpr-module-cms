CREATE TABLE `cms_page_visits` (
    `id` int(11) NOT NULL auto_increment,
    `url` varchar(255) default NULL,
    `visit_date` date default NULL,
    `ip` varchar(15) default NULL,
    `page_id` int default NULL,
    PRIMARY KEY  (`id`),
    KEY `date_and_ip` (`visit_date`,`ip`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;