<?php

/**
 * Класс, реализующий функционал для логирования событий приложения и для выведения отладочной информации.
*/
class Logger {

    private $debug;
//     private $logFiles = array('error' => ''); //лог-файл ошибок существует по умолчанию
    private $logFiles = array();
    
    public function __construct($debug = false) {
        $this->debug = $debug;
    }

//     public function __destruct(){
//         foreach ( $this->openLogFiles as $openLog ) {
//             fclose( $openLog );
//         }
//     }

    /**
     * Добавить к логгеру файл логов
     * 
     * @param mixed $logname. Если $logname массив - то один файл $logFilePath будет иметь несколько имен, которые содержатся в массиве $logname
     * @param string $logFilePath
     */
    public function addLog($logname, $logFilePath) {
        if ( is_array($logname) ) {
            if ( ($errorLogIndex = array_search('error', $logname)) !== false ) {
                 ini_set('error_log', $logFilePath);
                 unset($logname[$errorLogIndex]);
            }

            $this->logFiles = array_merge( $this->logFiles, array_fill_keys ( $logname, $logFilePath ));
        } else {
            if ( $logname === 'error' ) {
                ini_set('error_log', $logFilePath);
            }

            $this->logFiles[$logname] = $logFilePath;
        }
    }

    public function debug($enable = true) {
        $this->debug = $enable;
    }

    /**
     * Записать $message в лог $logname
     *
     * @param string $logname
     * @param string $message
     *
     * @return true в случае успеха или false в случае ошибки.
     */
    public function write($logname, $message) {
//         if ( ! array_key_exists($logname, $this->logFiles) ) {
//             return false;
//         }

        //обработка специальных имен логов
        if ( $logname === 'error' ) {
            return error_log($message);
        } else if ( $logfile = $this->logFiles[$logname] ){
            if ( $logname === 'debug' && ! $this->debug ) {
                return false;
            }
         return file_put_contents($logfile, $this->format($message), FILE_APPEND) > 0;
        }

        return false;
    }

//     /**
//      * Возвращает дескриптор лог-файла. Если файл не открыт, то открывает файл и возвращает дескриптор
//      *
//      * @param string $logname 
//      * @return resource
//      */
//     private function getFD($logname){
//         $filepath = $this->logFiles[$logname];
//         
//         if ( ! array_key_exists($filepath, $this->openLogFiles) ) {
//             $this->openLogFiles[$filepath] = fopen($filepath, 'a+b');
//         }
// 
//         return $this->openLogFiles[$filepath];
//     }

    /**
     * Форматирует собщение для логирования в формат, в котором сообщения записываются в лог файл.
     *
     * @param string $message
     * @return string отформатированное сообщение
     */
    private static function format($message) {
        return '[' . date('D M d H:i:s Y', time()). '] ' . $message . PHP_EOL; 
    }
}