<?php

namespace App\Model;

use App\ModelInterface;

class Flat implements ModelInterface
{
	public $id;
	public $address_id;
	public $status_id;
	// этаж
	public $floor;
	// цена м²
	public $price_m;
	// цена м² по акции
	public $price_m_a;
	// доп. наценка
	public $price_add;
	// номер квартиры
	public $flat_num;
	// кол-во комнат
	public $rooms;
	// общая площадь
	public $area_overall;
	// планировка
	public $plan;

	static $xlsCollation = [
		'A' => 'id',
		'C' => 'address',
		'D' => 'status',
		'L' => 'floor',
		'M' => 'price_m',
		'N' => 'price_add',
		'R' => 'flat_num',
		'S' => 'rooms',
		'V' => 'area_overall'
	];
	// погрешность поиска похожих квартир по площади (м²)
	const SIMILAR_AREA_OFFSET = 5;

	public static function getTableName()
	{
		return 'flat';
	}
}