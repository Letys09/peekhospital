<?php
	use App\Lib\Response,
		App\Lib\MiddlewareToken;
	require_once './core/defines.php';

	$app->group('/producto/', function() {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de producto');
		});

        $this->get('get/{id}', function($request, $response, $arguments){
            $info = $this->model->producto->get($arguments['id']);
            echo json_encode($info);
			exit;
        });

		$this->get('getAllBuscaTemplate/{inicial}/{limite}/{busqueda}', function($request, $response, $arguments) {
			$inicial = isset($_GET['start'])? $_GET['start']: $arguments['inicial'];
			$limite = isset($_GET['length'])? $_GET['length']: $arguments['limite'];
			$busqueda = isset($_GET['search']['value'])? (strlen($_GET['search']['value'])>0? $_GET['search']['value']: '_'): $arguments['busqueda'];
			$orden = isset($_GET['order'])? $_GET['columns'][$_GET['order'][0]['column']]['data']: 'nombre';
			$orden .= isset($_GET['order'])? " ".$_GET['order'][0]['dir']: " asc";

			if(count($_GET['order'])>1){
				for ($i=1; $i < count($_GET['order']); $i++) { 
					$orden .= ', '.$_GET['columns'][$_GET['order'][$i]['column']]['data'].' '.$_GET['order'][$i]['dir'];
				}
			}

            $productos = $this->model->producto->getAllBusca($inicial, $limite, $busqueda);
            $data = [];
            foreach($productos->result as $producto) {
                $accionesVisita = '<a href="#" data-id="'.$producto->id.'" data-unidad="'.$producto->unidad.'" data-popup="tooltip" title="Agregar" class="btn btn-lg btn-block btn-success btnAdd"><i class="fa fa-lg fa-shopping-cart"></i></a>';
				$stock = number_format($producto->cantidad, 2) !=null ? number_format($producto->cantidad, 2) : 'N/A';
                $data[] = array(
                    "cantidad" => "<div><h2>$stock</h2></div>",
                    "nombre" => "<div><h2>$producto->nombre</h2></div>",
                    "accionesVisita" => "<div class=\"pull-right acciones\">$accionesVisita</div>",
                    "data_id" => $producto->id,
                );
            }

            echo json_encode(array(
                'draw'=>$_GET['draw'],
                'data'=>$data,
                'recordsTotal'=>$productos->total,
                'recordsFiltered'=>$productos->filtered,
            ));
			exit(0);
		});
		
	})->add( new MiddlewareToken() );
?>