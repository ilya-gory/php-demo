<?php
namespace App\Handler\Flat;

use Interop\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\Twig;

class Popup
{
	/**
	 * @var Twig
	 */
	private $view;
	/**
	 * @var array
	 */
	private $config;

	public function __construct(ContainerInterface $container)
	{
		$this->view = $container->get('view');
		$this->config = $container->get('settings')['applicationLevel']['flat/popup'];
	}

	function __invoke(Request $request, Response $response, array $attrs)
	{
		$p = array_reduce(['fname', 'lname', 'phone', 'email', 'message', 'check', 'url'], function ($m, $n) use ($request) {
			$m[$n] = $request->getParam($n);
			return $m;
		}, []);
		$host = $request->getHeader('Host');
		mail(
			$this->config['email'],
			"[$host[0] Вопрос по квартире",
			$this->view->fetch('flat/item/pop_email.twig', $p)
		);
		return $response->withStatus(200, 'Sent');
	}


}