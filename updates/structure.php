<?php

$table = Db_Structure::table('cms_security');
	$table->primary_key('id', db_varchar, 25);
	$table->column('name', db_varchar);
	$table->column('description', db_varchar);

$table = Db_Structure::table('cms_pages');
	$table->primary_key('id');
	$table->column('name', db_varchar);
	$table->column('file_name', db_varchar);
	$table->column('url', db_varchar)->index();
	$table->column('title', db_varchar)->index();
	$table->column('head', db_text);
	$table->column('description', db_text);
	$table->column('keywords', db_text);
	$table->column('content', 'mediumtext');
	$table->column('sort_order', db_number)->index();
	$table->column('sitemap_visible', db_bool)->set_default(true);
	$table->column('published', db_bool)->set_default(true);
	$table->column('action_code', db_varchar, 100)->index();
	$table->column('code_post', db_text);
	$table->column('code_pre', db_text);
	$table->column('code_ajax', db_text);
	$table->column('template_id', db_number)->index();
	$table->column('theme_id', db_varchar, 64)->index();
	$table->column('parent_id', db_number);
	$table->column('security_id', db_varchar, 15)->set_default('everyone');
	$table->column('security_page_id', db_number);
	$table->column('unique_id', db_varchar, 25);
	$table->column('module_id', db_varchar, 30);
	$table->footprints();

$table = Db_Structure::table('cms_templates');
	$table->primary_key('id');
	$table->column('name', db_varchar, 100);
	$table->column('file_name', db_varchar);
	$table->column('content', 'mediumtext');
	$table->column('theme_id', db_varchar, 64)->index();
	$table->column('unique_id', db_varchar, 25);
	$table->column('is_default', db_bool);
	$table->footprints();

$table = Db_Structure::table('cms_partials');
	$table->primary_key('id');
	$table->column('name', db_varchar, 100);
	$table->column('file_name', db_varchar);
	$table->column('content', 'mediumtext');
	$table->column('theme_id', db_varchar, 64)->index();
	$table->column('module_id', db_varchar, 30);
	$table->footprints();

$table = Db_Structure::table('cms_content');
	$table->primary_key('id');
	$table->column('name', db_varchar, 100);
	$table->column('code', db_varchar, 100)->index();
	$table->column('type', db_varchar, 10)->set_default('html');
	$table->column('content', 'mediumtext');
	$table->column('is_global', db_bool);
	$table->column('page_id', db_number)->index();
	$table->column('theme_id', db_varchar, 64)->index();
	$table->footprints();

$table = Db_Structure::table('cms_strings');
	$table->primary_key('id');
	$table->column('code', db_varchar, 100)->index();
	$table->column('content', 'mediumtext');
	$table->column('original', 'mediumtext');
	$table->column('global', db_bool);
	$table->column('page_id', db_number)->index();
	$table->column('theme_id', db_varchar, 64)->index();

$table = Db_Structure::table('cms_themes');
	$table->primary_key('id');
	$table->column('name', db_varchar);
	$table->column('code', db_varchar, 100)->index();
	$table->column('description', db_text);
	$table->column('author_name', db_varchar);
	$table->column('author_website', db_varchar);
	$table->column('default_theme', db_bool);
	$table->column('enabled', db_bool)->set_default(true);

$table = Db_Structure::table('cms_page_visits');
	$table->primary_key('id');
	$table->column('url', db_varchar);
	$table->column('visit_date', db_date);
	$table->column('ip', db_varchar, 15);
	$table->column('page_id', db_number);
	$table->add_key('date_ip', array('visit_date', 'ip'));
