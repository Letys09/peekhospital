<?php
	use Slim\App;
	use Slim\Http\Request;
	use Slim\Http\Response;
	use	App\Lib\MiddlewareToken;

	return function (App $app) {
		$container = $app->getContainer();

		$app->get('/[{name}]', function (Request $request, Response $response, array $args) use ($container) {
			$this->logger->info("Slim-Skeleton '/' ".(isset($args['name']) ? $args['name'] : ''));
			if(!isset($args['name'])) { $args['name'] = 'agenda'; }
			
			if(!isset($_SESSION)) { session_start(); }
			if ((isset($_SESSION['usuario']))) {
					$params = array('vista' => ucfirst($args['name']));
					try{
						$params = array('vista' => ucfirst($args['name']), 'todo' => $this);
						return $this->view->render($response, "$args[name].phtml", $params);
					} catch (Throwable | Exception $e) {}
				return $this->renderer->render($response, "$args[name].phtml", $args);
			} else {
				return $this->renderer->render($response, 'login.phtml', $args);
			}
		});

		$app->get('/visita/{tipo}/{id}', function(Request $request, Response $response, array $args){
			date_default_timezone_set('America/Mexico_City');
			$this->model->transaction->iniciaTransaccion();
			$tipo = $args['tipo'];
			$cirugia = md5('cirugia');
			$procedimiento = md5('procedimiento');
			$anestesia = md5('anestesia');
			$hospital = md5('hospital');
			$fecha = date('Y-m-d H:i:s');
			$visita = false; 

			if($tipo == $cirugia || $tipo == $procedimiento || $tipo == $anestesia){
				$info = $this->model->agenda->getBy($args['id']);
				$farmacia_paquete = $this->model->agenda->getFarmaciaPaq($args['id']);
				if($farmacia_paquete->response){
					$datos = $info->result;
					$colaborador_id = $_SESSION['usuario']->colaborador_id;
					$info_paquete = $farmacia_paquete->result;
					if($info_paquete->visita_id != '' && $info_paquete->visita_id != null){
						$visita_id = $info_paquete->visita_id;
						$info_visita = $this->model->visita->get($info_paquete->visita_id)->result;
						$venta_id = $info_visita->venta_id;
						$detalles = $this->model->visita->getDetalles($venta_id)->result;
						$info_receta = $this->model->receta->getByVenta($venta_id);
						if(!is_object($info_receta->result)){
							$data_receta = [
								'colaborador_id' => $_SESSION['usuario']->colaborador_id,
								'propietario_id' => $datos->propietario_id,
								'mascota_id' => $datos->mascota_id,
								'fecha' => $fecha,
								'pie_pagina' => $datos->producto,
								'padecimiento' => '',
								'venta_id' => $venta_id
							];
							$add_receta = $this->model->receta->add($data_receta);
							$receta_id = $add_receta->result;
							$add_receta->state = $this->model->transaction->confirmaTransaccion();
						}else{
							$receta_id = $info_receta->result->id;
						}
					}else{
						$comprobante = $this->model->hospital->getSiguienteComprobante();
						$data_visita = [
							'propietario_id' => 396,
							'fecha_inicio' => $fecha,
							'colaborador_inicio_id' => $colaborador_id,
							'observaciones' => $datos->propietario.' '.$datos->mascota.' '.$datos->producto,
							'rasurado' => 0,
							'comprobante' => $comprobante
						];
						$visita = $this->model->visita->add($data_visita);
						if($visita->response) { 
							$visita_id = $visita->result;
							$edit_hospital = $this->model->hospital->edit([ 'comprobante' => intval($comprobante) ], 1); 
							if($edit_hospital->response) {
								$venta = $this->model->visita->addVenta(['facturar' => 0, 'status' => 1]); 
								if($venta->response) { 
									$venta_id = $venta->result;
									$data_receta = [
										'colaborador_id' => $_SESSION['usuario']->colaborador_id,
										'propietario_id' => $datos->propietario_id,
										'mascota_id' => $datos->mascota_id,
										'fecha' => date('Y-m-d'),
										'pie_pagina' => $datos->producto,
										'padecimiento' => '',
										'venta_id' => $venta_id
									];
									$add_receta = $this->model->receta->add($data_receta);
									if($add_receta->response){
										$receta_id = $add_receta->result;
										$folio_salida = $this->model->prod_salida->getSiguienteFolio();
										$data_salida = [ 
											'colaborador_id' => $colaborador_id, 
											'propietario_id' => 396, 
											'venta_id' => $venta_id, 
											'folio' => $folio_salida, 
											'fecha' => $fecha 
										];
										$salida = $this->model->prod_salida->add($data_salida);
										if ($salida->response) { 
											$salida_id = $salida->result;
											$paqFarm = $this->model->farmacia->asignaPaquete($info_paquete->farmacia_paquete_id, $visita_id);
											if ($paqFarm->response) {
												$this->model->seg_log->add('Asigna Paquete Farmacia desde hospital', $paqFarm->result, 'farmacia_paquete', 1);
												$data_venta = [ 'venta_id' => $venta_id, 'rasurado' => 0];
												$edit_visita = $this->model->visita->edit($data_venta, $visita_id); 
												if($edit_visita->response){
													$detalles = array();
													$seg_log = $this->model->seg_log->add('Alta nueva visita desde hospital', $visita_id, 'visita'); 
													if (!$seg_log->response) {
														$seg_log->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($seg_log);
													}
												} else { $edit_visita->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($edit_visita->SetResponse(false, 'No se actualizo la información de la visita')); }
											} else { $paqFarm->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($paqFarm->SetResponse(false, 'No se editó el paquete de farmacia')); }
										} else { $salida->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($salida->SetResponse(false, 'No se agregó la salida')); }
									} else { $add_receta->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($venta->SetResponse(false, 'No se agregó la receta')); }
								} else { $venta->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($venta->SetResponse(false, 'No se agregó la venta')); }
							} else { $edit_hospital->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($edit_hospital); }
						} else { $visita->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($visita->SetResponse(false, 'No se agregó la visita')); }

						$visita->state = $this->model->transaction->confirmaTransaccion();
					}

					$datos->visita_id = $visita_id;
					$datos->venta_id = $venta_id;
					$datos->detalles = $detalles;
					$datos->receta_id = $receta_id;
					$datos->farmacia = true;
					$args['datos'] = $datos;
					$visita = true;
				}
			}else if($tipo == $hospital){
				$datos = new stdClass();
				$visita_md5_id = $args['id']; //md5
				$info_venta = $this->model->visita->getBy($visita_md5_id);
				if($info_venta->response){
					$visita_id = $info_venta->result->id;
					$venta_id = $info_venta->result->venta_id;
					$detalles = $this->model->visita->getDetalles($venta_id)->result;
					$propietario_id = $info_venta->result->propietario_id;
					$propietario = $this->model->usuario->getPropietario($propietario_id)->result->nombre;
					$belleza = $this->model->visita->getBellezaByVisita($visita_id);
					if($belleza->response){
						$mascota_id = $belleza->result->mascota_id;
						$mascota = $belleza->result->mascota;
						$info_receta = $this->model->receta->getByVenta($venta_id);
						if(!is_object($info_receta->result)){
							$data_receta = [
								'colaborador_id' => $_SESSION['usuario']->colaborador_id,
								'propietario_id' => $propietario_id,
								'mascota_id' => $mascota_id,
								'fecha' => $fecha,
								'pie_pagina' => 'HOSPITALIZACIÓN',
								'padecimiento' => '',
								'venta_id' => $venta_id
							];
							$add_receta = $this->model->receta->add($data_receta);
							$receta_id = $add_receta->result;
							$add_receta->state = $this->model->transaction->confirmaTransaccion();
						}else{
							$receta_id = $info_receta->result->id;
						}
					}else { $belleza->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($belleza->SetResponse(false, 'No se pudo obtener información de la hospitalización')); }
				} else { $info_venta->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($info_venta->SetResponse(false, 'No se pudo obtener información de la visita')); }

				$datos->visita_id = $visita_id;
				$datos->venta_id = $venta_id;
				$datos->mascota_id = $mascota_id;
				$datos->mascota = $mascota;
				$datos->propietario_id = $propietario_id;
				$datos->propietario = $propietario;
				$datos->id = 0;
				$datos->receta_id = $receta_id;
				$datos->colaborador = $_SESSION['usuario']->nombre;
				$datos->producto = 'HOSPITALIZACIÓN';
				$datos->detalles = $detalles;
				$datos->farmacia = false;
				$args['datos'] = $datos;
				$visita = true;
			}

			if($visita)	return $this->renderer->render($response, 'visita.phtml', $args);
		});

	};

?>