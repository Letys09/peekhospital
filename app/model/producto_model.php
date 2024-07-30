<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response,
		Envms\FluentPDO\Literal;

	require_once './core/defines.php';

	class ProductoModel {
		private $db;
		private $table = 'producto';
		private $tableC = 'categoria';
		private $tableSub = 'subcategoria';
		private $response;
		
		public function __CONSTRUCT($db) {
			$this->db = $db;
			$this->response = new Response();
		}

        public function get($id){
			$this->response = new Response();
            $this->response->result = $this->db
                ->from($this->table)
                ->select(null)
                ->select("nombre, categoria_id, tipo, unidad, precio, stock, uso_controlado, iva, no_ventas")
                ->where("id", $id)
                ->fetch();
            if(!$this->response->result) $this->response->SetResponse(false, 'no existe el registro');
            else $this->response->SetResponse(true);

            return $this->response;
        }

        public function getAllBusca($inicial, $limite, $busqueda) {
            if ($busqueda != '_') { 
                $busqueda = array_reduce(array_filter(explode(' ', $busqueda), function($bus) { 
                    return strlen($bus) > 0; 
                }), function($imp, $bus) { 
                    return $imp .= "+".str_replace('/', '_', $bus)."* "; 
                }); 
            }
        
            $tbl_name = "hospital_tbl_".time()."_".random_int(0, 999999);
            try {
                $this->response->result = $this->db->getPdo()->query("CALL tbl_hospital('$busqueda', 'no_ventas', $inicial, $limite, '$tbl_name');")->fetchAll();
                $this->response->filtered = count($this->db->getPdo()->query("CALL tbl_hospital('$busqueda', 'no_ventas', 0, 10000, '$tbl_name');")->fetchAll());
                
                // Ejecutar la consulta para el total
                $this->response->total = $this->db->getPdo()
                    ->query("
                            SELECT COUNT(*) AS total 
                            FROM producto 
                            WHERE status = 1
                            AND producto.tipo IN (6, 7, 10);"
                        )->fetch()->total;
                
                return $this->response->SetResponse(true);
            } catch (PDOException $e) {
                // Manejo de errores
                $this->response->SetResponse(false, $e->getMessage());
                return $this->response;
            } finally {
                // Asegúrate de que la tabla temporal se elimine después de su uso
                $this->db->getPdo()->exec("DROP TABLE IF EXISTS $tbl_name;");
            }
        }

        public function edit($data, $id) {
			try{
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();

				if($this->response->result) { $this->response->SetResponse(true); }
				else	$this->response->SetResponse(false, 'no se actualizo el registro del producto '.$id);

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: edit producto $ex");
			}

			return $this->response;
		}
        
	}
?>