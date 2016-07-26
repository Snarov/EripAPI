<?php

/**
 * 
 * Класс, отвечающий за хранение и предоставление информации о параметрах выполнения сценария apictl. Состояние экземпляра класса определяется параметрами командной строки.
 * 
 * @property-read string $username
 */
class ScriptParams {
	/**
	 * @var array
	 */
	const OPTIONS = array(
        'a:' => 'adduser:',
        'u:' => 'user:',
        'p:' => 'password:',
	);
	/**
	 * @var help
	 */
	const HELP = <<<'EOT'
Использование: apictl.php <параметры>

Параметры:

--adduser; -u <имя пользователя>        Добавляет в базу нового пользователя с указанными именем.

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
    private $password;
    
	function __construct() {
        //здесь происходит разбор опций командной строки и установление состояния объекта
		$params = getopt(implode('', array_keys(self::OPTIONS)), self::OPTIONS);
        
        if ( ! empty($params['a'] ) ) {
          $this->username = $params['a'];
          $this->action = 'adduser';
        } else if ( ! empty($params['adduser'] ) ) {
          $this->username = $params['adduser'];
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
