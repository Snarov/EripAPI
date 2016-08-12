<?php

/**
 * Предоставляет API весь необходиомый функционал (методы)  для которого требуется взаимодейсвтие с БД.
*/
class DB {

    const  DEFAULT_CONF_PATH = API_ROOT_DIR . '/etc/db.php';

     /**
     * Строка, содержащая путь к файлу конфигурации
     *
     * @access private
     * @var string
     */
    private $confPath;
    
    private $db;

    private $DBHost;
    private $DBUser;
    private $DBPassword;
    private $DBName;
    
    /**
     * Массив, содержищий список имен столбцов для каждой таблицы. Имена таблиц являются ключами, а массив имен столбцов является значением
     *
     * @access private
     * @var array
     */
    private $tableColumns = array(); 

    public function __construct($confPath = '') {
        if ( empty( $this->confPath ) ) {
            $this->confPath = ! empty($confPath) ? $confPath : self::DEFAULT_CONF_PATH;
            require $this->confPath;

            $this->DBHost = $DBHost;
            $this->DBUser = $DBUser;
            $this->DBPassword = $DBPassword;
            $this->DBName = $DBName;
        }

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $this->db = new mysqli("p:{$this->DBHost}", $this->DBUser, $this->DBPassword, $this->DBName);
        $this->db->set_charset('utf8');
    }

    /**
     * Проверяет активно ли соединение с СУБД и переподключается в случае необходимости
     */
    public function ping() {
        try {
            @$this->db->ping();

            if ($this->db->errno == 2006) {
                $this->__construct();
            }
        } catch ( mysqli_sql_exception $e ) {
            // mysqli_sql_exception не позволяет просто так получтиь значение свойства code, поэтому используем рефлексию
            $reflect = new ReflectionClass($e);
            $property = $reflect->getProperty('code');
            $property->setAccessible(true); 
            $code = $property->getValue($e);
            if ($code == 2006) {
                $this->__construct($this->DBHost, $this->DBUser, $this->DBPassword, $this->DBName);
            }
        }
    }

