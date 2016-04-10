<?php

require __DIR__ . '/IEripAPI.php';
require __DIR__ . '/Security.php';
require __DIR__ . '/ParamsChecker.php';
require __DIR__ . '/ERIPMessageManager.php';

use EripAPI\ParamsChecker as ParamsChecker;
use EripAPI\ERIPMessageManager as MessageManager;

class APIInternalError extends Exception{};

class EripAPI implements IEripAPI {

    public $userId; // от имени пользователя с этим id выполняются функции API

     /**
     * Выставить новый счет в ЕРИП.
     *
     * @param integer $eripID Идентификатор услуги в ЕРИП
     * @param string $personalAccNum Номер лицевого счета (уникальное значение, однозначно идентифицирующее потребителя услуг или товар)
     * @param float $amount Сумма задолженности потребителя услуг перед производителем услуг. Отрицательное значение означает задолженность производителя перед потребителем
     * @param integer $currencyCode  Код валюты требований к оплате 
     * @param object $info Дополнительная инорфмация о счете
     * @param string $callbackURL Адрес, по которому произойдет обращение при изменении статуса заказа
     * @return integer Номер счета
     */
    function createBill( $eripID, $personalAccNum, $amount, $currencyCode, $info = null, $callbackURL = null) {
       ParamsChecker::createBillParamsCheck($eripID, $personalAccNum, $amount, $currencyCode, $info, $callbackURL);

       global $logger;
       global $db;
    
       if ( ! $db ) {
           $logger->write('error', 'Ошибка создания сообщения 202: невозможно подключиться к БД');
           throw APIInternalError(API_INTERNAL_ERR_MSG);
       }
       
       $ftpConnectionData = $db->getFtpConnectionData($this->userId);
       $msgNum = $db->getNextBillNum();
       if (  empty($ftpConnectionData) ||  empty($msgNum) ) {
           $logger->write('error', 'Ошибка создания сообщения 202: данные из БД не получены');
           throw APIInternalError(API_INTERNAL_ERR_MSG);
       }

       $eripCredentials = $db->getEripCredentials($userId);
       if ( empty($eripCredentials) ) {
           $logger->write('error', 'Ошибка создания сообщения 202: данные об абоненте ЕРИП не получены');
           throw APIInternalError(API_INTERNAL_ERR_MSG);
       }

       extract($ftpConnectionData);
       $msgManager = new MessageManager($ftp_host, $ftp_user, $ftp_password); //имена переменных не в camelCase потому что они идентичны именам столбцов в таблице БД
       if ( ! $msgManager->addMessage($msgNum, $eripID, $personalAccNum, $amount, $currencyCode, $eripCredentials, $info) ) {
           $logger->write('error', 'Ошибка создания сообщения 202: ошибка отправки файла сообщения на ftp-сервер ЕРИП');
           throw APIInternalError(API_INTERNAL_ERR_MSG);
       }
       
       $db->addBill($this->userId,  $eripID, $personalAccNum, $amount, $currencyCode, $info);
       if ( filter_var($callbackURL, FILTER_VALIDATE_URL) !== false ) {
           $db->addRunningOperation($this->userId, 1, array('callbackURL' => $callbackURL));
       }

       return $msgNum;
    }
    
    /**
     * Получить информацию о счете
     *
     * @param $billNum Номер счета
     * @return array Информация о счете или null если счета с таким номером не существует
     */
    function getBill($billNum){
        ParamsChecker::billNumCheck($billNum);

        global $db;
        if ( ! $db ) {
            $logger->write('error', 'Ошибка: невозможно подключиться к БД');
            throw APIInternalError(API_INTERNAL_ERR_MSG);
        }

        $rawBillDetails = $db->getBill($billNum);
        if ( empty ($rawBillDetails) ) {
            return null;
        }
        //Выбираем только нужные поля и изменяем стиль именования
        $billDetails['eripID'] = $rawBillDetails['erip_id'];
        $billDetails['personalAccNum'] = $rawBillDetails['personal_acc_num'];
        $billDetails['amount'] = $rawBillDetails['amount'];
        $billDetails['currencyCode'] = $rawBillDetails['currency_code'];
        $billDetails['status'] = $rawBillDetails['status'];
        $billDetails['timestamp'] = $rawBillDetails['timestamp'];
        if ( $rawBillDetails['customer_fullname'] ) {  $billDetails['info']['customerFullname'] = $rawBillDetails['customer_fullname']; }
        if ( $rawBillDetails['customer_address'] ) {  $billDetails['info']['customerAddress'] = $rawBillDetails['customer_address']; }
        if ( $rawBillDetails['additional_info'] ) {  $billDetails['info']['additionalInfo'] = $rawBillDetails['additional_info']; }
        if ( $rawBillDetails['additional_data'] ) {  $billDetails['info']['additionalData'] = $rawBillDetails['additional_data']; }
        //TODO добавить поле с информацией по счетчикам (meters)

        return $billDetails;
    }

