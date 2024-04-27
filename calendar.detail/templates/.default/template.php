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

// если регистрация запрещена
// ($arResult['EVENT_DETAIL']['register_disabled'] == 'N') ? $disabledRegister = false : $disabledRegister = true;

// // если зарегистрирован на мероприятие
// $registerCancel = $arResult['EVENT_DETAIL']['register_cancel'];

// // если регистрация возможна
// $register = $arResult['EVENT_DETAIL']['register'];

// // текст тултипа при hover на слот
// $tooltipText = $arResult['EVENT_DETAIL']['register_detail'];


$register = $arResult['EVENT_DETAIL']['register'];

//  Если установлена галочка апретить регистрацию
($arResult['EVENT_DETAIL']['register_disabled'] == 'N') ? $disabledRegister = false : $disabledRegister = true;

$registerCancel = $arResult['EVENT_DETAIL']['register_cancel'];

if (!empty($arResult['EVENT_DETAIL'])) { ?>
    <div class="event-popup">
        <div class="ec-item__tags">
          <? if (!empty($arResult['EVENT_DETAIL']['format']['name'])) { ?>
              <?// цвет тега в попапе ?>
              <div class="tag tag_<?=$arResult['EVENT_DETAIL']['format']['color']?> event-popup__tag"><?=$arResult['EVENT_DETAIL']['format']['name']?></div>
          <? } ?>
          <? if (!empty($arResult['EVENT_DETAIL']['register_disabled']=='Y')) { ?>
            <div id="event-tag__registration-stopped" class="tag">
              <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M7.01898 1.52206C7.31851 1.35342 7.65644 1.26483 8.00018 1.26483C8.34391 1.26483 8.68185 1.35342 8.98137 1.52206C9.2809 1.69069 9.53191 1.93368 9.71018 2.22758L9.7121 2.23074L15.3588 11.6574L15.3642 11.6666C15.5388 11.969 15.6312 12.3119 15.6322 12.6611C15.6332 13.0104 15.5427 13.3538 15.3697 13.6571C15.1968 13.9605 14.9474 14.2134 14.6465 14.3905C14.3455 14.5676 14.0034 14.6628 13.6542 14.6666L13.6468 14.6667L2.34618 14.6667C1.99698 14.6628 1.65487 14.5676 1.35389 14.3905C1.05291 14.2134 0.803545 13.9605 0.63061 13.6571C0.457674 13.3538 0.367194 13.0104 0.368172 12.6611C0.36915 12.3119 0.461551 11.969 0.636183 11.6666L0.641598 11.6574L6.29017 2.22757C6.46844 1.93368 6.71946 1.69069 7.01898 1.52206ZM8.00018 2.59816C7.8856 2.59816 7.77295 2.6277 7.67311 2.68391C7.57372 2.73987 7.49036 2.82038 7.43098 2.91775L1.78863 12.3372C1.73185 12.4371 1.70182 12.5499 1.7015 12.6649C1.70117 12.7813 1.73133 12.8957 1.78898 12.9969C1.84662 13.098 1.92975 13.1823 2.03007 13.2413C2.12952 13.2998 2.24244 13.3315 2.35777 13.3333H13.6426C13.7579 13.3315 13.8708 13.2998 13.9703 13.2413C14.0706 13.1823 14.1537 13.098 14.2114 12.9969C14.269 12.8957 14.2992 12.7813 14.2989 12.6649C14.2985 12.55 14.2685 12.4371 14.2117 12.3372L8.57018 2.91908L8.56937 2.91775C8.51 2.82038 8.42663 2.73987 8.32724 2.68391C8.2274 2.6277 8.11476 2.59816 8.00018 2.59816ZM8.00018 5.33333C8.36837 5.33333 8.66684 5.6318 8.66684 5.99999V8.66666C8.66684 9.03485 8.36837 9.33333 8.00018 9.33333C7.63199 9.33333 7.33351 9.03485 7.33351 8.66666V5.99999C7.33351 5.6318 7.63199 5.33333 8.00018 5.33333ZM7.33351 11.3333C7.33351 10.9651 7.63199 10.6667 8.00018 10.6667H8.00684C8.37503 10.6667 8.67351 10.9651 8.67351 11.3333C8.67351 11.7015 8.37503 12 8.00684 12H8.00018C7.63199 12 7.33351 11.7015 7.33351 11.3333Z" fill="#252628"/>
              </svg>
              <span>Запись приостановлена</span>
            </div>
          <? } ?>

          <? if (!$register && !empty($arResult['EVENT_DETAIL']['status'])) { ?>
              <?
              foreach ($arResult['EVENT_DETAIL']['status'] as $statusValue) {
                  $status = $statusValue;
              }
              ?>
              <div id="event-tag__status" class="tag">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path fill-rule="evenodd" clip-rule="evenodd" d="M13.6368 4.25459C13.8971 4.51494 13.8971 4.93705 13.6368 5.1974L7.08884 11.7454C6.82849 12.0057 6.40638 12.0057 6.14603 11.7454L2.36226 7.96167C2.10191 7.70132 2.1019 7.27921 2.36225 7.01886C2.6226 6.7585 3.04471 6.7585 3.30506 7.01885L6.61743 10.3311L12.694 4.25459C12.9543 3.99424 13.3764 3.99424 13.6368 4.25459Z" fill="white"/>
                </svg>
                <span><?=$status['name']?></span>
              </div>
          <? } ?>
          <? if ($arResult['EVENT_DETAIL']['category']['code'] === 'registration_program') { ?>
            <div id="event-tag__registration-category" class="tag">
              <span><?=$arResult['EVENT_DETAIL']['category']['name']?></span>
            </div>
          <? } ?>
        </div>

        <? if (!empty($arResult['EVENT_DETAIL']['name'])) { ?>
            <div class="event-popup__title"><?=$arResult['EVENT_DETAIL']['name']?></div>
        <? } ?>

        <? if (!empty($arResult['EVENT_DETAIL']['products']['name'])) { ?>
          <div class="event-popup__audience"><?= $arResult['EVENT_DETAIL']['products']['name'] ?></div>
        <? } ?>

        <section id="event-popup-info">
          <? // категория ?>
          <? if (!empty($arResult['EVENT_DETAIL']['category']['name']) && !empty($arResult['EVENT_DETAIL']['category']['iconDetail'])) { ?>
              <div class="event-popup__category">
                  <img src="<?=$arResult['EVENT_DETAIL']['category']['iconDetail']?>" alt="<?= $arResult['EVENT_DETAIL']['category']['name'] ?>" />
                  <span><?=$arResult['EVENT_DETAIL']['category']['name']?></span>
              </div>
          <? } ?>

          <? // открытость/закрытость ?>
          <? if (!empty($arResult['EVENT_DETAIL']['type']['name'])) { ?>
            <div class="event-popup__status">
              <? if ($arResult['EVENT_DETAIL']['type']['name'] === 'Открытое') { ?>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path fill="#252628" fill-rule="evenodd" clip-rule="evenodd" d="M7.1572 2.1065C8.23944 1.38501 9.51103 1 10.8117 1C14.4534 1 17.4 3.96445 17.4 7.6C17.4 7.60152 17.4 7.60304 17.4 7.60455C17.9477 7.61236 18.4373 7.63344 18.8606 7.69035C19.5816 7.7873 20.2728 8.00592 20.8335 8.56655C21.3941 9.12718 21.6127 9.81836 21.7097 10.5394C21.8001 11.2123 21.8001 12.0525 21.8 13.0276L21.8 15.3776C21.8 16.8671 21.8001 18.0954 21.6694 19.0676C21.5322 20.0882 21.233 20.9895 20.5113 21.7113C19.7896 22.433 18.8882 22.7322 17.8676 22.8694C16.8954 23.0001 15.6671 23 14.1776 23H9.62241C8.13286 23 6.90457 23.0001 5.93244 22.8694C4.91182 22.7322 4.01046 22.433 3.28874 21.7113C2.56702 20.9895 2.26785 20.0882 2.13063 19.0676C1.99993 18.0954 1.99997 16.8672 2 15.3776L2 13.0276C1.99995 12.0525 1.9999 11.2123 2.09036 10.5394C2.1873 9.81836 2.40592 9.12718 2.96655 8.56655C3.52718 8.00592 4.21837 7.7873 4.93943 7.69035C5.61226 7.5999 6.45245 7.59994 7.42759 7.6C7.45164 7.6 7.47578 7.6 7.5 7.6H15.2C15.2 5.17336 13.2323 3.2 10.8117 3.2C9.94537 3.2 9.09839 3.45644 8.37754 3.93701L8.11018 4.11526C7.60469 4.45224 6.92174 4.31565 6.58475 3.81017C6.24776 3.30469 6.38435 2.62173 6.88983 2.28474L7.1572 2.1065ZM5.23258 9.87074C4.75547 9.93488 4.60592 10.0384 4.52219 10.1222C4.43845 10.2059 4.33489 10.3555 4.27074 10.8326C4.20234 11.3413 4.20001 12.0318 4.20001 13.1V15.3C4.20001 16.8867 4.20234 17.9661 4.31101 18.7744C4.41543 19.5511 4.59954 19.9108 4.84437 20.1556C5.0892 20.4005 5.44892 20.5846 6.22558 20.689C7.03388 20.7977 8.11327 20.8 9.7 20.8H14.1C15.6867 20.8 16.7661 20.7977 17.5744 20.689C18.3511 20.5846 18.7108 20.4005 18.9556 20.1556C19.2005 19.9108 19.3846 19.5511 19.489 18.7744C19.5977 17.9661 19.6 16.8867 19.6 15.3V13.1C19.6 12.0318 19.5977 11.3413 19.5293 10.8326C19.4651 10.3555 19.3616 10.2059 19.2778 10.1222C19.1941 10.0384 19.0445 9.93488 18.5674 9.87074C18.0587 9.80234 17.3682 9.8 16.3 9.8H7.5C6.43182 9.8 5.74134 9.80234 5.23258 9.87074ZM11.9 12.55C12.5075 12.55 13 13.0425 13 13.65V16.95C13 17.5575 12.5075 18.05 11.9 18.05C11.2925 18.05 10.8 17.5575 10.8 16.95V13.65C10.8 13.0425 11.2925 12.55 11.9 12.55Z"/>
                </svg>
              <? } else { ?>
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path fill-rule="evenodd" clip-rule="evenodd" d="M4.66667 4.66671C4.66667 2.82576 6.15905 1.33337 8 1.33337C9.84095 1.33337 11.3333 2.82576 11.3333 4.66671V5.33337C11.3333 5.33429 11.3333 5.33522 11.3333 5.33613C11.6653 5.34086 11.962 5.35364 12.2185 5.38813C12.6555 5.44689 13.0744 5.57939 13.4142 5.91916C13.754 6.25894 13.8865 6.67784 13.9452 7.11485C14.0001 7.52262 14 8.03184 14 8.62284L14 10.0471C14 10.9498 14 11.6942 13.9208 12.2834C13.8377 12.902 13.6564 13.4483 13.219 13.8857C12.7815 14.3231 12.2353 14.5044 11.6167 14.5875C11.0275 14.6668 10.2831 14.6667 9.38037 14.6667H6.61964C5.71689 14.6667 4.97247 14.6668 4.38329 14.5875C3.76474 14.5044 3.21846 14.3231 2.78105 13.8857C2.34365 13.4483 2.16233 12.902 2.07917 12.2834C1.99996 11.6942 1.99998 10.9498 2 10.0471L2 8.62282C1.99997 8.03183 1.99994 7.52262 2.05476 7.11485C2.11352 6.67784 2.24601 6.25894 2.58579 5.91916C2.92556 5.57939 3.34446 5.44689 3.78148 5.38813C4.03802 5.35364 4.33472 5.34086 4.66668 5.33613C4.66667 5.33522 4.66667 5.33429 4.66667 5.33337V4.66671ZM6 5.33337H10V4.66671C10 3.56214 9.10457 2.66671 8 2.66671C6.89543 2.66671 6 3.56214 6 4.66671V5.33337ZM3.95914 6.70958C3.66998 6.74845 3.57935 6.81122 3.5286 6.86197C3.47785 6.91272 3.41508 7.00335 3.37621 7.29251C3.33475 7.60085 3.33334 8.01932 3.33334 8.66671V10C3.33334 10.9617 3.33475 11.6159 3.40062 12.1058C3.4639 12.5765 3.57548 12.7945 3.72386 12.9429C3.87224 13.0912 4.09026 13.2028 4.56096 13.2661C5.05084 13.332 5.70501 13.3334 6.66667 13.3334H9.33334C10.295 13.3334 10.9492 13.332 11.439 13.2661C11.9098 13.2028 12.1278 13.0912 12.2761 12.9429C12.4245 12.7945 12.5361 12.5765 12.5994 12.1058C12.6653 11.6159 12.6667 10.9617 12.6667 10V8.66671C12.6667 8.01932 12.6653 7.60085 12.6238 7.29251C12.5849 7.00335 12.5222 6.91272 12.4714 6.86197C12.4207 6.81122 12.33 6.74845 12.0409 6.70958C11.7325 6.66812 11.3141 6.66671 10.6667 6.66671H5.33334C4.68595 6.66671 4.26748 6.66812 3.95914 6.70958ZM8 8.33337C8.36819 8.33337 8.66667 8.63185 8.66667 9.00004V11C8.66667 11.3682 8.36819 11.6667 8 11.6667C7.63181 11.6667 7.33334 11.3682 7.33334 11V9.00004C7.33334 8.63185 7.63181 8.33337 8 8.33337Z" fill="#252628"/>
                </svg>
              <? } ?>
              <span><?=$arResult['EVENT_DETAIL']['type']['name']?> мероприятие</span>
            </div>
          <? } ?>

          <? // даты ?>
          <? if (!empty($arResult['EVENT_DETAIL']['date']['date_to'])) { ?>
              <div class="event-popup__date">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#clip0_657_25882)"><path fill-rule="evenodd" clip-rule="evenodd" d="M4.66699 0.666626C5.03518 0.666626 5.33366 0.965103 5.33366 1.33329V1.99996H10.667V1.33329C10.667 0.965103 10.9655 0.666626 11.3337 0.666626C11.7018 0.666626 12.0003 0.965103 12.0003 1.33329V1.99996H14.0609C14.696 1.99996 15.3337 2.47831 15.3337 3.21208V14.1212C15.3337 14.8549 14.696 15.3333 14.0609 15.3333H1.93972C1.30462 15.3333 0.666992 14.8549 0.666992 14.1212V3.21208C0.666992 2.47831 1.30462 1.99996 1.93972 1.99996H4.00033V1.33329C4.00033 0.965103 4.2988 0.666626 4.66699 0.666626ZM4.00033 3.33329H2.00033V5.99996H14.0003V3.33329H12.0003V3.99996C12.0003 4.36815 11.7018 4.66663 11.3337 4.66663C10.9655 4.66663 10.667 4.36815 10.667 3.99996V3.33329H5.33366V3.99996C5.33366 4.36815 5.03518 4.66663 4.66699 4.66663C4.2988 4.66663 4.00033 4.36815 4.00033 3.99996V3.33329ZM14.0003 7.33329H2.00033V14H14.0003V7.33329Z" fill="#252628"></path></g> <defs><clipPath id="clip0_657_25882"><rect width="16" height="16" fill="white"></rect></clipPath></defs></svg>
                <span><?=$arResult['EVENT_DETAIL']['date']['date_to']?></span>
              </div>
          <? } ?>
        </section>

        <? if (!empty($arResult['EVENT_DETAIL']['comment'])) { ?>
            <?
            $arResult['EVENT_DETAIL']['comment'] = str_replace('href', 'target="_blank" href', $arResult['EVENT_DETAIL']['comment']);
            ?>
            <div class="event-popup__subtitle">о мероприятии</div>
            <div class="event-popup__note"><?=$arResult['EVENT_DETAIL']['comment']?></div>
        <? } ?>

        <? if (!empty($arResult['EVENT_DETAIL']['audience'])) { ?>
            <div class="event-popup__audience">
                <div class="event-popup__subtitle">Для кого</div>
                <ul class="event-popup__audience-ul">
                    <? foreach ($arResult['EVENT_DETAIL']['audience'] as $audience) { ?>
                        <li class="event-popup__audience-li"><?= $audience['name'] ?></li>
                    <? } ?>
                </ul>
            </div>
        <? } ?>
        
        
        <? if (!empty($arResult['EVENT_DETAIL']['organizers'])) { ?>
            <div class="event-popup__subtitle"><?=Loc::getMessage('BLOCK_ORGANIZATOR_NAME')?></div>
            <div class="event-popup__users">
                <?
                foreach ($arResult['EVENT_DETAIL']['organizers'] as $managerEvent) { ?>
                    <?
                    $classMask = '';
                    if (!empty($managerEvent['PHOTO'])) {
                        $photoSpeakerCompany = $managerEvent['PHOTO'];
                    } else {
                        $photoSpeakerCompany = '/local/templates/bitrix24/images/user-default-avatar.svg';
                        $classMask = 'no_user_photo';
                    }
                    ?>
                    <div class="event-popup__user">
                        <div class="event-popup__user-img <?=$classMask?>">
                            <img src="<?=$photoSpeakerCompany?>"/>
                        </div>
                        <div class="event-popup__user-details">
                            <div class="event-popup__user-name"><a href="/company/personal/user/<?=$managerEvent['ID']?>/"><?=$managerEvent['FIO']?></a></div>
                            <div class="event-popup__user-status">Организатор - <?=$managerEvent['WORKPOSITION']?> <span
                                    class="tag tag_default"><?=$managerEvent['COMPANY']?></span></div>
                        </div>
                    </div>
                    <?
                } ?>
            </div>
        <? } ?>

        <? if (!empty($arResult['EVENT_DETAIL']['speaker']['fio']) || !empty($arResult['EVENT_DETAIL']['speakerCompany'])) { ?>
            <div class="event-popup__subtitle"><?=Loc::getMessage('BLOCK_SPEAKER')?></div>
            <? if (!empty($arResult['EVENT_DETAIL']['speakerCompany'])) { ?>
                <? foreach ($arResult['EVENT_DETAIL']['speakerCompany'] as $speakerID => $speakerCompany) { ?>
                    <?
                    $classMask = '';
                    if (!empty($speakerCompany['PHOTO'])) {
                        $photoSpeakerCompany = $speakerCompany['PHOTO'];
                    } else {
                        $photoSpeakerCompany = '/local/templates/bitrix24/images/user-default-avatar.svg';
                        $classMask = 'no_user_photo';
                    }
                    ?>
                    <div class="event-popup__user">
                        <div class="event-popup__user-img <?=$classMask?>">
                            <img src="<?=$photoSpeakerCompany?>" class=""/>
                        </div>
                        <div class="event-popup__user-details">
                            <div class="event-popup__user-name"><a href="/company/personal/user/<?=$speakerCompany['ID']?>/"><?=$speakerCompany['FIO']?></a></div>
                            <div class="event-popup__user-status"><span><?=$speakerCompany['POSITION']?></span></div>
                            <div class="tag tag_default"><span><?=$speakerCompany['COMPANY']?></span></div>
                        </div>
                    </div>
                <? } ?>
            <? } ?>
            <? if (!empty($arResult['EVENT_DETAIL']['speaker']['fio'])) { ?>
                <?
                $classMask = '';
                if (!empty($arResult['EVENT_DETAIL']['speaker']['photo'])) {
                    $photoSpeaker = $arResult['EVENT_DETAIL']['speaker']['photo'];
                } else {
                    $photoSpeaker = '/local/templates/bitrix24/images/user-default-avatar.svg';
                    $classMask = 'no_user_photo';
                }
                ?>
            <div class="event-popup__user">
                <div class="event-popup__user-img event-popup__user-img-guest <?=$classMask?>">
                    <img class="img-guest" src="<?=$photoSpeaker?>"/>
                </div>
                <div class="event-popup__user-details">
                    <div class="event-popup__user-name"><?=$arResult['EVENT_DETAIL']['speaker']['fio']?></div>
                    <? if (!empty($arResult['EVENT_DETAIL']['speaker']['description'])) { ?>
                        <div class="event-popup__user-status"><span><?=$arResult['EVENT_DETAIL']['speaker']['description']?></span></div>
                    <? } ?>
                </div>
            </div>
            <? } ?>
            <br>
        <? } ?>

        <?
        //  Показать блок с согласующим руководителем, если:
        //  - в мероприятии отмечено "Запрос согласования с куководителем"
        //  - сотрудник уже зарегистрирован
        //  - поле с руководителем не пустое
        if ($arResult['EVENT_DETAIL']['soglasovanie'] == 'Y' && !$register && !empty($arResult['EVENT_DETAIL']['fio_rukovoditelya'])) {
            $classMask = '';
            if (!empty($arResult['EVENT_DETAIL']['fio_rukovoditelya']['photo'])) {
                $photo = $arResult['EVENT_DETAIL']['fio_rukovoditelya']['photo'];
            } else {
                $photo = '/local/templates/bitrix24/images/user-default-avatar.svg';
                $classMask = 'no_user_photo';
            }
            ?>
            <div class="event-popup__subtitle"><?=Loc::getMessage('BLOCK_MANAGER_SELECT')?></div>
            <div class="event-popup__user">
                <div class="event-popup__user-img <?=$classMask?>"><img
                        src="<?=$photo?>"/></div>
                <div class="event-popup__user-details">
                    <div class="event-popup__user-name"><?=$arResult['EVENT_DETAIL']['fio_rukovoditelya']['fio']?></div>
                    <div class="event-popup__user-status"><?=$arResult['EVENT_DETAIL']['fio_rukovoditelya']['workposition']?><br>
                        <? if (!empty($arResult['EVENT_DETAIL']['fio_rukovoditelya']['department'][1])) { ?>
                            <span class="tag tag_default"><?=$arResult['EVENT_DETAIL']['fio_rukovoditelya']['department'][1]['NAME']?></span>
                        <? } ?>
                    </div>
                </div>
            </div>
        <? } ?>


        <?
        //  Показать выбора руководителя, если:
        //  - отмечено в мероприятии "Запрос согласования руководителя"
        //  - ещё нет регистрации
        if ($arResult['EVENT_DETAIL']['soglasovanie'] == 'Y' && $arResult['EVENT_DETAIL']['register'] == true) { ?>
            <div class="event-popup__subtitle"><?=Loc::getMessage('BLOCK_MANAGER_SELECT')?><span class="required">*</span></div>
        <?
        $arProperty['SETTINGS']['STARTED_BY'] = CUser::GetID();
        $controlID = "Multiple_" . RandString(6);
        ?>
            <span id="<? echo $controlID ?>_hids"><input type="hidden" name="<? echo $controlName ?>[]"></span>
            <div class="event-popup__manager" id="<? echo $controlID ?>_res"></div>
            <a class="event-popup__addManaged" href="javascript:void(0)" id="single-user-choice<? echo $controlID ?>"><?=Loc::getMessage('BTN_MANAGER_SELECT')?></a>
            <br>
            <script>
              var multiPopup<?echo $controlID?>;
              var singlePopup<?echo $controlID?>;
              var taskIFramePopup<?echo $controlID?>;

              function onMultipleSelect<?echo $controlID?>(arUsers) {
                var hiddens = BX.findChildren(BX('<?echo $controlID?>_hids'), {tagName: 'input'}, true);
                for (var i = 0; i < hiddens.length; i++)
                  hiddens[i].value = '';

                var text = '';
                for (var i = 0; i < arUsers.length; i++) {
                  var arUser = arUsers[i];
                  if (arUser) {
                    if (!hiddens[i]) {
                      hiddens[i] = BX.clone(hiddens[0], true);
                      hiddens[0].parentNode.insertBefore(hiddens[i], hiddens[0]);
                    }
                    hiddens[i].value = arUser.id;
                    text += ` <span class="input-user input-user_event-calendar test3"><span class="input-user__image "> <img class="input-user__img" src="${arUser.photo}"/></span> <span class="input-user__details"><span class="input-user__name">${BX.util.htmlspecialchars(arUser.name)}</span> <span class="input-user__position">${BX.util.htmlspecialchars(arUser.position)}</span></span></span>`;
                  }
                }

                if (singlePopup<?echo $controlID?>) {
                  singlePopup<?echo $controlID?>.close();
                }
                BX("<?echo $controlID?>_res").classList.add('visible');
                BX("<?echo $controlID?>_res").innerHTML = text;
              }

              function ShowSingleSelector<?echo $controlID?>(e) {
                if (!e) e = window.event;

                if (!singlePopup<?echo $controlID?>) {
                  singlePopup<?echo $controlID?> = new BX.PopupWindow("single-employee-popup-<?echo $controlID?>", this, {
                    offsetTop: 1,
                    autoHide: true,
                    content: BX("<?=CUtil::JSEscape($controlID)?>_selector_content"),
                    zIndex: 3000
                  });
                } else {
                  singlePopup<?echo $controlID?>.setBindElement(this);
                }

                if (singlePopup<?echo $controlID?>.popupContainer.style.display != "block")
                  singlePopup<?echo $controlID?>.show();

                return BX.PreventDefault(e);
              }

              function Clear<?echo $controlID?>() {
                O_<?=CUtil::JSEscape($controlID)?>.setSelected();
              }

              BX.ready(function () {
                BX.bind(BX("single-user-choice<?echo $controlID?>"), "click", ShowSingleSelector<?echo $controlID?>);
                BX.bind(BX("clear-user-choice"), "click", Clear<?echo $controlID?>);
              });
            </script>
            <?php
            $APPLICATION->IncludeComponent(
                'bitrix:intranet.user.selector.new',
                '.default',
                [
                    'MULTIPLE' => 'N',
                    'NAME' => $controlID,
                    'VALUE' => [],
                    'POPUP' => 'Y',
                    'ON_CHANGE' => 'onMultipleSelect' . $controlID,
                    'SITE_ID' => SITE_ID,
                    'PROPERTY_SETTINGS' => $arProperty['SETTINGS'] ?: []
                ],
                null,
                ['HIDE_ICONS' => 'Y']
            );
            ?>
        <? } ?>

        <div id="event-popup-fixed">
          <div id="event-popup-fixed-warning"></div>
          <div id="event-popup__btns">
              <? if ($register && !$disabledRegister) { ?>
                  <button class="ui-btn ui-btn-primary js-register-on-event" <? if (!empty($arResult['EVENT_DETAIL']['slots'])) {?> disabled <? } ?> id="js-register-on-event"><?=$arResult['EVENT_DETAIL']['btn']['register']?></button>
              <? } ?>
              <? if ($registerCancel) { ?>
                  <button class="ui-btn ui-btn-primary js-btn-cancel-register" id="<?=$arResult['EVENT_DETAIL']['eventID']?>"><?=$arResult['EVENT_DETAIL']['btn']['cancel']?></button>
              <? } ?>
              <button class="ui-btn ui-btn-secondary" id="button-go-to-calendar"><?=$arResult['EVENT_DETAIL']['btn']['calendar']?></button>
          </div>
        </div>
    </div>
<? } ?>

