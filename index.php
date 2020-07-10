<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
global $USER;
if ($USER->IsAuthorized()) {
    $userInfo = CUser::GetByID($USER->GetID())->Fetch();
    $isAdmin = $USER->IsAdmin();
}
?>
<html>
<head></head>
<body>
<div id="auth">
    <? if ($userInfo) {
        $userInfo = CUser::GetByID($USER->GetID())->Fetch();
        echo
            '[' . $userInfo['ID'] . '], ' .
            $userInfo['NAME'] . ' ' .
            $userInfo['LAST_NAME'] . ', ' .
            $userInfo['EMAIL'] . ' | ';
        ?>
      <form method="POST" action="" style="display: inline-block">
        <input type="hidden" name="action" value="logout">
        <input type="submit" value="logout"/>
      </form>
    <? } else { ?>
      <form method="POST" action="">
        <input type="hidden" name="action" value="login">
        <input type="text" name="login" placeholder="login"/>
        <input type="password" name="password" placeholder="password"/>
        <input type="submit" value="login"/>
      </form>
    <? } ?>
  <div id="mess"></div>
</div>
<hr>
<div>
    <? if ($isAdmin) { ?>
      <p id="jsCatalogImport"><a href="#">Catalog Import</a></p>
      <p id="jsSaleImport"><a href="#">Sale Import</a></p>
      <p id="jsHighLoadBlock"><a href="#">HighLoadBlock Import</a></p>
      <p id="jsExchangeOrder1C">
        <a href="#">Exchange Order 1C</a>
        <input type="text" placeholder="Order ID"/>
      </p>
    <? } ?>
  <p id="jsSaleExport">
    <a href="#">Sale Export</a>
    <input type="text" placeholder="Order ID (opt)"/>
    Ver.
    <select>
      <option value="2.05">2.05</option>
      <option value="2.09" selected>2.09</option>
    </select>
  </p>
  <p id="jsSaleInfo"><a href="#">Sale Info</a></p>
</div>
<hr>
<div id="data">
  <pre></pre>
</div>
<script type="text/javascript" src="https://yastatic.net/jquery/3.3.1/jquery.min.js"></script>
<script type="text/javascript" src="script.js"></script>
</body>
</html>