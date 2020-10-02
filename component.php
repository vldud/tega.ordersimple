<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

if (empty($arParams["PERSON_TYPE_ID"])) {
    ShowError(\Bitrix\Main\Localization\Loc::getMessage("PERSON_TYPE_IS_NOT_SET"));
    return;
}

\Bitrix\Main\Loader::includeModule("sale");

global $USER, $APPLICATION;
$arResult = [
    "PRICES" => [],
    "BASKET" => [],
    "DELIVERY" => [],
    "PAY_SYSTEM" => [],
    "ORDER_PROPS" => []
];

if (!$USER->IsAuthorized()) {
    if (!empty($arParams["ANONYMOUS_USER_ID"])) {
        $USER_ID = intval($arParams["ANONYMOUS_USER_ID"]);
    }
} else {
    $USER_ID = (new CUser)->GetID();
}
if (isset($USER_ID)) {
    $user = \Bitrix\Main\UserTable::getById($USER_ID);
    $arResult["USER"] = $user->Fetch();
}

if (empty($arResult["USER"])) {
    $APPLICATION->AuthForm(\Bitrix\Main\Localization\Loc::getMessage("SALE_ACCESS_DENIED"));
}

$form = $_POST[$arParams["FORM_NAME"]];
$isValidationEnabled = $_POST[$arParams["ENABLE_VALIDATION_INPUT_NAME"]] == "N";
\Bitrix\Sale\DiscountCouponsManager::init();
$order = \Bitrix\Sale\Order::create($arParams["SITE_ID"], $arResult["USER"]["ID"]);
$order->setPersonTypeId($arParams["PERSON_TYPE_ID"]);

/* get basket */
$basket = \Bitrix\Sale\Basket::loadItemsForFUser(
    \Bitrix\Sale\Fuser::getId(),
    $arParams["SITE_ID"]
)->getOrderableItems();
$order->setBasket($basket);
$basketItems = $basket->getBasketItems();
foreach ($basketItems as $basketItem) {
    $arResult["BASKET"][] = $basketItem->getFields()->getValues();
}
/* / get basket */

$shipmentCollection = $order->getShipmentCollection();
$shipment = $shipmentCollection->createItem();
$deliveryList = \Bitrix\Sale\Delivery\Services\Manager::getRestrictedList(
    $shipment,
    \Bitrix\Sale\Services\Base\RestrictionManager::MODE_CLIENT
);
foreach ($deliveryList as $delivery) {
    if (
        (intval($form["DELIVERY"]) == $delivery["ID"]) ||
        (
            !isset($form["DELIVERY"]) &&
            empty($arResult["DELIVERY"])
        )
    ) {
        $selectedDelivery = $delivery["ID"];
    }
    $arResult["DELIVERY"][$delivery["ID"]] = $delivery;
}
if (!isset($selectedDelivery) && !empty($arResult["DELIVERY"])) {
    reset($arResult["DELIVERY"]);
    $selectedDelivery = key($arResult["DELIVERY"]);
}
if (isset($selectedDelivery)) {
    $deliveryService = \Bitrix\Sale\Delivery\Services\Manager::getById($selectedDelivery);
    $arResult["DELIVERY"][$selectedDelivery] = array_merge($arResult["DELIVERY"][$selectedDelivery], ["CHECKED" => "Y"]);
    $shipment->setFields(array(
        'DELIVERY_ID' => $deliveryService['ID'],
        'DELIVERY_NAME' => $deliveryService['NAME'],
    ));
    $shipmentItemCollection = $shipment->getShipmentItemCollection();
    foreach ($order->getBasket() as $item) {
        $shipmentItem = $shipmentItemCollection->createItem($item);
        $shipmentItem->setQuantity($item->getQuantity());
    }
}

