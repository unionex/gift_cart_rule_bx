<?
// Листинг файла /bitrix/php_interface/include/sale_delivery/delivery_mysimple.php
use Bitrix\Main\Context,
    Bitrix\Main\Request;

CModule::IncludeModule("sale");

class CDeliveryMySimpleExpress
{
    function Init()
    {
        return array(
            /* Основное описание */
            "SID" => "fonteaqua_custom_1_express",
            "NAME" => "Доставка в зависимсти от мин суммы",
            "DESCRIPTION" => "",
            "DESCRIPTION_INNER" => "Стоимость доставки рассчитывается как разница между стоимостью заказа и настройкой минимальной стоимости",
            "BASE_CURRENCY" => COption::GetOptionString("sale", "default_currency", "RUB"),
            "HANDLER" => __FILE__,
            "DBGETSETTINGS" => array("CDeliveryMySimpleExpress", "GetSettings"),
            "DBSETSETTINGS" => array("CDeliveryMySimpleExpress", "SetSettings"),
            "GETCONFIG" => array("CDeliveryMySimpleExpress", "GetConfig"),
            "COMPABILITY" => array("CDeliveryMySimpleExpress", "Compability"),
            "CALCULATOR" => array("CDeliveryMySimpleExpress", "Calculate"),
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
        $request = Context::getCurrent()->getRequest();
        //Вычислим сумму всех товаров в корзине исходя из базовой цены, потому что доставку нужно считать именно от цены без скидок
        $totalSum = 0;
        foreach ($arOrder['ITEMS'] as $arItem) {
            $totalSum += $arItem['BASE_PRICE'] * $arItem['QUANTITY'];
        }

        // Если указана оплата бонусами, то в цене заказа бонусы должны быть учтены, иначе если стоимость заказа меньше минимальной после
        // применения бонусов, то обработчик докидывает стоимость до минимальной. В случае с с бонусами этого происходить не должно.
        $bonuses = (isset($request['ORDER_PROP_59']) && $request['ORDER_PROP_59']) ? (int)$request['ORDER_PROP_59'] : 0;
        $res = $_SESSION["CITY"]["MIN_SUM_TO_DELIVERY"] - ($totalSum + $bonuses);
        $res = $res < 0 ? 0 : $res;
        return array(
            "RESULT" => "OK",
            "VALUE" => $res,
        );
    }
}

// установим метод CDeliveryMySimple::Init в качестве обработчика события
AddEventHandler("sale", "onSaleDeliveryHandlersBuildList", array('CDeliveryMySimpleExpress', 'Init'));
?>