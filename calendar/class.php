<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use CUser;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Errorable;
use Bitrix\Main\Loader;
use Bitrix\Main\ErrorCollection;
use Local\Iblock\Helper as IblockHelper;
use Local\Portal\Inner\Orm\D7\UserTable;
use Local\User\Helper as UserHelper;
use Local\Iblock\Helper as IB;
use Bitrix\Main\Localization\Loc;

class Calendar extends CBitrixComponent implements Controllerable, Errorable
{
    protected $errorCollection;

    private static $userId;

    private static $iBlockRegEvent;

    private $arDefaultUrlTemplates404 = [
        'index' => '/',
        'detail' => '#EVENT_ID#/',
    ];

    /**
     * Логика заполнения $arParams
     * @param $arParams
     * @return mixed
     */
    public function onPrepareComponentParams($arParams)
    {
        Loader::includeModule('calendar');

        global $USER;
        self::$userId = $USER->GetID();

        self::$iBlockRegEvent = IB::getIblockIdByCode('reg_event');
        $arParams['USER_ID'] = CCalendar::GetCurUserId();
        //  Записываем ID инфоблока регистраии на мероприятия
        $arParams['IBLOCK_ID_REG_EVENT'] = IB::getIblockIdByCode('reg_event');
        //  записываем ID инфоблока списка мероприятий
        $arParams['IBLOCK_ID_LIST_EVENTT'] = IB::getIblockIdByCode('list_event');

        $this->errorCollection = new ErrorCollection();

        return parent::onPrepareComponentParams($arParams);
    }

    public function getErrors()
    {
        return $this->errorCollection->toArray();
    }

    public function configureActions()
    {
    }

    public function getErrorByCode($code)
    {
        return $this->errorCollection->getErrorByCode($code);
    }

    /**
     * Получаем категории
     * @return array|false
     */
    public function categoryAction()
    {
        return self::getHLData('CalendarCategory');
    }

    /**
     * Получаем типы мероприятий
     * @return array|false
     */
    public function typeAction()
    {
        return self::getHLData('CalendarTypes');
    }

    /**
     * Получаем формат мероприятий
     * @return array|false
     */
    public function formatAction()
    {
        return self::getHLData('CalndarFormat');
    }

    /**
     * Получаем приоритеты мероприятий
     * @return array|false
     */
    public function priorityAction()
    {
        return self::getHLData('CalendarPriority');
    }

