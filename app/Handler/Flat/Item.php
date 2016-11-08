<?php

namespace App\Handler\Flat;

use App\DB;
use App\Model\Address;
use App\Model\Flat;
use App\Model\Status;
use App\DB\Query;
use Interop\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Exception\NotFoundException;
use Slim\Http\Request;
use Slim\Views\Twig;

class Item
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
		$this->config = $container->get('settings')['applicationLevel']['flat/item'];
	}

	/**
	 * @param Request $request
	 * @param ResponseInterface $response
	 * @param array $attrs
	 * @return ResponseInterface
	 * @throws NotFoundException
	 */
	function __invoke(Request $request, ResponseInterface $response, array $attrs)
	{
		$addressList = $this->db->fetchAll(Address::getTableName(), Address::class);
		$statusList = $this->db->fetchAll(Status::getTableName(), Status::class);
		$flat = $this->db->flatSingle((int)$attrs['id']);

		if (!$flat) {
			throw new NotFoundException($request, $response);
		}
		$area = intval($flat->area_overall);
		// similar
		$query = new Query();
		$query->where_and('floor(area_overall)', Query::ASSOC_LT, $area + Flat::SIMILAR_AREA_OFFSET)
			->where_and('ceil(area_overall)', Query::ASSOC_GT, $area - Flat::SIMILAR_AREA_OFFSET)
			->where_and('id', Query::ASSOC_NE, $flat->id);
		$similar = $this->db->flatSimilar($query);
		unset($query);
		$query = new Query();
		$query->where_and('status_id', Query::ASSOC_IN, $this->config['status_avail']);
		if ($flat->plan != null) {
			$query->where_and('plan', Query::ASSOC_EQ, $flat->plan);
		}
		$similar2 = $this->db->flatSimilar2($query);

		return $this->view->render($response, 'flat/item.twig', [
			'addressList' => $addressList,
			'statusList' => $statusList,
			'flat' => $flat,
			'similar' => $similar,
			'similar2' => $similar2,
			'statusColors' => $this->config['status_colors']
		]);
	}


}