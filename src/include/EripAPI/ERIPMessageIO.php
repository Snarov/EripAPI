<?php

namespace EripAPI;

/**
* Класс, который служит для управления (создания, чтения, удаления ) сообщениями ЕРИП на FTP сервере ЕРИП
*/
class ERIPMessageIO {

    const MSG_VERSION = '5';
    const ENTRY_TYPE = '2';
    const DELIMITER = '^';
    const OUTPUT_MSG_NAME_PATTERN = '/^.*\.2(04|06|10|16)$/';

    private $ftpRoot;
    private $ftpConnection;
    private $ftpServAddr;
    private $ftpUser;
    private $ftpPassword;
    
    private $deletionBuffer = array(); //хранит удаленные файлы для того чтобы их можно было восстановить в дальнейшем с помощью соотв. методов

    public function __construct($ftpServAddr, $ftpUser, $ftpPassword) {
        $this->ftpServAddr = $ftpServAddr;
        $this->ftpUser = $ftpUser;
        $this->ftpPassword = $ftpPassword;
        
        $this->ftpRoot = "ftp://$ftpUser:$ftpPassword@$ftpServAddr";
        
        $this->ftp_connection = ftp_connect($ftpServAddr);
        if ( ! @ftp_login($this->ftp_connection, $ftpUser, $ftpPassword) ) {
            $this->ftp_connection = false;
        }
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
    public function addMessage($msgNum, $eripID, $personalAccNum, $amount, $currencyCode, $period, $eripCredentials, $msgTimestamp, $info){
        global $logger;
        
        $msgDatetime = date('YmdHis', $msgTimestamp);
        
        $header = self::MSG_VERSION . self::DELIMITER . $eripCredentials['subscriber_code'] . self::DELIMITER .
                    $msgNum . self::DELIMITER . $msgDatetime . self::DELIMITER . '1' .self::DELIMITER .
                    $eripCredentials['unp'] . self::DELIMITER . $eripCredentials['bank_code'] . self::DELIMITER . 
                    $eripCredentials['bank_account'] . self::DELIMITER . $eripID . self::DELIMITER . $currencyCode .
                    self::DELIMITER . 'PS';

        $body = self::ENTRY_TYPE . self::DELIMITER . $personalAccNum . self::DELIMITER .
                $info['customerFullname'] . self::DELIMITER . $info['customerAddress'] . self::DELIMITER . $period . self::DELIMITER .
                $amount . self::DELIMITER . self::DELIMITER . $msgDatetime . self::DELIMITER . $info['additionalInfo'] . self::DELIMITER .
                $info['additionalData'] . self::DELIMITER . self::DELIMITER . self::DELIMITER . self::DELIMITER . self::DELIMITER;

        $msgContent = iconv('UTF-8', 'CP1251', $header . PHP_EOL . $body);

        return file_put_contents("{$this->ftpRoot}/in/$msgNum.202", $msgContent) > 0;
    }

    /**
    * Считывает сообщение с FTP сервера и возвращает содержащуюся в нем информацию.
    *
    * @param string $filename Расширение имени файла определяет формат сообщения
    * @return array Массив с данными в случае успеха, иначе - false
    */
    public function readMessage( $filename ) {
        global $logger;
            
        $logger->write( "Попытка чтения сообщения $filename ...", 'debug', __FILE__, __LINE__ );

        $msgType = pathinfo("{$this->ftpRoot}/out/$filename")['extension'];
        if ( empty($msgType) ) {
            $logger->write("Не удалось получить информацию о расширении имени файла $filename", 'error', __FILE__, __LINE__ );
            return false;
        }

        $msgContent = file("{$this->ftpRoot}/out/$filename", FILE_IGNORE_NEW_LINES);
        if ( empty($msgContent) ) {
            $logger->write("Не удалось получить содержимое файла $filename", 'error', __FILE__, __LINE__ );
            return false;
        }
        $msgContent = $this->msgToUTF8($msgContent);

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
                                                    'err_count',
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
                                                    'personal_acc_num',
                                                    'paydoc_num',
                                                    'transfer_datetime',
                                                    'currency_code',
                                                    'total_amount',
                                                    'fine_amount',
                                                    'transfer_amount',
                                                    'agent_bank_code',
                                                    'agent_acc_num',
                                                    'budget_payment_code',
                                                  ),
                                    );
        $msgType2HeaderKeys['216'] = $msgType2HeaderKeys['206']; //для сообщения 216 заголовок такой же, как и для 206
        
        $msgType2BodyKeys = array(                                  //имена столбцов тела сообщения для каждого типа сообщений
                                    '204' => array(
                                                    'entry_num',
                                                    'err_text',
                                                    'src_entry',
                                                  ),
                                    '206' => array(
                                                    'entry_num',
                                                    'erip_id',
                                                    'personal_acc_num',
                                                    'customer_fullname',
                                                    'customer_address',
                                                    'period',
                                                    'amount',
                                                    'fine_amount',
                                                    'payment_datetime',
                                                    'null',
                                                    'bill_datetime',
                                                    'erip_op_num',
                                                    'agent_op_num',
                                                    'device_id',
                                                    'authorization_way',
                                                    'additional_info',
                                                    'agent_bank_code',
                                                    'additional_data',
                                                    'authorization_way_id',
                                                    'device_type_code',
                                                ),
                                    '210' => array(
                                                    'entry_num',
                                                    'erip_id',
                                                    'personal_acc_num',
                                                    'customer_fullname',
                                                    'customer_address',
                                                    'period',
                                                    'amount',
                                                    'fine_amount',
                                                    'transfer_amount',
                                                    'payment_datetime',
                                                    'meters',        //оплата показаний счетчиков пока что не поодерижвается этим API
                                                    'bill_datetime',
                                                    'erip_op_num',
                                                    'agent_op_num',
                                                    'device_id',
                                                    'authorization_way',
                                                    'additional_info',
                                                    'additional_data',
                                                    'authorization_way_id',
                                                    'device_type_code',
                                                  ),
                                    );
        $msgType2BodyKeys['216'] = $msgType2BodyKeys['206'];
        array_splice($msgType2BodyKeys['216'], 9, 0, 'reversal_datetime'); //для сообщения 216 тело отличается наличием одного дополнительного поля

