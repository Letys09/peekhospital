<?php
	use App\Lib\Response,
		App\Lib\MiddlewareToken;
use Envms\FluentPDO\Literal;

require_once './core/defines.php';
 
	$app->group('/det_venta/', function() {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de det_venta');
		});

		$this->post('add/', function($request, $response, $arguments) {
			$this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody(); 
			$pushFarmacia = false;
		
			$mascota_id = $parsedBody['mascota_id'];
			$propietario_original_id = $parsedBody['propietario_id'];
			$paquete_id = $parsedBody['paquete_id']; 
			$colaborador_id = $parsedBody['colaborador_id']; 
			$producto_id = $parsedBody['producto_id']; 
			$receta_id = $parsedBody['receta_id']; 
			$prop_farmacia = $parsedBody['farmacia'];
		
			$cantidad = floatval($parsedBody['cantidad']); 
			$venta_id = $parsedBody['venta_id']; 
			$venta = $this->model->visita->getVenta($venta_id)->result;
			$visita_id = $parsedBody['visita_id']; 
			$fecha = date('Y-m-d H:i:s'); 
			$fecha_corta = date('Y-m-d');
		
			$info_prod = $this->model->producto->get($producto_id)->result;
			$precio = $info_prod->precio;
			$subtotal = floatval($cantidad * $precio);
			$parsedBody['precio'] = $precio;
			$parsedBody['subtotal'] = $subtotal;
			
			if($prop_farmacia){
				$parsedBody['propietario_id'] = 396;
				$mascota_id = 654;
				$parsedBody['descuento_porcentaje'] = '100';
				$parsedBody['descuento_motivo'] = 'Paquete Farmacia';
				$parsedBody['total'] = '0.00';
			}else{
				$parsedBody['descuento_porcentaje'] = '0';
				$parsedBody['descuento_motivo'] = '';
				$parsedBody['propietario_id'] = $propietario_original_id;
				$mascota_id = $mascota_id;
			}
			
			$parsedBody['iva'] = 0;
			if($venta->facturar == 1) {
				if($info_prod->iva == 3){
					$precioSinIva = $precio/1.16; 
					$subtotal = ($cantidad*$precioSinIva);
					$parsedBody['precio'] = $precioSinIva;
					$parsedBody['subtotal'] = $subtotal;
					$parsedBody['total'] = $subtotal;
				}
				if($info_prod->iva == 2 || $info_prod->iva == 3) {
					$porc = 1;
					$s = floatval($subtotal);
					$i = 0;
					$p = 0;
					$parsedBody['iva'] = ($s - $i) * 0.16 * $porc;
				}
				$parsedBody['total'] += floatval($parsedBody['iva']);
			}
			$parsedBody['colaborador_asigno_id'] = $colaborador_id;
			$parsedBody['fecha_asigno'] = $fecha_corta;
		
			unset($parsedBody['visita_id'], $parsedBody['mascota_id'], $parsedBody['paquete_id'], $parsedBody['receta_id'], $parsedBody['farmacia']);
		
			$det_venta = $this->model->det_venta->add($parsedBody); 
			if($det_venta->response) { 
				$det_venta_id = $det_venta->result; $subtotal_venta = 0; $iva_venta = 0; $total_venta = 0;
				$detalles_det_venta = $this->model->det_venta->getByVenta($venta_id)->result; 
				foreach($detalles_det_venta as $detalle) { 
					$subtotal_venta += $detalle->subtotal; 
					$iva_venta += $detalle->iva; 
					$total_venta += $detalle->total; 
				}
				$data_edit_venta = [ 'subtotal' => $subtotal_venta, 'descuento' => $subtotal_venta+$iva_venta-$total_venta, 'iva' => $iva_venta, 'total' => $total_venta ];
				$edit_venta = $this->model->visita->editVenta($data_edit_venta, $venta_id); 
				if($edit_venta->response) {
					$salida_id = $this->model->prod_salida->getByVenta($venta_id)->result->id; 
					$descuento = $subtotal * (intval($parsedBody['descuento_porcentaje']) / 100);
					$data_det_salida = [ 
						'prod_salida_id' => $salida_id, 
						'producto_id' => $producto_id, 
						'cantidad' => $cantidad, 
						'precio' => $precio, 
						'importe' => $subtotal, 
						'descuento_importe' => $descuento, 
						'descuento_motivo' => $parsedBody['descuento_motivo'], 
						'total' => $total_venta
					];
					$det_salida = $this->model->prod_salida->addDetalle($data_det_salida); 
					if($det_salida->response) {
						$stock = $this->model->prod_stock->getByProducto($producto_id)->result; 
						if(count($stock) > 1 || ($info_prod->stock != null && floatval($info_prod->stock) > 0)) {
							$tipo = -1;
							if(count($stock) == 0) {
								$data_stock = [ 
									'producto_id' => $producto_id, 
									'tipo' => 1, 
									'inicial' => 0, 
									'cantidad' => $info_prod->stock, 
									'final' => $info_prod->stock, 
									'fecha' => $fecha, 
									'colaborador_id' => $colaborador_id, 
									'origen' => 0, 
									'origen_tipo' => 1, 
									'status' => 1 
								];
								$stock_inicial = $this->model->prod_stock->add($data_stock); 
								if($stock_inicial->response) { $inicial = $info_prod->stock; }
								else { 
									$stock_inicial->state = $this->model->transaction->regresaTransaccion(); 
									return $response->withJson($stock_inicial->SetResponse(false, 'No se agregó el registro de stock inicial, el cual no existía anteriormente')); 
								}
							} else {
								$inicial = $stock[0]->final;
							}
							
							$data_stock = [ 
								'producto_id' => $producto_id, 
								'tipo' => $tipo, 
								'inicial' => $inicial, 
								'cantidad' => $cantidad, 
								'final' => $inicial+($tipo*$cantidad), 
								'fecha' => $fecha, 
								'colaborador_id' => $colaborador_id, 
								'origen' => $salida_id, 
								'origen_tipo' => 2, 
								'det_venta_id' => $det_venta_id 
							];
							$prod_stock = $this->model->prod_stock->add($data_stock); 
							if($prod_stock->response) { 
								$prod_stock_id = $prod_stock->result;
								$edit_producto = $this->model->producto->edit(['stock'  =>  $data_stock['final'], 'no_ventas' => $info_prod->no_ventas+1], $producto_id); 
								if(!$edit_producto->response) {
									$edit_producto->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($edit_producto->SetResponse(false, 'No se editó el stock del producto')); 
								}
		
								// Solicitar a farmacia
								if(in_array($info_prod->tipo, [4,5,6,7])){
									$this->model->det_venta->edit(array('surtido' => 0), $det_venta_id);
									$dataFarmacia = array(
										'producto_id' => $producto_id, 
										'propietario_id' => $parsedBody['propietario_id'],
										'mascota_id' => $mascota_id,
										'det_venta_id' => $det_venta_id,
										'fecha' => $fecha,
										'origen_tipo' => 2, 
										'origen_id' => $visita_id, 
										'usuario_solicita' => $colaborador_id, 
										'cantidad' => $cantidad,
									);
									$this->model->farmacia->add($dataFarmacia);
									$pushFarmacia = true;
		
									if($info_prod->uso_controlado == 1){
										$dataControlado = array(
											'producto_id' 		=> $producto_id,
											'propietario_id' 	=> $propietario_original_id,
											'mascota_id' 		=> $mascota_id,
											'origen_id' 		=> $visita_id,
											'origen_tipo'		=> 2,
											'det_venta_id'		=> $det_venta_id, 
											'cantidad' 			=> $cantidad, 
											'dias' 				=> 1, 
											'surtidos' 			=> 1, 
											'restan' 			=> 0, 
											'completo' 			=> 1,
											'status' 			=> 2, 
										);
										$controlado = $this->model->farmacia->addControlado($dataControlado);
										$controlado->data = $dataControlado;
										if(!$controlado->response){ 
											$controlado->state = $this->model->transaction->regresaTransaccion(); 
											return $response->withJson($controlado); 
										}
									}
								}
							} else { $prod_stock->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($prod_stock->SetResponse(false, 'No se agregó la información del stock')); }
						}
		
						if($info_prod->categoria_id == 9){
							$this->model->mascota->edit(array('quirofano' => 1), $mascota_original_id);
							$this->model->seg_log->add('Mascota Cirugía',  $mascota_original_id, 'mascota');
						}
		
						if($info_prod->categoria_id == 11){
							$this->model->mascota->edit(array('quirofano' => 1),  $mascota_original_id);
							$this->model->seg_log->add('Mascota Procedimiento '.$info_prod->nombre,  $mascota_original_id, 'mascota');
						}
		
						switch ($info_prod->unidad) {
							case 1: $texto = 'Administrar por vía oral '.$cantidad.' pieza'; $tipo_admin = 3; break;
							case 2: $texto = 'Administrar por vía oral '.$cantidad.' caja'; $tipo_admin = 3; break;
							case 3: $texto = 'Administrar por vía oral '.$cantidad.' blister'; $tipo_admin = 3; break;
							case 4: $texto = 'Administrar por vía oral '.$cantidad.' tableta'; $tipo_admin = 3; break;
							case 5: $texto = 'Administrar por vía intravenosa '.$cantidad.' ml'; $tipo_admin = 1; break;
							case 6: $texto = 'Administrar por vía intravenosa '.$cantidad.' lt'; $tipo_admin = 1; break;
							case 7: $texto = 'Administrar por vía oral '.$cantidad.' mg'; $tipo_admin = 3; break;
							case 8: $texto = 'Administrar por vía oral '.$cantidad.' gr'; $tipo_admin = 3; break;
							case 9: $texto = 'Administrar por vía oral '.$cantidad.' kg'; $tipo_admin = 3; break;
							default: $texto = ' ';  $tipo_admin = ''; break;
						}
						$detalle_receta = [
							'receta_id' => $receta_id,
							'producto_id' => $producto_id,
							'medicamento' => $info_prod->nombre,
							'dosis' => $texto,
							'duracion' => 'Única dosis',
							'tipo_admin' => $tipo_admin,
							'surtir' => $cantidad, // el valor en este campo no solicita nuevamente el producto a farmacia
							'det_venta_id' => $det_venta_id
						];
						$add_detalle_receta = $this->model->receta->addDetalle($detalle_receta);
						if(!$add_detalle_receta->response){
							$add_detalle_receta->state = $this->model->transaction->regresaTransaccion(); 
							return $response->withJson($add_detalle_receta->SetResponse(false, 'No se agregó la información del detalle receta'));
						}
					} else { 
						$det_salida->state = $this->model->transaction->regresaTransaccion(); 
						return $response->withJson($det_salida->SetResponse(false, 'No se agregó la información del detalle la salida'));
					}
		
					$subtotal_salida = 0; $total_salida = 0;
					$detalles = $this->model->prod_salida->getDetBySalida($salida_id)->result; 
					foreach($detalles as $detalle) { 
						$subtotal_salida += $detalle->importe; 
						$total_salida += $detalle->total; 
					}
					$data_salida = [ 'importe'=>$subtotal_salida, 'descuento'=>$subtotal_salida-$total_salida, 'total'=>$total_salida ];
					$edit_salida = $this->model->prod_salida->edit($data_salida, $salida_id); 
					if($edit_salida->response) {
						$seg_log = $this->model->seg_log->add('Venta de producto desde touch', $det_venta_id, 'det_venta'); 
						if(!$seg_log->response) {
							$seg_log->state = $this->model->transaction->regresaTransaccion(); 
							return $response->withJson($seg_log->SetResponse(false, 'No se agregó la información en la tabla LOG'));
						}
					}
					$paqFarm = $this->model->farmacia->editPaquete($paquete_id, array('costo' => $subtotal_venta));
					
					if($pushFarmacia) $this->model->farmacia->sendPush();
		
				} else { $edit_venta->data = $data; $edit_venta->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($edit_venta->SetResponse(false, 'No se actualizó el total de la venta')); }
			} else { $det_venta->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($det_venta->SetResponse(false, 'No se agregó la información de la venta')); }
		
			$det_venta->receta_detalle_id = $add_detalle_receta->result;
			$det_venta->state = $this->model->transaction->confirmaTransaccion();
			echo json_encode($det_venta->SetResponse(true));
			exit(0);
		});

		$this->put('edit/{id}', function($request, $response, $arguments) {
			$this->model->transaction->iniciaTransaccion();
			$fecha			= date('Y-m-d H:i:s');
			$colaborador_id	= $_SESSION['usuario']->colaborador_id;
			$parsedBody		= $request->getParsedBody();
			$paquete_id     = $parsedBody['paquete_id']; 
			$det_venta_id	= $arguments['id'];				
			$info_det_venta	= $this->model->det_venta->get($det_venta_id)->result;
			$venta_id		= $info_det_venta->venta_id;		
			$info_venta		= $this->model->visita->getVenta($venta_id)->result;
			$producto_id	= $info_det_venta->producto_id;	
			$info_producto	= $this->model->producto->get($producto_id)->result;
			$info_salida	= $this->model->prod_salida->getByVenta($venta_id)->result;	
			$salida_id		= $info_salida->id;
			$info_visita	= $this->model->visita->getByVenta($venta_id)->result[0];	
			$visita_id		= $info_visita->id;
			$receta_detalle_id     = $parsedBody['receta_detalle_id']; 
			$prop_farmacia  = $parsedBody['farmacia']; 
		
			$pushFarmacia = false; $tipoPush = 1;
		
			$subtotal = floatval($info_producto->precio * $parsedBody['cantidad']);
			if($info_venta->facturar == 1) {
				if($info_prod->iva == 3){
					$precioSinIva = $precio/1.16; 
					$subtotal = ($cantidad*$precioSinIva);
					$parsedBody['precio'] = $precioSinIva;
					$parsedBody['subtotal'] = $subtotal;
					$parsedBody['total'] = $subtotal;
				}
				if($info_prod->iva == 2 || $info_prod->iva == 3) {
					$porc = 1;
					$s = floatval($subtotal);
					$i = 0;
					$p = 0;
					$parsedBody['iva'] = ($s - $i) * 0.16 * $porc;
				}
				$parsedBody['total'] += floatval($parsedBody['iva']);
			}
		
			$det_venta_nuevo = [
				'cantidad' => $parsedBody['cantidad'],
				'subtotal' => $subtotal,
			];
		
			$areTheSame		= true; 
			foreach($info_det_venta as $field => $value) { 
				if(isset($det_venta_nuevo->$field) && $det_venta_nuevo->$field!=$value) { 
					$areTheSame = false; break; 
				} 
			}
			$edit_det_venta	= $this->model->det_venta->edit($det_venta_nuevo, $det_venta_id);
			if($edit_det_venta->response || $areTheSame) { $edit_det_venta->areTheSame = $areTheSame;
				$cantidad	= floatval($det_venta_nuevo['cantidad']);
				$importe	= $subtotal;
		
				$subtotal_venta = 0; $total_venta = 0;
				$edit_detalles_det_venta = $this->model->det_venta->getByVenta($venta_id)->result; 
				foreach($edit_detalles_det_venta as $detalle) { 
					$subtotal_venta += $detalle->subtotal; 
					$total_venta += $detalle->total; 
				}
		
				$venta_nueva = [ 
					'subtotal' => $subtotal_venta,
					'total' => $total_venta 
				];
		
				$areTheSame	= true; 
				foreach($info_venta as $field => $value) { 
					if(isset($venta_nueva->$field) && $venta_nueva->$field!=$value) { 
						$areTheSame = false; break; 
					} 
				}
				$edit_venta	= $this->model->visita->editVenta($venta_nueva, $venta_id);
				$edit_venta->venta_nueva = $venta_nueva;
				$edit_venta->info_venta = $info_venta;
				$edit_venta->info_det_venta = $info_det_venta;
				$edit_venta->importe = $importe;
				$edit_venta->id = $venta_id;
				if($edit_venta->response || $areTheSame) { 
					$edit_venta->areTheSame = $areTheSame;
					if($info_det_venta->cantidad != $cantidad) {
						$tipo			= ($info_det_venta->cantidad > $cantidad) ? 1 : -1;
						$cantidad_nueva	= ($info_det_venta->cantidad - $cantidad) * $tipo;
		
						if($tipo > 0) {
							$entrada_nueva	= [ 
								'colaborador_id' => $colaborador_id, 
								'venta_id' => $venta_id, 
								'importe' => 0, 
								'descuento' => 0, 
								'tipo_descuento' => 1, 
								'subtotal' => 0, 
								'iva' => 0, 
								'total' => 0, 
								'fecha' => $fecha 
							];
							$add_entrada = $this->model->prod_entrada->add($entrada_nueva);
							if($add_entrada->response) { $entrada_id = $add_entrada->result;
								$cantidad_nueva	= $info_det_venta->cantidad - $cantidad;
								$add_det_entrada_nuevo = [ 
									'prod_entrada_id' => $entrada_id, 
									'producto_id' => $producto_id, 
									'cantidad' => $cantidad_nueva, 
									'costo' => 0, 
									'importe' => 0, 
									'descuento' => 0, 
									'subtotal' => 0, 
									'iva' => 0, 
									'total' => 0, 
								];
								$add_detalle_entrada	= $this->model->prod_entrada->addDetalle($add_det_entrada_nuevo);
								if($add_detalle_entrada->response) {
									$origen		= $entrada_id;
									$origenTipo	= 14;
								} else { 
									$add_detalle_entrada->state = $this->model->transaction->regresaTransaccion(); 
									return $response->withJson($add_detalle_entrada->SetResponse(false, 'NO se registró la entrada de la cantidad que se desconto')); 
								}
							} else { 
								$add_entrada->state = $this->model->transaction->regresaTransaccion(); 
								return $response->withJson($add_entrada->SetResponse(false, 'NO se dio de alta el registro para la nueva entrada')); 
							}
						} else {
							if($info_producto->stock == null || $info_producto->stock >= $cantidad_nueva) {
								$nueva_salida = [ 
									'importe' => $info_salida->importe + $subtotal, 
									'descuento' => $info_salida->importe + $subtotal, 
									'total' => 0 
								];
								$areTheSame	= true; 
								foreach($info_salida as $field => $value) { 
									if(isset($nueva_salida->$field) && $nueva_salida->$field != $value) { 
										$areTheSame = false; break; 
									} 
								}
								$edit_salida = $this->model->prod_salida->edit($nueva_salida, $salida_id);
								if($edit_salida->response || $areTheSame) { $edit_salida->areTheSame = $areTheSame;
									$det_salida_nuevo = [ 
										'prod_salida_id' => $salida_id, 
										'producto_id' => $producto_id, 
										'cantidad' => $cantidad_nueva, 
										'precio' => $info_producto->precio, 
										'importe' => $subtotal, 
										'descuento_importe' => $subtotal, 
										'descuento_motivo' => 'Paquete Farmacia', 
										'total' => 0 
									];
									if(!$prop_farmacia){
										$det_salida['descuento_importe'] = 0;
										$det_salida['descuento_motivo'] = '';
										$det_salida['total'] = $subtotal;
									}
									$add_det_salida	= $this->model->prod_salida->addDetalle($det_salida_nuevo);
									if($add_det_salida->response) {
										$origen		= $salida_id;
										$origenTipo	= 15;
									} else { $add_det_salida->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($add_det_salida->SetResponse(false, 'NO se dió de alta el registro del detalle para la nueva salida')); }
								} else { $edit_salida->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($edit_salida->SetResponse(false, 'NO se actualizó la información de la salida de productos')); }
							} else { $info_producto->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($info_producto->SetResponse(false, "NO hay suficiente stock del producto: $producto_id, se requieren $cantidad_nueva unidades")); }
						}
		
						if($info_producto->stock != null) {
							$inicial	= floatval($this->model->prod_stock->getByProducto($producto_id)->result[0]->final);
							$stock_nuevo = [
								'colaborador_id' => $colaborador_id, 
								'producto_id' => $producto_id, 
								'tipo' => $tipo, 
								'inicial' => $inicial, 
								'cantidad' => $cantidad_nueva, 
								'final' => $inicial + ($tipo * $cantidad_nueva), 
								'fecha' => $fecha, 
								'origen' => $origen, 
								'origen_tipo' => $origenTipo, 
								'det_venta_id' => $det_venta_id 
							];
							$add_stock	= $this->model->prod_stock->add($stock_nuevo);
							if($add_stock->response) {
								$stock_producto	= [ 'stock'	=> $stock_nuevo['final'] ];
								$edit_producto	= $this->model->producto->edit($stock_producto, $producto_id);
								if(!$edit_producto->response) { 
									$edit_producto->state = $this->model->transaction->regresaTransaccion(); 
									return $response->withJson($edit_producto->SetResponse(false, 'NO se actualizó la información del stock en el producto')); 
								}
							} else { 
								$add_stock->state = $this->model->transaction->regresaTransaccion(); 
								return $response->withJson($add_stock->SetResponse(false, 'NO se agregó el movimiento en el kardex'));
							}
						}
		
						$edit_det_venta->prod_stock = $this->model->producto->get($producto_id)->result->stock;
		
						if(in_array($info_producto->tipo,[4,5,6,7])){
							$info_farmacia = $this->model->farmacia->getByVenta($det_venta_id)[0];
							if($info_farmacia->status == 1){
								$this->model->farmacia->editByVenta(array('cantidad' => $cantidad), $det_venta_id);
								if($info_producto->uso_controlado == 1){
									$data_controlado = array(
										'cantidad' 			=> $cantidad, 
										'dias' 				=> 1, 
										'surtidos' 			=> 1, 
										'restan' 			=> 0, 
										'completo' 			=> 1,
									);
									$this->model->farmacia->editControladoByVenta($data_controlado, $det_venta_id);
								}
							}else if($info_farmacia->status == 2){
								if($cantidad < $info_farmacia->cantidad){
									$diferencia = $info_farmacia->cantidad - $cantidad;
									$data_devolucion = array(
										'producto_id' => $info_farmacia->producto_id, 
										'propietario_id' => $info_farmacia->propietario_id, 
										'mascota_id' => $info_farmacia->mascota_id, 
										'det_venta_id' => $info_farmacia->det_venta_id, 
										'tipo' => 2, 
										'fecha' => new Literal('NOW()'), 
										'usuario_solicita' => $colaborador_id, 
										'cantidad' => $diferencia, 
										'origen_id' => $info_farmacia->origen_id, 
										'origen_tipo' => $info_farmacia->origen_tipo, 
									);
									$farmacia = $this->model->farmacia->add($data_devolucion);
									$pushFarmacia = true; $pushTipo = 2;
									if(!$farmacia->response){
										$farmacia->state = $this->model->transaction->regresaTransaccion(); 
										return $response->withJson($farmacia->SetResponse(false, "NO se registro la devolución del producto en farmacia"));
									}
								}else{
									$diferencia = $cantidad - $info_farmacia->cantidad;
									$data_farmacia = array(
										'producto_id' => $info_farmacia->producto_id, 
										'propietario_id' => $info_farmacia->propietario_id, 
										'mascota_id' => $info_farmacia->mascota_id, 
										'det_venta_id' => $info_farmacia->det_venta_id, 
										'fecha' => new Literal('NOW()'), 
										'usuario_solicita' => $colaborador_id, 
										'cantidad' => $diferencia, 
										'origen_id' => $info_farmacia->origen_id, 
										'origen_tipo' => $info_farmacia->origen_tipo, 
									);
									$farmacia = $this->model->farmacia->add($data_farmacia);
									$pushFarmacia = true;
									if(!$farmacia->response){
										$farmacia->state = $this->model->transaction->regresaTransaccion(); 
										return $response->withJson($farmacia->SetResponse(false, "NO se registro la solicitud del producto en farmacia"));
									}
								}
							}
						}
		
						switch ($info_producto->unidad) {
							case 1: $texto = 'Administrar por vía oral '.$cantidad.' pieza'; $tipo_admin = 3; break;
							case 2: $texto = 'Administrar por vía oral '.$cantidad.' caja'; $tipo_admin = 3; break;
							case 3: $texto = 'Administrar por vía oral '.$cantidad.' blister'; $tipo_admin = 3; break;
							case 4: $texto = 'Administrar por vía oral '.$cantidad.' tableta'; $tipo_admin = 3; break;
							case 5: $texto = 'Administrar por vía intravenosa '.$cantidad.' ml'; $tipo_admin = 1; break;
							case 6: $texto = 'Administrar por vía intravenosa '.$cantidad.' lt'; $tipo_admin = 1; break;
							case 7: $texto = 'Administrar por vía oral '.$cantidad.' mg'; $tipo_admin = 3; break;
							case 8: $texto = 'Administrar por vía oral '.$cantidad.' gr'; $tipo_admin = 3; break;
							case 9: $texto = 'Administrar por vía oral '.$cantidad.' kg'; $tipo_admin = 3; break;
							default: $texto = ' ';  $tipo_admin = ''; break;
						}
						
						$this->model->receta->editDetalle(['dosis' => $texto, 'tipo_admin' => $tipo_admin, 'surtir' => $cantidad], $receta_detalle_id);
						
						$seg_log = $this->model->seg_log->add('Actualización detalle venta desde hospital', $det_venta_id, 'det_venta'); 
						if(!$seg_log->response) {
							$seg_log->state = $this->model->transaction->regresaTransaccion(); 
							return $response->withJson($seg_log);
						}else{
							if($pushFarmacia) $this->model->farmacia->sendPush($tipoPush);
						}
					}
					
					$paqFarm = $this->model->farmacia->editPaquete($paquete_id, array('costo' => $venta_nueva['subtotal']));
					$edit_venta->SetResponse(true);
				}
		
				$edit_det_venta->edit_venta = $edit_venta;
				$edit_det_venta->SetResponse(true);
			} else { 
				$edit_det_venta->state = $this->model->transaction->regresaTransaccion(); 
				return $response->withJson($edit_det_venta); 
			}
		
			$edit_det_venta->producto_id = $producto_id;
			$edit_det_venta->state = $this->model->transaction->confirmaTransaccion();
			return $response->withJson($edit_det_venta);
		});

		$this->put('del/{id}', function($request, $response, $arguments) {
			$this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody();

			$detalle_id = $arguments['id'];
			$venta_id = $parsedBody['venta_id'];
			$visita_id = $parsedBody['visita_id'];
			$paquete_id = $parsedBody['paquete_id'];
			$receta_detalle_id = $parsedBody['receta_detalle_id'];

			$colaborador_id = $_SESSION['usuario']->colaborador_id;
			$folio = $this->model->prod_entrada->getSiguienteFolio(); 
			$fecha = date('Y-m-d H:i:s');
			$pushFarmacia = false;

			$data_entrada = [ 
				'colaborador_id' => $colaborador_id, 
				'venta_id' => $venta_id, 
				'folio' => $folio, 
				'fecha' => $fecha 
			];
			$prod_entrada = $this->model->prod_entrada->add($data_entrada); 
			if($prod_entrada->response) { 
				$entrada_id = $prod_entrada->result;
				$info_det_venta = $this->model->det_venta->get($detalle_id)->result; 
				$del_det_venta = $this->model->det_venta->del($detalle_id); 
				if($del_det_venta->response) { 
					$subtotal_venta = 0; 
					$total_venta = 0;
					$iva_venta = 0;
					$detalles = $this->model->det_venta->getByVenta($venta_id)->result; 
					foreach($detalles as $detalle) { 
						$subtotal_venta += $detalle->subtotal; 
						$iva_venta += $detalle->iva; 
						$total_venta += $detalle->total; 
					}
					$data_edit_venta = [ 
						'subtotal' => $subtotal_venta, 
						'descuento' => $subtotal_venta+$iva_venta-$total_venta, 
						'iva' => $iva_venta, 
						'total' => $total_venta 
					];
					$areTheSame = true; 
					$edit_venta = $this->model->visita->editVenta($data_edit_venta, $venta_id);
					if($edit_venta->response || $areTheSame) {
						$edit_venta->SetResponse(true);
						$info_det_venta = $this->model->det_venta->get($detalle_id)->result; 
						$cantidad = floatval($info_det_venta->cantidad); 
						$desc_importe = floatval($info_det_venta->descuento_importe); 
						$desc_porcentaje = intval($info_det_venta->descuento_porcentaje); 
						$desc_motivo = $info_det_venta->descuento_motivo;

						$det_producto_id = $info_det_venta->producto_id; 
						$info_producto = $this->model->producto->get($det_producto_id)->result;
						
						$importe = floatval($info_det_venta->subtotal); 
						$descuento = $desc_importe > 0 ? $desc_importe : ($desc_importe * $desc_porcentaje / 100);
						$data_det_entrada = [ 
							'prod_entrada_id' => $entrada_id, 
							'producto_id' => $det_producto_id, 
							'cantidad' => $cantidad, 
							'costo' => $info_det_venta->precio, 
							'importe' => $importe, 
							'descuento' => $descuento, 
							'total' => $info_det_venta->total 
						];
						$add_det_entrada = $this->model->prod_entrada->addDetalle($data_det_entrada); 
						if($add_det_entrada->response) {
							$stock = $this->model->prod_stock->getByProducto($det_producto_id)->result;
							$prod_salida_id = $this->model->prod_salida->getByVenta($venta_id)->result->id;
							$detalle_idDetProdSalida = $this->model->prod_salida->detProdSalida($prod_salida_id, $det_producto_id)->result->id;
							$delProd = $this->model->prod_salida->delProd($detalle_idDetProdSalida);
							if(count($stock) > 0) {
								$tipo = 1; $inicial = $stock[0]->final;
								$data_stock = [ 
									'producto_id' => $det_producto_id, 
									'tipo' => $tipo, 
									'inicial' => $inicial, 
									'cantidad' => $cantidad, 
									'final' => $inicial+($tipo*$cantidad), 
									'fecha' => $fecha, 
									'colaborador_id' => $colaborador_id, 
									'origen' => $entrada_id, 
									'origen_tipo' => 9,
									'motivo' => 'Error al agregar en quirofano' 
								];
								$prod_stock = $this->model->prod_stock->add($data_stock); 
								if($prod_stock->response) {
									$edit_producto = $this->model->producto->edit([ 'stock' => $data_stock['final'] ], $det_producto_id); 
									if($edit_producto->response || $data['final'] == $info_producto->stock) {
										$edit_producto->SetResponse(true);
									} else { 
										$edit_producto->state = $this->model->transaction->regresaTransaccion(); 
										return $response->withJson($edit_producto->SetResponse(false, 'No se actualizó el stock en la tabla producto')); 
									}
								} else { 
									$prod_stock->state = $this->model->transaction->regresaTransaccion(); 
									return $response->withJson($prod_stock->SetResponse(false, 'No se actualizo el stock del producto')); 
								}
							}
						} else { 
							$add_det_entrada->state = $this->model->transaction->regresaTransaccion(); 
							return $response->withJson($add_det_entrada->SetResponse(false, 'No se agregó el detalle de la entrada')); 
						}
						if(in_array($info_producto->tipo, [4,5,6,7]) || in_array($info_producto->categoria_id, [5,6])){
							$info_farmacia = $this->model->farmacia->getByVenta($detalle_id);
							if(count($info_farmacia) > 0) {
								$info_farmacia = $info_farmacia[0];
								$this->model->farmacia->delByDetVenta($detalle_id);
								if($info_farmacia->status == 2){
									$data_devolucion = array(
										'producto_id' => $info_farmacia->producto_id, 
										'propietario_id' => $info_farmacia->propietario_id, 
										'mascota_id' => $info_farmacia->mascota_id, 
										'det_venta_id' => $info_farmacia->det_venta_id, 
										'tipo' => 2, 
										'fecha' => new Literal('NOW()'), 
										'usuario_solicita' => $colaborador_id, 
										'cantidad' => $info_farmacia->cantidad, 
										'origen_id' => $info_farmacia->origen_id, 
										'origen_tipo' => $info_farmacia->origen_tipo, 
									);
									$farmacia = $this->model->farmacia->add($data_devolucion);
									$pushFarmacia = true;
									if(!$farmacia->response){
										$farmacia->state = $this->model->transaction->regresaTransaccion();
										return $response->withJson($farmacia->SetResponse(false, "NO se registro la devolución del producto en farmacia"));
									}
								}
							}
						}
						
						$paqFarm = $this->model->farmacia->editPaquete($paquete_id, array('costo' => $subtotal_venta));
						$detalle_receta = $this->model->receta->delDetalle($receta_detalle_id);
						
					} else { 
						$edit_venta->data = $data; 
						$edit_venta->id = $detalle_id; 
						$edit_venta->state = $this->model->transaction->regresaTransaccion(); 
						return $response->withJson($edit_venta->SetResponse(false, 'No se actualizo la información de la venta')); 
					}

				} else { 
					$del_det_venta->state = $this->model->transaction->regresaTransaccion(); 
					return $response->withJson($del_det_venta->SetResponse(false, 'No se eliminó el detalle de la venta')); }

					$seg_log = $this->model->seg_log->add('Cancela venta de producto desde touch', $detalle_id, 'det_venta', 1); 
					if(!$seg_log->response) {
						$seg_log->state = $this->model->transaction->regresaTransaccion(); 
						return $response->withJson($seg_log);
				}else{
					if($pushFarmacia) $this->model->farmacia->sendPush(2);
				}
				

				$importe_entrada = 0; $total_entrada = 0; 
				$detalles = $this->model->prod_entrada->getByEntrada($entrada_id)->result; 
				foreach($detalles as $detalle) { 
					$importe_entrada += $detalle->importe; 
					$total_entrada += $detalle->total; 
				}
					$edit_entrada = $this->model->prod_entrada->edit([ 'importe'=>$importe_entrada, 'descuento'=>$importe_entrada-$total_entrada, 'subtotal'=>$total_entrada, 'iva'=>0, 'total'=>$total_entrada ], $entrada_id); 
					if(!$edit_entrada->response) {
						$edit_entrada->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($edit_entrada);
				}
			} else { $prod_entrada->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($prod_entrada); }

			$del_det_venta->state = $this->model->transaction->confirmaTransaccion();
			return $response->withJson($del_det_venta);
		});

		$this->put('editDetReceta/{id}', function($request, $response, $arguments) {
			$parsedBody = $request->getParsedBody();
			$cantidad = $parsedBody['cantidad'];
			$tipo = $parsedBody['tipo_admin'] == 1 ? ' intravenosa ' : ' intramuscular ';
			$data = [
				'dosis' => 'Administrar por vía'.$tipo.$cantidad.' ml',
				'tipo_admin' => $parsedBody['tipo_admin']
			];
			return $response->withJson($this->model->receta->editDetalle($data, $arguments['id']));
		});


	})->add( new MiddlewareToken() );
?>