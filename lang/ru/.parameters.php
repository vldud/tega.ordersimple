<?
$MESS["SITE_ID"] = "Сайт";
$MESS["PERSON_TYPE_ID"] = "Тип плательщика";
$MESS["ORDER_PROPS"] = "Свойства заказа";
$MESS["REQUIRED_ORDER_PROPS"] = "Обязательные для заполнения свойства";
$MESS["NOT_SELECTED"] = "Не выбран";
$MESS["ORDER_RESULT_PAGE"] = "Страница окончания оформления заказа";
$MESS["BASKET_PAGE"] = "Страница корзины";
$MESS["ANONYMOUS_USER_ID"] = "ID анонимного пользователя";
$MESS["EVENT_TYPES"] = "Типы почтовых событий, срабатывающих при создании нового заказа";
$MESS["EMAIL_PROPERTY"] = "Свойство \"E-mail\"";
$MESS["PHONE_PROPERTY"] = "Свойство \"Телефон\"";
$MESS["DATE_PROPERTY"] = "Свойство \"Дата заказа\"";
$MESS["FIO_PROPERTY"] = "Свойство \"ФИО\"";
$MESS["SET_DEFAULT_PROPERTIES_VALUES"] = "Заполнять E-mail, Телефон и ФИО из параметров пользователя по умолчанию";
$MESS["USE_DATE_CALCULATION"] = "Расчет доступных дат доставки";

$MESS["FORM_NAME"] = "Имя формы";
$MESS["FORM_ID"] = "ID формы";
$MESS["ENABLE_VALIDATION_INPUT_NAME"] = "Имя скрытого поля, включающего валидацию формы";
$MESS["ENABLE_VALIDATION_INPUT_ID"] = "ID скрытого поля, включающего валидацию формы";

$MESS["DATE_FORMAT"] = "Формат даты";
$MESS["DELIVERY_TIME_GROUP"] = "Ограничение доступных дат доставки";
$MESS["WEEKEND_PARAMETER_GROUP"] = "Выходные дни";
$MESS["PROPERTIES_GROUP"] = "Свойства заказа";
$MESS["HTML_ATTRIBUTES_GROUP"] = "HTML атрибуты";

$MESS["WEEKEND_DAY_MONDAY"] = "Пн.";
$MESS["WEEKEND_DAY_TUESDAY"] = "Вт.";
$MESS["WEEKEND_DAY_WEDNESDAY"] = "Ср.";
$MESS["WEEKEND_DAY_THURSDAY"] = "Чт.";
$MESS["WEEKEND_DAY_FRIDAY"] = "Птн.";
$MESS["WEEKEND_DAY_SATURDAY"] = "Суб.";
$MESS["WEEKEND_DAY_SUNDAY"] = "Вс.";

$MESS["PROHIBITED_DATES"] = "Нерабочие дни в формате d.m.Y";
$MESS["DATES_INTERVAL"] = "Интервал доступных дат (дни)";
$MESS["TIMESLOT"] = "Временной интервал (часы)";
$MESS["CLOSING_TIME"] = "Доставки на сегодня не доступны после (часы:минуты)";

$MESS["ANONYMOUS_USER_ID_TIP"] = "Если параметр задан, то при оформлении заказа без авторизации будет привязываться пользователь с данным ID";
$MESS["EVENT_TYPES_TIP"] = "Если параметр задан, то при оформлении заказа будет осуществляться рассылка по шаблонам выбранных типов почтовый событий. " .
    "Поля для типа почтового события:<br>" .
    "CONTACT_LIST - контактная информация заказчика<br>" .
    "DELIVERY_PRICE - стоимость доставки<br>" .
    "EMAIL - email заказчика<br>" .
    "ORDER_DATE - дата заказа<br>" .
    "ORDER_ID - код заказа<br>" .
    "ORDER_LIST - состав заказа<br>" .
    "ORDER_USER - заказчик<br>" .
    "PRICE - общая стоимость заказа<br>" .
    "PRODUCTS_PRICE - стоимость товаров";
$MESS["EMAIL_PROPERTY_TIP"] = "Данное свойство будет использовано в качестве e-mail адреса на который будет отправлено сообщение о создании заказа";
$MESS["SET_DEFAULT_PROPERTIES_VALUES_TIP"] = "Если выбрано, то для значения по умолчанию будут взяты из следующих полей пользователя:<br>ФИО -> LAST_NAME + NAME + SECOND_NAME<br>Телефон -> PERSONAL_PHONE<br>Email -> EMAIL";
$MESS["ORDER_RESULT_PAGE_TIP"] = "Если указано, то пользователь будет перенаправлен по данному адресу после успешного оформления заказа";
$MESS["BASKET_PAGE_TIP"] = "Если указано, то пользователь будет перенаправлен по данному адресу при отсутствии товаров в корзине";
$MESS["USE_DATE_CALCULATION_TIP"] = 'Если выбрано, то будет сформирован массив $arResult["AVAILABLE_DATES"] с доступными датами для заказа';
$MESS["DATE_FORMAT_TIP"] = "Формат, в котором будут записаны даты в массиве доступных дат заказа";
$MESS["DATES_INTERVAL_TIP"] = "Количество дней, начиная с текущего момента, даты которых попадут в массив доступных дат заказа";
$MESS["CLOSING_TIME_TIP"] = "После указанного времени сегодняшняя дата будет исключена из массива доступных дат заказа";
$MESS["PROHIBITED_DATES_TIP"] = "Конкретные даты, которые будут исключены из массива доступных дат заказа";
