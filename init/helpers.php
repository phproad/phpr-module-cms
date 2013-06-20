<?php

function theme_url($path = '/', $root_url = true, $public = false)
{
	return Cms_Html::theme_url($path, $root_url, $public);
}

function content_block($code, $name, $params=array())
{
	echo Cms_Html::content_block($code, $name, $params);
}

function global_content_block($code, $name, $params=array())
{
	echo Cms_Html::global_content_block($code, $name, $params);
}

function text_block($code, $name, $params=array())
{
	echo Cms_Html::content_block($code, $name, $params, 'text');
}

function global_text_block($code, $name, $params=array())
{
	echo Cms_Html::global_content_block($code, $name, $params, 'text');
}

function __($phrase, $params=null, $key=null)
{
	return Cms_Html::locale_string($phrase, $params, $key);
}

function flash_message()
{
	return Cms_Html::flash_message();
}

function format_currency($value)
{
	return Core_Locale::format_currency($value);
}