    /**
     * Возвращаем список мероприятий
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public function eventlistAction()
    {
        $arResult = [];
        $arFilter = [];
        global $USER;

        $arParams = self::request();

        if (empty($arParams['duration'])) return false;

        $duration = $arParams['duration'];

        $currentDate = strtotime(date('Y-m-d'));

        //  Собираю события по диапазону и другим фильтрам
        $arFilter = [
            '!PROPERTY_ID_ELEMENTA_KALENDARYA' => false,
            '>=ELEMENT.DATE_FROM_TS_UTC' => strtotime(date('d.m.Y H:i:s', $arParams['dateform'])),
            '<=ELEMENT.DATE_TO_TS_UTC' => strtotime(date('d.m.Y H:i:s', $arParams['dateto']).' +1 day'),
            'ACTIVE' => 'Y',
            '!PROPERTY_PUBLISHED' => false
        ];

        if (is_array($arParams['category']) && in_array('business_training', $arParams['category'])) {
            $products = false;
            if (!empty($arParams['products'])) {
                $products = $arParams['products'];
            }
            $category = array_diff($arParams['category'], array('business_training'));
            $arFilter[] = [
                "LOGIC" => "OR",
                [
                    '=PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_CATEGORY_EVENT' =>  ['business_training'],
                    '=PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_PRODUCTS' => $products
                ],
                [
                    '=PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_CATEGORY_EVENT' =>  $category,
                    '=PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_PRODUCTS' => false
                ]
            ];
        } else if(!empty($arParams['category'])) {
            $arFilter['=PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_CATEGORY_EVENT'] = $arParams['category'];
        }

        if (!empty($arParams['type'])) {
            $arFilter['=PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_TYPE_EVENT'] = $arParams['type'];
        }
        if (!empty($arParams['format'])) {
            $arFilter['=PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_FORMAT_EVENT'] = $arParams['format'];
        }
        if (!empty($arParams['priority'])) {
            $arFilter['=PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_PRIORITY_EVENT'] = $arParams['priority'];
        }

        $iBlockIDListEvents = IB::getIblockIdByCode('list_event');

        //  Если нет достука к списку и пользователь не админ, то показываем только открытые мероприятия
        $obPermission = new CIBlockSectionRights($iBlockIDListEvents, 0);
        $arPermission = $obPermission->GetRights();
        if (!empty($arPermission)) {
            foreach ($arPermission as $itemPermission) {
                $userPermissionId = preg_replace("/[^,.0-9]/", '', $itemPermission['GROUP_CODE']);
                $arUserPermission[$userPermissionId] = $userPermissionId;
            }
        }

        $rsUsers = UserTable::getList([
                                          'select' => ['ID', 'WORK_POSITION', 'UF_DEPARTMENT'],
                                          'filter' => ['=ID' => $this->arParams['USER_ID']],
                                      ]);

        if ($arUser = $rsUsers->Fetch()) {
            // Получаем список подразделений
            $nIblockID = IblockHelper::getIblockIdByCode('departments');
            $arDepartmentsByID = IblockHelper::getSections($nIblockID, 'ID');

            // Получаем структуру по ID текущего подразделения
            $arOrganizations = IblockHelper::getTreeSections(
                $arUser['UF_DEPARTMENT'][0],
                $arDepartmentsByID,
                0,
                [],
                true
            );

            $organizationIds = [];
            foreach ($arOrganizations as $arOrganization) {
                $organizationIds[] = $arOrganization['ID'];
            }

            if (!empty($organizationIds)) {
                $arFilter[] = [
                    "LOGIC" => "OR",
                    [
                        '=PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_PODRAZDELENIYA_DLYA_KOTORYKH_OTKRYT_DOSTUP' =>  $organizationIds
                    ],
                    [
                        '=PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_PODRAZDELENIYA_DLYA_KOTORYKH_OTKRYT_DOSTUP' => false
                    ]
                ];
            }
        }

        if (!empty($arUserPermission)) {
            if (!in_array($this->arParams['USER_ID'], $arUserPermission)) {
                $arFilter['!=PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_TYPE_EVENT'] = 'close';
            }
        }
        if ($USER->IsAdmin()) {
            unset($arFilter['!=PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_TYPE_EVENT']);
        }

        $arEvents['FILTER'] = self::getEvents($arFilter, $arParams, $duration);

        //  Собираю события входящие в промежуток времени и другим фильтрам
        //  Например для событий которые нужно отображить сгодня, а наличются они неделю назад и заканчиваются через неделю
        $arFilter = [
            '!PROPERTY_ID_ELEMENTA_KALENDARYA' => false,
            '<=ELEMENT.DATE_FROM_TS_UTC' => $currentDate,
            '>=ELEMENT.DATE_TO_TS_UTC' => $currentDate,
            'ACTIVE' => 'Y',
            '!PROPERTY_PUBLISHED' => false
        ];
        $arEvents['CURRENT'] = self::getEvents($arFilter, $arParams, $duration);

        foreach ($arEvents as $type => $arItems) {
            foreach ($arItems as $date => $events) {
                foreach ($events as $eventID => $item) {
                    $arResult[$date][$eventID] = $item;
                }
            }
        }
        return $arResult;
    }

    /**
     * Детальная информация по мероприятию
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public function detailAction($eventID = 0)
    {
        global $USER;
        $arResult = [];
        $arParams = self::request();
        if ($arParams['eventID'] <= 0 && $eventID > 0) {
            $arParams['eventID'] = $eventID;
        }
        if ($arParams['eventID'] > 0) {
            //  Текущая дата
            $dateReal = date('Y-m-d H:i:s');

            $arCategory = self::getHLData('CalendarCategory');
            $arTypes = self::getHLData('CalendarTypes');
            $arFormat = self::getHLData('CalendarFormat');
            $arPriority = self::getHLData('CalendarPriority');
            $arProduct = self::getHLData('CalendarProduct');
            $arAudience = self::getHLData('CalendarAudience');
            //712 - Список мероприятий
            $listEventsClass = \Local\Portal\Inner\Orm\D7\IblockElementTable::createEntity(712, false, "N")->getDataClass();
            //708 - Регистрации на мероприятия
            $listEventsRegisterClass = \Local\Portal\Inner\Orm\D7\IblockElementTable::createEntity(708, false, "N")->getDataClass();

            $result = $listEventsClass::getList(
                [
                    'filter' => [
                        '=ID' => $arParams['eventID'],
                        'ACTIVE' => 'Y'
                    ],
                    'select' => [
                        'ID',
                        'NAME',
                        'CREATED_BY',
                        'ACTIVE',
                        'IBLOCK_ID',
                        'RESPONSIBLE' => 'PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_RESPONSIBLE',
                        'CATEGORY' => 'PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_CATEGORY_EVENT',
                        'FORMAT' => 'PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_FORMAT_EVENT',
                        'TYPE' => 'PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_TYPE_EVENT',
                        'PRIORITY' => 'PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_PRIORITY_EVENT',
                        'DATE_FROM' => 'ELEMENT.DATE_FROM',
                        'DATE_TO' => 'ELEMENT.DATE_TO',
                        'COMMENT' => 'PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_COMMENT',
                        'PREVIEW_TEXT' => 'PROPERTY_ID_ELEMENTA_KALENDARYA.PREVIEW_TEXT',
                        'SPEAKER_FIO' => 'PROPERTY_SPEAKER_FIO',
                        'SPEAKER_DESCRIPTION' => 'PROPERTY_SPIKER_ORGANIZATSIYA_OPISANIE',
                        'SPEAKER_FOTO' => 'PROPERTY_SPIKER_FOTO',
                        'SPIKER_SOTRUDNIK_GK_DOM_RF' => 'PROPERTY_SPIKER_SOTRUDNIK_GK_DOM_RF',
                        'REGISTER_CLOSE' => 'PROPERTY_ZAPIS_NA_MEROPRIYATIE_PRIOSTANOVLENA',
                        'ZAPROS_SOGLASOVANIYA_RUKOVODITELYA' => 'PROPERTY_ZAPROS_SOGLASOVANIYA_RUKOVODITELYA',
                        'PRODOLZHITELNOST_SLOTA_MINUT' => 'PROPERTY_PRODOLZHITELNOST_SLOTA_MINUT', // Продолжительность слота (минут)
                        'KOLICHESTVO_ZAPISEY_NA_SLOT' => 'PROPERTY_KOLICHESTVO_ZAPISEY_NA_SLOT', // Количество записей на слот
                        'PRODUCTS' => 'PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_PRODUCTS',
                        'AUDIENCE' => 'PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_AUDIENCE',
                        'TYPE_EVENT' => 'PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_TYPE_EVENT',
                        'PODRAZDELENIYA_DLYA_KOTORYKH_OTKRYT_DOSTUP' => 'PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_PODRAZDELENIYA_DLYA_KOTORYKH_OTKRYT_DOSTUP',
                        'MAKSIMALNO_DOSTUPNOE_KOLICHESTVO_ZAPISEY_NA_MEROPR' => 'PROPERTY_MAKSIMALNO_DOSTUPNOE_KOLICHESTVO_ZAPISEY_NA_MEROPR',
                        'VSEGO_ZAREGISTRIROVANO' => 'PROPERTY_VSEGO_ZAREGISTRIROVANO',
                        'ZAPIS_NE_TREBUETSYA' => 'PROPERTY_ZAPIS_NE_TREBUETSYA'
                    ],
                    'order' => ['ID' => 'ASC'],
                    'runtime' => [
                        'ELEMENT' => [
                            'data_type' => '\Bitrix\Calendar\Internals\EventTable',
                            'reference' => [
                                '=this.PROPERTY_ID_ELEMENTA_KALENDARYA' => 'ref.ID'
                            ],
                            'join_type' => 'LEFT'
                        ],
                        'REGISTER' => [
                            'data_type' => $listEventsRegisterClass,
                            'reference' => [
                                '=this.ID' => 'ref.ID'
                            ],
                            'join_type' => 'LEFT'
                        ]
                    ]
                ]
            );
            while ($arItem = $result->fetch()) {
                //  Сохраняем максимальное количество записей на слот
                if ($arItem['KOLICHESTVO_ZAPISEY_NA_SLOT'] > 0) {
                    $arResult['error'] = Loc::getMessage('BTN_MSG_COUNT_SLOT', ['#COUNT_USER_TO_SLOT#' => $arItem['KOLICHESTVO_ZAPISEY_NA_SLOT']]);
                }
                //  Запретить регистрацию, если максимальное доступное количество записей на мероприятие равно
                //  количеству зарегистрированных на мероприятие
                $maxCountRegister = $arItem['MAKSIMALNO_DOSTUPNOE_KOLICHESTVO_ZAPISEY_NA_MEROPR'];
                $countRegister = $arItem['VSEGO_ZAREGISTRIROVANO'];
                $countRegisterCheck = true;
                if ($maxCountRegister > 0 && $countRegister > 0) {
                    if ($maxCountRegister == $countRegister) {
                        $countRegisterCheck = false;
                    }
                }

                //  Запрещаем регистрацию, если выставлен параметр "Запись не требуется"
                ($arItem['ZAPIS_NE_TREBUETSYA'] == 'Y') ? $registerStop = false : $registerStop = true;

                ($arItem['ZAPROS_SOGLASOVANIYA_RUKOVODITELYA'] == 'Y') ? $arResult['soglasovanie'] = 'Y' : $arResult['soglasovanie'] = 'N';
                $arResult['id'] = $arItem['ID'];
                $arResult['comment'] = $arItem['COMMENT'];
                $arResult['preview_text'] = $arItem['PREVIEW_TEXT'];
                $arResult['name'] = $arItem['NAME'];
                //  Регистрация приостановлена
                $arResult['register_disabled'] = ($arItem['REGISTER_CLOSE'] == 'Y') ? 'Y' : 'N';


                if (!empty($arItem['CATEGORY'])) {
                    if (!empty($arCategory)) {
                        foreach ($arCategory as $arCategoryItem) {
                            if ($arCategoryItem['code'] == $arItem['CATEGORY']) {
                                $arResult['category']  = $arCategoryItem;
                            }
                        }
                    }
                }
                if (!empty($arItem['TYPE'])) {
                    $arResult['type'] = $arTypes[$arItem['TYPE']];
                } else {
                    $arResult['type'] = $arTypes['open'];
                }
                if (!empty($arItem['FORMAT'])) {
                    $arResult['format'] = $arFormat[$arItem['FORMAT']];
                }

                //  Продукты мероприятий
                if (!empty($arItem['PRODUCTS'])) {
                    $arResult['products'] = $arProduct[$arItem['PRODUCTS']];
                }

                $arResult['departments_access'] = true;

                if (!empty($arItem['PODRAZDELENIYA_DLYA_KOTORYKH_OTKRYT_DOSTUP'])) {
                    $rsUsers = UserTable::getList([
                                                      'select' => ['ID', 'WORK_POSITION', 'UF_DEPARTMENT'],
                                                      'filter' => ['=ID' => $this->arParams['USER_ID']],
                                                  ]);

                    if ($arUser = $rsUsers->Fetch()) {
                        // Получаем список подразделений
                        $nIblockID = IblockHelper::getIblockIdByCode('departments');
                        $arDepartmentsByID = IblockHelper::getSections($nIblockID, 'ID');

                        // Получаем структуру по ID текущего подразделения
                        $arOrganizations = IblockHelper::getTreeSections(
                            $arUser['UF_DEPARTMENT'][0],
                            $arDepartmentsByID,
                            0,
                            [],
                            true
                        );

                        $organizationIds = [];
                        foreach ($arOrganizations as $arOrganization) {
                            $organizationIds[] = $arOrganization['ID'];
                        }

                        $arResult['departments_access'] = false;
                        foreach ($arItem['PODRAZDELENIYA_DLYA_KOTORYKH_OTKRYT_DOSTUP'] as $departmentsAccessId) {
                            if (in_array($departmentsAccessId, $organizationIds)) {
                                $arResult['departments_access'] = true;
                            }
                        }
                    }
                }

                //  Целевая аудитория мероприятий
                if (!empty($arItem['AUDIENCE'])) {
                    $arResult['audience'] = [];
                    foreach ($arItem['AUDIENCE'] as $audience) {
                        $arResult['audience'][] = $arAudience[$audience];
                    }
                }

                if (!empty($arItem['PRIORITY'])) {
                    $arResult['priority'] = $arPriority[$arItem['PRIORITY']];
                }

                //  Получаем разницу в днях между датой начала и окончания мероприятия
//                $diffetenceDays = self::countDayToDate($arItem['DATE_FROM'], $arItem['DATE_TO']) + 1;
                //  Если событие длится больше 1 дня, то показываем диапазон дат
//                if ($diffetenceDays > 1) {
//                    if (!empty($arItem['DATE_FROM']) && !empty($arItem['DATE_TO'])) {
//                        $arResult['date'] = [
//                            'date_from' => self::dateEventFormat($arItem['DATE_FROM'], false, $arItem['CATEGORY']),
//                            'date_to' => self::dateEventFormat($arItem['DATE_TO'], false, $arItem['CATEGORY']),
//                        ];
//                    }
//                } else {
                    //  Если мероприятие длится 1 день, показываем сокращённую дату
                    $arResult['date'] = [
                        'date_to' => self::dateEventFormat([$arItem['DATE_FROM'], $arItem['DATE_TO']], true, $arItem['CATEGORY'])
                    ];
//                }

                $arResult['created_by'] = $arItem['CREATED_BY'];
                //  Компания
                $arCompany = UserHelper::getUserDepartment($arItem['RESPONSIBLEID']);
                (!empty($arCompany)) ? $company = $arCompany[1]['NAME'] : $company = '';

                //  Организаторы
                $manager[$arItem['RESPONSIBLEID']] = [
                    'PHOTO' => CFile::GetPath($arItem['RESPONSIBLEPERSONAL_PHOTO']),
                    'FIO' => $arItem['RESPONSIBLELAST_NAME'] . ' ' . $arItem['RESPONSIBLENAME'] . ' ' . $arItem['RESPONSIBLESECOND_NAME'],
                    'WORKPOSITION' => $arItem['RESPONSIBLEWORK_POSITION'],
                    'ID' => $arItem['RESPONSIBLEID'],
                    'COMPANY' => $company
                ];
                $arResult['organizers'] = $manager;

                $arResult['speaker'] = [
                    'fio' => $arItem['SPEAKER_FIO'],
                    'description' => $arItem['SPEAKER_DESCRIPTION'],
                    'photo' => (!empty($arItem['SPEAKER_FOTOSUBDIR'])) ? ' /upload/' . $arItem['SPEAKER_FOTOSUBDIR'] . '/' . $arItem['SPEAKER_FOTOFILE_NAME'] : '',
                ];
                $arResult['date_to'] = ConvertDateTime($arItem['DATE_TO'], 'YYYY-MM-DD HH:MI:SS');

                // Организацтор из компании
                if ($arItem['SPIKER_SOTRUDNIK_GK_DOM_RFID'] > 0) {
                    $arResult['speakerCompany'][$arItem['SPIKER_SOTRUDNIK_GK_DOM_RFID']] = [
                        'FIO' =>   $arItem['SPIKER_SOTRUDNIK_GK_DOM_RFLAST_NAME'].' '.$arItem['SPIKER_SOTRUDNIK_GK_DOM_RFNAME'].' '.$arItem['SPIKER_SOTRUDNIK_GK_DOM_RFSECOND_NAME'],
                        'ID' => $arItem['SPIKER_SOTRUDNIK_GK_DOM_RFID'],
                        'POSITION' => $arItem['SPIKER_SOTRUDNIK_GK_DOM_RFWORK_POSITION'],
                        'PHOTO' => CFIle::GetPath($arItem['SPIKER_SOTRUDNIK_GK_DOM_RFPERSONAL_PHOTO']),
                    ];
                    $userCompany = UserHelper::getUserDepartment($arItem['SPIKER_SOTRUDNIK_GK_DOM_RFID']);
                    if ($userCompany[1]) {
                        $arResult['speakerCompany'][$arItem['SPIKER_SOTRUDNIK_GK_DOM_RFID']]['COMPANY'] = $userCompany[1]['NAME'];
                    }
                }
                //  Получаем разбивку интервалов слотов
                $arResult['slots'] = self::getTimeSlots($arItem);
            }

            //  Получение детальной информации о зарегистрированном мроприятии
            //  Получаем статусы регистрации на мероприятия ($eventListID)
            $result2 = $listEventsRegisterClass::getList(
                [
                    'filter' => [
                        '=PROPERTY_MEROPRIYATIE.ID' => $arResult['id'],
                        '=CREATED_BY' => $this->arParams['USER_ID'],
                        'ACTIVE' => 'Y'
                    ],
                    'select' => [
                        'ID',
                        'NAME',
                        'MEROPRIYATIE_ID' => 'PROPERTY_MEROPRIYATIE.ID',
                        'PROPERTY_MEROPRIYATIE.NAME',
                        'ACTIVE' => 'ACTIVE',
                        'FIO_RUKOVODITELYA' => 'PROPERTY_FIO_RUKOVODITELYA',
                        'STATUS' => 'PROPERTY_STATUS_PROTSESSA_SOGLASOVANIYA',
                        'DATA_FROM' => 'PROPERTY_DATA_MEROPRIYATIYA',
                    ],
                    'order' => ['ID' => 'ASC'],
                    'runtime' => [
                        'ELEMENT' => [
                            'data_type' => '\Bitrix\Calendar\Internals\EventTable',
                            'reference' => [
                                '=this.ID' => 'ref.ID'
                            ],
                            'join_type' => 'LEFT'
                        ],
                    ]
                ]
            );
            if ($arItem = $result2->fetch()) {
                $arResult['eventID'] = $arItem['ID'];
                $arResult['fio_rukovoditelya'] = [
                    'id' => $arItem['FIO_RUKOVODITELYAID'],
                    'fio' => $arItem['FIO_RUKOVODITELYALAST_NAME'] . ' ' . $arItem['FIO_RUKOVODITELYANAME'] . ' ' . $arItem['FIO_RUKOVODITELYASECOND_NAME'],
                    'last_name' => $arItem['FIO_RUKOVODITELYALAST_NAME'],
                    'name' => $arItem['FIO_RUKOVODITELYANAME'],
                    'second_name' => $arItem['FIO_RUKOVODITELYASECOND_NAME'],
                    'photo' => ($arItem['FIO_RUKOVODITELYAPERSONAL_PHOTO'] > 0) ? CFile::GetPath(
                        $arItem['FIO_RUKOVODITELYAPERSONAL_PHOTO']
                    ) : '',
                    'workposition' => $arItem['FIO_RUKOVODITELYAWORK_POSITION'],
                    'department' => UserHelper::getUserDepartment($arItem['FIO_RUKOVODITELYAID']),
                ];
                $arResult['date_from'] = $arItem['DATA_FROM'];
                $arResult['active'] = $arItem['ACTIVE'];
                $arResult['status'] = self::getStatusEvent($arItem);
                $arResult['register_check'] = true;
            }
        }

        $arResult['btn'] = [
            'register' => Loc::getMessage('BTN_REGISTER'),
            'calendar' => Loc::getMessage('BTN_CALENDAR'),
            'cancel' => Loc::getMessage('BTN_CANCEL'),
        ];

        //  Разрешать или запретить регистрацию и отмену регистрации
        //  false - запратить регистрацию
        //  register - запрет регистрации
        //  register_cancel - запрет отмены регистрации
        $arResult['register'] = true;
        $arResult['register_cancel'] = false;
        $arResult['error'] = '';

        //  Если запись на мероприятия приостановлена
        if ($arResult['register_disabled'] == 'Y' && $arResult['register_check']) {
            $arResult['register'] = false;
            $arResult['register_cancel'] = true;
            $arResult['error'] = Loc::getMessage('REGISTER_CLOSE');
            $arResult['slots'] = self::registerCloseSlots($arResult['slots']);
            return $arResult;
        }

        //  Запретить регистрацию, если максимально доступное количество записей равно количеству зарегистрированных
        if ($countRegisterCheck == false) {
            $arResult['register'] = false;
            $arResult['max_register'] = true;
            $arResult['error'] = Loc::getMessage('REGISTER_EVENT_MAX_REGISTER');
            return $arResult;
        }

        //  Запрещаем регистрацию, если выставлен параметр "Запись не требуется"
        if (!$registerStop) {
            $arResult['register'] = false;
            $arResult['register_cancel'] = false;
            $arResult['error'] = Loc::getMessage('REGISTER_NO');
        }

        //  Запретить, если текущая дата больше даты окончания мероприятия
        if ($dateReal > $arResult['date_to']) {
            $arResult['register'] = false;
            $arResult['register_cancel'] = false;
            $arResult['error'] = Loc::getMessage('REGISTER_ERROR_DATE');
            if ($arResult['organizers'][self::$userId]) {
                $arResult['status'][strtotime($arResult['date_from'])] = [
                    'code' => 'organizator',
                    'name' => Loc::getMessage('REGISTER_EVENT_ORGANIZATOR'),
                ];
            }
            //  Запрещаем выбирать любые слоты
            $arCloseAllSlots = self::closeAllSlots($arResult['slots']);
            unset($arResult['slots']);
            $arResult['slots'] = $arCloseAllSlots;
            return $arResult;
        }

        //  Если уже есть регистрация на мероприятие от текущего пользователя
        if ($arResult['active'] == 'Y') {
            $arResult['register'] = false;
            $arResult['register_cancel'] = true;
            $arResult['error'] = Loc::getMessage('REGISTER_EVENT_OLD');
            return $arResult;
        }

        //  Организатор не может зарегистрироваться на своё мероприятие
        if ($arResult['organizers'][self::$userId]) {
            $arResult['register'] = false;
            $arResult['register_cancel'] = false;
            $arResult['status'][strtotime($arResult['date_from'])] = [
                'code' => 'organizator',
                'name' => Loc::getMessage('REGISTER_EVENT_ORGANIZATOR'),
            ];
            $arResult['error'] = Loc::getMessage('REGISTER_ERROR_ORGANIZATOR');

            //  Запрещаем выбирать любые слоты
            $arCloseAllSlots = self::closeAllSlots($arResult['slots']);
            unset($arResult['slots']);
            $arResult['slots'] = $arCloseAllSlots;
            return $arResult;
        }

        //  Спикер не может зарегистрироваться на своё мероприятие
        if (is_array($arResult['speakerCompany'])) {
            foreach ($arResult['speakerCompany'] as $speakerID => $speaker) {
                if ($speakerID == self::$userId) {
                    $arResult['register'] = false;
                    $arResult['register_cancel'] = false;
                    $arResult['error'] = Loc::getMessage('REGISTER_ERROR_SPEAKER');
                    $arResult['status'][strtotime($arResult['date_from'])] = [
                        'code' => 'speaker',
                        'name' => Loc::getMessage('REGISTER_EVENT_SPEAKER'),
                    ];
                    //  Запрещаем выбирать любые слоты
                    $arCloseAllSlots = self::closeAllSlots($arResult['slots']);
                    unset($arResult['slots']);
                    $arResult['slots'] = $arCloseAllSlots;
                    return $arResult;
                }
            }
        }

        return $arResult;
    }

    /**
     * Запрет регистрации на все с лоты
     * @param $arSlotsTime - массив слотов
     * @return mixed
     */
    private static function closeAllSlots($arSlotsTime)
    {
        if (!empty($arSlotsTime)) {
            $arResult = [];
            foreach ($arSlotsTime as $key => $slotItem) {
                $arSlotsTime[$key]['register'] = 'N';
                $arSlotsTime[$key]['tooltip'] = Loc::getMessage('REGISTER_EVENT_LIMIT_SLOT');
            }
            return $arSlotsTime;
        }
        return false;
    }

