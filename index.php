<? require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php'); ?>
<html>
<head></head>
<body>
<div>
  <p id="jsCatalogImport"><a href="#">Catalog Import</a></p>
  <p id="jsSaleImport"><a href="#">Sale Import</a></p>
  <p id="jsSaleExport"><a href="#">Sale Export</a>
    Order ID (opt)
    <input type="text"/>
    Version
    <select>
      <option value="2.05">2.05</option>
      <option value="2.09" selected>2.09</option>
    </select>
  </p>
  <p id="jsSaleInfo"><a href="#">Sale Info</a></p>
  <p id="jsHighLoadBlock"><a href="#">HighLoadBlock Import</a></p>
</div>
<hr>
<div id="data">
  <pre></pre>
</div>
<script type="text/javascript" src="https://yastatic.net/jquery/3.3.1/jquery.min.js"></script>
<script type="text/javascript" src="script.js"></script>
</body>
</html>