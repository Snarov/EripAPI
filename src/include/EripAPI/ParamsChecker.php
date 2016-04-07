<?php

namespace EripAPI;

class InvalidParamValueException extends \Exception {};

/**
* Класс предоставляющий методы для проверки параметров методов API. В случае нахождения некорретного значения параметров
* методы класса бросают исключения, содержащее описание ошибки.
*/
abstract class ParamsChecker {

    const ERIP_ID_REGEX = '/^\d{8}$/';
    const PERSONAL_ACC_NUM_REGEX = '/^.{1,30}$/';
    const CURRENCY_CODE_REGEX = '/^\d{1,3}$/';
    const STATUS_REGEX = '/^[12345]$/';

/**
     * Проверяет корректность параметров, преданных методу createBill()
     *
     * @param int $eripID Идентификатор услуги в ЕРИП
     * @param string $personalAccNum Номер лицевого счета (уникальное значение, однозначно идентифицирующее потребителя услуг или товар)
     * @param float $amount Сумма задолженности потребителя услуг перед производителем услуг. Отрицательное значение означает задолженность производителя перед потребителем
     * @param int $currencyCode  Код валюты требований к оплате 
     * @param object $info Дополнительная инорфмация о платеже
     * @param string $callbackURL Адрес, по которому произойдет обращение при изменении статуса заказа
     * @return int Номер счета
     */
    static function createBillParamsCheck($eripID, $personalAccNum, $amount, $currencyCode, $info = null, $callbackURL = null) {
        $errMsg = '';

        if ( preg_match( self::ERIP_ID_REGEX, $eripID ) !== 1 ) {
            $errMsg .= "'eripID' must be an eight-digit number" . PHP_EOL;
        }
        if ( preg_match(self::PERSONAL_ACC_NUM, $personalAccNum) !== 1 ) {
            $errMsg .= "'personalAccNum' must be not empty and its maximum length is 30 characters" . PHP_EOL;
        }
        if ( ! is_numeric($amount) || $amount <= 0 ) {
            $errMsg .= "'amount' must be a positive number" . PHP_EOL;
        }
        if ( preg_match(self::CURRENCY_CODE_REGEX, $currencyCode) !== 1 ) {
            $errMsg .= "'currencyCode' must contain from 1 to 3 digits" . PHP_EOL;
        }
        //TODO добавить проверку необязательных параметров

        if ( ! empty($errMsg) ) {
            throw new InvalidParamValueException( 'Invalid parameter value:' . PHP_EOL . $errMsg, -32002);
        }
    }

    /**
    * Проверяет корректность billNum
    *
    * @param int $billNum
    */
    static function billNumCheck($billnum) {
        if ( ! is_numeric($billNum) || $billNum <= 0 ) {
            throw new InvalidParamValueException( 'Invalid parameter value:' . PHP_EOL . "'billNum' must be a positive number", -32002);
        }
    }

    /**
    * Проверяет корректность billNum
    *
    * @param int $paymentNum
    */
    static function paymentNumCheck($paymentNum) {
        if ( ! is_numeric($paymentNum) || $paymentNum <= 0 ) {
            throw new InvalidParamValueException( 'Invalid parameter value:' . PHP_EOL . "'paymentNum' must be a positive number", -32002);
        }
    }

    /**
    * Проверяет корректность параметров методов getBills() и getpayments()
    *
    * @param int $eripID Идентификатор услуги в ЕРИП.
    * @param int $fromDatetime Начало периода (UNIX-время)
    * @param int $toDatetime Конец периода (UNIX-время)
    * @param int $status Код статуса (1 - Ожидает оплату 2 - Просрочен 3 - Оплачен 4 - Оплачен частично 5 - Отменен)
    */
    static function getBillsOrPaymentsParamsCheck($eripID, $fromDatetime, $toDatetime, $status = null) {
        if ( preg_match( self::ERIP_ID_REGEX, $eripID ) !== 1 ) {
            $errMsg .= "'eripID' must be an eight-digit number" . PHP_EOL;
        }
        if ( ! is_numeric($fromDatetime) || ! is_numeric($toDatetime) || $fromDatetime < 0 || $toDatetime < 0 ) {
            $errMsg .= "'fromDatetime' and 'toDatetime' must be non-negative numbers" . PHP_EOL;
        } else if ( $toDatetime <= $fromDatetime ) {
            $errMsg .= "'toDatetime' must be greater than 'fromDatetime'" . PHP_EOL;
        }
        if (  null !== $status && ! preg_match(self::STATUS_REGEX, $status ) ){
            $errMsg .= "'status' value must be an integer from 1 to 5" . PHP_EOL;
        }

        if ( ! empty($errMsg) ) {
            throw new InvalidParamValueException( 'Invalid parameter value:' . PHP_EOL . $errMsg, -32002);
        }
    }
}
