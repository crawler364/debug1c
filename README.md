Доступ к index.php и loader1c.php запрещен для всех кроме админов (.access.php).
Доступ к остальным файлам можно закрыть через .htaccess.

Для фильтра по номеру заказа в catalog.import.1c компоненте (300+ строка)
if ($_GET['orderId']) {
    unset($arFilter);
    $arFilter["ID"] = (int)$_GET["orderId"];
}