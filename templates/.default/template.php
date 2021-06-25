<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}
$this->setFrameMode(false);

if ($arResult['USER_INFO']['IS_AUTHORIZED']) { ?>
    <table id="debug1c" class="debug1c">
        <tr>
            <td>
                <? echo "[{$arResult['USER_INFO']['ID']}] {$arResult['USER_INFO']['NAME']} {$arResult['USER_INFO']['LAST_NAME']} {$arResult['USER_INFO']['EMAIL']} "; ?>
                <a href="<?= $APPLICATION->GetCurPageParam("logout=yes&" . bitrix_sessid_get(), ['logout']) ?>"><?= GetMessage('WC_DEBUG1C_LOGOUT') ?></a>
                <hr>
            </td>
        </tr>
        <tr>
            <td>
                <form action="" method="post" name="debug">
                    <table>
                        <tr>
                            <td>
                                <label for="dir"></label>
                            </td>
                            <td>
                                <select id="dir" name="DIR">
                                    <option value="<?= GetMessage('WC_DEBUG1C_DIR_BITRIX') ?>" selected>
                                        <?= GetMessage('WC_DEBUG1C_DIR_BITRIX') ?>
                                    </option>
                                    <option value="<?= GetMessage('WC_DEBUG1C_DIR_LOCAL') ?>">
                                        <?= GetMessage('WC_DEBUG1C_DIR_LOCAL') ?>
                                    </option>
                                </select>
                                <?= GetMessage('WC_DEBUG1C_URL') ?>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <input type="radio" name="TYPE_MODE" id="catalog-import"
                                       value='{"type":"catalog","mode":"import"}'>
                            </td>
                            <td>
                                <label for="catalog-import"><?= GetMessage('WC_DEBUG1C_CATALOG_IMPORT') ?></label>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <input type="radio" name="TYPE_MODE" id="sale-import"
                                       value='{"type":"sale","mode":"import"}'>
                            </td>
                            <td>
                                <label for="sale-import"><?= GetMessage('WC_DEBUG1C_SALE_IMPORT') ?></label>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <input type="radio" name="TYPE_MODE" id="highloadblock-import"
                                       value='{"type":"reference"}'>
                            </td>
                            <td>
                                <label for="highloadblock-import"><?= GetMessage('WC_DEBUG1C_HIGHLOADBLOCK_IMPORT') ?></label>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <input type="radio" name="TYPE_MODE" id="exchange-order"
                                       value='{"type":"sale","mode":"exchange-order"}'>
                            </td>
                            <td>
                                <label for="exchange-order"><?= GetMessage('WC_DEBUG1C_EXCHANGE_ORDER') ?></label>
                                <label>
                                    <input type="text" name="exchange-order-id"
                                           placeholder="<?= GetMessage('WC_DEBUG1C_ORDER_ID') ?>">
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <input type="radio" name="TYPE_MODE" id="sale-query"
                                       value='{"type":"sale","mode":"query"}'>
                            </td>
                            <td>
                                <label for="sale-query"><?= GetMessage('WC_DEBUG1C_SALE_QUERY') ?></label>
                                <label>
                                    <input type="text" name="QUERY_ORDER_ID"
                                           placeholder="<?= GetMessage('WC_DEBUG1C_SALE_QUERY_ORDER_ID') ?>">
                                </label>
                                Ver.
                                <label>
                                    <select name="VERSION">
                                        <option value="2.05">2.05</option>
                                        <option value="2.09" selected>2.09</option>
                                        <option value="2.10">2.10</option>
                                        <option value="3.1">3.1</option>
                                    </select>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <input type="radio" name="TYPE_MODE" id="sale-info"
                                       value='{"type":"sale","mode":"info"}'>
                            </td>
                            <td>
                                <label for="sale-info"><?= GetMessage('WC_DEBUG1C_SALE_INFO') ?></label>
                            </td>
                        </tr>
                    </table>
                    <button data-action-submit type="submit">
                        <?= GetMessage('WC_DEBUG1C_SUBMIT_BUTTON') ?>
                    </button>
                    <hr>
                </form>
            </td>
        </tr>
        <tr>
            <td>
                <pre data-type="log"></pre>
            </td>
        </tr>
    </table>
<? } else {
    $APPLICATION->IncludeComponent('bitrix:system.auth.authorize', '');
} ?>

<script type="text/javascript">
    BX.ready(() => {
        if (!window.hasOwnProperty('WCDebug1C')) {
            window.WCDebug1C = new WCDebug1C(<?=Bitrix\Main\Web\Json::encode([
                'signedParameters' => $this->getComponent()->getSignedParameters(),
            ])?>);
        }
    });
</script>
