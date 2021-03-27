Для выгрузки по ИД заказа в catalog.import.1c добавить (300+ строка)
```
if ($_GET['orderId']) {
    unset($arFilter);
    $arFilter["ID"] = (int)$_GET["orderId"];
}
```
