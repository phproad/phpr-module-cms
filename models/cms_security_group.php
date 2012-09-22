<?php

class Cms_Security_Group extends Db_ActiveRecord
{
	const everyone = 'everyone';
	const users = 'users';
	const guests = 'guests';
	
	public $table_name = 'cms_security';
}

