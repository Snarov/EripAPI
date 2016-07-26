<?php

namespace EripAPI;

include_once API_ROOT_DIR . '/include/util/Functions.php';

/**
 * Класс, предоставляющий методы для обработки сообщений ЕРИП. Производит действия, который должны быть выполнены при появлении собщения какого-либо типа, например, сохраняет платеж в БД.
 * 
 * @package EripAPI
 */
class ERIPMessageHandler {

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
        
        $this->operations = $db->getRunningOperations($userId, 1, 2);
        foreach ( $this->operations as $operation) {
            if ( $operation['type'] !== 1 ) {
                // $logger->write('error', __METHOD__ . ': Для операции типа типа "мониторинг статуса счета" не задан номер счета');
                continue;
            }
            
            $billNum = $operation['params']['bill'];
            $bill = $db->getBill($billNum);
            if ( empty($bill) ) {
                $logger->write('error', __METHOD__ . ": Не удалось получить информацию о счете с номером $billNum");
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
        
        $billNum = $message['header']['msg202_num'];
        $operation = $this->getMonitoringOperationByBill($billNum);
        if ( $operation ) {
            if ( $message['header']['result'] != 0 ) {
                $billStatus = 0;
                $db->setBillStatus($billNum, $billStatus);
                $db->setBillError($billNum, $message['header']['err_message']);
                $db->finishOperation($operation['id']);

                $params = ['bill' => $billNum, 'status' => $billStatus]; //клиенту передается статус именно СЧЕТА, а не платежа
                $this->callbackNotify($url, $params);

                $logger->write('error', "ошибка обработки сообщения 202: {$message['header']['err_message']}");
            }
        } else {
            $logger->write('error', "Попытка обработать неожиданное сообщение: счет с номером $billNum не ожидал изменения статуса.");
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
                $operation = $this->getMonitoringOperationByBill($billNum);
                $addSuccessful = $db->updatePaymentOnBill($billNum, array_merge($paymentEntry, $message['header'])); //для начала сойдет :)
                if ( $addSuccessful && ! empty($operation) ) {
                    $billStatus = 2;
                    $db->setBillStatus($billNum, $billStatus);

                    $params = ['bill' => $billNum, 'account' => $paymentEntry['account_num'], 'status' => $billStatus, 'amount' => $paymentEntry['amount']]; //клиенту передается статус именно СЧЕТА, а не платежа
                    $url = $operation['params']['callback_url'];                    
                    $this->callbackNotify($url, $params);
                } else {
                    $logger->write('error', 'Ошибка добавления записи об оплате в базу!!!');
                }
            } else { // если совершен платеж платеж не по счету
                $addSuccessful = $db->updatePayment(null, array_merge($paymentEntry, $message['header'])); //для начала сойдет :)
                $operation = $this->getUserPaymentMonitoringOperation();
                if ( $addSuccessful && ! empty ($operation) ) {
                    $params = ['account' => $paymentEntry['account_num'], 'status' => 1, 'amount' => $paymentEntry['amount']]; //клиенту передается статус ПЛАТЕЖА, а не счета
                    $url = $operation['params']['callback_url'];
                    $this->callbackNotify($url, $params);
                } else {
                    $logger->write('error', 'Ошибка добавления записи об оплате в базу!!!');
                }
            }
        }
    }

