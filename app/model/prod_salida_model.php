<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class ProdSalidaModel {
		private $db;
		private $table = 'prod_salida'; 
		private $tableDPS = 'det_prod_salida'; 
		private $response;
		
		public function __CONSTRUCT($db) {
			require_once './core/defines.php';
			$this->db = $db;
		}

		public function getSiguienteFolio() {
			$this->response = new Response();
			$resultado = $this->db
				->from($this->table)
				->select(NULL)->select('CAST(folio AS SIGNED) AS folio')
				->orderBy('CAST(folio AS SIGNED) DESC')
				->fetch();

			return str_pad(($resultado? $resultado->folio+1: 1), 20, '0', STR_PAD_LEFT);
		}

        public function getByVenta($venta_id) {
			$this->response = new Response();
			$this->response->result = $this->db
				->from($this->table)
				->where('venta_id', $venta_id)
				->where('status', 1)
				->fetch();

			if($this->response->result) { $this->response->SetResponse(true); }
			else { $this->response->SetResponse(false, 'no existe el registro'); }

			return $this->response;
		}

        public function getDetBySalida($prod_salida_id, $producto_id=0) {
			$this->response = new Response();
			$this->response->result = $this->db
				->from($this->tableDPS)
				->where('prod_salida_id', $prod_salida_id)
				->where($producto_id==0? "true": "producto_id = $producto_id")
				->where("status", 1)
				->fetchAll();

			$this->response->total = $this->db
				->from($this->tableDPS)
				->select(NULL)->select('COUNT(*) AS total')
				->where('prod_salida_id', $prod_salida_id)
				->where($producto_id==0? "true": "producto_id = $producto_id")
				->where("status", 1)
				->fetch()
				->total;

			return $this->response->SetResponse(true);
		}

        public function add($data) {
			$this->response = new Response();
			try{
				$resultado = $this->db
					->insertInto($this->table, $data)
					->execute();

				if($resultado) { $this->response->SetResponse(true, "id del registro: $resultado"); }
				else { $this->response->SetResponse(false, 'no se inserto el registro'); }
				$this->response->result = $resultado;
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: add model $this->table");
			}

			return $this->response;
		}

        public function edit($data, $id) {
			$this->response = new Response();
			try{
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();

				if($this->response->result) { $this->response->SetResponse(true, "id actualizado: $id"); }
				else { $this->response->SetResponse(false, 'no se edito el registro'); }
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: edit prod_salida");
			}

			return $this->response;
		}

        public function addDetalle($data) {
			$this->response = new Response();
			try{
				$resultado = $this->db
					->insertInto($this->tableDPS, $data)
					->execute();

				if($resultado) { $this->response->SetResponse(true, "id del registro: $resultado"); }
				else { $this->response->SetResponse(false, 'no se inserto el registro'); }
				$this->response->result = $resultado;
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: add model $this->table");
			}

			return $this->response;
		}

		public function delProd($idDetProdSalida) {
			$this->response = new Response();
			try{
				$data['status'] = 0;
				$this->response->result = $this->db
					->update($this->tableDPS, $data)
					->where('id', $idDetProdSalida)
					// ->where('producto_id', $producto_id)
					->execute();

				if($this->response->result) { $this->response->SetResponse(true); }
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: del det_prod_salida");
			}
			
			return $this->response;
		}

		public function detProdSalida($prod_salida_id, $producto_id) {
			$this->response = new Response();
			try{
				$this->response->result = $this->db
					->from($this->tableDPS)
					->where('prod_salida_id', $prod_salida_id)
					->where('producto_id', $producto_id)
					->where('status', 1)
					->limit(1)
					->fetch();

				if($this->response->result) { $this->response->SetResponse(true); }
				else { $this->response->SetResponse(false, 'no existe el registro'); }
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: del model $this->table");
			}
			
			return $this->response;
		}
	}
?>