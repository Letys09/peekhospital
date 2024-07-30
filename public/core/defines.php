<?php
	use Jose\Component\KeyManagement\JWKFactory;

	ini_set('display_errors',1);

	if(!defined('SITE_NAME')) define('SITE_NAME', 'PeekWeb');
	if(!defined('URL_ROOT')) define('URL_ROOT',  'http://192.168.1.71/peekhospital/public');
	if(!defined('SITE_ROOT')) define('SITE_ROOT',  'http://192.168.1.71/peekhospital/public');
	if(!defined('URL_API')) define('URL_API',  'http://192.168.1.71/peekhospital/public/');
	if(!defined('URL_IMG_DEFAULT'))	define('URL_IMG_DEFAULT',  'http://192.168.1.71/peekhospital/public/assets/image/no_imagen.jpg');

	if (!isset($_SESSION)) session_start();
	date_default_timezone_set('America/Mexico_City');

	$_SESSION['id_sucursal'] = 1;
	$_SESSION['id_almacen'] = 1;
	$_SESSION['id_usuario_venta'] = 1;
	if (!isset($_SESSION['clave'])) { 
		$_SESSION['clave'] = json_encode(JWKFactory::createOctKey(
			1024, // Size in bits of the key. We recommend at least 128 bits.
			[
				'alg' => 'A256KW', // This key must only be used with the A256KW algorithm
				'use' => 'enc' // This key is used for encryption/decryption operations
			]
		));
	}
	$_SESSION['sucursal'] = 1;
?>