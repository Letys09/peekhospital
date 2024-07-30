<?php
	//use Slim\App;

	//return function (App $app) {
		$container = $app->getContainer();

		// view renderer
		$container['renderer'] = function ($c) {
			$settings = $c->get('settings')['renderer'];
			return new \Slim\Views\PhpRenderer($settings['template_path']);
		};

		// rpt renderer
		$container['rpt_renderer'] = function ($c) {
			$settings = $c->get('settings')['rpt_renderer'];
			return new \Slim\Views\PhpRenderer($settings['template_path']);
		};

		// frm renderer
		$container['frm_renderer'] = function ($c) {
			$settings = $c->get('settings')['frm_renderer'];
			return new \Slim\Views\PhpRenderer($settings['template_path']);
		};

		// monolog
		$container['logger'] = function ($c) {
			$settings = $c->get('settings')['logger'];
			$logger = new \Monolog\Logger($settings['name']);
			$logger->pushProcessor(new \Monolog\Processor\UidProcessor());
			$logger->pushHandler(new \Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
			return $logger;
		};

		// Database
			$container['db'] = function($c) {
				$connectionString = $c->get('settings')['connectionString'];
				
				$pdo = new PDO($connectionString['dns'], $connectionString['user'], $connectionString['pass']);

				$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

				return new \Envms\FluentPDO\Query($pdo);
				
			};
			
			// Register component view 
			$container['view'] = function ($container) {
				return new \Slim\Views\PhpRenderer('../templates/');
			};

			// Models
			$container['model'] = function($c) {
				return (object)[
					'seg_sesion' => new App\Model\SegSesionModel($c->db),
					'seg_log' => new App\Model\SegLogModel($c->db),
					'usuario' => new App\Model\UsuarioModel($c->db),
					'colaborador' => new App\Model\ColaboradorModel($c->db),
					'agenda' => new App\Model\AgendaModel($c->db),
					'producto' => new App\Model\ProductoModel($c->db),
					'hospital' => new App\Model\HospitalModel($c->db),
					'visita' => new App\Model\VisitaModel($c->db),
					'prod_salida' => new App\Model\ProdSalidaModel($c->db),
					'prod_entrada' => new App\Model\ProdEntradaModel($c->db),
					'farmacia' => new App\Model\FarmaciaModel($c->db),
					'receta' => new App\Model\RecetaModel($c->db),
					'det_venta' => new App\Model\DetVentaModel($c->db),
					'prod_stock' => new App\Model\ProdStockModel($c->db),
					'transaction' => new App\Lib\Transaction($c->db),
				];
			};
	//};
?>