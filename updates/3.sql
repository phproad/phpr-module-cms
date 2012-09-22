alter table cms_pages add column sitemap_visible tinyint(4) NULL default '1';
alter table cms_content add column type char(10) NULL default 'html';
