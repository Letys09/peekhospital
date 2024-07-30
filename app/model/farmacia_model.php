<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;
	use App\Lib\JWT;
use Envms\FluentPDO\Literal;

class FarmaciaModel {
		private $db;
		private $tblLog = 'farmacia_log';
		private $tblControl = 'prod_controlado';
		private $tblPaq = 'farmacia_paquete';
		private $response;
		//private $FBapiKey = 'AAAAI8hXknk:APA91bFFR2KNXx5gKSU6p5ZXy8zGW59k8qmN4PBWOu1CKoYaLUSJRX05kM2mb2giu__7nFLpUXqmgl7fHrUAj-gGNuMAiqp-6Pdhu-MRmwMDW0eH5krDMYjc5w2NlGzskkC_uS2uyrT6';
		private $FBapiKey = 'AAAAWb6Mrc8:APA91bEXXKbq7v3xOnKMNM6N9Qs_Z3rwIfYiYNGoZlZLTSasphTD8zmkDQ-UVyOVo015xSul4Vw8nyh0W0U-rvVvx1z0236UBB6q7Q-bl-rby-rtLHNc8KiJhEt_RB9p3KEy5v7es75y';
		
		public function __CONSTRUCT($db) {
			require_once './core/defines.php';
			$this->db = $db;
			$this->response = new Response();
		}

		public function getByVenta($venta, $tipo=1) {
			$resultado = $this->db
				->from($this->tblLog)
				->where("det_venta_id = $venta")
				->where("farmacia_log.tipo = $tipo")
				->where('status != 0')
				->fetchAll();

			return $resultado;
		}

		public function asignaPaquete($id, $visita){
			$data = array(
				'visita_id' => $visita, 
				'fecha_visita' => new Literal('NOW()'), 
				'status' => 2,
			);
			try {
				$this->response->result = $this->db
					->update($this->tblPaq, $data)
					->where('id', $id)
					->execute();

				if($this->response->result!=0) {
					$this->response->SetResponse(true, "id actualizado: $id");
				} else { $this->response->SetResponse(false, 'no se edito el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: edit farmacia_paquete');
			}

			return $this->response;
		}

		public function sendPush($tipo=1) {

			$data = [ 'tipo'=>$tipo ];
			$fields = [ 'to'=>"/topics/farmacia", 'data'=>$data ];

			$url = 'https://fcm.googleapis.com/fcm/send';
			$headers = [ 'Authorization: key=' . $this->FBapiKey, 'Content-Type: application/json' ];

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));

			$result = curl_exec($ch);
			if ($result === FALSE) {
				die('Curl failed: ' . curl_error($ch));
				return 'Curl fallo, push fallo';
			}
			curl_close($ch);

			return $result;
		}

        public function add($data) {
			try {
				$registro = $this->db
					->insertInto($this->tblLog, $data)
					->execute();

				if($registro != 0) {
					$this->response->SetResponse(true, 'id del registro: '.$registro);
				} else { $this->response->SetResponse(false, 'no se inserto el registro'); }

				$this->response->result = $registro;

			} catch(\PDOException $ex) {
				$this->response->result = false;
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: add farmacia');
			}

			return $this->response;
		}

        public function addControlado($data) {
			$registro = 0;
			try {
				$registro = $this->db
					->insertInto($this->tblControl, $data)
					->execute();

				if($registro != 0) {
					$this->response->SetResponse(true, 'id del registro: '.$registro);
				} else { $this->response->SetResponse(false, 'no se inserto el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: add model farmacia.'.$this->tblControl);
			}

			$this->response->result = $registro;
			return $this->response;
		}

        public function editPaquete($id, $data){
			try {
				$this->response->result = $this->db
					->update($this->tblPaq, $data)
					->where('id', $id)
					->execute();

				if($this->response->result!=0) {
					$this->response->SetResponse(true, "id actualizado: $id");
				} else { $this->response->SetResponse(false, 'no se edito el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: edit model farmacia_paquete.'.$this->tblControl);
			}

			return $this->response;
		}

		public function editByVenta($data, $venta, $tipo=1) {
			try {
				$this->response->result = $this->db
					->update($this->tblLog, $data)
					->where('det_venta_id', $venta)
					->where('farmacia_log.tipo', $tipo)
					->execute();

				if($this->response->result!=0) {
					$this->response->SetResponse(true, "id actualizado: $venta");
				} else { $this->response->SetResponse(false, 'no se edito el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: edit model seg_sesion.'.$this->tblLog);
			}

			return $this->response;
		}

		public function editControladoByVenta($data, $venta) {
			try {
				$this->response->result = $this->db
					->update($this->tblControl, $data)
					->where('det_venta_id', $venta)
					->execute();

				if($this->response->result!=0) {
					$this->response->SetResponse(true, "id actualizado: $venta");
				} else { $this->response->SetResponse(false, 'no se edito el registro '.$venta); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: edit model seg_sesion.'.$this->tblControl);
			}

			return $this->response;
		}

		public function delByDetVenta($detVenta, $tipo=1){
			$resultado = $this->db
						->update($this->tblLog, array('status' => 0))
						->where('det_venta_id',$detVenta)
						->where('tipo',$tipo)
						->where('status',1)
						->execute();
			return $resultado;
		}
	}
?>