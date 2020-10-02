<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

if ($arResult["ORDER_SUCCESSFULLY_CREATED"] == "Y") {
    echo GetMessage("ORDER_SUCCESSFULLY_CREATED");
    return;
}
?>

<script type="text/javascript">
    function submitForm(val) {
        BX('<? echo $arParams["ENABLE_VALIDATION_INPUT_ID"]; ?>').value = (val !== 'Y') ? "N" : "Y";
        var orderForm = BX('<? echo $arParams["FORM_ID"]; ?>');
        BX.submit(orderForm);
        return true;
    }
</script>

<div class="order-simple">
    <form method="post"
          id="<? echo $arParams["FORM_ID"]; ?>"
          name="<? echo $arParams["FORM_NAME"]; ?>"
          action="<? echo $arParams["FORM_ACTION"]; ?>">

        <?= bitrix_sessid_post() ?>

        <input type="hidden"
               name="<? echo $arParams["ENABLE_VALIDATION_INPUT_NAME"]; ?>"
               id="<? echo $arParams["ENABLE_VALIDATION_INPUT_ID"]; ?>"
               value="Y">

        <? if (is_array($arResult["ERRORS"]) && $arResult["HIDE_ERRORS"] != "Y") { ?>
            <div class="order-simple__block">
                <? foreach ($arResult["ERRORS"] as $error) { ?>
                    <div class="order-simple__error">
                        <? echo $error; ?>
                    </div>
                <? } ?>
            </div>
        <? } ?>

        <? if (!empty($arResult["ORDER_PROPS"])) { ?>
            <div class="order-simple__block">
                <div class="order-simple__block__title"><? echo GetMessage("ORDER_PROPS"); ?></div>
                <?
                foreach ($arResult["ORDER_PROPS"] as $arProp) { ?>
                    <div class="order-simple__field">
                        <label for="<? echo $arParams["FORM_NAME"] ?>_<?= $arProp["CODE"] ?>">
                        <span class="order-simple__field__title">
                            <?= $arProp["NAME"] ?>
                            <? if (in_array($arProp["ID"], $arParams["REQUIRED_ORDER_PROPS"])) { ?>*<? } ?>
                        </span>
                            <? if (
                                $arParams["USE_DATE_CALCULATION"] == "Y" &&
                                $arProp["ID"] == $arParams["DATE_PROPERTY"]
                            ) { ?>
                                <select name="<? echo $arParams["FORM_NAME"] ?>[<?= $arProp["CODE"] ?>]"
                                        id="date"
                                        autocomplete="off">
                                    <? foreach ($arResult['AVAILABLE_DATES'] as $date) { ?>
                                        <option
                                            <? if ($arResult["CURRENT_VALUES"]["ORDER_PROPS"]["DATE"] == $date){ ?>selected<? } ?>
                                            value="<?= $date["DATE_FORMATTED"] ?>">
                                            <?= $date["DATE_FORMATTED"] ?>
                                        </option>
                                    <? } ?>
                                </select>
                            <? } else { ?>
                                <input id="<? echo $arParams["FORM_NAME"] ?>_<?= $arProp["CODE"] ?>"
                                       value="<? echo $arResult["CURRENT_VALUES"]["ORDER_PROPS"][$arProp["CODE"]]; ?>"
                                       name="<? echo $arParams["FORM_NAME"] ?>[<?= $arProp["CODE"] ?>]"
                                       type="text"/>
                            <? } ?>
                        </label>
                    </div>
                <? } ?>
            </div>
        <? } ?>

        <? if (!empty($arResult["DELIVERY"])) { ?>
            <div class="order-simple__block">
                <div class="order-simple__block__title"><? echo GetMessage("DELIVERY"); ?></div>
                <?
                foreach ($arResult["DELIVERY"] as $arDelivery) { ?>
                    <div class="order-simple__field">
                        <label for="delivery_<?= $arDelivery["ID"] ?>">
                            <input
                                    type="radio"
                                    onchange="submitForm(); return false;"
                                    <? if ($arDelivery["CHECKED"] == "Y"){ ?>checked<? } ?>
                                    id="delivery_<?= $arDelivery["ID"] ?>"
                                    name="<? echo $arParams["FORM_NAME"] ?>[DELIVERY]"
                                    value="<?= $arDelivery["ID"] ?>"
                                    autocomplete="off"
                            />
                            <?= $arDelivery["NAME"] ?>
                        </label>
                    </div>
                <? } ?>
            </div>
        <? } ?>

        <? if ($arResult["PAY_SYSTEM"]) { ?>
            <div class="order-simple__block">
                <div class="order-simple__block__title"><? echo GetMessage("PAY_SYSTEM"); ?></div>
                <?
                foreach ($arResult["PAY_SYSTEM"] as $arPaySystem) { ?>
                    <div class="order-simple__field">
                        <label for="pay_system_<?= $arPaySystem["ID"] ?>">
                            <input type="radio"
                                   onchange="submitForm(); return false;"
                                   <? if ($arPaySystem["CHECKED"] == "Y"){ ?>checked<? } ?>
                                   id="pay_system_<?= $arPaySystem["ID"] ?>"
                                   name="<? echo $arParams["FORM_NAME"] ?>[PAY_SYSTEM]"
                                   value="<?= $arPaySystem["ID"] ?>"
                                   autocomplete="off"
                            />
                            <?= $arPaySystem["NAME"] ?>
                        </label>
                    </div>
                <? } ?>
            </div>
        <? } ?>

        <div class="order-simple__block">
            <div class="order-simple__block__title"><? echo GetMessage("COMMENT"); ?></div>
            <textarea
                    name="<? echo $arParams["FORM_NAME"] ?>[USER_COMMENT]"
                    id="comment"
            ><? echo $arResult["CURRENT_VALUES"]["ORDER_PROPS"]["USER_COMMENT"]; ?></textarea>
        </div>

        <div class="order-simple__block">
            <table class="order-simple__price-table">
                <tr>
                    <td>
                        <? echo GetMessage("ORDER_PRICE"); ?>
                    </td>
                    <td><? echo $arResult["PRICES"]["PRODUCTS_PRICE_FORMATTED"]; ?></td>
                </tr>
                <tr>
                    <td>
                        <? echo GetMessage("DELIVERY_PRICE"); ?>
                    </td>
                    <td><? echo $arResult["PRICES"]["DELIVERY_PRICE_FORMATTED"]; ?></td>
                </tr>
                <tr>
                    <td>
                        <? echo GetMessage("TOTAL_PRICE"); ?>
                    </td>
                    <td><? echo $arResult["PRICES"]["TOTAL_PRICE_FORMATTED"]; ?></td>
                </tr>
            </table>
        </div>

        <div class="order-simple__block">
            <? if ($arParams['USER_CONSENT'] == 'Y' && $arParams["AJAX_MODE"] != "Y") {
                $APPLICATION->IncludeComponent(
                    "bitrix:main.userconsent.request",
                    "",
                    array(
                        "ID" => $arParams["USER_CONSENT_ID"],
                        "IS_CHECKED" => $arParams["USER_CONSENT_IS_CHECKED"],
                        "AUTO_SAVE" => "N",
                        "IS_LOADED" => $arParams["USER_CONSENT_IS_LOADED"],
                        "INPUT_NAME" => "order_userconsent",
                        "INPUT_ID" => "order_userconsent",
                        "REPLACE" => array(
                            'button_caption' => "ќформить заказ",
                            'fields' => $arResult['USER_CONSENT_FIELDS']
                        )
                    )
                );
            } ?>
            <button onclick="submitForm('Y'); return false;"><? echo GetMessage("SUBMIT_BUTTON"); ?></button>
        </div>
    </form>
</div>



