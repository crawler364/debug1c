class WCDebug1C {
    constructor() {
        BX.ready(() => {
            this.wcDebug1c = BX('wc-debug1c');
            this.form = BX.findChild(this.wcDebug1c, {tag: 'form'}, true, false);
            this.data = BX.findChild(
                BX.findChild(this.wcDebug1c, {attribute: {'data-type': 'data'}}, true, false),
                {tag: 'pre'}, true, false
            );

            BX.bindDelegate(this.wcDebug1c, 'submit', this.form, this.handler.bind(this));
        });
    }

    handler(e) {
        BX.PreventDefault(e);

        let formData = new FormData(e.target);

        console.log(formData)
        BX.ajax.runComponentAction('wc:debug1c', 'handler', {
            mode: 'ajax',
            data: formData,
        }).then((response) => {
            console.log(response);
            this.parseLog();
        }, function (response) {
            console.log(response);
            // todo обработка ошибок
        });

    }

    parseLog() {
        setTimeout(function () {
            BX.ajax.insertToNode('log.txt', this.data);
        }, 500);


        /*BX.ajax({
            url: 'log.txt',
            dataType: 'html',
            async: false
        }).then((response) => {
            BX.adjust(this.data, {text: response});

            if (response.search('done') === -1) {
                parseLog();
            }
        }, (response) => {

        });*/
    }
}
