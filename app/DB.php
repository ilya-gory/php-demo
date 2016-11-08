<?php
namespace App;

use App\DB\Query;
use App\Model\Address;
use App\Model\Flat;
use App\Model\Status;
use Psr\Log\LoggerInterface;

class DB
{
	private $pdo;
	/**
	 * @var LoggerInterface
	 */
	private $logger;

	function __construct(array $config, LoggerInterface $logger)
	{
		$this->pdo = new \PDO($config['dsn'], $config['username'], $config['password']);
		$this->logger = $logger;
	}

	/**
	 * @param string $tName
	 * @param string $cName
	 * @return array
	 */
	function fetchAll($tName, $cName)
	{
		/** @noinspection SqlDialectInspection */
		return $this->pdo->query("SELECT * FROM $tName")->fetchAll(\PDO::FETCH_CLASS, $cName);
	}

	/**
	 * @param string $tName
	 * @param string $colName
	 * @return array
	 */
	function fetchAllColumn($tName, $colName)
	{
		return $this->pdo->query("SELECT $colName FROM $tName")->fetchAll(\PDO::FETCH_COLUMN);
	}

	/**
	 * @param string $tName
	 * @param string $cName
	 * @param int $id
	 * @return mixed
	 */
	function fetchOne($tName, $cName, $id)
	{
		/** @noinspection SqlDialectInspection */
		$stmt = $this->pdo->prepare("SELECT * FROM $tName WHERE id = :id");
		$stmt->execute(['id' => $id]);
		return $stmt->fetchObject($cName);
	}

	/**
	 * @param int $id
	 * @return Flat
	 */
	function flatSingle($id)
	{
		$tName = Flat::getTableName();
		$atName = Address::getTableName();
		$stName = Status::getTableName();

		/** @noinspection SqlDialectInspection */
		$sql = "SELECT $tName.*,$atName.title as address,$stName.title as status FROM $tName LEFT JOIN $atName ON $tName.address_id = $atName.id LEFT JOIN $stName ON $tName.status_id = $stName.id WHERE $tName.id = :id";
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute(['id' => $id]);
		return $stmt->fetchObject(Flat::class);
	}

	/**
	 * @return array
	 */
	function flatFilterValues()
	{
		$subs = [];
		$tName = Flat::getTableName();
		foreach (['rooms', 'floor'] as $d) {
			$subs[] = "(SELECT group_concat(DISTINCT $d) FROM $tName) as $d";
		}
		$d = "((price_m * $tName.area_overall)+$tName.price_add)";
		$subs[] = "(SELECT concat(MIN($d),',',MAX($d)) FROM $tName) as price_overall";
		$_subs = implode(',', $subs);
		$res = $this->pdo->query("SELECT $_subs", \PDO::FETCH_ASSOC)->fetch();
		return array_reduce(array_keys($res), function ($m, $k) use ($res) {
			$m[$k] = explode(',', $res[$k]);
			return $m;
		}, []);
	}

	function flatListFilter(Query $query)
	{
		$tName = Flat::getTableName();
		$atName = Address::getTableName();
		$stName = Status::getTableName();

		/** @noinspection SqlDialectInspection */
		$query->setSql("SELECT $tName.*,$atName.title as address,$stName.title as status FROM $tName LEFT JOIN $atName ON $tName.address_id = $atName.id LEFT JOIN $stName ON $tName.status_id = $stName.id");
		$stmt = $this->pdo->prepare($query->getSql());
		$stmt->execute($query->values);
		return $stmt->fetchAll(\PDO::FETCH_CLASS, Flat::class);
	}

	function flatSimilar(Query $query)
	{
		$tName = Flat::getTableName();

		/** @noinspection SqlDialectInspection */
		$query->setSql("SELECT id,flat_num,floor,rooms,price_m,price_m_a,area_overall,price_add FROM $tName");
		$stmt = $this->pdo->prepare($query->getSql());
		$stmt->execute($query->values);
		return $stmt->fetchAll(\PDO::FETCH_CLASS, Flat::class);
	}

	function flatSimilar2(Query $query)
	{
		$tName = Flat::getTableName();
		/** @noinspection SqlDialectInspection */
		$query->setSql("SELECT flat_num FROM $tName");
		$stmt = $this->pdo->prepare($query->getSql());
		$stmt->execute($query->values);
		return $stmt->fetchAll(\PDO::FETCH_COLUMN);
	}

	/**
	 * @param string $tName
	 * @param array $rows
	 */
	function insertRefs($tName, array $rows)
	{
		$rows = array_values($rows);
		/** @noinspection SqlDialectInspection */
		$stmt = $this->pdo->prepare("INSERT INTO $tName (title) VALUES (?)");
		$this->pdo->beginTransaction();
		foreach ($rows as $row) {
			if (!$stmt->execute([$row])) {
				$err = $stmt->errorInfo();
				throw new \PDOException(print_r($err));
			}
		}
		$this->pdo->commit();
	}

	function updateFlats(array $rows)
	{
		if (count($rows) == 0) {
			return 0;
		}
		$rows = array_map('get_object_vars', $rows);
		$fields = array_keys($rows[0]);
		$sets = [];
		foreach ($fields as $field) {
			if ($field == 'id') {
				continue;
			}
			$sets[] = "$field = :$field";
		}
		$_sets = implode(',', $sets);
		$tName = Flat::getTableName();
		/** @noinspection SqlDialectInspection */
		$stmt = $this->pdo->prepare("UPDATE $tName SET $_sets WHERE id = :id");
		$count = 0;
		$this->pdo->beginTransaction();
		foreach ($rows as $row) {
			if (!$stmt->execute($row)) {
				throw new \PDOException(print_r($stmt->errorInfo()));
			} else {
				$count += $stmt->rowCount();
			}
		}
		$this->pdo->commit();
		return $count;
	}

	function insertFlats(array $rows)
	{
		if (count($rows) == 0) {
			return 0;
		}
		$rows = array_map('get_object_vars', $rows);
		$fields = array_keys($rows[0]);
		$_fields = implode(',', $fields);
		$values = implode(',', array_fill(0, count($fields), '?'));
		$rows = array_map('array_values', $rows);
		$tName = Flat::getTableName();
		/** @noinspection SqlDialectInspection */
		$stmt = $this->pdo->prepare("INSERT INTO $tName ($_fields) VALUES ($values)");
		$this->pdo->beginTransaction();
		$cnt = 0;
		foreach ($rows as $row) {
			if (!$stmt->execute($row)) {
				throw new \PDOException(print_r($stmt->errorInfo()));
			} else {
				$cnt += $stmt->rowCount();
			}
		}
		$this->pdo->commit();
		return $cnt;
	}

}