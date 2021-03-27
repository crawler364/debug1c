### Установка
* Компонент распаковать в /local/components/wc/debug1c/
* Разместить компонент на любой странице (Путь в визуальном редакторе WC/Debug1C)
* Указать в параметрах компонента логин\пароль для http клиента дебага

### Режимы
* Catalog Import - загрузка каталога в ИБ (/upload/1c_catalog/*)
* Sale Import - загрузка заказов (/upload/1c_exchange/*)
* HighLoadBlock Import - загрузка справочников в HL блоки (/upload/1c_highloadblock/*)
* Exchange Order - пометить заказ на выгрузку в 1С
* Sale Query - выгрузка заказов помеченных для обмена с 1С (при выгрузке по ИД пометка игнорируется**)
* Sale Info - информация о настройках обмена


### Поддерживаемые компоненты обмена
* sale.export.1c (Sale Import, Exchange Order, Sale Query, Sale Info)
* catalog.import.1c (Catalog Import)
* catalog.import.hl (HighLoadBlock Import)

### Системные требования
* 1C-Битрикс: 17.5.10
* Кодировка: UTF-8

### Примечания
*Каталоги в которых должны располагаться .xml файлы соответственно. Название любое, используется 1 найденный файл. .xml для загрузки можно получить выгрузкой в папку с помощью соответствующего модуля обмена 1С.

**Для выгрузки по ИД заказа (Sale Query) в компонент sale.export.1c component.php  (300+ строка)

перед строкой
```
if($_SESSION["BX_CML2_EXPORT"]["cmlVersion"] >= doubleval(\Bitrix\Sale\Exchange\ExportOneCBase::SHEM_VERSION_2_10))
```
добавить
```
if ($_GET['orderId'] > 0) {
    unset($arFilter);
    $arFilter["ID"] = (int)$_GET['orderId'];
}
```
