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
    private $httpClient;
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
        $protocol = $this->request->isHttps() ? "https://" : "http://";
        $this->data = $this->request->toArray();

        if ($this->data['TYPE_MODE'] && $dataType = Json::decode(htmlspecialcharsback($this->data['TYPE_MODE']))) {
            $this->data = array_merge($this->data, $dataType);
        } else {
            $this->add2log(Loc::getMessage('WC_DEBUG1C_MODE_NOT_SELECTED'));
        }
        $this->url = "$protocol{$_SERVER['SERVER_NAME']}/{$this->data['DIR']}/admin/1c_exchange.php";

        if ($this->createHttpClient() && $this->modeCheckAuth()) {
            $this->controller();
        }

        $this->add2log(Loc::getMessage('WC_DEBUG1C_DONE'));
    }

    public function prepareTmpDirectoryAction(): string
    {
        $tmpDir = "{$_SERVER['DOCUMENT_ROOT']}$this->tmpDir";

        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }

        file_put_contents("{$_SERVER['DOCUMENT_ROOT']}$this->logFile", '');
        file_put_contents("{$_SERVER['DOCUMENT_ROOT']}$this->ordersFile", '');
        file_put_contents("{$_SERVER['DOCUMENT_ROOT']}$this->infoFile", '');

        return $this->logFile;
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

    private function modeCheckAuth(): bool
    {
        $url = "$this->url?type={$this->data['type']}&mode=checkauth";
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

    private function modeInit(): void
    {
        $version = $this->data['VERSION'] ? "version={$this->data['VERSION']}" : '';
        $url = "$this->url?type={$this->data['type']}&mode=init&sessid=$this->sessid&$version";

        if ($init = $this->convertEncoding($this->httpClient->get($url))) {
            $this->add2log(Loc::getMessage('WC_DEBUG1C_HTTP_CLIENT_INIT_SUCCESS'));
        }
    }

    private function modeImport($file): void
    {
        $url = "$this->url?type={$this->data['type']}&mode={$this->data['mode']}&sessid=$this->sessid&filename=$file";
        $get = $this->convertEncoding($this->httpClient->get($url));

        preg_match('/progress/', $get, $match);

        $this->add2log(Loc::getMessage('WC_DEBUG1C_REPLACE', ['#REPLACE#' => $get]));

        if ($match) {
            $this->modeImport($file);
        }
    }

    private function modeQuery(): void
    {
        $this->modeInit();
        $orderId = $this->data['QUERY_ORDER_ID'] ? "orderId={$this->data['QUERY_ORDER_ID']}" : '';
        $url = "$this->url?type={$this->data['type']}&mode={$this->data['mode']}&sessid=$this->sessid&$orderId";
        $get = $this->httpClient->get($url);

        file_put_contents("{$_SERVER['DOCUMENT_ROOT']}$this->ordersFile", $get);
        $this->add2log(Loc::getMessage('WC_DEBUG1C_FILE_LINK', ['#FILE#' => $this->ordersFile]));
    }

    private function modeInfo(): void
    {
        $url = "$this->url?type={$this->data['type']}&mode={$this->data['mode']}&sessid=$this->sessid";
        $get = $this->httpClient->get($url);

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

    private function createHttpClient(): bool
    {
        $this->httpClient = new HttpClient();
        $unsignedParameters = $this->getUnsignedParameters();
        $this->httpClient->setAuthorization($unsignedParameters['LOGIN'], $unsignedParameters['PASSWORD']);
        $this->httpClient->get($this->url);
        $cookie = $this->httpClient->getCookies()->toArray();

        if ($cookie['PHPSESSID']) {
            $this->httpClient->setCookies(['PHPSESSID' => $cookie['PHPSESSID'], 'XDEBUG_SESSION' => 'PHPSTORM']); // todo в параметр
            return true;
        }

        $this->add2log(Loc::getMessage('WC_DEBUG1C_HTTP_CLIENT_CREATE_ERROR'));
        return false;
    }
}
