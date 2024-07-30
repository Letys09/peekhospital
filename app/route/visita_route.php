<?php
	use App\Lib\Response,
		App\Lib\MiddlewareToken;
use Envms\FluentPDO\Literal;

	require_once './core/defines.php';

	$app->group('/visita/', function () use ($app) {
		$this->get('', function ($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de visita');
		});
		
	})->add( new MiddlewareToken() );
?>