    /**
     * Получить текущий статус счета
     * 
     * @param $billNum Номер счета 
     * @return int Код статуса (1 - Ожидает оплату 2 - Просрочен 3 - Оплачен 4 - Оплачен частично 5 - Отменен) или null в случае если счет не найден
     */
    function getBillStatus( $billNum ) {
        ParamsChecker::billNumCheck($billNum);

        global $db;
        if ( ! $db ) {
            $logger->write('error', 'Ошибка: невозможно подключиться к БД');
            throw APIInternalError(API_INTERNAL_ERR_MSG);
        }

        return $db->getBillStatus($billNum) ;
    }

    /**
     * Удалить счет
     *
     * @param $billNum Номер счета
     * @return bool true в случае успешного удаления, иначе - false
     */
    function deleteBill( $billNum ) {
        ParamsChecker::billNumCheck($billNum);

        global $logger;
        global $db;

        if ( ! $db ) {
            $logger->write('error', __METHOD__ . ': Ошибка: невозможно подключиться к БД');
            throw APIInternalError(API_INTERNAL_ERR_MSG);
        }

        $ftpConnectionData = $db->getFtpConnectionData($this->userId);
        if (  empty($ftpConnectionData) ) {
           $logger->write('error', __METHOD__ . ': Ошибка: не удается получить данные, необходимые для установления ftp-соединения');
           throw APIInternalError(API_INTERNAL_ERR_MSG);
        }

        extract($ftpConnectionData);
        $msgManager = new MessageManager($ftp_host, $ftp_user, $ftp_password); //имена переменных не в camelCase потому что они идентичны именам столбцов в таблице БД

        if ( $msgManager->deleteInMessage("$billNum.202") && $db->deleteBill($billNum) ) {
            return true;
        } else {
            $msgManager->undoDeleteMessage("$billNum.202");
            return false;
        }
    }

    /**
     * Получить список выставленных счетов за определнный промежуток времени и с определенным статусом. Если промежуток времени не указан, то возвращается список счетов за последние 30 дней
     *
     * @param int $eripID Идентификатор услуги в ЕРИП. Если не указан, то возвращаются данные по всем услугам данного ПУ.
     * @param int $fromTimestamp Начало периода (UNIX-время)
     * @param int $toTimestamp Конец периода (UNIX-время)
     * @param int $status Код статуса (1 - Ожидает оплату 2 - Просрочен 3 - Оплачен 4 - Оплачен частично 5 - Отменен). Если не указан, то будут возвращены все счета,
     * вне зависимости от их текущего статуса
     * @return array Список счетов 
     */
    function getBills( $eripID = null, $fromTimestamp = '', $toTimestamp = '', $status = '') {
        ParamsChecker::getBillsOrPaymentsParamsCheck($eripID, $fromTimestamp, $toTimestamp, $status);

        global $logger;
        global $db;
        
        if ( ! $db ) {
            $logger->write('error', __METHOD__ . ': Ошибка: невозможно подключиться к БД');
            throw APIInternalError(API_INTERNAL_ERR_MSG);
        }

        if ( '' === $fromTimestamp ) {
            $fromTimestamp = time() - 30 * 24 * 60 * 60; //устанавливается равным моменту, отстоящим на 30 дней назад.
        }
        if ( '' === $toTimestamp ) {
            $toTomestamp = time(); //устанавливается равным настоящему моменту
        }

        $rawBillDetails = $db->getBills($eripID, $fromTimestamp, $toTimestamp, $status);
        
        if ( empty ($rawBillDetails) ) {
            return null;
        }

        $billsDetails = array();
        foreach ( $rawBillsDetails as $rawBillDetails ) {
            $billDetails = array();
            
            //Выбираем только нужные поля и изменяем стиль именования
            $billDetails['eripID'] = $rawBillDetails['erip_id'];
            $billDetails['personalAccNum'] = $rawBillDetails['personal_acc_num'];
            $billDetails['amount'] = $rawBillDetails['amount'];
            $billDetails['currencyCode'] = $rawBillDetails['currency_code'];
            $billDetails['status'] = $rawBillDetails['status'];
            $billDetails['timestamp'] = $rawBillDetails['timestamp'];
            if ( $rawBillDetails['customer_fullname'] ) {  $billDetails['info']['customerFullname'] = $rawBillDetails['customer_fullname']; }
            if ( $rawBillDetails['customer_address'] ) {  $billDetails['info']['customerAddress'] = $rawBillDetails['customer_address']; }
            if ( $rawBillDetails['additional_info'] ) {  $billDetails['info']['additionalInfo'] = $rawBillDetails['additional_info']; }
            if ( $rawBillDetails['additional_data'] ) {  $billDetails['info']['additionalData'] = $rawBillDetails['additional_data']; }
            //TODO добавить поле с информацией по счетчикам (meters)

            $billsDetails[] = $billDetails;
        }

        return $billsDetails;
    }
    
