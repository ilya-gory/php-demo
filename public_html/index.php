<?php
ini_set('display_errors', 1);
require "../vendor/autoload.php";

$config = [
	'settings' => [
		'displayErrorDetails' => true,
		/*
		 * конфиг приложения тут
		 */
		'applicationLevel' => [
			// фильтрация списка квартир
			'flat/collection' => [
				// id статусов, которые скрывают квартиры
				'status_hide' => [7, 9]
			],
			'flat/item' => [
				// red | green
				'status_colors' => [
					'1' => 'green'
				],
				// id статусов "свободно"
				'status_avail' => [18],
			],
			'flat/popup' => [
				// почта на которую отправляется вопрос из popup
				'email' => ''
			]
		]
	],
	'db' => [
		'development' => [
			'dsn' => 'mysql:host=localhost;dbname=dbase;charset=utf8',
			'username' => 'root',
			'password' => 'pass'
		],
		'production' => [
			'dsn' => 'mysql:host=localhost;dbname=dbase',
			'username' => 'admin',
			'password' => 'pass'
		]
	]
];

$app = new Slim\App($config);
$app->getContainer()['logger'] = function () {
	$logger = new Monolog\Logger('dev');
	$stdoutHandler = new \Monolog\Handler\StreamHandler('php://stdout', \Monolog\Logger::INFO);
	$logger->pushHandler($stdoutHandler);
	return $logger;
};
$app->getContainer()['db'] = function (\Interop\Container\ContainerInterface $container) use ($config) {
	$env = $container->get('environment')['PHP_ENV'] ?: 'production';
	return new \App\DB($config['db'][$env], $container->get('logger'));
};
$app->getContainer()['view'] = function (\Interop\Container\ContainerInterface $container) {
	$view = new \Slim\Views\Twig(realpath('../view'), [
		'cache' => $container->get('environment')['PHP_ENV'] == 'development' ? false : realpath('../cache')
	]);
	$basePath = rtrim(str_ireplace('index.php', '', $container['request']->getUri()->getBasePath()), '/');
	$view->addExtension(new Slim\Views\TwigExtension($container['router'], $basePath));
	return $view;
};
$app->get('/_import', \App\Handler\Import::class);
$app->get('/flat', App\Handler\Flat\Collection::class);
$app->get('/flat/{id}', App\Handler\Flat\Item::class);
$app->post('/_popup', App\Handler\Flat\Popup::class);
$app->get('/[{path:.*}]', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {
	$view = $request->getAttribute('path') ?: 'index';
	$r = '';
	try {
		$r = $this->view->render($response, "pages/$view.twig");
	} catch (Twig_Error_Loader $ex) {
		throw new \Slim\Exception\NotFoundException($request, $response);
	}
	return $r;
});
$app->run();