    /**
     * Обрабатывает сообщение 210, в соответствии с операцией типа "мониторинг статуса счета" или "мониторинг поступления нвоых платежей.
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
            if (  $billNum ) { // если совершен платеж по счету
                $operation = $this->getMonitoringOperationByBill($billNum);

                $transferTimestamp = strtotime($paymentEntry['transfer_datetime']);
                $updateSuccessful = $db->updatePaymentOnBill($billNum, array_merge($paymentEntry, $message['header'], ['status' => 2, 'transfer_timestamp' => $transferTimestamp])); //для начала сойдет :)
                if ( $updateSuccessful && ! empty($operation) ) {
                    $billStatus = 3;
                    $db->setBillStatus($billNum, $billStatus);
                    $db->finishOperation($operation['id']);

                    $params = ['bill' => $billNum, 'account' => $paymentEntry['account_num'], 'status' => $billStatus, 'amount' => $paymentEntry['amount']]; //клиенту передается статус именно СЧЕТА, а не платежа
                    $url = $operation['params']['callback_url'];
                    $this->callbackNotify($url, $params);
                } else {
                    $logger->write('error', 'Ошибка обновления записи об оплате в базе!!!');
                }
            } else {  // если совершен платеж не по счету
                $paymentId = getPaymentNumByPayment($paymentEntry);
                if ( ! $paymentId ) {
                    $logger->write('error', 'Сообщение 210 без предшествующего сообщения 206');
                    return;
                }
                
                $updateSuccessful = $db->updatePayment($paymentId, array_merge($paymentEntry, $message['header'])); //для начала сойдет :)
                $operation = $this->getUserPaymentMonitoringOperation();
                if ( $updateSuccessful && ! empty ($operation) ) {
                    $params = [ 'account' => $paymentEntry['account_num'], 'status' => 2, 'amount' => $paymentEntry['amount']]; //клиенту передается статус ПЛАТЕЖА, а не счета
                    $url = $operation['params']['callback_url'];
                    $this->callbackNotify($url, $params);
                } else {
                    $logger->write('error', 'Ошибка добавления записи об оплате в базу!!!');
                }
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
            $billNum = $this->getBillNumByPaymentMessage($paymentEntry);
            if ( ! $billNum ) { // если совершен платеж по счету
                $logger->write('error', "Попытка обработать неожиданное сообщение: получено сообщение об оплате счета, не ожидающего оплаты");
                $operation = $this->getMonitoringOperationByBill($billNum);

                $reversalTimestamp = strtotime($paymentEntry['reversal_datetime']);
                $updateSuccessful = $db->updatePaymentOnBill($billNum, array_merge($paymentEntry, $message['header'], ['status' => 3, 'reversal_timestamp' => $reversalTimestamp])); //для начала сойдет :)
                if ( $updateSuccessful && ! empty($operation) ) {
                    $billStatus = 5;
                    $db->setBillStatus($billNum, $billStatus);
                    $db->finishOperation($operation['id']);

                    $params = ['bill' => $billNum, 'account' => $paymentEntry['account_num'], 'status' => $billStatus, 'amount' => $paymentEntry['amount']]; //клиенту передается статус именно СЧЕТА, а не платежа
                    $url = $operation['params']['callback_url'];
                    $this->callbackNotify($url, $params);
                } else {
                    $logger->write('error', 'Ошибка обновления записи об оплате в базе!!!');
                }
            } else { // если совершен платеж не по счету
                $paymentId = getPaymentNumByPayment($paymentEntry);
                if ( ! $paymentId ) {
                    $logger->write('error', 'Сообщение 216 без предшествующего сообщения 206');
                    return;
                }
                
                $updateSuccessful = $db->updatePayment($paymentId, array_merge($paymentEntry, $message['header'])); //для начала сойдет :)
                $operation = $this->getUserPaymentMonitoringOperation();
                if ( $updateSuccessful && ! empty ($operation ) ) {
                    $params = ['account' => $paymentEntry['account_num'], 'status' => 3, 'amount' => $paymentEntry['amount']]; //клиенту передается статус ПЛАТЕЖА, а не счета
                    $url = $operation['params']['callback_url'];
                    $this->callbackNotify($url, $params);
                } else {
                    $logger->write('error', 'Ошибка добавления записи об оплате в базу!!!');
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
        foreach ( $this->operations as $operation ) {
            if ( $operation['params']['bill'] === $billNum ) {
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
        if ( empty ( $paymentEntry['account_num'] ) ) {
            return false;
        }
        
        foreach ( $this->bills as $billNum => $bill ) {
            if ( $bill['personal_acc_num'] === $paymentEntry['account_num'] &&
                 $bill['erip_id'] === $paymentEntry['erip_id'] &&
                 $bill['timestamp'] === strtotime($paymentEntry['bill_datetime'])
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
        global $db;
        
        if ( empty ( ! empty ( $paymentEntry['account_num'] ) ) ) {
            return false;
        }

        $params = array (
            'central_node_op_num' => $paymentEntry['central_node_op_num'],
            'agent_op_num' => $paymentEntry['agent_op_num'],
            'device_id' => $paymentEntry['device_id'],
            'amount' => $paymentEntry['amount'],
        );
        
        return $db->getPaymentIdWithParams($params);
    }
    
    /**
     * Уведомляет клиента, вызывая предоставленный им callback. Метод берет на себя добавление HMAC к запросу
     *
     * @param string $url;
     * @param array $params
     */
    private function callbackNotify($url, $params) {
        global $logger;
        
        $pid = pcntl_fork();
        if ( $pid == -1 ) {
            $logger->write('error', 'Ошибка запуска фоновой процедуры отправки уведомления клиенту: не удалось создать дочерний процесс');
        } else if ( $pid > 0 ) {
            //начало кода дочернего процесса
            global $db;
            
            $url .= '?';
            foreach ( $params as $key => $value ) {
                $url .= "$key=$value&";
                $hmacText .= $value;
            }
            //добавляем временнУю метку и HMAC
            $time = time();

            $hmacText .= $time;
            $secretKey = $db->getUserSecretKey($this->userId);
            $hmac = hash_hmac( self::HMAC_ALG, $hmacText, $secretKey );
            
            $url .= "&time=$time&hmac=$hmac";
            

            $getParams = ['timeout' => 5];
            for ( $tryCount = 0; $tryCount < 3; $tryCount++ ) {
                sleep($tryCount * 15);

                $httpCode = get_http_response_code($url);
                if ( 200 == $httpCode) {
                    die(0);
                }
            }

            $logger->write('error', 'Не удалось отправить уведомление клиенту: клиент не ответил кодом 200');
            die(1);
        }
    }
}
