class WCDebug1C {
    constructor(params) {
        this.params = params;
        this.wcDebug1c = BX('wc-debug1c');
        this.data = BX.findChild(this.wcDebug1c, {tag: 'pre', attribute: {'data-type': 'debug-data'}}, true, false);
        BX.bindDelegate(this.wcDebug1c, 'submit', {
            tag: 'form',
            attribute: {'name': 'debug'}
        }, this.handler.bind(this));
    }

    handler(e) {
        BX.PreventDefault(e);
        let formData = new FormData(e.target);

        BX.ajax.runComponentAction('wc:debug1c', 'handler', {
            mode: 'ajax',
            data: formData,
            //signedParameters: this.params.signedParameters,
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
