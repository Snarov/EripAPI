#!/opt/lampp/bin/php
<?php
//скрипт для управления сервисом

define ('API_ROOT_DIR', __DIR__);

// ini_set('error_log', API_ROOT_DIR . '/../log/error.log');

include API_ROOT_DIR . '/include/util/ScriptParams.php';
include __DIR__ . '/include/util/Logger.php';

if ( ! function_exists('random_int') ) {    // для работы с php с версией ниже чем php7
    include API_ROOT_DIR . '/../lib/random_compat/random_compat.phar';
}

define('PASSWORD_DEFAULT_LEN', 12);
define('SECRET_KEY_LEN', 128);
$params = new ScriptParams;

$logger = new Logger;
$logger->addLog(array('main'), API_ROOT_DIR . '/../log/eripapi.log');
$logger->addLog(array('debug'), API_ROOT_DIR . '/../log/debug.log');
// var_dump($logger->write('error', 'huj'));
// var_dump(ini_get('error_log'));

/**
 * Generate a random string, using a cryptographically secure 
 * pseudorandom number generator (random_int)
 * 
 * For PHP 7, random_int is a PHP core function
 * For PHP 5.x, depends on https://github.com/paragonie/random_compat
 * 
 * @param int $length      How many characters do we want?
 * @param string $keyspace A string of all possible characters
 *                         to select from
 * @return string
 */
function random_str($length, $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
{
    $str = '';
    $max = mb_strlen($keyspace, '8bit') - 1;
    for ($i = 0; $i < $length; ++$i) {
        $str .= $keyspace[random_int(0, $max)];
    }
    return $str;
}

//связывает имена действий с функциями, которые выполняются для этих действий. А-ля "массив функций"
$actions = array (
    'adduser' => function() use ($params) {
        global $logger;
        
        $username = $params->username;
        $password = random_str(10);
        $secretKey = bin2hex(openssl_random_pseudo_bytes(SECRET_KEY_LEN, $keyStrong));

        if ( $keyStrong ) {
           $passwordHash = password_hash($password, PASSWORD_BCRYPT);

           require_once API_ROOT_DIR . '/include/EripAPI/DB.php';
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
        
    },
    
    'chpass' => function() use ($params) {
        global $logger;
        
        $username = $params->username;
        $password = $params->password;
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        
        require_once API_ROOT_DIR . '/include/EripAPI/DB.php';
        $db = new DB;
        $passChanged = $db->changeUserPassword($username, $passwordHash);
        
        if( $passChanged ) {
            $logger->write('main', 'Смена пароля пользователя' . $username);
            echo "Пароль пользователя $username успешно изменен." . PHP_EOL;
            exit(0);
        } else {
            exit ('Не удалось изменить пароль' . PHP_EOL);
        }
    },
    
    );

$actions[$params->action]();
