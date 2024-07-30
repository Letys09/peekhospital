<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;
	require_once './core/defines.php';

	class ProdEntradaModel {
		private $db;
		private $table = 'prod_entrada';
		private $tableD = 'det_prod_entrada';
		private $tableP = 'producto';
		private $response;
		
		public function __CONSTRUCT($db) {
			require_once './core/defines.php';
			$this->db = $db;
			// $this->response = new Response();
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
				$this->response->SetResponse(false, "catch: add prod_entrada");
			}

			return $this->response;
		}
        
        public function addDetalle($data) {
			try{
				$resultado = $this->db
					->insertInto($this->tableD, $data)
					->execute();

				if($resultado) { $this->response->SetResponse(true, "id del registro: $resultado"); }
				else { $this->response->SetResponse(false, 'no se inserto el registro'); }
				$this->response->result = $resultado;
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: add det_prod_entrada");
			}

			return $this->response;
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

        public function getByEntrada($prod_entrada_id, $producto_id=0) {
			$this->response->result = $this->db
				->from($this->tableD)
				->select("CONCAT_WS(' ', CASE WHEN codigo IS NOT NULL AND LENGTH(codigo) > 0 THEN CONCAT('(',codigo,')') ELSE '' END, nombre, COALESCE(marca, '')) AS producto, cantidadxcaja")
				->leftJoin("$this->tableP ON $this->tableD.producto_id = $this->tableP.id")
				->where('prod_entrada_id', $prod_entrada_id)
				->where($producto_id==0? "true": "producto_id = $producto_id")
				->where("$this->tableD.status > 0")
				->fetchAll();

			$this->response->total = $this->db
				->from($this->tableD)
				->select(NULL)->select('COUNT(*) AS total')
				->where('prod_entrada_id', $prod_entrada_id)
				->where($producto_id==0? "true": "producto_id = $producto_id")
				->where('status > 0')
				->fetch()
				->total;

			$this->response->descuento = $this->db
				->from($this->tableD)
				->select("SUM(importe - subtotal) as descuento")
				->where('prod_entrada_id', $prod_entrada_id)
				->where($producto_id==0? "true": "producto_id = $producto_id")
				->where('status > 0')
				->fetch()
				->descuento;

			$this->response->devoluciones = $this->db
				->from($this->tableD)
				->select("SUM($this->tableD.total * (1 - ($this->table.descuento / $this->table.importe))) AS devoluciones")
				->leftJoin("$this->table ON $this->table.id = prod_entrada_id")
				->where('prod_entrada_id', $prod_entrada_id)
				->where($producto_id==0? "true": "producto_id = $producto_id")
				->where("$this->tableD.status", 2)
				->fetch()
				->devoluciones;

			return $this->response->SetResponse(true);
		}

        public function edit($data, $id) {
			$this->response = new Response();
			try{
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();

				if($this->response->result)	$this->response->SetResponse(true, "id actualizado: $id");
				else { $this->response->SetResponse(false, 'no se edito el registro'); }
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: edit model $this->table");
			}

			return $this->response;
		}
	}
?>