<?php

namespace WC\Components;

use Bitrix\Main\Engine\Action;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\Response\AjaxJson;
use Bitrix\Main\Error;
use Bitrix\Main\Request;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Result;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Json;
use Bitrix\Sale\Order;

Loc::loadMessages(__FILE__);
Loc::loadMessages(__DIR__ . '/class.php');

class Debug1CAjaxController extends Controller
{
    private $tmpDir;
    private $logFile;
    private $ordersFile;
    private $infoFile;
    private $data;
    private $url;
    private $httpClient;
    private $sessid;
    private $log;

    /** @var Debug1C $class */
    private $class;
    /**
     * @var mixed
     */
    private $exchangeUrl;

    public function __construct(Request $request = null)
    {
        parent::__construct($request);

        \Bitrix\Main\Loader::includeModule('sale');

        $this->class = \CBitrixComponent::includeComponentClass('wc:debug1c');
        $this->data = $this->getData();
        if ($this->data['EXCHANGE_URL']) {
            $this->exchangeUrl = $this->class::getPathExchangeUrl(false, $this->data['EXCHANGE_URL']);
        } else {
            $this->exchangeUrl = $this->class::getPathExchangeUrl(false);
        }
        $this->logFile = $this->class::getLogFile();
    }

    private function getData(): array
    {
        $data = $this->request->toArray() ?: [];

        if ($data['TYPE_MODE'] && $dataType = Json::decode(htmlspecialcharsback($data['TYPE_MODE']))) {
            $data = array_merge($data, $dataType);
        } else {
            $this->add2log(Loc::getMessage('WC_DEBUG1C_MODE_NOT_SELECTED'));
        }

        return $data;
    }

    public function configureActions(): array
    {
        return [
            'init' => [
                'prefilters' => [], 'postfilters' => [],
            ],
            'prepare' => [
                'prefilters' => [], 'postfilters' => [],
            ],
        ];
    }

    public function processBeforeAction(Action $action): bool
    {

        if (!$this->createHttpClient()) {
            $this->add2log(Loc::getMessage('WC_DEBUG1C_HTTP_CLIENT_CREATE_ERROR'));
            return false;
        }

        return true;
    }

    public function prepareAction(): void
    {
        if (!$this->class::prepareTmpDir()) {
            $this->add2log(Loc::getMessage('WC_DEBUG1C_PREPARE_DIR_ERROR'));
        }
    }


    public function initAction(): AjaxJson
    {
        $this->add2log(Loc::getMessage('WC_DEBUG1C_URL', ['#URL#'=>$this->exchangeUrl]));

        if ($this->modeCheckAuth()) {
            $this->controller();
        }

        $this->add2log(Loc::getMessage('WC_DEBUG1C_DONE'));

        return new AjaxJson();
    }

    private function modeCheckAuth(): bool
    {
        $url = "$this->exchangeUrl?type={$this->data['type']}&mode=checkauth";
        $get = $this->convertEncoding($this->httpClient->get($url));

        preg_match('/sessid=(?!")\K.*/', $get, $sessid);

        if ($this->sessid = $sessid[0]) {
            $this->httpClient->setHeader('X-Bitrix-Csrf-Token', $this->sessid, true);
            $this->add2log(Loc::getMessage('WC_DEBUG1C_HTTP_CLIENT_AUTH_SUCCESS'));
            return true;
        }

        $this->add2log(Loc::getMessage('WC_DEBUG1C_HTTP_CLIENT_AUTH_ERROR'));
        return false;
    }

    private function controller(): void
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
                        $this->modeInit();
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

                        $order->setField('UPDATED_1C', 'N');

                        if ($order->save()->isSuccess()) {
                            $this->add2log(Loc::getMessage('WC_DEBUG1C_ORDER_MARKED', ['#ORDER_ID#' => $this->data['QUERY_ORDER_ID']]));
                        } else {
                            $this->add2log(Loc::getMessage('WC_DEBUG1C_ORDER_NOT_UPDATED', ['#ORDER_ID#' => $this->data['QUERY_ORDER_ID']]));
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
    }


    private function modeInit(): void
    {
        $version = $this->data['VERSION'] ? "version={$this->data['VERSION']}" : '';
        $url = "$this->exchangeUrl?type={$this->data['type']}&mode=init&sessid=$this->sessid&$version";

        if ($init = $this->convertEncoding($this->httpClient->get($url))) {
            $this->add2log(Loc::getMessage('WC_DEBUG1C_HTTP_CLIENT_INIT_SUCCESS'));
        }
    }

    private function modeImport($file): void
    {
        $url = "$this->exchangeUrl?type={$this->data['type']}&mode={$this->data['mode']}&sessid=$this->sessid&filename=$file";
        $get = $this->convertEncoding($this->httpClient->get($url));

        preg_match('/progress/', $get, $match);

        $this->add2log(Loc::getMessage('WC_DEBUG1C_REPLACE', ['#REPLACE#' => $get]));

        if ($match) {
            $this->modeImport($file);
        }
    }

    private function modeQuery(): void
    {
        $orderId = $this->data['QUERY_ORDER_ID'] ? "orderId={$this->data['QUERY_ORDER_ID']}" : '';
        $url = "$this->exchangeUrl?type={$this->data['type']}&mode={$this->data['mode']}&sessid=$this->sessid&$orderId";
        $get = $this->httpClient->get($url);

        file_put_contents($this->class::getOrderFile(), $get);
        $this->add2log(Loc::getMessage('WC_DEBUG1C_FILE_LINK', ['#FILE#' => $this->class::getOrderFile(false)]));
    }

    private function modeInfo(): void
    {
        $url = "$this->exchangeUrl?type={$this->data['type']}&mode={$this->data['mode']}&sessid=$this->sessid";
        $get = $this->httpClient->get($url);

        file_put_contents($this->class::getFileInfo(), $get);
        $this->add2log(Loc::getMessage('WC_DEBUG1C_FILE_LINK', ['#FILE#' => $this->class::getFileInfo(false)]));
    }

    private function getImportFile($dir): ?string
    {
        $files = scandir("{$_SERVER['DOCUMENT_ROOT']}/upload/$dir/", 1);

        foreach ($files as $file) {
            $info = new \SplFileInfo($file);

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

        file_put_contents($this->logFile, $this->log);
    }

    private function convertEncoding($str): string
    {
        return mb_convert_encoding($str, 'UTF-8', 'windows-1251'); // todo в параметр
    }


    private function createHttpClient(): bool
    {
        $this->httpClient = new HttpClient();
        $unsignedParameters = $this->getUnsignedParameters();
        $this->httpClient->setAuthorization($unsignedParameters['LOGIN'], $unsignedParameters['PASSWORD']);
        $this->httpClient->get($this->exchangeUrl);
        $cookie = $this->httpClient->getCookies()->toArray();

        if ($cookie['PHPSESSID']) {
            $this->httpClient->setCookies(['PHPSESSID' => $cookie['PHPSESSID'], 'XDEBUG_SESSION' => 'PHPSTORM']); // todo в параметр
            return true;
        }

        return false;
    }
}
