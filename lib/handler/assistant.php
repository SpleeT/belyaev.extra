<?php

namespace belyaev\extra\handler;
use Bitrix\Main\Application;
use Bitrix\Main\Page\Asset;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Json;

class Assistant
{
  // Проверка на Лид и сделку, для других страниц добавить их URI
  // при ручном обновлении фрейма не подгрузится
  const CRM_DETAIL_PAGE = [
    '/lead/details/',
    '/deal/details/'
  ];
  /**
  * Добавление вспомогательных файлов в загрузку шаблона
  */
  public static function initInstance ()
  {
    if (!Loader::includeModule('belyaev.extra')) {
        throw new LoaderException(Loc::getMessage('CANT_INCLUDE_MODULE_EXTRA'));
    }
    try {
      if (Option::get('belyaev.extra', 'belyaev_extra_select_tarif_button_enabled') == 'Y') {
          if(self::detectCrmDetailPage()) self::addSelectButton();
      }
    } catch (\Throwable $e) {
    }
  }

  private static function addSelectButton() {
    $asset = Asset::getInstance();
    $asset->addJs('/local/js/belyaev.extra/extrapostnode.js');
    $asset->addCss('/local/modules/belyaev.extra/src/css/extra_tarif.css', true);
  }

  private static function detectCrmDetailPage() {
    $curPage = $GLOBALS['APPLICATION']->GetCurPage();
    $result = false;
    foreach (self::CRM_DETAIL_PAGE as $accesPage) {
      if (strpos($curPage, $accesPage) !== false) $result = true;
    }
    return $result;
  }
  /**
  * Расчет дистанции по долготе и широте
  * @param array $point1 Формат массива [0] => Широта [1] => Долгота
  * @param array $point2 Формат массива [0] => Широта [1] => Долгота
  * @param string $type  M ИЛИ K! Метры или километры
  * @return float расстояние
  */
  private static function calcDistance(array $point1, array $point2, $type) {
    $lt1 = $point1[0];
    $lt2 = $point2[0];
    $lg1 = $point1[1];
    $lg2 = $point2[1];
    $theta = $lg1 - $lg2;
    $miles = (sin(deg2rad($lt1)) * sin(deg2rad($lt2))) + (cos(deg2rad($lt1)) * cos(deg2rad($lt2)) * cos(deg2rad($theta)));
    $miles = acos($miles);
    $miles = rad2deg($miles);
    $miles = $miles * 60 * 1.1515;
    $kilometers = $miles * 1.609344;
    $meters = $kilometers * 1000;
    $data = [
      "K" => $kilometers,
      "M" => $meters,
      "Mi" => $miles
    ];
    return number_format($data[$type],2);
  }

  /**
  * Метод получения тарифов, логика их получения описана в нем. Исключена Частная почта и письмо
  * @param string $clientPostalCode Индекс клиента
  * @param float $entityWeight Общий вес товаров, по умолчанию 300
  * @param bool $prepayment Проверяет на доступность наложенного платежа в СДЭКе
  * @return array массив доступных и не исключенных в логике метода тарифов
  */
  public static function getExtrapostTarifs($clientPostalCode, $entityWeight = 0, $prepayment = true)
  {
    $EXTRA_API_KEY = Option::get('belyaev.extra', 'belyaev_extra_api_key_extrapost');
    $EXTRA_URI = Option::get('belyaev.extra', 'belyaev_extra_url_to_extrapost');
    $DEFAULT_INDEX = Option::get('belyaev.extra', 'belyaev_extra_postal_code_default');
    $WEIGHT = (empty($entityWeight)) ? 300 : $entityWeight; // DEFAULT VALUE
    $servicesArray = array();

    $agent = new HttpClient();
    $agent->setHeader('Content-Type', 'application/json', true);
    $agent->setHeader('Authorization', "Basic ".$EXTRA_API_KEY, true);
    $responseRate = $agent->get("{$EXTRA_URI}rates?from={$DEFAULT_INDEX}&to={$clientPostalCode}&weight={$WEIGHT}");
    $getHomePoint = $agent->get("{$EXTRA_URI}geo/ops/{$clientPostalCode}/coords");
    $getHomePoint = json_decode($getHomePoint);
    // Некий эксепшн если вылезла ошибка на стороне Extrapost
    if (!empty(json_decode($responseRate)->message)) {
      $servicesArray = ["ERROR" => json_decode($responseRate)->message];
      return $servicesArray;
    }
    $parse = explode("<|>", $responseRate);
    // Далее обработка идет под конкретные задачи, тут можно формировать свои условия выбора тарифа.
    foreach ($parse as $key => $value) {
      $decodeData = json_decode($value, 1);
      foreach ($decodeData as $key => $tarifs) {
        if(empty($tarifs) || $tarifs['carrier'] == "PrivatePost" || $tarifs['service'] == "Letter") continue;
        $shipTotal = round($tarifs['rate']);
        if($tarifs['message']) {
          $check = strpos($tarifs['message'], 'однозначно идентифицировать город');
          if ($check !== false) continue;
          $tarifs['title'] = "{$tarifs['title']} - {$tarifs['message']}";
          $shipTotal = rand('9999', '999999'); //Для сортировки ставим рандомные цифры больших значений
        }
        // Для отправки в data-value требуется рандомное число заменить на error
        $dataValueTotal = ($shipTotal > 9999) ? "error" : $shipTotal;
        $extraData = "{$tarifs['carrier']}:{$tarifs['service']}:{$dataValueTotal}";
        $tarifs['dataExtra'] = $extraData;
        $tarifs['rateTotal'] = $shipTotal;
        $servicesArray[$shipTotal] = $tarifs;
      }
    }
    ksort($servicesArray);
    $responseShops = $agent->get("{$EXTRA_URI}geo/parcelshops/{$clientPostalCode}");
    $parseShops = explode("<|>", $responseShops);
    foreach ($parseShops as $key => $value) {
      $decodeShops = json_decode($value, 1);
      foreach ($decodeShops as $key => $shops) {
        if($shops['properties']['carrier'] == "RussianPost") continue;
        if($shops['properties']['cash_on_delivery'] == false && $prepayment = false) continue;
        $getEndPoint = $shops['geometry']['coordinates'];
        $calcDist = self::calcDistance($getHomePoint, $getEndPoint, "K");
        $shops['distance'] = $calcDist;
        $shopsArray[] = $shops;
      }
    }
    usort($shopsArray, function($a,$b){
      return $a['distance'] <=> $b['distance'];
    });
    if(count($shopsArray)>5) $shopsArray = array_slice($shopsArray,0,5);
    foreach ($servicesArray as $key => $value) {
      $service = $value['service'];
      switch ($service) {
        case 'FacilityToFacility':
          $servicesArray[$key]['OPS'] = $shopsArray;
          break;
        case 'ExpressLightFacilityToFacility':
          $servicesArray[$key]['OPS'] = $shopsArray;
          break;
        case 'EconomyFacilityToFacility':
          $servicesArray[$key]['OPS'] = $shopsArray;
          break;
      }
    }
    if(empty($servicesArray)) {
      $servicesArray = ['ERROR' => "Ответ не был получен, обратитесь к администратору или перезагрузите страницу"];
    }
    return $servicesArray;
  }
}

?>