    /**
     * Запрет регистрации, если запись на мероприятие приостановлена
     * @param $arSlotsTime
     * @return false
     */
    private static function registerCloseSlots($arSlotsTime)
    {
        if (!empty($arSlotsTime)) {
            $arResult = [];
            foreach ($arSlotsTime as $key => $slotItem) {
                $arSlotsTime[$key]['register'] = 'N';
                $arSlotsTime[$key]['tooltip'] = Loc::getMessage('REGISTER_CLOSE');
            }
            return $arSlotsTime;
        }
        return false;
    }

    /**
     * Запрет регистрации на слоты без изменений tooltip
     * @param $arSlotsTime
     * @return false
     */
    private static function closeAllSlotsNoToltip($arSlotsTime)
    {
        if (!empty($arSlotsTime)) {
            $arResult = [];
            foreach ($arSlotsTime as $key => $slotItem) {
                $arSlotsTime[$key]['register'] = 'N';
                (!empty($slotItem['tooltip'])) ? $arSlotsTime[$key]['tooltip'] = $slotItem['tooltip'] : $arSlotsTime[$key]['tooltip'] = '';
            }
            return $arSlotsTime;
        }
        return false;
    }

    /**
     * Дату начала и дату окончания слота разбиваем по заранее переданным интервалам в минутах
     * Возвращаем массив интервалов
     * @param $arParams
     * @return array|false
     */
    private static function getTimeSlots($arParams)
    {
        //  ID мероприятия из списка
        $eventId = $arParams['ID'];
        //  Дата и время начала слота
        $dateStart = $arParams['DATE_FROM'];
        //  дата и время окончания слота
        $dateEnd = $arParams['DATE_TO'];
        //  Продолжительность слота в минутах
        $intervalDateSlot = $arParams['PRODOLZHITELNOST_SLOTA_MINUT'];
        //  Максимальное количество записей на слот
        ($arParams['KOLICHESTVO_ZAPISEY_NA_SLOT'] > 0) ? $countRecord = $arParams['KOLICHESTVO_ZAPISEY_NA_SLOT'] : $countRecord = 0;
        if ($eventId > 0 && !empty($dateStart) && !empty($dateEnd) && $intervalDateSlot > 0) {
            $arResult = [];

            //  Получаем регистрации пользователей на слоты
            $arSlots = self::getRegisterEventList($eventId);

            //  Собираем даты начала и окончания слота
            $start_date = date_create($dateStart);
            $end_date = date_create($dateEnd);
            $end_date = $end_date->modify('+' . $intervalDateSlot . ' minutes');

            //  Получаем интервалы разбивки даты начала и окончания по длительности слота в минутах
            $interval = DateInterval::createFromDateString($intervalDateSlot . ' minutes');
            $daterange = new DatePeriod($start_date, $interval, $end_date);

            //  Проверка зарегистрирован ли пользователь хотя бы на один слот
            $checkRegisterSlot = false;
            //  Собираем результирующий массив слотов
            foreach ($daterange as $key => $date) {
                $dateSlot = $date->format('H:i');
                $arResult[$key]['time'] = $dateSlot;

                //  Есть ли у пользователя хотя бы одна регистрация
                $oneRegister = false;
                if ($arSlots['USER_REGISTER_SLOTS'][self::$userId]) $oneRegister = true;

                //  Нельзя регистрироваться на слоты, если количество записей на слоты не указано
                if ($countRecord <= 0) {
                    return self::closeAllSlotsNoToltip($arResult);
                }
                //  Если у пользователя есть регистрация на любой из слотов
                if (in_array($dateSlot, $arSlots['USER_REGISTER_SLOTS'][self::$userId])) {
                    //  Нельзя регистрироваться на слот, если пользователь на него уже зарегистрирован
                    $arResult[$key]['register'] = 'N';
                    $arResult[$key]['current_user'] = self::$userId;
                    $arResult[$key]['tooltip'] = Loc::getMessage('REGISTER_EVENT_CURRENT_USER');
                } else if (!in_array($dateSlot, $arSlots['USER_REGISTER_SLOTS'][self::$userId])) {
                    //  Если пользователь не зарегистрирован на текущий слот
                    $checkCountRegisterSlot = $arSlots['CHECK_SLOTS']['check'][$dateSlot];
                    if ($checkCountRegisterSlot > 0 && $checkCountRegisterSlot == $countRecord) {
                        //  Если на текущий слот достигнут лимит регистраций
                        $arResult[$key]['register'] = 'N';
                        $arResult[$key]['tooltip'] = Loc::getMessage('BTN_MSG_COUNT_SLOT', ['#COUNT_USER_TO_SLOT#' => $countRecord, '#PIP#' => self::numberWr($countRecord, ['человек', 'человека', 'человек'])]);
                    } else {
                        $arResult[$key]['register'] = 'Y';
                        $arResult[$key]['tooltip'] = Loc::getMessage('REGISTER_ANOTHER_SLOT');
                    }
                    if (!$oneRegister && !$arSlots['USER_REGISTER_SLOTS'][self::$userId] && $checkCountRegisterSlot < $countRecord) {
                        $arResult[$key]['register'] = 'Y';
                        $arResult[$key]['tooltip'] = Loc::getMessage('EMPTY_TIME_SLOT');
                    }
                }
            }

            return $arResult;
        }
        return false;
    }

