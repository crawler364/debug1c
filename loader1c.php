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

        $protocol = CMain::IsHTTPS() ? "https://" : "http://";
        $this->context = \Bitrix\Main\Context::getCurrent();
        $this->request = $this->context->getRequest();
        $this->type = $this->request->get('type');
        $this->mode = $this->request->get('mode');
        $this->version = $this->request->get('version');
        $this->orderId = $this->request->get('orderId');
        $this->url = "$protocol{$_SERVER['SERVER_NAME']}/bitrix/admin/1c_exchange.php";

        $this->http = new \Bitrix\Main\Web\HttpClient();
        $this->http->setAuthorization($this->login, $this->password);
        $this->http->setCookies(['PHPSESSID' => uniqid('', false), 'XDEBUG_SESSION' => 'PHPSTORM']);
        $this->checkAuth();
    }


    //todo почему в отдельный метод?
    private function checkAuth()
    {
        $fullUrl = "$this->url?type=$this->type&mode=checkauth";
        $checkauth = $this->convertEncoding($this->http->get($fullUrl)); // todo обработка ошибки
        preg_match('/sessid=.*/', $checkauth, $sessid);
        $this->sessid = $sessid[0];
        if ($this->sessid) {
            $this->http->setHeader('X-Bitrix-Csrf-Token', $this->sessid, true);
            $this->add2log($this->getMessage('WC_HTTP_CLIENT_AUTH_SUCCESS'));
        }
    }

    private function init()
    {
        $version = $this->version ? "version=$this->version" : '';
        $fullUrl = "$this->url?type=$this->type&$this->sessid&mode=init&$version";
        if ($init = $this->convertEncoding($this->http->get($fullUrl))) {
            $this->add2log($this->getMessage('WC_HTTP_CLIENT_INIT_SUCCESS'));
        }
    }

    public function handler()
    {
        if ($this->mode === 'import' || $this->mode === 'exchangeOrder1C') {
            global $USER;
            if (!$USER->IsAdmin()) {
                $this->add2log($this->getMessage('WC_UNAUTHORIZED'));
                $this->add2log($this->getMessage('WC_DONE'));
                exit;
            }
        }
        switch ($this->type) {
            case 'catalog':
                // $this->init();
                if (!$file = $this->getImportFile('1c_catalog')) {
                    $this->add2log($this->getMessage('WC_FILE_NOT_FOUND'));
                    break;
                }
                $this->add2log($this->getMessage('WC_IMPORTING_FILE', ['#FILE#' => $file]));
                $fullUrl = "$this->url?type=$this->type&mode=$this->mode&filename=$file&$this->sessid";
                $this->stepImport($fullUrl);
                break;
            case 'sale':
                switch ($this->mode) {
                    case 'import':
                        // $this->init();
                        if (!$file = $this->getImportFile('1c_exchange')) {
                            $this->add2log($this->getMessage('WC_FILE_NOT_FOUND'));
                            break;
                        }
                        $this->add2log($this->getMessage('WC_IMPORTING_FILE', ['#FILE#' => $file]));
                        $fullUrl = "$this->url?type=$this->type&mode=$this->mode&filename=$file&$this->sessid";
                        $this->stepImport($fullUrl);
                        break;
                    case 'query':
                        $this->init();
                        $orderId = $this->orderId ? "orderId=$this->orderId" : '';
                        $fullUrl = "$this->url?type=$this->type&mode=$this->mode&$this->sessid&$orderId";
                        $query = $this->http->get($fullUrl);
                        file_put_contents($this->ordersFile, $query);
                        $this->add2log($this->getMessage('WC_FILE_LINK', ['#FILE#' => $this->ordersFile]));
                        break;
                    case'info':
                        $fullUrl = "$this->url?type=$this->type&mode=$this->mode&$this->sessid";
                        $info = $this->http->get($fullUrl);
                        file_put_contents($this->infoFile, $info);
                        $this->add2log($this->getMessage('WC_FILE_LINK', ['#FILE#' => $this->infoFile]));
                        break;
                    case'exchangeOrder1C':
                        $this->add2log($this->getMessage('WC_SEARCHING_ORDER'));
                        if ($order = \Bitrix\Sale\Order::load($this->orderId)) {
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
                $fullUrl = "$this->url?type=$this->type&mode=$this->mode&filename=$file&$this->sessid";
                $this->stepImport($fullUrl);
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
        return false;
    }

    private function stepImport($fullUrl)
    {
        $import = $this->convertEncoding($this->http->get($fullUrl));
        preg_match('/progress/', $import, $match);
        $this->add2log($this->getMessage('WC_REPLACE', ['#REPLACE#' => $import]));
        if ($match) {
            $this->stepImport($fullUrl);
        }
    }

    private function add2log($str)
    {
        $str = preg_replace("/[\\n]/", " ", $str);
        $this->log .= date('d.m.y H:i:s') . ": $str \n";
        file_put_contents($this->logFile, $this->log);
    }

    private function convertEncoding($str)
    {
        return mb_convert_encoding($str, 'UTF-8', 'windows-1251');
    }

    private function getMessage($code, $replace = null, $language = 'en')
    {
        return Bitrix\Main\Localization\Loc::getMessage($code, $replace, $language);
    }
}

$loader1C = new Loader1C();
$loader1C->handler();