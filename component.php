<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

if (empty($arParams["PERSON_TYPE_ID"])) {
    ShowError(GetMessage("PERSON_TYPE_IS_NOT_SET"));
    return;
}

CModule::IncludeModule("sale");
CModule::IncludeModule("user");

global $USER;
if (!$USER->IsAuthorized()) {
    if (!empty($arParams["ANONYMOUS_USER_ID"])) {
        $USER_ID = intval($arParams["ANONYMOUS_USER_ID"]);
    }
} else {
    $cUser = new CUser;
    $USER_ID = $cUser->GetID();
}
if (isset($USER_ID)) {
    $dbUsers = CUser::GetByID($USER_ID);
    $arResult["USER"] = $dbUsers->Fetch();
}
if (empty($arResult["USER"])) {
    $APPLICATION->AuthForm(GetMessage("SALE_ACCESS_DENIED"));
}

/* get basket items, this code is copied from sale.order.ajax */
$arUserResult = array();
$arElementId = array();
$arSku2Parent = array();
$arSetParentWeight = array();
$DISCOUNT_PRICE_ALL = 0;
$arUserResult["MAX_DIMENSIONS"] = $arUserResult["ITEMS_DIMENSIONS"] = array();
CSaleBasket::UpdateBasketPrices(CSaleBasket::GetBasketUserID(), $arParams["SITE_ID"]);
$arSelFields = array("ID", "CALLBACK_FUNC", "MODULE", "PRODUCT_ID", "QUANTITY", "DELAY",
    "CAN_BUY", "PRICE", "WEIGHT", "NAME", "CURRENCY", "CATALOG_XML_ID", "VAT_RATE",
    "NOTES", "DISCOUNT_PRICE", "PRODUCT_PROVIDER_CLASS", "DIMENSIONS", "TYPE", "SET_PARENT_ID", "DETAIL_PAGE_URL"
);
$dbBasketItems = CSaleBasket::GetList(
    array("ID" => "ASC"),
    array(
        "FUSER_ID" => CSaleBasket::GetBasketUserID(),
        "LID" => $arParams["SITE_ID"],
        "ORDER_ID" => "NULL"
    ),
    false,
    false,
    $arSelFields
);
$mailBasketList = "";
$baseCurrency = CCurrency::GetBaseCurrency();
while ($arItem = $dbBasketItems->GetNext()) {
    if ($arItem["DELAY"] == "N" && $arItem["CAN_BUY"] == "Y") {
        $arItem["BASE_PRICE"] = $arItem["PRICE"] = roundEx($arItem["PRICE"], SALE_VALUE_PRECISION);
        $arItem["QUANTITY"] = DoubleVal($arItem["QUANTITY"]);

        $arItem["WEIGHT"] = DoubleVal($arItem["WEIGHT"]);
        $arItem["VAT_RATE"] = DoubleVal($arItem["VAT_RATE"]);

        $arDim = $arItem["DIMENSIONS"] = $arItem["~DIMENSIONS"];

        if (is_array($arDim)) {
            $arItem["DIMENSIONS"] = $arDim;
            unset($arItem["~DIMENSIONS"]);

            $arUserResult["MAX_DIMENSIONS"] = CSaleDeliveryHelper::getMaxDimensions(
                array(
                    $arDim["WIDTH"],
                    $arDim["HEIGHT"],
                    $arDim["LENGTH"]
                ),
                $arUserResult["MAX_DIMENSIONS"]);

            $arUserResult["ITEMS_DIMENSIONS"][] = $arDim;
        }

        if ($arItem["VAT_RATE"] > 0 && !CSaleBasketHelper::isSetItem($arItem)) {
            $arUserResult["bUsingVat"] = "Y";
            if ($arItem["VAT_RATE"] > $arUserResult["VAT_RATE"]) {
                $arUserResult["VAT_RATE"] = $arItem["VAT_RATE"];
            }
            $divisor = $arItem["VAT_RATE"] + 1;
            $v = ($divisor != 0) ? ($arItem["PRICE"] * $arItem["QUANTITY"] / $divisor) : 0;
            $v = roundEx(($v * $arItem["VAT_RATE"]), SALE_VALUE_PRECISION);
            $arItem["VAT_VALUE"] = roundEx($v / $arItem["QUANTITY"], SALE_VALUE_PRECISION);
            $arUserResult["VAT_SUM"] += $v;
            unset($divisor, $v);
        }
        $arItem["PRICE_FORMATED"] = SaleFormatCurrency($arItem["PRICE"], $arItem["CURRENCY"]);
        $v = ($arUserResult["WEIGHT_KOEF"] != 0) ? DoubleVal($arItem["WEIGHT"] / $arUserResult["WEIGHT_KOEF"]) : 0;
        $arItem["WEIGHT_FORMATED"] = roundEx($v, SALE_WEIGHT_PRECISION) . " " . $arUserResult["WEIGHT_UNIT"];
        unset($v);

        if ($arItem["DISCOUNT_PRICE"] > 0) {
            $arItem["DISCOUNT_PRICE_PERCENT"] = (($arItem["DISCOUNT_PRICE"] + $arItem["PRICE"]) != 0) ?
                $arItem["DISCOUNT_PRICE"] * 100 / ($arItem["DISCOUNT_PRICE"] + $arItem["PRICE"]) : 0;
            $arItem["DISCOUNT_PRICE_PERCENT_FORMATED"] = roundEx($arItem["DISCOUNT_PRICE_PERCENT"], 0) . "%";
        }

        $arItem["PROPS"] = array();
        $dbProp = CSaleBasket::GetPropsList(
            array("SORT" => "ASC", "ID" => "ASC"),
            array("BASKET_ID" => $arItem["ID"], "!CODE" => array("CATALOG.XML_ID", "PRODUCT.XML_ID"))
        );
        while ($arProp = $dbProp->GetNext()) {
            if (array_key_exists('BASKET_ID', $arProp)) {
                unset($arProp['BASKET_ID']);
            }
            if (array_key_exists('~BASKET_ID', $arProp)) {
                unset($arProp['~BASKET_ID']);
            }

            $arProp = array_filter($arProp, array("CSaleBasketHelper", "filterFields"));

            $arItem["PROPS"][] = $arProp;
        }

        if (!CSaleBasketHelper::isSetItem($arItem)) {
            $DISCOUNT_PRICE_ALL += $arItem["DISCOUNT_PRICE"] * $arItem["QUANTITY"];
            $arItem["DISCOUNT_PRICE"] = roundEx($arItem["DISCOUNT_PRICE"], SALE_VALUE_PRECISION);
            $arUserResult["PRODUCTS_PRICE"] += $arItem["PRICE"] * $arItem["QUANTITY"];
        }

        if (!CSaleBasketHelper::isSetItem($arItem)) {
            $arUserResult["ORDER_WEIGHT"] += $arItem["WEIGHT"] * $arItem["QUANTITY"];
        }

        if (CSaleBasketHelper::isSetItem($arItem))
            $arSetParentWeight[$arItem["SET_PARENT_ID"]] += $arItem["WEIGHT"] * $arItem['QUANTITY'];

        $arUserResult["BASKET_ITEMS"][] = $arItem;
        $mailBasketList .= $arItem['NAME'] . ' ' . $arItem['QUANTITY'] . ' шт. x ' . round((floatval($arItem['PRICE']) * floatval($arItem['QUANTITY'])), 2) . ' руб.</br>';
    }

    $arUserResult["PRICE_WITHOUT_DISCOUNT"] = SaleFormatCurrency($arUserResult["PRODUCTS_PRICE"] + $DISCOUNT_PRICE_ALL, $allCurrency);

    // count weight for set parent products
    if (!empty($arUserResult["BASKET_ITEMS"])) {
        foreach ($arUserResult["BASKET_ITEMS"] as &$arItem) {
            if (CSaleBasketHelper::isSetParent($arItem)) {
                $arItem["WEIGHT"] = $arSetParentWeight[$arItem["ID"]] / $arItem["QUANTITY"];
                $v = ($arUserResult["WEIGHT_KOEF"] != 0) ? doubleval($arItem["WEIGHT"] / $arUserResult["WEIGHT_KOEF"]) : 0;
                $arItem["WEIGHT_FORMATED"] = roundEx($v, SALE_WEIGHT_PRECISION) . " " . $arUserResult["WEIGHT_UNIT"];
                unset($v);
            }
        }
    }

    $v = ($arUserResult["WEIGHT_KOEF"] != 0) ?
        DoubleVal($arUserResult["ORDER_WEIGHT"] / $arUserResult["WEIGHT_KOEF"]) : 0;
    $arUserResult["ORDER_WEIGHT_FORMATED"] = roundEx($v, SALE_WEIGHT_PRECISION) . " " . $arUserResult["WEIGHT_UNIT"];
    unset($v);
    $arUserResult["PRODUCTS_PRICE_FORMATED"] = SaleFormatCurrency($arUserResult["PRODUCTS_PRICE"], $baseCurrency);
    $arUserResult["VAT_SUM_FORMATED"] = SaleFormatCurrency($arUserResult["VAT_SUM"], $baseCurrency);

    $arElementId[] = $arItem["PRODUCT_ID"];

    if ($bUseCatalog) {
        $arParent = CCatalogSku::GetProductInfo($arItem["PRODUCT_ID"]);
        if ($arParent) {
            $arElementId[] = $arParent["ID"];
            $arSku2Parent[$arItem["PRODUCT_ID"]] = $arParent["ID"];
        }
    }
    unset($arItem);
}

