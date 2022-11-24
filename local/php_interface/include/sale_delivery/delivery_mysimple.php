<?
// Листинг файла /bitrix/php_interface/include/sale_delivery/delivery_mysimple.php

CModule::IncludeModule("sale");

use Bitrix\Sale;

class CDeliveryMySimple
{
    function Init()
    {
        return array(
            /* Основное описание */
            "SID" => "fonteaqua_custom_1",
            "NAME" => "Доставка в зависимсти от мин суммы",
            "DESCRIPTION" => "",
            "DESCRIPTION_INNER" => "Стоимость доставки рассчитывается как разница между стоимостью заказа и настройкой минимальной стоимости",
            "BASE_CURRENCY" => COption::GetOptionString("sale", "default_currency", "RUB"),
            "HANDLER" => __FILE__,
            /* Методы обработчика */
            "DBGETSETTINGS" => array("CDeliveryMySimple", "GetSettings"),
            "DBSETSETTINGS" => array("CDeliveryMySimple", "SetSettings"),
            "GETCONFIG" => array("CDeliveryMySimple", "GetConfig"),
            "COMPABILITY" => array("CDeliveryMySimple", "Compability"),
            "CALCULATOR" => array("CDeliveryMySimple", "Calculate"),
            /* Список профилей доставки */
            "PROFILES" => array(
                "simple" => array(
                    "TITLE" => "Доставка",
                    "DESCRIPTION" => "Срок доставки до 3 дней",
                    "RESTRICTIONS_WEIGHT" => array(0), // без ограничений
                    "RESTRICTIONS_SUM" => array(0), // без ограничений
                ),
            )
        );
    }

    // настройки обработчика
    function GetConfig()
    {
        $arConfig = array(
            "CONFIG_GROUPS" => array(
                "all" => "Стоимость доставки",
            ),

            "CONFIG" => array(),
        );

        // настройками обработчика в данном случае являются значения стоимости доставки в различные группы местоположений.
        // для этого сформируем список настроек на основе списка групп

        $dbLocationGroups = CSaleLocationGroup::GetList();
        while ($arLocationGroup = $dbLocationGroups->Fetch()) {
            $arConfig["CONFIG"]["price_" . $arLocationGroup["ID"]] = array(
                "TYPE" => "STRING",
                "DEFAULT" => "",
                "TITLE" =>
                    "Стоимость доставки в группу \""
                    . $arLocationGroup["NAME"] . "\" "
                    . "(" . COption::GetOptionString("sale", "default_currency", "RUB") . ')',
                "GROUP" => "all",
            );
        }

        return $arConfig;
    }

    // подготовка настроек для занесения в базу данных
    function SetSettings($arSettings)
    {
        // Проверим список значений стоимости. Пустые значения удалим из списка.
        foreach ($arSettings as $key => $value) {
            if (strlen($value) > 0) {
                $arSettings[$key] = doubleval($value);
            } else {
                unset($arSettings[$key]);
            }
        }

        // вернем значения в виде сериализованного массива.
        // в случае более простого списка настроек можно применить более простые методы сериализации.
        return serialize($arSettings);
    }

    // подготовка настроек, полученных из базы данных
    function GetSettings($strSettings)
    {
        // вернем десериализованный массив настроек
        return unserialize($strSettings);
    }

    // метод проверки совместимости в данном случае практически аналогичен рассчету стоимости
    function Compability($arOrder, $arConfig)
    {
        return array('simple');
    }

    // собственно, рассчет стоимости
    function Calculate($profile, $arConfig, $arOrder, $STEP, $TEMP = false)
    {
        //Вычислим сумму всех товаров в корзине исходя из базовой цены, потому что доставку нужно считать именно от цены без скидок

        $basket = Sale\Basket::loadItemsForFUser(Sale\Fuser::getId(), Bitrix\Main\Context::getCurrent()->getSite());

        if (!$basket->isEmpty()) {
            // берём сумму товаров из объекта корзины, в случае расчёта доставки до оформления заказа, при редактировании корзины.
            $price = $basket->getBasePrice();
        } else {
            // берём сумму товаров из заказа, в случае расчёта доставки после оформления заказа, например при редактировании в админке.
            /**
             * NB! обратить внимание на обработчик события OnBeforeUserRegisterHandler, в районе вызова $item->setQuantity,
             * он тригерит событие расчёта доставки для неявно авторизованного пользователя, ранее совершавшего покупки и передаёт неверное
             * в этом случае содержимое $arOdrer некорректное, если в заказе >1 товара.
             * код некорректно считает сумму для "неавторизованного" клиента, который ранее уже оформлял заказ.
             */
            $price = 0;
            foreach ($arOrder['ITEMS'] as $arItem) {
                $price += $arItem['BASE_PRICE'] * $arItem['QUANTITY'];
            }
        }

        $res = $_SESSION["CITY"]["MIN_SUM_TO_DELIVERY"] - $price;
        $res = max($res, 0);

        return array(
            "RESULT" => "OK",
            "VALUE" => $res,
        );
    }
}

// установим метод CDeliveryMySimple::Init в качестве обработчика события
AddEventHandler("sale", "onSaleDeliveryHandlersBuildList", array('CDeliveryMySimple', 'Init'));
