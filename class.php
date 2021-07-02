<?php


namespace WC\Components;


use Bitrix\Main\Localization\Loc;

class Debug1C extends \CBitrixComponent
{
    public function __construct($component = null)
    {
        parent::__construct($component);

        \CUtil::InitJSCore(['ajax']);
    }

    protected function listKeysSignedParameters(): array
    {
        return ['PASSWORD', 'LOGIN'];
    }

    public function onPrepareComponentParams($arParams): array
    {
        $arParams['LOG_FILE'] = self::getLogFile(false);

        return $arParams;
    }

    public function executeComponent()
    {
        global $USER;

        if ($USER->IsAuthorized()) {
            $this->arResult['USER_INFO'] = \CUser::GetByID($USER->GetID())->Fetch();
            $this->arResult['USER_INFO']['IS_AUTHORIZED'] = true;
            $this->arResult['USER_INFO']['IS_ADMIN'] = $USER->IsAdmin();

            if (!self::prepareTmpDir()) {
                throw new \Bitrix\Main\SystemException(Loc::getMessage('WC_DEBUG1C_PREPARE_DIR_ERROR'));
            }
        }

        $this->includeComponentTemplate();
    }

    public static function prepareTmpDir(): bool
    {
        if (!is_dir($tmpDir = self::getPathTmpDir()) && !mkdir($tmpDir, 0777, true)) {
            return false;
        }

        $files = [
            'LOG' => self::getLogFile(),
            'ORDER' => self::getOrderFile(),
            'INFO' => self::getFileInfo(),
        ];

        foreach ($files as $file) {
            if (file_put_contents($file, '') === false) {
                return false;
            }
        }

        return true;
    }

    public static function getPathTmpDir($absolutePath = true): string
    {
        return self::getPathBase($absolutePath, '/upload/tmp/debug1c');
    }

    public static function getPathBase($absolutePath, $path): string
    {
        if ($absolutePath) {
            $path = "{$_SERVER['DOCUMENT_ROOT']}$path";
        }

        return $path;
    }

    private static function getFileBase($absolutePath, $name): string
    {
        $tmpDirPath = self::getPathTmpDir($absolutePath);

        return "$tmpDirPath/$name";
    }

    public static function getLogFile($absolutePath = true): string
    {
        return self::getFileBase($absolutePath, 'log.txt');
    }

    public static function getOrderFile($absolutePath = true): string
    {
        return self::getFileBase($absolutePath, 'order.xml');
    }

    public static function getFileInfo($absolutePath = true): string
    {
        return self::getFileBase($absolutePath, 'info.xml');
    }

    public static function getPathExchangeUrl($absolutePath = true, $path = 'bitrix/admin/1c_exchange.php'): string
    {
        $request = \Bitrix\Main\Context::getCurrent()->getRequest();
        $path = self::getPathBase($absolutePath, $path);
        $protocol = $request->isHttps() ? "https://" : "http://";

        return "$protocol{$_SERVER['SERVER_NAME']}/$path";
    }
}
