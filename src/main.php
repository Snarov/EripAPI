<?php

define ('API_ROOT_DIR', __DIR__);

require_once API_ROOT_DIR . '/include/EripAPI/EripAPI.php';
require_once API_ROOT_DIR . '/include/EripAPI/DB.php';
require_once API_ROOT_DIR . '/../lib/JsonRPC/Server.php';
include API_ROOT_DIR . '/include/util/Logger.php';

use JsonRPC\Server as Server;

$logger = new Logger;
$logger->addLog('error', API_ROOT_DIR . '/../log/error.log');
$logger->addLog('main', API_ROOT_DIR . '/../log/eripapi.log');
$logger->addLog('debug', API_ROOT_DIR . '/../log/debug.log');
$logger->addLog('access', API_ROOT_DIR . '/../log/access.log');
$logger->debug(true);

try {
    $db = new DB;
    //передем серверу анонимную функцию, производящую аутентификацию пользователя
    $server = new Server( '', function ($username, $password) use ($logger) { 
                                  if( ! $authOK = EripAPI\Security::authenticate($username, $password ) ) {
                                      $logger->write('access',
                                      "({$_SERVER['REMOTE_ADDR']}" .
                                       (! empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? ":{$_SERVER['HTTP_X_FORWARDED_FOR']}" : '' ) . ') ' .
                                       "Authentication failed. Username: $username. Password: $password" );
                                  }
                                  return $authOK;
                              }
                        );    
    $server->attachException( 'EripAPI\HMACException' );
    $server->attachException( 'EripAPI\MsgTimeException' );
    $server->attach( new EripAPI );
    $server->before( array( 'EripAPI\Security', 'verifyHMAC' ) );

    $response = $server->execute();
    return $response;
} catch (Exception $e) {
    $logger->write('error', $e);
    $logger->write('main', 'Выполнение завершилось сбоем');
} finally {
    $logger->write('access', "({$_SERVER['REMOTE_ADDR']}" .
    (! empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? ":{$_SERVER['HTTP_X_FORWARDED_FOR']}" : '' ) . ') ' .
    $server->getUsername() . ' sent "' . $server->getPayload() . '". Response: "' . $response . '".');
    
}


