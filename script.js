async function parseLog() {
    await new Promise(r => setTimeout(r, 500));
    $.ajax({
        url: 'log.txt',
        dataType: 'text',
        async: false
    }).done(function (res) {
        $('#data pre').html(res);
        if (res.search('done') == -1) {
            parseLog();
        }
    });
}

$(document).ready(function () {
    $("#jsCatalogImport a").click(function () {
        $.ajax({url: 'loader1c.php?type=catalog&mode=import'});
        parseLog();
    });
    $('#jsSaleImport a').click(function () {
        $.ajax({url: 'loader1c.php?type=sale&mode=import'});
        parseLog();
    });
    $('#jsSaleExport a').click(function () {
        let orderId = $('#jsSaleExport input').val();
        let version = $('#jsSaleExport select').val();
        $.ajax({url: 'loader1c.php?type=sale&mode=query&version=2.05&orderId=' + orderId + '&version=' + version});
        parseLog();
    });
    $('#jsSaleInfo a').click(function () {
        $.ajax({url: 'loader1c.php?type=sale&mode=info'});
        parseLog();
    });
    $('#jsHighLoadBlock a').click(function () {
        $.ajax({url: 'loader1c.php?type=reference&mode=import'});
        parseLog();
    });
});