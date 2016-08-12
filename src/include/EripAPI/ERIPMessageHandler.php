<?php

namespace EripAPI;

include_once API_ROOT_DIR . '/include/util/Functions.php';

/**
 * Класс, предоставляющий методы для обработки сообщений ЕРИП. Производит действия, который должны быть выполнены при появлении собщения какого-либо типа, например, сохраняет платеж в БД.
 * 
 * @package EripAPI
 */
class ERIPMessageHandler {

    const HMAC_ALG = 'sha512';

    /**
     * @access private
     * @var integer
     *
     */
    private $userId;
    
    /**
     * Список выполняющихся операций пользователя, которые ждут обработки файлов (Операции типа "мониторинг статуса счета" и "мониторинг поступления нвоых платежей")
     *2
     * @access private
     * @var array
     */
    private $operations;

     /**
     * Список счетов, для которых ожидается оплата
     *
     * @access private
     * @var array
     */
    private $bills;

    public function __construct($userId) {
        global $db;
        global $logger;

        $this->userId = $userId;
        
        $this->operations = array_merge( $db->getRunningOperations($userId, 1), $db->getRunningOperations($userId, 2));
        foreach ( $this->operations as $index => $operation ) {
            if ( $operation['type'] !== 1 ) {
                continue;
            }
            
            if ( ! isset( $operation['params']['bill'] ) ) {
                $logger->write( "Для операции {$operation['id']} не задан номер счета", 'error');
                unset ( $this->operations[$index] );
                continue;
            }
            $billNum = $operation['params']['bill'];
            $bill = $db->getBill($billNum);
            if ( empty($bill) ) {
                $logger->write( ": Не удалось получить информацию о счете с номером $billNum", 'error', __FILE__, __LINE__);
                continue;
            }

            $this->bills[$billNum] = $bill;
        }
    }

    /**
     * Обрабатывает сообщение, в соответствии с операцией типа "мониторинг статуса счета".
     *
     * @param array $message
     */
    public function handle($message) {
        $method = "handle{$message['type']}";
        self::$method($message);
    }

    /**
     * Обрабатывает сообщение 204, в соответствии с состоянием операции типа "мониторинг статуса счета".
     *
     * @param array $message
     */
    public function handle204($message) {
        global $db;
        global $logger;
        
        $billNum =  $message['header']['msg202_num'] ;
        $operation = $this->getMonitoringOperationByBill($billNum);
        if ( $operation ) {
            if ( $message['header']['result'] != 0 ) {
                $billStatus = 3;
                $db->setBillStatus($billNum, $billStatus);
                $db->setBillError($billNum, $message['header']['err_msg']);
                $db->finishOperation($operation['id'], false);

                $url = isset( $operation['params']['callbackURL'] ) ? $operation['params']['callbackURL'] : null;
                $params = ['bill' => $billNum, 'status' => $billStatus]; //клиенту передается статус именно СЧЕТА, а не платежа

                if ( $url ) {
                    $this->callbackNotify($url, $params);
                }
                
                $logger->write( "ошибка обработки сообщения 202 {$message['header']['err_msg']}", 'error');
            } else {
                $db->setBillStatus($billNum, 1);
                $logger->write("сообщение 202 по счету номер {$message['header']['msg202_num']} успешно обработано сервером ЕРИП", 'main');
            }
        } else {
            $logger->write("Попытка обработать неожиданное сообщение: счет с номером $billNum не ожидал изменения статуса либо не существует.", 'error', __FILE__, __LINE__);
        }
    }

    /**
     * Обрабатывает сообщение 206, в соответствии с операцией типа "мониторинг статуса счета" или "мониторинг поступления новых платежей".
     *
     * @param array $message
     */
    public function handle206($message) {
        global $db;
        global $logger;

        foreach ( $message['body'] as $paymentEntry ) {
            $billNum = $this->getBillNumByPayment($paymentEntry);

            if ( $billNum ) { // если совершен платеж по счету
                $operation = $this->getMonitoringOperationByBill($billNum) and
                           $addSuccessful = $db->updatePaymentOnBill( $this->userId, $billNum, array_merge($message['header'], $paymentEntry, ['status' => 1, ])); 
                
                if ( $addSuccessful && ! empty($operation) ) {
                    $params = ['service_id' => $paymentEntry['erip_id'], 'bill' => $billNum, 'account' => $paymentEntry['personal_acc_num'], 'status' => 1, 'amount' => $paymentEntry['amount'] ]; //клиенту передается статус именно ПЛАТЕЖА, а не счета
                    
                    $url = isset( $operation['params']['callbackURL'] ) ? $operation['params']['callbackURL'] : null;
                    if ( $url ) {
                        $this->callbackNotify($url, $params);
                    }
                } else {
                    $logger->write( 'Ошибка добавления записи об оплате в базу!!!', 'error');
                }
            } else { // если совершен платеж  не по счету
                $operation = $this->getUserPaymentMonitoringOperation() and
                           $addSuccessful = $db->updatePayment( $this->userId, null, array_merge($message['header'], $paymentEntry, ['status' => 1, ]));
                
                if ( ! empty( $addSuccessful ) && ! empty ($operation) ) {
                    $params = ['service_id' => $paymentEntry['erip_id'], 'account' => $paymentEntry['personal_acc_num'], 'status' => 1, 'amount' => $paymentEntry['amount']]; //клиенту передается статус ПЛАТЕЖА, а не счета
                    
                    $url = isset( $operation['params']['callbackURL'] ) ? $operation['params']['callbackURL'] : null;
                    if ( $url ) {
                        $this->callbackNotify($url, $params);
                    }
                    
                    $logger->write("Сообщение 206 для пользователя {$this->userId} успешно обработано", 'main');
                } else {
                    $logger->write('Ошибка добавления записи об оплате в базу!!!', 'error');
                }
            }
        }
    }

