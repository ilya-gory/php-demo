<?php
namespace App\DB;
class Query
{
	private $sql = '';
	public $values = [];
	private $placeholders = [];
	private $statement = [];

	const ASSOC_IN = 'IN';
	const ASSOC_NIN = 'NOT IN';
	const ASSOC_EQ = '=';
	const ASSOC_NE = '<>';
	const ASSOC_GT = '>';
	const ASSOC_LT = '<';
	const ASSOC_GTE = '>=';
	const ASSOC_LTE = '<=';
	const U_WHERE = 'WHERE';
	const U_AND = 'AND';
	const U_OR = 'OR';

	public function __construct($sql = null)
	{
		if ($sql != null) {
			$this->sql = $sql;
		}
	}

	function getSql()
	{
		return $this->sql . ' ' . implode(' ', $this->statement);
	}

	function setSql($str)
	{
		$this->sql = $str;
	}

	private function placeholder($f)
	{
		$k = 1;
		$f = str_ireplace(['(', ')'], '_', $f);
		if (key_exists($f, $this->placeholders)) {
			$k = $this->placeholders[$f] + 1;
		}
		$this->placeholders[$f] = $k;
		return "$f$k";
	}

	private function prep($field, $value)
	{
		$p = $this->placeholder($field);
		$this->values[$p] = $value;
		return $p;
	}

	// plain SOME = OTHER
	private function stmtPlain($field, $assoc, $value)
	{
		return "$field $assoc :" . $this->prep($field, $value);
	}

	// array SOME IN (OTHERs)
	private function stmtArray($field, $assoc, $values)
	{
		$stmt = [];
		foreach ($values as $value) {
			$stmt[] = ":" . $this->prep($field, $value);
		}
		return "$field $assoc (" . implode(',', $stmt) . ')';
	}

	private function op($u, $field = null, $assoc, $value)
	{
		if (count($this->statement) == 0 && $u != self::U_WHERE) {
			$u = self::U_WHERE;
		}
		$this->statement[] = $u;
		if ($field != null) {
			$this->statement[] = $this->{is_array($value) ? 'stmtArray' : 'stmtPlain'}($field, $assoc, $value);
		}
		return $this;
	}

	function where($field, $assoc, $value)
	{
		return $this->op(self::U_WHERE, $field, $assoc, $value);
	}

	function where_and($field, $assoc, $value)
	{
		return $this->op(self::U_AND, $field, $assoc, $value);
	}

	function where_or($field, $assoc, $value)
	{
		return $this->op(self::U_OR, $field, $assoc, $value);
	}
}