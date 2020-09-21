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

        $protocol = CMain::IsHTTPS() ? "https://" : "http://";
        $this->context = \Bitrix\Main\Context::getCurrent();
        $this->request = $this->context->getRequest();
        $this->type = $this->request->get('type');
        $this->mode = $this->request->get('mode');
        $this->version = $this->request->get('version');
        $this->orderId = $this->request->get('orderId');
        $this->destination = $this->request->get('destination');
        $this->url = "$protocol{$_SERVER['SERVER_NAME']}/$this->destination/admin/1c_exchange.php";

        $this->http = new \Bitrix\Main\Web\HttpClient();
        $this->http->setAuthorization($this->login, $this->password);
        $this->http->setCookies(['PHPSESSID' => uniqid('', false), 'XDEBUG_SESSION' => 'PHPSTORM']);
        $this->checkAuth();
    }

    private function checkAuth()
    {
        $fullUrl = "$this->url?type=$this->type&mode=checkauth";
        $checkauth = $this->convertEncoding($this->http->get($fullUrl));
        preg_match('/sessid=.*/', $checkauth, $sessid);
        $this->sessid = $sessid[0];
        if ($this->sessid) {
            $this->http->setHeader('X-Bitrix-Csrf-Token', $this->sessid, true);
            $this->getMessage(2);
        }
    }

    private function init()
    {
        $version = $this->version ? "version=$this->version" : '';
        $fullUrl = "$this->url?type=$this->type&$this->sessid&mode=init&$version";
        if ($init = $this->convertEncoding($this->http->get($fullUrl))) {
            $this->getMessage(3);
        }
    }

    public function handler()
    {

        if ($this->mode === 'import' || $this->mode === 'exchangeOrder1C') {
            global $USER;
            if (!$USER->IsAdmin()) {
                $this->getError(0);
                $this->getMessage(1);
                exit;
            }
        }
        switch ($this->type) {
            case 'catalog':
                // $this->init();
                if (!$file = $this->getImportFile('1c_catalog')) {
                    $this->getError(1);
                    break;
                }
                $this->getMessage(4, $file);
                $fullUrl = "$this->url?type=$this->type&mode=$this->mode&filename=$file&$this->sessid";
                $this->stepImport($fullUrl);
                break;
            case 'sale':
                switch ($this->mode) {
                    case 'import':
                        // $this->init();
                        if (!$file = $this->getImportFile('1c_exchange')) {
                            $this->getError(1);
                            break;
                        }
                        $this->getMessage(4, $file);
                        $fullUrl = "$this->url?type=$this->type&mode=$this->mode&filename=$file&$this->sessid";
                        $this->stepImport($fullUrl);
                        break;
                    case 'query':
                        $this->init();
                        $orderId = $this->orderId ? "orderId=$this->orderId" : '';
                        $fullUrl = "$this->url?type=$this->type&mode=$this->mode&$this->sessid&$orderId";
                        $query = $this->http->get($fullUrl);
                        file_put_contents($this->ordersFile, $query);
                        $this->getMessage(5, $this->ordersFile);
                        break;
                    case'info':
                        $fullUrl = "$this->url?type=$this->type&mode=$this->mode&$this->sessid";
                        $info = $this->http->get($fullUrl);
                        file_put_contents($this->infoFile, $info);
                        $this->getMessage(5, $this->infoFile);
                        break;
                    case'exchangeOrder1C':
                        $this->getMessage(6);
                        if ($order = \Bitrix\Sale\Order::load($this->orderId)) {
                            $this->getMessage(7);
                        } else {
                            $this->getError(2);
                            break;
                        }
                        $oldDateUpdate = $order->getField('DATE_UPDATE')->toString();
                        $order->setField('UPDATED_1C', 'Y');
                        $order->save();
                        $order->setField('UPDATED_1C', 'N');
                        $order->save();
                        $newDateUpdate = $order->getField('DATE_UPDATE')->toString();
                        if ($oldDateUpdate !== $newDateUpdate && $order->getField('UPDATED_1C') === 'N') {
                            $this->getMessage(8, $newDateUpdate);
                        } else {
                            $this->getError(3);
                            break;
                        }
                        break;
                }
                break;
            case 'reference':
                // $this->init();
                if (!$file = $this->getImportFile('1c_highloadblock')) {
                    $this->getError(1);
                    break;
                }
                $this->getMessage(4, $file);
                $fullUrl = "$this->url?type=$this->type&mode=$this->mode&filename=$file&$this->sessid";
                $this->stepImport($fullUrl);
                break;
        }

        $this->getMessage(1);
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
    }

    private function stepImport($fullUrl)
    {
        $import = $this->convertEncoding($this->http->get($fullUrl));
        preg_match('/progress/', $import, $match);
        $this->getMessage(0, $import);
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

    private function getMessage($num, $param = null)
    {
        switch ($num) {
            case 0:
                $mess = $param;
                break;
            case 1:
                $mess = 'done';
                break;
            case 2:
                $mess = 'http client authentification success';
                break;
            case 3:
                $mess = 'http client initialisation success';
                break;
            case 4:
                $mess = "importing file $param";
                break;
            case 5:
                $mess = '<a href="' . $param . '" target="_blank">' . $param . '</a>';
                break;
            case 6:
                $mess = 'searching order..';
                break;
            case 7:
                $mess = "order #$this->orderId found";
                break;
            case 8:
                $mess = "order #$this->orderId marked for an exchange with 1C $param";
                break;
            default:
                $mess = null;
        }

        $this->add2log($mess);
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

    private function convertEncoding($str)
    {
        return mb_convert_encoding($str, 'UTF-8', 'windows-1251');
    }
}

$loader1C = new Loader1C();
$loader1C->handler();