    /**
     * Получаем регистрации на слоты
     * @param $iEventId
     * @return array|false
     */
    private static function getRegisterEventList($iEventId)
    {
        if (self::$iBlockRegEvent > 0) {
            $arResult = [];
            $obj = CIBlockElement::GetList(
                [],
                [
                    'ACTIVE' => 'Y',
                    'IBLOCK_ID' => self::$iBlockRegEvent,
                    '=PROPERTY_MEROPRIYATIE' => $iEventId
                ],
                false,
                false,
                [
                    'CREATED_BY',
                    'PROPERTY_SLOT'
                ]
            );
            while ($arRes = $obj->Fetch()) {
                $slot = unserialize($arRes['PROPERTY_SLOT_VALUE']);
                if (!empty($slot['VALUE'])) {
                    foreach ($slot['VALUE'] as $time) {
                        if ($arResult['CHECK_SLOTS']['time'][$time]) {
                            $arResult['CHECK_SLOTS']['check'][$time] = $arResult['CHECK_SLOTS']['check'][$time] + 1;
                        } else {
                            $arResult['CHECK_SLOTS']['time'][$time] = $time;
                            $arResult['CHECK_SLOTS']['check'][$time] = 1;
                        }
                    }
                    $arResult['USER_REGISTER_SLOTS'][$arRes['CREATED_BY']] = $slot['VALUE'];
                }
            }
            if (!empty($arResult)) {
                return $arResult;
            }
            return false;
        }
        return false;
    }

    /**
     * Склонение слова в зависимости от числа
     * @param $number - число
     * @param $arWr - слово, которое нужно склонять
     * @return string
     */
    private static function numberWr($number, $arWr)
    {
        if (!empty($arWr) && $number >= 0) {
            $num = $number % 100;
            if ($num > 19) {
                $num = $num % 10;
            }
            $str = '';
            switch ($num) {
                case 1:  $str .= $arWr[0]; break;
                case 2:
                case 3:
                case 4:  $str .= $arWr[1]; break;
                default: $str .= $arWr[2]; break;
            }
            return $str;
        }
    }

