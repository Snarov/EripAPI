<?php

namespace EripAPI;

/**
* Класс, который служит для управления (создания, чтения, удаления ) сообщениями ЕРИП на FTP сервере ЕРИП
*/
class ERIPMessageIO {

    const MSG_VERSION = '5';
    const ENTRY_TYPE = '2';
    const DELIMITER = '^';

    private $ftpRoot;
    private $ftpServAddr;
    private $ftpUser;
    private $ftpPassword;
    
    private $deletionBuffer = array(); //хранит удаленные файлы для того чтобы их можно было восстановить в дальнейшем с помощью соотв. методов

    public function __construct($ftpServAddr, $ftpUser, $ftpPassword) {
        $this->$ftpServAddr = $ftpServAddr;
        $this->$ftpUser;
        $this->ftpPassword;
        
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
    *
    * @return boolean true в случае успешной отправки сообщения, иначе - false.
    */
    public function addMessage($msgNum, $eripID, $personalAccNum, $amount, $currencyCode, $eripCredentials, $msgTimestamp, $info){
        $msgDatetime = date('YmdHis', $msgTimestamp);
        
        $header = self::MSG_VERSION . self::DELIMITER . $eripCredentials['subcriber_code'] . self::DELIMITER .
                    $msgNum . self::DELIMITER . $msgDatetime . self::DELIMITER . '1' .self::DELIMITER .
                    $eripCredentials['unp'] . self::DELIMITER . $eripCredentials-['bank_code'] . self::DELIMITER .
                    $eripCredentials['account_num'] . self::DELIMITER . $eripID . self::DELIMITER . $currencyCode .
                    self::DELIMITER;

        $body = self::ENTRY_TYPE . self::DELIMITER . $personalAccNum . self::DELIMITER .
                $info['fullname'] . self::DELIMITER . $info['address'] . self::DELIMITER . self::DELIMITER .
                $amount . self::DELIMITER . self::DELIMITER . $msgDatetime . self::DELIMITER . $info['additionalInfo'] . self::DELIMITER .
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
                                                  ),
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
                                                  ),
                                    '206' => array(
                                                    'entry_num',
                                                    'erip_id',
                                                    'account_num',
                                                    'customer_fullname',
                                                    'customer_address',
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
                                                    'customer_fullname',
                                                    'customer_address',
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
                                                    'device_type_code',
                                                  ),
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

        $message = array( 'header' => $header, 'body' => $body, 'type' => $msgType);
        return message;
    }

    /**
    * Удаляет файл входящего сообщения на сервере ЕРИП. Файл для удаления должен находится в директории in или in/bak. Отмена удаления может быть произведена с помощью вызыова метода
    * udnoDeleteMessage() с тем же значением аргумента $filename, что и при вызовае этой функции.
    *
    * @param string $filename 
    * @return boolean true в случае успеха, иначе - false
    */
    public function deleteInMessage($filename){
        if ( file_exists("{$this->ftpRoot}/in/$filename") ) {
             $fileURL = "{$this->ftpRoot}/in/$filename";
        } else if ( file_exists("{$this->ftpRoot}/in/bak/$filename") ) {
            $fileURL = "{$this->ftpRoot}/in/bak/$filename";
        }

        return $this->deleteFile($filename, $fileURL);
    }

    /**
    * Удаляет файл исходящего сообщения на сервере ЕРИП. Файл для удаления должен находится в директории out. Отмена удаления может быть произведена с помощью вызыова метода
    * udnoDeleteMessage() с тем же значением аргумента $filename, что и при вызовае этой функции.
    *
    * @param string $filename 
    * @return boolean true в случае успеха, иначе - false
    */
    public function deleteOutMessage($filename){
        return $this->deleteFile($filename, "$ftpRoot/out/$filename");
    }

    /**
     * Отменяет ранее выполненное удаление файла
     *
     * @param $filename
     * @return boolean true, если файл успешно восстановлен, иначе - false
     */
    public function undoDeleteMessage($filename) {
        global $logger;

        if ( ! array_key_exists($filename, $this->deletionBuffer) ) {
            return false;
        }

        if ( file_put_contents($this->deletionBuffer[$filename]['file_URL'], $this->deletionBuffer[$filename]['file_content'] ) ) {
            unset($this->deletionBuffer[$filename]);
            return true;
        } else {
            $logger->write('error', __METHOD__ . ': Ошибка восстановления удаленного файла: не удается произвести запись в файл');
            return false;
        }
    }

     /**
     * Опрашивает ftp-сервер и возвращает список всех новых файлов в ftp-папке пользователя, появившихся с момента последнего опроса, если таковые имеются
     *
     * @param integer $userId
     * @return array Список файлов или пустой массив, если новых файлов не появлялось
     */
    public function  getNewFilesList() {
        //TODO реализовать функцию, основываясь на ифнормации о ftp-сервере
    }

    /**
     * Удаляет указанный файл и сохраняет его в буфер для возможности дальнейшей отмены удаления
     *
     * @param $filename
     * @param $fileURL
     * @return boolean true в случае успеха, иначе - false
     */
    private function deleteFile ($filename, $fileURL) {
        global $logger;
        
        if ($fileContent = file_get_contents($fileURL) ) {
            $deleteSuccesful = unlink($fileURL);
            if ( $deleteSuccessful ) {
                $this->deletionBuffer[$filename] = ['file_URL' => $fileURL, 'file_content' => $fileContent];
            } else {
                $logger->write('error', __METHOD__ . 'Ошибка удаления файла с ftp-сервера');
            }

            return $deleteSuccesful;
        } else {
            $logger->write('error', __METHOD__ . 'Ошибка чтения файла с ftp-сервера');
            return false;
        }
    }
}