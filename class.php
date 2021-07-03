<?php


namespace WC\Components;


use Bitrix\Main\IO\File;
use Bitrix\Main\Localization\Loc;

class Debug1C extends \CBitrixComponent
{
    public function __construct($component = null)
    {
        parent::__construct($component);

        \CUtil::InitJSCore(['ajax']);
    }

    public function onPrepareComponentParams($arParams): array
    {
        $arParams['LOG_FILE'] = self::getPathLogFile(false);

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

            $this->includeComponentTemplate();
        } else {
            $this->includeComponentTemplate('template_auth');
        }
    }

    protected function listKeysSignedParameters(): array
    {
        return ['PASSWORD', 'LOGIN'];
    }

    public static function getExchangeUrl($path = '/bitrix/admin/1c_exchange.php'): ?string
    {
        if (!File::isFileExists(self::getPathBase(true, $path))){
            return null;
        }

        $request = \Bitrix\Main\Context::getCurrent()->getRequest();
        $path = self::getPathBase(false, $path);
        $protocol = $request->isHttps() ? "https://" : "http://";

        return "$protocol{$_SERVER['SERVER_NAME']}$path";
    }

    public static function getPathBase($absolutePath, $path): string
    {
        if ($absolutePath) {
            $path = "{$_SERVER['DOCUMENT_ROOT']}$path";
        }

        return $path;
    }

    public static function getPathLogFile($absolutePath = true): string
    {
        return self::getPathFileBase($absolutePath, 'log.txt');
    }

    public static function getPathTmpDir($absolutePath = true): string
    {
        return self::getPathBase($absolutePath, '/upload/tmp/debug1c');
    }

    public static function prepareTmpDir(): bool
    {
        if (!is_dir($tmpDir = self::getPathTmpDir()) && !mkdir($tmpDir, 0777, true)) {
            return false;
        }

        $files = [
            'LOG' => self::getPathLogFile(),
            'ORDER' => self::getPathOrderFile(),
            'INFO' => self::getPathFileInfo(),
        ];

        foreach ($files as $file) {
            if (file_put_contents($file, '') === false) {
                return false;
            }
        }

        return true;
    }

    public static function getPathOrderFile($absolutePath = true): string
    {
        return self::getPathFileBase($absolutePath, 'order.xml');
    }

    public static function getPathFileInfo($absolutePath = true): string
    {
        return self::getPathFileBase($absolutePath, 'info.xml');
    }

    private static function getPathFileBase($absolutePath, $name): string
    {
        $tmpDirPath = self::getPathTmpDir($absolutePath);

        return "$tmpDirPath/$name";
    }
}
