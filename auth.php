<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

class Auth
{
    public function __construct()
    {
        $this->context = \Bitrix\Main\Context::getCurrent();
        $this->request = $this->context->getRequest();
        $this->action = $this->request->get('action');
    }

    public function handler()
    {
        global $USER;
        switch ($this->action) {
            case 'login':
                $login = $this->request->get('login');
                $password = $this->request->get('password');
                $result = $USER->Login($login, $password, "Y");
                define('NO_KEEP_STATISTIC', true);
                header('Content-Type: application/json');
                echo \Bitrix\Main\Web\Json::encode($result);
                break;
            case 'logout':
                $USER->Logout();
                break;
        }
    }
}

$auth = new Auth();
$auth->handler();