if (!empty($arUserResult["BASKET_ITEMS"])) {
    foreach ($arUserResult["BASKET_ITEMS"] as &$arResultItem) {
        $productId = $arResultItem["PRODUCT_ID"];
        $arParent = CCatalogSku::GetProductInfo($productId);
        if ((int)$arProductData[$productId]["PREVIEW_PICTURE"] <= 0
            && (int)$arProductData[$productId]["DETAIL_PICTURE"] <= 0
            && $arParent) {
            $productId = $arParent["ID"];
        }

        if ((int)$arProductData[$productId]["PREVIEW_PICTURE"] > 0)
            $arResultItem["PREVIEW_PICTURE"] = $arProductData[$productId]["PREVIEW_PICTURE"];
        if ((int)$arProductData[$productId]["DETAIL_PICTURE"] > 0)
            $arResultItem["DETAIL_PICTURE"] = $arProductData[$productId]["DETAIL_PICTURE"];
        if ($arProductData[$productId]["PREVIEW_TEXT"] != '')
            $arResultItem["PREVIEW_TEXT"] = $arProductData[$productId]["PREVIEW_TEXT"];

        if (!empty($arProductData[$arResultItem["PRODUCT_ID"]])) {
            foreach ($arProductData[$arResultItem["PRODUCT_ID"]] as $key => $value) {
                if (strpos($key, "PROPERTY_") !== false) {
                    $arResultItem[$key] = $value;
                }
            }
        }

        // if sku element doesn't have some property value - we'll show parent element value instead
        if (array_key_exists($arResultItem["PRODUCT_ID"], $arSku2Parent) && !empty($arCustomSelectFields)) {
            foreach ($arCustomSelectFields as $field) {
                $fieldVal = $field . "_VALUE";
                $parentId = $arSku2Parent[$arResultItem["PRODUCT_ID"]];

                if ((!isset($arResultItem[$fieldVal]) || (isset($arResultItem[$fieldVal]) && strlen($arResultItem[$fieldVal]) == 0))
                    && (isset($arProductData[$parentId][$fieldVal]) && !empty($arProductData[$parentId][$fieldVal]))) // can be array or string
                {
                    $arResultItem[$fieldVal] = $arProductData[$parentId][$fieldVal];
                }
            }
        }

        $arResultItem["PREVIEW_PICTURE_SRC"] = "";
        if (isset($arResultItem["PREVIEW_PICTURE"]) && intval($arResultItem["PREVIEW_PICTURE"]) > 0) {
            $arImage = CFile::GetFileArray($arResultItem["PREVIEW_PICTURE"]);
            if ($arImage) {
                $arFileTmp = CFile::ResizeImageGet(
                    $arImage,
                    array("width" => "110", "height" => "110"),
                    BX_RESIZE_IMAGE_PROPORTIONAL,
                    true
                );
                $arResultItem["PREVIEW_PICTURE_SRC"] = $arFileTmp["src"];
            }
        }

        $arResultItem["DETAIL_PICTURE_SRC"] = "";
        if (isset($arResultItem["DETAIL_PICTURE"]) && intval($arResultItem["DETAIL_PICTURE"]) > 0) {
            $arImage = CFile::GetFileArray($arResultItem["DETAIL_PICTURE"]);
            if ($arImage) {
                $arFileTmp = CFile::ResizeImageGet(
                    $arImage,
                    array("width" => "110", "height" => "110"),
                    BX_RESIZE_IMAGE_PROPORTIONAL,
                    true
                );
                $arResultItem["DETAIL_PICTURE_SRC"] = $arFileTmp["src"];
            }
        }
    }
}
if (isset($arResultItem))
    unset($arResultItem);
