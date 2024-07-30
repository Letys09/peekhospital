<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;
use Envms\FluentPDO\Literal;

class VisitaModel {
		private $db;
		private $table = 'visita';
		private $tableVB = 'visita_belleza';
		private $tableV = 'venta';
		private $tableD = 'det_venta';
		private $tableProp = 'propietario';
		private $tableCol = 'colaborador';
		private $tableUsu = 'usuario';
		private $tableDR = 'det_receta';
		private $response;

		public function __CONSTRUCT($db) {
			date_default_timezone_set('America/Mexico_City');
			$this->db = $db;
			$this->response = new Response();
		}

		public function get($id) {
			$this->response = new Response();
			$this->response->result = $this->db
				->from($this->table)
				->select(null)
				->select("venta_id")
				->where('id', $id)
				->fetch();

			if($this->response->result) { $this->response->SetResponse(true,' '); }
			else { $this->response->SetResponse(false, 'no existe el registro'); }

			return $this->response;
		}

		public function getBy($id) {
			$this->response = new Response();
			$this->response->result = $this->db
				->from($this->table)
				->select(null)
				->select("id, propietario_id, venta_id")
				->where("MD5(id)", $id)
				->fetch();

			if($this->response->result) { $this->response->SetResponse(true,' '); }
			else { $this->response->SetResponse(false, 'no existe el registro'); }

			return $this->response;
		}

		public function getByVenta($venta_id, $inicio='2000/01/01', $final=null) {
			if($final == null) { $final = date('Y/m/d'); }
			$this->response->result = $this->db
				->from($this->table)
				->where('venta_id', $venta_id)
				->where("DATE_FORMAT(fecha_inicio, '%Y-%m-%d') BETWEEN '$inicio' AND '$final'")
				->where('status > 0')
				->fetchAll();

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) AS total')
				->where('venta_id', $venta_id)
				->where("DATE_FORMAT(fecha_inicio, '%Y-%m-%d') BETWEEN '$inicio' AND '$final'")
				->where('status > 0')
				->fetch()
				->total;

			return $this->response->SetResponse(true);
		}

		public function getVenta($id) {
			$this->response = new Response();
			$this->response->result = $this->db
				->from($this->tableV)
				->where('id', $id)
				->fetch();

			if($this->response->result) { $this->response->SetResponse(true,' '); }
			else { $this->response->SetResponse(false, 'no existe el registro'); }

			return $this->response;
		}

		public function getBelleza($mascota_id){
			$this->response->result = $this->db
				->from($this->tableVB)
				->select(null)
				->select("visita_id")
				->innerJoin("$this->table ON $this->table.id = $this->tableVB.visita_id")
				->where("$this->tableVB.mascota_id", $mascota_id)
				->where("$this->tableVB.status != 0")
				->where("$this->table.status = 1")
				->groupBy("visita_id")
				->fetch();

			if($this->response->result) { $this->response->SetResponse(true); }
			else { $this->response->SetResponse(false, 'no existe registro'); }

			return $this->response;
		}

		public function getBellezaByVisita($visita_id){
			$this->response->result = $this->db
				->from($this->tableVB)
				->select(null)
				->select("mascota_id, CONCAT_WS(' ', nombre, apellidos) AS mascota")
				->innerJoin("mascota ON mascota.id = $this->tableVB.mascota_id")
				->where("$this->tableVB.status != 0")
				->where("$this->tableVB.visita_id", $visita_id)
				->fetch();

			if($this->response->result) { $this->response->SetResponse(true); }
			else { $this->response->SetResponse(false, 'no existe registro'); }

			return $this->response;
		}

		public function getDetalles($venta_id) {
			$this->response = new Response();
			$this->response->result = $this->db
				->from($this->tableD)
				->select(null)
				->select("$this->tableD.id, $this->tableD.producto_id, cantidad, producto.nombre, producto.unidad, det_receta.id AS receta_detalle_id, det_receta.tipo_admin")
				->innerJoin("$this->tableDR ON $this->tableDR.det_venta_id = $this->tableD.id")
				->where("$this->tableD.venta_id", $venta_id)
				->where("$this->tableD.status", 1)
				->fetchAll();

			if($this->response->result) { $this->response->SetResponse(true,' '); }
			else { $this->response->SetResponse(false, 'no hay detalles de venta'); }

			return $this->response;
		}

		public function add($data) {
			try {
				$this->response->result = $this->db
					->insertInto($this->table, $data)
					->execute();

				if($this->response->result) { $this->response->SetResponse(true); }
				else { $this->response->SetResponse(false, 'no se inserto el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: add visita: ".$ex);
			}

			return $this->response;
		}

		public function edit($data, $id) {
			$this->response = new Response();
			try {
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();
					
				if($this->response->result)	{ $this->response->SetResponse(true, 'actualizado'); }
				else { $this->response->SetResponse(false, 'no se edito el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: edit model visita".$ex);
			}

			return $this->response;
		}

		public function addVenta($data) {
			$this->response = new Response();
			try{
				$this->response->result = $this->db
					->insertInto($this->tableV, $data)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, 'id del registro: '.$this->response->result); }
				else { $this->response->SetResponse(false, 'no se inserto el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: add venta");
			}

			return $this->response;
		}
		
		public function editVenta($data, $id) {
			$this->response = new Response();
			$data['fecha_modificacion'] = date('Y-m-d H:i:s');
			try{
				
				$this->response->result = $this->db
					->update($this->tableV, $data)
					->where('id', $id)
					->execute();

				if($this->response->result!=0)	$this->response->SetResponse(true, "id actualizado: $id");
				else { $this->response->SetResponse(false, 'no se edito el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: edit venta");
			}

			return $this->response;
		}

	}
?>