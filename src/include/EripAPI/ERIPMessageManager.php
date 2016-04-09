<?php

namespace EripAPI;

/**
* Класс, который служит для управления (создания, чтения, удаления ) сообщениями ЕРИП на FTP сервере ЕРИП
*/
class ERIPMessageManager {

    const MSG_VERSION = '5';
    const ENTRY_TYPE = '2';
    const DELIMITER = '^';

    private $ftpRoot;

    public function __construct($ftpServAddr, $ftpUser, $ftpPassword) {
        $this->ftpRoot = "ftp://$ftpUser:$ftpPassword@$ftpServAddr";
    }

    /**
    * Добавляет список требований к оплате на сервер ЕРИП (сообщение 202). На данный момент каждое требование к оплате помещается в отдельный файл.
    *
    * @param int $msgNum номер сообщения
    * @param int $eripID Идентификатор услуги в ЕРИП
    * @param string $personalAccNum Номер лицевого счета (уникальное значение, однозначно идентифицирующее потребителя услуг или товар)
    * @param float $amount Сумма задолженности потребителя услуг перед производителем услуг. Отрицательное значение означает задолженность производителя перед потребителем
    * @param int $currencyCode  Код валюты требований к оплате 
    * @param array ERIPCredentials $eripCredentials Данные производителя услуг в системе ЕРИП.
    * @param object $info Дополнительная инорфмация о платеже
    * @return boolean true в случае успешной отправки сообщения, иначе - false.
    */
    public function addMessage($msgNum, $eripID, $personalAccNum, $amount, $currencyCode, $eripCredentials, $info){
        $header = self::MSG_VERSION . self::DELIMITER . $eripCredentials['subcriber_code'] . self::DELIMITER .
                    $msgNum . self::DELIMITER . date('YmdHis') . self::DELIMITER . '1' .self::DELIMITER .
                    $eripCredentials['unp'] . self::DELIMITER . $eripCredentials-['bank_code'] . self::DELIMITER .
                    $eripCredentials['account_num'] . self::DELIMITER . $eripID . self::DELIMITER . $currencyCode .
                    self::DELIMITER;

        $body = self::ENTRY_TYPE . self::DELIMITER . $personalAccNum . self::DELIMITER .
                $info['fullname'] . self::DELIMITER . $info['address'] . self::DELIMITER . self::DELIMITER .
                $amount . self::DELIMITER . self::DELIMITER . self::DELIMITER . $info['additionalInfo'] . self::DELIMITER .
                $info['additionalData'] . self::DELIMITER . self::DELIMITER . self::DELIMITER . self::DELIMITER . 
                'PS' . self::DELIMITER;

        $msgContent = iconv('UTF-8', 'CP1251', $header . PHP_EOL . $body);

        return file_put_contents("$ftpRoot/$msgNum.202", $msgContent) > 0;
    }

    /**
    * Считывает сообщение с FTP сервера (из папки out) и возвращает содержащуюся в нем информацию.
    *
    * @param string $filename Расширение имени файла определяет формат сообщения
    * @return array Массив с данными в случае успеха, иначе - false
    */
    public function readMessage($filename) {

        $msgType = pathinfo("$ftpRoot/$filename")['extension'];
        if ( empty($msgType) || $msgType ) {
            return false;
        }

        $msgContent = file("$ftpRoot/out/$filename");
        if ( empty($msgContent) ) {
            return false;
        }

        $msgType2HeaderKeys = array(                         //имена столбцов заголовка сообщения для каждого типа сообщений
                                    '204' => array(
                                                    'msg_version',
                                                    'sender_code',
                                                    'response_num',
                                                    'response_datetime',
                                                    'msg202_num',
                                                    'msg202_datetime',
                                                    'result',
                                                    'err_msg',
                                                  )
                                    '206' => array(
                                                    'msg_version',
                                                    'subscriber_code',
                                                    'msg_num',
                                                    'msg_datetime',
                                                    'entries_count',
                                                    'agent_code',
                                                    'unp',
                                                    'currency_code',
                                                    'total_amount',
                                                    'fine_amount',
                                                    ),
                                    '210' => array(
                                                    'msg_version',
                                                    'subscriber_code',
                                                    'msg_num',
                                                    'msg_datetime',
                                                    'entries_count',
                                                    'agent_code',
                                                    'unp',
                                                    'bank_code',
                                                    'account_num',
                                                    'paydoc_num',
                                                    'transfer_datetime',
                                                    'currency_code',
                                                    'total_amount',
                                                    'fine_amount',
                                                    'transfer_amount',
                                                    'agent_bank_code',
                                                    'agent_account_num',
                                                    'budget_payment_code',
                                                  ),
                                    );
        $msgType2HeaderKeys['216'] = $msgType2HeaderKeys['206']; //для сообщения 216 заголовок такой же, как и для 206
        
        $msgType2BodyKeys = array(                                  //имена столбцов тела сообщения для каждого типа сообщений
                                    '204' => array(
                                                    'entry_num',
                                                    'err_text',
                                                  )
                                    '206' => array(
                                                    'entry_num',
                                                    'erip_id',
                                                    'account_num',
                                                    'fullname',
                                                    'address'
                                                    'period',
                                                    'amount',
                                                    'fine_amount',
                                                    'payment_datetime',
                                                    'null',
                                                    'bill_datetime',
                                                    'central_node_op_num',
                                                    'agent_op_num',
                                                    'device_id',
                                                    'authorization_way',
                                                    'additional_info',
                                                    'agent_code',
                                                    'additional_data',
                                                    'authorization_way_id',
                                                    'device_type_code',
                                                ),
                                    '210' => array(
                                                    'entry_num',
                                                    'erip_id',
                                                    'account_num',
                                                    'fullname',
                                                    'address'
                                                    'period',
                                                    'amount',
                                                    'fine_amount',
                                                    'transfer_amount',
                                                    'operation_datetime',
                                                    'meters',        //оплата показаний счетчиков пока что не поодерижвается этим API
                                                    'bill_datetime',
                                                    'central_node_op_num',
                                                    'agent_op_num',
                                                    'device_id',
                                                    'authorization_way',
                                                    'additional_info',
                                                    'additional_data',
                                                    'authorization_way_id',
                                                    'devi   ce_type_code',
                                    );
        $msgType2BodyKey['216'] = array_splice($msgType2BodyKeys['206'], 9, 0, 'reversal_datetime'); //для сообщения 216 тело отличается наличием одного дополнительного поля

        if ( ! $headerKeys = $msgType2HeaderKeys[$msgType] ) {
            return false;
        }
        $headerValues = explode('^', $msgContent[0]);
        $header = array_combine($headerKeys, $headerValues);

        if ( ! $bodyKeys = $msgType2BodyKeys[$msgType] ) {
            return false;
        }
        $body = array();
        for ( $i = 1; $i < count($msgContent); $i++ ) {
            $bodyValues = explode('^', $msgContent[$i]);
            $body[$i - 1] = array_combine($bodyKeys, $bodyValues);
        } 

        $message = array( 'header' => $header, 'body' => $body);
        return message;
    }

    /**
    * Удаляет файл сообщения на сервере ЕРИП. Файл для удаления должен находится в директории in
    *
    * @param string $filename 
    * @return boolean true в случае успеха, иначе - false
    */
    public function deleteMessage($filename){
        return unlink("$ftpRoot/in/$filename");
    }
}