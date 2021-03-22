<?php

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

class Loader1C
{
    private $logFile = 'log.txt';
    private $ordersFile = 'orders.xml';
    private $infoFile = 'info.xml';
    private $login = '';
    private $password = '';

    public function __construct()
    {
        file_put_contents($this->logFile, '');
        file_put_contents($this->ordersFile, '');
        file_put_contents($this->infoFile, '');

        Bitrix\Main\Loader::includeModule('sale');

        Bitrix\Main\Localization\Loc::loadMessages(__FILE__);
        $this->data = Bitrix\Main\Context::getCurrent()->getRequest()->toArray();

        $protocol = CMain::IsHTTPS() ? "https://" : "http://";
        $this->url = "$protocol{$_SERVER['SERVER_NAME']}/{$this->data['kernelDir']}/admin/1c_exchange.php";

        $this->http = new \Bitrix\Main\Web\HttpClient();
        $this->http->setAuthorization($this->login, $this->password);
        $this->http->setCookies(['PHPSESSID' => uniqid(), 'XDEBUG_SESSION' => 'PHPSTORM']);
    }

    private function checkAuth()
    {
        $url = "$this->url?type={$this->data['type']}&mode=checkauth";
        $get = $this->convertEncoding($this->http->get($url));

        preg_match('/sessid=\K.*/', $get, $sessid);

        if ($this->sessid = $sessid[0]) {
            $this->http->setHeader('X-Bitrix-Csrf-Token', $this->sessid, true);
            $this->add2log($this->getMessage('WC_HTTP_CLIENT_AUTH_SUCCESS'));
        }
    }

    private function init()
    {
        $version = $this->version ? "version={$this->data['version']}" : '';
        $url = "$this->url?type={$this->data['type']}&mode=init&sessid=$this->sessid&$version";
        if ($init = $this->convertEncoding($this->http->get($url))) {
            $this->add2log($this->getMessage('WC_HTTP_CLIENT_INIT_SUCCESS'));
        }
    }

    private function import($file)
    {
        $url = "$this->url?type={$this->data['type']}&mode={$this->data['mode']}&sessid=$this->sessid&filename=$file";
        $get = $this->convertEncoding($this->http->get($url));
        preg_match('/progress/', $get, $match);
        $this->add2log($this->getMessage('WC_REPLACE', ['#REPLACE#' => $get]));
        if ($match) {
            $this->import($file);
        }
    }

    private function query()
    {
        $this->init();
        $orderId = $this->data['orderId'] ? "orderId=$this->orderId" : '';
        $url = "$this->url?type={$this->data['type']}&mode={$this->data['mode']}&sessid=$this->sessid&$orderId";
        $get = $this->http->get($url);
        file_put_contents($this->ordersFile, $get);
        $this->getMessage(5, $this->ordersFile);
    }

    private function info()
    {
        $url = "$this->url?type={$this->data['type']}&mode={$this->data['mode']}&sessid=$this->sessid";
        $get = $this->http->get($url);
        file_put_contents($this->infoFile, $get);
        $this->add2log($this->getMessage('WC_FILE_LINK', ['#FILE#' => $this->infoFile]));
    }

    public function handler()
    {
        $this->checkAuth();

        if ($this->mode === 'import' || $this->mode === 'exchangeOrder1C') {
            global $USER;
            if (!$USER->IsAdmin()) {
                $this->add2log($this->getMessage('WC_UNAUTHORIZED'));
                $this->add2log($this->getMessage('WC_DONE'));
                exit;
            }
        }

        switch ($this->data['type']) {
            case 'catalog':
                switch ($this->data['mode']) {
                    case 'import':
                        // $this->init();
                        if (!$file = $this->getImportFile('1c_catalog')) {
                            $this->add2log($this->getMessage('WC_FILE_NOT_FOUND'));
                            break;
                        }
                        $this->add2log($this->getMessage('WC_IMPORTING_FILE', ['#FILE#' => $file]));
                        $this->import($file);
                        break;
                }
                break;
            case 'sale':
                switch ($this->data['mode']) {
                    case 'import':
                        // $this->init();
                        if (!$file = $this->getImportFile('1c_exchange')) {
                            $this->add2log($this->getMessage('WC_FILE_NOT_FOUND'));
                            break;
                        }
                        $this->add2log($this->getMessage('WC_IMPORTING_FILE', ['#FILE#' => $file]));
                        $this->import($file);
                        break;
                    case 'query':
                        $this->query();
                        break;
                    case'info':
                        $this->info();
                        break;
                    case'exchangeOrder1C':
                        $this->add2log($this->getMessage('WC_SEARCHING_ORDER'));
                        if ($order = \Bitrix\Sale\Order::load($this->data['orderId'])) {
                            $this->add2log($this->getMessage('WC_ORDER_FOUND', ['#ORDER_ID#' => $this->orderId]));
                        } else {
                            $this->add2log($this->getMessage('WC_ORDER_NOT_FOUND', ['#ORDER_ID#' => $this->orderId]));
                            break;
                        }
                        $oldDateUpdate = $order->getField('DATE_UPDATE')->toString();
                        $order->setField('UPDATED_1C', 'Y');
                        $order->save();
                        $order->setField('UPDATED_1C', 'N');
                        $order->save();
                        $newDateUpdate = $order->getField('DATE_UPDATE')->toString();
                        if ($oldDateUpdate !== $newDateUpdate && $order->getField('UPDATED_1C') === 'N') {
                            $this->add2log($this->getMessage('WC_ORDER_MARKED', ['#ORDER_ID#' => $this->orderId, '#DATE#' => $newDateUpdate]));
                        } else {
                            $this->add2log($this->getMessage('WC_ORDER_DONT_UPDATED', ['#ORDER_ID#' => $this->orderId]));
                            break;
                        }
                        break;
                }
                break;
            case 'reference':
                // $this->init();
                if (!$file = $this->getImportFile('1c_highloadblock')) {
                    $this->add2log($this->getMessage('WC_FILE_NOT_FOUND'));
                    break;
                }
                $this->add2log($this->getMessage('WC_IMPORTING_FILE', ['#FILE#' => $file]));
                $this->import($file);
                break;
        }

        $this->add2log($this->getMessage('WC_DONE'));
    }

    private function getImportFile($dir)
    {
        $files = scandir($_SERVER['DOCUMENT_ROOT'] . '/upload/' . $dir . '/', 1);

        foreach ($files as $file) {
            $info = new SplFileInfo($file);
            if ($info->getExtension() === 'xml') {
                return $file;
            }
        }

        return null;
    }

    private function add2log($str)
    {
        $str = preg_replace("/[\\n]/", " ", $str);
        $this->log .= date('d.m.y H:i:s') . ": $str \n";
        file_put_contents($this->logFile, $this->log);
    }

    private function getMessage($code, $replace = null, $language = 'en')
    {
        return Bitrix\Main\Localization\Loc::getMessage($code, $replace, $language);
    }

    private function getError($num)
    {
        switch ($num) {
            case 0:
                $error = "error #$num - you must be an admin for this";
                break;
            case 1:
                $error = "error #$num - import file not found";
                break;
            case 2:
                $error = "error #$num - order #$this->orderId not found";
                break;
            case 3:
                $error = "error #$num - order #$this->orderId don't updated";
                break;
            default:
                $error = null;
        }
        $this->add2log($error);
    }

    private function convertEncoding($str): string
    {
        return mb_convert_encoding($str, 'UTF-8', 'windows-1251');
    }
}

$loader1C = new Loader1C();
$loader1C->handler();