    /**
     * Регистрация на мероприятие
     * @return bool
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public function registerAction($eventID = 0, $userManagerID = 0)
    {
        $arParams = self::request();

        if (empty($arParams['eventID'])) {
            $arParams['eventID'] = $eventID;
        }

        if (empty($arParams['userManagerID'])) {
            $arParams['userManagerID'] = $userManagerID;
        }

        if ($arParams['eventID'] > 0) {

            //  VKP2-7637
            $arStopRegister = CIBlockElement::GetList(
                [],
                [
                    'IBLOCK_ID' => $this->arParams['IBLOCK_ID_LIST_EVENTT'],
                    'ID' => $arParams['eventID']
                ],
                false,
                false,
                [
                    'ID',
                    'NAME',
                    'PROPERTY_ZAPIS_NA_MEROPRIYATIE_PRIOSTANOVLENA',
                ]
            )->Fetch();
            if ($arStopRegister['PROPERTY_ZAPIS_NA_MEROPRIYATIE_PRIOSTANOVLENA_VALUE'] == 'Y') {
                return 'blocked';
            }

            //  Получение информации о мероприятии
            $arEvent = self::getEventToID($arParams['eventID']);

            //  Получение данных пользователя, регистрирующегося на мероприятие
            $arUserData = \Bitrix\Main\UserTable::getList(
                [
                    'filter' => [
                        'ID' => $this->arParams['USER_ID']
                    ],
                    'select' => [
                        'LAST_NAME',
                        'NAME',
                        'SECOND_NAME',
                        'PERSONAL_PROFESSION',
                        'EMAIL'
                    ]
                ]
            )->fetch();

            $arProperty = [];
            //  Получение ID свойства "Мероприятие" (список мероприятий) и запись в него значения
            $meropriyatieFieldID = self::getFieldID($this->arParams['IBLOCK_ID_REG_EVENT'], 'MEROPRIYATIE');
            if ($meropriyatieFieldID > 0) {
                $arProperty[$meropriyatieFieldID] = $arParams['eventID'];
            }

            if ($arParams['userManagerID'] > 0) {
                //  Получение ID свойства "ФИО руководителя"
                $userFioFieldID = self::getFieldID($this->arParams['IBLOCK_ID_REG_EVENT'], 'FIO_RUKOVODITELYA');
                if ($userFioFieldID > 0) {
                    $arProperty[$userFioFieldID] = $arParams['userManagerID'];
                }
            }

            //  олучение ID свойства "ФИО сотрудника"
            $userFioFieldID = self::getFieldID($this->arParams['IBLOCK_ID_REG_EVENT'], 'FIO_SOTRUDNIKA');
            if ($userFioFieldID > 0) {
                $arProperty[$userFioFieldID] = $arUserData['LAST_NAME'] . ' ' . $arUserData['NAME'] . ' ' . $arUserData['SECOND_NAME'];
            }

            //  Получение ID свойства "Должность"
            $workPositionFieldID = self::getFieldID($this->arParams['IBLOCK_ID_REG_EVENT'], 'DOLZHNOST');
            if ($workPositionFieldID > 0) {
                $property[$workPositionFieldID] = $arUserData['PERSONAL_PROFESSION'];
            }
            //  Получение ID свойства "Подразделение"
            $departmentFieldID = self::getFieldID($this->arParams['IBLOCK_ID_REG_EVENT'], 'PODRAZDELENIE');
            if ($departmentFieldID > 0) {
                $arWorkCompanyDepartment = UserHelper::getUserDepartment($this->arParams['USER_ID']);
                foreach ($arWorkCompanyDepartment as $companyItem) {
                    $company .= $companyItem['NAME'] . ' \\ ';
                }
                $company = substr($company, 0, -2);
                $arProperty[$departmentFieldID] = $company;
            }

            //  Получение ID свойства "Электроная почта"
            $userMailFieldID = self::getFieldID($this->arParams['IBLOCK_ID_REG_EVENT'], 'ELEKTRONNAYA_POCHTA');
            if ($userMailFieldID > 0) {
                $arProperty[$workPositionFieldID] = $arUserData['EMAIL'];
            }

            //  Получение ID свойства "Тип мероприятия"
            if (!empty($arParams['eventType'])) {
                $eventTypeFieldID = self::getFieldID($this->arParams['IBLOCK_ID_REG_EVENT'], 'TYPE_EVENT');
                if ($eventTypeFieldID > 0) {
                    $arProperty[$eventTypeFieldID] = $arParams['eventType'];
                }
            }

            //  Получение ID свойства "Слот"
            if (!empty($arParams['slot'])) {
                $eventTypeFieldID = self::getFieldID($this->arParams['IBLOCK_ID_REG_EVENT'], 'SLOT');
                if ($eventTypeFieldID > 0) {
                    $arProperty[$eventTypeFieldID] = $arParams['slot'];
                }
            }

            //  Получение спикеров
            $evenSpikerCompany = self::getFieldID($this->arParams['IBLOCK_ID_REG_EVENT'], 'SPIKER');
            $spikerEvent = '';
            $obj = CIBlockElement::GetList([], ['IBLOCK_ID' => $this->$arParams['IBLOCK_ID_LIST_EVENTT'], 'ID' => $arParams['eventID']], false, false, ['ID', 'NAME', 'PROPERTY_SPIKER_SOTRUDNIK_GK_DOM_RF', 'PROPERTY_SPEAKER_FIO']);
            if ($resSpiker = $obj->GetNext()) {
                if (!empty($resSpiker['PROPERTY_SPEAKER_FIO_VALUE'])) {
                    $spikerEvent .= $resSpiker['PROPERTY_SPEAKER_FIO_VALUE'] . ', ';
                }
                if (!empty($resSpiker['PROPERTY_SPIKER_SOTRUDNIK_GK_DOM_RF_VALUE'])) {
                    foreach ($resSpiker['PROPERTY_SPIKER_SOTRUDNIK_GK_DOM_RF_VALUE'] as $speakerDomRfId) {
                        $arUs = CUser::GetByID($speakerDomRfId)->GetNext();
                        if (!empty($arUs)) {
                            $spikerEvent .= $arUs['LAST_NAME'] . ' ' . $arUs['NAME'] . ' ' . $arUs['SECOND_NAME']. ', ';
                        }
                    }
                }
                if (!empty($spikerEvent)) {
                    $spikerEvent = substr($spikerEvent, 0, -2);
                    $arProperty[$evenSpikerCompany] = $spikerEvent;
                }
            }

            //  Получение ID свойства "Категория мероприятия"
            if (!empty($arParams['eventType'])) {
                $eventCategoryFieldID = self::getFieldID($this->arParams['IBLOCK_ID_REG_EVENT'], 'CATEGORY_EVENT');
                if ($eventCategoryFieldID > 0) {
                    $arProperty[$eventCategoryFieldID] = $arParams['eventCategory'];
                }
            }

            //  Получение ID свойства "Формат мероприятия"
            if (!empty($arParams['eventType'])) {
                $eventFormatFieldID = self::getFieldID($this->arParams['IBLOCK_ID_REG_EVENT'], 'FORMAT_EVENT');
                if ($eventFormatFieldID > 0) {
                    $arProperty[$eventFormatFieldID] = $arParams['eventFormat'];
                }
            }

            $el = new CIBlockElement;

            $arFields = [
                'MODIFIED_BY' => $this->arParams['USER_ID'],
                'IBLOCK_ID' => $this->arParams['IBLOCK_ID_REG_EVENT'],
                'NAME' => 'Заявка',
                'ACTIVE' => 'Y',
                'PROPERTY_VALUES' => $arProperty,
            ];

            $eventID = $el->Add($arFields);

            if ($eventID > 0) {
                $arBizProcTemplateID = self::getBPTemplateID();
                if (!empty($arBizProcTemplateID)) {
                    foreach ($arBizProcTemplateID as $bizProcTemplateID) {
                        self::startBP($eventID, $bizProcTemplateID);
                    }
                }

                //  Обновление данных о мероприятии
                $arFieldsUpdateEvent = [];
                $countRegister = $arEvent['VSEGO_ZAREGISTRIROVANO'] + 1;
                $arFieldsUpdateEvent['VSEGO_ZAREGISTRIROVANO'] = $countRegister;
                if ($countRegister == $arEvent['MAKSIMALNO_DOSTUPNOE_KOLICHESTVO_ZAPISEY_NA_MEROPR']) {
                    $arFieldsUpdateEvent['ZAPIS_NA_MEROPRIYATIE_PRIOSTANOVLENA'] = 'Y';
                }
                if (!empty($arFieldsUpdateEvent)) {
                    self::updateEvent($arParams['eventID'], $arFieldsUpdateEvent);
                }

                if (empty($arError)) {
                    return true;
                }
                return $arError;
            } else {
                return 'Регистрация не успешна';
            }
            return false;

        }
    }

    /**
     * Обновление мданных мероприятия
     * @param $eventID - ID мероприятия
     * @param $arParams
     */
    private function updateEvent($eventID, $arParams)
    {
        $arProperty = [];

        //  Количество зарегистрированных на мероприятие
        if ($arParams['VSEGO_ZAREGISTRIROVANO'] >= 0) {
            $arProperty['VSEGO_ZAREGISTRIROVANO'] = (int)$arParams['VSEGO_ZAREGISTRIROVANO'];
        }

        //  Приостановить запись на мероприятие
        if (!empty($arParams['ZAPIS_NA_MEROPRIYATIE_PRIOSTANOVLENA'])) {
            $arProperty['ZAPIS_NA_MEROPRIYATIE_PRIOSTANOVLENA'] = $arParams['ZAPIS_NA_MEROPRIYATIE_PRIOSTANOVLENA'];
        }

        if (!empty($arProperty)) {
            CIBlockElement::SetPropertyValuesEx($eventID, $this->arParams['IBLOCK_ID_LIST_EVENTT'], $arProperty);
        }
    }

    /**
     * Отмена регистрации на мероприятие
     * @return bool|mixed
     */
    public function cancelAction()
    {
        $arParams = self::request();
        if ($arParams['eventID'] > 0 && $arParams['userId'] > 0) {

            Loader::IncludeModule('bizproc');
            $arBizProcTemplateID = self::getBPTemplateID();

            //  Останавливаю запущенные БП элемента списка
            $arWFId = self::getWFToElementID($arParams['eventID'], $arBizProcTemplateID);
            if (!empty($arWFId) && $arWFId != false)
            {
                foreach ($arWFId as $workwlofID) {
                    CBPDocument::TerminateWorkflow($workwlofID, false, $arErrors);
                }
            }

            //  Обновление данных мероприятия
            $eventID = self::getEventToRegisterId($arParams['eventID']);
            if ($eventID > 0) {
                $arFieldsUpdateEvent = [];

                //  Получение информации о мероприятии
                $arEvent = self::getEventToID($eventID);
                $countRegister = self::getCountRegisterEvent($eventID);

                if ($countRegister >= 0) {
                    $arFieldsUpdateEvent = [
                        'VSEGO_ZAREGISTRIROVANO' => $countRegister - 1
                    ];
                }
                if($arEvent['MAKSIMALNO_DOSTUPNOE_KOLICHESTVO_ZAPISEY_NA_MEROPR'] > 0) {
                    $iMaxCountRegoster = $arEvent['MAKSIMALNO_DOSTUPNOE_KOLICHESTVO_ZAPISEY_NA_MEROPR'];
                    $iCountRegister =  $arEvent['VSEGO_ZAREGISTRIROVANO'];
                    if ($iMaxCountRegoster == $iCountRegister) {
                        $arFieldsUpdateEvent = [
                            'ZAPIS_NA_MEROPRIYATIE_PRIOSTANOVLENA' => 'N'
                        ];
                    }
                }

                //  Обновление данных о мероприятии
                if (!empty($arFieldsUpdateEvent)) {
                    self::updateEvent($eventID, $arFieldsUpdateEvent);
                }
            }

            //  Деактивация записи о регистрации
            $el = new CIBlockElement;
            $arField = ["ACTIVE" => "N"];
            return $el->Update($arParams['eventID'], $arField);
        }
    }

