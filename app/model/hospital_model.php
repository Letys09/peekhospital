<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;
	use Slim\Http\UploadedFile;

	class HospitalModel {
		private $db;
		private $table = 'hospital'; 
		private $response;
		
		public function __CONSTRUCT($db) {
			$this->db = $db;
			$this->response = new Response();
			require_once './core/defines.php';
		}

		public function getSiguienteComprobante() {
			$resultado = $this->db
				->from($this->table)
				->select(NULL)->select('CAST(comprobante AS SIGNED) AS comprobante')
				->orderBy('CAST(comprobante AS SIGNED) DESC')
				->fetch();

			return str_pad(($resultado? $resultado->comprobante+1: 1), 7, '0', STR_PAD_LEFT);
		}

		public function edit($data, $id) {
			try{
				$this->db->getPdo()->query("SET NAMES utf8mb4;");
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, "id actualizado: $id"); }
				else { $this->response->SetResponse(false, 'no se edito el registro comprobante'); }

			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: edit model $this->table");
			}

			return $this->response;
		}

	}
?>