<?php
	use App\Lib\Response,
		App\Lib\MiddlewareToken;
use Envms\FluentPDO\Literal;

/*** Grupo bajo la ruta visita ***/
	require_once './core/defines.php';

	$app->group('/visita/', function () use ($app) {
		$this->get('', function ($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de visita');
		});

		$this->get('find/{busqueda}', function ($request, $response, $arguments) {
			return $response->withJson($this->model->visita->find($arguments['busqueda']));
		});

		$this->get('get/{id}', function ($request, $response, $arguments) {
			$visita = $this->model->visita->get($arguments['id']);
			if($visita->response) {
				$visita->result->propietario = $this->model->usuario->get($this->model->propietario->get($visita->result->propietario_id)->result->usuario_id)->result;
				if($visita->result->status == 1 && $visita->result->colaborador_id!=null) { $visita->result->colaborador = $this->model->usuario->get($this->model->colaborador->get($visita->result->colaborador_id)->result->usuario_id)->result; }
				$visita->result->colaborador_inicio = $this->model->usuario->get($this->model->colaborador->get($visita->result->colaborador_inicio_id)->result->usuario_id)->result;
				$visita->result->colaborador_cancela = $visita->result->colaborador_cancela_id > 0 ? $this->model->usuario->get($this->model->colaborador->get($visita->result->colaborador_cancela_id)->result->usuario_id)->result : null;
				if($visita->result->colaborador_termino_id != null) { $visita->result->colaborador_termino = $this->model->usuario->get($this->model->colaborador->get($visita->result->colaborador_termino_id)->result->usuario_id)->result; }
				else { $visita->result->colaborador_termino = [ 'id'=>0, 'nombre'=>'', 'apellidos'=>'']; }
				$visita->result->venta = $this->model->venta->get($visita->result->venta_id)->result;
				$detalles = $this->model->det_venta->getByVenta($visita->result->venta_id)->result;
				if($visita->result->status == 0 && count($detalles) == 0) $detalles = $this->model->det_venta->getByVentaCancelada($visita->result->venta_id)->result;

				$devoluciones = $this->model->devolucion->getByVenta($visita->result->venta_id)->result;
				if(count($devoluciones) > 0) {
					$devoluciones = array_column($devoluciones, 'det_venta_id');
					$detalles = array_filter($detalles, function($detalle) use($devoluciones) { return !in_array($detalle->id, $devoluciones); });
				}
				$visita->result->detalles = $detalles;
				foreach($visita->result->detalles as &$detalle) {
					$det_venta_id = $detalle->detVentaId;
					$detalle->producto = $this->model->producto->get($detalle->producto_id)->result;
					if($detalle->mascota_id != null) {
						$detalle->mascota = $this->model->mascota->get($detalle->mascota_id)->result;
						$detalle->extra = $this->model->mascota->getExtra($detalle->id);
					} else { $detalle->mascota = [ 'id'=>0, 'nombre'=>'N/A' ]; }

					if($detalle->categoria_id == 5 || $detalle->categoria_id == 6){
						$datosVacuna = $this->model->vacuna->getByDetVenta($det_venta_id, 1)->result;
						if(is_array($datosVacuna)){
							$cant = COUNT($datosVacuna);
							if($cant > 0){
								$arrayDataVacuna['lote'] = $datosVacuna[0]->lote;
								$arrayDataVacuna['notas'] = $datosVacuna[0]->observaciones;
								$arrayDataVacuna['siguiente'] = $datosVacuna[0]->siguiente;
								$detalle->dataVacuna = $arrayDataVacuna;
							}
						}
					} 
					$detalle->colaborador = $this->model->usuario->get($this->model->colaborador->get($detalle->colaborador_id, 0)->result->usuario_id)->result;
				}
				$visita->result->devoluciones = $this->model->devolucion->getByVenta($visita->result->venta_id)->result;
				// $visita->result->devoluciones = $this->model->det_venta->getByVenta($visita->result->venta_id, 0, 2)->result;
				foreach($visita->result->devoluciones as &$devolucion) {
					$devolucion->det_venta = $this->model->det_venta->get($devolucion->det_venta_id)->result;
					$devolucion->producto = $this->model->producto->get($devolucion->det_venta->producto_id)->result;
					$devolucion->colaborador = $this->model->usuario->get($this->model->colaborador->get($devolucion->colaborador_id)->result->usuario_id)->result;
				}
				$visita->result->pagos = $this->model->pago->getByVisita($visita->result->id)->result;
				$visita->result->pagado = 0; foreach($visita->result->pagos as &$pago) {
					$visita->result->pagado += floatval($pago->cantidad);
					$pago->colaborador = $this->model->usuario->get($this->model->colaborador->get($pago->colaborador_id)->result->usuario_id)->result;
					$pago->metodo_pago = $this->model->metodo_pago->get($pago->metodo)->result;
				}

				if($visita->result->propietario_id == $_SESSION['prop_farm']){
					$visita->paquete = $this->model->farmacia->getPaqueteByVisita($visita->result->id, false)->result;
					$visitaOriginal = $this->model->det_venta->get($visita->paquete->det_venta_id)->result->venta_id;
					$visita->paquete->visitaOriginal = $visitaOriginal;
				}
			}

			echo json_encode($visita);
			exit(0);
			//return $response->withJson($visita);
		});

		$this->get('getPending/[{propietario_id}]', function($request, $response, $arguments) {
			$propietario_id = isset($arguments['propietario_id'])? $arguments['propietario_id']: 0;
			return $response->withJson($this->model->visita->getPending($propietario_id));
		});
		
		$this->get('getByVenta/{venta_id}[/{inicio}/{fin}]', function ($request, $response, $arguments) {
			date_default_timezone_set('America/Mexico_City');
			$arguments['inicio'] = isset($arguments['inicio'])? $arguments['inicio']: '2000/01/01';
			$arguments['fin'] = isset($arguments['fin'])? $arguments['fin']: date('Y/m/d');
			return $response->withJson($this->model->visita->getByVenta($arguments['venta_id'], $arguments['inicio'], $arguments['fin']));
		});

		$this->get('getByPropietario/{propietario_id}[/{inicio}/{fin}]', function ($request, $response, $arguments) {
			ini_set('memory_limit','640M');
			date_default_timezone_set('America/Mexico_City');
			$arguments['inicio'] = isset($arguments['inicio'])? $arguments['inicio']: '2000/01/01';
			$arguments['fin'] = isset($arguments['fin'])? $arguments['fin']: date('Y/m/d');
			$visitas = $this->model->visita->getByPropietario($arguments['propietario_id'], $arguments['inicio'], $arguments['fin']);
			foreach($visitas->result as $visita) {
				if($visita->status==1 && $visita->colaborador_id!=null) { $visita->colaborador = $this->model->usuario->get($this->model->colaborador->get($visita->colaborador_id)->result->usuario_id)->result; }
				$visita->colaborador_inicio = $this->model->usuario->get($this->model->colaborador->get($visita->colaborador_inicio_id)->result->usuario_id)->result;
				if($visita->colaborador_termino_id != null) { $visita->colaborador_termino = $this->model->usuario->get($this->model->colaborador->get($visita->colaborador_termino_id)->result->usuario_id)->result; }
				else { $visita->colaborador_termino = [ 'id'=>0, 'nombre'=>'', 'apellidos'=>'']; }
				$visita->venta = $this->model->venta->get($visita->venta_id)->result;

				$detalles = $this->model->det_venta->getByVenta($visita->venta_id)->result;
				foreach($detalles as $detalle) {
					$detalle->producto = $this->model->producto->get($detalle->producto_id)->result;
					if($detalle->mascota_id != null) {
						$detalle->mascota = $this->model->mascota->get($detalle->mascota_id)->result;
					} else { $detalle->mascota = [ 'id'=>0, 'nombre'=>'N/A' ]; }
				}
				$visita->det_venta = $detalles;
				
				if($arguments['propietario_id'] == $_SESSION['prop_farm']){
					$paq = $this->model->farmacia->getPaqueteByVisita($visita->id, false)->result;
					$visita->paquetef = '<strong>'.strtoupper($paq->mascota).'</strong> '.strtoupper($paq->propietario).'<br>'.$paq->concepto;
				}

				$pagos = $this->model->pago->getByVisita($visita->id)->result;
				$pagado = 0; foreach($pagos as $pago) {
					$pagado += floatval($pago->cantidad);
				}
				// $visita->pagos = $pagos;
				$visita->pagado = $pagado;
			}

			echo json_encode($visitas);
			exit(0);
			return $response->withJson($visitas);
		});

		$this->get('getVisitsByOwner/{year}/{month}[/{colaborador_id}]', function($request, $response, $arguments) {
			$arguments['colaborador_id'] = isset($arguments['colaborador_id'])? $arguments['colaborador_id']: 0;
			return $response->withJson($this->model->visita->getVisitsByOwner($arguments['year'], $arguments['month'], $arguments['colaborador_id']));
		});

		$this->get('getCountByPropietario/{propietario_id}/{year}/{month}', function($request, $response, $arguments) {
			return $response->withJson($this->model->visita->getCountByPropietario($arguments['propietario_id'], $arguments['year'], $arguments['month']));
		});

		$this->get('getByPropietarioLight/{propietario_id}[/{inicio}/{fin}]', function ($request, $response, $arguments) {
			date_default_timezone_set('America/Mexico_City');
			$arguments['inicio'] = isset($arguments['inicio'])? $arguments['inicio']: '2000/01/01';
			$arguments['fin'] = isset($arguments['fin'])? $arguments['fin']: date('Y/m/d');
			$visitas = $this->model->visita->getByPropietario($arguments['propietario_id'], $arguments['inicio'], $arguments['fin']);
			foreach($visitas->result as $visita) {				
				$colaborador_inicio = $this->model->usuario->get($this->model->colaborador->get($visita->colaborador_inicio_id, 0)->result->usuario_id)->result;
				$visita->fechas = date('d/m/Y', strtotime($visita->fecha_inicio)).' - <strong>'.$colaborador_inicio->nombre.'</strong>';
				if($visita->colaborador_termino_id != null){
					$colaborador_termino = $this->model->usuario->get($this->model->colaborador->get($visita->colaborador_termino_id, 0)->result->usuario_id)->result;
					$visita->fechas .= '<br>'.date('d/m/Y', strtotime($visita->fecha_termino)).' - <strong>'.$colaborador_termino->nombre.'</strong>';
				}
				
				if($arguments['propietario_id'] != $_SESSION['prop_farm']){
					$conceptos = array(); $arrMascotasID = [];
					$detalles = $this->model->det_venta->getByVenta($visita->venta_id)->result;
					foreach($detalles as $detalle) {
						if($detalle->mascota_id != null) {
							$mascota = $this->model->mascota->get($detalle->mascota_id)->result;
							$conceptos[] = $mascota->nombre;
	
							if(!in_array($detalle->mascota_id, $arrMascotasID)) {
								$arrMascotasID[] = $detalle->mascota_id;
							}
						}else{
							$conceptos[] = 'Venta';
						}
					}
					$conceptos = array_unique($conceptos);
					$visita->conceptos = implode(', ',$conceptos);
					$visita->mascotas_id = implode(',',$arrMascotasID);
				}else{
					$paq = $this->model->farmacia->getPaqueteByVisita($visita->id, false)->result;
					$visita->conceptos = '<strong>'.strtoupper($paq->mascota).'</strong> '.strtoupper($paq->propietario).'<br>'.$paq->concepto;
					$visita->mascotas_id = $_SESSION['prop_farm_masc'];
				}

				$venta = $this->model->venta->get($visita->venta_id)->result;
				$visita->subtotal = $venta->subtotal;
				$visita->descuento = $venta->descuento;
				$visita->total = $venta->total;

				$pagos = $this->model->pago->getByVisita($visita->id)->result;
				$pagado = 0; foreach($pagos as $pago) {
					$pagado += $pago->cantidad - $pago->cambio - $pago->a_favor;
				}
				$visita->pagado = $pagado;

				$visita->colaborador = '';
				if($visita->colaborador_id > 0){
					$col = $this->model->colaborador->get($visita->colaborador_id)->result;
					$visita->colaborador = $col->nombre;
				}
			}

			echo json_encode($visitas);
			exit(0);
			//return $response->withJson($visitas);
		});

		$this->get('getByColaborador/{colaborador_id}[/{inicio}/{fin}]', function ($request, $response, $arguments) {
			date_default_timezone_set('America/Mexico_City');
			$arguments['inicio'] = isset($arguments['inicio'])? $arguments['inicio']: '2000/01/01';
			$arguments['fin'] = isset($arguments['fin'])? $arguments['fin']: date('Y/m/d');
			return $response->withJson($this->model->visita->getByColaborador($arguments['colaborador_id'], $arguments['inicio'], $arguments['fin']));
		});

		$this->get('getAll/[{pagina}/{limite}[/{inicio}/{fin}]]', function ($request, $response, $arguments) {
			date_default_timezone_set('America/Mexico_City');
			$arguments['pagina'] = isset($arguments['pagina'])? $arguments['pagina']: 0;
			$arguments['limite'] = isset($arguments['limite'])? $arguments['limite']: 0;
			$arguments['inicio'] = isset($arguments['inicio'])? $arguments['inicio']: '2000/01/01';
			$arguments['fin'] = isset($arguments['fin'])? $arguments['fin']: date('Y/m/d');
			return $response->withJson($this->model->visita->getAll($arguments['pagina'], $arguments['limite'], $arguments['inicio'], $arguments['fin']));
		});

		$this->get('getTotal/', function ($request, $response, $arguments) {
			return $response->withJson($this->model->visita->getTotal());
		});

		$this->get('getAdeudoAfavor/{propietario_id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$totalVendido = 0; $cambio = 0;
			$ventas = $this->model->venta->getAll(0, 0, $arguments['propietario_id'])->result;
			foreach($ventas as $venta) {
				$totalVendido += floatval($venta->total);
				if($venta->cambio != null) { $cambio += floatval($venta->cambio); }
			}

			$totalPagado = 0;
			$pagos = $this->model->pago->getAll(0, 0, $arguments['propietario_id'])->result;
			foreach($pagos as $pago) {
				$totalPagado += floatval($pago->cantidad);
			}

			$this->response->adeudo = $totalVendido+$cambio>$totalPagado? ($totalVendido+$cambio)-$totalPagado: 0;
			$this->response->afavor = $totalVendido+$cambio<$totalPagado? $totalPagado-($totalVendido+$cambio): 0;
			return $response->withJson($this->response->SetResponse(true));
		});

		$this->post('add/', function ($request, $response, $arguments) use ($app) {
			date_default_timezone_set('America/Mexico_City');
			$this->model->transaction->iniciaTransaccion();
			$colaborador_id = $this->model->colaborador->getByUsuario($_SESSION['usuario']->id)->result->id; $fecha = date('Y-m-d H:i:s');

			$parsedBody = $request->getParsedBody();
			$parsedBody['fecha_inicio'] = $parsedBody['fecha_inicio'].date(' H:i:s');
			$detalles = $parsedBody['detalles']; unset($parsedBody['detalles']);
			if(isset($parsedBody['pagos'])) { $pagos = $parsedBody['pagos']; unset($parsedBody['pagos']); } else { $pagos = []; }
			if(isset($parsedBody['accion_saldo'])) { $accion_saldo = $parsedBody['accion_saldo']; unset($parsedBody['accion_saldo']); }
			if(intval($parsedBody['status']) == 1) { $parsedBody['colaborador_id'] = $colaborador_id; }
			if(isset($parsedBody['facturar'])) { 
				$facturar = $parsedBody['propietario_id'] == $_SESSION['prop_farm'] ? 0 : $parsedBody['facturar']; 
				unset($parsedBody['facturar']); 
			}
			$parsedBody['colaborador_inicio_id'] = $colaborador_id; 
			if(intval($parsedBody['status']) == 2) { 
				$parsedBody['colaborador_termino_id'] = $colaborador_id; 
				$this->model->propietario->edit(array('primera' => 0), $parsedBody['propietario_id']);
				$parsedBody['fecha_termino'] = date('Y-m-d H:i:s');
			}
			if(isset($parsedBody['paquete'])) { $paquete = $parsedBody['paquete']; unset($parsedBody['paquete']); }
			$pushFarmacia = false; $finalizaVisita = false;

			$rasurado = $this->model->visita->getTotal()->porcentaje < intval($this->model->visita->getPorcentajeRasurado());
			$parsedBody['comprobante'] = $this->model->hospital->getSiguienteComprobante();
			$visita = $this->model->visita->add($parsedBody); 
			if($visita->response) { $id_visita = $visita->result;
				$edit_hospital = $this->model->hospital->edit([ 'comprobante'=>intval($parsedBody['comprobante']) ], 1); 
				if($edit_hospital->response) {
					$rasurado = $rasurado && intval($facturar)==0;
					$venta = $this->model->venta->add(['facturar'=>$facturar, 'status'=>1]); 
					if($venta->response) { 
						$id_venta = $venta->result; $subtotal_venta = 0; $iva_venta = 0; $total_venta=0;
						$data = [ 
							'colaborador_id'=>$colaborador_id, 
							'propietario_id'=>$parsedBody['propietario_id'], 
							'venta_id'=>$id_venta, 
							'folio'=>$this->model->prod_salida->getSiguienteFolio(), 
							'fecha'=>$fecha 
						];
						$salida = $this->model->prod_salida->add($data); 
						if($salida->response) { 
							$id_salida = $salida->result; $subtotal_salida = 0; $total_salida = 0;
							$descuento_venta = 0;
							$descuento_leal = 0;
							foreach($detalles as $detalle) { 
								if(isset($detalle['mascota_id']) && !is_numeric($detalle['mascota_id'])) { unset($detalle['mascota_id']); }
								$colaborador_asignado = $detalle['colaborador_id']; 
								$producto_id = $detalle['producto_id']; $detalle['venta_id'] = $id_venta; $detalle['colaborador_asigno_id'] = $colaborador_id;
								$extra = $detalle['extra']; unset($detalle['extra']);
								unset($detalle['siguiente_aplicacion']); 
								if(isset($detalle['uso'])) $uso = $detalle['uso']; unset($detalle['uso']);

								if($parsedBody['propietario_id'] == $_SESSION['prop_farm']){
									$detalle['descuento_porcentaje'] = '100';
									$detalle['descuento_motivo'] = 'Paquete Farmacia';
									$detalle['total'] = '0.00';
								}

								$prodInfo = $this->model->producto->get($producto_id)->result;
								$detalle['iva'] = 0;
								$descuentoDetalle = 0;
								$cantidadDetalle = floatval($detalle['cantidad']); 
								$precioDetalle = floatval($detalle['precio']); 
								$importeDetalle = $cantidadDetalle * $precioDetalle; 
								$descuentoDetalle = floatval($detalle['descuento_porcentaje'])!=0? ($importeDetalle * (intval($detalle['descuento_porcentaje']) / 100)): floatval($detalle['descuento_importe']);
								$descuento_venta += floatval($descuentoDetalle);
								$descuento_leal += $detalle['descuento_leal'];
								$detalle['tipo_iva'] = $prodInfo->iva;
								// if($prodInfo->iva == 2 && $facturar!=0) {
								if(($prodInfo->iva == 2 || $prodInfo->iva == 3 || $detalle['servicio'] != 0) && $facturar!=0) {
									// $porc = in_array($producto_id, $_SESSION['prod_farm_paq'])? 0.7: 1;
									$porc = 1;
									// $detalle['iva'] = floatval($detalle['subtotal']) * 0.16 * $porc;
									$detalle['iva'] = floatval($detalle['subtotal'] - $descuentoDetalle) * 0.16 * $porc;
									$detalle['total'] += floatval($detalle['iva']);
								}
								$det_venta = $this->model->det_venta->add($detalle); 
								if($det_venta->response) { 
									$idDetVenta = $det_venta->result; $subtotal_venta += floatval($detalle['subtotal']); $iva_venta += floatval($detalle['iva']); $total_venta += floatval($detalle['total']);
									$seg_log = $this->model->seg_log->add('Venta de producto', $idDetVenta, 'det_venta'); 
									if($prodInfo->es_paquete) {
										if(($producto_id == $_SESSION['paquete_vacunas_perro'] || $producto_id == $_SESSION['paquete_vacunas_gato']) && $prodInfo->paquete_vacunas == 1){
											$productos = $this->model->det_paquete->getByPaquete($producto_id)->result;  //$producto_id = 1936											
											$primeraVacuna = $productos[0]; //es la primera vacuna que se pone del paquete
											$primeraVacunaId = $primeraVacuna->producto_id; //es el id de la primera vacuna que se pone del paquete (1264)
											$segundaVacunaId = $productos[1]->producto_id;
											$contador = 0;
											foreach($productos as $vacuna){
												if($vacuna->categoria_id == 5){
													$sigAplicacion = $vacuna->periodo_aplicacion;
													$fechaActual = strtotime(date('Y-m-d'));
													switch ($sigAplicacion) {
														case '1': $sigAplicacion = null; break;
														case '2': $sigAplicacion = date('Y-m-d', strtotime('+6 month', $fechaActual)); break;
														case '3': $sigAplicacion = date('Y-m-d', strtotime('+1 year', $fechaActual)); break;
														default : $sigAplicacion = null; break;
													}
													if($producto_id == $_SESSION['paquete_vacunas_perro']){ $proxima = date('Y-m-d', strtotime('+15 days', $fechaActual)); }
													else{ $proxima = date('Y-m-d', strtotime('+21 days', $fechaActual)); }
													$dataVacuna = array(
														'mascota_id' => $detalle['mascota_id'], 
														'masc_medicion_id' => null,  
														'det_venta_id' => $idDetVenta, 
														'tipo' => $vacuna->categoria_id, 
														'descripcion' => $vacuna->nombre,
														'producto_paquete_id' => $producto_id,
													);
													
													if($vacuna->producto_id == $primeraVacunaId){
														if($contador == 0){
															$dataVacuna['producto_id'] = $primeraVacunaId;
															$dataVacuna['aplicacion'] = $detalle['fecha_asigno'];
															$dataVacuna['siguiente'] = $sigAplicacion;
															$dataVacuna['lote'] = $extra['loteVacuna'];
															$dataVacuna['observaciones'] = $extra['notasVacuna'];
															$dataVacuna['status'] = 2;
														}else{
															$dataVacuna['producto_id'] = $vacuna->producto_id;
															$dataVacuna['prox_vacuna_paquete'] = $proxima;
															$dataVacuna['status'] = 1;
														}
													}else if($vacuna->producto_id == $segundaVacunaId){
														$dataVacuna['producto_id'] = $vacuna->producto_id;
														$dataVacuna['prox_vacuna_paquete'] = $proxima;
														$dataVacuna['status'] = 1;
													}else{
														$dataVacuna['producto_id'] = $vacuna->producto_id;
														$dataVacuna['status'] = 1;
													}													
													$addVacuna = $this->model->vacuna->add($dataVacuna);
													if(!$addVacuna->response){
														$addVacuna->state = $this->model->transaction->regresaTransaccion(); 
														return $response->withjson($addVacuna);
													}
												}
												$contador++;
											}

											$dataPaquete = [ 
												'prod_salida_id' => $id_salida, 
												'producto_id' => $producto_id, 
												'cantidad' => floatval($detalle['cantidad']), 
												'precio' => $detalle['precio'], 
												'importe' => $detalle['cantidad'] * $detalle['precio'], 
												'descuento_importe' => $detalle['descuento_importe'], 
												'descuento_motivo' => $detalle['descuento_motivo'], 
												'total' => $detalle['total'] 
											];
											$det_salida = $this->model->det_prod_salida->add($dataPaquete); 

											//$paqueteId es el id de producto_paquete_id, ya que necesitamos el id del paquete de aplicación de la vacuna para traer los insumos 
											//que se necesitan en la aplicación de vacuna
											$paqueteId = $this->model->det_paquete->getByProdPaq($primeraVacunaId, $producto_id)->result->producto_paquete_id; //1109
											$prodsAplicacionVacuna = $this->model->det_paquete->getByPaquete($paqueteId)->result; //productos que incluye la aplicación de la vacuna

											foreach($prodsAplicacionVacuna as $prodAplicacion) { 
												$productoId = $prodAplicacion->producto_id;
												$cantidad = floatval($detalle['cantidad']) * $prodAplicacion->cantidad;
												$infoProducto = $this->model->producto->get($productoId); 
												if($infoProducto->result->stock == null || $infoProducto->result->stock >= $cantidad) { 
													$infoProducto = $infoProducto->result;
													$precio = $infoProducto->precio; 
													$importe = $cantidad * $precio; 
													// $descuento = floatval($prodAplicacion->descuento_porcentaje)!=0? ($importe * (intval($prodAplicacion->descuento_porcentaje) / 100)) : floatval($prodAplicacion->descuento_importe); 
													$descuento = $importe; 
													$total = $importe - $descuento;
													$data = [ 
														'prod_salida_id' => $id_salida, 
														'producto_id' => $prodAplicacion->producto_id, 
														'cantidad' => $cantidad, 
														'precio' => $precio, 
														'importe' => $importe, 
														'descuento_importe' => $descuento, 
														'descuento_motivo' => '', 
														'total' => $total 
													];
													$det_salida = $this->model->det_prod_salida->add($data); 
													if($det_salida->response) { 
														// $subtotal_salida += $importe; $total_salida += $total;
														$subtotal_salida = floatval($detalle['subtotal']); $total_salida = floatval($detalle['total']);
														$stock = $this->model->prod_stock->getByProducto($productoId)->result; 
														if(count($stock) > 1 || ($infoProducto->stock != null && floatval($infoProducto->stock) > 0)) { 
															$tipo=-1;
															if(count($stock) == 0) {
																$data = [ 
																	'producto_id' => $productoId, 
																	'tipo' => 1, 
																	'inicial' => 0, 
																	'cantidad' => $infoProducto->stock, 
																	'final' => $infoProducto->stock, 
																	'fecha' => $fecha, 
																	'colaborador_id' => $colaborador_id, 
																	'origen' => 0, 
																	'origen_tipo' => 1, 
																	'status' => 1 
																];
																$stock_inicial = $this->model->prod_stock->add($data); 
																if($stock_inicial->response) { $inicial = $infoProducto->stock; }
																else { 
																	$stock_inicial->state = $this->model->transaction->regresaTransaccion(); 
																	return $response->withJson($stock_inicial->SetResponse(false, 'No se agrego el registro de stock inicial, el cual no existía anteriormente')); 
																}
															} else {
																$inicial = $stock[0]->final;
															}
															$data = [ 
																'producto_id'=>$productoId, 
																'tipo'=>$tipo, 
																'inicial'=>$inicial, 
																'cantidad'=>$cantidad, 
																'final'=>$inicial+($tipo*$cantidad), 
																'fecha'=>$fecha, 
																'colaborador_id'=>$colaborador_id, 
																'origen'=>$id_salida, 
																'origen_tipo'=>7,
																'det_venta_id' => $idDetVenta 
															];
															$prod_stock = $this->model->prod_stock->add($data); 
															if($prod_stock->response) {
																$edit_producto = $this->model->producto->edit(['stock'=>$data['final']], $productoId); 
																if($edit_producto->response) {
																	// Solicitar a farmacia
																	if(in_array($infoProducto->tipo, [4,5,6,7]) && ($parsedBody['propietario_id'] == $_SESSION['cliente_general'] || isset($detalle['mascota_id']))){
																		$this->model->det_venta->edit(array('surtido' => 0), $idDetVenta);
																		$dataFarmacia = array(
																			'producto_id' => $infoProducto->id, 
																			'propietario_id' => $parsedBody['propietario_id'],
																			'mascota_id' => $parsedBody['propietario_id'] != $_SESSION['cliente_general'] ? $detalle['mascota_id'] : $_SESSION['mascota_cliente_general'],
																			'det_venta_id' => $idDetVenta,
																			'fecha' => $fecha,
																			'origen_tipo' => 2, 
																			'origen_id' => $id_visita, 
																			'usuario_solicita' => $_SESSION['colaborador']->id, 
																			'cantidad' => $cantidad,
																		);
																		$this->model->farmacia->add($dataFarmacia);
																		$pushFarmacia = true;
																	}
																} else {
																	$edit_producto->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($edit_producto);
																}
															} else { $prod_stock->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($prod_stock); }
														}

														if(intval($infoProducto->categoria_id) == 5 && isset($detalle['mascota_id'])){
															$idMedicion = null;
															if(isset($extra['peso'])){
																$this->model->seg_log->add('Entro a peso', $extra['tipo'], 'peso', 0);
																$dataMedicion = array(
																	'mascota_id' => $detalle['mascota_id'], 
																	'fecha' => $detalle['fecha_asigno'], 
																	'peso' => $extra['peso'], 
																	'temperatura' => $extra['temperatura'], 
																	'longitud' =>'', 
																	'altura' => '', 
																	'frecuencia_cardiaca' => $extra['cardiaca'], 
																	'frecuencia_respiratoria' => $extra['respiratoria'], 
																	'origen' => 'Concepto Visita'
																);
																$medi = $this->model->mascota->addMedicion($dataMedicion);
																$idMedicion = $medi->result;
															}
														}
													} else { $det_salida->state = $this->model->transaction->regresaTransaccion(); return $response->withjson($det_salida); }
												} else { $infoProducto->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($infoProducto->SetResponse(false, "NO hay suficiente stock del producto: $productoId, se requieren $cantidad unidades.")); }
											}											
										}else{
											if($prodInfo->id == $_SESSION['paq_cirugia']){
												$productos = json_decode($extra['cirugia']); unset($extra['cirugia']);
											}else{
												$productos = $this->model->det_paquete->getByPaquete($producto_id)->result; 
											}
											foreach($productos as $producto) { 
												$producto_id = $producto->producto_id;
												$infoProducto = $this->model->producto->get($producto_id); 
												if($infoProducto->result->tipo == 5){
													$cantidad = $producto->cantidad;
												}else{
													$cantidad = floatval($detalle['cantidad']) * $producto->cantidad;
												}
												if($infoProducto->result->stock==null || $infoProducto->result->stock>=$cantidad) { $infoProducto = $infoProducto->result;
													$precio = $infoProducto->precio; $importe = $cantidad * $precio; $descuento = floatval($detalle['descuento_porcentaje'])!=0? ($importe * (intval($detalle['descuento_porcentaje']) / 100)): floatval($detalle['descuento_importe']); $total = $importe - $descuento;
													$data = [ 'prod_salida_id'=>$id_salida, 'producto_id'=>$producto->producto_id, 'cantidad'=>$cantidad, 'precio'=>$precio, 'importe'=>$importe, 'descuento_importe'=>$descuento, 'descuento_motivo'=>$detalle['descuento_motivo'], 'total'=>$total ];
													$det_salida = $this->model->det_prod_salida->add($data); 
													if($det_salida->response) { $subtotal_salida += $importe; $total_salida += $total;
														$stock=$this->model->prod_stock->getByProducto($producto_id)->result; 
														if(count($stock) > 1 || ($infoProducto->stock!=null && floatval($infoProducto->stock)>0)) { 
															$tipo=-1;
															if(count($stock) == 0) {
																$data = [ 
																	'producto_id' => $producto_id, 
																	'tipo' => 1, 
																	'inicial' => 0, 
																	'cantidad' => $infoProducto->stock, 
																	'final' => $infoProducto->stock, 
																	'fecha' => $fecha, 
																	'colaborador_id' =>$colaborador_id, 
																	'origen' => 0, 
																	'origen_tipo' => 1, 
																	'status' => 1 ];
																$stock_inicial = $this->model->prod_stock->add($data); 
																if($stock_inicial->response) { $inicial = $infoProducto->stock; }
																else { 
																	$stock_inicial->state = $this->model->transaction->regresaTransaccion(); 
																	return $response->withJson($stock_inicial->SetResponse(false, 'No se agrego el registro de stock inicial, el cual no existía anteriormente')); 
																}
															} else {
																$inicial = $stock[0]->final;
															}
															$data = [ 'producto_id'=>$producto_id, 'tipo'=>$tipo, 'inicial'=>$inicial, 'cantidad'=>$cantidad, 'final'=>$inicial+($tipo*$cantidad), 'fecha'=>$fecha, 'colaborador_id'=>$colaborador_id, 'origen'=>$id_salida, 'origen_tipo'=>7, 'det_venta_id' => $idDetVenta ];
															$prod_stock = $this->model->prod_stock->add($data); 
															if($prod_stock->response) {
																$edit_producto = $this->model->producto->edit(['stock'=>$data['final']], $producto_id); 
																if($edit_producto->response) {
																	// Solicitar a farmacia
																	if(in_array($infoProducto->tipo, [4,5,6,7]) && ($parsedBody['propietario_id']==$_SESSION['cliente_general'] || isset($detalle['mascota_id']))){
																		$this->model->det_venta->edit(array('surtido' => 0), $idDetVenta);
																		$dataFarmacia = array(
																			'producto_id' => $infoProducto->id, 
																			'propietario_id' => $parsedBody['propietario_id'],
																			'mascota_id' => $parsedBody['propietario_id']!=$_SESSION['cliente_general']?$detalle['mascota_id']:$_SESSION['mascota_cliente_general'],
																			'det_venta_id' => $idDetVenta,
																			'fecha' => $fecha, //$data['fecha'],
																			'origen_tipo' => 2, 
																			'origen_id' => $id_visita, 
																			'usuario_solicita' => $_SESSION['colaborador']->id, 
																			'cantidad' => $cantidad,
																		);
																		$this->model->farmacia->add($dataFarmacia);
																		$pushFarmacia = true;
																	}
																	// Agregar registro si es producto de uso controlado
																	if($infoProducto->uso_controlado == 1){
																		$dataControlado = array(
																			'producto_id' 		=> $infoProducto->id,
																			'propietario_id' 	=> $parsedBody['propietario_id'],
																			'mascota_id' 		=> $parsedBody['propietario_id'] != $_SESSION['cliente_general'] ? $detalle['mascota_id'] : $_SESSION['mascota_cliente_general'],
																			'det_venta_id'		=> $idDetVenta, 
																			'origen_id' 		=> $id_visita,
																			'origen_tipo'		=> 2,
																			'cantidad' 			=> $cantidad, 
																			'dias' 				=> 1, 
																			'surtidos' 			=> 1, 
																			'restan' 			=> 0, 
																			'completo' 			=> 1, 
																			'siguiente' 		=> date('Y-m-d', strtotime($fecha.' +'.(2).' days')), 
																			'status' 			=> 2, 
																		);
																		$controlado = $this->model->farmacia->addControlado($dataControlado);
																		$controlado->data = $dataControlado;
																		if(!$controlado->response){ $controlado->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($controlado); }
																	}
																} else {
																	$edit_producto->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($edit_producto);
																}
															} else { $prod_stock->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($prod_stock); }
															if($rasurado) { $rasurado = $infoProducto->uso_controlado==0; }
														}

														if(!in_array(intval($infoProducto->categoria_id), [9, 10, 20, 21]) && isset($detalle['mascota_id'])){
															$extra['tipo'] = $infoProducto->categoria_id;
															unset($extra['categoria']);
															$extra['mascota_id'] = $detalle['mascota_id'];
															$extra['visita_id'] = $id_visita;
															$extra['det_venta_id'] = $idDetVenta;
															if($colaborador_asignado != $detalle['colaborador_asigno_id']){
																$extra['status'] = 3;
															}

															// RECORDATORIO
															if(isset($extra['recordatorio'])) { unset($extra['recordatorio']); }

															if($infoProducto->categoria_id != 5 && $infoProducto->categoria_id != 6) { $this->model->mascota->addBelleza($extra); }
												
															$idMedicion = null;
															if(isset($extra['peso'])){
																//if($extra['peso'] != '' || $extra['temperatura'] != '' || $extra['cardiaca'] != '' || $extra['respiratoria'] != ''){
																	$dataMedicion = array('mascota_id' => $detalle['mascota_id'], 
																							'fecha' => $detalle['fecha_asigno'], 
																							'peso' => $extra['peso'], 
																							'temperatura' => $extra['temperatura'], 
																							'longitud' =>'', 
																							'altura' => '', 
																							'frecuencia_cardiaca' => $extra['cardiaca'], 
																							'frecuencia_respiratoria' => $extra['respiratoria'], 
																							'origen' => 'Concepto Visita');
																	$medi = $this->model->mascota->addMedicion($dataMedicion);
																	$idMedicion = $medi->result;
																//}
															}

															if($extra['tipo'] == 5 || $extra['tipo'] == 6){
																$dataVacu = array('mascota_id' => $extra['mascota_id'], 
																				'masc_medicion_id' => $idMedicion, 
																				'producto_id' => $detalle['producto_id'], 
																				'det_venta_id' => $idDetVenta, 
																				'tipo' => $extra['tipo'], 
																				'descripcion' => $infoProducto->nombre, 
																				'aplicacion' => $detalle['fecha_asigno'], 
																				// 'siguiente' => $extra['siguiente'], 
																				'lote' => $extra['lote'], 
																				'observaciones' => $extra['notas'], 
																				'status' => 2, 
																				);
																if($extra['tipo'] == 5){
																	if(isset($extra['siguiente']) && $extra['siguiente'] != ''){
																		$dataVacu['siguiente'] = $extra['siguiente'];
																	}else{
																		$sigAplicacion = $infoProducto->periodo_aplicacion;
																		$fechaActual = strtotime(date('Y-m-d'));
																		switch ($sigAplicacion) {
																			case '1': $sigAplicacion = null; break;
																			case '2': $sigAplicacion = date('Y-m-d', strtotime('+6 month', $fechaActual)); break;
																			case '3': $sigAplicacion = date('Y-m-d', strtotime('+1 year', $fechaActual)); break;
																			default : $sigAplicacion = null; break;
																		}
																		$dataVacu['siguiente'] = $sigAplicacion;
																	}
																}else{
																	$dataVacu['siguiente'] = $extra['siguiente'];
																}
																$vacunaAdd = $this->model->vacuna->add($dataVacu);
																if(!$vacunaAdd->response){
																	$vacunaAdd->state = $this->model->transaction->regresaTransaccion(); 
																	return $response->withjson($vacunaAdd); 
																}
															}
														}
													} else { $det_salida->state = $this->model->transaction->regresaTransaccion(); return $response->withjson($det_salida); }
												} else { $infoProducto->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($infoProducto->SetResponse(false, "NO hay suficiente stock del producto: $producto_id, se requieren $cantidad unidades. Se cancela la transacción")); }
											}
										}
									} else {
										$cantidad = floatval($detalle['cantidad']); 
										$precio = floatval($detalle['precio']); 
										$importe = $cantidad * $precio; 
										$descuento = floatval($detalle['descuento_porcentaje'])!=0? ($importe * (intval($detalle['descuento_porcentaje']) / 100)): floatval($detalle['descuento_importe']);
										$data = [ 'prod_salida_id'=>$id_salida, 'producto_id'=>$producto_id, 'cantidad'=>$cantidad, 'precio'=>$precio, 'importe'=>$importe, 'descuento_importe'=>$descuento, 'descuento_motivo'=>$detalle['descuento_motivo'], 'total'=>$importe - $descuento ];
										$det_salida = $this->model->det_prod_salida->add($data); 
										if($det_salida->response) { $subtotal_salida += floatval($detalle['subtotal']); $total_salida += floatval($detalle['total']);
											$stock=$this->model->prod_stock->getByProducto($producto_id)->result; 
											if(count($stock) > 1  || ($prodInfo->stock!=null && floatval($prodInfo->stock)>0)) {
												$tipo=-1;
												if(count($stock) == 0) {
													$data = [ 'producto_id' => $producto_id, 'tipo' => 1, 'inicial' => 0, 'cantidad' => $prodInfo->stock, 'final' => $prodInfo->stock, 'fecha' => $fecha, 'colaborador_id' =>$colaborador_id, 'origen' => 0, 'origen_tipo' => 1, 'status' => 1 ];
													$stock_inicial = $this->model->prod_stock->add($data); 
													if($stock_inicial->response) { $inicial = $prodInfo->stock; }
													else { 
														$stock_inicial->state = $this->model->transaction->regresaTransaccion(); 
														return $response->withJson($stock_inicial->SetResponse(false, 'No se agrego el registro de stock inicial, el cual no existía anteriormente')); 
													}
												} else { $inicial=$stock[0]->final; }
												 
												$data = [ 'producto_id'=>$producto_id, 'tipo'=>$tipo, 'inicial'=>$inicial, 'cantidad'=>$cantidad, 'final'=>$inicial+($tipo*$cantidad), 'fecha'=>$fecha, 'colaborador_id'=>$colaborador_id, 'origen'=>$id_salida, 'origen_tipo'=>2, 'det_venta_id' => $idDetVenta ];
												$prod_stock = $this->model->prod_stock->add($data); 
												if($prod_stock->response) { $id_prod_stock = $prod_stock->result;
													$edit_producto = $this->model->producto->edit(['stock' => $data['final']], $producto_id); 
													if(!$edit_producto->response) {
														$edit_producto->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($edit_producto->SetResponse(false, 'No se edito el stock del producto'.$producto_id)); 
													}
												} else { $prod_stock->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($prod_stock->SetResponse(false, 'No se agrego la información del stock')); }
											}
											
											//$infoProducto = $this->model->producto->get($producto_id)->result;
											if($rasurado) { $rasurado = intval($prodInfo->uso_controlado)==0; }
											
											// Solicitar a farmacia
											if(in_array($prodInfo->tipo, [4,5,6,7]) && ($parsedBody['propietario_id']==$_SESSION['cliente_general'] || isset($detalle['mascota_id']))){
												$this->model->det_venta->edit(array('surtido' => 0), $idDetVenta);
												$dataFarmacia = array(
													'producto_id' => $prodInfo->id, 
													'propietario_id' => $parsedBody['propietario_id'],
													'mascota_id' => $parsedBody['propietario_id']!=$_SESSION['cliente_general']?$detalle['mascota_id']:$_SESSION['mascota_cliente_general'],
													'det_venta_id' => $idDetVenta,
													'fecha' => $fecha, //$data['fecha'],
													'origen_tipo' => 2, 
													'origen_id' => $id_visita, 
													'usuario_solicita' => $_SESSION['colaborador']->id, 
													'cantidad' => $cantidad,
												);
												$this->model->farmacia->add($dataFarmacia);
												$pushFarmacia = true;

												if($prodInfo->uso_controlado == 1){
													$dataControlado = array(
														'producto_id' 		=> $prodInfo->id,
														'propietario_id' 	=> $parsedBody['propietario_id'],
														'mascota_id' 		=> $parsedBody['propietario_id']!=$_SESSION['cliente_general']?$detalle['mascota_id']:$_SESSION['mascota_cliente_general'],
														// 'origen_id' 		=> $id_venta,
														'origen_id' 		=> $id_visita,
														'origen_tipo'		=> 2,
														'det_venta_id'		=> $idDetVenta, 
														'cantidad' 			=> $cantidad, 
														'dias' 				=> $uso['dias'], 
														'surtidos' 			=> $uso['surtidos'], 
														'restan' 			=> $uso['dias'] - $uso['surtidos'], 
														'completo' 			=> $uso['dias'] == $uso['surtidos'] ? 1 : 0, 
														'siguiente' 		=> date('Y-m-d', strtotime($fecha.' +'.($uso['dias']+1).' days')), 
														'status' 			=> 2, 
													);
													$controlado = $this->model->farmacia->addControlado($dataControlado);
													$controlado->data = $dataControlado;
													if(!$controlado->response){ $controlado->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($controlado); }
												}
											}
										} else { $det_salida->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($det_salida->SetResponse(false, 'No se agrego la información del detalle la salida')); }

										//if(in_array($extra['categoria'],[14,15,16,17,18]) > -1){
										//if(in_array($extra['categoria'],[9,10,20,21]) == -1){
										$idMedicion = null;
										if(!in_array($extra['categoria'],[5,6,9,10,20,21]) && isset($detalle['mascota_id'])){
											//$extra['tipo'] = $extra['categoria'] - 13;
											$extra['tipo'] = $extra['categoria'];
											unset($extra['categoria']);
											$extra['mascota_id'] = $detalle['mascota_id'];
											$extra['visita_id'] = $id_visita;
											$extra['det_venta_id'] = $idDetVenta;
											if($colaborador_asignado != $detalle['colaborador_asigno_id']){
												$extra['status'] = 3;
											}

											// RECORDATORIO
											unset($extra['recordatorio']);

											$det_salida->extra = $extra;
											$bellezaAdd = $this->model->mascota->addBelleza($extra);
											if($bellezaAdd){
												$det_salida->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($det_salida->SetResponse(false, 'No se registro la belleza'));
											}

											if(isset($extra['peso'])){
												//if($extra['peso'] != '' || $extra['temperatura'] != '' || $extra['cardiaca'] != '' || $extra['respiratoria'] != ''){
													$dataMedicion = array('mascota_id' => $detalle['mascota_id'], 
																			'fecha' => $detalle['fecha_asigno'], 
																			'peso' => $extra['peso'], 
																			'temperatura' => $extra['temperatura'], 
																			'longitud' =>'', 
																			'altura' => '', 
																			'frecuencia_cardiaca' => $extra['cardiaca'], 
																			'frecuencia_respiratoria' => $extra['respiratoria'], 
																			'origen' => 'Concepto Visita');
													$medi = $this->model->mascota->addMedicion($dataMedicion);
													$idMedicion = $medi->result;
												//}
											}
										}

										if(($prodInfo->categoria_id == 5 || $prodInfo->categoria_id == 6) && isset($detalle['mascota_id'])){
											$dataVacu = array('mascota_id' => $detalle['mascota_id'], 
												'producto_id' => $detalle['producto_id'], 
												'tipo' => $prodInfo->categoria_id, 
												'descripcion' => $prodInfo->nombre, 
												'aplicacion' => $detalle['fecha_asigno'], 
												// 'siguiente' => $extra['siguiente'], 
												'lote' => $extra['lote'], 
												'observaciones' => $extra['notas'], 
												'status' => 2, 
												'det_venta_id' => $idDetVenta,
											);

											if($idMedicion > 0) $dataVacu['masc_medicion_id'] = $idMedicion;

											if(isset($extra['siguiente']) && $extra['siguiente'] != ''){
												$dataVacu['siguiente'] = $extra['siguiente'];
											}else{
												if($prodInfo->categoria_id == 5){
													$sigAplicacion = $prod_data->periodo_aplicacion;
													$fechaActual = strtotime(date('Y-m-d'));
													switch ($sigAplicacion) {
														case '1': $sigAplicacion = null; break;
														case '2': $sigAplicacion = date('Y-m-d', strtotime('+6 month', $fechaActual)); break;
														case '3': $sigAplicacion = date('Y-m-d', strtotime('+1 year', $fechaActual)); break;
														default : $sigAplicacion = null; break;
													}
													$dataVacu['siguiente'] = $sigAplicacion;
												}
											}

											$resVacu = $this->model->vacuna->add($dataVacu);
											if(!$resVacu->response){
												$resVacu->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($resVacu->SetResponse(false, 'No se registro la vacuna'));
											}
											$this->model->seg_log->add('Agrega Vacuna / Desparacitación', $resVacu->result, 'masc_vacuna');
										}

										if($prodInfo->categoria_id == 9 && ($parsedBody['propietario_id']==$_SESSION['cliente_general'] || isset($detalle['mascota_id']))){
											if(!isset($detalle['mascota_id'])) {
												$detalle['mascota_id'] = $_SESSION['mascota_cliente_general'];
											}
											$this->model->mascota->edit(array('quirofano' => 1), $detalle['mascota_id']);
											$this->model->seg_log->add('Mascota Cirugía', $detalle['mascota_id'], 'mascota');
										}
										if($prodInfo->categoria_id == 10 && ($parsedBody['propietario_id']==$_SESSION['cliente_general'] || isset($detalle['mascota_id']))){
											$this->model->mascota->edit(array('hospitalizado' => 1), $detalle['mascota_id']);
											$this->model->seg_log->add('Mascota Hospital', $detalle['mascota_id'], 'mascota');
										}
									}
									
									if(in_array($producto_id, $_SESSION['prod_farm_paq']) && isset($detalle['mascota_id'])){
										$dataFarmPaq = array(
											'colaborador_id' => $detalle['colaborador_id'], 
											'det_venta_id' => $idDetVenta, 
											'producto_id' => $producto_id, 
											'mascota_id' => $detalle['mascota_id'], 
											'fecha' => $fecha, 
										);
										//$det_venta->paquete = $dataFarmPaq;
										$paqFarm = $this->model->farmacia->addPaquete($dataFarmPaq);
										if(!$paqFarm->response){
											$det_venta->state = $this->model->transaction->regresaTransaccion(); 
											return $response->withJson($det_venta->SetResponse(false, 'No se agrego el paquete de farmacia'));
										}
									} elseif(in_array($producto_id, $_SESSION['prod_vacunas_paq']) && isset($detalle['mascota_id'])){
										$dataFarmPaq = array(
											'colaborador_id' => $detalle['colaborador_id'], 
											'det_venta_id' => $idDetVenta, 
											'producto_id' => $producto_id, 
											'mascota_id' => $detalle['mascota_id'], 
											'fecha' => $fecha, 
										);
										$paqFarm = $this->model->farmacia->addPaquete($dataFarmPaq); if(!$paqFarm->response){
											$det_venta->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($det_venta->SetResponse(false, 'No se agrego el paquete de vacunas'));
										}
									}
								} else { $det_venta->data = $detalle; $det_venta->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($det_venta->SetResponse(false, 'No se agrego el registro del detalle de la venta')); }
							}
	
							// $data = [ 'subtotal' => $subtotal_venta, 'iva' => $iva_venta, 'descuento' => $subtotal_venta+$iva_venta-$total_venta, 'total' => $total_venta ];
							$data = [ 'subtotal' => $subtotal_venta, 'iva' => $iva_venta, 'descuento' => $subtotal_venta+$iva_venta-$total_venta, 'total' => $subtotal_venta+$iva_venta-$descuento_venta-$descuento_leal ];
							$edit_venta = $this->model->venta->edit($data, $id_venta); if($edit_venta->response) {
								if($parsedBody['propietario_id'] == $_SESSION['prop_farm']){
									$paqFarm = $this->model->farmacia->asignaPaquete($paquete, $id_visita, $subtotal_venta);
									if($paqFarm->response){
										$this->model->seg_log->add('Asigna Paquete Farmacia', $paqFarm->result, 'farmacia_paquete', 1);
									}else{ $paqFarm->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($paqFarm->SetResponse(false, 'No se guardo el paquete de farmacia')); }
								}

								$data = [ 'importe'=>$subtotal_salida, 'descuento'=>$subtotal_salida-$total_salida, 'total'=>$total_salida ];
								$edit_salida = $this->model->prod_salida->edit($data, $id_salida); if($edit_salida->response) {
									$totalPagos = 0;
									foreach($pagos as $infoPago) {
										$infoPago['visita_id'] = $id_visita; $infoPago['fecha'] = $infoPago['fecha'].date(' H:i:s'); $infoPago['fecha_registro'] = date('Y-m-d H:i:s');
										$pago = $this->model->pago->add($infoPago); if($pago->response) {
											$pago_id = $pago->result;
											$seg_log = $this->model->seg_log->add('Registro de pago', $pago_id, 'pago', 1);
											if(intval($infoPago['metodo']) == $_SESSION['metodo_pago_saldo_favor']) {
												$propietario_id = $infoPago['propietario_id'];
												$saldo = $this->model->saldo_favor->getSaldoByPropietario($propietario_id)->result;
												$monto = floatval($infoPago['cantidad']);
												$data_saldo = [ 'propietario_id'=>$propietario_id, 'fecha'=>date('Y-m-d H:i:s'), 'monto'=>$monto, 'tipo'=>-1, 'saldo'=>$saldo-$monto, 'visita_id'=>$id_visita ];
												$saldo = $this->model->saldo_favor->add($data_saldo); if(!$saldo->response) {
													$saldo->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($saldo); 
												}
											}
											if($rasurado) { $rasurado = in_array($infoPago['metodo'], [1]); }
											$totalPagos += floatval($infoPago['cantidad']);
										} else { $pago->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($pago->SetResponse(false, 'No se registro el pago')); }
									}
			
									$cambio = $totalPagos - $total_venta;
									if($cambio>=0 && $parsedBody['status']==2) {
										if(isset($accion_saldo)) {
											switch(intval($accion_saldo)) {
												case 1:
													$edit_venta = $this->model->venta->edit(['cambio' => $totalPagos-$total_venta], $id_venta); if($edit_venta->response) {
														$data_pago = [ 'cambio' => $cambio ];
													} else { $edit_venta->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($edit_venta->SetResponse(false, 'No se actualizo la información del cambio')); }
													break;

												default:
													$propietario_id = $parsedBody['propietario_id'];
													$saldo = $this->model->saldo_favor->getSaldoByPropietario($propietario_id)->result;
													$data_saldo = [ 'propietario_id'=>$propietario_id, 'fecha'=>$fecha, 'monto'=>$cambio, 'tipo'=>1, 'saldo'=>$saldo+$cambio, 'visita_id'=>$id_visita ];
													$saldo = $this->model->saldo_favor->add($data_saldo); if($saldo->response) {
														$data_pago = [ 'a_favor' => $cambio ];
													} else { $saldo->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($saldo); }
													break;
											}

											$pagos_visita = $this->model->pago->getByVisita($id_visita)->result;
											// $pagos_efectivo = array_filter($pagos_visita, function($pago) { return intval($pago->metodo) == 1; });
											// usort($pagos_efectivo, function($a, $b) { return $a->id < $b->id? -1: 1; });
											usort($pagos_visita, function($a, $b) { return $a->id < $b->id? -1: 1; });

											// $last_pago = end($pagos_efectivo);
											$last_pago = end($pagos_visita);
											if(is_object($last_pago)) {
												$edit_pago = $this->model->pago->edit($data_pago, $last_pago->id); if(!$edit_pago->response) {
													$edit_pago->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($edit_pago->SetResponse(false, 'No se registro el cambio/saldo a favor en el pago'));
												}
											} else { $edit_venta->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($edit_venta->SetResponse(false, 'No se encontro un pago en efectivo')); }
										}

										$finalizaVisita = true;
										$rasurado = $rasurado && intval($parsedBody['status']==2);
									} else { $rasurado = false; }
	
									$data = [ 'venta_id' => $id_venta, 'rasurado' => ($rasurado? 1: 0) ];
									$edit_visita = $this->model->visita->edit($data, $id_visita); if($edit_visita->response) {
										$seg_log = $this->model->seg_log->add('Alta nueva visita', $id_visita, 'visita'); if(!$seg_log->response) {
											$seg_log->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($seg_log);
										}else{
											// if($pushFarmacia) $this->model->farmacia->sendPush();
											if($finalizaVisita){
												$seg_log = $this->model->seg_log->add('Finaliza visita', $id_visita, 'visita');
											}
										}
									} else { $edit_visita->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($edit_visita->SetResponse(false, 'No se actualizo la información de la visita')); }
								} else { $edit_salida->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($edit_salida->SetResponse(false, 'No se actualizo el total de la salida')); }
							} else { $edit_venta->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($edit_venta->SetResponse(false, 'No se actualizó el total de la venta')); }
						} else { $salida->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($salida->SetResponse(false, 'No se agrego la salida')); }
					} else { $venta->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($venta->SetResponse(false, 'No se agrego la venta')); }
				} else { $edit_hospital->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($edit_hospital); }
			} else { $visita->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($visita->SetResponse(false, 'No se agrego la visita')); }

			/** LEAL API */
			if(intval($parsedBody['status']) == 2 && $parsedBody['propietario_id']!=$_SESSION['cliente_general'] && $parsedBody['propietario_id']!=$_SESSION['prop_farm']){
				//if($descuento_leal > 0){	/** LEAL REDENCION */
					/*$apiLeal = $app->getContainer()->get('router')->getNamedRoute("redimeLealApi");
					$apiLeal->setArgument("visita", $id_visita);
					$apiLeal->run($request, $response);*/
				//}else{						/** LEAL ACUMULACION */
					// $apiLeal = $app->getContainer()->get('router')->getNamedRoute("addLealApi");
					// $apiLeal->setArgument("visita", $id_visita);
					// $apiLeal->run($request, $response);
				//}
			}

			$visita->state = $this->model->transaction->confirmaTransaccion();
			return $response->withJson($visita);
		});

		$this->put('edit/{id}', function($request, $response, $arguments) use ($app) {
			$this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody();
			$idVisita = $arguments['id'];
			$colaborador_id = $this->model->colaborador->getByUsuario($_SESSION['usuario']->id)->result->id; $fecha = date('Y-m-d H:i:s');
			if(isset($parsedBody['accion_saldo'])) { $accion_saldo = $parsedBody['accion_saldo']; unset($parsedBody['accion_saldo']); }
			if(isset($parsedBody['facturar'])) { $facturar = $parsedBody['propietario_id'] == $_SESSION['prop_farm'] ? 0 : $parsedBody['facturar']; unset($parsedBody['facturar']); }
			$finalizada = false;
			if(intval($parsedBody['status']) == 2) { 
				$finalizada = true;
				$parsedBody['colaborador_termino_id'] = $colaborador_id; 
				$this->model->propietario->edit(array('primera' => 0), $parsedBody['propietario_id']);
				$parsedBody['fecha_termino'] = date('Y-m-d H:i:s');
			}

			unset($parsedBody['detalles']);
			unset($parsedBody['pagos']);
			unset($parsedBody['fecha_inicio']);
			$areTheSame = true; $visitaInfo = $this->model->visita->get($idVisita)->result; 
			$idVenta = $visitaInfo->venta_id; $ventaInfo = $this->model->venta->get($idVenta)->result;
			$detalles = $this->model->det_venta->getByVenta($idVenta)->result;
			$descuento_leal = 0;
			foreach($detalles as $detalle) { $descuento_leal += $detalle->descuento_leal; }
			foreach($parsedBody as $field => $value) { if($visitaInfo->$field != $value) { $areTheSame = false; break; } }
			$visita = $this->model->visita->edit($parsedBody, $idVisita);
			if($visita->response || $areTheSame) { $visita->areTheSame = $areTheSame;
				// $idVenta = $visitaInfo->venta_id; $ventaInfo = $this->model->venta->get($idVenta)->result;
				//$detalles = $this->model->det_venta->getByVenta($idVenta)->result;
				if(isset($facturar) && $facturar!=$ventaInfo->facturar) {					
					$iva_venta = 0; $total_venta = 0; $descuento_venta = 0; $sub = 0;			
					foreach($detalles as $detalle) {
						$productoInfo = $this->model->producto->get($detalle->producto_id)->result;
						if($productoInfo->iva == 3 && $facturar == 0){
							$detalle->precio = $productoInfo->precio;
							$detalle->subtotal = $productoInfo->precio * $detalle->cantidad;
						}else if($productoInfo->iva == 3 && $facturar == 1){
							$detalle->precio = ($productoInfo->precio / 1.16);
							$detalle->subtotal = ($productoInfo->precio / 1.16) * $detalle->cantidad;
						}
						$s = floatval($detalle->subtotal);
						$i = floatval($detalle->descuento_importe);
						$p = intval($detalle->descuento_porcentaje) / 100;
						$l = floatval($detalle->descuento_leal);
						$sub += $s; 

						$descuentoDetalle = floatval($detalle->descuento_porcentaje)!=0? ($s * (intval($detalle->descuento_porcentaje) / 100)): floatval($detalle->descuento_importe);
						$descuento_venta += floatval($descuentoDetalle);
						//$descuento_leal += $detalle->descuento_leal;

						$detalle->iva = 0; 
						// if($facturar && $productoInfo->iva==2) {
						if($facturar && ($detalle->tipo_iva==2 || $detalle->tipo_iva==3 || $detalle->servicio!=0)) {
						// if($facturar && ($productoInfo->iva==2 || $productoInfo->iva==3 || $detalle->servicio!=0)) {
							// $porc = in_array($detalle->producto_id, $_SESSION['prod_farm_paq'])? 0.7: 1;
							$porc = 1;
							// $detalle->iva = $s * 0.16 * $porc;
							$detalle->iva = ($s-$descuentoDetalle-$l) * 0.16 * $porc;
						}
						// $detalle->total = $s - $i - ($s * $p) + $detalle->iva;
						$detalle->total = floatval($s - $descuentoDetalle - $l + $detalle->iva);
						
						$data = ['iva'=>number_format($detalle->iva, 2,'.',''), 'total'=>number_format($detalle->total,2,'.',''), 'subtotal' => $s, 'precio' => $detalle->precio];
						// $data = ['iva'=>number_format($detalle->iva, 2), 'total'=>$detalle->total, 'subtotal' => $s, 'precio' => $detalle->precio];
						$edit_detalle = $this->model->det_venta->edit($data, $detalle->id); if($edit_detalle->response) {
							$iva_venta += $detalle->iva; $total_venta += $detalle->total;
						} else { $edit_detalle->data = $data; $edit_detalle->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($edit_detalle); }
						
					}

					// $data = ['facturar'=>$facturar, 'iva'=>$iva_venta, 'total'=>$total_venta-$descuento_venta-$descuento_leal];
					$data = ['subtotal'=>$sub, 'descuento' => ($descuento_venta+$descuento_leal), 'facturar'=>$facturar, 'iva'=>$iva_venta, 'total'=>$total_venta];
					$venta = $this->model->venta->edit($data, $idVenta); if(!$venta->response) {
						$venta->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($venta);
					}
				}

				$agregarSaldo = false; $favorExist = 0;
				$pagado = 0; $pagos = $this->model->pago->getByVisita($idVisita)->result; 
				foreach($pagos as $pago) { 
					if($pago->cambio > 0 || $pago->a_favor > 0) $agregarSaldo = true; 
					$favorExist += $pago->a_favor;
					$pagado += ($pago->cantidad - $pago->cambio - $pago->a_favor); 
				}
				if($agregarSaldo) $accion_saldo = 2;
				$cambio = round($pagado - floatval($ventaInfo->total), 2);
				if($cambio>=0 && $parsedBody['status']==2) {
					if(isset($accion_saldo)) {
						switch(intval($accion_saldo)) {
							case 1:
								$edit_venta = $this->model->venta->edit(['cambio' => $cambio], $idVenta); if($edit_venta->response || floatval($ventaInfo->cambio)==floatval($cambio)) {
									$saldo_favor = $this->model->saldo_favor->getByVisita($idVisita, 1); if($saldo_favor->response) {
										$del_saldo_favor = $this->model->saldo_favor->delByVisita($idVisita); if($del_saldo_favor->response) {
											$saldo_favor = $this->model->saldo_favor->getByPropietario($visitaInfo->propietario_id, 'ASC')->result;
											$acumulado = 0;
											foreach($saldo_favor as $saldo) {
												$tipo = $saldo->tipo; $acumulado += $tipo * $saldo->monto;
												$edit_saldo = $this->model->saldo_favor->edit(['saldo'=>$acumulado], $saldo->id); if(!$edit_saldo->response && $acumulado!=$saldo->saldo) {
													$del_saldo_favor->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($del_saldo_favor->SetResponse(false, 'No se cancelo el saldo a favor anterior'));
												}
											}
										} else { $del_saldo_favor->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($del_saldo_favor->SetResponse(false, 'No se cancelo el saldo a favor anterior')); }
									}
									$data_pago = [ 'cambio' => $cambio, 'a_favor' => 0 ];
								} else { $edit_venta->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($edit_venta->SetResponse(false, 'No se actualizo la información del cambio')); }
								break;

							default:
								$edit_venta = $this->model->venta->edit(['cambio' => $agregarSaldo ? $ventaInfo->cambio : 0], $idVenta); if($edit_venta->response || floatval($ventaInfo->cambio)==0 || $agregarSaldo) {
									$propietario_id = $this->model->visita->get($idVisita)->result->propietario_id;
									$saldo_visita = $this->model->saldo_favor->getByVisita($idVisita, 1); 
									$saldo = $this->model->saldo_favor->getSaldoByPropietario($propietario_id)->result;
									if(count($saldo_visita->result) == 0) {
										$data_saldo = [ 'propietario_id'=>$propietario_id, 'fecha'=>$fecha, 'monto'=>$cambio, 'tipo'=>1, 'saldo'=>$saldo+$cambio, 'visita_id'=>$idVisita ];
										$saldo = $this->model->saldo_favor->add($data_saldo); if(!$saldo->response) {
											$saldo->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($saldo);
										}
									}else{
										$dataSaldo = array('monto' => $cambio, 'saldo' => $saldo+$cambio);
										$this->model->saldo_favor->edit($dataSaldo, $saldo_visita->result[0]->id);
									}

								$data_pago = [ 'a_favor' => $cambio, /*'cambio' => 0*/ ];
								} else { $edit_venta->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($edit_venta->SetResponse(false, 'No se actualizo la información del cambio')); }

								break;
						}

						$pagos_visita = $this->model->pago->getByVisita($idVisita)->result;
						// $pagos_efectivo = array_filter($pagos_visita, function($pago) { return intval($pago->metodo) == 1; });
						// usort($pagos_efectivo, function($a, $b) { return $a->id < $b->id? -1: 1; });
						usort($pagos_visita, function($a, $b) { return $a->id < $b->id? -1: 1; });

						// $last_pago = end($pagos_efectivo);
						$last_pago = end($pagos_visita);
						if(is_object($last_pago) && isset($data_pago)) {
							$areTheSame = true;
							foreach($last_pago as $field => $value) { if(isset($data_pago[$field]) && $data_pago[$field]!=$value) { $areTheSame = false; break; } }
							$edit_cambio = $this->model->pago->edit($data_pago, $last_pago->id); if(!$edit_cambio->response && !$areTheSame) { $edit_cambio->areTheSame = $areTheSame;
								$edit_cambio->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($edit_cambio->SetResponse(false, 'No se registro el cambio/saldo a favor en el pago'));
							}
						} else { $edit_venta->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($edit_venta->SetResponse(false, 'No se encontro un pago en efectivo')); }
					}
				}

				if($finalizada){
					foreach ($detalles as $det) {
						if(in_array($det->producto_id, $_SESSION['prod_farm_paq'])){
							$visitaFarmacia = $this->model->farmacia->findByDetOri($det->id);
							if($visitaFarmacia > 0){
								$this->model->visita->edit(array('status' => 2), $visitaFarmacia);
							}
						}
					}
					$seg_log = $this->model->seg_log->add('Finaliza visita', $idVisita, 'visita');
				}

				if(!$visita->areTheSame) {
					$seg_log = $this->model->seg_log->add('Actualización información visita', $idVisita, 'visita'); if(!$seg_log->response) {
						$seg_log->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($seg_log);
					}
				}
				$this->model->visita->cerrarVisita($idVisita);

				$visita->SetResponse(true);
			} else { 
				$visita->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($visita); 
			}

			if(intval($parsedBody['status']) == 2 && $parsedBody['propietario_id']!=$_SESSION['cliente_general'] && $parsedBody['propietario_id']!=$_SESSION['prop_farm']){
				//if($descuento_leal > 0){	/** LEAL REDENCION */
					/*$apiLeal = $app->getContainer()->get('router')->getNamedRoute("redimeLealApi");
					$apiLeal->setArgument("visita", $id_visita);
					$apiLeal->run($request, $response);*/
				//}else{						/** LEAL ACUMULACION */
					// $apiLeal = $app->getContainer()->get('router')->getNamedRoute("addLealApi");
					// $apiLeal->setArgument("visita", $idVisita);
					// $apiLeal->run($request, $response);
				//}
			}

			$visita->state = $this->model->transaction->confirmaTransaccion();
			return $response->withJson($visita);
		});

		/*** Ruta para dar de baja un det_paquete ***/
		$this->put('del/{id}', function($request, $response, $arguments) {
			$this->model->transaction->iniciaTransaccion();

			$visita = $this->model->visita->del($arguments['id']); if($visita->response) {
				$seg_log = $this->model->seg_log->add('Cancelación visita', $arguments['id'], 'visita', 1); if(!$seg_log->response) {
					$seg_log->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($seg_log);
				}
			} else { $visita->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($visita); }

			$visita->state = $this->model->transaction->confirmaTransaccion();
			return $response->withJson($visita);
		});

		$this->put('cancel/{id}[/{motivo}]', function($request, $response, $arguments) use ($app) {
			$this->model->transaction->iniciaTransaccion();
			$idVisita = $arguments['id']; $motivo = $arguments['motivo']; $infoVisita = $this->model->visita->get($idVisita)->result;
			$infoVenta = $this->model->venta->get($infoVisita->venta_id)->result; $idVenta = $infoVenta->id;
			$detVenta = $this->model->det_venta->getByVenta($idVenta)->result;
			$infoSalida = $this->model->prod_salida->getByVenta($idVenta)->result; $idSalida = $infoSalida->id;
			$detSalida = $this->model->det_prod_salida->getBySalida($idSalida)->result;
			$colaborador_id = $this->model->colaborador->getByUsuario($_SESSION['usuario']->id)->result->id;

			$cancel_visita = $this->model->visita->del($idVisita, $motivo); if($cancel_visita->response) {
				$cancel_venta = $this->model->venta->del($idVenta); if($cancel_venta->response) {
					$pushFarmacia = false;
					foreach($detVenta as $detalle) {
						if(in_array($detalle->tipo, [4,5,6,7]) || in_array($detalle->categoria_id, [5,6])){
							$farm = $this->model->farmacia->getByVenta($detalle->detVentaId);
							if(count($farm) > 0) {
								$farm = $farm[0];
								$this->model->farmacia->delByDetVenta($detalle->detVentaId);
								if($farm->status == 2){
									$dataDev = array(
										'producto_id' => $farm->producto_id, 
										'propietario_id' => $farm->propietario_id, 
										'mascota_id' => $farm->mascota_id, 
										'det_venta_id' => $farm->det_venta_id, 
										'tipo' => 2, 
										'fecha' => new Literal('NOW()'), 
										'usuario_solicita' => $_SESSION['colaborador']->id, 
										'cantidad' => $farm->cantidad, 
										'origen_id' => $farm->origen_id, 
										'origen_tipo' => $farm->origen_tipo, 
									);
									$farmacia = $this->model->farmacia->add($dataDev);
									$pushFarmacia = true;
									if(!$farmacia->response){
										$farmacia->state = $this->model->transaction->regresaTransaccion(); 
										return $response->withJson($farmacia->SetResponse(false, "No se registro la devolución de producto "));
									}
								}
							}
						}
						if(in_array($detalle->producto_id, $_SESSION['prod_farm_paq'])) {
							$this->model->farmacia->delPaqByVenta($detalle->id);
						}

						$delVacunas = $this->model->vacuna->delByDetVenta($detalle->detVentaId);
					}

					$importe = $infoSalida->importe; $descuento = $infoSalida->descuento; $subtotal = $importe - $descuento; $fecha = date('Y-m-d H:i:s');
					$data = [ 'colaborador_id'=>$colaborador_id, 'venta_id'=>$idVenta, 'folio'=>$this->model->prod_entrada->getSiguienteFolio(), 'importe'=>$importe, 'descuento'=>$descuento, 'subtotal'=>$subtotal, 'iva'=>0, 'total'=>$subtotal, 'fecha'=>$fecha ];
					$entrada = $this->model->prod_entrada->add($data); if($entrada->response) { $idEntrada = $entrada->result;
						foreach($detSalida as $detalle) { $producto_id = $detalle->producto_id; $cantidad = $detalle->cantidad;
							$data = [ 'prod_entrada_id'=>$idEntrada, 'producto_id'=>$producto_id, 'cantidad'=>$cantidad, 'costo'=>$detalle->precio, 'importe'=>$detalle->importe, 'descuento'=>$detalle->descuento_importe, 'total'=>$detalle->total ];
							$det_entrada = $this->model->det_prod_entrada->add($data); if($det_entrada->response) {
								$stock=$this->model->prod_stock->getByProducto($producto_id)->result; if(count($stock) > 0) { $tipo = 1; $inicial = $stock[0]->final;
									$data = [ 'producto_id'=>$producto_id, 'tipo'=>$tipo, 'inicial'=>$inicial, 'cantidad'=>$cantidad, 'final'=>$inicial + ($tipo * $cantidad), 'fecha'=>$fecha, 'colaborador_id'=>$colaborador_id, 'origen'=>$idEntrada, 'origen_tipo'=>8 ];
									$prod_stock = $this->model->prod_stock->add($data); if($prod_stock->response) {
										$edit_producto = $this->model->producto->edit(['stock' => $data['final']], $producto_id); if(!$edit_producto->response) {
											$edit_producto->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($edit_producto);
										}
									} else { $prod_stock->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($prod_stock); }
								}
							} else { $det_entrada->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($det_entrada); }
						}

						$seg_log = $this->model->seg_log->add('Cancelación visita', $arguments['id'], 'visita', 1); if(!$seg_log->response) {
							$seg_log->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($seg_log);
						}
						// if($pushFarmacia) $this->model->farmacia->sendPush(2);
					} else { $entrada->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($entrada); }
				} else { $cancel_venta->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($cancel_venta); }
			} else { $cancel_visita->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($cancel_visita); }

			/** LEAL ANULACION DE ACUMULACION POR CANCELACION */
			if($infoVisita->propietario_id!=$_SESSION['cliente_general'] && $infoVisita->propietario_id!=$_SESSION['prop_farm']){
				$apiLeal = $app->getContainer()->get('router')->getNamedRoute("delLealApi");
				$apiLeal->setArgument("visita", $idVisita);
				$apiLeal->run($request, $response);
			}

			$cancel_visita->state = $this->model->transaction->confirmaTransaccion();
			return $response->withJson($cancel_visita);
		});

		$this->put('abrirVisita/{id}', function($request, $response, $arguments) {
			$this->model->transaction->iniciaTransaccion();
			$resultado = new Response();

			$visita_colaborador = $this->model->visita->get($arguments['id'])->result->colaborador_id;
			if(intval($visita_colaborador) == 0){
				$colaborador_id = $this->model->colaborador->getByUsuario($_SESSION['usuario']->id)->result->id;
				$edit_visita = $this->model->visita->edit([ 'colaborador_id'=>$colaborador_id ], $arguments['id']); if($edit_visita->response || $visita_colaborador==$colaborador_id) {
					$seg_log = $this->model->seg_log->add('Abrir visita', $arguments['id'], 'visita'); if(!$seg_log->response) {
						$seg_log->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($seg_log);
					}
	
					$edit_visita->SetResponse(true);
				}
				$edit_visita->state = $this->model->transaction->confirmaTransaccion();
			}else{
				$this->model->transaction->regresaTransaccion(); 
				return $response->withJson($resultado->SetResponse(false));
			}
			return $response->withJson($edit_visita);
		});

		$this->put('cerrarVisita/{id}', function($request, $response, $arguments) {
			$this->model->transaction->iniciaTransaccion();

			$edit_visita = $this->model->visita->cerrarVisita($arguments['id']); if($edit_visita->response) {
				$seg_log = $this->model->seg_log->add('Cerrar visita', $arguments['id'], 'visita'); if(!$seg_log->response) {
					$seg_log->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($seg_log);
				}
			} else { $edit_visita->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($edit_visita); }

			$edit_visita->state = $this->model->transaction->confirmaTransaccion();
			return $response->withJson($edit_visita);
		});

		$this->put('unlockVisita/{id}', function($request, $response, $arguments) {
			$this->model->transaction->iniciaTransaccion();

			$edit_visita = $this->model->visita->cerrarVisita($arguments['id']); if($edit_visita->response) {
				$seg_log = $this->model->seg_log->add('Desbloquea visita', $arguments['id'], 'visita'); if(!$seg_log->response) {
					$seg_log->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($seg_log);
				}
			} else { $edit_visita->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($edit_visita); }

			$edit_visita->state = $this->model->transaction->confirmaTransaccion();
			return $response->withJson($edit_visita);
		});

		$this->get('getVisitaEstetica/{visita}/{pet}', function($request, $response, $arguments){
			$conceptos = $this->model->visita->getVisitaEstetica($arguments['visita'], $arguments['pet']);

			return $response->withJson($conceptos);
		});

		$this->post('setColaborador', function ($req, $res, $args) {
			$parsedBody = $req->getParsedBody();
			$data[$parsedBody['name']] = $parsedBody['value'];
			$data['colaborador_asigno_id'] = $_SESSION['colaborador']->id;

			$resultado = $this->model->det_venta->edit($data,$parsedBody['pk']);

	        if($resultado->result > 0){
				$data = array('status' => 3);
				$resultado = $this->model->mascota->updateExtra($data, $parsedBody['pk']);
				$this->model->seg_log->add('Asigna Colaborador Estetica', $parsedBody['pk'], 'det_venta');
			}
	        return $res->withHeader('Content-type', 'application/json')
	                   ->write(json_encode($resultado));
		});

		$this->get('getFinalizada/{comp}', function ($request, $response, $arguments) {
			$visita = $this->model->visita->getBy('comprobante', $arguments['comp']);
			$res = array('success' => false);
			if(is_object($visita)){
				$res['id'] = $visita->idVisita;

				$pagos = $this->model->pago->getByVisita($visita->idVisita)->result;
				$pagosTot = 0;
				foreach ($pagos as $pago) {
					$pagosTot += ($pago->cantidad - $pago->cambio);
				}
				$pagosTot = number_format($pagosTot, 2,'.','');
				$visita->total = number_format($visita->total, 2,'.','');
				if($pagosTot > $visita->total){
					$res['message'] = 'Esta visita no se puede finalizar porque tiene saldo a favor';
				}else{
					$devoluciones = $this->model->devolucion->getByVenta($visita->idVisita)->total;
					if($devoluciones > 0){
						$res['message'] = 'Esta visita no se puede finalizar porque tiene devoluciones';
					}else{
						$res['fecha'] = $visita->fecha_inicio;
						$res['comprobante'] = $visita->comprobante;
						$prop = $this->model->propietario->get($visita->propietario_id)->result;
						$res['propietario'] = $prop->nombre.' '.$prop->apellidos;
						$res['success'] = true;
					}
				}
			}else{
				$res['message'] = 'No existe visita con ese número de comprobante';
			}

			echo json_encode($res);
			exit(0);
			//return $response->withJson($visita);
		});

		$this->put('free/{id}', function($request, $response, $arguments) use ($app) {
			$this->model->transaction->iniciaTransaccion();

			$edit_visita = $this->model->visita->freeVisita($arguments['id']); if($edit_visita->response) {
				$seg_log = $this->model->seg_log->add('Desfinaliza visita', $arguments['id'], 'visita'); if(!$seg_log->response) {
					$seg_log->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($seg_log);
				}
			} else { $edit_visita->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($edit_visita); }

			/** LEAL ANULACION DE ACUMULACION POR CANCELACION */
			$idVisita = $arguments['id']; $infoVisita = $this->model->visita->get($idVisita)->result;
			if($infoVisita->propietario_id!=$_SESSION['cliente_general'] && $infoVisita->propietario_id!=$_SESSION['prop_farm']){
				$apiLeal = $app->getContainer()->get('router')->getNamedRoute("delLealApi");
				$apiLeal->setArgument("visita", $idVisita);
				$apiLeal->run($request, $response);
			}

			$edit_visita->state = $this->model->transaction->confirmaTransaccion();
			return $response->withJson($edit_visita);
		});

		$this->get('getAllDataTables/{inicial}/{limite}/{desde}/{hasta}/{busqueda}', function($request, $response, $arguments) {
			$inicial = isset($_GET['start'])? $_GET['start']: $arguments['inicial'];
			$limite = isset($_GET['length'])? $_GET['length']: $arguments['limite']; $limite = $limite>0? $limite: 10;
			$busqueda = isset($_GET['search']['value'])? (strlen($_GET['search']['value'])>0? $_GET['search']['value']: '_'): $arguments['busqueda'];
			$orden = isset($_GET['order'])? $_GET['columns'][$_GET['order'][0]['column']]['data']: 'nombre';
			$orden .= isset($_GET['order'])? " ".$_GET['order'][0]['dir']: " asc";
			$desde = $arguments['desde'];
			$hasta = $arguments['hasta'];
			
			if(count($_GET['order'])>1){
				for ($i=1; $i < count($_GET['order']); $i++) { 
					$orden .= ', '.$_GET['columns'][$_GET['order'][$i]['column']]['data'].' '.$_GET['order'][$i]['dir'];
				}
			}

			$visitas = $this->model->visita->getAllDataTables($inicial, $limite, $desde, $hasta, $busqueda, $orden);

			$data = [];
			if(!isset($_SESSION)) { session_start(); }
			foreach($visitas->result as $visita) {
				$status = $visita->status == 1;
				$data[] = array(
					"fecha_inicio" => "<small class=\"fecha_inicio\">".date('d/m/Y', strtotime($visita->fecha_inicio))."</small>",
					"fecha_termino" => "<small class=\"fecha_termino\">".($status? '': date('d/m/Y H:i:s', strtotime($visita->fecha_termino)))."</small>",
					"comprobante" => "<small class=\"comprobante\"><a class=\"text-info\" href=\"". URL_ROOT ."/comprobante/". md5($visita->id) ."\" target=\"_BLANK\"> $visita->comprobante</a></small>",
					"ticket" => "<small class=\"ticket\"><a class=\"text-info\" href=\"". URL_ROOT."/ticket/".md5($visita->id) ."\" target=\"_BLANK\">Ver Ticket</a></small>",
					"colaborador" => "<small class=\"colaborador\" data-id=\"$visita->colaborador_id\">". ucwords(strtolower($visita->colaborador)) ."</small>",
					"propietario" => "<small class=\"propietario\" data-id=\"$visita->propietario_id\"><a class=\"text-info\" href=\"". URL_ROOT."/propietario/".md5($visita->propietario_id) ."\">". ucwords(strtolower($visita->propietario)) ."</a></small>",
					"subtotal" => "<small class=\"subtotal\">". number_format($visita->subtotal, 2) ."</small>",
					"descuento" => "<small class=\"descuento\">". number_format($visita->descuento, 2) ."</small>",
					"total" => "<small class=\"total\">". number_format($visita->total, 2) ."</small>",
					"pagado" => "<small class=\"pagado\">". number_format($visita->pagado, 2) ."</small>",
					"saldo" => "<small class=\"saldo\">". number_format(floatval($visita->total)-floatval($visita->pagado), 2) ."</small>",
					"observaciones" => "<small class=\"observaciones\">$visita->observaciones</small>",
					"estatus" => "<small class=\"estatus label label-". ($status? "warning": "success") ."\">". ($status? "Sin finalizar": "Finalizada") ."</small>",
					"data_id" => $visita->id,
				);
			}

			echo json_encode(array(
				'draw' => $_GET['draw'],
				'data' => $data,
				'recordsTotal' => $visitas->total,
				'recordsFiltered' => $visitas->filtered,
			));
			exit(0);
		});

		$this->get('export/{format}/{inicial}/{final}/{busqueda}', function($request, $response, $arguments) {
			$visitas = $this->model->visita->getVisits($arguments['inicial'], $arguments['final'], $arguments['busqueda'])->result;
			if($arguments['format'] == 'xlsx') {
				return $this->rpt_renderer->render($response, 'xlsx_visitas.phtml', ['visitas'=>$visitas]);
			} elseif($arguments['format'] == 'pdf') {
				return $this->rpt_renderer->render($response, 'pdf_visitas.phtml', ['visitas'=>$visitas, 'vista'=>'rpt visitas']);
			}
		});

		$this->put('addPresupuestoToVisit/{presupuesto_id}/{visita_id}', function($request, $response, $arguments){
			$this->model->transaction->iniciaTransaccion();
			$presupuesto_id = $arguments['presupuesto_id'];
			$visita_id = $arguments['visita_id'];
			$presupuesto = $this->model->presupuesto->get($presupuesto_id)->result;
			$det_presupuesto = $this->model->det_presupuesto->getByPresupuesto($presupuesto->id)->result;
			$visita = $this->model->visita->get($visita_id)->result;
			$venta = $this->model->venta->get($visita->venta_id)->result;
			$det_venta = $this->model->det_venta->getByVenta($venta->id)->result;
			$pushFarmacia = false;
			$colaborador_asigna = $this->model->colaborador->getByUsuario($_SESSION['usuario']->id)->result->id;
			$colaborador_id = $presupuesto->colaborador_id;
			$propietario_id = $presupuesto->propietario_id;
			$fecha_agrega_a_presupuesto = $presupuesto->fecha;
			$fecha_agrega_a_visita = date('Y/m/d H:i:s');
			$fecha = date('Y-m-d H:i:s');
			
			foreach($det_presupuesto as $detalle){
				$mascota_id = $detalle->mascota_id;
				$producto_id = $detalle->producto_id;
				$precio = $detalle->precio;
				$descuento = $presupuesto->descuento;
				$cantidadDet = $detalle->cantidad;
				$subtotal = $detalle->subtotal;
				$descuento_importe = $detalle->descuento_importe;
				$descuento_porcentaje = $detalle->descuento_porcentaje;
				$total = $detalle->total;
				$salida_data = [ 'colaborador_id'=>$colaborador_asigna, 'propietario_id'=>$propietario_id, 'venta_id'=>$visita->venta_id, 'folio'=>$this->model->prod_salida->getSiguienteFolio(), 'fecha'=>$fecha_agrega_a_visita, ];
				$salida = $this->model->prod_salida->add($salida_data); 
				$SUBTOTAL = 0; $DESCUENTO = 0; $TOTAL = 0;
				if($salida->response) {
					$salida_id = $salida->result;
					$prodInfo = $this->model->producto->get($detalle->producto_id)->result;
					if(in_array($prodInfo->tipo, [4,5,6,7]) && ($presupuesto->propietario_id==$_SESSION['cliente_general'] || isset($detalle->mascota_id))) { $data_det_venta['surtido'] = 0; }

					$data_det_venta = [
						'venta_id'=>$venta->id, 
						'colaborador_id' => $colaborador_id,
						'propietario_id' => $propietario_id,
						'mascota_id'     => $mascota_id,
						'producto_id'    => $producto_id,
						'fecha'          => $fecha_agrega_a_presupuesto,
						'precio'         => floatval($precio),
						'cantidad'       => floatval($cantidadDet),
						'subtotal'       => floatval($subtotal),
						'descuento_importe' => floatval($descuento_importe),
						'descuento_porcentaje' => $descuento_porcentaje,
						'descuento_motivo' => $detalle->descuento_motivo,
						'total' => $total,
						'colaborador_asigno_id' => $colaborador_asigna,
						'fecha_asigno' => $fecha_agrega_a_visita,
						'status' => 1,
					];
					$edit_det_venta = $this->model->det_venta->add($data_det_venta);
					if($edit_det_venta->response){  $det_venta_id = $edit_det_venta->result;
						$producto_data = $this->model->producto->get($producto_id)->result;
						if($producto_data->es_paquete) {
							if(($producto_id == $_SESSION['paquete_vacunas_perro'] || $producto_id == $_SESSION['paquete_vacunas_gato']) && $producto_data->paquete_vacunas == 1){
								$productos = $this->model->det_paquete->getByPaquete($producto_id)->result;  //$producto_id = 1936											
								$primeraVacuna = $productos[0]; //es la primera vacuna que se pone del paquete
								$primeraVacunaId = $primeraVacuna->producto_id; //es el id de la primera vacuna que se pone del paquete (1264)
								$segundaVacunaId = $productos[1]->producto_id;
								$contador = 0;
								foreach($productos as $vacuna){
									if($vacuna->categoria_id == 5){
										$sigAplicacion = $vacuna->periodo_aplicacion;
										$fechaActual = strtotime(date('Y-m-d'));
										switch ($sigAplicacion) {
											case '1': $sigAplicacion = null; break;
											case '2': $sigAplicacion = date('Y-m-d', strtotime('+6 month', $fechaActual)); break;
											case '3': $sigAplicacion = date('Y-m-d', strtotime('+1 year', $fechaActual)); break;
											default : $sigAplicacion = null; break;
										}
										if($producto_id == $_SESSION['paquete_vacunas_perro']){ $proxima = date('Y-m-d', strtotime('+15 days', $fechaActual)); }
										else{ $proxima = date('Y-m-d', strtotime('+21 days', $fechaActual)); }
										$dataVacuna = array(
											'mascota_id' => $detalle->mascota_id, 
											'masc_medicion_id' => null,  
											'det_venta_id' => $det_venta_id, 
											'tipo' => $vacuna->categoria_id, 
											'descripcion' => $vacuna->nombre,
											'producto_paquete_id' => $producto_id,
										);
										
										if($vacuna->producto_id == $primeraVacunaId){
											if($contador == 0){
												$dataVacuna['producto_id'] = $primeraVacunaId;
												$dataVacuna['aplicacion'] = $fecha;
												$dataVacuna['siguiente'] = $sigAplicacion;
												$dataVacuna['lote'] = '';
												$dataVacuna['observaciones'] = '';
												$dataVacuna['status'] = 2;
											}else{
												$dataVacuna['producto_id'] = $vacuna->producto_id;
												$dataVacuna['prox_vacuna_paquete'] = $proxima;
												$dataVacuna['status'] = 1;
											}
										}else if($vacuna->producto_id == $segundaVacunaId){
											$dataVacuna['producto_id'] = $vacuna->producto_id;
											$dataVacuna['prox_vacuna_paquete'] = $proxima;
											$dataVacuna['status'] = 1;
										}else{
											$dataVacuna['producto_id'] = $vacuna->producto_id;
											$dataVacuna['status'] = 1;
										}													
										$addVacuna = $this->model->vacuna->add($dataVacuna);
										if(!$addVacuna->response){
											$addVacuna->state = $this->model->transaction->regresaTransaccion(); 
											return $response->withjson($addVacuna);
										}
									}
									$contador++;
								}

								$dataPaquete = [ 
									'prod_salida_id' => $salida_id, 
									'producto_id' => $producto_id, 
									'cantidad' => floatval($detalle->cantidad), 
									'precio' => $detalle->precio, 
									'importe' => $detalle->cantidad * $detalle->precio, 
									'descuento_importe' => $detalle->descuento_importe, 
									'descuento_motivo' => $detalle->descuento_motivo, 
									'total' => $detalle->total 
								];
								$det_salida = $this->model->det_prod_salida->add($dataPaquete); 

								//$paqueteId es el id de producto_paquete_id, ya que necesitamos el id del paquete de aplicación de la vacuna para traer los insumos 
								//que se necesitan en la aplicación de vacuna
								$paqueteId = $this->model->det_paquete->getByProdPaq($primeraVacunaId, $producto_id)->result->producto_paquete_id; //1109
								$prodsAplicacionVacuna = $this->model->det_paquete->getByPaquete($paqueteId)->result; //productos que incluye la aplicación de la vacuna

								foreach($prodsAplicacionVacuna as $prodAplicacion) { 
									$productoId = $prodAplicacion->producto_id;
									$cantidad = floatval($detalle->cantidad) * $prodAplicacion->cantidad;
									$infoProducto = $this->model->producto->get($productoId); 
									if($infoProducto->result->stock == null || $infoProducto->result->stock >= $cantidad) { 
										$infoProducto = $infoProducto->result;
										$precio = $infoProducto->precio; 
										$importe = $cantidad * $precio; 
										// $descuento = floatval($prodAplicacion->descuento_porcentaje)!=0? ($importe * (intval($prodAplicacion->descuento_porcentaje) / 100)) : floatval($prodAplicacion->descuento_importe); 
										$descuento = $importe; 
										$total = $importe - $descuento;
										$data = [ 
											'prod_salida_id' => $salida_id, 
											'producto_id' => $prodAplicacion->producto_id, 
											'cantidad' => $cantidad, 
											'precio' => $precio, 
											'importe' => $importe, 
											'descuento_importe' => $descuento, 
											'descuento_motivo' => '', 
											'total' => $total 
										];
										$det_salida = $this->model->det_prod_salida->add($data); 
										if($det_salida->response) { 
											// $subtotal_salida += $importe; $total_salida += $total;
											$subtotal_salida = floatval($detalle->subtotal); $total_salida = floatval($detalle->total);
											$stock = $this->model->prod_stock->getByProducto($productoId)->result; 
											if(count($stock) > 1 || ($infoProducto->stock != null && floatval($infoProducto->stock) > 0)) { 
												$tipo=-1;
												if(count($stock) == 0) {
													$data = [ 
														'producto_id' => $productoId, 
														'tipo' => 1, 
														'inicial' => 0, 
														'cantidad' => $infoProducto->stock, 
														'final' => $infoProducto->stock, 
														'fecha' => $fecha, 
														'colaborador_id' => $colaborador_id, 
														'origen' => 0, 
														'origen_tipo' => 1, 
														'status' => 1 
													];
													$stock_inicial = $this->model->prod_stock->add($data); 
													if($stock_inicial->response) { $inicial = $infoProducto->stock; }
													else { 
														$stock_inicial->state = $this->model->transaction->regresaTransaccion(); 
														return $response->withJson($stock_inicial->SetResponse(false, 'No se agrego el registro de stock inicial, el cual no existía anteriormente')); 
													}
												} else {
													$inicial = $stock[0]->final;
												}
												$data = [ 
													'producto_id'=>$productoId, 
													'tipo'=>$tipo, 
													'inicial'=>$inicial, 
													'cantidad'=>$cantidad, 
													'final'=>$inicial+($tipo*$cantidad), 
													'fecha'=>$fecha, 
													'colaborador_id'=>$colaborador_id, 
													'origen'=>$salida_id, 
													'origen_tipo'=>7,
													'det_venta_id' => $det_venta_id 
												];
												$prod_stock = $this->model->prod_stock->add($data); 
												if($prod_stock->response) {
													$edit_producto = $this->model->producto->edit(['stock'=>$data['final']], $productoId); 
													if($edit_producto->response) {
														// Solicitar a farmacia
														if(in_array($infoProducto->tipo, [4,5,6,7]) && ($propietario_id == $_SESSION['cliente_general'] || isset($detalle->mascota_id))){
															$this->model->det_venta->edit(array('surtido' => 0), $det_venta_id);
															$dataFarmacia = array(
																'producto_id' => $infoProducto->id, 
																'propietario_id' => $propietario_id,
																'mascota_id' => $propietario_id != $_SESSION['cliente_general'] ? $detalle->mascota_id : $_SESSION['mascota_cliente_general'],
																'det_venta_id' => $det_venta_id,
																'fecha' => $fecha,
																'origen_tipo' => 2, 
																'origen_id' => $visita_id, 
																'usuario_solicita' => $_SESSION['colaborador']->id, 
																'cantidad' => $cantidad,
															);
															$this->model->farmacia->add($dataFarmacia);
															$pushFarmacia = true;
														}
													} else {
														$edit_producto->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($edit_producto);
													}
												} else { $prod_stock->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($prod_stock); }
											}

											if(intval($infoProducto->categoria_id) == 5 && isset($detalle->mascota_id)){
												$idMedicion = null;
												if(isset($extra['peso'])){
													$this->model->seg_log->add('Entro a peso', $extra['tipo'], 'peso', 0);
													$dataMedicion = array(
														'mascota_id' => $detalle->mascota_id, 
														'fecha' => $fecha, 
														'peso' => '', 
														'temperatura' => '', 
														'longitud' =>'', 
														'altura' => '', 
														'frecuencia_cardiaca' => '', 
														'frecuencia_respiratoria' => '', 
														'origen' => 'Concepto Visita'
													);
													$medi = $this->model->mascota->addMedicion($dataMedicion);
													$idMedicion = $medi->result;
												}
											}
										} else { $det_salida->state = $this->model->transaction->regresaTransaccion(); return $response->withjson($det_salida); }
									} else { $infoProducto->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($infoProducto->SetResponse(false, "NO hay suficiente stock del producto: $productoId, se requieren $cantidad unidades.")); }
								}	
							}else{
								$productos = $this->model->det_paquete->getByPaquete($producto_id)->result;
								foreach($productos as $prod) { 
									$prod_data = $this->model->producto->get($prod->producto_id)->result; $prod_id = $prod_data->id;
									if($prod_data->tipo == 5){
										$cantidad = $prod->cantidad;
									}else{
										$cantidad = $cantidadDet * $prod->cantidad; 
									}
									$precio = $prod_data->precio; $importe = $cantidad * $precio; $descuento = 0; $total = $importe - $descuento;
									$paquete_data = $this->model->producto->get($prod->producto_paquete_id)->result;
									if($prod_data->stock==null || $prod_data->stock>=$cantidad) {
										$det_salida_data = [ 'prod_salida_id'=>$salida_id, 'producto_id'=>$prod_id, 'cantidad'=>$cantidad, 'precio'=>$precio, 'importe'=>$importe, 'descuento_importe'=>$descuento, 'descuento_motivo'=>'', 'total'=>$total, ];
										$det_salida = $this->model->det_prod_salida->add($det_salida_data); if($det_salida->response) {
											$SUBTOTAL += $importe; $DESCUENTO += $descuento; $TOTAL += $total;
											$stock = $this->model->prod_stock->getByProducto($prod_id)->result; if($prod_data->stock != null) {
												$tipo = -1; $inicial = $prod_data->stock;
												if(count($stock) == 0) {
													$stock_inicial_data = [ 'producto_id'=>$prod_id, 'tipo'=>$tipo, 'inicial'=>0, 'cantidad'=>$inicial, 'final'=>$inicial, 'fecha'=>$fecha_agrega_a_visita, 'colaborador_id'=>$colaborador_id, 'origen'=>0, 'origen_tipo'=>3 ];
													$stock_inicial = $this->model->prod_stock->add($stock_inicial_data); if(!$stock_inicial->response) {
														$stock_inicial->data = $stock_inicial_data; $stock_inicial->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($stock_inicial);
													}
												}

												$stock_final_data = [ 'producto_id'=>$prod_id, 'tipo'=>$tipo, 'inicial'=>$inicial, 'cantidad'=>$cantidad, 'final'=>$inicial + ($tipo * $cantidad), 'fecha'=>$fecha_agrega_a_visita, 'colaborador_id'=>$colaborador_id, 'origen'=>$salida_id, 'origen_tipo'=>16, 'det_venta_id' => $det_venta_id ];
												$stock_final = $this->model->prod_stock->add($stock_final_data); if($stock_final->response) {
													$producto = $this->model->producto->edit([ 'stock'=>$stock_final_data['final'] ], $prod_id); if($producto->response) {
														if(in_array($prod_data->tipo, [4,5,6,7]) && ($propietario_id==$_SESSION['cliente_general'] || isset($mascota_id))){
															$farmacia_data = [ 'producto_id'=>$prod_id, 'propietario_id'=>$propietario_id, 'det_venta_id'=>$det_venta_id, 'fecha'=>$fecha_agrega_a_visita, 'origen_tipo'=>2, 'origen_id'=>$visita_id, 'usuario_solicita'=>$colaborador_id, 'cantidad'=>$cantidad ];
															if($propietario_id!=$_SESSION['cliente_general'] && isset($mascota_id)) { $farmacia_data['mascota_id'] = $mascota_id; }
															else { $farmacia_data['mascota_id'] = $_SESSION['mascota_cliente_general']; }
															$farmacia = $this->model->farmacia->add($farmacia_data); if($farmacia->response) {
																if(in_array($prod_data->tipo, [4,5,6,7])){
																	$pushFarmacia = true;
							
																	if($prod_data->uso_controlado == 1){
																		$dataControlado = array(
																			'producto_id'=>$prod_id, 
																			'propietario_id'=>$propietario_id, 
																			'mascota_id'=>$farmacia_data['mascota_id'], 
																			'origen_id'=>$visita->id, 
																			'origen_tipo'=>2, 
																			'det_venta_id'=>$det_venta_id, 
																			'cantidad'=>$cantidad, 
																			'dias'=>$detalle->dias, 
																			'surtidos'=>$detalle->surtidos, 
																			'restan'=>$detalle->dias - $detalle->surtidos, 
																			'completo'=>$detalle->dias == $detalle->surtidos ? 1 : 0, 
																			'siguiente'=>date('Y-m-d', strtotime($fecha_agrega_a_presupuesto.' +'.($detalle->dias+1).' days')), 
																			'status'=>2,
																		);
																		$controlado = $this->model->farmacia->addControlado($dataControlado); $controlado->data = $dataControlado; if(!$controlado->response){ 
																			$controlado->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($controlado); 
																		}
																	}
																}
															} else {
																$farmacia->data = $farmacia_data; $farmacia->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($farmacia);
															}
														}
													} else { $producto->data = [ 'stock'=>$stock_final_data['final'] ]; $producto->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($producto); }
												} else { $det_salida->data = $det_salida_data; $det_salida->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($det_salida); }
											}

											if(!in_array(intval($prod_data->categoria_id), [9, 10, 20, 21]) && isset($detalle->mascota_id) && isset($detalle->extra)) {
												$extra = $detalle->extra;
												$extra['tipo'] = $prod_data->categoria_id;
												$extra['mascota_id'] = $detalle->mascota_id;
												$extra['visita_id'] = $visita_id;
												$extra['det_venta_id'] = $det_venta_id;
												if(isset($extra['categoria'])) { unset($extra['categoria']); }
												if(isset($extra['recordatorio'])) { unset($extra['recordatorio']); }
												if(intval($prod_data->categoria_id) != 5 && intval($prod_data->categoria_id) != 6){
													$belleza = $this->model->mascota->addBelleza($extra); 
													if(!$belleza->response) {
														$belleza->data = $belleza_data; $belleza->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($belleza);
													}
												}
												// if($belleza->response) {
													$medicion_id = null;
													if(isset($extra['peso'])) {
														$medicion_data = [ 'mascota_id'=>$detalle->mascota_id, 'fecha'=>$fecha_agrega_a_visita, 'peso'=>$extra['peso'], 'temperatura'=>$extra['temperatura'], 'longitud'=>'', 'altura'=>'', 'frecuencia_cardiaca'=>$extra['frecuencia_cardiaca'], 'frecuencia_respiratoria'=>$extra['frecuencia_respiratoria'], 'origen'=>'Concepto Visita' ];
														$medicion = $this->model->mascota->addMedicion($medicion_data); 
														if($medicion->response) {
															$medicion_id = $medicion->result;
														} else { 
															$medicion->data = $medicion_data; $medicion->state = $this->model->transaction->regresaTransaccion(); 
															return $response->withJson($medicion); 
														}
													}

													if(in_array($extra['tipo'], [5, 6])) {
														$vacuna_data = [ 
															'mascota_id'=>$detalle->mascota_id, 
															'masc_medicion_id'=>$medicion_id, 
															'producto_id'=>$prod_id, 
															'tipo'=>$extra['tipo'], 
															'descripcion'=>$prod_data->nombre, 
															'aplicacion'=>$fecha_agrega_a_visita, 
															// 'siguiente'=>$extra['siguiente'], 
															'lote'=>$extra['lote'], 
															'observaciones'=>$extra['notas'], 
															'status'=>2 
														];
														if(isset($extra['siguiente']) && $extra['siguiente'] != ''){
															$vacuna_data['siguiente'] = $extra['siguiente'];
														}else{
															if($extra['tipo'] == 5){
																$sigAplicacion = $prod_data->periodo_aplicacion;
																$fechaActual = strtotime(date('Y-m-d'));
																switch ($sigAplicacion) {
																	case '1': $sigAplicacion = null; break;
																	case '2': $sigAplicacion = date('Y-m-d', strtotime('+6 month', $fechaActual)); break;
																	case '3': $sigAplicacion = date('Y-m-d', strtotime('+1 year', $fechaActual)); break;
																	default : $sigAplicacion = null; break;
																}
																$vacuna_data['siguiente'] = $sigAplicacion;
															}
														}
														
														$vacuna = $this->model->vacuna->add($vacuna_data); 
														if(!$vacuna->response) {
															$vacuna->data = $vacuna_data; 
															$vacuna->state = $this->model->transaction->regresaTransaccion(); 
															return $response->withJson($vacuna);
														}
													}
												// } else { $belleza->data = $belleza_data; $belleza->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($belleza); }
											}
										} else { $det_salida->data = $det_salida_data; $det_salida->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($det_salida); }
									} else { $this->response = new Response(); $response->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($this->response->SetResponse(false, "Stock insuficiente para el producto $prod_data->nombre del paquete $paquete_data->nombre")); }
								}
							}
						} else {
							$prod_data = $this->model->producto->get($producto_id)->result; $prod_id = $prod_data->id;
							if($prod_data->stock==null || $prod_data->stock>=$cantidadDet) {
								$det_salida_data = [ 'prod_salida_id'=>$salida_id, 'producto_id'=>$prod_id, 'cantidad'=>$cantidadDet, 'precio'=>$precio, 'importe'=>$subtotal, 'descuento_importe'=>$descuento, 'descuento_motivo'=>$detalle->descuento_motivo, 'total'=>$total ];
								$det_salida = $this->model->det_prod_salida->add($det_salida_data); if($det_salida->response) {
									$SUBTOTAL += $subtotal; $DESCUENTO += $descuento; $TOTAL += $total;
									$stock = $this->model->prod_stock->getByProducto($prod_id)->result; if($prod_data->stock != null) {
										$tipo = -1; $inicial = $prod_data->stock;
										if(count($stock) == 0) {
											$stock_inicial_data = [ 'producto_id'=>$prod_id, 'tipo'=>$tipo, 'inicial'=>0, 'cantidad'=>$prod_data->stock, 'final'=>$prod_data->stock, 'fecha'=>$fecha, 'colaborador_id'=>$colaborador_id, 'origen'=>0, 'origen_tipo'=>3 ];
											$stock_inicial = $this->model->prod_stock->add($stock_inicial_data); if(!$stock_inicial->response) {
												$stock_inicial->data = $stock_inicial_data; $stock_inicial->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($stock_inicial);
											}
										}

										$stock_final_data = [ 'producto_id'=>$prod_id, 'tipo'=>$tipo, 'inicial'=>$inicial, 'cantidad'=>$cantidadDet, 'final'=>$inicial + ($tipo * $cantidadDet), 'fecha'=>$fecha_agrega_a_visita, 'colaborador_id'=>$colaborador_id, 'origen'=>$salida_id, 'origen_tipo'=>16, 'det_venta_id' => $det_venta_id ];
										$stock_final = $this->model->prod_stock->add($stock_final_data); if($stock_final->response) {
											$producto = $this->model->producto->edit([ 'stock'=>$stock_final_data['final'] ], $prod_id); if($producto->response) {
												if(in_array($prod_data->tipo, [4,5,6,7]) && ($propietario_id==$_SESSION['cliente_general'] || isset($detalle->mascota_id))){
													$farmacia_data = [ 
														'producto_id'=>$prod_id, 
														'propietario_id'=>$propietario_id, 
														'det_venta_id'=>$det_venta_id, 
														'fecha'=>$fecha_agrega_a_visita, 
														'origen_tipo'=>2, 
														'origen_id'=>$visita_id, 
														'usuario_solicita'=>$colaborador_id, 
														'cantidad'=>$cantidadDet 
													];
													if($propietario_id!=$_SESSION['cliente_general'] && isset($detalle->mascota_id)) { $farmacia_data['mascota_id'] = $detalle->mascota_id; }
													else { $farmacia_data['mascota_id'] = $_SESSION['mascota_cliente_general']; }
													$farmacia = $this->model->farmacia->add($farmacia_data); if($farmacia->response) {
														if(in_array($prod_data->tipo, [4,5,6,7])){
															$pushFarmacia = true;
					
															if($prod_data->uso_controlado == 1){
																$dataControlado = array(
																	'producto_id'=>$prod_id, 
																	'propietario_id'=>$propietario_id, 
																	'mascota_id'=>$farmacia_data['mascota_id'], 
																	'origen_id'=>$visita_id, 
																	'origen_tipo'=>2, 
																	'det_venta_id'=>$det_venta_id, 
																	'cantidad'=>$cantidadDet, 
																	'dias'=>$detalle->dias, 
																	'surtidos'=>$detalle->surtidos, 
																	'restan'=>$detalle->dias - $detalle->surtidos, 
																	'completo'=>$detalle->dias == $detalle->surtidos ? 1 : 0, 
																	'siguiente'=>date('Y-m-d', strtotime($fecha_agrega_a_visita.' +'.($detalle->dias+1).' days')), 
																	'status'=>2,
																);
																$controlado = $this->model->farmacia->addControlado($dataControlado); $controlado->data = $dataControlado; if(!$controlado->response){ 
																	$controlado->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($controlado); 
																}
															}
														}
													} else {
														$farmacia->data = $farmacia_data; $farmacia->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($farmacia);
													}
												}
											} else { $producto->data = [ 'stock'=>$stock_final_data['final'] ]; $producto->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($producto); }
										} else { $det_salida->data = $det_salida_data; $det_salida->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($det_salida); }
									}

									if(!in_array(intval($prod_data->categoria_id), [9, 10, 20, 21]) && isset($detalle->mascota_id) && isset($detalla->extra)) {
										$extra = $detalle->extra;
										$extra['tipo'] = $prod_data->categoria_id;
										$extra['mascota_id'] = $detalle->mascota_id;
										$extra['visita_id'] = $visita_id;
										$extra['det_venta_id'] = $det_venta_id;
										if(isset($extra['categoria'])) { unset($extra['categoria']); }
										if(isset($extra['recordatorio'])) { unset($extra['recordatorio']); }
										if($prod_data->categoria_id != 5 && $prod_data->categoria_id != 6){
											$belleza = $this->model->mascota->addBelleza($extra);
											if(!$belleza->response){
												$belleza->data = $belleza_data; $belleza->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($belleza);
											}
										} 
										// if($belleza->response) {
											$medicion_id = null;
											if(isset($extra['peso'])) {
												$medicion_data = [ 'mascota_id'=>$detalle->mascota_id, 'fecha'=>$fecha_agrega_a_visita, 'peso'=>$extra['peso'], 'temperatura'=>$extra['temperatura'], 'longitud'=>'', 'altura'=>'', 'frecuencia_cardiaca'=>$extra['frecuencia_cardiaca'], 'frecuencia_respiratoria'=>$extra['frecuencia_respiratoria'], 'origen'=>'Concepto Visita' ];
												$medicion = $this->model->mascota->addMedicion($medicion_data); if($medicion->response) {
													$medicion_id = $medicion->result;
												} else { $medicion->data = $medicion_data; $medicion->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($medicion); }
											}

											if(in_array($extra['tipo'], [5, 6])) {
												$vacuna_data = [ 
													'mascota_id'=>$detalle->mascota_id, 
													'masc_medicion_id'=>$medicion_id, 
													'producto_id'=>$prod_id, 
													'tipo'=>$extra['tipo'], 
													'descripcion'=>$prod_data->nombre, 
													'aplicacion'=>$fecha_agrega_a_visita, 
													// 'siguiente'=>$extra['siguiente'], 
													'lote'=>$extra['lote'], 
													'observaciones'=>$extra['notas'], 
													'status'=>2 
												];
												if(isset($extra['siguiente']) && $extra['siguiente'] != ''){
													$vacuna_data['siguiente'] = $extra['siguiente'];
												}else{
													if($extra['tipo'] == 5){
														$sigAplicacion = $prod_data->periodo_aplicacion;
														$fechaActual = strtotime(date('Y-m-d'));
														switch ($sigAplicacion) {
															case '1': $sigAplicacion = null; break;
															case '2': $sigAplicacion = date('Y-m-d', strtotime('+6 month', $fechaActual)); break;
															case '3': $sigAplicacion = date('Y-m-d', strtotime('+1 year', $fechaActual)); break;
															default : $sigAplicacion = null; break;
														}
														$vacuna_data['siguiente'] = $sigAplicacion;
													}
												}
												$vacuna = $this->model->vacuna->add($vacuna_data); 
												if(!$vacuna->response) {
													$vacuna->data = $vacuna_data;
													$vacuna->state = $this->model->transaction->regresaTransaccion(); 
													return $response->withJson($vacuna);
												}
											}
										// } else { $belleza->data = $belleza_data; $belleza->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($belleza); }
									}

									if(in_array($producto_id, $_SESSION['prod_farm_paq']) && isset($detalle->mascota_id)){
										$dataFarmPaq = array(
											'colaborador_id' => $colaborador_id, 
											'det_venta_id' => $det_venta_id, 
											'producto_id' => $producto_id, 
											'mascota_id' => $detalle->mascota_id, 
											'fecha' => $fecha_agrega_a_visita, 
										);
										//$det_venta->paquete = $dataFarmPaq;
										$paqFarm = $this->model->farmacia->addPaquete($dataFarmPaq);
										if(!$paqFarm->response){
											$det_venta->state = $this->model->transaction->regresaTransaccion(); 
											return $response->withJson($det_venta->SetResponse(false, 'No se agrego el paquete de farmacia'));
										}
									}
								} else { $det_salida->data = $det_salida_data; $det_salida->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($det_salida); }
							} else { 
								$this->response = new Response(); 
								$this->response->state = $this->model->transaction->regresaTransaccion(); 
								return $response->withJson($this->response->SetResponse(false, 'No hay stock suficiente del producto '.$prod_data->nombre));
							 }
						}
					}else { $salida->data = $salida_data; $salida->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($salida); }
				} else { $salida->data = $salida_data; $salida->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($salida); }
			}

			$data_venta = array(
				'subtotal' => ($venta->subtotal + $presupuesto->subtotal), 
				'descuento' => ($venta->descuento + $presupuesto->descuento), 
				'total' => ($venta->total + $presupuesto->total), 
			);
			$edit_venta = $this->model->venta->edit($data_venta, $venta->id);
			if($edit_venta->response){
				$data_presupuesto = [
					'visita_id' => $arguments['visita_id'],
					'status' => 2,
				];
				$edit_presupuesto = $this->model->presupuesto->edit($data_presupuesto, $presupuesto->id);
				if(!$edit_presupuesto->response){
					$edit_presupuesto->state = $this->model->transaction->regresaTransaccion(); 
					return $response->withJson($edit_presupuesto);
				}
			}else{
				$edit_venta->state = $this->model->transaction->regresaTransaccion(); 
				return $response->withJson($edit_venta);
			}
			
			$seg_log = $this->model->seg_log->add('Agrega presupuesto a visita', $arguments['presupuesto_id'], 'presupuesto'); 
			if(!$seg_log->response) {
				$seg_log->state = $this->model->transaction->regresaTransaccion(); 
				return $response->withJson($seg_log);
			}

			$edit_presupuesto->state = $this->model->transaction->confirmaTransaccion();
			return $response->withJson($edit_presupuesto);
		});

		$this->get('getByPeriodo/{periodo}', function($request, $response, $arguments){
			$actual = date('Y-m-d'); 
			$total = 0;
			switch ($arguments['periodo']){
				case 1:
					$fechaini = strtotime('-30 day', strtotime($actual));
					$fechaini = date('Y-m-d', $fechaini);
					$res = $this->model->visita->getByPeriodo($arguments['periodo'], $fechaini, $actual)->result;
				break;
				case 2:
					$fechafin = strtotime('-30 day', strtotime($actual));
					$fechafin = date('Y-m-d', $fechafin);
					$fechaini = strtotime('-5 month', strtotime($fechafin));
					$fechaini = date('Y-m-d', $fechaini);
					$res = $this->model->visita->getByPeriodo($arguments['periodo'], $fechaini, $fechafin)->result;
				break;
				case 3:
					$fechafin = strtotime('-30 day', strtotime($actual));
					$fechafin = date('Y-m-d', $fechafin);
					$fechaini = strtotime('-11 month', strtotime($fechafin));
					$fechaini = date('Y-m-d', $fechaini);
					$res = $this->model->visita->getByPeriodo($arguments['periodo'], $fechaini, $fechafin)->result;
				break;
				case 4:					
					$fechafin = strtotime('-30 day', strtotime($actual));
					$fechafin = date('Y-m-d', $fechafin);
					$fechafin = strtotime('-11 month', strtotime($fechafin));
					$fechafin = date('Y-m-d', $fechafin);
					$fechafin = strtotime('-1 day', strtotime($fechafin));
					$fechafin = date('Y-m-d', $fechafin);
					$res = $this->model->visita->getByPeriodo($arguments['periodo'], '2021-01-01', $fechafin)->result;
				break;
			}
			foreach($res as $r){
				$total += $r->visitas;
			}
			$res['total'] = $total;
			return $this->response->withJson($res);
			
		});

		$this->get('getByPropietarioNew/{propietario_id}', function ($request, $response, $arguments) {
			date_default_timezone_set('America/Mexico_City');
			$visitas = $this->model->visita->getByPropietarioNew($arguments['propietario_id']);
			foreach($visitas->result as $visita) {
				if($visita->colaborador_id != null){
					$visita->colaborador = $this->model->usuario->get($this->model->colaborador->get($visita->colaborador_id)->result->usuario_id)->result; 
				}
				if($visita->colaborador_termino_id != null) { 
					$visita->colaborador_termino = $this->model->usuario->get($this->model->colaborador->get($visita->colaborador_termino_id)->result->usuario_id)->result; 
				}else { 
					$visita->colaborador_termino = [ 'id'=>0, 'nombre'=>'', 'apellidos'=>'']; 
				}

				$detalles = $this->model->det_venta->getByVenta($visita->venta_id)->result;
				foreach($detalles as $detalle) {
					if($detalle->mascota_id != null) {
						$detalle->mascota = $this->model->mascota->get($detalle->mascota_id)->result;
					} else { $detalle->mascota = [ 'id'=>0, 'nombre'=>'N/A' ]; }
				}
				$visita->det_venta = $detalles;
				
				if($arguments['propietario_id'] == $_SESSION['prop_farm']){
					$paq = $this->model->farmacia->getPaqueteByVisita($visita->id, false)->result;
					$visita->paquetef = '<strong>'.strtoupper($paq->mascota).'</strong> '.mb_strtoupper($paq->propietario).'<br>'.$paq->concepto;
				}

				$pagos = $this->model->pago->getByVisita($visita->id)->result;
				$pagado = 0; foreach($pagos as $pago) {
					$pagado += floatval($pago->cantidad);
				}
				$visita->pagado = $pagado;
			}

			echo json_encode($visitas);
			exit(0);
			return $response->withJson($visitas);
		});
	})->add( new MiddlewareToken() );
?>