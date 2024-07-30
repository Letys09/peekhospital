<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;
	use App\Lib\JWT;
use Envms\FluentPDO\Literal;

class SegSesionModel {
		private $db;
		private $table = 'seg_sesion';
		private $response;
		
		public function __CONSTRUCT($db) {
			require_once './core/defines.php';
			$this->db = $db;
			$this->response = new Response();
		}

		public function get($id) {
			$this->response->result = $this->db
				->from($this->table)
				->where('id', $id)
				->fetch();

			if($this->response->result)	{ return $this->response->SetResponse(true); }
			else { return $this->response->SetResponse(false, 'no existe el registro'); }
		}
		
		public function getByToken($token) {
			$this->response->result = $this->db
				->from($this->table)
				->where('token', $token)
				->fetch();

			if($this->response->result)	{ return $this->response->SetResponse(true); }
			else { return $this->response->SetResponse(false, 'no existe el registro'); }
		}

		public function getByUsuario($usuario_id, $status=1, $since=null, $to=null, $limite=1) {
			$this->response->result = $this->db
				->from($this->table)
				->where('usuario_id', $usuario_id)
				->where((!is_null($since) && !is_null($to))? "CAST(iniciada AS DATE) BETWEEN '$since' AND '$to'": "TRUE")
				->where('status'.($status==0? '>': '=').$status)
				->orderBy('status DESC, iniciada DESC')
				->limit($limite)
				->fetchAll();

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) AS total')
				->where('usuario_id', $usuario_id)
				->where((!is_null($since) && !is_null($to))? "CAST(iniciada AS DATE) BETWEEN '$since' AND '$to'": "TRUE")
				->where('status'.($status==0? '>': '=').$status)
				->fetch()
				->total;

			return $this->response;
		}

		public function add($data) {
			try {
				$sesion = $this->db
					->insertInto($this->table, $data)
					->execute();

				if($sesion != 0) {
					$getSesion = $this->get($sesion)->result;
					$_SESSION['token'] = $getSesion->token;
					$_SESSION['id_sesion'] = $getSesion->id;
					
					$this->response->SetResponse(true, 'id del registro: '.$sesion);
				} else { $this->response->SetResponse(false, 'no se inserto el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: add model seg_sesion');
			}

			$this->response->result = $sesion;
			return $this->response;
		}

		public function edit($data, $id) {
			try {
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();

				if($this->response->result!=0) {
					$sesion = $this->get($id)->result;
					$_SESSION['token'] = $sesion->token;
					$_SESSION['id_sesion'] = $sesion->id;

					$this->response->SetResponse(true, "id actualizado: $id");
				} else { $this->response->SetResponse(false, 'no se edito el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: edit model seg_sesion');
			}

			return $this->response;
		}

		public function del($id) {
			try {
				$this->response->result = $this->db
					->deleteFrom($this->table)
					->where('id', $id)
					->execute();

				if($this->response->result!=0)	$this->response->SetResponse(true, 'id eliminado: '.$id);
				else	$this->response->SetResponse(false, 'no se elimino el registro');

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: del model seg_sesion');
			}

			return $this->response;
		}

		public function crearToken($usuario) {
			$JWT = new JWT();
			$datos = [
				'nbf' => time(),
				// 'exp' => time() + 14400,
				'aud' => SITE_NAME,
				'id_usuario' => $usuario->id,
			];

			return $JWT->crearToken(json_encode($datos));
		}

		public function logout() {
			if(isset($_SESSION['id_sesion'])) {
				$data = [
					'status' => 0,
					'finalizada' => new Literal('NOW()'),
				];
				$this->response = $this->edit($data, $_SESSION['id_sesion']);
			}

			session_unset();
			session_regenerate_id(true);
			session_destroy();

			return $this->response;
		}
	}
?>