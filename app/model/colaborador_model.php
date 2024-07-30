<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class ColaboradorModel {
		private $db;
		private $table = 'colaborador';
		private $tableU = 'usuario';
		private $response;
		
		public function __CONSTRUCT($db) {
			$this->db = $db;
			$this->response = new Response();
		}

		public function get($id, $status=1) {
			$this->response->result = $this->db
				->from($this->table)
				->select(null)->select("colaborador.*, usuario.*, colaborador.id AS id")
				->where('colaborador.id', $id)
				// ->where($status==0? "TRUE": "status > 0")
				->fetch();

			if($this->response->result) $this->response->SetResponse(true);
			else $this->response->SetResponse(false, 'no existe el registro');

			return $this->response;
		}

		public function find($filtro) {
			$this->response->result = $this->db
				->from($this->table)
				->select(null)->select("id_empleado, CONVERT(CAST(CONVERT(nombre USING latin1) AS binary) USING utf8) AS nombre, CONVERT(CAST(CONVERT(apellidos USING latin1) AS binary) USING utf8) AS apellidos, telefono, email, fk_usuario_tipo, fk_sucursal, status")
				->leftJoin("$this->tableU on id_usuario = id_empleado")
				->where("CONCAT_WS(' ', nombre, apellidos, telefono, email) LIKE '%$filtro%'")
				->where("status", 1)
				->fetchAll();
				
			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select("COUNT(*) AS total")
				->leftJoin("$this->tableU on id_usuario = id_empleado")
				->where("CONCAT_WS(' ', nombre, apellidos, telefono, email) LIKE '%$filtro%'")
				->where("status", 1)
				->fetch()
				->total;

			return $this->response->SetResponse(true);
		}

		public function getByUsuario($usuario_id) {
			$this->response->result = $this->db
				->from($this->table)
				->select("$this->tableU.*, $this->table.id")
				->leftJoin("$this->tableU on $this->tableU.id = usuario_id")
				->where('usuario_id', $usuario_id)
				->where('status', 1)
				->fetch();

			if($this->response->result) $this->response->SetResponse(true);
			else $this->response->SetResponse(false, 'no existe el registro');

			return $this->response;
		}

		/*public function getAll($pagina, $limite, $filtro=0) {
			$inicial = $pagina * $limite;
			$filtro = $filtro==0? "_": $filtro;
			$this->response->result = $this->db
				->from($this->table)
				->select(null)->select("id_empleado, CONVERT(CAST(CONVERT(nombre USING latin1) AS binary) USING utf8) AS nombre, CONVERT(CAST(CONVERT(apellidos USING latin1) AS binary) USING utf8) AS apellidos, telefono, email, fk_usuario_tipo, fk_sucursal, status")
				->leftJoin("$this->tableU on id_usuario = id_empleado")
				->where("CONCAT_WS(' ', nombre, apellidos, telefono, email) LIKE '%$filtro%'")
				->where("status", 1)
				->limit("$inicial, $limite")
				->orderBy('apellidos ASC')
				->fetchAll();

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) Total')
				->leftJoin("$this->tableU on id_usuario = id_empleado")
				->where("CONCAT_WS(' ', nombre, apellidos, telefono, email) LIKE '%$filtro%'")
				->where("status", 1)
				->fetch()
				->Total;

			return $this->response->SetResponse(true);
		}*/

		public function getAll() {
			$this->response->result = $this->db
				->from($this->table)
				->select(null)->select("colaborador.*, usuario.*, colaborador.id AS id")
				//->select(null)->select("colaborador.*, usuario.*, direccion.*")
				//->innerJoin('direccion ON direccion.id = usuario.direccion_id')
				->where("status != 0")
				->orderBy('status, CONCAT(nombre,apellidos) ASC')
				->fetchAll();

			return $this->response->SetResponse(true);
		}		

		public function getAllLight() {
			$this->response->result = $this->db
				->from($this->table)
				->select(null)->select("CONCAT(usuario.nombre, ' ', usuario.apellidos) AS nombre, colaborador.id AS id, color")
				->where("status != 0")
				->orderBy('status, CONCAT(nombre,apellidos) ASC')
				->fetchAll();

			return $this->response->SetResponse(true);
		}

		public function add($data) {
			try {
				$this->response->result = $this->db
					->insertInto($this->table, $data)
					->execute();

				if($this->response->result) $this->response->SetResponse(true, 'id del registro: '.$this->response->result);
				else $this->response->SetResponse(false, 'no se inserto el registro');

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: add model empleado');
			}
				
			return $this->response;
		}

		public function edit($data, $id) {
			try {
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();

				$this->response->SetResponse(true);

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: edit model empleado');
			}

			return $this->response;
		}

		public function del($id_empleado) {
			try {
				$data['status'] = 0;
				$this->response = $this->edit($data, $id_empleado);
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: del model empleado');
			}

			return $this->response;
		}

		public function getInfoUser($colaborador_id){
			$this->response->result = $this->db
				->from($this->table)
				->select(null)
				->select("nombre")
				->innerJoin("usuario ON usuario.id = colaborador.usuario_id")
				->where("$this->table.id", $colaborador_id)
				->fetch();
			return $this->response->setResponse(true);
		}
	}
?>