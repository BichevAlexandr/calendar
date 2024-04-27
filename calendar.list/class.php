<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

CBitrixComponent::includeComponentClass('local:calendar');


class CalendarListComponent extends Calendar
{
    public function onPrepareComponentParams($arParams)
    {
        return parent::onPrepareComponentParams($arParams);
    }


    public function executeComponent()
    {
        if (!$this->initComponentTemplate()) {
            $this->SetTemplatename('.default');
        }

        $this->includeComponentTemplate();
    }
}
