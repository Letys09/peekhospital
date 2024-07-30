<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response,
    	Envms\FluentPDO\Literal;

	class SegLogModel {
		private $db;
		private $table = 'seg_log';
		private $response;
		
		public function __CONSTRUCT($db) {
			$this->db = $db;
			$this->response = new Response();
		}

		public function get($id_seg_log) {
			$this->response->result = $this->db
				->from($this->table)
				->where('id_seg_log', $id_seg_log)
				->fetch();

			if($this->response->result)	return $this->response->SetResponse(true);
			else	return $this->response->SetResponse(false, 'no existe el registro');
		}

		public function getAll() {
			$this->response->result = $this->db
				->from($this->table)
				->fetchAll();

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) Total')
				->fetch()
				->Total;

			return $this->response->SetResponse(true);
		}

		public function getByUsuario($fk_usuario, $since=null, $to=null) {
			$usuario = $fk_usuario == 0 ? '1=1' : 'fk_usuario = '.$fk_usuario;
			
			$this->response->result = $this->db
				->from($this->table)
				->select("$this->table.fecha,
						CONCAT_WS(' ', user1.nombre, user1.apellidos) AS colaborador,
						$this->table.descripcion,
						IF($this->table.tabla = 'cita', (SELECT CONCAT(DATE_FORMAT(cita.fecha, '%d/%m/%Y'), ' ', cita.inicio, ' ', mascota.nombre, ' ', mascota.apellidos)),
						IF($this->table.tabla = 'cita_pension', (SELECT CONCAT(DATE_FORMAT(cita_pension.fecha_llegada, '%d/%m/%Y'), ' ', pet1.nombre, ' ', pet1.apellidos)),
    					IF($this->table.tabla = 'det_prod_entrada', (SELECT prod_entrada.folio),
    					IF($this->table.tabla = 'documento', (SELECT documento.titulo),
    					IF($this->table.tabla = 'gastos_caja', (SELECT gastos_caja.concepto),
    					IF($this->table.tabla = 'historia_clinica', (SELECT CONCAT(pet2.nombre, ' ', pet2.apellidos, ' ', historia_clinica.titulo)),
						IF($this->table.tabla = 'mascota', (SELECT CONCAT(pet3.nombre, ' ', pet3.apellidos)),
    					IF($this->table.tabla = 'pago', (SELECT CONCAT('Folio ', visit.comprobante, ' Propietario ', u.nombre, ' ', u.apellidos, ' Cantidad $ ', pago.cantidad)), 
    					IF($this->table.tabla = 'prod_entrada_pago', (SELECT CONCAT('Folio', ' ', entrada.folio)),
    					IF($this->table.tabla = 'producto',(SELECT product.nombre),
    					IF($this->table.tabla = 'propietario', (SELECT CONCAT(user3.nombre, ' ', user3.apellidos)),
    					IF($this->table.tabla = 'receta', (SELECT CONCAT('Mascota', ' ', pet.nombre, ' ', pet.apellidos, ' ', 'Folio', ' ', receta.id)),
    					IF($this->table.tabla = 'requisicion', (SELECT CONCAT('Folio', ' ', requisicion.folio)),
    					IF($this->table.tabla = 'visita', (SELECT CONCAT('Folio', ' ', visita.comprobante, ' ', 'Propietario', ' ', user4.nombre, ' ', user4.apellidos)),
    					IF($this->table.tabla = 'det_venta', (SELECT CONCAT_WS(' ', 'Folio', visita1.comprobante, 'Producto', producto.nombre, 'Cantidad', det_venta.cantidad, 'Total con IVA $', det_venta.total)),
						' '))))))))))))))) AS adicional")
				->leftJoin('usuario AS user1 ON user1.id = seg_log.usuario_id')
				->leftJoin('cita ON cita.id = seg_log.registro')
				->leftJoin('mascota ON mascota.id = cita.mascota')
				->leftJoin('cita_pension ON cita_pension.id = seg_log.registro')
				->leftJoin('mascota AS pet1 ON pet1.id = cita_pension.mascota_id')
				->leftJoin('det_prod_entrada ON det_prod_entrada.id = seg_log.registro')
				->leftJoin('prod_entrada ON prod_entrada.id = det_prod_entrada.prod_entrada_id')
				->leftJoin('documento ON documento.id = seg_log.registro')
				->leftJoin('gastos_caja ON gastos_caja.id = seg_log.registro')
				->leftJoin('historia_clinica ON historia_clinica.id = seg_log.registro')
				->leftJoin('mascota AS pet2 ON pet2.id = historia_clinica.mascota_id')
				->leftJoin('mascota AS pet3 ON pet3.id = seg_log.registro')
				->leftJoin('pago ON pago.id = seg_log.registro')
				->leftJoin('propietario prop ON prop.id = pago.propietario_id')
				->leftJoin('usuario u ON u.id = prop.usuario_id')
				->leftJoin('visita AS visit ON visit.id = pago.visita_id')
				->leftJoin('prod_entrada_pago ON prod_entrada_pago.id = seg_log.registro')
				->leftJoin('prod_entrada AS entrada ON entrada.id = prod_entrada_pago.prod_entrada_id')
				->leftJoin('producto AS product ON product.id = seg_log.registro')
				->leftJoin('propietario ON propietario.id = seg_log.registro')
				->leftJoin('usuario AS user3 ON user3.id = propietario.usuario_id')
				->leftJoin('receta ON receta.id = seg_log.registro')
				->leftJoin('mascota AS pet ON pet.id = receta.mascota_id')
				->leftJoin('requisicion ON requisicion.id = seg_log.registro')
				->leftJoin('visita ON visita.id = seg_log.registro')
				->leftJoin('propietario AS prop ON prop.id = visita.propietario_id')
				->leftJoin('usuario AS user4 ON user4.id = prop.usuario_id')
				->leftJoin('det_venta ON det_venta.id = seg_log.registro')
				->leftJoin('visita AS visita1 ON visita1.venta_id = det_venta.venta_id')
				->leftJoin('producto ON producto.id = det_venta.producto_id')
				->where($usuario)
				->where((!is_null($since) && !is_null($to))? "DATE_FORMAT($this->table.fecha, '%Y-%m-%d') BETWEEN '$since' AND '$to'": "TRUE")
				->where("$this->table.mostrar = 1")
				->orderBy('fecha DESC')
				->fetchAll();
				

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) AS total')
				->where($usuario)
				->where((!is_null($since) && !is_null($to))? "DATE_FORMAT(fecha, '%Y-%m-%d') BETWEEN '$since' AND '$to'": "TRUE")
				->where('mostrar = 1')
				->fetch()
				->total;

			return $this->response;
		}

		public function add($descripcion, $registro, $tipo, $mostrar=0){
			if (!isset($_SESSION)) {
				session_start();
			}
			if(isset($_SESSION['usuario'])){
				$user = $_SESSION['usuario']->id;
				$sesion = $_SESSION['id_sesion'];
			}else{
				$user = 1;
				$sesion = 1;
			}
	
			$data = array(
				'usuario_id' => $user, 
				'seg_sesion_id' => $sesion, 
				'fecha' => new Literal('NOW()'), 
				'descripcion' => $descripcion, 
				'registro' => $registro, 
				'mostrar' => $mostrar,
				'tabla' => $tipo);
			try {
				$this->response->result = $this->db
					->insertInto($this->table, $data)
					->execute();

				if($this->response->result != 0){
					$this->response->SetResponse(true, 'id_seg_log del registro: '.$this->response->result);    	
					$dataSesion = array('finalizada' => new Literal('NOW()'));
					$this->db->update('seg_sesion', $dataSesion, $_SESSION['id_sesion'])->execute();
				}
				else { $this->response->SetResponse(false, 'no se inserto el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: add model seg_log');
			}

			return $this->response;
		}

		public function edit($data, $id_seg_log) {
			try {
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id_seg_log', $id_seg_log)
					->execute();

				if($this->response->result!=0)	$this->response->SetResponse(true, 'id_seg_log actualizado: '.$id_seg_log);
				else { $this->response->SetResponse(false, 'no se edito el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: edit model seg_log');
			}

			return $this->response;
		}

		public function del($id_seg_log) {
			try {
				$this->response->result = $this->db
					->deleteFrom($this->table)
					->where('id_seg_log', $id_seg_log)
					->execute();

				if($this->response->result!=0)	$this->response->SetResponse(true, 'id_seg_log eliminado: '.$id_seg_log);
				else	$this->response->SetResponse(false, 'no se elimino el registro');

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: del model seg_log');
			}

			return $this->response;
		}
	}
?>