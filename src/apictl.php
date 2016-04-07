#!/usr/bin/php
<?php
//скрипт для управления сервисом

define ('API_ROOT_DIR', __DIR__);

// ini_set('error_log', API_ROOT_DIR . '/../log/error.log');

include API_ROOT_DIR . '/include/util/ScriptParams.php';
include __DIR__ . '/include/util/Logger.php';

define('PASSWORD_DEFAULT_LEN', 12);
define('SECRET_KEY_LEN', 128);
$params = new ScriptParams;

$logger = new Logger;
$logger->addLog(array('main'), API_ROOT_DIR . '/../log/eripapi.log');
$logger->addLog(array('debug'), API_ROOT_DIR . '/../log/debug.log');
// var_dump($logger->write('error', 'huj'));
// var_dump(ini_get('error_log'));

//связывает имена действий с функциями, которые выполняются для этих действий. А-ля "массив функций"
$actions = array (
    'adduser' => function() use ($params) {

        global $logger;
        
        $username = $params->username;
        $password = bin2hex(openssl_random_pseudo_bytes(PASSWORD_DEFAULT_LEN, $passStrong));
        $secretKey = bin2hex(openssl_random_pseudo_bytes(SECRET_KEY_LEN, $keyStrong));

        if ( $passStrong && $keyStrong ) {
           $passwordHash = password_hash($password, PASSWORD_BCRYPT);

           require_once __DIR__ . '/include/EripAPI/DB.php';
            $db = new DB;
            $userCreated =  $db->addUser($username, $passwordHash, $secretKey);
        } else {
            $logger->write('error', 'Пользователь не может быть создан: в системе отсутствует криптостойкий генератор');
        }

        if( $userCreated ) {
            $logger->write('main', 'Создан новый пользователь ' . $username);
            echo "Пользователь $username создан. Пароль: $password" . PHP_EOL;
            exit(0);
        } else {
            exit ('Не удалось создать пользователя' . PHP_EOL);
        }
        
    } );

$actions[$params->action]();