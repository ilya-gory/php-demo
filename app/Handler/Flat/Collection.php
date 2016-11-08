<?php
namespace App\Handler\Flat;

use App\DB;
use App\DB\Query;
use App\Model\Flat;
use Interop\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Request;
use Slim\Views\Twig;

class Collection
{

	/**
	 * @var Twig
	 */
	private $view;
	/**
	 * @var DB
	 */
	private $db;
	/**
	 * @var array
	 */
	private $config;

	public function __construct(ContainerInterface $container)
	{
		$this->view = $container->get('view');
		$this->db = $container->get('db');
		$this->config = $container->get('settings')['applicationLevel']['flat/collection'];
	}

	/**
	 * @param array $memo
	 * @param Flat $flat
	 * @return array
	 */
	private function planGrouping($memo, $flat)
	{
		if ($flat->plan == null)
			return $memo;

		if (!key_exists($flat->plan, $memo))
			$memo[$flat->plan] = [];

		$memo[$flat->plan][] = $flat;
		return $memo;
	}

	function __invoke(Request $request, ResponseInterface $response, array $args)
	{
		$q = new Query();
		foreach (['floor', 'rooms'] as $param) {
			$p = $request->getParam($param);
			if ($p == null)
				continue;
			if (!is_array($p))
				$p = [$p];
			$q->where_and($param, Query::ASSOC_IN, $p);
		}
		unset($param, $p);
		foreach (['min', 'max'] as $param) {
			$p = $request->getParam("price_$param");
			if ($p == null)
				continue;
			$q->where_and('price_overall', $param == 'min' ? Query::ASSOC_GT : Query::ASSOC_LT, (float)$p);
		}
		$q->where_and('status_id', Query::ASSOC_NIN, $this->config['status_hide']);

		$flatList = $this->db->flatListFilter($q);
		$filters = $this->db->flatFilterValues();

		$flatPlaned = array_reduce($flatList, [$this, 'planGrouping'], []);

		return $this->view->render($response, 'flat/collection.twig', [
			'flatList' => $flatList,
			'filter' => $filters,
			'query' => $request->getQueryParams(),
			'flatPlaned' => $flatPlaned
		]);
	}

}