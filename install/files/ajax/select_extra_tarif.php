<?php
  define('PUBLIC_AJAX_MODE', true);
  define('STOP_STATISTICS', true);
  define('NO_AGENT_CHECK', true);

  require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

  use Bitrix\Main\Loader;
  use Bitrix\Main\Context;
  use Bitrix\Main\Web\Json;
  use Bitrix\Main\ArgumentException;
  use Bitrix\Main\Security\SecurityException;
  use Bitrix\Main\Localization\Loc;
  use belyaev\extra\handler\CrmAction;
  use belyaev\extra\handler\Assistant;
  use Bitrix\Main\Config\Option;

  global $USER;

  try {
    if (!Loader::includeModule('belyaev.extra')) {
        throw new LoaderException(Loc::getMessage('CANT_INCLUDE_MODULE_EXTRA'));
    }
    if (!check_bitrix_sessid()) {
        throw new SecurityException(Loc::getMessage('INCORRECT_SESSID'));
    }
    if (!Context::getCurrent()->getRequest()->isAjaxRequest()) {
        throw new \RuntimeException(Loc::getMessage('NOT_AJAX'));
    }
    if (empty(Option::get('belyaev.extra', 'belyaev_extra_postal_code_default'))) {
        echo Json::encode(array('ERROR' => Loc::getMessage('DEFAULT_INDEX_IS_EMPTY')), JSON_UNESCAPED_UNICODE);
        throw new ArgumentException(Loc::getMessage('DEFAULT_INDEX_IS_EMPTY'));
    }
    if (empty(Option::get('belyaev.extra', 'belyaev_extra_api_key_extrapost')) || empty(Option::get('belyaev.extra', 'belyaev_extra_url_to_extrapost'))) {
        echo Json::encode(array('ERROR' => Loc::getMessage('EXTRA_API_SETTINGS_EMPTY')), JSON_UNESCAPED_UNICODE);
        throw new ArgumentException(Loc::getMessage('EXTRA_API_SETTINGS_EMPTY'));
    }
    $result = false;
    $entityID = $_REQUEST['id'];
    $entityType = $_REQUEST['type'];
    $action = $_REQUEST['action'];
    if($entityID && $entityType && $action) {
      //Обработка обновлений сущности
      if ($action == "updateEntity") {
        $dataFields = $_REQUEST['data'];
        $extraData = @$dataFields["ANOTHER_FOR_AJAX"];
        if (!$extraData) unset($dataFields["ANOTHER_FOR_AJAX"]);
        if(!empty($extraData)) {
          $dataFields[CrmAction::ADDRESS_OPS_USERFIELD] = ($extraData['address']) ? $extraData['address'] : false;
          $dataFields[CrmAction::NETCOST_DELIVERY_USERFIELD] = ($extraData['cost']) ? $extraData['cost'] : false;
          $dataFields[CrmAction::PERIOD_DELIVERY_USERFIELD] = ($extraData['term']) ? $extraData['term'] : false;
        }
        $result = CrmAction::setEntityData($entityType, $entityID, $dataFields);
        echo Json::encode($dataFields, JSON_UNESCAPED_UNICODE);
      }
      //Обработка и вывод индекса, расчет веса
      if ($action == "getEntityIndex") { // Получаем индекс
        $getData = CrmAction::getEntityData($entityType, $entityID);
        $prepayment = true;
        if (Option::get('belyaev.extra', 'belyaev_extra_select_prepayment_assist') == 'Y') {
          // Проверяем, если сумма предоплаты пуста или меньше суммы предоплаты - ставим НАЛОЖЕННЫЙ платеж
          $prepSumField = $getData[CrmAction::PREPAYMENT_SUM_USERFIELD];
          $opportunity = $getData['OPPORTUNITY'];
          if (empty($prepSumField) || $opportunity > $prepSumField) $prepayment = false;
        }
        if (!empty(@$getData["CONTACT_ADDRESS"][1]['POSTAL_CODE'])) {
          $result = Assistant::getExtrapostTarifs($getData["CONTACT_ADDRESS"][1]['POSTAL_CODE'], $getData[CrmAction::WEIGHT_USERFIELD], $prepayment);
          echo Json::encode($result, JSON_UNESCAPED_UNICODE);
        } elseif (!empty(@$getData["ADDRESS_POSTAL_CODE"])) {
          $result = Assistant::getExtrapostTarifs($getData["ADDRESS_POSTAL_CODE"], $getData[CrmAction::WEIGHT_USERFIELD], $prepayment);
          echo Json::encode($result, JSON_UNESCAPED_UNICODE);
        } else {
          echo Json::encode(array('ERROR' => Loc::getMessage('ADDRESS_POSTAL_CODE')), JSON_UNESCAPED_UNICODE);
        }
      }
    } else {
      echo Json::encode(array('ERROR' => Loc::getMessage('INCORRECT_DATA_REQUEST')), JSON_UNESCAPED_UNICODE);
    }

  } catch (\Throwable $e) {
      echo Json::encode(array('ERROR' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
  }


  require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php');
?>
