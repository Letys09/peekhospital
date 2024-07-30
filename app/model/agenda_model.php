<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;
	use Slim\Http\UploadedFile;
	use PHPMailer\PHPMailer\PHPMailer;
	use PHPMailer\PHPMailer\Exception;
	require '../vendor/autoload.php';

	class AgendaModel {
		private $db;
		private $table = 'agenda';
		private $tableP = 'producto';
		private $tableM = 'mascota';
		private $tableProp = 'propietario';
		private $tableU = 'usuario';
		private $tableC = 'colaborador';
		private $tableDV = 'det_venta';
		private $tableFP = 'farmacia_paquete';
		private $tableR = 'rasurado';
		private $tableV = 'visita';
		private $tblVenta = 'venta';
		private $response;
		
		public function __CONSTRUCT($db) {
			$this->db = $db;
			$this->response = new Response();
		}

        public function get($tipo){
			$this->response = new Response();
			switch ($tipo) {
				case 'cirugia': $where = "$this->tableP.categoria_id = 9"; break;
				case 'procedimiento': $where = "$this->tableP.categoria_id = 11"; break;
				case 'anestesia': $where = "$this->tableP.categoria_id in(9, 11)"; break;
				default: $where = TRUE; break;
			}
            $this->response->result = $this->db
				->from($this->table)
				->select(null)
				->select("$this->table.id, $this->table.fecha, $this->table.hora, $this->tableP.nombre AS producto,  CONCAT_WS(' ', $this->tableM.nombre, $this->tableM.apellidos) AS mascota, 
						  CONCAT_WS(' ', medico.nombre, medico.apellidos) AS colaborador, CONCAT_WS(' ', prop.nombre, prop.apellidos) AS propietario")
				->leftJoin("producto ON producto.id = agenda.producto_id ")
				->innerJoin("$this->tableDV ON $this->tableDV.id = $this->table.det_venta_id")
				->innerJoin("$this->tableM ON $this->tableM.id = $this->tableDV.mascota_id")
				->innerJoin("$this->tableC ON $this->tableC.id = $this->tableDV.colaborador_id")
				->innerJoin("$this->tableU AS medico ON medico.id = $this->tableC.usuario_id")
				->innerJoin("$this->tableProp ON $this->tableProp.id = $this->tableDV.propietario_id")
				->innerJoin("$this->tableU AS prop ON prop.id = $this->tableProp.usuario_id")
				->where($where)
				->where("$this->table.fecha", date('Y-m-d'))
				->where("$this->table.status", 1)
				->orderBy("$this->table.hora ASC")
				->fetchAll();
			if($this->response->result) return $this->response->setResponse(true);
			else return $this->response->setResponse(false, 'Sin registros en agenda');
        }

		public function getHospital(){
			$this->response = new Response();
			$this->response->result = $this->db
				->from($this->tableM)
				->select(null)
				->select("$this->tableM.id AS mascota_id, CONCAT_WS(' ', $this->tableM.nombre, $this->tableM.apellidos) AS mascota, CONCAT_WS(' ', prop.nombre, prop.apellidos) AS propietario")
				->innerJoin("$this->tableProp ON $this->tableProp.id = $this->tableM.propietario_id")
				->innerJoin("$this->tableU AS prop ON prop.id = $this->tableProp.usuario_id")
				->where("mascota.hospitalizado", 1)
				->where("mascota.status", 1)
				->where("mascota.fallecido", 0)
				->fetchAll();

			if($this->response->result) return $this->response->setResponse(true);
			else return $this->response->setResponse(false, 'Sin registros de hospital');
		}

		public function getVisitas() {
			$rasurado = $this->db->from($this->tableR)->where('id', 1)->fetch()->status;
			$fecha = date('Y-m-d');
			$resultado = $this->db
				->from($this->tableV)
				->select(null)
				->select("visita.id, propietario_id, DATE_FORMAT(fecha_inicio, '%Y-%m-%d') AS fecha, comprobante, venta_id, visita.colaborador_inicio_id ")
				->where("rasurado <= $rasurado")
				->where("DATE_FORMAT(fecha_inicio, '%Y-%m-%d') = '$fecha'")
				->where('visita.status', 1)
				->orderBy('fecha_inicio DESC')
				->fetchAll();
			return $resultado;
		}

		public function getFarmaciaPaq($id){
			$this->response = new Response();
			$this->response->result = $this->db
				->from($this->table)
				->select(null)
				->select("farmacia_paquete_id, $this->tableFP.visita_id")
				->where("MD5($this->table.id)", $id)
				->fetch();
			if($this->response->result) return $this->response->setResponse(true);
			else return $this->response->setResponse(false, 'No hay registros con esa información');
		}

		public function getBy($id){
			$this->response = new Response();
            $this->response->result = $this->db
				->from($this->table)
				->select(null)
				->select("$this->table.id, $this->tableP.nombre AS producto, $this->tableM.id AS mascota_id,  CONCAT_WS(' ', $this->tableM.nombre, $this->tableM.apellidos) AS mascota, 
						  CONCAT_WS(' ', medico.nombre, medico.apellidos) AS colaborador, prop.id AS propietario_id, CONCAT_WS(' ', prop.nombre, prop.apellidos) AS propietario")
				->leftJoin("producto ON producto.id = agenda.producto_id ")
				->innerJoin("$this->tableDV ON $this->tableDV.id = $this->table.det_venta_id")
				->innerJoin("$this->tableM ON $this->tableM.id = $this->tableDV.mascota_id")
				->innerJoin("$this->tableC ON $this->tableC.id = $this->tableDV.colaborador_id")
				->innerJoin("$this->tableU AS medico ON medico.id = $this->tableC.usuario_id")
				->innerJoin("$this->tableProp ON $this->tableProp.id = $this->tableDV.propietario_id")
				->innerJoin("$this->tableU AS prop ON prop.id = $this->tableProp.usuario_id")
				->where("MD5($this->table.id)", $id)
				->fetch();
			if($this->response->result) return $this->response->setResponse(true);
			else return $this->response->setResponse(false, 'No existe el registro con id '.$id);
        }

	}
?>