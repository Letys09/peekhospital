<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;
	use Slim\Http\UploadedFile;
	use PHPMailer\PHPMailer\PHPMailer;
	use PHPMailer\PHPMailer\Exception;
	require '../vendor/autoload.php';

	class UsuarioModel {
		private $db;
		private $table = 'usuario';
		private $tableC = 'colaborador';
		private $tableProp = 'propietario';
		private $tableP = 'seg_permiso';
		private $tableA = 'seg_accion';
		private $tableM = 'seg_modulo';
		private $response;
		
		public function __CONSTRUCT($db) {
			$this->db = $db;
			$this->response = new Response();
		}

		public function login($passcode) {
			$usuario = $this->db
				->from($this->table)
				->select(null)
				->select("$this->table.id, CONCAT_WS(' ', nombre, apellidos) AS nombre, $this->tableC.id as colaborador_id")
				->innerJoin("$this->tableC ON $this->tableC.usuario_id = $this->table.id")
				->where('passcode', $passcode)
				->where('status', 1)
				->fetch();

			if(is_object($usuario)) {				
				$this->addSessionLogin($usuario);
				$this->response->SetResponse(true, 'Switch Code correcto'); 
			} else {
				$this->response->SetResponse(false, 'Switch Code incorrecto'); 
			}

			$this->response->result = $usuario;
			return $this->response;
		}

		public function addSessionLogin($usuario){
			$browser = $_SERVER['HTTP_USER_AGENT'];
			$ipAddr = $_SERVER['REMOTE_ADDR'];
	
			if (!isset($_SESSION)) { session_start(); }
			$_SESSION['ip']  = $ipAddr;
			$_SESSION['navegador']  = $browser;
			$_SESSION['usuario']  = $usuario;
		}

		public function getPropietario($id){
			$this->response = new Response();
			$this->response->result = $this->db
				->from($this->tableProp)
				->select(null)
				->select("CONCAT_WS(' ', usuario.nombre, usuario.apellidos) AS nombre")
				->where("$this->tableProp.id", $id)
				->fetch();
			return $this->response->setResponse(true);
		}

		public function getColaborador($id){
			$this->response = new Response();
			$this->response->result = $this->db
				->from($this->tableC)
				->select(null)
				->select("CONCAT_WS(' ', usuario.nombre, usuario.apellidos) AS nombre")
				->where("$this->tableC.id", $id)
				->fetch();
			return $this->response->setResponse(true);
		}

	}
?>