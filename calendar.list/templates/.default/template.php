<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
  die();
}

/**
 * @global CMain $APPLICATION
 * @var array $arParams
 * @var array $arResult
 * @var CatalogSectionComponent $component
 * @var CBitrixComponentTemplate $this
 * @var string $templateName
 * @var string $componentPath
 * @var string $templateFolder
 */
$bodyClass = $APPLICATION->GetPageProperty("BodyClass");
$APPLICATION->SetPageProperty("BodyClass", ($bodyClass ? $bodyClass." " : "")." page_calendar");
Dbogdanoff\Bitrix\Vue::includeComponent(
  [
    'event-calendar/main-page',
    'event-calendar/filter/category',
    'event-calendar/filter/type',
    'event-calendar/el/day',
  ]
);

?>
<div id="app">
  <event-calendar-main-page v-bind:category="category"></event-calendar-main-page>
</div>

<script>
  var mainVueApp = new Vue({
      el: '#app',
      data() {
        return {
          months: ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'],

        }
      }
    })
  ;
</script>
