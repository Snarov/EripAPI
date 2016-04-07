<?php

/**
 * Предоставляет API весь необходиомый функционал для которого требуется взаимодейсвтие с БД.
*/
class DB {

    const  DEFAULT_CONF_PATH = API_ROOT_DIR . '/etc/db.php';
    
    private $db;

    public function __construct($confPath = '') {
        $confPath = ! empty($confPath) ? $confPath : self::DEFAULT_CONF_PATH;
        require $confPath;

        mysqli_report(MYSQLI_REPORT_STRICT); 
        $this->db = new mysqli($DBHost, $DBUser, $DBPassword, $DBName);
    }

    /**
     * Возвращает пароль пользователя, если пользователь с таким именем существует и активен. Иначе возвращает false
     *
     * @param $username
     * @return string
     */
    public function getUserPasswordHash ( $username ) {
        global $logger;
        
        $password = false;
        try {
            $stmt = $this->db->prepare('SELECT password FROM users WHERE name = ? AND state = ?');
            $activeCode = 1;
            $stmt->bind_param('sd', $username, $activeCode);
            $stmt->execute();
            $stmt->bind_result($password);
            $stmt->fetch();

            if ($this->db->errno) {
                $logger->write('error', $this->db-error);
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write('error', $e);
        }
        return $password;
    }

    /**
     * Возвращает секретный ключ пользователя с именем $username или false в случае ошибки.
     *
     * @param string $username
     * @return string
     */
    public function getUserSecretKey($username) {
         global $logger;
        
        $secretKey = false;
        try {
            $stmt = $this->db->prepare('SELECT secret_key FROM users WHERE name = ?');
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $stmt->bind_result($secretKey);
            $stmt->fetch();

            if ($this->db->errno) {
                $logger->write('error', $this->db-error);
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write('error', $e);
        }  
        return $secretKey;
    }
    
    /**
     * Добавляет в базу нового пользователя. В случае успеха возвращает true, иначе - false
     *
     * @param string $username
     * @param string $password Пароль передается в уже зашифрованном виде.
     * @param string
     * @return boolean
     * TODO добавить больше параметров
     *
     */
    public function addUser($username, $password, $secretKey){
        try {
            $stmt = $this->db->prepare('INSERT INTO users (name, password, secret_key, erip_requisites) VALUES (?, ?, ?, 1)');
            $stmt->bind_param('sss', $username, $password, $secretKey);

            global $logger;
            if ( $stmt->execute() ) {
                return true;
            } else {
                $logger->write('error', 'Ошибка создания нового пользователя: ' . $this->db->error);
                return false;
            }
           
        } catch ( Exception $e ) {
            $logger->write('error', $e);
            return false;
        }
    }
}