    /**
     * Обрабатывает сообщение 210, в соответствии с операцией типа "мониторинг статуса счета" или "мониторинг поступления новых платежей".
     * Вызов этой функции для какого-либо конкретного $message должен происходить после вызова 
     * handle206() для того же $message
     *
     * @param array $message
     */
    public function handle210($message) {
        global $db;
        global $logger;

        foreach ( $message['body'] as $paymentEntry ) {
            $billNum = $this->getBillNumByPayment($paymentEntry);
            
            if ( $billNum ) { // если совершен платеж по счету
                $operation = $this->getMonitoringOperationByBill($billNum) and               
                           $updateSuccessful = $db->updatePaymentOnBill( $this->userId, $billNum, array_merge($message['header'], $paymentEntry, ['status' => 2, ])); //для начала сойдет :)
                
                if ( $updateSuccessful && ! empty($operation) ) {
                    $params = ['bill' => $billNum, 'account' => $paymentEntry['personal_acc_num'], 'status' => 2, 'amount' => $paymentEntry['amount']]; //клиенту передается статус именно ПЛАТЕЖА, а не счета
                    
                    $url = isset( $operation['params']['callbackURL'] ) ? $operation['params']['callbackURL'] : null;
                     if ( $url ) {
                        $this->callbackNotify($url, $params);
                    }
                     
                } else {
                    $logger->write('Ошибка обновления записи об оплате в базе!!!', 'error');
                }
            } else if ( $operation = $this->getUserPaymentMonitoringOperation() ) {  // если совершен платеж не по счету и ожидаются оплаты не по счетам
                $paymentId = $this->getPaymentIdByPayment($paymentEntry);
                $updateSuccessful = $db->updatePayment(  $this->userId, ! empty( $paymentId ) ? $paymentId : null , array_merge($message['header'], $paymentEntry, ['status' => 2, ]));
                
                if ( $updateSuccessful ) {
                    $params = ['service_id' => $paymentEntry['erip_id'], 'account' => $paymentEntry['personal_acc_num'], 'status' => 2, 'amount' => $paymentEntry['amount']]; //клиенту передается статус ПЛАТЕЖА, а не счета
                    
                    $url = isset( $operation['params']['callbackURL'] ) ? $operation['params']['callbackURL'] : null;
                    if ( $url ) {
                        $this->callbackNotify($url, $params);
                    }
                } else {
                    $logger->write('Ошибка добавления записи об оплате в базу!!!', 'error');
                }
            } else {
                $logger->write('Неожиданное сообщение 210', 'error');
            }
        }
    }

     /**
     * Обрабатывает сообщение 216, в соответствии с операцией типа "мониторинг статуса счета" или "мониторинг поступления нвоых платежей.
     * Вызов этой функции для какого-либо конкретного $message должен происходить после вызова 
     * handle206() для того же $message
     *
     * @param array $message
     */
    public function handle216($message) {
        global $db;
        global $logger;

        foreach ( $message['body'] as $paymentEntry ) {
            $billNum = $this->getBillNumByPayment($paymentEntry);
            $reversalTimestamp = strtotime($message['header']['reversal_datetime']);
            
            if ( $billNum ) { // если совершен платеж по счету
                $operation = $this->getMonitoringOperationByBill($billNum) and
                           $updateSuccessful = $db->updatePaymentOnBill( $this->userId, $billNum, array_merge($message['header'], $paymentEntry, ['status' => 3, 'reversal_datetime' => $reversalTimestamp])); 
                if ( $updateSuccessful && ! empty($operation) ) {
                    $params = ['service_id' => $paymentEntry['erip_id'], 'bill' => $billNum, 'account' => $paymentEntry['personal_acc_num'], 'status' => 3, 'amount' => $paymentEntry['amount']]; //клиенту передается статус именно ПЛАТЕЖА, а не счета
                    $url = isset( $operation['params']['callbackURL'] ) ? $operation['params']['callbackURL'] : null;
                    if ( $url ) {
                        $this->callbackNotify($url, $params);
                    }
                } else {
                    $logger->write('Ошибка обновления записи об оплате в базе!!!', 'error');
                }
            }  else if ( $operation = $this->getUserPaymentMonitoringOperation() ) { // если совершен платеж не по счету и ожидаются оплаты не по счетам
                $paymentId = $this->getPaymentIdByPayment($paymentEntry);
                $updateSuccessful = $db->updatePayment( $this->userId, ! empty( $paymentId ) ? $paymentId : null, array_merge($message['header'], $paymentEntry, ['status' => 3, 'reversal_datetime' => $reversalTimestamp] ) );
                if ( $updateSuccessful ) {
                    $params = ['service_id' => $paymentEntry['erip_id'], 'account' => $paymentEntry['personal_acc_num'], 'status' => 3, 'amount' => $paymentEntry['amount']]; //клиенту передается статус ПЛАТЕЖА, а не счета
                    $url = isset( $operation['params']['callbackURL'] ) ? $operation['params']['callbackURL'] : null;
                    if ( $url ) {
                        $this->callbackNotify($url, $params);
                    }
                } else {
                    $logger->write( 'Ошибка добавления записи об оплате в базу!!!' , 'error');
                }
            }
        }
    }