$paymentCollection = $order->getPaymentCollection();
$payment = $paymentCollection->createItem();
$paySystemList = \Bitrix\Sale\PaySystem\Manager::getListWithRestrictions(
    $payment,
    \Bitrix\Sale\Services\Base\RestrictionManager::MODE_CLIENT
);
foreach ($paySystemList as $paySystem) {
    if (
        (intval($form["PAY_SYSTEM"]) == $paySystem["ID"]) ||
        (
            !isset($form["PAY_SYSTEM"]) &&
            empty($arResult["PAY_SYSTEM"])
        )
    ) {
        $selectedPaySystem = $paySystem["ID"];
    }
    $arResult["PAY_SYSTEM"][$paySystem["ID"]] = $paySystem;
}
if (!isset($selectedPaySystem) && !empty($arResult["PAY_SYSTEM"])) {
    reset($arResult["PAY_SYSTEM"]);
    $selectedPaySystem = key($arResult["PAY_SYSTEM"]);
}
if (isset($selectedPaySystem)) {
    $paySystemService = \Bitrix\Sale\PaySystem\Manager::getObjectById($selectedPaySystem);
    $arResult["PAY_SYSTEM"][$selectedPaySystem] = array_merge($arResult["PAY_SYSTEM"][$selectedPaySystem], ["CHECKED" => "Y"]);
    $payment->setFields(array(
        'PAY_SYSTEM_ID' => $paySystemService->getField("PAY_SYSTEM_ID"),
        'PAY_SYSTEM_NAME' => $paySystemService->getField("NAME"),
    ));
}

$currency = $order->getCurrency();
$arResult["PRICES"]["TOTAL_PRICE"] = $order->getPrice();
$arResult["PRICES"]["TOTAL_PRICE_FORMATTED"] = SaleFormatCurrency(
    $arResult["PRICES"]["TOTAL_PRICE"],
    $currency
);
$arResult["PRICES"]["DELIVERY_PRICE"] = $order->getDeliveryPrice();
$arResult["PRICES"]["DELIVERY_PRICE_FORMATTED"] = SaleFormatCurrency(
    $arResult["PRICES"]["DELIVERY_PRICE"],
    $currency
);
$arResult["PRICES"]["PRODUCTS_PRICE"] = $arResult["PRICES"]["TOTAL_PRICE"] - $arResult["PRICES"]["DELIVERY_PRICE"];
$arResult["PRICES"]["PRODUCTS_PRICE_FORMATTED"] = SaleFormatCurrency(
    $arResult["PRICES"]["PRODUCTS_PRICE"],
    $currency
);

if (floatval($arResult["PRICES"]["PRODUCTS_PRICE"]) == 0) {
    if ($arParams["BASKET_PAGE"] !== "") {
        LocalRedirect($arParams["BASKET_PAGE"]);
    } else {
        ShowError(\Bitrix\Main\Localization\Loc::getMessage("EMPTY_CART"));
        return;
    }
}

$propertiesFilter = [
    "ID" => $arParams["ORDER_PROPS"],
    "PERSON_TYPE_ID" => $arParams["PERSON_TYPE_ID"]
];

$propertiesRuntime = [];
if (isset($selectedPaySystem)) {
    $propertiesFilter[] = [
        "LOGIC" => "OR",
        [
            "REL_PS.ENTITY_ID" => $selectedPaySystem,
        ],
        [
            "REL_PS.ENTITY_ID" => false
        ]
    ];
    $propertiesRuntime[] = new \Bitrix\Main\Entity\ReferenceField(
        'REL_PS',
        '\Bitrix\Sale\Internals\OrderPropsRelationTable',
        array("=ref.PROPERTY_ID" => "this.ID", "=ref.ENTITY_TYPE" => new \Bitrix\Main\DB\SqlExpression('?', 'P')),
        array("join_type" => "left")
    );
}
if (isset($selectedDelivery)) {
    $propertiesFilter[] = [
        "LOGIC" => "OR",
        [
            "REL_DLV.ENTITY_ID" => $selectedDelivery,
        ],
        [
            "REL_DLV.ENTITY_ID" => false
        ]
    ];
    $propertiesRuntime[] = new \Bitrix\Main\Entity\ReferenceField(
        'REL_DLV',
        '\Bitrix\Sale\Internals\OrderPropsRelationTable',
        array("=this.ID" => "ref.PROPERTY_ID", "=ref.ENTITY_TYPE" => new \Bitrix\Main\DB\SqlExpression('?', 'D')),
        array("join_type" => "left")
    );
}

