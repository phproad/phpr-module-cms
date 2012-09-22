CREATE TABLE IF NOT EXISTS `cms_security` (
  `id` varchar(15) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO `cms_security` (`id`, `name`, `description`) VALUES
('everyone', 'All', 'Page will be displayed to the public'),
('users', 'Registered users only', 'Only logged in users can access this page'),
('guests', 'Guests only', 'Only guests will be able to access this page');

CREATE TABLE IF NOT EXISTS `cms_pages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `head` text,
  `description` text,
  `keywords` text,
  `content` mediumtext,
  `sort_order` int(11) DEFAULT NULL,
  `published` tinyint(4) DEFAULT '1',
  `action_code` varchar(100) DEFAULT NULL,
  `code_post` text,
  `code_pre` text,
  `code_ajax` text,
  `template_id` int(11) DEFAULT NULL,
  `theme_id` varchar(64) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `security_id` varchar(15) DEFAULT 'everyone',
  `security_page_id` int(11) DEFAULT NULL,
  `created_user_id` int(11) DEFAULT NULL,
  `updated_user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `unique_id` varchar(25) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `url` (`url`),
  KEY `title` (`title`),
  KEY `action_code` (`action_code`),
  KEY `template_id` (`template_id`),
  KEY `theme_id` (`theme_id`),
  KEY `sort_order` (`sort_order`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `cms_templates` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(100) default NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `content` mediumtext,
  `theme_id` varchar(64) DEFAULT NULL,
  `created_user_id` int(11) default NULL,
  `updated_user_id` int(11) default NULL,
  `created_at` datetime default NULL,
  `updated_at` datetime default NULL,
  `unique_id` varchar(25) DEFAULT NULL,
  PRIMARY KEY  (`id`),
  KEY `theme_id` (`theme_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `cms_partials` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(100) default NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `content` mediumtext,
  `theme_id` varchar(64) DEFAULT NULL,
  `created_user_id` int(11) default NULL,
  `updated_user_id` int(11) default NULL,
  `created_at` datetime default NULL,
  `updated_at` datetime default NULL,
  PRIMARY KEY  (`id`),
  KEY `theme_id` (`theme_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `cms_content` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(100) default NULL,
  `code` varchar(100) default NULL,
  `content` mediumtext,
  `is_global` tinyint(4) DEFAULT NULL,
  `page_id` int(11) DEFAULT NULL,
  `theme_id` varchar(64) DEFAULT NULL,
  `created_user_id` int(11) default NULL,
  `updated_user_id` int(11) default NULL,
  `created_at` datetime default NULL,
  `updated_at` datetime default NULL,
  PRIMARY KEY  (`id`),
  KEY `page_id` (`page_id`),
  KEY `theme_id` (`theme_id`),
  KEY `code` (`code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `cms_strings` (
  `id` int(11) NOT NULL auto_increment,
  `code` varchar(100) default NULL,
  `content` mediumtext,
  `original` mediumtext,
  `global` tinyint(4) DEFAULT NULL,
  `page_id` int(11) DEFAULT NULL,
  `theme_id` varchar(64) DEFAULT NULL,
  PRIMARY KEY  (`id`),
  KEY `page_id` (`page_id`),
  KEY `theme_id` (`theme_id`),
  KEY `code` (`code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `cms_themes` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(255) default NULL,
  `code` varchar(100) default NULL,
  `description` text,
  `author_name` varchar(255) default NULL,
  `author_website` varchar(255) default NULL,
  `default_theme` tinyint(4) default NULL,
  `enabled` tinyint(4) default 1,
  PRIMARY KEY  (`id`),
  KEY `code` (`code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;