<?php

define ('API_ROOT_DIR', __DIR__);

require_once API_ROOT_DIR . '/include/EripAPI/EripAPI.php';
require_once API_ROOT_DIR . '/include/EripAPI/DB.php';
require_once API_ROOT_DIR . '/../lib/JsonRPC/Server.php';
include API_ROOT_DIR . '/include/util/Logger.php';
include API_ROOT_DIR . '/include/util/ErrorMessages.php';

class InactiveUserException extends Exception{}

use JsonRPC\Server as Server;

$logger = new Logger;
$logger->addLog('error', API_ROOT_DIR . '/../log/error.log');
$logger->addLog('main', API_ROOT_DIR . '/../log/eripapi.log');
//$logger->addLog('debug', 'php://stdout');
$logger->addLog('debug', API_ROOT_DIR . '/../log/debug.log');
$logger->addLog('access', API_ROOT_DIR . '/../log/access.log');
$logger->debug(true);

date_default_timezone_set('Europe/Minsk');

try {
    $db = new DB;
    //передем серверу анонимную функцию, производящую аутентификацию пользователя
    $server = new Server( '', function ($username, $password) use ($logger) { 
                                  if( ! $authOK = EripAPI\Security::authenticate($username, $password ) ) {
                                      $logger->write(
                                      "({$_SERVER['REMOTE_ADDR']}" .
                                       (! empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? ":{$_SERVER['HTTP_X_FORWARDED_FOR']}" : '' ) . ') ' .
                                      "Authentication failed. Username: $username. Password: $password", 'access' );
                                  }
                                  return $authOK;
                              }
                        );
    
    $server->attachException( 'EripAPI\HMACException' );
    $server->attachException( 'EripAPI\MsgTimeException' );
    $server->attachException( 'APIInternalError' );
    $server->attachException( 'InactiveUserException' );
    $server->attachException( 'EripAPI\InvalidParamValueException' );
    // TODO зарегистрировать исключения
    set_error_handler(function ( $errno, $errstr, $errfile, $errline ) use (&$logger) {
        $logger->write( "$errno: $errstr", 'error', $errfile, $errline );
        
        http_response_code(500);
        throw new APIInternalError(API_INTERNAL_ERROR_MSG, 1); }, E_ERROR );
    
    $eripAPI = new EripAPI;
    
    $server->attach( $eripAPI );
    $server->before( function ($username, $password, $class, $method, $param) use ($db, $eripAPI, $logger) {
        if ( 1 != $db->getUserState( $username ) ) {
            throw new InactiveUserException("User account is switched off. Service is not available for user '$username'");
        }
        EripAPI\Security::verifyHMAC($username, $password, $class, $method, $param);
        $eripAPI->userId = $db->getUserIdByName($username);
    }
    );
    
    $response = $server->execute();
    echo $response;
} catch (Exception $e) {
    $logger->write($e, 'error');
    $logger->write('Выполнение завершилось сбоем', 'error');
} finally {
    if ( empty( $response ) ) {
        $logger->write('Выполнение завершилось сбоем: '  .  "({$_SERVER['REMOTE_ADDR']}" .
    (! empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? ":{$_SERVER['HTTP_X_FORWARDED_FOR']}" : '' ) . ') ' .
                   $server->getUsername() . ' sent "' . print_r($server->getPayload(), true), 'error');
    }
    $logger->write("({$_SERVER['REMOTE_ADDR']}" .
    (! empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? ":{$_SERVER['HTTP_X_FORWARDED_FOR']}" : '' ) . ') ' .
                   $server->getUsername() . ' sent "' . print_r($server->getPayload(), true) . '". Response: "' . (isset ($response) ? $response : '') . '".', 'access');
    
}


