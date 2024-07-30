<?php
	use App\Lib\Response,
		App\Lib\MiddlewareToken,
		PHPMailer\PHPMailer\PHPMailer,
		PHPMailer\PHPMailer\Exception,
		Slim\Http\UploadedFile;
		require_once './core/defines.php';

	$app->group('/usuario/', function() use ($app) {

		$this->post('login/', function($request, $response, $arguments) {
			if(!isset($_SESSION)) { session_start(); }
			$parsedBody = $request->getParsedBody();
			$passcode = strrev(md5(sha1($parsedBody['code'])));

			$usuario = $this->model->usuario->login($passcode);
			if($usuario->response) {
				$token = $this->model->seg_sesion->crearToken($usuario->result);
				$data = [
					'usuario_id' => $usuario->result->id,
					'ip_address' => $_SERVER['REMOTE_ADDR'],
					'user_agent' => $_SERVER['HTTP_USER_AGENT'],
					'iniciada' => date('Y-m-d H:i:s'),
					'token' => $token,
				];
				$this->model->seg_sesion->add($data);				

				$this->model->seg_log->add('Inicia hospital', $usuario->result->id, 'usuario');
				$this->logger->info("Slim-Skeleton 'usuario/login/' ".$usuario->result->id);
			}

			return $response->withJson($usuario);
		});

		$this->get('logout', function($request, $response, $arguments) use ($app) {
			if(!isset($_SESSION)) { session_start(); }
			$this->model->seg_sesion->logout();

			return $this->response->withRedirect('../login');
		});

	});

?>