/* / the basket assembly; copied from sale.order.ajax */


if (floatval($arUserResult["PRODUCTS_PRICE"]) == 0) {
    if ($arParams["BASKET_PAGE"] !== "") {
        LocalRedirect($arParams["BASKET_PAGE"]);
    } else {
        ShowError(GetMessage("EMPTY_CART"));
        return;
    }
}
$arResult["BASKET"] = $arUserResult;
$arResult["PRICES"] = array();
$arParams["FORM_ACTION"] = $APPLICATION->GetCurPage();

/* get delivery */
$dbDelivery = CSaleDelivery::GetList(array("SORT" => "ASC"), array("ACTIVE" => "Y", "LID" => $arParams["SITE_ID"]), false, false, array("*"));
$arResult["DELIVERY"] = array();
while ($arDelivery = $dbDelivery->Fetch()) {
    if ((intval($_POST[$arParams["FORM_NAME"]]["DELIVERY"]) == $arDelivery["ID"]) || (!isset($_POST[$arParams["FORM_NAME"]]["DELIVERY"]) && empty($arResult["DELIVERY"]))) {
        $arResult["DELIVERY"][$arDelivery["ID"]] = array_merge($arDelivery, array("CHECKED" => "Y"));
        $arResult["PRICES"]["DELIVERY_PRICE"] = $arDelivery["PRICE"];
    } else {
        $arResult["DELIVERY"][$arDelivery["ID"]] = $arDelivery;
    }
}
/* / get delivery */

