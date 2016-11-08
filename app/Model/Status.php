<?php

namespace App\Model;

use App\ModelInterface;

class Status implements ModelInterface
{
	public $id;
	public $title;

	public function __construct()
	{
		$this->id = (integer)$this->id;
	}

	public static function getTableName()
	{
		return 'status';
	}

}