<?php

namespace EripAPI;

class HMACException extends \RuntimeException{}
class MsgTimeException extends \RuntimeException{}

/**
 * Предоставляет методы служащие для обеспечения безопасности 
 */
abstract class Security {

    const HMAC_ALG = 'sha512';

    /**
     Ширина окрестности возле значения текущего времени сервера, для которой запрос с временной меткой, находящейся в этой окрестности будет обработан.
     */
     const ALLOWED_TIME_DELTA = 60;

    /**
     * Метод аутентификации пользователя
     *
     * @param string $username
     * @param string $password
     * @return boolean true, если аутентификация пройдена, иначе - false
     */
    public static function authenticate( $username, $password ) {
        global $db;
        $passwordHash = $db->getUserPasswordHash( $username );

        return password_verify( $password, $passwordHash );        
    }

    /**
     * Проверка HMAC. Если HMAC неверен или метка времени не соответсвует времени сервера, то выбрасывается исключение и возвращается JSON-RPC ошибка сервера -32001 и -32000 соответственно
     *
     * @param string $username
     * @param string $password
     * @param string $class Класс, метод которогго вызывается
     * @param string $method Имя, вызываемого метода API
     * @param string $params Параметры JSON_RPC вызова
     *
     */
    public static function verifyHMAC ( $username, $password, $class, $method, $params) {
        $msgTime = $params['time'];
        $currentTime = time();

        if ( isset( $msgTime ) && $msgTime > $currentTime - self::ALLOWED_TIME_DELTA / 2 &&  $msgTime < $currentTime + self::ALLOWED_TIME_DELTA ) {
            if ( ! empty( $params['hmac'] ) ) {
                global $db;
                $secretKey = $db->getUserSecretKey( $db->getUserIdByName($username) );
                $hmacText = '';

                $reflection = new \ReflectionMethod( $class, $method );
                $methodParams = $reflection->getParameters();
                foreach ($methodParams as $p) {
                    $name = $p->getName();
                    
                    if ( isset( $params[ $name ] ) ) {
                        $hmacText .= $params[ $name ];
                    } else if ( ! $p->isDefaultValueAvailable() ) {
                        throw new \InvalidArgumentException('Missing argument: '.$name);
                    }
                }
                $hmacText .= $msgTime;
                 
                $hmac = hash_hmac( self::HMAC_ALG, $hmacText, $secretKey );
                if ( ! hash_equals( $hmac,  $params['hmac'] ) ) {
                    throw new HMACException( 'HMAC is incorrect. Aborted.', -32001 );
                }
            } else {
                 throw new HMACException( 'HMAC is empty. Aborted.', -32001 );
            }        
        } else {
             throw new MsgTimeException( 'Message time is not in allowed range. Aborted.', -32000 );
        }
    }
}