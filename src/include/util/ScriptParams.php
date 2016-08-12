<?php

/**
 * 
 * Класс, отвечающий за хранение и предоставление информации о параметрах выполнения сценария apictl. Состояние экземпляра класса определяется параметрами командной строки.
 * 
 * @property-read string $username
 */
class ScriptParams {
    //шаблоны параметров
    const FTP_ADDR_PATTERN = '/^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$/';
    const FTP_USER_PATTERN = '/^(?=.{1,20}$)(?![_.])(?!.*[_.]{2})[a-zA-Z0-9._]+(?<![_.])$/';
    const FTP_PASSWORD_PATTERN = '/^(?=.{1,20}$)(?![_.])(?!.*[_.]{2})[a-zA-Z0-9._]+(?<![_.])$/';
    const SUBSCRIBER_CODE_PATTERN = '/^\d{8}$/';
    const UNP_PATTERN = '/^\d{9}$/';
    const BANK_CODE_PATTERN = '/^\d{3}$/';
    const BANK_ACCOUNT_PATTERN = '/^\d{13}$/';

    const REQ_COUNT = 7;
    
	/**
	 * @var array
	 */
	const OPTIONS = array(
        'a:' => 'adduser:',
        'r:' => 'requisites:',
        'u:' => 'user:',
        'p:' => 'password:',
	);
	/**
	 * @var help
	 */
	const HELP = <<<'EOT'
Использование: apictl.php <параметры>

Параметры:

--adduser; -a <имя пользователя> --requisites; -r <реквизиты>       Добавляет в базу нового пользователя с указанными именем.
<реквизиты> имеют следующий формат и разделяются ":" (двоеточие) :
<адрес ftp-сервера>;<имя ftp-пользователя>;<пароль ftp-пользователя>;<код абонента ЕРИП>;<УНП ПУ>;<код банка ПУ>;<расчетный счет ПУ>

--user; -u <имя пользователя>           Задает/изменяет пароль пользователя
--password; -p <пароль> 

--help; -h                              Показать это сообщение

EOT;

    /*
     * @var string Действия, которое требуется выполнить
     */
   private $action;
    
    /**
     * @var string
     */
    private $username;
    private $requisites;
    private $password;
    
	function __construct() {
        //здесь происходит разбор опций командной строки и установление состояния объекта
		$params = getopt(implode('', array_keys(self::OPTIONS)), self::OPTIONS);
        
        if ( (! empty($params['a']) || ! empty($params['adduser'] ) ) && ( ! empty($params['r']) || ! empty($params['requisites'] ) ) ){
            
            $this->username = ! empty ($params['a']) ? $params['a'] : $params['adduser'];
            $reqStr =  ! empty ($params['r']) ? $params['r'] : $params['requisites'];
            $requisites = explode(':', $reqStr);

            if ( self::REQ_COUNT != count( $requisites ) ) {
                $this->handleBadParams();
            }

            $this->requisites = array_combine( array( 'ftp_addr',
                                                      'ftp_user',
                                                      'ftp_password',
                                                      'subscriber_code',
                                                      'unp',
                                                      'bank_code',
                                                      'bank_account',
            ), $requisites);
            
            //проверка корректности введеных реквизитов
            if (
                preg_match( self::FTP_ADDR_PATTERN, $this->requisites['ftp_addr'] ) !== 1 ||
                preg_match( self::FTP_USER_PATTERN, $this->requisites['ftp_user'] ) !== 1 ||
                preg_match( self::FTP_PASSWORD_PATTERN, $this->requisites['ftp_password'] ) !== 1 ||
                preg_match( self::SUBSCRIBER_CODE_PATTERN, $this->requisites['subscriber_code'] ) !== 1 ||
                preg_match( self::UNP_PATTERN, $this->requisites['unp'] ) !== 1 ||
                preg_match( self::BANK_CODE_PATTERN, $this->requisites['bank_code'] ) !== 1 ||
                preg_match( self::BANK_ACCOUNT_PATTERN, $this->requisites['bank_account'] ) !== 1 ) {
                $this->handleBadParams();
            }
                
            $this->action = 'adduser';
        } else if ( ! empty($params['u']) || ! empty($params['user']) ) {
            $this->username = ! empty($params['u']) ? $params['u'] : $params['user'];
            $this->password = ! empty($params['p']) ? $params['p'] : $params['password'];
            if ( empty( $this->password ) ) {
                $this->handleBadParams();
            } else {
                $this->action = 'chpass';
            }
       } else {
          $this->handleBadParams();
       }
               
	}
	//Все поля только для чтения
	function __get($name) {
		return $this->$name;
	}
	/**
	 * Функция вызывается в случае поступления некорректной строки параметров. Она выводит help и завершает работу скрипта
	 */
	private function handleBadParams() {
		exit(self::HELP . "\n");
	}
}
