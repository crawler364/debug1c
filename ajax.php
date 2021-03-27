<?php

use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Request;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Json;
use Bitrix\Sale\Order;

class WCDebug1CAjaxController extends Controller
{
    private $tmpDir;
    private $logFile;
    private $ordersFile;
    private $infoFile;
    private $data;
    private $url;
    private $http;
    private $sessid;
    private $log;

    public function __construct(Request $request = null)
    {
        parent::__construct($request);

        Bitrix\Main\Loader::includeModule('sale');
        Bitrix\Main\Localization\Loc::loadMessages(__FILE__);

        $this->tmpDir = "/upload/tmp/debug1c";
        $this->logFile = "$this->tmpDir/log.txt";
        $this->ordersFile = "$this->tmpDir/orders.xml";
        $this->infoFile = "$this->tmpDir/info.xml";
    }

    public function configureActions(): array
    {
        return [
            'init' => [
                'prefilters' => [], 'postfilters' => [],
            ],
            'prepareTmpDirectory' => [
                'prefilters' => [], 'postfilters' => [],
            ],
        ];
    }

    public function initAction(): void
    {
        $protocol = CMain::IsHTTPS() ? "https://" : "http://";
        $this->data = Bitrix\Main\Context::getCurrent()->getRequest()->toArray();
        if ($this->data['type-mode'] && $dataType = Json::decode(htmlspecialcharsback($this->data['type-mode']))) {
            $this->data = array_merge($this->data, $dataType);
        } else {
            $this->add2log(Loc::getMessage('WC_DEBUG1C_MODE_NOT_SELECTED'));
        }
        $this->url = "$protocol{$_SERVER['SERVER_NAME']}/{$this->data['dir']}/admin/1c_exchange.php";

        $this->createHttpClient();
        $this->modeCheckAuth();
        $this->handler();
    }

    public function prepareTmpDirectoryAction(): string
    {
        $tmpDir = "{$_SERVER['DOCUMENT_ROOT']}$this->tmpDir";

        if (!is_dir($tmpDir)) {
            mkdir($tmpDir);
        }

        file_put_contents("{$_SERVER['DOCUMENT_ROOT']}$this->logFile", '');
        file_put_contents("{$_SERVER['DOCUMENT_ROOT']}$this->ordersFile", '');
        file_put_contents("{$_SERVER['DOCUMENT_ROOT']}$this->infoFile", '');

        return $this->logFile;
    }

    private function handler(): void
    {
        switch ($this->data['type']) {
            case 'catalog':
                switch ($this->data['mode']) {
                    case 'import':
                        // $this->modeInit(); todo в параметр
                        if (!$file = $this->getImportFile('1c_catalog')) {
                            break;
                        }
                        $this->add2log(Loc::getMessage('WC_DEBUG1C_IMPORTING_FILE', ['#FILE#' => $file]));
                        $this->modeImport($file);
                        break;
                }
                break;
            case 'sale':
                switch ($this->data['mode']) {
                    case 'import':
                        // $this->modeInit(); todo в параметр
                        if (!$file = $this->getImportFile('1c_exchange')) {
                            break;
                        }
                        $this->add2log(Loc::getMessage('WC_DEBUG1C_IMPORTING_FILE', ['#FILE#' => $file]));
                        $this->modeImport($file);
                        break;
                    case 'query':
                        $this->modeQuery();
                        break;
                    case'info':
                        $this->modeInfo();
                        break;
                    case'exchange-order':
                        $this->add2log(Loc::getMessage('WC_DEBUG1C_SEARCHING_ORDER'));
                        if ($this->data['exchange-order-id'] > 0 && $order = Order::load($this->data['exchange-order-id'])) {
                            $this->add2log(Loc::getMessage('WC_DEBUG1C_ORDER_FOUND', ['#ORDER_ID#' => $this->data['exchange-order-id']]));
                        } else {
                            $this->add2log(Loc::getMessage('WC_DEBUG1C_ORDER_NOT_FOUND', ['#ORDER_ID#' => $this->data['exchange-order-id']]));
                            break;
                        }
                        /** @var Bitrix\Main\Type\Date $date */
                        $date = $order->getField('DATE_UPDATE');
                        $oldDateUpdate = $date->toString();
                        $order->setField('UPDATED_1C', 'Y');
                        $order->save();
                        $order->setField('UPDATED_1C', 'N');
                        $order->save();
                        $date = $order->getField('DATE_UPDATE');
                        $newDateUpdate = $date->toString();
                        if ($oldDateUpdate !== $newDateUpdate && $order->getField('UPDATED_1C') === 'N') {
                            $this->add2log(Loc::getMessage('WC_DEBUG1C_ORDER_MARKED', ['#ORDER_ID#' => $this->data['query-order-id'], '#DATE#' => $newDateUpdate]));
                        } else {
                            $this->add2log(Loc::getMessage('WC_DEBUG1C_ORDER_DONT_UPDATED', ['#ORDER_ID#' => $this->data['query-order-id']]));
                            break;
                        }
                        break;
                }
                break;
            case 'reference':
                // $this->modeInit(); todo в параметр
                if (!$file = $this->getImportFile('1c_highloadblock')) {
                    break;
                }
                $this->add2log(Loc::getMessage('WC_DEBUG1C_IMPORTING_FILE', ['#FILE#' => $file]));
                $this->modeImport($file);
                break;
        }

        $this->add2log(Loc::getMessage('WC_DEBUG1C_DONE'));
    }

