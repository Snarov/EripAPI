<?php

require __DIR__ . '/IEripAPI.php';
require __DIR__ . '/Security.php';
require __DIR__ . '/ParamsChecker.php';

use EripAPI\ParamsChecker as ParamsChecker;

class EripAPI implements IEripAPI {

     /**
     * Выставить новый счет в ЕРИП.
     *
     * @param int $eripID Идентификатор услуги в ЕРИП
     * @param string $personalAccNum Номер лицевого счета (уникальное значение, однозначно идентифицирующее потребителя услуг или товар)
     * @param float $amount Сумма задолженности потребителя услуг перед производителем услуг. Отрицательное значение означает задолженность производителя перед потребителем
     * @param int $currencyCode  Код валюты требований к оплате 
     * @param object $info Дополнительная инорфмация о платеже
     * @param string $callbackURL Адрес, по которому произойдет обращение при изменении статуса заказа
     * @return int Номер счета
     */
    function createBill( $eripID, $personalAccNum, $amount, $currencyCode, $info = null, $callbackURL = null) {
       ParamsChecker::createBillParamsCheck();
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
     * @param $paymentNum
     * @return object Информация об оплате
     */
    function getPayment( $paymentNum ) {
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
