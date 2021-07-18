<?php


namespace WC\Components;


use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Error;
use Bitrix\Main\Loader;
use Bitrix\Main\Request;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Json;
use Bitrix\Sale\Order;

Loc::loadMessages(__FILE__);
Loc::loadMessages(__DIR__ . '/class.php');

class Debug1CAjaxController extends Controller
{
    /** @var Debug1C $class */
    private $class;
    private $params;
    private $httpClient;
    private $sessid;
    private $log;
    private $logFile;

    public function __construct(Request $request = null)
    {
        parent::__construct($request);

        Loader::includeModule('sale');

        $this->class = \CBitrixComponent::includeComponentClass('wc:debug1c');
        $this->logFile = $this->class::getPathLogFile();
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

    public function prepareAction(): string
    {
        if (!$this->class::prepareTmpDir()) {
            $this->addError(new Error(Loc::getMessage('WC_DEBUG1C_PREPARE_DIR_ERROR')));
        }

        return empty($this->getErrors()) ?
            Loc::getMessage('WC_DEBUG1C_PREPARE_DIR_SUCCESS') : Loc::getMessage('WC_DEBUG1C_PREPARE_DIR_ERROR');
    }

    public function initAction(): string
    {
        $this->add2log(Loc::getMessage('WC_DEBUG1C_STARTED'));

        $this->params = $this->getParams();
        $this->httpClient = $this->createHttpClient();

        if (empty($this->getErrors())) {
            $this->modeCheckAuth();
        }

        if (empty($this->getErrors())) {
            $this->modeController();
        }

        $this->add2log(Loc::getMessage('WC_DEBUG1C_COMPLETED'));

        return empty($this->getErrors()) ?
            Loc::getMessage('WC_DEBUG1C_COMPLETED_SUCCESS') : Loc::getMessage('WC_DEBUG1C_COMPLETED_ERROR');
    }

    private function getParams(): ?array
    {
        $params = $this->request->toArray() ?: [];

        // TYPE_MODE
        if ($params['TYPE_MODE'] && $dataType = Json::decode(htmlspecialcharsback($params['TYPE_MODE']))) {
            $params = array_merge($params, $dataType);
        } else {
            $this->add2logError(Loc::getMessage('WC_DEBUG1C_MODE_NOT_SELECTED'));
            return null;
        }

        // EXCHANGE_URL
        if ($exchangeUrl = $params['EXCHANGE_URL'] ?
            $this->class::getExchangeUrl($params['EXCHANGE_URL']) : $this->class::getExchangeUrl()) {
            $this->add2log(Loc::getMessage('WC_DEBUG1C_EXCHANGE_URL', ['#URL#' => $exchangeUrl]));
            $params['EXCHANGE_URL'] = $exchangeUrl;
        } else {
            $this->add2logError(Loc::getMessage('WC_DEBUG1C_FILE_NOT_EXIST', ['#FILE#' => $params['EXCHANGE_URL']]));
            return null;
        }

        return $params;
    }

    private function add2logError($message): void
    {
        $this->addError(new Error($message));
        $this->add2log($message);
    }

    private function add2log($str): void
    {
        $str = preg_replace("/[\\n]/", " ", $str);
        $this->log .= date('d.m.y H:i:s') . ": $str \n";

        file_put_contents($this->logFile, $this->log);
    }

    private function createHttpClient(): ?HttpClient
    {
        $httpClient = new HttpClient();

        $unsignedParameters = $this->getUnsignedParameters();

        if (!$unsignedParameters['LOGIN']) {
            $this->add2logError(Loc::getMessage('WC_DEBUG1C_EMPTY_PARAM', ['#PARAM#' => 'LOGIN']));
            return null;
        }
        if (!$unsignedParameters['PASSWORD']) {
            $this->add2logError(Loc::getMessage('WC_DEBUG1C_EMPTY_PARAM', ['#PARAM#' => 'PASSWORD']));
            return null;
        }

        $httpClient->setAuthorization($unsignedParameters['LOGIN'], $unsignedParameters['PASSWORD']);

        $httpClient->get($this->params['EXCHANGE_URL']);
        $cookie = $httpClient->getCookies()->toArray();

        if (!$cookie['PHPSESSID']) {
            $this->add2logError(Loc::getMessage('WC_DEBUG1C_HTTP_CLIENT_CREATE_ERROR'));
            return null;
        }

        $httpClient->setCookies(['PHPSESSID' => $cookie['PHPSESSID'], 'XDEBUG_SESSION' => 'PHPSTORM']); // todo в параметр

        return $httpClient;
    }

    private function modeCheckAuth(): bool
    {
        $url = "{$this->params['EXCHANGE_URL']}?type={$this->params['TYPE']}&mode=checkauth";
        $get = $this->convertEncoding($this->httpClient->get($url));

        preg_match('/sessid=(?!")\K.*/', $get, $sessid);

        if ($this->sessid = $sessid[0]) {
            $this->httpClient->setHeader('X-Bitrix-Csrf-Token', $this->sessid, true);
            $this->add2log(Loc::getMessage('WC_DEBUG1C_HTTP_CLIENT_AUTH_SUCCESS'));
            return true;
        }

        $this->add2logError(Loc::getMessage('WC_DEBUG1C_HTTP_CLIENT_AUTH_ERROR'));

        return false;
    }

    private function convertEncoding($str): string
    {
        return mb_convert_encoding($str, 'UTF-8', 'windows-1251'); // todo в параметр
    }

    private function modeController(): void
    {
        switch ($this->params['TYPE']) {
            case 'catalog':
                switch ($this->params['MODE']) {
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
                switch ($this->params['MODE']) {
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

                        if ($this->params['EXCHANGE_ORDER_ID'] > 0 && $order = Order::load($this->params['EXCHANGE_ORDER_ID'])) {
                            $this->add2log(Loc::getMessage('WC_DEBUG1C_ORDER_FOUND', ['#ORDER_ID#' => $this->params['EXCHANGE_ORDER_ID']]));
                        } else {
                            $this->add2log(Loc::getMessage('WC_DEBUG1C_ORDER_NOT_FOUND', ['#ORDER_ID#' => $this->params['EXCHANGE_ORDER_ID']]));
                            break;
                        }

                        $order->setField('UPDATED_1C', 'N');

                        if ($order->save()->isSuccess()) {
                            $this->add2log(Loc::getMessage('WC_DEBUG1C_ORDER_MARKED', ['#ORDER_ID#' => $this->params['QUERY_ORDER_ID']]));
                        } else {
                            $this->add2log(Loc::getMessage('WC_DEBUG1C_ORDER_NOT_UPDATED', ['#ORDER_ID#' => $this->params['QUERY_ORDER_ID']]));
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

    private function getImportFile($dir): ?string
    {
        $files = scandir("{$_SERVER['DOCUMENT_ROOT']}/upload/$dir/", 1);

        foreach ($files as $file) {
            $info = new \SplFileInfo($file);

            if ($info->getExtension() === 'xml') {
                return $file;
            }
        }

        $this->add2logError(Loc::getMessage('WC_DEBUG1C_FILE_NOT_FOUND'));

        return null;
    }

    private function modeImport($file): void
    {
        $url = "{$this->params['EXCHANGE_URL']}?type={$this->params['TYPE']}&mode={$this->params['MODE']}&sessid=$this->sessid&filename=$file";
        $get = $this->convertEncoding($this->httpClient->get($url));

        preg_match('/progress/', $get, $match);

        $this->add2log(Loc::getMessage('WC_DEBUG1C_REPLACE', ['#REPLACE#' => $get]));

        if ($match) {
            $this->modeImport($file);
        }
    }

    private function modeInit(): void
    {
        $version = $this->params['VERSION'] ? "version={$this->params['VERSION']}" : '';
        $url = "{$this->params['EXCHANGE_URL']}?type={$this->params['TYPE']}&mode=init&sessid=$this->sessid&$version";

        if ($init = $this->convertEncoding($this->httpClient->get($url))) {
            $this->add2log(Loc::getMessage('WC_DEBUG1C_HTTP_CLIENT_INIT_SUCCESS'));
        }
    }

    private function modeQuery(): void
    {
        $orderId = $this->params['QUERY_ORDER_ID'] ? "orderId={$this->params['QUERY_ORDER_ID']}" : '';
        $url = "{$this->params['EXCHANGE_URL']}?type={$this->params['TYPE']}&mode={$this->params['MODE']}&sessid=$this->sessid&$orderId";
        $get = $this->httpClient->get($url);

        file_put_contents($this->class::getPathOrderFile(), $get);
        $this->add2log(Loc::getMessage('WC_DEBUG1C_FILE_LINK', ['#FILE#' => $this->class::getPathOrderFile(false)]));
    }

    private function modeInfo(): void
    {
        $url = "{$this->params['EXCHANGE_URL']}?type={$this->params['TYPE']}&mode={$this->params['MODE']}&sessid=$this->sessid";
        $get = $this->httpClient->get($url);

        file_put_contents($this->class::getPathFileInfo(), $get);
        $this->add2log(Loc::getMessage('WC_DEBUG1C_FILE_LINK', ['#FILE#' => $this->class::getPathFileInfo(false)]));
    }
}