<script>
  //    Регистрация на мероприятие
  BX.bind(BX('js-register-on-event'), 'click', function (e) {
    e.preventDefault();

    // собираем выбранные слоты
    const checkedNodeSlots = document.querySelectorAll('.slot__item.checked');
    const checkedSlots = [...checkedNodeSlots].map(el => el.innerText);

    const data = {
      'element': '#<?=$controlID?>_hids input', //  Input с ID выбранным согласующим руководителем
      'eventId': '<?=$arResult["EVENT_DETAIL"]["id"]?>', //  ID события календаря
      'eventName': '<?=$arResult["EVENT_DETAIL"]["name"]?>',    //  Название события
      'eventType' : '<?=$arResult['EVENT_DETAIL']['type']['code']?>',   //  Тип события
      'eventCategory': '<?=$arResult['EVENT_DETAIL']['category']['code']?>',    //  Категория события
      'eventFormat': '<?=$arResult['EVENT_DETAIL']['format']['code']?>',    //  Формат события
      'soglasovanie': '<?=$arResult['EVENT_DETAIL']['soglasovanie']?>',    //  Запрос на согласование (Y/N)
      'userId': <?=CUser::GetID()?>,    //  ID регистрирующегося пользователя
      'managerId': 0,   //  ID выбранного согласующего руководителя
      'error': '',  //  Сюда пишем ошибки,
      'apiEvent': 'register',
      'eventPopUpMessage': '',
      'slots': checkedSlots,
    }

    $.each($(data.element), function()
    {
      if (this.value > 0) {
        data.managerId = this.value
      }
    })

    if (data.soglasovanie == 'Y' && data.managerId <= 0) {
      data.error = 'Не выбран руководитель'
    }

    if (data.managerId == data.userId) {
      data.error = 'Нельзя выбрать себя в качестве согласующего руководителя'
    }

    if (data.error.length == 0) {
      $('#js-register-on-event').hide();
      //    Если нет ошибок, то отправляем запрос в API для регистрации на мероприятие
      BX.ajax.runComponentAction('local:calendar', 'register',
        {
          mode: 'class',
          method: 'post',
          data: {
            eventID: data.eventId,
            userManagerID: data.managerId,
            eventType: data.eventType,
            eventCategory: data.eventCategory,
            eventFormat: data.eventFormat,
            slot: data.slots,
          },
        },
      ).then(function (response) {
        if (response.data == true) {
          // if (data.eventType.length > 0) {
          //   if (data.eventType == 'open') {
          //     data.eventPopUpMessage = `Вы успешно записались на мероприятие «${data.eventName}». Позже мы вышлем вам письмо с инструкцией для подключения.`;
          //   }
          //   if (data.soglasovanie == 'Y') {
          //     data.eventPopUpMessage = `Вы успешно подали заявку на регистрацию на мероприятие «${data.eventName}». После согласования вашей заявки со стороны руководителя вам будет выслана инструкция для подключения.`;
          //   }
          // } else {
            //  Если у мероприятия отсутствует тип
            data.eventPopUpMessage = `Вы успешно записались на мероприятие «${data.eventName}»`;
          // }
          //    Закрываем шторку, если регистрация прошла успешно
          BX.SidePanel.Instance.getTopSlider().requestParams.showSuccess = data;
          BX.SidePanel.Instance.close();
        }
        //  VKP2-7637
        if (response.data == 'blocked') {
          data.eventPopUpMessage = `Вы зарегистрированы на мероприятие<br/>${data.eventName}`;
          //    Закрываем шторку, если регистрация прошла успешно
          BX.SidePanel.Instance.getTopSlider().requestParams.showSuccess = data;
          BX.SidePanel.Instance.close();
        }
      })
    } else {
      notification('error', data.error, title = '')
    }
  })


  //    Отмена регистрации на мероприятие
  $('.js-btn-cancel-register').on('click', function(e) {
    e.preventDefault()
    const data = {
      'eventId': $(this).attr('id'),
      'userId': <?=CUser::GetID()?>,
      'eventName': '<?=$arResult["EVENT_DETAIL"]["name"]?>',
      'apiEvent': 'cancel'
    }

    if (data.eventId > 0 && data.userId > 0) {
      BX.ajax.runComponentAction('local:calendar', 'cancel',
        {
          mode: 'class',
          method: 'post',
          data: {
            eventID: data.eventId,
            userId: data.userId,
          },
        },
      ).then(function (response) {
        if (response.data == true) {
          data.eventPopUpMessage = `Вы отменили регистрацию на мероприятие<br/>${data.eventName}`;
          //    Закрываем шторку, если деактивация элемента инфоблока (регистрации на мероприятие) прошла успешно
          BX.SidePanel.Instance.getTopSlider().requestParams.showSuccess = data
          BX.SidePanel.Instance.close()
        }
      })
    }
  })

  // редирект в календарь по клику в кнопку календаря
  const calendarButtonLink = document.querySelector('#button-go-to-calendar');
  calendarButtonLink.addEventListener('click', () => {
    const pos = window.location.href.search("calendar");
    pos ? BX.SidePanel.Instance.close() : window.location.href = '/calendar/';
  })

  const popupNodeEl = document.querySelector('.event-popup');
  const fixedButtonsWarningNodeEl = document.querySelector('#event-popup-fixed-warning');

  // Получение временных слотов
  BX.ajax.runComponentAction('local:calendar', 'detail',
    {
      mode: 'class',
      method: 'post',
      data: {
        eventID: '<?=$arResult["EVENT_DETAIL"]["id"]?>',
      },
    },
  ).then((response) => {
    drawSlots(response.data);
    
    if (response.data.error) {
      fixedButtonsWarningNodeEl.innerHTML = `
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="12" cy="12" r="10.5" stroke="#DE4A2A" stroke-width="2"/>
          <path d="M12 7.5V12" stroke="#DE4A2A" stroke-width="2" stroke-linecap="round"/>
          <circle cx="12" cy="16.5" r="1.5" fill="#DE4A2A"/>
        </svg>
        <span>${response.data.error}</span>`;
    }
  }).catch(error => console.error(error));

  const registerBtn = document.querySelector('#js-register-on-event');

  // Рисуем временные слоты
  const drawSlots = (slots) => {
    if (slots.slots.length >= 1) {
      const slotsBlock = document.createElement("section");
      slotsBlock.id = 'slots';

      <? if (!$register && !empty($arResult['EVENT_DETAIL']['status'])) { ?>
        slotsBlock.classList.add('registered');
      <? } ?>
    
      const slotsBlockTitle = document.createElement("div");
      slotsBlockTitle.className = 'event-popup__subtitle';
      slotsBlockTitle.innerText = 'СЛОТЫ ДЛЯ ЗАПИСИ';
      slotsBlock.appendChild(slotsBlockTitle);

      slots.slots.forEach(el => {
        const slot = document.createElement("button");
        slot.innerText = `${el.time}`;
        
        slot.setAttribute('data-tooltip', el.tooltip);

        let slotClasses = ['slot__item'];
        
        if (el.register === 'N') {
          slotClasses.push('disabled');
        }

        if (el.current_user) {
          slotClasses.push('registered-slot');
        } 

        if (!el.current_user && el.register === 'Y') {
          slotClasses.push('ready-to-register');
        }


        slot.className = [...slotClasses].toString().split(',').join(' ');
        slotsBlock.appendChild(slot);
      });

      popupNodeEl.append(slotsBlock);

      // добавляем тултип на слоты
      const tooltipNodeEl = document.createElement("div");
      tooltipNodeEl.id="event-popup__slots-tooltip";

      popupNodeEl.append(tooltipNodeEl);

      // клики по слотам
      const slotsNodes = document.querySelectorAll('.slot__item:not(.disabled)');
      slotsNodes.forEach(el => {
        el.addEventListener('click', () => {
          const prevChecked = document.querySelector('.slot__item.checked');
          if (prevChecked) {
            if (prevChecked === el) {
              el.classList.toggle('checked');
              registerBtn.setAttribute('disabled', 'disabled');
              return
            } else {
              prevChecked.classList.remove('checked');
            }
          } 
          registerBtn.removeAttribute('disabled');
          el.classList.add('checked');
        });
      });
    }
  }
</script>



