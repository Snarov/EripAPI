<?php

namespace EripAPI;

class InvalidParamValueException extends \Exception {};

/**
* Класс предоставляющий методы для проверки параметров методов API. В случае нахождения некорретного значения параметров
* методы класса бросают исключения, содержащее описание ошибки.
*/
abstract class ParamsChecker {

    const ERIP_ID_REGEX = '/^\d{1,8}$/';
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
        if ( preg_match(self::PERSONAL_ACC_NUM_REGEX, $personalAccNum) !== 1 ) {
            $errMsg .= "'personalAccNum' must be not empty and its maximum length is 30 characters" . PHP_EOL;
        }
        if ( ! is_numeric($amount) || $amount < 0 ) {
            $errMsg .= "'amount' must be a non-negative number" . PHP_EOL;
        }
        if ( preg_match(self::CURRENCY_CODE_REGEX, $currencyCode) !== 1 ) {
            $errMsg .= "'currencyCode' must contain from 1 to 3 digits" . PHP_EOL;
        }
        if ( $callbackURL !== null && filter_var($callbackURL, FILTER_VALIDATE_URL) === false ) {
            $errMsg .="'callbackURL' must be valid URL" . PHP_EOL;
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
    static function billNumCheck($billNum) {
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
    * Проверяет корректность параметров методов getBills() и getPayments()
    *
    * @param int $eripID Идентификатор услуги в ЕРИП.
    * @param int $fromTimestamp Начало периода (UNIX-время)
    * @param int $toTimestamp Конец периода (UNIX-время)
    * @param int $status Код статуса (1 - Ожидает оплату 2 - Просрочен 3 - Оплачен 4 - Оплачен частично 5 - Отменен)
    */
    static function getBillsOrPaymentsParamsCheck($eripID, $fromTimestamp, $toTimestamp, $status) {
        if (  null !== $eripID && preg_match( self::ERIP_ID_REGEX, $eripID ) !== 1 ) {
            $errMsg .= "'eripID' must be a number with lenght from 1 to 8" . PHP_EOL;
        }

        if ( '' !== $fromTimestamp ) {
            $fromTimestamp = strtotime($fromTimestamp);
        }
        if ( '' !== $toTimestamp ) {
            $toTimestamp = strtotime($toTimestamp);
        }
        
        if (  '' !== $fromTimestamp && ( ! is_numeric($fromTimestamp) || $fromTimestamp < 0 ) ) {
            $errMsg .= "'fromTimestamp' value is invalid. It must be a date in format YYYYMMDDHHMMSS" . PHP_EOL;
        }
        if (  '' !== $toTimestamp && ( ! is_numeric($toTimestamp) || $tofromTimestamp < 0 ) ) {
            $errMsg .= "'toTimestamp' value is invalid. It must be a date in format YYYYMMDDHHMMS" . PHP_EOL;
        }
        if ( ! empty($fromTimestamp) && ! empty($toTimestamp) &&  $toTimestamp <= $fromTimestamp ) {
            $errMsg .= "'toTimestamp' must be greater than 'fromTimestamp'" . PHP_EOL;
        }
        
        if (  null !== $status && ! preg_match(self::STATUS_REGEX, $status ) ){
            $errMsg .= "'status' value must be an integer from 1 to 5" . PHP_EOL;
        }

        if ( ! empty($errMsg) ) {
            throw new InvalidParamValueException( 'Invalid parameter value:' . PHP_EOL . $errMsg, -32002);
        }
    }
}
