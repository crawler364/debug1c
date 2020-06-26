<?
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

        $this->context = \Bitrix\Main\Context::getCurrent();
        $this->request = $this->context->getRequest();
        $this->type = $this->request->get('type');
        $this->mode = $this->request->get('mode');
        $this->version = $this->request->get('version');
        $this->orderId = $this->request->get('orderId');
        $this->url = 'http://' . $_SERVER['SERVER_NAME'] . '/local/admin/1c_exchange.php';

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
        $init = $this->convertEncoding($this->http->get($fullUrl));
        $this->getMessage(3);
    }

    public function handler()
    {
        if ($this->mode == 'import') {
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
            if ($info->getExtension() == 'xml') {
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