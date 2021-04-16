<?php
defined('B_PROLOG_INCLUDED') || die;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Loader;
use Bitrix\Main\EventManager;
use Bitrix\Main\Web\HttpClient;
use belyaev\extra\handler\Assistant;
use belyaev\extra\handler\CrmAction;

Class belyaev_extra extends CModule
{
  var $MODULE_ID = "belyaev.extra";
  var $MODULE_VERSION;
  var $MODULE_VERSION_DATE;
  var $MODULE_NAME;
  var $MODULE_DESCRIPTION;
  var $MODULE_CSS;

  function __construct()
  {
    $arModuleVersion = array();

    include(dirname(__FILE__) . '/version.php');

    if (is_array($arModuleVersion) && array_key_exists("VERSION", $arModuleVersion))
    {
      $this->MODULE_VERSION = $arModuleVersion["VERSION"];
      $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
    }
      $this->MODULE_NAME = Loc::getMessage('BELYAEV_EXTRA.MODULE_NAME');
      $this->MODULE_DESCRIPTION = Loc::getMessage('BELYAEV_EXTRA.MODULE_NAME');
      $this->PARTNER_NAME = Loc::getMessage('BELYAEV_EXTRA.PARTNER_NAME');
      $this->PARTNER_URI = Loc::getMessage('BELYAEV_EXTRA.PARTNER_URI');
    }

  function DoInstall()
  {
    ModuleManager::registerModule($this->MODULE_ID);
    Loader::includeModule($this->MODULE_ID);
    Option::set($this->MODULE_ID, 'VERSION_DB', $this->versionToInt());
    self::checkUserField(); // Установка полей
    $this->InstallFiles();
    $this->InstallEvents();
  }

  function DoUninstall()
  {
    Option::delete($this->MODULE_ID, ["name" => 'VERSION_DB']);
    ModuleManager::unRegisterModule($this->MODULE_ID);
    $this->UnInstallFiles();
    $this->UnInstallEvents();
  }

  function InstallFiles()
  {
      CopyDirFiles(
          __DIR__ . '/files/js',
          $_SERVER["DOCUMENT_ROOT"] . '/local/js/' .$this->MODULE_ID,
          true,
          true
      );
      CopyDirFiles(
          __DIR__ . '/files/ajax',
          $_SERVER["DOCUMENT_ROOT"] . '/ajax/' . $this->MODULE_ID,
          true,
          true
      );
  }

  function UnInstallFiles()
  {
      DeleteDirFilesEx('/local/js/' . $this->MODULE_ID);
      DeleteDirFilesEx('/ajax/' . $this->MODULE_ID);
  }

  function InstallEvents()
  {
    $eventManager = EventManager::getInstance();

    $eventManager->registerEventHandlerCompatible(
        'main',
        'OnBeforeEndBufferContent',
        $this->MODULE_ID,
        Assistant::class,
        'initInstance'
    );
    $eventManager->registerEventHandlerCompatible(
        'crm',
        'OnAfterCrmLeadProductRowsSave',
        $this->MODULE_ID,
        CrmAction::class,
        'calcWeightLeadProducts'
    );
    $eventManager->registerEventHandlerCompatible(
        'crm',
        'OnAfterCrmDealProductRowsSave',
        $this->MODULE_ID,
        CrmAction::class,
        'calcWeightDealProducts'
    );

  }

  function UnInstallEvents()
  {
    $eventManager = EventManager::getInstance();

    $eventManager->unRegisterEventHandler(
        'main',
        'OnBeforeEndBufferContent',
        $this->MODULE_ID,
        Assistant::class,
        'initInstance'
    );
    $eventManager->unRegisterEventHandler(
        'crm',
        'OnAfterCrmLeadProductRowsSave',
        $this->MODULE_ID,
        CrmAction::class,
        'calcWeightLeadProducts'
    );
    $eventManager->unRegisterEventHandler(
        'crm',
        'OnAfterCrmDealProductRowsSave',
        $this->MODULE_ID,
        CrmAction::class,
        'calcWeightDealProducts'
    );
  }

  private function versionToInt()
  {
      return intval(preg_replace('/[^0-9]+/i', '', $this->MODULE_VERSION_DATE));
  }

  private static function checkUserField()
  {
    $entityArr = [
      "CRM_LEAD",
      "CRM_DEAL"
    ];
    foreach ($entityArr as $type) {
      $check = \CUserTypeEntity::GetList(
        false,
        array(
          "ID" => CrmAction::WEIGHT_USERFIELD,
          "ENTITY_ID" => $type
        )
      );
      if(!$check->Fetch()) self::addUserField($type);
    }

  }

  public static function addUserField($entityID)
  {
    try {
      $userFields = new \CUserTypeEntity();
      // Пользовательское поле Общий вес
      $userFields->Add([
        "ENTITY_ID"       => $entityID,
        "FIELD_NAME"      => CrmAction::WEIGHT_USERFIELD,
        "USER_TYPE_ID"    => "double",
        "EDIT_FORM_LABEL" => [
          "ru"  => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_WEIGHT_RU'),
          "en"  => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_WEIGHT_EN')
        ],
        'LIST_COLUMN_LABEL' => array(
          'ru'    => Loc::getMessage('BELYAEV_EXTRA.WEIGHT_NAME_RU'),
          'en'    => Loc::getMessage('BELYAEV_EXTRA.WEIGHT_NAME_EN'),
        ),
        'LIST_FILTER_LABEL' => array(
          'ru'    => Loc::getMessage('BELYAEV_EXTRA.WEIGHT_NAME_RU'),
          'en'    => Loc::getMessage('BELYAEV_EXTRA.WEIGHT_NAME_EN'),
          ),
      ]);
      // Пользовательское поле Сумма предоплата
      $userFields->Add([
        "ENTITY_ID"       => $entityID,
        "FIELD_NAME"      => CrmAction::PREPAYMENT_SUM_USERFIELD,
        "USER_TYPE_ID"    => "integer",
        "EDIT_FORM_LABEL" => [
          "ru"  => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_PREPAYMENT_SUM_RU'),
          "en"  => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_PREPAYMENT_SUM_EN')
        ],
        "LIST_COLUMN_LABEL" => array(
          "ru"    => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_PREPAYMENT_SUM_RU'),
          "en"    => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_PREPAYMENT_SUM_EN'),
        ),
        "LIST_FILTER_LABEL" => array(
          "ru"    => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_PREPAYMENT_SUM_RU'),
          "en"    => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_PREPAYMENT_SUM_EN'),
          ),
      ]);
      // Пользовательское поле Адрес ПВЗ
      $userFields->Add([
        "ENTITY_ID"       => $entityID,
        "FIELD_NAME"      => CrmAction::ADDRESS_OPS_USERFIELD,
        "USER_TYPE_ID"    => "string",
        "EDIT_FORM_LABEL" => [
          "ru"  => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_ADDRESS_OPS_RU'),
          "en"  => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_ADDRESS_OPS_EN')
        ],
        "LIST_COLUMN_LABEL" => array(
          "ru"    => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_ADDRESS_OPS_RU'),
          "en"    => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_ADDRESS_OPS_EN'),
        ),
        "LIST_FILTER_LABEL" => array(
          "ru"    => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_ADDRESS_OPS_RU'),
          "en"    => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_ADDRESS_OPS_EN'),
          ),
      ]);
      // Пользовательское поле Срок доставки
      $userFields->Add([
        "ENTITY_ID"       => $entityID,
        "FIELD_NAME"      => CrmAction::PERIOD_DELIVERY_USERFIELD,
        "USER_TYPE_ID"    => "integer",
        "EDIT_FORM_LABEL" => [
          "ru"  => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_PERIOD_DELIVERY_RU'),
          "en"  => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_PERIOD_DELIVERY_EN')
        ],
        "LIST_COLUMN_LABEL" => array(
          "ru"    => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_PERIOD_DELIVERY_RU'),
          "en"    => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_PERIOD_DELIVERY_EN'),
        ),
        "LIST_FILTER_LABEL" => array(
          "ru"    => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_PERIOD_DELIVERY_RU'),
          "en"    => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_PERIOD_DELIVERY_EN'),
          ),
      ]);
      // Пользовательское поле Себестоимость доставки
      $userFields->Add([
        "ENTITY_ID"       => $entityID,
        "FIELD_NAME"      => CrmAction::NETCOST_DELIVERY_USERFIELD,
        "USER_TYPE_ID"    => "double",
        "EDIT_FORM_LABEL" => [
          "ru"  => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_NETCOST_DELIVERY_RU'),
          "en"  => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_NETCOST_DELIVERY_EN')
        ],
        "LIST_COLUMN_LABEL" => array(
          "ru"    => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_NETCOST_DELIVERY_RU'),
          "en"    => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_NETCOST_DELIVERY_EN'),
        ),
        "LIST_FILTER_LABEL" => array(
          "ru"    => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_NETCOST_DELIVERY_RU'),
          "en"    => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_NETCOST_DELIVERY_EN'),
          ),
      ]);
      $userFields->Add([
        "ENTITY_ID"       => $entityID,
        "FIELD_NAME"      => CrmAction::CARRIER_ID_USERFIELD,
        "USER_TYPE_ID"    => "string",
        "EDIT_FORM_LABEL" => [
          "ru"  => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_ID_CARRIER_RU'),
          "en"  => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_ID_CARRIER_EN')
        ],
        "LIST_COLUMN_LABEL" => array(
          "ru"    => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_ID_CARRIER_RU'),
          "en"    => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_ID_CARRIER_EN'),
        ),
        "LIST_FILTER_LABEL" => array(
          "ru"    => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_ID_CARRIER_RU'),
          "en"    => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_ID_CARRIER_EN'),
          ),
      ]);
      $userFields->Add([
        "ENTITY_ID"       => $entityID,
        "FIELD_NAME"      => CrmAction::CARRIER_TITLE_USERFIELD,
        "USER_TYPE_ID"    => "enumeration",
        "EDIT_FORM_LABEL" => [
          "ru"  => Loc::getMessage('BELYAEV_EXTRA.CARRIER_RU'),
          "en"  => Loc::getMessage('BELYAEV_EXTRA.CARRIER_EN')
        ],
        "LIST_COLUMN_LABEL" => array(
          "ru"    => Loc::getMessage('BELYAEV_EXTRA.CARRIER_RU'),
          "en"    => Loc::getMessage('BELYAEV_EXTRA.CARRIER_EN'),
        ),
        "LIST_FILTER_LABEL" => array(
          "ru"    => Loc::getMessage('BELYAEV_EXTRA.CARRIER_RU'),
          "en"    => Loc::getMessage('BELYAEV_EXTRA.CARRIER_EN'),
          ),
      ]);
      $userFields->Add([
        "ENTITY_ID"       => $entityID,
        "FIELD_NAME"      => CrmAction::SERVICE_TITLE_USERFIELD,
        "USER_TYPE_ID"    => "enumeration",
        "EDIT_FORM_LABEL" => [
          "ru"  => Loc::getMessage('BELYAEV_EXTRA.TARIF_CARRIER_RU'),
          "en"  => Loc::getMessage('BELYAEV_EXTRA.TARIF_CARRIER_EN')
        ],
        "LIST_COLUMN_LABEL" => array(
          "ru"    => Loc::getMessage('BELYAEV_EXTRA.TARIF_CARRIER_RU'),
          "en"    => Loc::getMessage('BELYAEV_EXTRA.TARIF_CARRIER_EN'),
        ),
        "LIST_FILTER_LABEL" => array(
          "ru"    => Loc::getMessage('BELYAEV_EXTRA.TARIF_CARRIER_RU'),
          "en"    => Loc::getMessage('BELYAEV_EXTRA.TARIF_CARRIER_EN'),
          ),
      ]);
      // Only for CRM_DEAL
      if ($entityID == "CRM_DEAL") {
        $userFields->Add([
          "ENTITY_ID"       => $entityID,
          "FIELD_NAME"      => CrmAction::COST_DELIVERY_USERFIELD,
          "USER_TYPE_ID"    => "double",
          "EDIT_FORM_LABEL" => [
            "ru"  => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_NETCOST_DELIVERY_RU'),
            "en"  => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_NETCOST_DELIVERY_EN')
          ],
          "LIST_COLUMN_LABEL" => array(
            "ru"    => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_NETCOST_DELIVERY_RU'),
            "en"    => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_NETCOST_DELIVERY_EN'),
          ),
          "LIST_FILTER_LABEL" => array(
            "ru"    => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_NETCOST_DELIVERY_RU'),
            "en"    => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_NETCOST_DELIVERY_EN'),
            ),
        ]);
        $userFields->Add([
          "ENTITY_ID"       => $entityID,
          "FIELD_NAME"      => CrmAction::DELIVERY_STATUS_USERFIELD,
          "USER_TYPE_ID"    => "string",
          "EDIT_FORM_LABEL" => [
            "ru"  => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_STATUS_DELIVERY_RU'),
            "en"  => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_STATUS_DELIVERY_EN')
          ],
          "LIST_COLUMN_LABEL" => array(
            "ru"    => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_STATUS_DELIVERY_RU'),
            "en"    => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_STATUS_DELIVERY_EN'),
          ),
          "LIST_FILTER_LABEL" => array(
            "ru"    => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_STATUS_DELIVERY_RU'),
            "en"    => Loc::getMessage('BELYAEV_EXTRA.USERFIELD_STATUS_DELIVERY_EN'),
            ),
        ]);
      }
    } catch (\Throwable $e) {
    }
  }
}
?>