    /**
     * Возвращает пароль пользователя, если пользователь с таким именем существует и активен. Иначе возвращает false
     *
     * @param $username
     * @return string
     */
    public function getUserPasswordHash ( $username ) {
        global $logger;
        $this->ping();
        
        $password = false;
        try {
            $stmt = $this->db->prepare('SELECT password FROM users WHERE name = ?');
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $stmt->bind_result($password);
            $stmt->fetch();

            if ($this->db->errno) {
                $logger->write($this->db-error, 'error');
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write($e, 'error');
        }
        return $password;
    }

    /**
     * Возвращает секретный ключ пользователя с именем $username или false в случае ошибки.
     *
     * @param string $username
     * @return string
     */
    public function getUserSecretKey($userId) {
        global $logger;
        $this->ping();
        
        $secretKey = false;
        try {
            $stmt = $this->db->prepare('SELECT secret_key FROM users WHERE id = ?');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->bind_result($secretKey);
            $stmt->fetch();

            if ($this->db->errno) {
                $logger->write($this->db-error, 'error');
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write($e, 'error');
        }  
        return $secretKey;
    }

    /**
     * Добавляет в базу нового пользователя. В случае успеха возвращает true, иначе - false
     *
     * @param string $username
     * @param string $password Пароль передается в уже зашифрованном виде.
     * @param array $eripRequisites массив со значениями реквизитов, необходимых для обеспечения взаисодействия с ЕРИП
     *
     * @return boolean
     */
    public function addUser($username, $password, $secretKey, $eripRequisites) {
        global $logger;
        $this->ping();

        try {
            $stmt = $this->db->prepare('INSERT INTO users (name, password, secret_key) VALUES (?, ?, ?)');
            $stmt->bind_param('sss', $username, $password, $secretKey);

            if ( $stmt->execute() ) {
                $userId = $this->db->insert_id;

                $stmt = $this->db->prepare('INSERT INTO erip_requisites (user, ftp_host, ftp_user, ftp_password, subscriber_code, unp, bank_code, bank_account) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('isssiiii',
                                  $userId,
                                  $eripRequisites['ftp_addr'],
                                  $eripRequisites['ftp_user'],
                                  $eripRequisites['ftp_password'],
                                  $eripRequisites['subscriber_code'],
                                  $eripRequisites['unp'],
                                  $eripRequisites['bank_code'],
                                  $eripRequisites['bank_account']
                );

                if ( $stmt->execute() ) {
                    return true;
                } else {
                     $logger->write( 'Ошибка добавления реквизитов ЕРИП' . $this->db->error, 'error' );
                     return false;
                }                
            } else {
                $logger->write( 'Ошибка создания нового пользователя: ' . $this->db->error, 'error' );
                return false;
            }

        } catch ( Exception $e ) {
            $logger->write ( $e, 'error');
            return false;
        }
    }

    /**
    * Изменяет пароль пользователя. В случае успеха возвращает true, иначе - false
    * 
    * @param string $username
    * @param string $newPassword Пароль передается в уже зашифрованном виде.
    *
    * @return boolean
    *
    */
    public function changeUserPassword($username, $newPassword) {
        global $logger;
        $this->ping();

        try {
            $query = "UPDATE users SET password = '$newPassword' WHERE name LIKE '$username'";

            if ( $this->db->query($query) ) {
                return true;
            } else {
                $logger->write('Ошибка изменения пароля: ' . $this->db->error, 'error');
                return false;
            }

        } catch ( Exception $e ) {
            $logger->write($e, 'error');
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
        $this->ping();
        
        $userId = false;
        
        try {
            $stmt = $this->db->prepare('SELECT id FROM users WHERE name LIKE ?');
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $stmt->bind_result($userId);
            $stmt->fetch();

            global $logger;
            if ($this->db->errno) {
                $logger->write( $this->db-error, 'error');
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write($e, 'error');
        }
        
        return $userId;
    }

    /**
     * Возвращает номер, с которым будет создан новый счет
     *
     * @return integer номер или false, если произошла ошибка
     */
    public function getNextBillNum() {
        $this->ping();
        
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
        global $logger;
        $this->ping();
        
        $ftpConnectionData = array();

        try {
            $stmt = $this->db->prepare('SELECT ftp_host, ftp_user, ftp_password FROM erip_requisites WHERE user = ?');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $ftpConnectionData = $this->fetch($stmt);
            
            if ($this->db->errno) {
                $logger->write( $this->db-error, 'error');
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write( $e, 'error');
        }
        
        return $ftpConnectionData[0];
    }

    /**
     * Возращает данные абонента ЕРИП: код абонента, УНП, код банка, номер банковского счета
     *
     * @param integer $userId
     * @return array код абонента, УНП, код банка, номер банковского счет
     */
    public function getEripCredentials($userId) {
        global $logger;
        $this->ping();
        
        $eripCredentials = array();

        try {
            $stmt = $this->db->prepare('SELECT subscriber_code, unp, bank_code, bank_account FROM erip_requisites WHERE user = ?');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $eripCredentials = $this->fetch($stmt);
            
            if ($this->db->errno) {
                $logger->write($this->db-error, 'error');
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write($e, 'error');
        }
        
        return $eripCredentials[0];
    }

    /**
     * Добавляет новый счет (требование к оплате) в базу.
     *
     * @param $userId id пользователя, выставившего счет
     * @param integer $eripID Идентификатор услуги в ЕРИП
     * @param string $personalAccNum Номер лицевого счета (уникальное значение, однозначно идентифицирующее потребителя услуг или товар)
     * @param float $amount Сумма задолженности потребителя услуг перед производителем услуг. Отрицательное значение означает задолженность производителя перед потребителем
     * @param integer $currencyCode  Код валюты требований к оплате
     * @param integer $period  Период, за который выставляется счет
     * @param integer $billTimestamp Временная метка UNIX выставления счета  
     * @param object $info Дополнительная инорфмация о счете
     *
     * return boolean true в случае успешного добавления, иначе - false
     */
    public function addBill($userId, $eripID, $personalAccNum, $amount, $currencyCode, $period, $billTimestamp, $info) {
        global $logger;
        $this->ping();
        
        //создаем части SQL-запроса для заполнения необязательных столбцов, использую данные массива $info
        $optionalFields = '';
        $optionalValues = '';
        if( ! empty ( $info ) ) {
            if ( array_key_exists('customerFullname', $info) ) {
                $optionalFields .= ', `customer_fullname`';
                $optionalValues .= ", '{$info['customerFullname']}'";
            }
            if ( array_key_exists('customerAddress', $info) ) {
                $optionalFields .= ', `customer_address`';
                $optionalValues .= ", '{$info['customerAddress']}'";
            }
            if ( array_key_exists('additionalInfo', $info) ) {
                $optionalFields .= ', `additional_info`';
                $optionalValues .= ", '{$info['additionalInfo']}'";
            }
            if ( array_key_exists('additionalData', $info) ) {
                $optionalFields .= ', `additional_data`';
                $optionalValues .= ", '{$info['additionalData']}'";
            }
            if ( array_key_exists('meters', $info) ) {
                //TODO развернуть массив meters
            }
        }

        try {
            $query = "INSERT INTO bills (`user`, `erip_id`, `personal_acc_num`, `amount`, " . (! empty( $period ) ? '`period`, ' : '') . "`currency_code`, `datetime` $optionalFields) " .
                   "VALUES ('$userId', '$eripID', '$personalAccNum', '$amount', " . (! empty( $period ) ? "'$period', " : '') . "'$currencyCode', FROM_UNIXTIME('$billTimestamp') $optionalValues)";
            if ( $this->db->query($query) ) {
                return true;
            } else {
                $logger->write ('Ошибка добавления счета: ' . $this->db->error, 'error');
                return false;
            }
           
        } catch ( Exception $e ) {
            $logger->write ($e, 'error');
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
    public function addRunningOperation($userId, $type, array $operationParams = null) {
        $this->ping();
        
        try {
            $stmt = $this->db->prepare('INSERT INTO running_operations (owner, type) VALUES (?, ?)');
            $stmt->bind_param('ii', $userId, $type);

            global $logger;
            if ( $stmt->execute() ) {
                if ( @$operationParams ) {
                    $runningOperationId = $this->db->insert_id;
                    
                    foreach ( $operationParams as $name => $value ) {
                        $stmt = $this->db->prepare('INSERT INTO runops_custom_params (operation, param_name, value) VALUES (?, ?, ?)');
                        $stmt->bind_param('iss', $runningOperationId, $name, $value);
                        if ( ! $stmt->execute() ) {
                            $logger->write('Ошибка добавления параметров выполняющейся операции: ' . $this->db->error, 'error');
                            return false;
                        }
                    }

                    return true;
                }
            } else {
                $logger->write( 'Ошибка добавления выполняющейся операции: ' . $this->db->error, 'error');
                return false;
            }
           
        } catch ( Exception $e ) {
            $logger->write( $e, 'error');
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
        global $logger;
        $this->ping();
        
        try {
            $stmt = $this->db->prepare('SELECT RO.*, U.name AS username, OT.name AS typename, OT.description FROM running_operations RO JOIN users U ON RO.owner = U.id JOIN operations_types OT ON OT.id = RO.type WHERE RO.id = ?');
            $stmt->bind_param('i', $operationId);
            $stmt->execute();
            $operation = $this->fetch($stmt);
            if ( is_array( $operation ) ) {
                $operation = $operation[0];
            }

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
                $logger->write($this->db-error, 'error');
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write( $e, 'error');
        }
        
        return ! empty( $operation ) ? $operation : array();
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
        $this->ping();
        
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
                $logger->write( $this->db-error, 'error');
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write( $e, 'error');
        }

        return $operations;
    }

    /**
     * Возращает список id активных пользователей, которые являются владельцами хотя бы одной выполняемой операции
     * 
     * @return array пустой массив, если таких пользователей нету
     */
    public function getUsersWithRunningOperations() {
        $userIds = array();
        $this->ping();

        try {
            $owners = $this->fetch($this->db->query('SELECT DISTINCT U.id FROM running_operations RO JOIN users U ON RO.owner = U.id AND U.state = 1'));
            foreach ( $owners as $row ) {
                $userIds[] = $row['id'];
            }
            
            global $logger;
            if ($this->db->errno) {
                $logger->write( $this->db-error, 'error', __FILE__, __LINE__ );
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write( $e, 'error' );
        }
        
        return $userIds;
    }

     /**
     * Возращает список id активных пользователей
     * 
     * @return array пустой массив, если таких пользователей нету
     */
    public function getActiveUsers() {
        $this->ping();
        
        $userIds = array();

        try {
            $users = $this->fetch($this->db->query('SELECT id FROM users WHERE state = 1'));
            foreach ( $users as $row ) {
                $userIds[] = $row['id'];
            }
            
            global $logger;
            if ($this->db->errno) {
                $logger->write($this->db-error, 'error');
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write($e, 'error');
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
        $this->ping();
        
        try {
            $stmt = $this->db->prepare('SELECT * FROM bills WHERE id = ?');
            $stmt->bind_param('i', $billNum);
            $stmt->execute();
            $bill = $this->fetch($stmt);

            global $logger;
            if ($this->db->errno) {
                $logger->write( $this->db-error, 'error');
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write($e, 'error');
        }
        
        return isset( $bill[0] ) ? $bill[0] : array();
    }

     /**
     * Возвращает id пользователя, который является владельцем счета с номером $billNum
     *
     * @param integer $billNum
     * @return integer id пользователя. В случае ошибки или несуществования счета возвращается null
    */
    public function getBillUser($billNum) {
        global $logger;
        $this->ping();
        
        try {
            $stmt = $this->db->prepare('SELECT user FROM bills WHERE id = ?');
            $stmt->bind_param('i', $billNum);
            $stmt->execute();
            $stmt->bind_result($billUser);
            $stmt->fetch();

            if ($this->db->errno) {
                $logger->write( $this->db-error, 'error');
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write( $e, 'error');
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
        $this->ping();
        
        try {
            $stmt = $this->db->prepare('SELECT status FROM bills WHERE id = ?');
            $stmt->bind_param('i', $billNum);
            $stmt->execute();
            $stmt->bind_result($billStatus);
            $stmt->fetch();

            if ($this->db->errno) {
                $logger->write( $this->db-error, 'error');
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write( $e, 'error');
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
        $this->ping();
        
        try {
            $stmt = $this->db->prepare('UPDATE bills SET status = ? WHERE id = ?');
            $stmt->bind_param('ii', $status,  $billNum);
            $setSuccessful = $stmt->execute();

            if ($this->db->errno) {
                $logger->write( $this->db-error, 'error');
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write( $e, 'error');
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
                $logger->write( $this->db-error, 'error');
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write( $e, 'error');
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
        $this->ping();
        
        try {
            $deleteSuccessful = $this->db->query("DELETE FROM bills WHERE id = $billNum");
            
            if ($this->db->errno) {
                $logger->write( $this->db-error, 'error');
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write( $e, 'error');
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
        global $logger;
        $this->ping();
        
        //части SQL-запроса для необязательных условий
        $optionalConditions = '';
        if ( null !== $eripID ) { $optionalConditions .= "AND erip_id = $eripID "; }
        if ( null !== $status ) { $optionalConditions .= "AND status = $status"; }

        try {
            $stmt = $this->db->prepare('SELECT * FROM bills WHERE datetime BETWEEN FROM_UNIXTIME(?) AND FROM_UNIXTIME(?) AND user = ? ' . $optionalConditions);
            $stmt->bind_param('iii', $fromTimestamp, $toTimestamp, $userId);
            $stmt->execute();
            $bills = $this->fetch($stmt);

            if ($this->db->errno) {
                $logger->write( $this->db-error, 'error');
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write( $e, 'error');
        }

        return $bills;
    }

    /**
     * Проверяет существует ли счет с указанным номером
     *
     * @param integer $billNum
     * @return boolean true, если счет существует, иначе - false
     */
    public function  billExists($billNum) {
         global $logger;
         $this->ping();
        
        try {
           $billExists = $this->fetch($this->db->query("SELECT EXISTS (SELECT * FROM bills WHERE id = $billNum)"));

            if ($this->db->errno) {
                $logger->write( $this->db-error, 'error');
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write( $e, 'error');
        }
        
        return $billExists[0][key($billExists[0])] == true;
    }
    
    /**
     * Возвращает информацию по платежу
     *
     * @param $billNum номер счета, по которому проведен платеж
     * @return Запись о платеже или пустой массив, если оплаты счета с таким номером не существует
     */
    public function getPaymentsByBill($billNum) {
        global $logger;
        $this->ping();
        
        try {
            $stmt = $this->db->prepare('SELECT P.* FROM payments P JOIN bills B ON P.bill = B.id AND P.bill = ?');
            $stmt->bind_param('i', $billNum);
            $stmt->execute();
            $payment = $this->fetch($stmt);
            $logger->write('Query result data: ' . print_r($payment, true), 'debug', __FILE__, __LINE__);

            global $logger;
            if ($this->db->errno) {
                $logger->write($this->db-error, 'error');
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write($e, 'error');
        }
        
        return $payment;
    }

    /**
     * Возвращает id платежа с указанными параметрами. Если платежей, удовлетворяющих условиям несколько (вообще такого быть не должно) - то будет возвращен id первого обнаруженного 
     *
     * @param array $params параметры, которым должен соответствовать искомый платеж
     * @return integer id платежа или false, если платежа удовлетворяющего данным параметрам не существует
     */
    public function getPaymentIdWithParams(array $params) {
        global $logger;
        $this->ping();
        
        //создаем условную часть SQL-запроса
        $whereClause = '';

        $names =  array_keys($params);
        foreach ( $params as $name => $value ) {
            if ( $this->columnExists('payments', $name) ) {
                $whereClause .= "$name = $value";
                if ( end($names) !== $name ) {
                    $whereClause .= ' AND ';
                }
            }
        }
        
        try {
            $logger->write("Query SELECT id FROM payments WHERE $whereClause ", 'debug', __FILE__, __LINE__ );
            $payment = $this->fetch($this->db->query("SELECT id FROM payments WHERE $whereClause"));
            if ($this->db->errno) {
                $logger->write($this->db-error, 'error', __FILE__, __LINE__);
            }
            
            if ( empty ($payment) ) {
                return false;
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write($e, 'error');
            return false;
        }
        
        return $payment[0]['id'];
    }

    /**
     * Обновляет запись об оплате счета с номером $billNum. Создает запись, если таковой не существует
     *
     * @param $userId
     * @param $billNum
     * @param array $data обновленные значения полей.
     *
     * @return boolean true, если изменения успешно внесены, иначе - false
     */
    public function updatePaymentOnBill($userId, $billNum, $data) {
        global $logger;
        $this->ping();
        
        try {
            $paymentExists = $this->fetch($this->db->query("SELECT EXISTS (SELECT * FROM payments WHERE bill = $billNum AND `user` = $userId)"));
            if ( $paymentExists[0][key($paymentExists[0])] == true ) {
                //создаем часть SQL-запроса для изменения столбцов, используя данные массива $data.
                $setPart = '';

                foreach ( $data as $name => $value ) {
                    if ( $this->columnExists('payments', $name) && ! empty( $value ) ) {
                        $setPart .=   ! empty( $setPart ) ? ', ': '';  //из за предыдущего if не поулчается определить последнюю итарцию по ключу массива
                        if ( strpos( $name, 'datetime' ) !== false ) {// если  элемент содержит датавремя, то нужно следовать особым правилам
                            $time = strtotime( $value );
                            $setPart .= "$name = FROM_UNIXTIME($time)";
                        } else {
                            $setPart .= "$name = '$value'";
                        }
                    }
                }
                
                $successful = $this->db->query("UPDATE payments SET $setPart WHERE bill = $billNum AND `user` = $userId");
            } else {
                //создаем части SQL-запроса для заполнения столбцов, используя данные массива $data. Здесь задаются значения как для обязательных, так и необязательных столбцов
                $fields = '';
                $values = '';
                foreach ( $data as $name => $value ) {
                    if ( $this->columnExists('payments', $name) && ! empty( $value ) ) {
                        $fields .= ! empty( $fields ) ? ', ' : '';
                        $values .= ! empty( $values ) ? ', ' : '';
                        if ( strpos( $name, 'datetime' ) !== false ) { // если  элемент содержит датавремя, то нужно следовать особым правилам
                            $fields .= "$name";
                            $time = strtotime($value);
                            $values .= "FROM_UNIXTIME($time)";
                        } else {
                            $fields .= "$name";
                            $values .= "'$value'";
                        }
                    }
                }

                //здесь задаются значения для столбцов, значение которых требует некоторой обработки перед встаывкой в таблицу
                $stmt = $this->db->prepare("INSERT INTO payments (`user`, bill, $fields) VALUES (?, ?, $values)");
                $stmt->bind_param('ii', $userId, $billNum);
                $successful = $stmt->execute();
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write($e, 'error');
        }

        return  isset( $successful ) ? $successful : false;
    }

     /**
     * Обновляет запись об оплате счета с номером $billNum. Создает запись, если таковой не существует
     *
     * @param $userId
     * @param $billNum
     * @param array $data обновленные значения полей.
     *
     * @return boolean true, если изменения успешно внесены, иначе - false
     */
    public function updatePayment($userId, $paymentNum, $data) {
        global $logger;
        $this->ping();
        
        try {
            if ( $paymentNum ) {
                //создаем часть SQL-запроса для изменения столбцов, используя данные массива $data.
                $setPart = '';
                
                foreach ( $data as $name => $value ) {
                    if ( $this->columnExists('payments', $name) ) {
                        $setPart .=   ! empty( $setPart ) ? ', ': '';  //из за предыдущего if не поулчается определить последнюю итарцию по ключу массива с помощью end()
                        if ( strpos( $name, 'datetime' ) !== false ) { // если  элемент содержит датавремя, то нужно следовать особым правилам
                            $time = strtotime( $value );
                            $setPart .= "$name = FROM_UNIXTIME($time)";
                        } else {
                            $setPart .= "$name = '$value'";
                        }
                    }
                }

                $successful = $this->db->query("UPDATE payments SET $setPart WHERE id = $paymentNum AND `user` = $userId");
            } else {
                //создаем части SQL-запроса для заполнения столбцов, используя данные массива $data. Здесь задаются значения как для обязательных, так и необязательных столбцов
                $fields = '';
                $values = '';
                
                foreach ( $data  as $name => $value ) {
                    if ( $this->columnExists('payments', $name) && ! empty( $value ) ) {
                        $fields .= ! empty( $fields ) ? ', ' : '';
                        $values .= ! empty( $values ) ? ', ' : '';
                        if ( strpos( $name, 'datetime' ) !== false ) { // если  элемент содержит датавремя, то нужно следовать особым правилам
                              $fields .= "$name";
                              $time = strtotime($value);
                              $values .= "FROM_UNIXTIME($time)";
                        } else {
                            $fields .= "$name";
                            $values .= "'$value'";
                        }
                    }
                }

                $successful = $this->db->query("INSERT INTO payments ( `user`, $fields ) VALUES ( $userId, $values )");
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write($e, 'error');
        }

        return isset( $successful ) ? $successful : false;
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
        global $logger;
        $this->ping();
        
        //части SQL-запроса для необязательных условий
        $optionalConditions = '';
        if ( null !== $eripID ) { $optionalConditions .= "AND erip_id = $eripID "; }
        if ( null !== $status ) { $optionalConditions .= "AND status = $status"; }

        try {
           $result = $this->db->query("SELECT * FROM payments WHERE payment_datetime BETWEEN FROM_UNIXTIME($fromTimestamp)" .
                                     "AND FROM_UNIXTIME($toTimestamp) AND `user` = $userId " . $optionalConditions );
            $payments = $this->fetch($result);

            if ($this->db->errno) {
                $logger->write( $this->db-error, 'error');
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write($e, 'error');
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
    public function finishOperation($operationId, $endStatus = true, $additionalInfo = '') {
        global $logger;
        $this->ping();
        
        $operation = $this->getRunningOperation($operationId);
        if ( empty($operation) ) {
            return false;
        }
        $operation['start_datetime'] = strtotime($operation['start_datetime']);
        
        try {
            $deleteSuccessful = $this->db->query("DELETE FROM running_operations WHERE id = $operationId");

            if ( ! $deleteSuccessful ) {
                return false;
            }

            $stmt = $this->db->prepare('INSERT INTO operations_history (operation_id, username, operation_type_name, operation_desc, start_datetime, end_datetime, end_status, additional_info) VALUES (?, ?, ?, ?, FROM_UNIXTIME(?), FROM_UNIXTIME(?), ?, ?)');
            $operationEndTime = time();
            $stmt->bind_param('isssiiis', $operation['id'], $operation['username'], $operation['typename'], $operation['description'], $operation['start_datetime'], $operationEndTime, $endStatus, $additionalInfo);
            $successfulArchive = $stmt->execute();
            if ( ! $successfulArchive ) {
                $logger->write( "Не удалось добавить операцию с номером {$operation['id']} в историю операций", 'error');
            }
            
            if ($this->db->errno) {
                $logger->write($this->db-error, 'error', __FILE__, __LINE__);
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write( $e, 'error');
        }  

        return true;
    }

    /**
     * Возвращает состояние пользователя с указаным именем
     *
     * @param string $username 
     *
     * @return int status или false, если пользователя с именем $username не существует
     */
    public function getUserState( $username ) {
        global $logger;
        $this->ping();
        
        try {
            $result = $this->fetch($this->db->query("SELECT state FROM users WHERE name LIKE '$username' "));
            if ($this->db->errno) {
                $logger->write($this->db-error, 'error', __FILE__, __LINE__);
            }
            
            if ( empty ($result) ) {
                return false;
            }
        } catch (mysqli_sql_exception $e) {
            $logger->write($e, 'error');
            return false;
        }
        
        return $result[0]['state'];
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
        $this->ping();

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
                $logger->write( $e, 'error');
                return -1;
            }  
        }

        return in_array($columnName, $this->tableColumns[$tableName]);
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
