<?php

namespace App\Model;

use App\ModelInterface;

class Address implements ModelInterface
{
	/**
	 * @var integer
	 */
	public $id;
	public $title;

	public function __construct()
	{
		$this->id = (integer)$this->id;
	}
	

	public static function getTableName()
	{
		return 'address';
	}
}