$arResult["PRICES"]["DELIVERY_PRICE"] = (isset($arResult["PRICES"]["DELIVERY_PRICE"])) ? $arResult["PRICES"]["DELIVERY_PRICE"] : 0;
$arResult["PRICES"]["PRODUCTS_PRICE"] = $arResult["BASKET"]["PRODUCTS_PRICE"];
$arResult["PRICES"]["PRODUCTS_PRICE_FORMATED"] = $arResult["BASKET"]["PRODUCTS_PRICE_FORMATED"];
$arResult["PRICES"]["DELIVERY_PRICE_FORMATED"] = SaleFormatCurrency($arResult["PRICES"]["DELIVERY_PRICE"], $baseCurrency);
$arResult["PRICES"]["TOTAL_PRICE"] = $arResult["PRICES"]["DELIVERY_PRICE"] + $arResult["BASKET"]["PRODUCTS_PRICE"];
$arResult["PRICES"]["TOTAL_PRICE_FORMATED"] = SaleFormatCurrency($arResult["PRICES"]["TOTAL_PRICE"], $baseCurrency);

/* get pay systems */
$arResult["PAY_SYSTEM"] = array();
$dbPaySystem = CSalePaySystem::GetList(
    $arOrder = Array("SORT" => "ASC"),
    Array(
        "ACTIVE" => "Y",
        "PERSON_TYPE_ID" => $arParams["PERSON_TYPE_ID"]
    )
);
while ($paySystem = $dbPaySystem->Fetch()) {
    if ((intval($_POST[$arParams["FORM_NAME"]]["PAY_SYSTEM"]) == $paySystem["ID"]) ||
        (!isset($_POST[$arParams["FORM_NAME"]]["PAY_SYSTEM"]) && empty($arResult["PAY_SYSTEM"]))) {
        $arResult["PAY_SYSTEM"][$paySystem["ID"]] = array_merge($paySystem, array("CHECKED" => "Y"));
    } else {
        $arResult["PAY_SYSTEM"][$paySystem["ID"]] = $paySystem;
    }
}
/* / get pay systems */

