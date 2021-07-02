class WCDebug1C {
    constructor(params) {
        this.parameters = params.parameters;
        this.signedParameters = params.signedParameters;
        this.debug1C = BX('debug1c');
        this.log = BX.findChild(this.debug1C, {tag: 'pre', attribute: {'data-type': 'log'}}, true, false);

        BX.bindDelegate(this.debug1C, 'submit', {
            tag: 'form',
            attribute: {'name': 'debug'}
        }, this.handler.bind(this));
    }

    async handler(e) {
        BX.PreventDefault(e);
        BX.showWait();

        await BX.ajax.runComponentAction('wc:debug1c', 'prepare', {mode: 'ajax'});
        this.parse();

        BX.ajax.runComponentAction('wc:debug1c', 'init', {
            mode: 'ajax',
            data: new FormData(e.target),
            signedParameters: this.signedParameters,
        }).then((response) => {
            BX.closeWait();
        }, (response) => {
            // все ошибки пишутся в лог
        });
    }

    parse() {
        BX.ajax({
            url: this.parameters.logFile,
            dataType: 'html',
            cache: false,
            onsuccess: (response) => {
                console.log(response);
                BX.adjust(this.log, {html: response});
                if (response.search('debug completed') === -1) {
                    this.loopTimeout = setTimeout(() => {
                        this.parse()
                    }, 500);
                } else {
                    clearTimeout(this.loopTimeout);
                }
            }
        });
    }
}
