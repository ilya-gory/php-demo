<?php

namespace App\Handler;

use App\DB;
use App\Model\Flat;
use Interop\Container\ContainerInterface;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Request;
use Slim\Views\Twig;

class Import
{
	/**
	 * @var DB
	 */
	private $db;
	/**
	 * @var \PHPExcel_Worksheet
	 */
	private $ws;
	/**
	 * @var Logger
	 */
	private $logger;
	/**
	 * @var Twig
	 */
	private $view;

	/**
	 * @param string $s
	 * @return string
	 */
	static function cleanString($s)
	{
		return preg_replace('/\s+/', ' ', trim($s));
	}

	/**
	 * @param string $rName
	 * @return string
	 */
	static function refClassName($rName)
	{
		return '\\App\\Model\\' . ucwords($rName, '_');
	}

	/**
	 * @param Object[] $arr
	 * @param string $id
	 * @return mixed
	 */
	static function by($arr, $id)
	{
		return array_reduce($arr, function ($m, $r) use ($id) {
			$m[$r->{$id}] = $r;
			return $m;
		}, []);
	}

	function __construct(ContainerInterface $container)
	{
		$this->db = $container->get('db');
		$this->logger = $container->get('logger');
		$this->view = $container->get('view');
	}

	function __invoke(Request $request, ResponseInterface $response, array $args)
	{
		$this->readFile(realpath('../ftp/reestr_kv.xlsx'));
		$this->updateRefs('address');
		$this->updateRefs('status');
		$rowsCount = $this->updateFlats();
		$this->view->render($response, 'import.twig', [
			'rowsCount' => $rowsCount
		]);
	}

	/**
	 * @param string $path
	 * @throws \PHPExcel_Reader_Exception
	 */
	function readFile($path)
	{
		$t = \PHPExcel_IOFactory::identify($path);
		/**
		 * @var \PHPExcel_Reader_Abstract $r
		 */
		$r = \PHPExcel_IOFactory::createReader($t);
		$r->setReadDataOnly(true);
		/**
		 * @var \PHPExcel $f
		 */
		$f = $r->load($path);
		$this->ws = $f->getActiveSheet()->garbageCollect();
		unset($r);
		unset($f);
	}

	function walkRows(callable $yeld)
	{
		$rc = $this->ws->getHighestRowAndColumn();
		for ($i = 2; $i < $rc['row']; $i++) {
			try {
				$yeld($i);
			} catch (\Exception $e) {
				$this->logger->error($e->getMessage());
			}
		}
	}

	/**
	 * @param string $rName
	 * @param string $id
	 * @return mixed
	 */
	function refBy($rName, $id = 'title')
	{
		$cName = self::refClassName($rName);
		/** @noinspection PhpUndefinedMethodInspection */
		$tName = $cName::getTableName();
		$arr = $this->db->fetchAll($tName, $cName);
		return self::by($arr, $id);
	}

	/**
	 * @param string $rName
	 */
	function updateRefs($rName)
	{
		$colName = array_search($rName, Flat::$xlsCollation);
		$titles = [];
		// collect xls
		$this->walkRows(function ($i) use ($colName, &$titles) {
			$titles[] = self::cleanString($this->ws->getCell($colName . $i)->getValue());
		});
		// merge with db
		$cName = self::refClassName($rName);
		/** @noinspection PhpUndefinedMethodInspection */
		$tName = $cName::getTableName();
		$refs = $this->db->fetchAllColumn($tName, 'title');
		$this->db->insertRefs($tName, array_diff(array_unique($titles), $refs));
	}

	function updateFlats()
	{
		$status = $this->refBy('status');
		$address = $this->refBy('address');
		if (count($status) == 0) {
			throw new \Error('No statuses found!');
		}
		if (count($address) == 0) {
			throw new \Error('No addresses found');
		}
		$saved = self::by($this->db->fetchAll(Flat::getTableName(), Flat::class), 'id');
		$proceed = [
			'n' => [],
			'u' => []
		];
		$idColl = array_search('id', Flat::$xlsCollation);
		$this->walkRows(function ($i) use ($saved, $idColl, $status, $address, &$proceed) {
			$id = (integer)$this->ws->getCell($idColl . $i)->getValue();
			$isSaved = key_exists($id, $saved);
			$flat = new Flat();
			$flat->id = $id;
			foreach (Flat::$xlsCollation as $column => $prop) {
				if ($column == $idColl) {
					continue;
				}
				$cellData = $this->ws->getCell($column . $i)->getValue();
				switch ($prop) {
					case 'address':
						$r = $address[self::cleanString($cellData)];
						$flat->address_id = $r->id;
						break;
					case 'status':
						$r = $status[self::cleanString($cellData)];
						$flat->status_id = $r->id;
						break;
					default:
						$flat->{$prop} = $cellData;
				}
			}
			$proceed[$isSaved ? 'u' : 'n'][] = $flat;
			unset($flat);
		});
		$cnt = 0;
		$cnt += $this->db->insertFlats($proceed['n']);
		$cnt += $this->db->updateFlats($proceed['u']);
		return $cnt;
	}
}