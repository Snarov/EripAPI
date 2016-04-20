<?php

/**
 * Предоставляет API весь необходиомый функционал (методы)  для которого требуется взаимодейсвтие с БД.
*/
class DB {

    const  DEFAULT_CONF_PATH = API_ROOT_DIR . '/etc/db.php';
    
    private $db;
    /**
     * Массив, содержищий список имен столбцов для каждой таблицы. Имена таблиц являются ключами, а массив имен столбцов является значением
     *
     * @access private
     * @var array
     */
    private $tableColumns = array(); 

    public function __construct($confPath = '') {
        $confPath = ! empty($confPath) ? $confPath : self::DEFAULT_CONF_PATH;
        require $confPath;

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
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
            return $result->fetch_assoc()['auto_increment'];
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
    public function addBill($userId, $eripID, $personalAccNum, $amount, $currencyCode, $billTimestamp, array $info) {
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
            
            $query = "INSERT INTO bills (user, erip_id, personal_acc_num, amount, currency_code, timestamp $optionalFields)" .
                   "VALUES ($userId, $eripID, $personalAccNum, $amount, $currencyCode, $billTimestamp $optionalValues)";
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
     * Возвращает запись выполняемой операции c дополнительными полями
     *
     * @param integer $operationId
     * @return array Данные операции или пустой массив, если операции с таким номером не существует
     */
    public function getRunningOperation($operationId) {
        try {
            $stmt = $this->db->prepare('SELECT RO.*, U.name AS username, OT.name AS typename, OT.description FROM running_operations RO JOIN users U ON RO.owner = U.id' .
                                       'JOIN operations_types OT ON OT.id = RO.type WHERE RO.id = ?');
            $stmt->bind_param('i', $operationId);
            $stmt->execute();
            $operation = $this->fetch($stmt);

            $stmt = $this->db->prepare('SELECT * FROM runops_custom_params WHERE operation = ?');
            $stmt->bind_param('i', $operationId);
            $stmt->execute();
            $rawParams = $this->fetch($stmt);

            $params = array();
            foreach ( $rawParams as $rawParam ) {
                $params[$rawParam['param_name']] = $rawParam['value'];
            }
            $operation['params'] = $params;

            global $logger;
            if ($this->db->errno) {
                $logger->write('error', $this->db-error);
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write('error', $e);
        }
        
        return $operation;
    }

    /**
     * Возвращает набор записей выполняемых операций, соответстувующих условию, с параметрами.
     *
     * @param integer $userId
     * @param integer $type
     *
     * @return array набор записей или пустой массив, если записей удовлетворяющих условию не найдено
     */
    public function getRunningOperations($userId, $type) {
        global $logger;
        
        try {
            $stmt = $this->db->prepare('SELECT * FROM running_operations WHERE owner = ? AND type = ?');
            $stmt->bind_param('ii', $userId, $type);
            $stmt->execute();
            $operations = $this->fetch($stmt);

            foreach ( $operations as &$operation ) {
                $stmt = $this->db->prepare('SELECT * FROM runops_custom_params WHERE operation = ?');
                $stmt->bind_param('i', $operation['id']);
                $stmt->execute();
                $rawParams = $this->fetch($stmt);

                $params = array();
                foreach ( $rawParams as $rawParam ) {
                    $params[$rawParam['param_name']] = $rawParam['value'];
                }
                $operation['params'] = $params;
            }
            unset($operation);

            if ($this->db->errno) {
                $logger->write('error', $this->db-error);
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write('error', $e);
        }

        return $operations;
    }

    /**
     * Возращает список id пользователей, которые являются владельцами хотя бы одной выполняемой операции
     * 
     * @return array пустой массив, если таких пользователей нету
     */
    public function getUsersWithRunningOperations() {
        $userIds = array();

        try {
            $owners = $this->fetch($this->db->query('SELECT DISTINCT owner FROM running_operations'));
            foreach ( $owners as $row ) {
                $userIds[] = $row['owner'];
            }
            
            global $logger;
            if ($this->db->errno) {
                $logger->write('error', $this->db-error);
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write('error', $e);
        }
        
        return $userIds;
    }
    
    /**
     * Возвращает данные из строки таблицы счетов
     *
     * @param integer $billNum
     * @return array Запись о счете или пустой массив, если счета с таким номером не существует
     */
    public function getBill($billNum) {
        try {
            $stmt = $this->db->prepare('SELECT * FROM bills WHERE id = ?');
            $stmt->bind_param('i', $billNum);
            $stmt->execute();
            $bill = $this->fetch($stmt);

            global $logger;
            if ($this->db->errno) {
                $logger->write('error', $this->db-error);
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write('error', $e);
        }
        
        return $bill;
    }

     /**
     * Возвращает id пользователя, который является владельцем счета с номером $billNum
     *
     * @param integer $billNum
     * @return integer id пользователя. В случае ошибки или несуществования счета возвращается null
    */
    public function getBillUser($billNum) {
        global $logger;
        
        try {
            $stmt = $this->db->prepare('SELECT user FROM bills WHERE id = ?');
            $stmt->bind_param('i', $billNum);
            $stmt->execute();
            $stmt->bind_result($billUser);
            $stmt->fetch();

            if ($this->db->errno) {
                $logger->write('error', $this->db-error);
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write('error', $e);
        }
        
        return $billUser;
    }
    
    /**
     * Возвращает значение поля status счета с указанным номером
     *
     * @param integer $billNum
     * @return integer Код статуса. Если счета не существует, то его код статуса - NULL
    */
    public function getBillStatus($billNum) {
        global $logger;
        
        try {
            $stmt = $this->db->prepare('SELECT status FROM bills WHERE id = ?');
            $stmt->bind_param('i', $billNum);
            $stmt->execute();
            $stmt->bind_result($billStatus);
            $stmt->fetch();

            if ($this->db->errno) {
                $logger->write('error', $this->db-error);
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write('error', $e);
        }  
        return $billStatus;
    }

    /**
     * Задает значение поля status счета с указанным номером
     *
     * @param integer $billNum
     * @param integer $status
     *
     * @return boolean true, если изменения успешно внесены, иначе - false
    */
    public function setBillStatus($billNum, $status) {
        global $logger;
        
        try {
            $stmt = $this->db->prepare('UPDATE bills SET status = ? WHERE id = ?');
            $stmt->bind_param('ii', $status,  $billNum);
            $setSuccessful = $stmt->execute();

            if ($this->db->errno) {
                $logger->write('error', $this->db-error);
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write('error', $e);
        }  
        return $setSuccessful;
    }

    /**
     * Задает значение поля error_msg счета с указанным номером
     *
     * @param integer $billNum
     * @param string $errMsg
     *
     * @return boolean true, если изменения успешно внесены, иначе - false
    */
    public function setBillError($billNum, $errMsg) {
        global $logger;
        
        try {
            $stmt = $this->db->prepare('UPDATE bills SET error_msg = ? WHERE id = ?');
            $stmt->bind_param('si', $errMsg, $billNum);
            $setSuccessful = $stmt->execute();

            if ($this->db->errno) {
                $logger->write('error', $this->db-error);
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write('error', $e);
        }  
        return $setSuccessful;
    }

    /**
     * Производит удаление записи о платеже
     *
     * @param integer $billNum
     * @return boolean true - в случае успешного удаления, иначе - false
     */
    public function deleteBill($billNum) {
        global $logger;
        
        try {
            $deleteSuccessful = $this->db->query("DELETE FROM bills WHERE id = $billNum");
            
            if ($this->db->errno) {
                $logger->write('error', $this->db-error);
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write('error', $e);
        }  
        return $deleteSuccessful;
    }

    /**
     * Возвращает все записи из таблицы bills за указанный период и с указаными значениями( опционально )
     *
     * @param integer $userId
     * @param integer $eripID Идентификатор услуги в ЕРИП. Если не указан, то возвращаются данные по всем услугам данного ПУ.
     * @param integer $fromTimestamp  Начало периода (UNIX-время)
     * @param integer $toTimestamp  Конец периода (UNIX-время)
     * @param integer $status Код статуса
     *
     * @return Все строки, удовлетворяющие условиям
     */
    public function getBills($userId, $eripID, $fromTimestamp , $toTimestamp, $status) {
        //части SQL-запроса для необязательных условий
        $optionalConditions = '';
        if ( null !== $eripID ) { $optionalConditions .= "AND erip_id = $eripID "; }
        if ( null !== $status ) { $optionalConditions .= "AND status = $status"; }

        try {
            $stmt = $this->db->prepare('SELECT * FROM bills WHERE timestamp BETWEEN ? AND ? AND user = ?' . $optionalConditions);
            $stmt->bind_param('iii', $fromTimestamp, $toTimestamp, $userId);
            $stmt->execute();
            $bills = $this->fetch($stmt);

            if ($this->db->errno) {
                $logger->write('error', $this->db-error);
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write('error', $e);
        }

        return $bills;
    }

    /**
     * Возвращает информацию по платежу
     *
     * @param $billNum номер счета, по которому проведен платеж
     * @return Запись о платеже или пустой массив, если оплаты счета с таким номером не существует
     */
    public function getPayment($billNum) {
        try {
            $stmt = $this->db->prepare('SELECT P.*, B.erip_id, B.personal_acc_num FROM payments P JOIN bills B ON P.bill = B.id AND B.bill = ?');
            $stmt->bind_param('i', $billNum);
            $stmt->execute();
            $payment = $this->fetch($stmt);

            global $logger;
            if ($this->db->errno) {
                $logger->write('error', $this->db-error);
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write('error', $e);
        }
        
        return $payment;
    }

    /**
     * Обновляет запись об оплате счета с номером $billNum. Создает запись, если таковой не существует
     *
     * @param $billNum
     * @param array $data обновленные значения полей.
     *
     * @return boolean true, если изменения успешно внесены, иначе - false
     */
    public function updatePayment($billNum, $data) {
        global $logger;
        
        try {
            $paymentExists = $this->fetch($this->db->query("SELECT EXISTS (SELECT * FROM payments WHERE bill = $billNum)"));
            if ( $paymentExists[0] == true ) {
                 //создаем часть SQL-запроса для изменения столбцов, используя данные массива $data.в
                $setPart = '';
                foreach ( $data as $name => $value ) {
                    if ( $this->columnExists('payments', $name) ) {
                        $setPart .= "$name = $value";
                        if ( end(array_keys($data)) !== $name ) {
                            $setPart .= ', ';
                        }
                    }
                }
                
                $successful = $this->db->query("UPDATE payments SET $setPart WHERE bill = $billNum");
            } else {
                //создаем части SQL-запроса для заполнения столбцов, используя данные массива $data. Здесь задаются значения как для обязательных, так и необязательных столбцов
                $fields = '';
                $values = '';
                foreach ( $data as $name => $value ) {
                    if ( $this->columnExists('payments', $name) ) {
                        $fields .= ", $name";
                        $values .= ", $value";
                    }
                }
                //здесь задаются значения для столбцов, значение которых требует некоторой обработки перед встаывкой в таблицу
                $stmt = $this->db->prepare("INSERT INTO payments (bill, erip_op_num, payment_timestamp $fields) VALUES (?, ?, ? $values)"); 
                $stmt->bind_param('iii', $billNum, $data['central_node_op_num'], strtotime($data['payment_datetime']));
                $successful = $stmt->execute();
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write('error', $e);
        }

        return $successful;
    }
    
    /**
     * Возвращает все записи из таблицы payments за указанный период и с указаными значениями ( опционально )
     *
     * @param integer $userId 
     * @param integer $eripID Идентификатор услуги в ЕРИП. Если не указан, то возвращаются данные по всем услугам данного ПУ.
     * @param integer $fromTimestamp  Начало периода (UNIX-время)
     * @param integer $toTimestamp  Конец периода (UNIX-время)
     * @param integer $status Код статуса
     *
     * @return Все строки, удовлетворяющие условиям
     */
    public function getPayments($userId, $eripID, $fromTimestamp , $toTimestamp, $status) {
        //части SQL-запроса для необязательных условий
        $optionalConditions = '';
        if ( null !== $eripID ) { $optionalConditions .= "AND erip_id = $eripID "; }
        if ( null !== $status ) { $optionalConditions .= "AND status = $status"; }

        try {
            $stmt = $this->db->prepare('SELECT P.*, B.erip_id, B.personal_acc_num FROM payments P JOIN bills B ON P.bill = B.id WHERE timestamp BETWEEN ? AND ? AND B.user = ?' . $optionalConditions);
            $stmt->bind_param('iii', $fromTimestamp, $toTimestamp, $userId);
            $stmt->execute();
            $payments = $this->fetch($stmt);

            if ($this->db->errno) {
                $logger->write('error', $this->db-error);
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write('error', $e);
        }

        return $payments;
    }

    /**
     * Завершает выполняемую операцию, удаляя запись о ней из таблицы running_operations и помещая ее в архив операций
     *
     * @params integer $operationId
     * @params boolean $endStatus
     * @params string $additionalInfo
     *
     * @return boolean true, если изменения успешно внесены, иначе - false. Если не удалось добавить операцию в историю операций, то все равно будет возвращено true
     */
    public function finishOperation($operationId, $endStatus = true, $additionalInfo = null) {
        global $logger;
        
        $operation = $this->getRunningOperation($operationId);
        if ( empty($operation) ) {
            return false;
        }
        
        try {
            $deleteSuccessful = $this->db->query("DELETE FROM running_operations WHERE id = $operationId");
            if ( ! $deleteSuccessful ) {
                return false;
            }

            $stmt = $this->db->prepare('INSERT INTO operations_history (username, operation_type_name, operation_desc, start_timestamp, end_timestamp, end_status, additional_info)' .
                                       'VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('sssiiis', $operation['username'], $operation['typename'], $operation['descsription'], $operation['start_timestamp'], time(), $endStatus, $additionalInfo);
            $successfulArchive = $stmt->execute();
            if ( ! $successfulArchive ) {
                $logger->write('error', "Не удалось добавить операцию с номером {$operation['id']} в историю операций");
            }
            
            if ($this->db->errno) {
                $logger->write('error', $this->db-error);
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write('error', $e);
        }  

        return true;
    }

    /**
     * Проверяет, существует ли в таблице с именем $tableName столбец $columnName
     *
     * @param string $tableName
     * @param string $columnName
     *
     * @return boolean true, если столбец существует, false, если не существует. В случае ошибки возвращается -1
     */
    private function columnExists($tableName, $columnName) {
        global $logger;

        if ( ! array_key_exists($tableName, $this->tableColumns) ) {
            try {
                $result = $this->fetch($this->db->query("SHOW COLUMNS FROM $tableName"));
                if ( empty ($result) ) {
                    return false;
                }

                $this->tableColumns[$tableName] = array();
                foreach ( $result as $row ) {
                    $this->tableColumns[$tableName][] = $row['Field'];
                }
            } catch (mysqli_sql_exception $e) {
                $logger->write('error', $e);
                return -1;
            }  
        }

        return in_array($columnName, $this->tableColumns);
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