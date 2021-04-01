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

    async handler(e) {
        BX.PreventDefault(e);
        BX.showWait();

        let formData = new FormData(e.target);

        let logFile = await BX.ajax.runComponentAction('wc:debug1c', 'prepareTmpDirectory', {
            mode: 'ajax',
        });

        this.parseLogFile(logFile.data);

        BX.ajax.runComponentAction('wc:debug1c', 'init', {
            mode: 'ajax',
            data: formData,
            signedParameters: this.params.signedParameters,
        });
    }

    async parseLogFile(logFile) {
        await new Promise(r => setTimeout(r, 500));

        BX.ajax({
            url: `${logFile}`,
            dataType: 'html',
            cache: false,
            onsuccess: (response) => {
                BX.adjust(this.log, {html: response});
                if (response.search('done') === -1) {
                    this.parseLogFile(logFile);
                } else {
                    BX.closeWait();
                }
            }
        });
    }
}