    /**
     * Поиск ID мероприятия по ID регистрации
     * @param $registerId - ID регистрации
     * @return mixed
     */
    private function getEventToRegisterId($registerId)
    {
        if ($registerId > 0) {
            $obj = CIBlockElement::GetList([], ['=IBLOCK_ID' => $this->arParams['IBLOCK_ID_REG_EVENT'], '=ID' => $registerId], false, false, ['PROPERTY_MEROPRIYATIE']);
            if ($arRes = $obj->Fetch()) {
                if ($arRes['PROPERTY_MEROPRIYATIE_VALUE'] > 0) {
                    return $arRes['PROPERTY_MEROPRIYATIE_VALUE'];
                }
            }
        }
    }

    /**
     * Получение количества зарегистрированных на мероприятие
     * @param $eventId - ID мероприятия
     * @return mixed
     */
    private function getCountRegisterEvent($eventId)
    {
        if ($eventId > 0) {
            $obj = CIBlockElement::GetList([], ['=IBLOCK_ID' => $this->arParams['IBLOCK_ID_LIST_EVENTT'], '=ID' => $eventId], false, false, ['PROPERTY_VSEGO_ZAREGISTRIROVANO']);
            if ($arRes = $obj->Fetch()) {
                if ($arRes['PROPERTY_VSEGO_ZAREGISTRIROVANO_VALUE'] > 0) {
                    return $arRes['PROPERTY_VSEGO_ZAREGISTRIROVANO_VALUE'];
                }
            }
        }
    }

    private function getEvents($arFilter, $arParams, $duration)
    {

        $arResult = [];
        \Bitrix\Main\Loader::includeModule('calendar');

        $speakerID = 0;

        //  Получаем мероприятия по фильтру
        //  712 - Список мероприятий
        $listEventsClass = \Local\Portal\Inner\Orm\D7\IblockElementTable::createEntity(712, false, "N")->getDataClass();
        //  708 - Регистрации на мероприятия
        $listEventsRegisterClass = \Local\Portal\Inner\Orm\D7\IblockElementTable::createEntity(708, false, "N")->getDataClass();

        $result = $listEventsClass::getList(
            [
                'filter' => $arFilter,
                'select' => [
                    'ID',
                    'NAME',
                    'CATEGORY' => 'PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_CATEGORY_EVENT',
                    'FORMAT' => 'PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_FORMAT_EVENT',
                    'TYPE' => 'PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_TYPE_EVENT',
                    'PRIORITY' => 'PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_PRIORITY_EVENT',
                    'SPEAKER' => 'PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_SPIKER_SOTRUDNIK_GK_DOM_RF',
                    'RESPONSIBLE' => 'PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_RESPONSIBLE',
                    'PRODUCTS' => 'PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_PRODUCTS',
                    'AUDIENCE' => 'PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_AUDIENCE',
                    'TYPE_EVENT' => 'PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_TYPE_EVENT',
                    'PODRAZDELENIYA_DLYA_KOTORYKH_OTKRYT_DOSTUP' => 'PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_PODRAZDELENIYA_DLYA_KOTORYKH_OTKRYT_DOSTUP',
                    'CATELNDAR_EVENT' => 'ELEMENT.ID',
                    'DATE_FROM' => 'ELEMENT.DATE_FROM',
                    'DATE_TO' => 'ELEMENT.DATE_TO',
                    'DATE_FROM_TS' => 'ELEMENT.DATE_FROM_TS_UTC',
                    'DATE_TO_TS' => 'ELEMENT.DATE_TO_TS_UTC',
                    'REGISTER_CLOSE' => 'PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_ZAPIS_NA_MEROPRIYATIE_PRIOSTANOVLENA',
                    'ZAPROS_SOGLASOVANIYA_RUKOVODITELYA' => 'PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_ZAPROS_SOGLASOVANIYA_RUKOVODITELYA',
                    'MAKSIMALNO_DOSTUPNOE_KOLICHESTVO_ZAPISEY_NA_MEROPR' => 'PROPERTY_MAKSIMALNO_DOSTUPNOE_KOLICHESTVO_ZAPISEY_NA_MEROPR',
                    'VSEGO_ZAREGISTRIROVANO' => 'PROPERTY_VSEGO_ZAREGISTRIROVANO'
                ],
                'order' => ['DATE_FROM' => 'ASC'],
                'runtime' => [
                    'ELEMENT' => [
                        'data_type' => '\Bitrix\Calendar\Internals\EventTable',
                        'reference' => [
                            '=this.PROPERTY_ID_ELEMENTA_KALENDARYA' => 'ref.ID'
                        ],
                        'join_type' => 'LEFT'
                    ],
                    'REGISTER' => [
                        'data_type' => $listEventsRegisterClass,
                        'reference' => [
                            '=this.ID' => 'ref.ID'
                        ],
                        'join_type' => 'LEFT'
                    ]
                ]
            ]
        );

        while ($arItem = $result->fetch()) {
            $date = date("Y-m-d H:i:s", strtotime(ConvertDateTime($arItem['DATE_FROM'], 'YYYY-MM-DD HH:MM:SS')));

            //  Собираем всех спикеров компании
            $arSpeaker[strtotime($date)][$arItem['ID'].'_'.strtotime($arItem['DATE_FROM'])][$arItem['SPEAKERID']] = $arItem['SPEAKERID'];

            //  Собираем организаторов
            $arResponsible[strtotime($date)][$arItem['ID'].'_'.strtotime($arItem['DATE_FROM'])][$arItem['RESPONSIBLEID']] = $arItem['RESPONSIBLEID'];

            //  Если запрос за день
            if ($duration == 'day') {
                //  Запись промежуточных событий
                $inRange_start = ($arItem['DATE_FROM_TS'] <= $arParams['dateform'] && $arItem['DATE_TO_TS'] >= $arParams['dateform']) ? true : false;
                if ($inRange_start) {
                    $arResult[strtotime($date)][$arItem['ID'].'_'.strtotime($arItem['DATE_FROM'])] = self::buildDate($arItem, $duration);
                }
                //  Запись событий 1 дня
                $arResult[strtotime($date)][$arItem['ID'].'_'.strtotime($arItem['DATE_FROM'])] = self::buildDate($arItem, $duration);
                //  Проверяем приостановлена ли запись на мероприятие
                $arResult[strtotime($date)][$arItem['ID'].'_'.strtotime($arItem['DATE_FROM'])]['register_disabled'] = ($arItem['REGISTER_CLOSE'] == 'Y') ? 'Y' : 'N';
                $arResult[strtotime($date)][$arItem['ID'].'_'.strtotime($arItem['DATE_FROM'])]['agreement'] = ($arItem['ZAPROS_SOGLASOVANIYA_RUKOVODITELYA'] == 'Y') ? 'Y' : 'N';
            }

            //  Если запрос за период
            if (in_array($duration, ['week', 'month', 'period'])) {
                //  Сохраняю однодневки
                $arResult[strtotime($date)][$arItem['ID'].'_'.strtotime($arItem['DATE_FROM'])] = self::buildDate($arItem, $duration);
                //  Проверяю является ли текукщий пользователь спикером
                if ($arItem['SPEAKERID'] == self::$userId) {
                    $arResult[strtotime($date)][$arItem['ID'].'_'.strtotime($arItem['DATE_FROM'])]['SPEAKERID'][$arItem['SPEAKERID']] = $arItem['SPEAKERID'];
                }
                $arResult[strtotime($date)][$arItem['ID'].'_'.strtotime($arItem['DATE_FROM'])]['agreement'] = ($arItem['ZAPROS_SOGLASOVANIYA_RUKOVODITELYA'] == 'Y') ? 'Y' : 'N';
                //  Получаю длительность события в днях
                $differenceEventDay = self::countDayToDate($arItem['DATE_FROM_TS'], $arItem['DATE_TO_TS']) + 1;
                //  $differenceEventDay раз сравниваю вхождение даты начала мероприятия с датами начала и окончания по фильтру

                if ($differenceEventDay > 1) {
                    for ($i = 1; $i < $differenceEventDay; $i++) {
                        //  Прибавляю $i дней к дате начала события и узнаю есть ли вхождение в диапазон по фильтру
                        $dateEventStart_srt = strtotime(date("Y-m-d H:i:s", strtotime($arItem['DATE_FROM'].'+ '.$i.' days')));
                        $checkDateStart = self::dateRangeCheck($dateEventStart_srt, $arParams['dateform'], $arParams['dateto']);
                        //  Если вхождени есть
                        if ($checkDateStart) {
                            $arResult[strtotime(date("Y-m-d", strtotime($arItem['DATE_FROM'].'+ '.$i.' days')))][$arItem['ID'].'_'.strtotime($arItem['DATE_FROM'])] = self::buildDate($arItem, $duration);
                        }
                    }
                }

            }
            $eventListID[$arItem['ID']] = $arItem['ID'];
        }

        //  Получаем статусы регистрации на мероприятия
        $result2 = $listEventsRegisterClass::getList(
            [
                'filter' => [
                    '=PROPERTY_MEROPRIYATIE.ID' => $eventListID,
                    '=CREATED_BY' => CUser::GetId(),
                    'ACTIVE' => 'Y'
                ],
                'select' => [
                    'ID',
                    'NAME',
                    'ACTIVE',
                    'MEROPRIYATIE_ID' => 'PROPERTY_MEROPRIYATIE.ID',
                    'PROPERTY_MEROPRIYATIE.NAME',
                    'STATUS' => 'PROPERTY_STATUS_PROTSESSA_SOGLASOVANIYA',
                    'DATA_FROM' => 'PROPERTY_DATA_MEROPRIYATIYA'
                ],
                'order' => ['ID' => 'ASC'],
                'runtime' => [
                    'ELEMENT' => [
                        'data_type' => '\Bitrix\Calendar\Internals\EventTable',
                        'reference' => [
                            '=this.ID' => 'ref.ID'
                        ],
                        'join_type' => 'LEFT'
                    ],
                ]
            ]
        );
        while ($arItem = $result2->fetch()) {
            $eventStatusList[] = self::getStatusEvent($arItem);
        }

        //  Соединяем статусы БП, проверяем на спикеров, орагнизаторов и список событий
        foreach ($arResult as $date => $event) {
            foreach ($event as $dateEvent => $eventDetail) {
                //  Проверяем на спикера
                if ($arSpeaker[$date][$dateEvent][self::$userId]) {
                    $arResult[$date][$dateEvent]['status'] = [
                        'name' => Loc::getMessage('REGISTER_EVENT_SPEAKER'),
                        'code' => 'spiker'
                    ];
                }
                //  Проверяем на организатора
                if ($arResponsible[$date][$dateEvent][self::$userId]) {
                    $arResult[$date][$dateEvent]['status'] = [
                        'name' => Loc::getMessage('REGISTER_EVENT_ORGANIZATOR'),
                        'code' => 'organizator'
                    ];
                }
                //  Проверяем статусы региастрации
                if (!empty($eventStatusList)) {
                    foreach ($eventStatusList as $evStatusValue) {
                        if ($evStatusValue[$dateEvent]) {
                            $arResult[$date][$dateEvent]['status'] = $evStatusValue[$dateEvent];
                        }
                    }
                }
            }
        }

        return $arResult;
    }

