# Простой и легко кастомизируемый компонент оформления заказа. 

## Особенности:
- Ориентирован на простую доработку под нужды клиента
- Доступно оформление заказа без регистрации. Заказ в этом случае будет привязываться к заданному в настройках пользователю "Анонимный покупатель"
- Можно определять дополнительные почтовые события, срабатывающие при оформлении заказа
- Опционально для свойств, соответствующих полям "ФИО", "Телефон" и "E-mail", осуществляется автоподстановка значений по умолчанию из данных пользователя
- Опционально включается функционал управлением доступными датами для доставки заказа. При этом доступные даты попадают в выпадающий список соответствующего свойства

## Установка
Скопируйте содержимое репозитория в папку */bitrix/components/tega/order.simple/*.
Компонент будет доступен на вкладке "tega" в списке копмонентов визуального редактора.