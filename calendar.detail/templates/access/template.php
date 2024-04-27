<?

include($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");
global $APPLICATION;
use Bitrix\Main\Page\Asset;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);
$APPLICATION->ShowViewContent('PAGE_TOASTS');
$assets = Asset::getInstance();
$assets->addCss(SITE_TEMPLATE_PATH . '/plugins/styles/pages/events-calendar/popup.css');
$assets->addCss(SITE_TEMPLATE_PATH . '/plugins/styles/ui/button/ui.buttons.css');
$assets->addJs('/bitrix/js/main/popup/dist/main.popup.bundle.js');
//  нужен для ToastNotification
$assets->addJs(SITE_TEMPLATE_PATH . '/plugins/scripts/ui/toast/script.js');

if ($arResult['EVENT_DETAIL']['soglasovanie']  == 'Y') {
    $assets->addCss('/local/intranet.user.organization.selector/templates/.default/style.css');
    $assets->addJs('/local/components/local/intranet.user.organization.selector/templates/.default/users.js');
    CJSCore::Init(["popup"]);
}

CJSCore::Init(["jquery"]);
CJSCore::Init("sidepanel");
$APPLICATION->ShowHead();

$register = $arResult['EVENT_DETAIL']['register'];

//  Если установлена галочка апретить регистрацию

($arResult['EVENT_DETAIL']['register_disabled'] == 'N') ? $disabledRegister = false : $disabledRegister = true;

$registerCancel = $arResult['EVENT_DETAIL']['register_cancel'];

if (!empty($arResult['EVENT_DETAIL'])) { ?>
    <div class="event-popup">
        <div class="event-popup__title">Доступ запрещен</div>
        <div class="event-popup__notification">
            <img src="/images/icons/ui/notify/warning-circle.svg">
            <span>Данное мероприятие является закрытым. Ваше подразделение не входит в группу доступа.
                По вопросам доступа можете обратиться к организатору мероприятия:
                <a href="/company/personal/user/99581/">Нерадовской Александре Игоревне</a>.</span>
        </div>
        <div class="event-popup__btns">
            <a href="#" class="ui-btn ui-btn-primary js-close" id="js-close">Закрыть</a>
        </div>
    </div>
    <?
} ?>

<script>
  BX.bind(BX('js-close'), 'click', function (e) {
    e.preventDefault()
    BX.SidePanel.Instance.getTopSlider().requestParams.showSuccess = '0'
    BX.SidePanel.Instance.close()
  })
</script>



