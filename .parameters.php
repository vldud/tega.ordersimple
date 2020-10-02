<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$arEventType = array();
$rsEventType = CEventType::GetList(array("LID" => LANGUAGE_ID));
while ($eventType = $rsEventType->Fetch()){
    $arEventType[$eventType["EVENT_NAME"]] = $eventType["NAME"];
}

$arSite = array();
$obSite = CSite::GetList($by = "def", $order = "desc", Array());
while ($site = $obSite->Fetch()) {
    $arSite[$site["ID"]] = "[" . $site["ID"] . "] " . $site["NAME"];
    if(!isset($siteDefault)){
        $siteDefault = $site["ID"];
    }
}

$arComponentParameters = array(
    "GROUPS" => array(
        "DELIVERY_TIME" => array(
            "NAME" => \Bitrix\Main\Localization\Loc::getMessage("DELIVERY_TIME_GROUP"),
            "SORT" => 120
        ),
        "WEEKEND" => array(
            "NAME" => \Bitrix\Main\Localization\Loc::getMessage("WEEKEND_PARAMETER_GROUP"),
            "SORT" => 130
        ),
        "PROPERTIES" => array(
            "NAME" => \Bitrix\Main\Localization\Loc::getMessage("PROPERTIES_GROUP"),
            "SORT" => 110
        ),
        "HTML_ATTRIBUTES" => array(
            "NAME" => \Bitrix\Main\Localization\Loc::getMessage("HTML_ATTRIBUTES_GROUP"),
            "SORT" => 140
        ),
    ),
    "PARAMETERS" => array(
        "USER_CONSENT" => array(),
        "AJAX_MODE" => array(),
        "SITE_ID" => array(
            "TYPE" => "LIST",
            "MULTIPLE" => "N",
            "VALUES" => $arSite,
            "DEFAULT" => $siteDefault,
            "ADDITIONAL_VALUES" => "N",
            "NAME" => \Bitrix\Main\Localization\Loc::getMessage("SITE_ID"),
            "PARENT" => "BASE",
            "REFRESH" => "Y"
        ),
        "ORDER_RESULT_PAGE" => array(
            'NAME' => \Bitrix\Main\Localization\Loc::getMessage("ORDER_RESULT_PAGE"),
            'TYPE' => 'STRING',
            'MULTIPLE' => 'N',
            "DEFAULT" => "",
            'PARENT' => 'BASE'
        ),
        "BASKET_PAGE" => array(
            'NAME' => \Bitrix\Main\Localization\Loc::getMessage("BASKET_PAGE"),
            'TYPE' => 'STRING',
            'MULTIPLE' => 'N',
            "DEFAULT" => "/personal/cart/",
            'PARENT' => 'BASE'
        ),
        "ANONYMOUS_USER_ID" => array(
            'NAME' => \Bitrix\Main\Localization\Loc::getMessage("ANONYMOUS_USER_ID"),
            'TYPE' => 'STRING',
            'MULTIPLE' => 'N',
            "DEFAULT" => "",
            'PARENT' => 'BASE'
        ),
        "EVENT_TYPES" => array(
            "TYPE" => "LIST",
            "MULTIPLE" => "Y",
            "VALUES" => $arEventType,
            "ADDITIONAL_VALUES" => "N",
            "NAME" => \Bitrix\Main\Localization\Loc::getMessage("EVENT_TYPES"),
            "PARENT" => "BASE",
            "REFRESH" => "N"
        ),
        "FORM_NAME" => array(
            'NAME' => \Bitrix\Main\Localization\Loc::getMessage("FORM_NAME"),
            'TYPE' => 'STRING',
            'MULTIPLE' => 'N',
            "DEFAULT" => "simple_order_form",
            'PARENT' => 'HTML_ATTRIBUTES'
        ),
        "FORM_ID" => array(
            'NAME' => \Bitrix\Main\Localization\Loc::getMessage("FORM_ID"),
            'TYPE' => 'STRING',
            'MULTIPLE' => 'N',
            "DEFAULT" => "simple_order_form",
            'PARENT' => 'HTML_ATTRIBUTES'
        ),
        "ENABLE_VALIDATION_INPUT_NAME" => array(
            'NAME' => \Bitrix\Main\Localization\Loc::getMessage("ENABLE_VALIDATION_INPUT_NAME"),
            'TYPE' => 'STRING',
            'MULTIPLE' => 'N',
            "DEFAULT" => "validation",
            'PARENT' => 'HTML_ATTRIBUTES'
        ),
        "ENABLE_VALIDATION_INPUT_ID" => array(
            'NAME' => \Bitrix\Main\Localization\Loc::getMessage("ENABLE_VALIDATION_INPUT_ID"),
            'TYPE' => 'STRING',
            'MULTIPLE' => 'N',
            "DEFAULT" => "simple_order_form_validation",
            'PARENT' => 'HTML_ATTRIBUTES'
        )
    ),
);

