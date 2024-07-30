<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class RecetaModel {
		private $db;
		private $table = 'receta';
		private $tblDet = 'det_receta';
		private $response;
		
		public function __CONSTRUCT($db) {
			$this->db = $db;
			$this->response = new Response();
		}
		
        public function getByVenta($venta_id){
			$this->response->result = $this->db
				->from($this->table)
				->where("venta_id", $venta_id)
				->fetch();
			return $this->response->setResponse(true);
		}

		public function add($data) {
			try{
				$this->response->result = $this->db
					->insertInto($this->table, $data)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, 'id del registro: '.$this->response->result); }
				else { $this->response->SetResponse(false, 'no se inserto el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: add model receta');
			}

			return $this->response;
		}

		public function edit($data, $id) {
			try{
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();

				$this->response->SetResponse(true, 'id actualizado: '.$id);

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: edit model receta');
			}

			return $this->response;
		}
		
		public function del($id) {
			try{
				$data['status'] = 0;
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();

				if($this->response->result!=0)	$this->response->SetResponse(true, 'id baja: '.$id);
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: del model receta');
			}

			return $this->response;
		}

		public function addDetalle($data) {
			try{
				$this->response->result = $this->db
					->insertInto($this->tblDet, $data)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, 'id del registro: '.$this->response->result); }
				else { $this->response->SetResponse(false, 'no se inserto el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: add model det_receta');
			}

			return $this->response;
		}

        public function editDetalle($data, $id) {
			try{
				$this->response->result = $this->db
					->update($this->tblDet, $data)
					->where('id', $id)
					->execute();

				$this->response->SetResponse(true, 'id actualizado: '.$id);

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: edit detalle_receta');
			}

			return $this->response;
		}

		public function getDetalle($receta) {
			$resultado = $this->db
				->from($this->tblDet)
				->select('IFNULL(det_venta.surtido, 0) AS surtido, producto.uso_controlado, producto.stock')
				->where('receta_id', $receta)
				->where('det_receta.status', 1)
				->fetchAll();

			return $resultado;
		}

		public function getDetalleById($id){
			$resultado = $this->db
				->from($this->tblDet)
				->select('IFNULL(det_venta.surtido, 1) AS surtido, producto.uso_controlado, producto.stock')
				->where('det_receta.id', $id)
				->where('det_receta.status', 1)
				->fetch();

			return $resultado;
		}

		public function delDetalle($id) {
			try{
				$data['status'] = 0;
				$this->response->result = $this->db
					->update($this->tblDet, $data)
					->where('id', $id)
					->execute();

				if($this->response->result!=0)	$this->response->SetResponse(true, 'id baja: '.$id);
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: del detalle_receta');
			}

			return $this->response;
		}


	}
?>