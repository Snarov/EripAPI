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
     * @return object billDetails
     */
    function getBillDetails( $billNum ){
        return null;
    }

    /**
     * Получить текущий статус счета
     * 
     * @param $billNum Номер счета 
     * @return int Код статуса (1 - Ожидает оплату 2 - Просрочен 3 - Оплачен 4 - Оплачен частично 5 - Отменен)
     */
    function getBillStatus( $billNum ) {
        return 1;
    }

    /**
     * Удалить счет
     *
     * @param $billNum Номер счета
     * @return bool true в случае успешного удаления, иначе - false
     */
    function deleteBill( $billNum ) {
        return true;
    }

    /**
     * Получить список выставленных счетов за определнный промежуток времени и с определенным статусом. Если промежуток времени не указан, то возвращается список счетов за последние 30 дней
     *
     * @param int $eripID Идентификатор услуги в ЕРИП. Если не указан, то возвращаются данные по всем услугам данного ПУ.
     * @param int $fromDatetime Начало периода (UNIX-время)
     * @param int $toDatetime Конец периода (UNIX-время)
     * @param int $status Код статуса (1 - Ожидает оплату 2 - Просрочен 3 - Оплачен 4 - Оплачен частично 5 - Отменен). Если не указан, то будут возвращены все счета,
     * вне зависимости от их текущего статуса
     * @return array Список счетов 
     */
    function getBills( $eripID, $fromDatetime = '', $toDatetime = '', $status = '') {
        return array();
    }
    
    /**
     * Получить детальную информацию по платежу
     *
     * @param $billNum
     * @return object Информация об оплате
     */
    function getPayment( $billNum ) {
        return null;
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