    private function modeCheckAuth(): void
    {
        $url = "$this->url?type={$this->data['type']}&mode=checkauth";
        $get = $this->convertEncoding($this->http->get($url));

        preg_match('/sessid=\K.*/', $get, $sessid);

        if ($this->sessid = $sessid[0]) {
            $this->http->setHeader('X-Bitrix-Csrf-Token', $this->sessid, true);
            $this->add2log(Loc::getMessage('WC_DEBUG1C_HTTP_CLIENT_AUTH_SUCCESS'));
        }
    }

    private function modeInit(): void
    {
        $version = $this->data['version'] ? "version={$this->data['version']}" : '';
        $url = "$this->url?type={$this->data['type']}&mode=init&sessid=$this->sessid&$version";
        if ($init = $this->convertEncoding($this->http->get($url))) {
            $this->add2log(Loc::getMessage('WC_DEBUG1C_HTTP_CLIENT_INIT_SUCCESS'));
        }
    }

    private function modeImport($file): void
    {
        $url = "$this->url?type={$this->data['type']}&mode={$this->data['mode']}&sessid=$this->sessid&filename=$file";
        $get = $this->convertEncoding($this->http->get($url));
        preg_match('/progress/', $get, $match);
        $this->add2log(Loc::getMessage('WC_DEBUG1C_REPLACE', ['#REPLACE#' => $get]));
        if ($match) {
            $this->modeImport($file);
        }
    }

    private function modeQuery(): void
    {
        $this->modeInit();
        $orderId = $this->data['query-order-id'] ? "orderId={$this->data['query-order-id']}" : '';
        $url = "$this->url?type={$this->data['type']}&mode={$this->data['mode']}&sessid=$this->sessid&$orderId";
        $get = $this->http->get($url);
        file_put_contents("{$_SERVER['DOCUMENT_ROOT']}$this->ordersFile", $get);
        $this->add2log(Loc::getMessage('WC_DEBUG1C_FILE_LINK', ['#FILE#' => $this->ordersFile]));
    }

    private function modeInfo(): void
    {
        $url = "$this->url?type={$this->data['type']}&mode={$this->data['mode']}&sessid=$this->sessid";
        $get = $this->http->get($url);
        file_put_contents("{$_SERVER['DOCUMENT_ROOT']}$this->infoFile", $get);
        $this->add2log(Loc::getMessage('WC_DEBUG1C_FILE_LINK', ['#FILE#' => $this->infoFile]));
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

        $this->add2log(Loc::getMessage('WC_DEBUG1C_FILE_NOT_FOUND'));
        return null;
    }

    private function add2log($str): void
    {
        $str = preg_replace("/[\\n]/", " ", $str);
        $this->log .= date('d.m.y H:i:s') . ": $str \n";
        file_put_contents("{$_SERVER['DOCUMENT_ROOT']}$this->logFile", $this->log);
    }

    private function convertEncoding($str): string
    {
        return mb_convert_encoding($str, 'UTF-8', 'windows-1251'); // todo в параметр
    }

    private function createHttpClient(): void
    {
        $unsignedParameters = $this->getUnsignedParameters();

        $this->http = new HttpClient();
        $this->http->setAuthorization($unsignedParameters['LOGIN'], $unsignedParameters['PASSWORD']);
        $this->http->get($this->url);
        $cookie = $this->http->getCookies()->toArray();
        $this->http->setCookies(['PHPSESSID' => $cookie['PHPSESSID'], 'XDEBUG_SESSION' => 'PHPSTORM']); // todo в параметр
    }
}