    /**
     * Собираем массив события
     * @param $arItem - массив с данными события
     * @param $duration - тип запроса
     * @return array
     */
    private static function buildDate($arItem, $duration)
    {
        $arResult = [];
        $arCategory = self::getHLData('CalendarCategory');
        $arTypes = self::getHLData('CalendarTypes');
        $arFormat = self::getHLData('CalendarFormat');
        $arPriority = self::getHLData('CalendarPriority');
        $arProduct = self::getHLData('CalendarProduct');
        $arAudience = self::getHLData('CalendarAudience');

        $arResult['name'] = $arItem['NAME'];
        $arResult['id'] = $arItem['ID'];
        $arResult['duration'] = $duration;

        //  Категория мероприятия
        if (!empty($arItem['CATEGORY'])) {
            if (!empty($arCategory)) {
                foreach ($arCategory as $arCategoryItem) {
                    if ($arCategoryItem['code'] == $arItem['CATEGORY']) {
                        $arResult['category']  = $arCategoryItem;
                    }
                }
            }
        }

        //  Тип мероприятия
        if (!empty($arItem['TYPE'])) {
            $arResult['type'] = $arTypes[$arItem['TYPE']];
        } else {
            $arResult['type'] = $arTypes['open'];
        }

        //  Формат мероприятия
        if (!empty($arItem['FORMAT'])) {
            $arResult['format'] = $arFormat[$arItem['FORMAT']];
        }

        //  Приоритет мероприятия
        if (!empty($arItem['PRIORITY'])) {
            $arResult['priority'] = $arPriority[$arItem['PRIORITY']];
        }

        //  Продукты мероприятий
        if (!empty($arItem['PRODUCTS'])) {
            $arResult['products'] = $arProduct[$arItem['PRODUCTS']];
        }

        //  Целевая аудитория мероприятий
        if (!empty($arItem['AUDIENCE'])) {
            $arResult['audience'] = [];
            foreach ($arItem['AUDIENCE'] as $audience) {
                $arResult['audience'][] = $arAudience[$audience];
            }
        }
        //  Даты начала и окончания в читабельном виде для морды
        if (!empty($arItem['DATE_FROM']) && !empty($arItem['DATE_TO'])) {
            $arResult['date'] = [
                'date_from' => self::dateEventFormat([$arItem['DATE_FROM'], $arItem['DATE_TO']], false, $arItem['CATEGORY']),
                'date_to' => self::dateEventFormat([$arItem['DATE_FROM'], $arItem['DATE_TO']], false, $arItem['CATEGORY']),
                'date_from_ts' => $arItem['DATE_FROM_TS'],
                'date_to_ts' => $arItem['DATE_TO_TS'],
            ];
            if ($arItem['CATEGORY'] == 'registration_program') {
                $arResult['date'] = [
                    'date_to' => self::dateEventFormat([$arItem['DATE_FROM'], $arItem['DATE_TO']], false, $arItem['CATEGORY']),
                    'date_from_ts' => $arItem['DATE_FROM_TS'],
                    'date_to_ts' => $arItem['DATE_TO_TS'],
                ];
            }
        }
        ($arItem['REGISTER_CLOSE'] == 'Y') ? $arResult['register_disabled'] = 'Y' : $arResult['register_disabled'] = 'N';

        return $arResult;
    }

    /**
     * Проверяем вхождение даты в диапазон
     * @param $date - сравниваемая дата
     * @param $dateStart - дата начала диапазона
     * @param $dateEnd - дата окончания диапазона
     * @return bool
     */
    private static function dateRangeCheck($date, $dateStart, $dateEnd)
    {
        if (!empty($date) && !empty($dateStart) && !empty($dateEnd)) {
            $start_date = $dateStart;
            $end_date = $dateEnd;

            $date = $date;

            $inRange = ($date >= $start_date && $date <= $end_date) ? true : false;
            return $inRange;
        }
        return false;
    }

    /**
     * Получение стутусов от БП
     * @param $arParams
     * @return array|false
     */
    private static function getStatusEvent($arParams)
    {
        if (!empty($arParams)) {
            $arResult = [];
            if (!empty($arParams['STATUSXML_ID'])) {
                $statusName = $arParams['STATUSVALUE'];
                if ($arParams['STATUSXML_ID'] == 'inprogress') {
                    $statusName = Loc::getMessage('REGISTER_EVENT_STATUS_INPROGRESS');
                } else {
                    if ($arParams['STATUSXML_ID'] == 'success') {
                        $statusName = Loc::getMessage('REGISTER_EVENT_STATUS_SUCCESS');
                    }
                }

                $arResult[$arParams['MEROPRIYATIE_ID'].'_'.strtotime($arParams['DATA_FROM'])] = [
                    'code' => $arParams['STATUSXML_ID'],
                    'name' => $statusName,
                ];
            } else {
                if (!empty($arParams['STATUS_REGISTRATSII_NA_MEROPRIYATIEVALUE'])) {
                    $arResult[$arParams['MEROPRIYATIE_ID'].'_'.strtotime($arParams['DATA_FROM'])] = [
                        'code' => $arParams['STATUS_REGISTRATSII_NA_MEROPRIYATIEXML_ID'],
                        'name' => $arParams['STATUS_REGISTRATSII_NA_MEROPRIYATIEVALUE'],
                    ];
                }
            }
            return $arResult;
        }
        return false;
    }

