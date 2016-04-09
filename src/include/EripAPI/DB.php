<?php

/**
 * Предоставляет API весь необходиомый функционал (методы)  для которого требуется взаимодейсвтие с БД.
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

    public function __destruct() {
        $db->close();
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

    /**
     * Возвращает id юзера по его имени
     *
     * @param string $username
     * @return integer id пользователя или false - если произошла ошибка или пользователя с таким именем не существует
     */
    public function getUserIdByName($username) {
        $userId = false;
        
        try {
            $stmt = $this->db->prepare('SELECT id FROM users WHERE name LIKE ?');
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $stmt->bind_result($userId);
            $stmt->fetch();

            global $logger;
            if ($this->db->errno) {
                $logger->write('error', $this->db-error);
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write('error', $e);
        }
        
        return $secretKey;
    }

    /**
     * Возвращает номер, с которым будет создан новый счет
     *
     * @return integer номер или false, если произошла ошибка
     */
    public function getNextBillNum() {
        $result = $this->db->query("SELECT auto_increment FROM information_schema.tables WHERE table_name = 'bills' AND table_schema = DATABASE( )");
        
        if ( $result ) {
            return $result-fetch_assoc()['auto_increment'];
        } else {
            return false;
        }
    }
    
    /**
     * Возвращает пользовательские данные для соединения с ftp-сервером: Хост, имя пользователя и пароль.
     *
     * @param integer $userId
     * @return array Хост, имя пользователя и пароль. Пустой массив в случае ошибки
     */
    public function getFtpConnectionData($userId) {
        $ftpConnectionData = array();

        try {
            $stmt = $this->db->prepare('SELECT ftp_host, ftp_user, ftp_password FROM erip_requisites WHERE user = ?');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $ftpConnectionData = $this->fetch($stmt);

            global $logger;
            if ($this->db->errno) {
                $logger->write('error', $this->db-error);
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write('error', $e);
        }
        
        return $ftpConnectionData;
    }

    /**
     * Возращает данные абонента ЕРИП: код абонента, УНП, код банка, номер банковского счета
     *
     * @param integer $userId
     * @return array код абонента, УНП, код банка, номер банковского счет
     */
    public function getEripCredentials($userid) {
        $eripCredentials = array();

        try {
            $stmt = $this->db->prepare('SELECT subscriber_code, unp, bank_code, bank_account FROM erip_requisites WHERE user = ?');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $ftpConnectionData = $this->fetch($stmt);

            global $logger;
            if ($this->db->errno) {
                $logger->write('error', $this->db-error);
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write('error', $e);
        }
        
        return $eripCredentials;
    }

    /**
     * Добавляет новый счет (требование к оплате) в базу.
     *
     * @param $userId id пользователя, выставившего счет
     * @param integer $eripID Идентификатор услуги в ЕРИП
     * @param string $personalAccNum Номер лицевого счета (уникальное значение, однозначно идентифицирующее потребителя услуг или товар)
     * @param float $amount Сумма задолженности потребителя услуг перед производителем услуг. Отрицательное значение означает задолженность производителя перед потребителем
     * @param integer $currencyCode  Код валюты требований к оплате 
     * @param object $info Дополнительная инорфмация о счете
     *
     * return boolean true в случае успешного добавления, иначе - false
     */
    public function addBill($userId, $eripID, $personalAccNum, $amount, $currencyCode, array $info) {
        //создаем части SQL-запроса для заполнения необязательных столбцов, использую данные массива $info
        $optionalFields = '';
        $optionalValues = '';
        if( ! empty ($info ) ) {
            if ( array_key_exists('customerFullname', $info) ) {
                $optionalFields .= ', customer_fullname';
                $optionalValues .= ", {$info['customer_fullname']}";
            }
            if ( array_key_exists('subscriberAddress', $info) ) {
                $optionalFields .= ', subscriber_adress';
                $optionalValues .= ", {$info['subscriber_address']}";
            }
            if ( array_key_exists('additionalInfo', $info) ) {
                $optionalFields .= ', additional_info';
                $optionalValues .= ", {$info['additional_info']}";
            }
            if ( array_key_exists('additionalData', $info) ) {
                $optionalFields .= ', additional_data';
                $optionalValues .= ", {$info['additional_data']}";
            }
            if ( array_key_exists('meters', $info) ) {
                //TODO развернуть массив meters
            }
        }

        try {
            global $logger;
            
            $query = "INSERT INTO bills (user, erip_id, personal_acc_num, amount, currency_code, $optionalFields)" .
                   "VALUES ($userId, $eripID, $personalAccNum, $amount, $currencyCode, $optionalValues)";
            if ( $this->db->query($query) ) {
                return true;
            } else {
                $logger->write('error', 'Ошибка добавления счета: ' . $this->db->error);
                return false;
            }
           
        } catch ( Exception $e ) {
            $logger->write('error', $e);
            return false;
        }
    }

    /**
     * Создает новую выполняющуся операцию
     *
     * @param integer $userId Пользователь, запустивший операцию
     * @param integer string $operationTypeIndex
     * @param array $operationParams
     *
     * @return boolean true, если операция добавлена успешно, иначе - false
     */
    public function addRunningOperation($userId, $operationTypeIndex, array $operationParams = null) {
        try {
            $stmt = $this->db->prepare('INSERT INTO running_operations (owner, type) VALUES (?, ?)');
            $stmt->bind_param('ii', $userId, $type);

            global $logger;
            if ( $stmt->execute() ) {
                if ( $operationParams ) {
                    $runningOperationId = $this->db->insert_id;
                    
                    foreach ( $opeartionParams as $name => $value ) {
                        $stmt = $this->db->prepare('INSERT INTO runops_custom_params (operation, param_name, value) VALUES (?, ?, ?)');
                        $stmt->bind_param('iss', $runningOperationId, $name, $value);
                        if ( ! $stmt->execute() ) {
                            $logger->write('error', 'Ошибка добавления параметров выполняющейся операции: ' . $this->db->error);
                            return false;
                        }
                    }

                    return true;
                }
            } else {
                $logger->write('error', 'Ошибка добавления выполняющейся операции: ' . $this->db->error);
                return false;
            }
           
        } catch ( Exception $e ) {
            $logger->write('error', $e);
            return false;
        }
    }
    
    /**
     * Разворачивает результат запроса и помещает значения столбцов в массив
     *
     * @param $result Результат обычного запроса либо подготовленного выроажения.
     * @return array Массив, содержащий строки из результата запроса
     */
    private function fetch($result) {
        $array = array();
    
        if( $result instanceof mysqli_stmt ) {
            $result->store_result();
        
            $variables = array();
            $data = array();
            $meta = $result->result_metadata();
        
            while( $field = $meta->fetch_field() ) {
                $variables[] = &$data[$field->name];
            }
        
            call_user_func_array(array($result, 'bind_result'), $variables);
        
            $i = 0;
            while( $result->fetch() ) {
                $array[$i] = array();
                foreach( $data as $key => $value ) {
                    $array[$i][$key] = $value;
                }
                $i++;
            }
        } else if( $result instanceof mysqli_result ) {
            while( $row = $result->fetch_assoc() ) {
                $array[] = $row;
            }
        }
    
        return $array;
    }
}