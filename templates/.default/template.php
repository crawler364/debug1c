<div id="wc-debug1c">
    <div>
        <? if ($arResult['USER_INFO']) {
            echo "[{$arResult['USER_INFO']['ID']}] {$arResult['USER_INFO']['NAME']} {$arResult['USER_INFO']['LAST_NAME']} | | {$arResult['USER_INFO']['EMAIL']} | | "; ?>
            <a href="<?= $APPLICATION->GetCurPageParam("logout=yes&" . bitrix_sessid_get(), ['logout']) ?>"><?= GetMessage('WC_DEBUG1C_LOGOUT') ?></a>
        <? } else { ?>
            <? $APPLICATION->IncludeComponent('bitrix:system.auth.authorize', '') ?>
        <? } ?>
    </div>
    <hr>
    <div>
        <form action="" method="post">
            <table>
                <tr>
                    <td>
                        <label for="debug-dir"></label>
                    </td>
                    <td>
                        <select id="debug-dir" name="debug-dir">
                            <option value="<?= GetMessage('WC_DEBUG1C_DIR_BITRIX') ?>"
                                    selected><?= GetMessage('WC_DEBUG1C_DIR_BITRIX') ?></option>
                            <option value="<?= GetMessage('WC_DEBUG1C_DIR_LOCAL') ?>"><?= GetMessage('WC_DEBUG1C_DIR_LOCAL') ?></option>
                        </select>
                        <?= GetMessage('WC_DEBUG1C_URL') ?>
                    </td>
                </tr>
                <? if ($arResult['USER_INFO']['IS_ADMIN']) { ?>
                    <tr>
                        <td>
                            <input type="radio" name="debug-mode" id="catalog-import" value="catalog-import">
                        </td>
                        <td>
                            <label for="catalog-import"><?= GetMessage('WC_DEBUG1C_CATALOG_IMPORT') ?></label>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <input type="radio" name="debug-mode" id="sale-import" value="sale-import">
                        </td>
                        <td>
                            <label for="sale-import"><?= GetMessage('WC_DEBUG1C_SALE_IMPORT') ?></label>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <input type="radio" name="debug-mode" id="highloadblock-import"
                                   value="highloadblock-import">
                        </td>
                        <td>
                            <label for="highloadblock-import"><?= GetMessage('WC_DEBUG1C_HIGHLOADBLOCK_IMPORT') ?></label>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <input type="radio" name="debug-mode" id="exchange-order" value="exchange-order">
                        </td>
                        <td>
                            <label for="exchange-order"><?= GetMessage('WC_DEBUG1C_EXCHANGE_ORDER') ?></label>
                            <label>
                                <input type="text" placeholder="<?= GetMessage('WC_DEBUG1C_ORDER_ID') ?>">
                            </label>
                        </td>
                    </tr>
                <? } ?>
                <tr>
                    <td>
                        <input type="radio" name="debug-mode" id="sale-export" value="sale-export">
                    </td>
                    <td>
                        <label for="sale-export"><?= GetMessage('WC_DEBUG1C_SALE_EXPORT') ?></label>
                        <label>
                            <input type="text" placeholder="<?= GetMessage('WC_DEBUG1C_SALE_EXPORT_ORDER_ID') ?>">
                        </label>
                        Ver.
                        <label>
                            <select>
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
                        <input type="radio" name="debug-mode" id="sale-info" value="sale-info">
                    </td>
                    <td>
                        <label for="sale-info"><?= GetMessage('WC_DEBUG1C_SALE_INFO') ?></label>
                    </td>
                </tr>
            </table>

            <button data-action-submit type="submit"><?= GetMessage('WC_DEBUG1C_SUBMIT_BUTTON') ?></button>
        </form>
    </div>
    <hr>
    <div data-type="data">
        <pre></pre>
    </div>
</div>

<script type="text/javascript">
    if (!window.hasOwnProperty('WCDebug1C')) {
        window.WCDebug1C = new WCDebug1C(<?=Bitrix\Main\Web\Json::encode([
            'test' => $arParams['test'],
        ])?>);
    }
</script>
