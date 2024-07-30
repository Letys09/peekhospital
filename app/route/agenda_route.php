<?php
	use App\Lib\Response,
		App\Lib\MiddlewareToken,
		PHPMailer\PHPMailer\PHPMailer,
		PHPMailer\PHPMailer\Exception,
		Slim\Http\UploadedFile;
		require_once './core/defines.php';

	$app->group('/agenda/', function() use ($app) {

		$this->get('get/{tipo}', function($request, $response, $arguments){
			$tipo = $arguments['tipo'];
			$data = ['response' => false];
			if($tipo == 'cirugia' || $tipo == 'procedimiento' || $tipo == 'anestesia'){
				$info = $this->model->agenda->get($tipo);
				if($info->response){
					$data['registros'] = $info->result;
					$data['response'] = true;
				}
			}else if($tipo == 'hospital'){
				$info = $this->model->agenda->getHospital();
				if($info->response){
					$registros = array();
					foreach($info->result as $registro){
						$info_visita = $this->model->visita->getBelleza($registro->mascota_id);
						if($info_visita->response){
							$registro->id = $info_visita->result->visita_id;
							array_push($registros, $registro);
						}
					}

					$data['registros'] = $registros;
					$data['response'] = true;
				}
			}else if($tipo == 'visita'){
				$visitas = $this->model->agenda->getVisitas();
				foreach($visitas as $visita){
					$visita->propietario = $this->model->usuario->getPropietario($visita->propietario_id)->result->nombre; 
					$visita->colaborador = $this->model->usuario->getColaborador($visita->colaborador_inicio_id)->result->nombre; 
				}
				$data['registros'] = $visitas;
				$data['response'] = true;
			}
			return $response->withJson($data);
        });

	});

?>