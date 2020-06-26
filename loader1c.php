<?
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

class Loader1C
{
    private $logFile = 'log.txt';
    private $ordersFile = 'orders.xml';
    private $infoFile = 'info.xml';

    public function __construct()
    {
        global $USER;
        if (!$USER->IsAdmin()) {
            $this->add2log($this->getError(0));
            exit;
        }
        file_put_contents($this->logFile, '');
        file_put_contents($this->ordersFile, '');
        file_put_contents($this->infoFile, '');
        $serverName = $_SERVER['SERVER_NAME'];
        $this->url = "http://$serverName/local/admin/1c_exchange.php";
        $this->type = $_GET['type'];
        $this->mode = $_GET['mode'];
        $this->version = $_GET['version'];
        $this->orderId = $_GET['orderId'];
        $this->http = new \Bitrix\Main\Web\HttpClient();
        $this->http->setAuthorization('XXXXXXXXX', 'XXXXXXXXX');
        $this->http->setCookies(['PHPSESSID' => uniqid('', false), 'XDEBUG_SESSION' => 'PHPSTORM']);
        $this->checkAuth();
    }

    private function checkAuth()
    {
        $fullUrl = "$this->url?type=$this->type&mode=checkauth";
        $checkauth = $this->convertEncoding($this->http->get($fullUrl));
        preg_match('/sessid=.*/', $checkauth, $sessid);
        $this->sessid = $sessid[0];
        $this->http->setHeader('X-Bitrix-Csrf-Token', $this->sessid, true);
        $this->add2log($checkauth);
    }

    private function init()
    {
        $version = $this->version ? "version=$this->version" : '';
        $fullUrl = "$this->url?type=$this->type&$this->sessid&mode=init&$version";
        $init = $this->convertEncoding($this->http->get($fullUrl));
        $this->add2log($init);
    }

    public function handler()
    {
        switch ($this->type) {
            case 'catalog':
                // $this->init();
                if (!$file = $this->getImportFile('1c_catalog')) {
                    $this->getError(1);
                    break;
                }
                $this->add2log("importing file '$file'");
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
                        $this->add2log("importing file '$file'");
                        $fullUrl = "$this->url?type=$this->type&mode=$this->mode&filename=$file&$this->sessid";
                        $this->stepImport($fullUrl);
                        break;
                    case 'query':
                        $this->init();
                        $orderId = $this->orderId ? "orderId=$this->orderId" : '';
                        $fullUrl = "$this->url?type=$this->type&mode=$this->mode&$this->sessid&$orderId";
                        $query = $this->http->get($fullUrl);
                        file_put_contents($this->ordersFile, $query);
                        $this->add2log('<a href="' . $this->ordersFile . '" target="_blank">' . $this->ordersFile . '</a>');
                        break;
                    case'info':
                        $fullUrl = "$this->url?type=$this->type&mode=$this->mode&$this->sessid";
                        $info = $this->http->get($fullUrl);
                        file_put_contents($this->infoFile, $info);
                        $this->add2log('<a href="' . $this->infoFile . '" target="_blank">' . $this->infoFile . '</a>');
                        break;
                }
                break;
            case 'reference':
                // $this->init();
                if (!$file = $this->getImportFile('1c_highloadblock')) {
                    $this->getError(1);
                    break;
                }
                $this->add2log("importing file '$file'");
                $fullUrl = "$this->url?type=$this->type&mode=$this->mode&filename=$file&$this->sessid";
                $this->stepImport($fullUrl);
                break;
        }

        $this->add2log('done');
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
        $this->add2log($import);
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

    private function getError($num)
    {
        switch ($num) {
            case 0:
                $error = "Ошибка #$num. Надо быть админом.";
                break;
            case 1:
                $error = "Ошибка #$num. Файл для импорта не найден.";
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