/* get order properties */
$dbOrderProps = CSaleOrderProps::GetList(
    array("SORT" => "ASC", "ID" => "ASC"),
    array(
        "ID" => $arParams["ORDER_PROPS"],
    ),
    false,
    false,
    array()
);
while ($arOrderProps = $dbOrderProps->Fetch()) {
    $arResult["ORDER_PROPS"][] = $arOrderProps;
    if ($arOrderProps["ID"] == $arParams["FIO_PROPERTY"]) {
        $FIO_PROPERTY_CODE = $arOrderProps["CODE"];
    }
    if ($arOrderProps["ID"] == $arParams["PHONE_PROPERTY"]) {
        $PHONE_PROPERTY_CODE = $arOrderProps["CODE"];
    }
    if ($arOrderProps["ID"] == $arParams["EMAIL_PROPERTY"]) {
        $EMAIL_PROPERTY_CODE = $arOrderProps["CODE"];
    }
}
/* / get order properties */

/* get a list of available dates for order */
if ($arParams["USE_DATE_CALCULATION"] == "Y") {
    $arParams['DATE_FORMAT'] = ($arParams['DATE_FORMAT'] !== "") ? $arParams['DATE_FORMAT'] : "d.m.Y";
    $dateIterator = 0;
    if (!empty($arParams['CLOSING_TIME'])) {
        $today = new DateTime();
        $closed = new DateTime();
        $arClosedTime = explode(":", $arParams['CLOSING_TIME']);
        if (isset($arClosedTime[1])) {
            $closed->setTime($arClosedTime[0], $arClosedTime[1]);
            if ($today > $closed) {
                $dateIterator++;
            }
        }
        unset($today, $closed, $arClosedTime);
    }
    while ($dateIterator <= intval($arParams['DATES_INTERVAL'])) {
        $timestamp = mktime(0, 0, 0) + 86400 * intval($dateIterator);
        $obDateTime = \Bitrix\Main\Type\DateTime::createFromTimestamp($timestamp);
        if (!in_array($obDateTime->format("d.m.Y"), $arParams['PROHIBITED_DATES']) &&
            $arParams['WEEKEND_DAY_' . $obDateTime->format("w")] != "Y") {
            $arResult['AVAILABLE_DATES'][] = array(
                "DATE" => $obDateTime,
                "DATE_FORMATTED" => $obDateTime->format($arParams['DATE_FORMAT'])
            );
        }
        $dateIterator++;
    }
}
/* / get a list of available dates for order*/

if ($arParams['USER_CONSENT'] == 'Y' && !empty($arResult["ORDER_PROPS"])) {
    foreach ($arResult["ORDER_PROPS"] as $key => $arProp) {
        $arResult['USER_CONSENT_FIELDS'][] = $arProp["NAME"];
    }
}