    /**
     * Получить детальную информацию по платежу
     *
     * @param $billNum
     * @return object Информация об оплате и null, если платежа по счету с таким номером не существует
     */
    function getPayment($billNum) {
        ParamsChecker::billNumCheck($billNum);

        global $logger;
        global $db;
        
        if ( ! $db ) {
            $logger->write('error', __METHOD__ . ': Ошибка: невозможно подключиться к БД');
            throw APIInternalError(API_INTERNAL_ERR_MSG);
        }

        $rawPaymentDetails = $db->getPayment($billNum);
        if ( empty ($rawPaymentBillDetails) ) {
            return null;
        }
         //Выбираем только нужные поля и изменяем стиль именования
        $paymentDetails['eripID'] = $rawPaymentDetails['erip_id'];
        $paymentDetails['personalAccNum'] = $rawPaymentDetails['personal_acc_num'];
        $paymentDetails['amount'] = $rawPaymentDetails['amount'];
        $paymentDetails['fineAmount'] = $rawPaymentDetails['fine_amount'];
        $paymentDetails['currencyCode'] = $rawPaymentDetails['currency_code'];
        $paymentDetails['status'] = $rawPaymentDetails['status'];
        $paymentDetails['paymentTimestamp'] = $rawPaymentDetails['payment_timestamp'];
        $paymentDetails['eripOpNum'] = $rawPaymentDetails['erip_op_num'];
        $paymentDetails['deviceId'] = $rawPaymentDetails['device_id'];
        $paymentDetails['agentBankCode'] = $rawPaymentDetails['agent_bank_code'];
        $paymentDetails['agentAccNum'] = $rawPaymentDetails['agent_acc_num'];
        $paymentDetails['budgetPaymentCode'] = $rawPaymentDetails['budget_payment_code'];
        if ( $paymentDetails['agent_op_num'] ) { $paymentDetails['agentOpNum'] = $rawPaymentDetails['agent_op_num']; }
        if ( $paymentDetails['transfer_timestamp'] ) { $paymentDetails['transfer_timestamp'] = $rawPaymentDetails['transfer_timestamp']; }
        if ( $paymentDetails['reversal_timestamp'] ) { $paymentDetails['reversal_timestamp'] = $rawPaymentDetails['reversal_timestamp']; }
        if ( $rawPaymentDetails['customer_fullname'] ) {  $paymentDetails['info']['customerFullname'] = $rawPaymentDetails['customer_fullname']; }
        if ( $rawPaymentDetails['customer_address'] ) {  $paymentDetails['info']['customerAddress'] = $rawPaymentDetails['customer_address']; }
        if ( $paymentDetails['authorization_way'] ) { $paymentDetails['authorizationWay'] = $rawPaymentDetails['authorization_way']; }
        if ( $rawPaymentDetails['additional_info'] ) {  $paymentDetails['info']['additionalInfo'] = $rawPaymentDetails['additional_info']; }
        if ( $rawPaymentDetails['additional_data'] ) {  $paymentDetails['info']['additionalData'] = $rawPaymentDetails['additional_data']; }
        if ( $paymentDetails['authorization_way_id'] ) { $paymentDetails['authorizationWayId'] = $rawPaymentDetails['authorization_way_id']; }
        if ( $paymentDetails['device_type_code'] ) { $paymentDetails['deviceTypeCode'] = $rawPaymentDetails['device_type_code']; }
        //TODO добавить поле с информацией по счетчикам (meters)

        return $paymentDetails;
    }
    
    /**
     * Получить список оплаченных счетов за определенный промежуток времени. Если промежуток времени не указан, то возвращается список оплаченных счетов за последние 30 дней
     *
     * @param int $eripID Идентификатор услуги в ЕРИП. Если не указан, то возвращаются данные по всем услугам данного ПУ.
     * @param int $fromDatetime Начало периода (UNIX-время)
     * @param int $toDatetime Конец периода (UNIX-время)
     * @return array Список оплаченных счетов
     */
    function getPayments ( $eripID, $fromDatetime = '', $toDatetime = '' ){
        return array();
    }
}