        if ( ! $headerKeys = $msgType2HeaderKeys[$msgType] ) {
            return false;
        }
        $headerValues = explode('^', $msgContent[0]);
        //ЕРИП шлет сообщения,формат которых не соответствет документации, поэтому приходится выкручиваться
        if ( count( $headerKeys ) !== count( $headerValues ) ) {
            $headerValues[] = '1';
        }
        $header = array_combine($headerKeys, $headerValues);

        if ( ! $bodyKeys = $msgType2BodyKeys[$msgType] ) {
            return false;
        }
        $body = array();
        
        if ( $msgType != 204 ) {
            for ( $i = 1; $i <= $header['entries_count']; $i++ ) {
                $bodyValues = explode('^', $msgContent[$i]);
                if ( count( $bodyValues ) === count( $bodyKeys ) ) {
                    $body[$i - 1] = array_combine($bodyKeys, $bodyValues);
                }
            }
        }

        $message = array( 'header' => $header, 'body' => $body, 'type' => $msgType);
        $logger->write( "Прочитано сообщение $filename: " . print_r( $message, true ), 'debug', __FILE__, __LINE__);
        
        if ( ! $this->markAsViewed($filename) ) {
            $logger->write("Не удалось пометить сообщение $filename как прочитанное", 'error');
        }
        
        return $message;
    }


    /**
    * Удаляет файл сообщения на сервере ЕРИП. Отмена удаления может быть произведена с помощью вызыова метода
    * udnoDeleteMessage() с тем же значением аргумента $filename, что и при вызовае этой функции.
    *
    * @param string $filename 
    * @return boolean true в случае успеха, иначе - false
    */
    public function deleteMessage($filename){
        return $this->deleteFile($filename, "$this->ftpRoot/$filename");
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
            $logger->write( ': Ошибка восстановления удаленного файла: не удается произвести запись в файл', 'error', __FILE__, __LINE__);
            return false;
        }
    }

     /**
     * Опрашивает ftp-сервер и возвращает список всех новых файлов в ftp-папке пользователя, появившихся с момента последнего опроса, если таковые имеются
     * Новыми считаются файлы, которые находятся в папке /out. Прочитанные и обработанные файлы должны быть помечены, т.е. перенесены в папку /out/bak. 
     *
     * @param integer $userId
     * @return array Список файлов, отсортированных по типу (202=>204=>206=> или пустой массив, если новых файлов не появлялось. False в случае неудачи
     */
    public function  getNewFilesList() {
        $fileList = ftp_nlist($this->ftp_connection, '/out');
        if ( ! is_array( $fileList ) ) {
            return false;
        }

        $fileList = array_filter( $fileList, function( $fileName ) {
            return preg_match( self::OUTPUT_MSG_NAME_PATTERN, $fileName ) === 1;
        } );

        usort( $fileList, function ( $a, $b ) { // сортируем по возрастанию типов
            $msgTypeA = explode( '.', $a )[1];
            $msgTypeB = explode( '.', $b )[1];

            return strcmp( $msgTypeA, $msgTypeB );
        } );

        return $fileList;
    }

    /**
     * Удаляет указанный файл и сохраняет его в буфер для возможности дальнейшей отмены удаления
     *
     * @param string $filename
     * @param string $fileURL

     * @return boolean true в случае успеха, иначе - false
     */
    private function deleteFile( $filename, $fileURL ) {
        global $logger;
        
        if ($fileContent = file_get_contents($fileURL) ) {
            $deletionSuccessful = unlink($fileURL);
            if ( ! empty( $deletionSuccessful ) ) {
                $this->deletionBuffer[$filename] = ['file_URL' => $fileURL, 'file_content' => $fileContent];
            } else {
                $logger->write( 'Ошибка удаления файла с ftp-сервера', 'error');
            }
            return $deletionSuccessful;
        } else {
            $logger->write( 'Ошибка чтения файла с ftp-сервера', 'error');
            return false;
        }
    }
    
    /** 
     * Отмечает сообщение с указанным именем как прочитанное. Прочитанные файлы перемещаются из /out в /out/bak. При успехе возвращает true, иначе - false. Так как по непоятным причинам ftp сервер запрещает перемещать файлы, то приходится их считывать, удалять и затем записывать в другое место.
     *
     * @param string $filename 
     */
    private function markAsViewed( $filename ) {
        $newFilename = 'out/bak/' . $filename; //предполагатеся что помечаются файлы только из папки /out
        $filename = 'out/' . $filename;
        
        $successfull = $this->deleteFile( $filename, "$this->ftpRoot/$filename") &&
        file_put_contents( "$this->ftpRoot/$newFilename", $this->deletionBuffer[$filename]['file_content']);

        return $successfull;
    }

    /**
     * Изменяет кодировку массива, содержащего строки входящего сообщения с cp1251 на UTF8. Вынесено в отдельную функцию во имя безопасности
     *
     * @param array $msg
     *
     * @return array Массив перекодированных строк
     */
    private function msgToUTF8( $msg ) {
        foreach( $msg as &$string ) {
            $string = iconv( 'cp1251', 'UTF-8', $string);
        }

        return $msg;
    }
}