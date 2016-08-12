<?php
//Опрашивает фтп сервер, на наличие новых событий (файлов) и выполняет определенные действия при их появлении

define ('API_ROOT_DIR', __DIR__);
require_once API_ROOT_DIR . '/include/EripAPI/DB.php';
require_once API_ROOT_DIR . '/include/EripAPI/ERIPMessageIO.php';
require_once API_ROOT_DIR . '/include/EripAPI/ERIPMessageHandler.php';
include API_ROOT_DIR . '/include/util/Logger.php';

use EripAPI\ERIPMessageIO as MessageIO;
use EripAPI\ERIPMessageHandler as MessageHandler;

date_default_timezone_set('Europe/Minsk');

$logger = new Logger;
$logger->addLog('error', API_ROOT_DIR . '/../log/error.log');
$logger->addLog('main', API_ROOT_DIR . '/../log/eripapi.log');
//$logger->addLog('debug', 'php://stdout');
$logger->addLog('debug', API_ROOT_DIR . '/../log/debug.log');
$logger->addLog('access', API_ROOT_DIR . '/../log/access.log');
$logger->debug(true);

try {
    $db = new DB;
} catch ( Exception $e ) {
    $logger->write('Не удалось подключиться к MySQL: ' . $e->getMessage(), 'error');
    die(1);
}

$usersWithRunningOperations = $db->getUsersWithRunningOperations();

if ( empty( $usersWithRunningOperations ) ) {
    $logger->write( 'Нету пользователей с текущими операциями', 'debug', __FILE__, __LINE__);
    die();
}

foreach ( $usersWithRunningOperations as $userId ) {
    $ftpConnectionData = $db->getFtpConnectionData($userId);
    
    extract($ftpConnectionData);
    $msgIO = new MessageIO($ftp_host, $ftp_user, $ftp_password); //имена переменных не в camelCase потому что они идентичны именам столбцов в таблице БД

    $newFilesList = $msgIO->getNewFilesList();
    $logger->write( "Список новых сообщений пользователя номер $userId: " . print_r( $newFilesList, true ), 'debug', __FILE__ , __LINE__);
 
    if ( empty ( $newFilesList ) ) {
        continue;
    }

    $msgHandler = new MessageHandler($userId);
    foreach ( $newFilesList as $newFile ) {
        $message = $msgIO->readMessage($newFile);
        if ( ! $message ) {
            $logger->write( "Не удалось прочитать файл $newFile", 'error', __FILE__, __LINE__ );
            continue;
        }

        $msgHandler->handle($message);
    }
}