    /**
     * Получение совйств мероприятия по его ID
     * @param $eventID
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    private function getEventToID($eventID)
    {
        $arResult = [];
        if ($eventID > 0) {
            //712 - Список мероприятий
            $listEventsClass = \Local\Portal\Inner\Orm\D7\IblockElementTable::createEntity(712, false, "N")->getDataClass();
            $result = $listEventsClass::getList(
                [
                    'filter' => [
                        '=ID' => $eventID,
                    ],
                    'select' => [
                        'ID',
                        'NAME',
                        'ACTIVE',
                        'CATEGORY' => 'PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_CATEGORY_EVENT',
                        'FORMAT' => 'PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_FORMAT_EVENT',
                        'TYPE' => 'PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_TYPE_EVENT',
                        'PRIORITY' => 'PROPERTY_ID_ELEMENTA_KALENDARYA.PROPERTY_PRIORITY_EVENT',
                        'MAKSIMALNO_DOSTUPNOE_KOLICHESTVO_ZAPISEY_NA_MEROPR' => 'PROPERTY_MAKSIMALNO_DOSTUPNOE_KOLICHESTVO_ZAPISEY_NA_MEROPR',
                        'VSEGO_ZAREGISTRIROVANO' => 'PROPERTY_VSEGO_ZAREGISTRIROVANO',
                    ],
                    'order' => ['ID' => 'ASC'],
                    'runtime' => [
                        'ELEMENT' => [
                            'data_type' => '\Bitrix\Calendar\Internals\EventTable',
                            'reference' => [
                                '=this.PROPERTY_ID_ELEMENTA_KALENDARYA' => 'ref.ID'
                            ],
                            'join_type' => 'LEFT'
                        ]
                    ]
                ]
            );
            while ($arItem = $result->fetch()) {
                $arResult = [
                    'CATEGORY' => $arItem['CATEGORY'],
                    'FORMAT' => $arItem['FORMAT'],
                    'TYPE' => $arItem['TYPE'],
                    'PRIORITY' => $arItem['PRIORITY'],
                    'MAKSIMALNO_DOSTUPNOE_KOLICHESTVO_ZAPISEY_NA_MEROPR' => $arItem['MAKSIMALNO_DOSTUPNOE_KOLICHESTVO_ZAPISEY_NA_MEROPR'],
                    'VSEGO_ZAREGISTRIROVANO' => $arItem['VSEGO_ZAREGISTRIROVANO'],
                ];
            }
        }
        return $arResult;
    }

    /**  Получение массива WorkwlofID по ID заявки на регистрацию (элемент ИБ)
     * @param $elementID - ID заявки на регистрацию
     * @param $arBizProcTemplateID - массив ID шаблонов БП, которые должны запуститься автоматически при создании элемента списка
     * @return array|false
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    private function getWFToElementID($elementID, $arBizProcTemplateID)
    {
        $arResult = [];
        if ($elementID > 0 && !empty($arBizProcTemplateID)) {
            foreach ($arBizProcTemplateID as $bpTemplateID) {
                $state = \Bitrix\Bizproc\Workflow\Entity\WorkflowStateTable::getList(
                    [
                        'filter' => [
                            'STATE' => ['InProgress'],
                            'WORKFLOW_TEMPLATE_ID' => $bpTemplateID,
                            'DOCUMENT_ID' => $elementID
                        ],
                        'select' => [
                            'ID',
                            'DOCUMENT_ID',
                            'STATE',
                        ],
                    ]
                );
                while ($arState = $state->fetch()) {
                    $arResult[$arState['DOCUMENT_ID']] = $arState['ID'];
                }
            }
            if (!empty($arResult)) {
                return $arResult;
            }
        }
        return false;
    }

    /**
     * Получение массива ID шаблонов бизнес-процессов с автозапуском после создания элемента списка
     * @return mixed
     * @throws \Bitrix\Main\LoaderException
     */
    private function getBPTemplateID()
    {
        $arResult = [];
        Loader::IncludeModule('bizproc');
        $nIblockID = Local\Iblock\Helper::getIblockIdByCode('reg_event');
        $arWorkflowTemplates = CBPDocument::GetWorkflowTemplatesForDocumentType(
            [
                'lists',
                'Bitrix\Lists\BizprocDocumentLists',
                'iblock_' . $nIblockID
            ]
        );
        foreach ($arWorkflowTemplates as $arTemplate) {
            if ($arTemplate['AUTO_EXECUTE'] == 1) {
                $arResult[] =  $arTemplate['ID'];
            }
        }
        return $arResult;
    }

    /**
     * Запускает БП для элемента списка
     * @param $elementListID - ID шаблона БП
     * @param $bpTemplateID - ID элемента ИБ (списка)
     * @return string|null
     * @throws \Bitrix\Main\LoaderException
     */
    private function startBP($elementListID, $bpTemplateID)
    {
        Loader::IncludeModule('bizproc');
        $workflowID = CBPDocument::StartWorkflow(
            $bpTemplateID,
            ['lists', 'Bitrix\Lists\BizprocDocumentLists', $elementListID],
            [
                'Employee' => 'user_' . CUser::GetID(),
                'TargetUser' => 1
            ],
            $arError
        );
        return $workflowID;
    }

    /**
     * Получение ID свойства инфоблока по его символьному коду
     * @param $fieldCode
     * @return mixed|string
     */
    private function getFieldID($iBlockId, $fieldCode)
    {
        $id = '';
        if (!empty($fieldCode) && $iBlockId > 0) {
            $objProp = CIBlockProperty::GetList(
                [],
                ['ACTIVE' => 'Y', 'IBLOCK_ID' => $iBlockId, 'CODE' => $fieldCode]
            );
            if ($resProp = $objProp->Fetch()) {
                $id = $resProp['ID'];
            }
        }
        return $id;
    }

    /**
     * Получаем данные из highloadblock
     * @param $code - код хайлоада
     * @return array|false
     */
    private function getHLData($code)
    {
        $arResult = [];
        if (!empty($code)) {
            $arRes = \Local\HighloadBlock\Helper::getDataByCode($code);
            if (!empty($arRes)) {
                foreach ($arRes as $item) {
                    $arResult[$item['UF_XML_ID']] = [
                        'code' => $item['UF_XML_ID'],
                        'name' => $item['UF_NAME'],
                        'id' => $item['ID'],
                    ];

                    if (!empty($item['UF_COLOR'])) {
                        $arResult[$item['UF_XML_ID']]['color'] = $item['UF_COLOR'];
                    }
                    //  Тонкая иконка
                    if ($item['UF_ICON_LITE'] > 0) {
                        $arResult[$item['UF_XML_ID']]['icon'] = CFile::GetPath($item['UF_ICON_LITE']);
                    }

                    //  Жирная иконка
                    if ($item['UF_ICON_BOLD'] > 0) {
                        $arResult[$item['UF_XML_ID']]['iconDetail'] = CFile::GetPath($item['UF_ICON_BOLD']);
                    }
                    //  Белая иконка
                    if ($item['UF_ICON_WHITE'] > 0) {
                        $arResult[$item['UF_XML_ID']]['iconWhite'] = CFile::GetPath($item['UF_ICON_WHITE']);
                    }
                    if (!empty($item['UF_DESCRIPTION'])) {
                        $arResult[$item['UF_XML_ID']]['description'] = $item['UF_DESCRIPTION'];
                    }
                    if ($code == 'CalendarCategory' && $item['UF_XML_ID'] == 'business_training') {
                        $arResProduct = \Local\HighloadBlock\Helper::getDataByCode('CalendarProduct');
                        $arResult[$item['UF_XML_ID']]['products'] = [];
                        foreach ($arResProduct as $itemProduct) {
                            $arResult[$item['UF_XML_ID']]['products'][] = [
                                'id' => $itemProduct['ID'],
                                'name' => $itemProduct['UF_NAME'],
                                'code' => $itemProduct['UF_XML_ID']
                            ];
                        }
                    }
                    //  Если категории мерпориятий, то проставляем сортировку
                    if ($code == 'CalendarCategory') {
                        foreach ($arResult as $key => $category) {
                            if ($key == $item['UF_XML_ID']) {
                                $arResult[$item['UF_SORT']] = $category;
                                unset($arResult[$key]);
                            }
                        }
                    }
                }
            }
        }

        return $arResult;
    }

    /**
     * Приводим дату к формату "07 июля 2022 в 13:00"
     * @param $dateObject - объект/массив даты
     * @return false|string|string[]
     */
    private function dateEventFormat($date, $detail = false, $sCategory = '')
    {
        if ($sCategory == 'registration_program') {
            return Loc::getMessage('REGISTER_EVENT_REGISTER_DEDPLINE_TIME') . mb_strtolower(FormatDate("j F Y | H:i", MakeTimeStamp($date[1])));
        } else {
            if ($detail) {

            }
            if (!empty($date[0]) && !empty($date[1])) {
                $timeStart = mb_strtolower(FormatDate("H:i", MakeTimeStamp($date[0])));
                $timeEnd = mb_strtolower(FormatDate("H:i", MakeTimeStamp($date[1])));
                return mb_strtolower(FormatDate("j F Y", MakeTimeStamp($date[0]))).' | '.$timeStart.' - '.$timeEnd;
            }
        }
        return false;
    }

    /**
     * Получение параметров запроса
     * @return array
     * @throws \Bitrix\Main\SystemException
     */
    private function request()
    {
        $request = Bitrix\Main\HttpApplication::getInstance()->getContext()->getRequest();
        return $request->getPostList()->toArray();
    }

    /**
     * Получение разницы между датами в днях
     * @param $dateStart (timestamp) - дата начала
     * @param $dateEnd (timestamp) - дата окончания
     * @return int
     */
    private function countDayToDate($dateStart, $dateEnd)
    {
        $result = 0;

        $seconds = abs($dateStart - $dateEnd);
        $result = floor($seconds / 86400);
        return $result;
    }

    public function executeComponent()
    {
        global $APPLICATION;
        $APPLICATION->AddChainItem(
          Loc::getMessage('CURRENT_ITEM_CHAIN'),
          $this->arParams['SEF_FOLDER']
        );

        // SEF чпу компонента
        $arSefVariablesParams = $this->arDefaultUrlTemplates404;

        $SEF_FOLDER = $this->arParams['SEF_FOLDER'];

        $arVariables = [];

        $engine = new CComponentEngine($this);

        $arUrlTemplates = CComponentEngine::MakeComponentUrlTemplates(
            $arSefVariablesParams,
            $this->arParams['SEF_FOLDER']
        );

        $arVariableAliases = CComponentEngine::MakeComponentVariableAliases(
            $arSefVariablesParams,
            $this->arParams['VARIABLE_ALIASES']
        );

        $componentPage = $engine->guessComponentPath(
            $this->arParams['SEF_FOLDER'],
            $arUrlTemplates,
            $arVariables
        );

        if (strlen($componentPage) <= 0) {
            $componentPage = 'template';
        }

        $this->arResult = [
            'FOLDER' => $SEF_FOLDER,
            'URL_TEMPLATES' => $arUrlTemplates,
            'VARIABLES' => $arVariables,
            'ALIASES' => $arVariableAliases,
        ];


        if ($this->arResult['VARIABLES']['EVENT_ID'] > 0) {
            $this->arResult['EVENT_DETAIL'] = self::detailAction($this->arResult['VARIABLES']['EVENT_ID']);
        }


        if (!$this->initComponentTemplate()) {
            $this->SetTemplatename('.default');
        }

        $this->includeComponentTemplate($componentPage);
    }
}
