async function parseLog() {
    await new Promise(r => setTimeout(r, 500));
    $.ajax({
        url: 'log.txt',
        dataType: 'text',
        async: false
    }).done(function (res) {
        $('#data pre').html(res);
        if (res.search('done') === -1) {
            parseLog();
        }
    });
}

$(document).ready(function () {
    $('#auth #form').submit(function (e) {
        e.preventDefault();
        const $mess = $('#auth #mess');
        $mess.html('');
        const data = $(e.target).serializeArray().reduce((acc, {name, value}) => ({...acc, [name]: value}), {});
        switch (data.action) {
            case 'login':
                $.ajax({
                    url: 'auth.php?action=' + data.action + '&login=' + data.login + '&password=' + data.password,
                    dataType: 'json'
                }).done(function (res) {
                    if (res.TYPE === 'ERROR') {
                        $mess.html(res.MESSAGE);
                    } else {
                        location.reload();
                    }
                });
                break;
            case 'logout':
                $.ajax({url: 'auth.php?action=' + data.action}).done(function () {
                    location.reload();
                });
                break;
        }
    });
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
        $.ajax({url: 'loader1c.php?type=sale&mode=query&orderId=' + orderId + '&version=' + version});
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
    $('#jsExchangeOrder1C a').click(function () {
        let orderId = $('#jsExchangeOrder1C input').val();
        $.ajax({url: 'loader1c.php?type=sale&mode=exchangeOrder1C&orderId=' + orderId});
        parseLog();
    });
});