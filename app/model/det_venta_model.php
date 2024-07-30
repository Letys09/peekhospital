<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response,
		Envms\FluentPDO\Literal;
	require_once './core/defines.php';

	class DetVentaModel {
		private $db;
		private $table = 'det_venta';
		private $tableV = 'venta'; 
		private $tableP = 'producto';
		private $response;

		public function __CONSTRUCT($db) {
			$this->db = $db;
			// $this->response = new Response();
		}

        public function getByVenta($venta_id, $producto_id=0) {
			$this->response = new Response();
			$this->response->result = $this->db
				->from($this->table)
				->select(null)->select("$this->tableP.*, $this->table.*, $this->table.iva AS iva, $this->table.id AS detVentaId")
				->innerJoin("$this->tableP ON $this->table.producto_id = $this->tableP.id")
				->where($venta_id!=0? "venta_id = $venta_id": "true")
				->where(!is_array($producto_id)? ($producto_id==0? "true": "producto_id = $producto_id"): "producto_id IN (".implode(',', $producto_id).")")
				->where("$this->table.status", 1)
				->fetchAll();

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select("COUNT(*) AS total")
				->where($venta_id!=0? "venta_id = $venta_id": "true")
				->where(!is_array($producto_id)? ($producto_id==0? "true": "producto_id = $producto_id"): "producto_id IN (".implode(',', $producto_id).")")
				->where("status", 1)
				->fetch()
				->total;

			return $this->response->SetResponse(true);
		}

		public function get($id) {
			$this->response = new Response();
			$this->response->result = $this->db
				->from($this->table)
				->where('id', $id)
				->fetch();

			if($this->response->result) $this->response->SetResponse(true);
			else { $this->response->SetResponse(false, 'no existe el registro'); }

			return $this->response;
		}
        
        public function add($data) {
			$this->response = new Response();
			$data['fecha'] = new Literal('NOW()');
			try{
				$this->response->result = $this->db
					->insertInto($this->table, $data)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, 'id del registro: '.$this->response->result); }
				else { $this->response->SetResponse(false, 'no se inserto el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: add model $this->table");
			}

			return $this->response;
		}

		public function edit($data, $id) {
			$this->response = new Response();
			try{
				$orgInfo = $this->get($id)->result; $areTheSame = true;
				foreach($orgInfo as $field => $value) { if(isset($data[$field]) && $data[$field] != $value) { $areTheSame = false; break; } }
				if(!$areTheSame) {
					$this->response->result = $this->db
						->update($this->table, $data)
						->where('id', $id)
						->execute();
				} else { $this->response->result = true; }

				if($this->response->result!=0)	{ $this->response->SetResponse(true, "id actualizado: $id"); }
				else { $this->response->SetResponse(false, 'no se edito el registro '.$id); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: edit model $this->table");
			}

			return $this->response;
		}

		public function del($id) {
			$this->response = new Response();
			try{
				$data['status'] = 0;
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();

				if($this->response->result!=0)	{ 
					$this->response->SetResponse(true, "id baja: $id"); 
					$this->db->update('visita_belleza', $data)
					->where('det_venta_id', $id)
					->execute();
				}
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: del det_venta");
			}
			return $this->response;
		}

	}
?>