    /**
     * Возвращает данные операции монтиринга изменения статсуса счета с заданным номером. Проверяются только операции мониторинга текущего пользователя
     *
     * @param $billNum
     * @return array Массив с данными операции, если операция существует или false, если не существует
     */
    private function getMonitoringOperationByBill($billNum) {
        global $logger;
        
        foreach ( $this->operations as $operation ) {
            if ( $operation['params']['bill'] == $billNum ) {
                return $operation;
            }
        }

        return false;
    }

     /**
     * Возвращает данные операции типа "мониторинг поступления нвоых платежей. Проверяются только операции текущего пользователя
     *
     * @return array Массив с данными операции, если операция существует или false, если не существует
     */
    private function getUserPaymentMonitoringOperation() {
        foreach ( $this->operations as $operation ) {
            if ( $operation['type'] === 2 ) {
                return $operation;
            }
        }

        return false;
    }

    /**
     * Определяет счет, по которому была совершена оплата, записи о которой в сообщении является $paymentEntry, и возвращает его номер
     *
     * @param array $paymentEntry
     * @return integer Номер счета, если оплата совершена по счету или false, если соответствия не найдено
     */
    private function getBillNumByPayment($paymentEntry) {
        if ( empty ( $paymentEntry['bill_datetime'] ) ) {
            return false;
        }
        
        foreach ( $this->bills as $billNum => $bill ) {
            if ( $bill['user'] == $this->userId &&
                 $bill['personal_acc_num'] == $paymentEntry['personal_acc_num'] &&
                 $bill['erip_id'] == $paymentEntry['erip_id'] &&
                 strtotime( $bill['datetime'] ) === strtotime($paymentEntry['bill_datetime']) &&
                 ( empty ( $bill['period'] ) &&  empty ( $paymentEntry['period'] ) || $bill['period'] == $paymentEntry['period'] ) 
            ) {
                return $billNum;
            }
        }

        return false;
    }

    /**
     * Определяет id записи об оплате по сообщению об оплате, которым является $payment, и возвращает этот id
     *
     * @param array $payment
     * @return integer Номер оплаты, или false, если соответствия не найдено
     */
    private function getPaymentIdByPayment($paymentEntry) {
        global $logger;
        global $db;

        $params = array (
            'user' => $this->userId,
            'erip_id' => $paymentEntry['erip_id'],
            'personal_acc_num' => $paymentEntry['personal_acc_num'],
            'erip_op_num' => $paymentEntry['erip_op_num'],
            'agent_op_num' => $paymentEntry['agent_op_num'],
            'device_id' => $paymentEntry['device_id'],
            'amount' => $paymentEntry['amount'],
        );
        
        $payment = $db->getPaymentIdWithParams($params);
        return $payment;
    }
    
    /**
     * Уведомляет клиента, вызывая предоставленный им callback. Метод берет на себя добавление HMAC к запросу
     *
     * @param string $url;
     * @param array $params
     */
    private function callbackNotify($url, $params) {
        global $logger;
        global $db;

        $secretKey = $db->getUserSecretKey($this->userId); // получаем ключ в основном потоке, т.к. существует риск что потом родительский поток закроет соединение с mysql прежде чем дочерний поток запросит ключ

        $pid = pcntl_fork();
        if ( $pid == -1 ) {
            $logger->write( 'Ошибка запуска фоновой процедуры отправки уведомления клиенту: не удалось создать дочерний процесс', 'error');
        } else if ( $pid ) {
            return;
        } else {
            //начало кода дочернего процесса
            
            $url .= '?';
            $hmacText = '';
            foreach ( $params as $key => $value ) {
                $url .= "$key=$value&";
                $hmacText .= $value;
            }
            //добавляем временнУю метку и HMAC
            $time = time();
            $hmacText .= $time;
            $hmac = hash_hmac( self::HMAC_ALG, $hmacText, $secretKey );
            
            $url .= "time=$time&hmac=$hmac";

            for ( $tryCount = 0; $tryCount < 3; $tryCount++ ) {
                sleep($tryCount * 5);

                $httpCode = get_http_response_code($url);
                if ( 200 == $httpCode) {
                    die(0);
                }
            }

            $logger->write( 'Не удалось отправить уведомление клиенту: клиент не ответил кодом 200', 'error');
            die(1);
        }
    }
}
