<?php

define ('API_ROOT_DIR', __DIR__);

require_once API_ROOT_DIR . '/include/EripAPI/EripAPI.php';
require_once API_ROOT_DIR . '/include/EripAPI/DB.php';
require_once API_ROOT_DIR . '/../lib/JsonRPC/Server.php';
include API_ROOT_DIR . '/include/util/Logger.php';
include API_ROOT_DIR . '/include/util/ErrorMessages.php';

use JsonRPC\Server as Server;

$logger = new Logger;
// $logger->addLog('error', API_ROOT_DIR . '/../log/error.log');
$logger->addLog('main', API_ROOT_DIR . '/../log/eripapi.log');
$logger->addLog('debug', 'php://stdout');
$logger->addLog('access', API_ROOT_DIR . '/../log/access.log');
$logger->debug(true);

date_default_timezone_set('Europe/Minsk');

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
    $server->attachException( 'APIInternalError' );
    $server->attachException( 'EripAPI\InvalidParamValueException' );
    //TODO зарегистрировать исключения
    
    $eripAPI = new EripAPI;
    
    $server->attach( $eripAPI );
    $server->before( function ($username, $password, $class, $method, $param) use ($db, $eripAPI) {
                         EripAPI\Security::verifyHMAC($username, $password, $class, $method, $param);
                         $eripAPI->userId = $db->getUserIdByName($username);
                       }
                   );
    
    $response = $server->execute();
    echo $response;
} catch (Exception $e) {
    $logger->write('error', $e);
    $logger->write('main', 'Выполнение завершилось сбоем');
} finally {
    $logger->write('access', "({$_SERVER['REMOTE_ADDR']}" .
    (! empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? ":{$_SERVER['HTTP_X_FORWARDED_FOR']}" : '' ) . ') ' .
    $server->getUsername() . ' sent "' . $server->getPayload() . '". Response: "' . $response . '".');
    
}