if (isset($_POST[$arParams["FORM_NAME"]])) {
    if ($_POST[$arParams["ENABLE_VALIDATION_INPUT_NAME"]] == "N") {
        $arResult["HIDE_ERRORS"] = "Y";
    }

    /* get current values */
    if (!empty($arResult["ORDER_PROPS"])) {
        foreach ($arResult["ORDER_PROPS"] as $key => $arProp) {
            $arResult["CURRENT_VALUES"]["ORDER_PROPS"][$arProp["CODE"]] = htmlspecialcharsbx($_POST[$arParams["FORM_NAME"]][$arProp["CODE"]]);
            $arUserResult["ORDER_PROP"][$arProp["ID"]] = $arResult["CURRENT_VALUES"]["ORDER_PROPS"][$arProp["CODE"]];
        }
    }
    $arResult["CURRENT_VALUES"]["PAY_SYSTEM"] = intval($_POST[$arParams["FORM_NAME"]]["PAY_SYSTEM"]);
    $arResult["CURRENT_VALUES"]["DELIVERY"] = intval($_POST[$arParams["FORM_NAME"]]["DELIVERY"]);
    $arResult["CURRENT_VALUES"]["USER_COMMENT"] = htmlspecialcharsbx($_POST[$arParams["FORM_NAME"]]["USER_COMMENT"]);
    /* / get current values */

    /* properties validation */
    $arResult["ERRORS"] = array();
    $arResult["ERRORS_FIELDS"] = array();
    if (!empty($arResult["ORDER_PROPS"])) {
        foreach ($arResult["ORDER_PROPS"] as $key => $arProp) {
            if (($arResult["CURRENT_VALUES"]["ORDER_PROPS"][$arProp["CODE"]] == "") &&
                in_array($arProp["ID"], $arParams["REQUIRED_ORDER_PROPS"])) {
                $arResult["ERRORS"][] = GetMessage("REQUIRED_FIELD") . "\"" . $arProp["NAME"] . "\"";
                $arResult["ERRORS_FIELDS"][] = $arProp["CODE"];
            }
        }
    }
    /* / properties validation */

    if (!array_key_exists($arResult["CURRENT_VALUES"]["PAY_SYSTEM"], $arResult["PAY_SYSTEM"])) {
        $arResult["ERRORS"][] = GetMessage("PAY_SYSTEM_ERROR");
    }
    if (!array_key_exists($arResult["CURRENT_VALUES"]["DELIVERY"], $arResult["DELIVERY"])) {
        $arResult["ERRORS"][] = GetMessage("DELIVERY_ERROR");
    }

    $PERSON_TYPE_ID = $arParams['PERSON_TYPE_ID'];

    if (empty($arResult["ERRORS"]) && $arResult["HIDE_ERRORS"] != "Y") {
        $arErrors = array();
        $arWarnings = array();

        $arOrderDat = CSaleOrder::DoCalculateOrder(
            $arParams["SITE_ID"],
            $USER_ID,
            $arUserResult["BASKET_ITEMS"],
            $PERSON_TYPE_ID,
            $arUserResult["ORDER_PROP"],
            $arResult["CURRENT_VALUES"]["DELIVERY"],
            $arResult["CURRENT_VALUES"]["PAY_SYSTEM"],
            array(),
            $arErrors,
            $arWarnings
        );

        $arFields = array(
            "LID" => $arParams["SITE_ID"],
            "PERSON_TYPE_ID" => $PERSON_TYPE_ID,
            "PAYED" => "N",
            "CANCELED" => "N",
            "STATUS_ID" => "N",
            "PRICE" => $arResult["PRICES"]["TOTAL_PRICE"],
            "PRICE_DELIVERY" => $arResult["PRICES"]["DELIVERY_PRICE"],
            "USER_ID" => (int)$USER_ID,
            "PAY_SYSTEM_ID" => $arResult["CURRENT_VALUES"]["PAY_SYSTEM"],
            "DELIVERY_ID" => $arResult["CURRENT_VALUES"]["DELIVERY"],
            "USER_DESCRIPTION" => $arResult["CURRENT_VALUES"]["USER_COMMENT"],
            "CURRENCY" => $baseCurrency,
            "DISCOUNT_VALUE" => $arOrderDat["DISCOUNT_PRICE"]
        );

        $arResult["ORDER_ID"] = (int)CSaleOrder::DoSaveOrder($arOrderDat, $arFields, 0, $arErrors);

        /* events sending */
        if (!empty($arParams["EVENT_TYPES"]) &&
            $USER->IsAuthorized() &&
            (!empty($arResult["USER"]["EMAIL"]) ||
                isset($EMAIL_PROPERTY_CODE) &&
                !empty($arResult["CURRENT_VALUES"]["ORDER_PROPS"][$EMAIL_PROPERTY_CODE])
            )) {
            $mailContactList = "";
            $mailUserName = "";
            if ($arParams["PHONE_PROPERTY"] != "" && isset($arUserResult["ORDER_PROP"][$arParams["PHONE_PROPERTY"]])) {
                $mailContactList .= GetMessage("PHONE_PROPERTY") .
                    $arUserResult["ORDER_PROP"][$arParams["PHONE_PROPERTY"]] . '<br/>';
            }
            if ($arParams["DATE_PROPERTY"] != "" && isset($arUserResult["ORDER_PROP"][$arParams["DATE_PROPERTY"]])) {
                $mailContactList .= GetMessage("DATE_PROPERTY") .
                    $arUserResult["ORDER_PROP"][$arParams["DATE_PROPERTY"]] . '<br/>';
            }
            if ($arParams["EMAIL_PROPERTY"] != "" && isset($arUserResult["ORDER_PROP"][$arParams["EMAIL_PROPERTY"]])) {
                $mailContactList .= GetMessage("EMAIL_PROPERTY") .
                    $arUserResult["ORDER_PROP"][$arParams["EMAIL_PROPERTY"]] . '<br/>';
            }
            if ($arParams["FIO_PROPERTY"] != "" && isset($arUserResult["ORDER_PROP"][$arParams["FIO_PROPERTY"]])) {
                $mailUserName = $arUserResult["ORDER_PROP"][$arParams["FIO_PROPERTY"]];
            }
            $arEventFieldsUs = array(
                "ORDER_ID" => $arResult["ORDER_ID"],
                "PRICE" => $arFields["PRICE"],
                "PRICE_DELIVERY" => $arFields["PRICE_DELIVERY"],
                "ORDER_LIST" => $mailBasketList,
                "EMAIL" => (
                    isset($EMAIL_PROPERTY_CODE) &&
                    !empty($arResult["CURRENT_VALUES"]["ORDER_PROPS"][$EMAIL_PROPERTY_CODE])
                ) ? $arResult["CURRENT_VALUES"]["ORDER_PROPS"][$EMAIL_PROPERTY_CODE] : $arResult["USER"]["EMAIL"],
                "ORDER_USER" => $mailUserName,
                "ORDER_DATE" => date('d.m.Y'),
                "CONTACT_LIST" => $mailContactList,
                "SALE_EMAIL" => COption::GetOptionString("sale", "order_email", "")
            );
            if (!empty($arParams["EVENT_TYPES"])) {
                foreach ($arParams["EVENT_TYPES"] as $eventTypeID) {
                    CEvent::Send($eventTypeID, $arParams["SITE_ID"], $arEventFieldsUs);
                }
            }
        }
        /* / events sending */

        if ($arParams["ORDER_RESULT_PAGE"] !== "") {
            LocalRedirect($arParams["ORDER_RESULT_PAGE"] . "?ORDER_ID=" . $arResult["ORDER_ID"], true);
        } else {
            $arResult["ORDER_SUCCESSFULLY_CREATED"] = "Y";
        }
    }
} else {
    if ($arParams["SET_DEFAULT_PROPERTIES_VALUES"] == "Y" && $USER->IsAuthorized() && isset($arResult["USER"])) {
        if (isset($FIO_PROPERTY_CODE)) {
            $fullName = $arResult["USER"]["LAST_NAME"];
            $fullName .= (($fullName == "") ? "" : " ") . $arResult["USER"]["NAME"];
            $fullName .= (($fullName == "") ? "" : " ") . $arResult["USER"]["SECOND_NAME"];
            $arResult["CURRENT_VALUES"]["ORDER_PROPS"][$FIO_PROPERTY_CODE] = $fullName;
        }
        if (isset($PHONE_PROPERTY_CODE)) {
            $arResult["CURRENT_VALUES"]["ORDER_PROPS"][$PHONE_PROPERTY_CODE] = $arResult["USER"]["PERSONAL_PHONE"];
        }
        if (isset($EMAIL_PROPERTY_CODE)) {
            $arResult["CURRENT_VALUES"]["ORDER_PROPS"][$EMAIL_PROPERTY_CODE] = $arResult["USER"]["EMAIL"];
        }
    }
}

$this->IncludeComponentTemplate();