$properties = \Bitrix\Sale\Property::getList([
    'select' => ['*'],
    'filter' => $propertiesFilter,
    'runtime' => $propertiesRuntime,
    'group' => ['ID'],
    'order' => ['SORT' => 'ASC']
]);
while ($property = $properties->fetch()) {
    $arResult["ORDER_PROPS"][] = $property;
    if ($property["ID"] == $arParams["FIO_PROPERTY"]) {
        $FIO_PROPERTY_CODE = $property["CODE"];
    }
    if ($property["ID"] == $arParams["PHONE_PROPERTY"]) {
        $PHONE_PROPERTY_CODE = $property["CODE"];
    }
    if ($property["ID"] == $arParams["EMAIL_PROPERTY"]) {
        $EMAIL_PROPERTY_CODE = $property["CODE"];
    }
    if ($property["ID"] == $arParams["DATE_PROPERTY"]) {
        $DATE_PROPERTY_CODE = $property["CODE"];
    }
}

/* get a list of available dates */
if ($arParams["USE_DATE_CALCULATION"] == "Y") {
    $arParams['DATE_FORMAT'] = ($arParams['DATE_FORMAT'] !== "") ? $arParams['DATE_FORMAT'] : "d.m.Y";
    $dateIterator = 0;
    if (!empty($arParams['CLOSING_TIME'])) {
        $today = new \DateTime();
        $closed = new \DateTime();
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
/* / get a list of available dates */

if ($arParams['USER_CONSENT'] == 'Y' && !empty($arResult["ORDER_PROPS"])) {
    foreach ($arResult["ORDER_PROPS"] as $key => $arProperty) {
        $arResult['USER_CONSENT_FIELDS'][] = $arProperty["NAME"];
    }
}

if (isset($form)) {
    if ($isValidationEnabled) {
        $arResult["HIDE_ERRORS"] = "Y";
    }
    $order->doFinalAction(true);
    $propertyCollection = $order->getPropertyCollection();

    /* get current values */
    if (!empty($arResult["ORDER_PROPS"])) {
        foreach ($arResult["ORDER_PROPS"] as $key => $arProperty) {
            $arResult["CURRENT_VALUES"]["ORDER_PROPS"][$arProperty["CODE"]] = htmlspecialcharsbx($form[$arProperty["CODE"]]);
            foreach ($propertyCollection as $property) {
                if ($property->getField('CODE') == $arProperty["CODE"]) {
                    $property->setValue($arResult["CURRENT_VALUES"]["ORDER_PROPS"][$arProperty["CODE"]]);
                }
            }
        }
    }
    $arResult["CURRENT_VALUES"]["USER_COMMENT"] = htmlspecialcharsbx($form["USER_COMMENT"]);
    if (strlen($arResult["CURRENT_VALUES"]["USER_COMMENT"]) > 0) {
        $order->setField('USER_DESCRIPTION', $arResult["CURRENT_VALUES"]["USER_COMMENT"]);
    }
    /* / get current values */

    /* validation */
    $arResult["ERRORS"] = array();
    $arResult["ERRORS_FIELDS"] = array();
    if (!empty($arResult["ORDER_PROPS"])) {
        foreach ($arResult["ORDER_PROPS"] as $key => $arProperty) {
            if (
                ($arResult["CURRENT_VALUES"]["ORDER_PROPS"][$arProperty["CODE"]] == "") &&
                in_array($arProperty["ID"], $arParams["REQUIRED_ORDER_PROPS"])
            ) {
                $arResult["ERRORS"][] = \Bitrix\Main\Localization\Loc::getMessage("REQUIRED_FIELD") .
                    "\"" .
                    $arProperty["NAME"] .
                    "\"";
                $arResult["ERRORS_FIELDS"][] = $arProperty["CODE"];
            }
        }
    }
    if ($selectedPaySystem != $form["PAY_SYSTEM"]) {
        $arResult["ERRORS"][] = \Bitrix\Main\Localization\Loc::getMessage("PAY_SYSTEM_ERROR");
    }
    if ($selectedDelivery != $form["DELIVERY"]) {
        $arResult["ERRORS"][] = \Bitrix\Main\Localization\Loc::getMessage("DELIVERY_ERROR");
    }
    /* / validation */

    if (empty($arResult["ERRORS"]) && $arResult["HIDE_ERRORS"] != "Y") {
        $savingResult = $order->save();
        if (!$savingResult->isSuccess()) {
            $errors = $savingResult->getErrorMessages();
            $arResult["ERRORS"] = array_merge($arResult["ERRORS"], $errors);
        } else {
            $arResult["ORDER_ID"] = $order->GetId();

            /* events sending */
            if (
                !empty($arParams["EVENT_TYPES"]) &&
                $USER->IsAuthorized() &&
                (
                    !empty($arResult["USER"]["EMAIL"]) ||
                    isset($EMAIL_PROPERTY_CODE) &&
                    !empty($arResult["CURRENT_VALUES"]["ORDER_PROPS"][$EMAIL_PROPERTY_CODE])
                )
            ) {
                $mailContactList = "";
                $mailUserName = "";
                $mailBasketList = "";
                if (
                    isset($PHONE_PROPERTY_CODE) &&
                    isset($arResult["CURRENT_VALUES"]["ORDER_PROPS"][$PHONE_PROPERTY_CODE])
                ) {
                    $mailContactList .= \Bitrix\Main\Localization\Loc::getMessage("PHONE_PROPERTY") .
                        $arResult["CURRENT_VALUES"]["ORDER_PROPS"][$PHONE_PROPERTY_CODE] . '<br/>';
                }
                if (
                    isset($EMAIL_PROPERTY_CODE) &&
                    isset($arResult["CURRENT_VALUES"]["ORDER_PROPS"][$EMAIL_PROPERTY_CODE])
                ) {
                    $mailContactList .= \Bitrix\Main\Localization\Loc::getMessage("EMAIL_PROPERTY") .
                        $arResult["CURRENT_VALUES"]["ORDER_PROPS"][$EMAIL_PROPERTY_CODE] . '<br/>';
                }
                if (
                    isset($DATE_PROPERTY_CODE) &&
                    isset($arResult["CURRENT_VALUES"]["ORDER_PROPS"][$DATE_PROPERTY_CODE])
                ) {
                    $mailContactList .= \Bitrix\Main\Localization\Loc::getMessage("DATE_PROPERTY") .
                        $arResult["CURRENT_VALUES"]["ORDER_PROPS"][$DATE_PROPERTY_CODE] . '<br/>';
                }
                if (
                    isset($FIO_PROPERTY_CODE) &&
                    isset($arResult["CURRENT_VALUES"]["ORDER_PROPS"][$FIO_PROPERTY_CODE])
                ) {
                    $mailUserName = $arResult["CURRENT_VALUES"]["ORDER_PROPS"][$FIO_PROPERTY_CODE];
                }
                $mailBasket = $order->getBasket();
                if(!empty($mailBasket)){
                    foreach ($mailBasket as $basketItem) {
                        $mailBasketList .= $basketItem->getField('NAME') .
                            ' ' .
                            $basketItem->getQuantity() .
                            ' x ' .
                            SaleFormatCurrency($basketItem->getPrice(), $currency) .
                            ' = ' .
                            SaleFormatCurrency($basketItem->getFinalPrice(), $currency) .
                            '</br>';
                    }
                }
                $arEventFieldsUs = array(
                    "ORDER_ID" => $arResult["ORDER_ID"],
                    "PRICE" => $arResult["PRICES"]["TOTAL_PRICE_FORMATTED"],
                    "DELIVERY_PRICE" => $arResult["PRICES"]["DELIVERY_PRICE_FORMATTED"],
                    "PRODUCTS_PRICE" => $arResult["PRICES"]["PRODUCTS_PRICE_FORMATTED"],
                    "ORDER_LIST" => $mailBasketList,
                    "EMAIL" => (
                        isset($EMAIL_PROPERTY_CODE) &&
                        !empty($arResult["CURRENT_VALUES"]["ORDER_PROPS"][$EMAIL_PROPERTY_CODE])
                    ) ? $arResult["CURRENT_VALUES"]["ORDER_PROPS"][$EMAIL_PROPERTY_CODE] : $arResult["USER"]["EMAIL"],
                    "ORDER_USER" => $mailUserName,
                    "ORDER_DATE" => date('d.m.Y'),
                    "CONTACT_LIST" => $mailContactList,
                    "SALE_EMAIL" => \Bitrix\Main\Config\Option::get("sale", "order_email", "")
                );
                if (!empty($arParams["EVENT_TYPES"])) {
                    foreach ($arParams["EVENT_TYPES"] as $eventTypeID) {
                        \Bitrix\Main\Mail\Event::send([
                            "EVENT_NAME" => $eventTypeID,
                            "LID" => $arParams["SITE_ID"],
                            "C_FIELDS" => $arEventFieldsUs
                        ]);
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
    }
} else {
    if (
        $arParams["SET_DEFAULT_PROPERTIES_VALUES"] == "Y" &&
        $USER->IsAuthorized() &&
        isset($arResult["USER"])
    ) {
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