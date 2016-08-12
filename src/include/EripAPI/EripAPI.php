<?php

require __DIR__ . '/IEripAPI.php';
require __DIR__ . '/Security.php';
require __DIR__ . '/ParamsChecker.php';
require __DIR__ . '/ERIPMessageIO.php';

use EripAPI\ParamsChecker as ParamsChecker;
use EripAPI\ERIPMessageIO as MessageIO;

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
     * @param integer $period  Период, за который выставляется счет 
     * @param object $info Дополнительная инорфмация о счете
     * @param string $callbackURL Адрес, по которому произойдет обращение при изменении статуса заказа
     * @return integer Номер счета
     */
    function createBill( $eripID, $personalAccNum, $amount, $currencyCode, $period = null, $info = null, $callbackURL = null) {
       ParamsChecker::createBillParamsCheck($eripID, $personalAccNum, $amount, $currencyCode, $info, $callbackURL);

       global $logger;
       global $db;
       
       $ftpConnectionData = $db->getFtpConnectionData($this->userId);
       
       if (  empty($ftpConnectionData) ) {
           $logger->write( 'Ошибка создания сообщения 202: данные не получены', 'error');
           throw new APIInternalError(API_INTERNAL_ERROR_MSG);
       }

       $eripCredentials = $db->getEripCredentials($this->userId);
       if ( empty($eripCredentials) ) {
           $logger->write('Ошибка создания сообщения 202: данные об абоненте ЕРИП не получены', 'error');
           throw new APIInternalError(API_INTERNAL_ERROR_MSG);
       }

       $msgTimestamp = time();
       
       extract($ftpConnectionData);
       
       $msgNum = $db->getNextBillNum();
       if( ! $db->addBill($this->userId,  $eripID, $personalAccNum, $amount, $currencyCode, $period,  $msgTimestamp, $info) ) {
           $logger->write('Ошибка создания сообщения 202: ошибка создания записи счета', 'error');
           throw new APIInternalError(API_INTERNAL_ERROR_MSG);
       }

       $msgIO = new MessageIO($ftp_host, $ftp_user, $ftp_password); //имена переменных не в camelCase потому что они идентичны именам столбцов в таблице БД
       if ( ! $msgIO->addMessage($msgNum, $eripID, $personalAccNum, $amount, $currencyCode, $period, $eripCredentials, $msgTimestamp, $info) ) {
           $db->deleteBill($msgNum);
           $logger->write('Ошибка создания сообщения 202: ошибка отправки файла сообщения на ftp-сервер ЕРИП', 'error');
           throw new APIInternalError(API_INTERNAL_ERROR_MSG);
       }
       
       $params = array();
       if ( $callbackURL ) {
           $params['callbackURL'] = $callbackURL;
           $params['bill'] = $msgNum;
       }
       $db->addRunningOperation($this->userId, 1, $params);
       
       return $msgNum;
    }
    
    /**
     * Получить информацию о счете
     *
     * @param $billNum Номер счета
     * @return array Информация о счете или null, если счета с таким номером не существует у данного пользователя
     */
    function getBill($billNum){
        ParamsChecker::billNumCheck($billNum);

        global $db;
      
        if ( $db->getBillUser($billNum) !== $this->userId ) {
            return null;
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
        $billDetails['timestamp'] = (string)strtotime($rawBillDetails['datetime']);
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
     * @return int Код статуса (0 - в обработке; 1 - активен и  ожидает оплаты; 2 - истек; 3 - ошибочен ) или null в случае если счет не найден
     */
    function getBillStatus( $billNum ) {
        ParamsChecker::billNumCheck($billNum);

        global $db;

        if ( $db->getBillUser($billNum) !== $this->userId ) {
            return null;
        }

        return $db->getBillStatus($billNum) ;
    }

    /**
     * Удалить счет. Пока не поддерживается
     *
     * @param $billNum Номер счета
     * @return bool true в случае успешного удаления, иначе - false
     */
    function deleteBill( $billNum ) {
        return "not supported yet";
        
        global $logger;
        global $db;
        
        ParamsChecker::billNumCheck($billNum);

        if ( ! $db->billExists($billNum) ) {
            return false;
        }

        if ( $db->getBillUser($billNum) !== $this->userId ) {
            return false;
        }

        $ftpConnectionData = $db->getFtpConnectionData($this->userId);
        if (  empty($ftpConnectionData) ) {
            $logger->write( __METHOD__ . ': Ошибка: не удается получить данные, необходимые для установления ftp-соединения', 'error');
           throw new APIInternalError(API_INTERNAL_ERROR_MSG, -32003);
        }

        extract($ftpConnectionData);
        $msgManager = new MessageIO($ftp_host, $ftp_user, $ftp_password); //имена переменных не в camelCase потому что они идентичны именам столбцов в таблице БД

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
     * @param int $status Код статуса (0 - в обработке; 1 - активен и  ожидает оплаты; 2 - истек; 3 - ошибочен). Если не указан, то будут возвращены все счета,
     * вне зависимости от их текущего статуса
     *
     * @return array Список счетов 
     */
    function getBills( $eripID = null, $fromTimestamp = '', $toTimestamp = '', $status = null) {
        ParamsChecker::getBillsOrPaymentsParamsCheck($eripID, $fromTimestamp, $toTimestamp, $status);

        global $logger;
        global $db;
        
        if ( '' === $fromTimestamp ) {
            $fromTimestamp = time() - 30 * 24 * 60 * 60; //устанавливается равным моменту, отстоящим на 30 дней назад.
        } else {
            $fromTimestamp = strtotime($fromTimestamp);
        }
        if ( '' === $toTimestamp ) {
            $toTimestamp = time(); //устанавливается равным настоящему моменту
        } else {
            $toTimestamp = strtotime($toTimestamp);
        }

        $rawBillsDetails = $db->getBills($this->userId, $eripID, $fromTimestamp, $toTimestamp, $status);
        
        if ( empty ($rawBillsDetails) ) {
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
            $billDetails['timestamp'] = (string)strtotime($rawBillDetails['datetime']);
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
     * Получить детальную информацию по платежам по счету 
     *
     * @param $billNum
     * @return object Информация об оплате и null, если платежа по счету с таким номером не существует у данного пользователя
     */
    function getPaymentsOnBill($billNum) {
        ParamsChecker::billNumCheck($billNum);

        global $logger;
        global $db;
        
        if ( $db->getBillUser($billNum) !== $this->userId ) {
            return null;
        }

        $rawPaymentsDetails = $db->getPaymentsByBill($billNum);
        if ( empty ($rawPaymentsDetails) ) {
            return null;
        }

        $paymentsDetails = array();
        foreach ( $rawPaymentsDetails as $rawPaymentDetails ) {
            $logger->write("payment structure: " . print_r($rawPaymentDetails, true), 'debug' , __FILE__, __LINE__);
            //Выбираем только нужные поля и изменяем стиль именования
            $paymentDetails['eripID'] = $rawPaymentDetails['erip_id'];
            $paymentDetails['personalAccNum'] = $rawPaymentDetails['personal_acc_num'];
            $paymentDetails['amount'] = $rawPaymentDetails['amount'];
            $paymentDetails['fineAmount'] = $rawPaymentDetails['fine_amount'];
            $paymentDetails['currencyCode'] = $rawPaymentDetails['currency_code'];
            $paymentDetails['status'] = $rawPaymentDetails['status'];
            $paymentDetails['paymentTimestamp'] = (string)strtotime($rawPaymentDetails['payment_datetime']);
            $paymentDetails['eripOpNum'] = $rawPaymentDetails['erip_op_num'];
            $paymentDetails['deviceId'] = $rawPaymentDetails['device_id'];
            $paymentDetails['agentBankCode'] = $rawPaymentDetails['agent_bank_code'];
            $paymentDetails['agentAccNum'] = $rawPaymentDetails['agent_acc_num'];
            if ( $rawPaymentDetails['budget_payment_code'] ) { $paymentDetails['budgetPaymentCode'] = $rawPaymentDetails['budget_payment_code'];}
            if ( $rawPaymentDetails['agent_op_num'] ) { $paymentDetails['agentOpNum'] = $rawPaymentDetails['agent_op_num']; }
            if ( $rawPaymentDetails['transfer_datetime'] ) { $paymentDetails['transferTimestamp'] = (string)strtotime($rawPaymentDetails['transfer_datetime']); }
            if ( $rawPaymentDetails['reversal_datetime'] ) { $paymentDetails['reversalTimestamp'] = (string)strtotime($rawPaymentDetails['reversal_datetime']); }
            if ( $rawPaymentDetails['authorization_way'] ) { $paymentDetails['authorizationWay'] = $rawPaymentDetails['authorization_way']; }
            if ( $rawPaymentDetails['authorization_way_id'] ) { $paymentDetails['authorizationWayId'] = $rawPaymentDetails['authorization_way_id']; }
            if ( $rawPaymentDetails['device_type_code'] ) { $paymentDetails['deviceTypeCode'] = $rawPaymentDetails['device_type_code']; }
            if ( $rawPaymentDetails['additional_info'] ) {  $paymentDetails['info']['additionalInfo'] = $rawPaymentDetails['additional_info']; }
            if ( $rawPaymentDetails['additional_data'] ) {  $paymentDetails['info']['additionalData'] = $rawPaymentDetails['additional_data']; }
            if ( $rawPaymentDetails['customer_fullname'] ) {  $paymentDetails['info']['customerFullname'] = $rawPaymentDetails['customer_fullname']; }
            if ( $rawPaymentDetails['customer_address'] ) {  $paymentDetails['info']['customerAddress'] = $rawPaymentDetails['customer_address']; }
           
            //TODO добавить поле с информацией по счетчикам (meters)

            $paymentsDetails[] = $paymentDetails;
        }

        return $paymentsDetails;
    }
    
    /**
     * Получить список оплаченных счетов за определенный промежуток времени. Если промежуток времени не указан, то возвращается список оплаченных счетов за последние 30 дней
     *
     * @param integer $eripID Идентификатор услуги в ЕРИП. Если не указан, то возвращаются данные по всем услугам данного ПУ.
     * @param integer $fromTimestamp Начало периода (UNIX-время)
     * @param integer $toTimestamp Конец периода (UNIX-время)
     * @param integer $status Код статуса (1 - Оплата совершена, но средства не переведены на расчетный счет производителя услуги,  2 - Оплата совершена и средства переведены 
     * на расчетный счет производителя услуги   3 - Сторнирован). Если не указан, то будут возвращены все платежи, вне зависимости от их текущего статуса.
     *
     * @return array Список оплаченных счетов
     */
    function getPayments ( $eripID = null, $fromTimestamp = '', $toTimestamp = '', $status = null ){
        ParamsChecker::getBillsOrPaymentsParamsCheck($eripID, $fromTimestamp, $toTimestamp, $status);

        global $logger;
        global $db;
        
        if ( '' === $fromTimestamp ) {
            $fromTimestamp = time() - 30 * 24 * 60 * 60; //устанавливается равным моменту, отстоящим на 30 дней назад.
        }
        if ( '' === $toTimestamp ) {
            $toTimestamp = time(); //устанавливается равным настоящему моменту
        }

        $rawPaymentsDetails = $db->getPayments($this->userId, $eripID, $fromTimestamp, $toTimestamp, $status);
        
        if ( empty ($rawPaymentsDetails) ) {
            return null;
        }

        $paymentsDetails = array();
        foreach ( $rawPaymentsDetails as $rawPaymentDetails ) {
            $paymentDetails = array();

            $logger->write("payment structure: " . print_r($rawPaymentDetails, true), 'debug' , __FILE__, __LINE__);
            //Выбираем только нужные поля и изменяем стиль именования
            $paymentDetails['eripID'] = $rawPaymentDetails['erip_id'];
            $paymentDetails['personalAccNum'] = $rawPaymentDetails['personal_acc_num'];
            $paymentDetails['amount'] = $rawPaymentDetails['amount'];
            $paymentDetails['fineAmount'] = $rawPaymentDetails['fine_amount'];
            $paymentDetails['currencyCode'] = $rawPaymentDetails['currency_code'];
            $paymentDetails['status'] = $rawPaymentDetails['status'];
            $paymentDetails['paymentTimestamp'] = (string)strtotime($rawPaymentDetails['payment_datetime']);
            $paymentDetails['eripOpNum'] = $rawPaymentDetails['erip_op_num'];
            $paymentDetails['deviceId'] = $rawPaymentDetails['device_id'];
            $paymentDetails['agentBankCode'] = $rawPaymentDetails['agent_bank_code'];
            $paymentDetails['agentAccNum'] = $rawPaymentDetails['agent_acc_num'];
            if ( $rawPaymentDetails['budget_payment_code'] ) { $paymentDetails['budgetPaymentCode'] = $rawPaymentDetails['budget_payment_code'];}
            if ( $rawPaymentDetails['agent_op_num'] ) { $paymentDetails['agentOpNum'] = $rawPaymentDetails['agent_op_num']; }
            if ( $rawPaymentDetails['transfer_datetime'] ) { $paymentDetails['transferTimestamp'] = (string)strtotime($rawPaymentDetails['transfer_datetime']); }
            if ( $rawPaymentDetails['reversal_datetime'] ) { $paymentDetails['reversalTimestamp'] = (string)strtotime($rawPaymentDetails['reversal_datetime']); }
            if ( $rawPaymentDetails['authorization_way'] ) { $paymentDetails['authorizationWay'] = $rawPaymentDetails['authorization_way']; }
            if ( $rawPaymentDetails['authorization_way_id'] ) { $paymentDetails['authorizationWayId'] = $rawPaymentDetails['authorization_way_id']; }
            if ( $rawPaymentDetails['device_type_code'] ) { $paymentDetails['deviceTypeCode'] = $rawPaymentDetails['device_type_code']; }
            if ( $rawPaymentDetails['additional_info'] ) {  $paymentDetails['info']['additionalInfo'] = $rawPaymentDetails['additional_info']; }
            if ( $rawPaymentDetails['additional_data'] ) {  $paymentDetails['info']['additionalData'] = $rawPaymentDetails['additional_data']; }
            if ( $rawPaymentDetails['customer_fullname'] ) {  $paymentDetails['info']['customerFullname'] = $rawPaymentDetails['customer_fullname']; }
            if ( $rawPaymentDetails['customer_address'] ) {  $paymentDetails['info']['customerAddress'] = $rawPaymentDetails['customer_address']; }
            //TODO добавить поле с информацией по счетчикам (meters)

            $paymentsDetails[] = $paymentDetails;
        }

        return $paymentsDetails;
    }
}
