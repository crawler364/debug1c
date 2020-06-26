Доступ через .htaccess. Импорт только для админов.

Для фильтра по номеру заказа в catalog.import.1c компоненте (300+ строка)
```
if ($_GET['orderId']) {
    unset($arFilter);
    $arFilter["ID"] = (int)$_GET["orderId"];
}
```