<?php
namespace belyaev\extra\handler;

use Bitrix\Main\Loader;
use Bitrix\Main\Context;
use Bitrix\Main\Web\Json;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Security\SecurityException;
use Bitrix\Main\Localization\Loc;
use Bitrix\Crm\EntityRequisite;
use Bitrix\Main\Config\Option;

/**
 * Статические методы для взаимодействия с crm
 */
class CrmAction
{
  /**
  * Константы с названиями пользовательских полей. Добавляются при первой установке модуля и не удаляются
  */
  const WEIGHT_USERFIELD = "UF_BELYAEV_EXTRA_WEIGHT_FIELD";
  const PREPAYMENT_SUM_USERFIELD = "UF_BELYAEV_EXTRA_PREPAYMENT_SUM";
  const ADDRESS_OPS_USERFIELD = "UF_BELYAEV_EXTRA_ADDRESS_OPS";
  const PERIOD_DELIVERY_USERFIELD = "UF_BELYAEV_EXTRA_DELIVERY_PERIOD";
  const NETCOST_DELIVERY_USERFIELD = "UF_BELYAEV_EXTRA_NETCOST_DELIVERY";

  /**
  * Получение полей сущности по ID
  * @param entity Сущность "DEAL" or "LEAD"
  * @param entityID Идентификатор сущности
  * @return array поля сущности заданные в методе
  */
  public static function getEntityData($entity, $entityID)
  {
    $entityMethod = self::selectTypeOfEntity($entity);
    if(!$entityMethod) exit;
    $entityData = $entityMethod::GetListEx(
        array(),
        array('=ID' => $entityID),
        false,
        false,
        array('ID','CONTACT_ID','ADDRESS_POSTAL_CODE', self::WEIGHT_USERFIELD, self::PREPAYMENT_SUM_USERFIELD, 'OPPORTUNITY')
    )->Fetch();
    if($contactID = $entityData["CONTACT_ID"]) {
      $addresses = (new EntityRequisite)->getList([
        "filter" => [
          "ENTITY_ID"       => $contactID,
          "ENTITY_TYPE_ID"  => \CCrmOwnerType::Contact
        ]
      ]);
      $entityData["CONTACT_ADDRESS"] = EntityRequisite::getAddresses($addresses->fetch()['ID']);
    }
    return $entityData;
  }

  /**
  * Функция для обновления сущности
  * @param array $data Данные в формате метода ССrm{Entity}::Update
  * @return bool ответ метода ядра, bool
  */
  public static function setEntityData($entity, $entityID, $data)
  {
    $entityMethod = self::selectTypeOfEntity($entity);
    if(!$entityMethod) exit;
    $entityData = $entityMethod->Update($entityID, $data);
    return $entityData;
  }

  private static function calcWeightProducts($id, $productArr = array(), $entity)
  {
    if (empty($productArr)) exit;
    $productIds = array();
    $result = 0;
    foreach ($productArr as $product) {
      $productIds[] = $product['PRODUCT_ID'];
    }
    $arrOfWeights = self::getWeightProducts($productIds);
    foreach ($productArr as $product) {
      $result += $product['QUANTITY'] * $arrOfWeights[$product['PRODUCT_ID']];
    }
    if($result) self::setEntityData($entity, $id, [
      self::WEIGHT_USERFIELD => round($result,3)*1000
    ]);
  }

  public static function calcWeightDealProducts($event, $productArr = array())
  {
    if (Option::get('belyaev.extra', 'belyaev_extra_select_weight_calculator_enabled') == 'Y') {
      $result = self::calcWeightProducts($event, $productArr, "DEAL");
    }
  }

  public static function calcWeightLeadProducts($event, $productArr = array())
  {
    if (Option::get('belyaev.extra', 'belyaev_extra_select_weight_calculator_enabled') == 'Y') {
      $result = self::calcWeightProducts($event, $productArr, "LEAD");
    }
  }
  /**
  * Статический метод используется для расчета веса. Настройте его под свои товары.
  * В данном случае вес находится в поле PROPERTY_239, используйте свое.
  * Если вес у Вас в граммах, в методе calcWeightProducts уберите *1000
  * Так же PROPERTY_239_VALUE требуется заменить на свои значения
  * @param array $productIds массив ID товаров
  * @return array Возвращает вес для каждого товара из изначального массива
  */
  private static function getWeightProducts($productIds)
  {
    $arrOfWeights = array();
    $getProducts = \CCrmProduct::GetList(
      false,
      array("ID" => $productIds, "ACTIVE"=>"Y"),
  		array('ID', 'PROPERTY_239')
    );
    while($wFetch = $getProducts->Fetch()) {
      $arrOfWeights[$wFetch['ID']] = $wFetch['PROPERTY_239_VALUE'];
    }
    return $arrOfWeights;
  }

  /**
  * Статический метод для определения типа сущности
  * @param string $entity "DEAL" or "LEAD"
  * @return class Возвращает класс для работы с сущностью
  */
  private static function selectTypeOfEntity($entity)
  {
    $resultMethod = false;
    if($entity == "LEAD") $resultMethod = new \CCrmLead;
    if($entity == "DEAL") $resultMethod = new \CCrmDeal;
    return $resultMethod;
  }
}

?>
