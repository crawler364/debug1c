class WCDebug1C {
    constructor(params) {
        this.params = params;
        this.wcDebug1c = BX('wc-debug1c');
        this.log = BX.findChild(this.wcDebug1c, {tag: 'pre', attribute: {'data-type': 'log'}}, true, false);
        BX.bindDelegate(this.wcDebug1c, 'submit', {
            tag: 'form',
            attribute: {'name': 'debug'}
        }, this.handler.bind(this));
    }

    handler(e) {
        BX.PreventDefault(e);
        let formData = new FormData(e.target);

        BX.ajax.runComponentAction('wc:debug1c', 'init', {
            mode: 'ajax',
            data: formData,
            signedParameters: this.params.signedParameters,
        }).then((response) => {
            console.log(response);
            this.parseLog();
        }, function (response) {
            console.log(response);
            // todo обработка ошибок
        });
    }

    parseLog() {
        BX.ajax.runComponentAction('wc:debug1c', 'getLog', {
            mode: 'ajax',
        }).then((response) => {
            console.log(response.data);
            BX.adjust(
                this.log,
                {html:response.data}
            );
        }, function (response) {
            console.log(response);
            // todo обработка ошибок
        });

        /* console.log(this.log);
         setTimeout(function () {
             BX.ajax.insertToNode('/upload/tmp/debug1c/log.txt', this.log);
         }, 1000);

         console.log(this.log);
 */

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
