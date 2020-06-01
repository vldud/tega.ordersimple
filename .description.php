<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
$arComponentDescription = array(
    "NAME" => GetMessage("ORDER_DESCR"),
    "DESCRIPTION" => GetMessage("ORDER_DESCR"),
    "PATH" => array(
        "ID" => "tega",
        "CHILD" => array(
            "ID" => "order_simple",
            "NAME" => GetMessage("ORDER_DESCR")
        )
    ),
    "ICON" => "/images/icon.gif",
);
?>