if (\Bitrix\Main\Loader::includeModule("sale")) {
    $dbPersonType = CSalePersonType::GetList(
        array("SORT" => "ASC"),
        array("LID" => (empty($arCurrentValues["SITE_ID"])) ? $siteDefault : $arCurrentValues["SITE_ID"], "ACTIVE" => "Y")
    );
    $arPersonType = array();
    while ($personType = $dbPersonType->GetNext()) {
        $arPersonType[$personType["ID"]] = $personType["NAME"];
    }
    $arComponentParameters["PARAMETERS"]["PERSON_TYPE_ID"] = array(
        "TYPE" => "LIST",
        "MULTIPLE" => "N",
        "VALUES" => array(0 => \Bitrix\Main\Localization\Loc::getMessage("NOT_SELECTED")) + $arPersonType,
        "ADDITIONAL_VALUES" => "N",
        "NAME" => \Bitrix\Main\Localization\Loc::getMessage("PERSON_TYPE_ID"),
        "PARENT" => "PROPERTIES",
        "REFRESH" => "Y"
    );

    $dbProps = CSaleOrderProps::GetList(
        array("SORT" => "ASC"),
        array(
            "PERSON_TYPE_ID" => $arCurrentValues["PERSON_TYPE_ID"]
        ),
        false,
        false,
        array()
    );
    $arProfileProps = array();
    while ($prop = $dbProps->Fetch()){
        $arProfileProps[$prop["ID"]] = $prop["NAME"];
    }
    $arComponentParameters["PARAMETERS"]["ORDER_PROPS"] = array(
        "TYPE" => "LIST",
        "MULTIPLE" => "Y",
        "VALUES" => $arProfileProps,
        "ADDITIONAL_VALUES" => "N",
        "NAME" => \Bitrix\Main\Localization\Loc::getMessage("ORDER_PROPS"),
        "PARENT" => "PROPERTIES",
        "REFRESH" => "Y"
    );

    $arVisibleProps = is_array($arCurrentValues["ORDER_PROPS"]) ? array_flip($arCurrentValues["ORDER_PROPS"]) : array();
    array_walk($arVisibleProps, function(&$val, $key, $arProfileProps){
        $val = $arProfileProps[$key];
    }, $arProfileProps);
    $arComponentParameters["PARAMETERS"]["REQUIRED_ORDER_PROPS"] = array(
        "TYPE" => "LIST",
        "MULTIPLE" => "Y",
        "VALUES" => $arVisibleProps,
        "DEFAULT" => "",
        "ADDITIONAL_VALUES" => "N",
        "NAME" => \Bitrix\Main\Localization\Loc::getMessage("REQUIRED_ORDER_PROPS"),
        "PARENT" => "PROPERTIES",
        "REFRESH" => "N"
    );
    $arComponentParameters["PARAMETERS"]["EMAIL_PROPERTY"] = array(
        "TYPE" => "LIST",
        "MULTIPLE" => "N",
        "VALUES" => array(0 => \Bitrix\Main\Localization\Loc::getMessage("NOT_SELECTED")) + $arVisibleProps,
        "ADDITIONAL_VALUES" => "N",
        "NAME" => \Bitrix\Main\Localization\Loc::getMessage("EMAIL_PROPERTY"),
        "PARENT" => "PROPERTIES",
        "REFRESH" => "N"
    );
    $arComponentParameters["PARAMETERS"]["PHONE_PROPERTY"] = array(
        "TYPE" => "LIST",
        "MULTIPLE" => "N",
        "VALUES" => array(0 => \Bitrix\Main\Localization\Loc::getMessage("NOT_SELECTED")) + $arVisibleProps,
        "ADDITIONAL_VALUES" => "N",
        "NAME" => \Bitrix\Main\Localization\Loc::getMessage("PHONE_PROPERTY"),
        "PARENT" => "PROPERTIES",
        "REFRESH" => "N"
    );
    $arComponentParameters["PARAMETERS"]["FIO_PROPERTY"] = array(
        "TYPE" => "LIST",
        "MULTIPLE" => "N",
        "VALUES" => array(0 => \Bitrix\Main\Localization\Loc::getMessage("NOT_SELECTED")) + $arVisibleProps,
        "ADDITIONAL_VALUES" => "N",
        "NAME" => \Bitrix\Main\Localization\Loc::getMessage("FIO_PROPERTY"),
        "PARENT" => "PROPERTIES",
        "REFRESH" => "N"
    );
    $arComponentParameters["PARAMETERS"]["SET_DEFAULT_PROPERTIES_VALUES"] = array(
        "TYPE" => "CHECKBOX",
        "MULTIPLE" => "N",
        "DEFAULT" => "Y",
        "NAME" => \Bitrix\Main\Localization\Loc::getMessage("SET_DEFAULT_PROPERTIES_VALUES"),
        "PARENT" => "PROPERTIES",
        "REFRESH" => "N"
    );
    $arComponentParameters["PARAMETERS"]["USE_DATE_CALCULATION"] = array(
        "TYPE" => "CHECKBOX",
        "MULTIPLE" => "N",
        "DEFAULT" => "N",
        "NAME" => \Bitrix\Main\Localization\Loc::getMessage("USE_DATE_CALCULATION"),
        "PARENT" => "PROPERTIES",
        "REFRESH" => "Y"
    );
    if($arCurrentValues["USE_DATE_CALCULATION"] == "Y" && CModule::IncludeModule("iblock")){
        $arComponentParameters["PARAMETERS"]["DATE_PROPERTY"] = array(
            "TYPE" => "LIST",
            "MULTIPLE" => "N",
            "VALUES" => array(0 => \Bitrix\Main\Localization\Loc::getMessage("NOT_SELECTED")) + $arVisibleProps,
            "ADDITIONAL_VALUES" => "N",
            "NAME" => \Bitrix\Main\Localization\Loc::getMessage("DATE_PROPERTY"),
            "PARENT" => "PROPERTIES",
            "REFRESH" => "N"
        );
        $arComponentParameters["PARAMETERS"]["DATE_FORMAT"] = CIBlockParameters::GetDateFormat(
            \Bitrix\Main\Localization\Loc::getMessage("DATE_FORMAT"),
            "DELIVERY_TIME"
        );
        $arComponentParameters["PARAMETERS"]["DATES_INTERVAL"] = array(
            "PARENT" => "DELIVERY_TIME",
            "NAME" => \Bitrix\Main\Localization\Loc::getMessage("DATES_INTERVAL"),
            "TYPE" => "STRING",
            "MULTIPLE" => "N",
            "DEFAULT" => "14",
            "REFRESH" => "N",
            "COLS" => "10"
        );
        $arComponentParameters["PARAMETERS"]["CLOSING_TIME"] = array(
            "PARENT" => "DELIVERY_TIME",
            "NAME" => \Bitrix\Main\Localization\Loc::getMessage("CLOSING_TIME"),
            "TYPE" => "STRING",
            "MULTIPLE" => "N",
            "DEFAULT" => "18:00",
            "REFRESH" => "N",
            "COLS" => "10"
        );
        $arComponentParameters["PARAMETERS"]["PROHIBITED_DATES"] = array(
            "PARENT" => "DELIVERY_TIME",
            "NAME" => \Bitrix\Main\Localization\Loc::getMessage("PROHIBITED_DATES"),
            "TYPE" => "STRING",
            "MULTIPLE" => "Y",
            "DEFAULT" => array("01.01.1970"),
            "REFRESH" => "N",
            "COLS" => "10"
        );
        $arComponentParameters["PARAMETERS"]["WEEKEND_DAY_1"] = Array(
            "PARENT" => "WEEKEND",
            "NAME" => \Bitrix\Main\Localization\Loc::getMessage("WEEKEND_DAY_MONDAY"),
            "TYPE" => "CHECKBOX",
        );
        $arComponentParameters["PARAMETERS"]["WEEKEND_DAY_2"] = Array(
            "PARENT" => "WEEKEND",
            "NAME" => \Bitrix\Main\Localization\Loc::getMessage("WEEKEND_DAY_TUESDAY"),
            "TYPE" => "CHECKBOX",
        );
        $arComponentParameters["PARAMETERS"]["WEEKEND_DAY_3"] = Array(
            "PARENT" => "WEEKEND",
            "NAME" => \Bitrix\Main\Localization\Loc::getMessage("WEEKEND_DAY_WEDNESDAY"),
            "TYPE" => "CHECKBOX",
        );
        $arComponentParameters["PARAMETERS"]["WEEKEND_DAY_4"] = Array(
            "PARENT" => "WEEKEND",
            "NAME" => \Bitrix\Main\Localization\Loc::getMessage("WEEKEND_DAY_THURSDAY"),
            "TYPE" => "CHECKBOX",
        );
        $arComponentParameters["PARAMETERS"]["WEEKEND_DAY_5"] = Array(
            "PARENT" => "WEEKEND",
            "NAME" => \Bitrix\Main\Localization\Loc::getMessage("WEEKEND_DAY_FRIDAY"),
            "TYPE" => "CHECKBOX",
        );
        $arComponentParameters["PARAMETERS"]["WEEKEND_DAY_6"] = Array(
            "PARENT" => "WEEKEND",
            "NAME" => \Bitrix\Main\Localization\Loc::getMessage("WEEKEND_DAY_SATURDAY"),
            "TYPE" => "CHECKBOX",
        );
        $arComponentParameters["PARAMETERS"]["WEEKEND_DAY_0"] = Array(
            "PARENT" => "WEEKEND",
            "NAME" => \Bitrix\Main\Localization\Loc::getMessage("WEEKEND_DAY_SUNDAY"),
            "TYPE" => "CHECKBOX",
        );
    }
}