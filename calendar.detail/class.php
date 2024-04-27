<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

CBitrixComponent::includeComponentClass('local:calendar');


class CalendarEventDetailComponent extends Calendar
{
    public function onPrepareComponentParams($arParams)
    {
        return parent::onPrepareComponentParams($arParams);
    }

    public function detailAction($eventID = 0)
    {
        return parent::detailAction($eventID);
    }

//    public function registerAction()
//    {
//        $arParams = self::request();
//        return parent::registerAction($arParams['eventID'], $arParams['userManagerID']);
//    }
//
//    private function request()
//    {
//        $request = Bitrix\Main\HttpApplication::getInstance()->getContext()->getRequest();
//        return $request->getPostList()->toArray();
//    }

    public function executeComponent()
    {
        if ($this->arParams['EVENT_ID'] > 0) {
            $this->arResult['EVENT_DETAIL'] = self::detailAction($this->arParams['EVENT_ID']);
        }

        if (!empty($this->arResult['EVENT_DETAIL']['departments_access'])) {
            $this->SetTemplatename('.default');
        } else {
            $this->SetTemplatename('access');
        }

        $this->includeComponentTemplate();
    }
}
