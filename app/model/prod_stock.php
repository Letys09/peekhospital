<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class ProdStockModel {
		private $db;
		private $table = 'prod_stock'; 
		private $response;
		
		public function __CONSTRUCT($db) {
			require_once './core/defines.php';
			$this->db = $db;
			// $this->response = new Response();
		}
	
		/*** get  por ID ***/
		public function get($id) {
			$this->response = new Response();
			$this->response->result = $this->db
				->from($this->table)
				->where('id', $id)
				->fetch();

			if($this->response->result)	return $this->response->SetResponse(true,' ');
			else	return $this->response->SetResponse(false,'no existe el registro');

			return $this->response;
		}// fin de get

		/*** find ***/
		public function find($filtro) {
			$this->response = new Response();
			$this->response->result = $this->db
				->from($this->table)
				->select(NULL)->select('producto_id, colaborador_id, fecha, tipo, inicial, cantidad, final')
				->where("CONCAT_WS(' ', producto_id, colaborador_id, fecha, tipo, inicial, cantidad, final) LIKE ?" , "%$filtro%")
				->fetchAll();

			return $this->response->SetResponse(true);
		}//fin find

		/*** getAll ***/
		public function getAll() {
			$this->response = new Response();
			$this->response->result = $this->db
				->from($this->table)
				->where('status', 1)
				->fetchAll();

			$this->response->total = $this->db
				->from($this->table)->select(null)
				->select('COUNT(*) Total')
				->where('status', 1)
				->fetch()
				->Total;

			return $this->response->SetResponse(true);
		}// fin de getAll 

		/*** Ruta para obtener el stock por medio del producto **/
		public function getByProducto($producto_id, $inicio='2000-01-01', $fin=null) {
			$this->response = new Response();
			$fin = $fin!=null? $fin: date('Y-m-d');
			$this->response->result = $this->db
				->from($this->table)
				->where("producto_id", $producto_id)
				->where("CAST(fecha AS DATE) BETWEEN '$inicio' AND '$fin'")
				->where("status", 1)
				->orderBy("fecha DESC, id DESC")
				->fetchAll();

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) Total')
				->where('producto_id', $producto_id)
				->where("CAST(fecha AS DATE) BETWEEN '$inicio' AND '$fin'")
				->where('status', 1)
				->fetch()
				->Total;

			return $this->response->SetResponse(true);
		}// fin de getAll 

		public function getByOrigen($producto_id, $prod_entrada_id, $tipo_origen) {
			$this->response = new Response();
			$this->response->result = $this->db
				->from($this->table)
				->where('producto_id', $producto_id)
				->where('origen', $prod_entrada_id)
				->where('origen_tipo', $tipo_origen)
				->where('status', 1)
				->fetch();

			if($this->response->result) { $this->response->SetResponse(true); }
			else { $this->response->SetResponse(false, 'no existe el registro'); }

			return $this->response;
		}

		public function arreglaStock($producto_id, $prod_stock_id) {
			$this->response = new Response();
			try {
				$data_stock = $this->db
					->from($this->table)
					->where('producto_id', $producto_id)
					->where("id > $prod_stock_id")
					->where('status', 1)
					->orderBy('fecha ASC')
					->fetchAll();
				$regInicial = $this->db
					->from($this->table)
					->where('id', $prod_stock_id)
					->fetch();

				$tipo = intval($regInicial->tipo); $inicial = intval($regInicial->inicial); $cantidad = intval($regInicial->cantidad); $final = intval($regInicial->final); $status = intval($regInicial->status);
				// if($inicial+$cantidad != $final) {
				// 	$actualizacion = $this->db
				// 		->update($this->table, ['final' => $inicial+$cantidad])
				// 		->where('id', $prod_stock_id)
				// 		->execute();
				// 	if(!$actualizacion) { return $this->response->SetResponse(false); }
				// }
				// $inicial = $status == 0? $inicial: $inicial + ($tipo * $cantidad);
				$inicial = $status == 0? $inicial: $final;
				foreach($data_stock as $stock) {
					$cantidad = intval($stock->cantidad); $tipo = intval($stock->tipo); $final = $inicial + ($tipo * $cantidad);
					$actualizacion = $this->db
						->update($this->table, ['inicial'=>$inicial, 'final'=>$final])
						->where('id', $stock->id)
						->execute();
					if($actualizacion) { $inicial = $final; }
					else { return $this->response->SetResponse(false); }
				}

				$this->response->result = $inicial;
				$this->response->SetResponse(true);
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false);
			}

			return $this->response;
		}

		/*** add ***/
		public function add($data) {
			$this->response = new Response();
			$data['fecha'] = date("Y-m-d H:i:s");
			try{
				$this->response->result = $this->db
					->insertInto($this->table, $data)
					->execute();

				if($this->response->result!=0)	$this->response->SetResponse(true, 'id del registro: '.$this->response->result);    
				else { $this->response->SetResponse(false, 'no se inserto el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: add model prod_stock $ex");
			}

			return $this->response;
		}//fin de add

		/*** edit ***/
		public function edit($data, $id) {
			$this->response = new Response();
			try{
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();

				if($this->response->result!=0)	$this->response->SetResponse(true, 'id actualizado: '.$id);    
				else { $this->response->SetResponse(false, 'no se edito el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: edit model prod_stock');
			}

			return $this->response;
		}//fin de edit

		/*** del ***/
		public function del($id) {
			$this->response = new Response();
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
				$this->response->SetResponse(false, 'catch: del model prod_stock');
			}

			return $this->response;
		}//fin de del

		public function getByProdControlado($prod_id=0, $inicio, $fin) {
			$this->response = new Response();
			$fin = $fin!=null? $fin: date('Y-m-d');
			$prod = $prod_id == 0 ? "TRUE" : "prod_stock.producto_id = $prod_id";
			$this->response->result = $this->db->getPdo()
			->query("SELECT prod_stock.id, prod_stock.producto_id, prod_stock.inicial, prod_stock.cantidad, prod_stock.final, prod_stock.fecha, DATE_FORMAT(prod_stock.fecha, '%d/%m/%Y') AS fecha_mov, 
						prod_stock.origen, prod_stock.origen_tipo, prod_stock.det_venta_id, CONCAT_WS(' ', producto.nombre, producto.marca) as nombre, 
						producto.pres_comercial,producto.sagarpa, producto.principio_act, DATE_FORMAT(prod_stock.fecha, '%Y-%m-%d') AS date 
					FROM prod_stock   
					INNER JOIN producto ON producto.id = prod_stock.producto_id    
					INNER JOIN det_venta ON det_venta.id = prod_stock.det_venta_id    
					WHERE 
					DATE_FORMAT(prod_stock.fecha, '%Y-%m-%d') BETWEEN '$inicio' AND '$fin'    
						AND $prod   
						AND prod_stock.status = 1    
						AND producto.uso_controlado = 1    
						AND (prod_stock.origen_tipo IN (2,7,15,16))
						AND prod_stock.det_venta_id IS NOT NULL
						AND det_venta.status = 1
					UNION
					SELECT prod_stock.id, prod_stock.producto_id, prod_stock.inicial, prod_stock.cantidad, prod_stock.final, prod_stock.fecha, DATE_FORMAT(prod_stock.fecha, '%d/%m/%Y') AS fecha_mov, 
						prod_stock.origen, prod_stock.origen_tipo, prod_stock.det_venta_id, CONCAT_WS(' ', producto.nombre, producto.marca) as nombre, 
						producto.pres_comercial,producto.sagarpa, producto.principio_act, DATE_FORMAT(prod_stock.fecha, '%Y-%m-%d') AS date 
					FROM prod_stock   
					INNER JOIN producto ON producto.id = prod_stock.producto_id       
					WHERE DATE_FORMAT(prod_stock.fecha, '%Y-%m-%d') BETWEEN '$inicio' AND '$fin'    
						AND $prod   
						AND prod_stock.status = 1    
						AND producto.uso_controlado = 1    
						AND prod_stock.origen_tipo = 11
					ORDER BY 
					fecha ASC, 
					id ASC;")
			->fetchAll();

			return $this->response->SetResponse(true);
		}

		public function getKardex($producto_id){
			$this->response = new Response();
			$this->response->result = $this->db
				->from($this->table)
				->where("producto_id", $producto_id)
				->where("fecha >= '2024-05-17'")
				->where("origen_tipo in(2,7,15,16)")
				->fetchAll();
			return $this->response->setResponse(true);
		}

		public function getMovimientos($det_venta_id){
			$this->response->result = $this->db
				->from($this->table)
				->select(null)
				->select("inicial, cantidad, final, origen_tipo")
				->where("det_venta_id", $det_venta_id)
				->where("status", 1)
				->fetchAll();
			return $this->response->setResponse(true);
		}

		public function getUltimo($prod_id, $fecha){
			$this->response->result = $this->db
				->from($this->table)
				->where("producto_id", $prod_id)
				->where("DATE_FORMAT(fecha, '%Y-%m-%d') < '$fecha'")
				->orderBy("id DESC")
				->fetch();
			return $this->response->setResponse(true);
		}

		public function getSalida($prod_id, $origen, $fecha){
			$this->response->result = $this->db
				->from($this->table)
				->where('producto_id', $prod_id)
				->where('origen', $origen)
				->where('origen_tipo = 12 OR origen_tipo = 13')
				->where("DATE_FORMAT(fecha, '%Y-%m-%d') >= '$fecha'")
				->fetch();
		
			if($this->response->result) { $this->response->SetResponse(true); }
			else { $this->response->SetResponse(false, 'No hay registro con esos datos'); }

			return $this->response;
		}
	}//fin de prod_stock
?>