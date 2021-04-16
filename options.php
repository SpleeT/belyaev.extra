<?php
defined('B_PROLOG_INCLUDED') || die;
use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;

$moduleID = 'belyaev.extra';

$aTabs = array(
    array(
        'DIV' => 'belyaev_extra_options',
        'TAB' => Loc::getMessage('BELYAEV_EXTRA_MAIN_TAB'),
        'OPTIONS' => array(
            Loc::getMessage('BELYAEV_EXTRA_MAIN_TAB'),
            array(
                'belyaev_extra_select_tarif_button_enabled',
                Loc::getMessage('BELYAEV_EXTRA_SELECT_TARIF_BUTTON_ENABLED'),
                null,
                array('checkbox'),
            ),
            array(
                'belyaev_extra_select_weight_calculator_enabled',
                Loc::getMessage('BELYAEV_EXTRA_WEIGHT_CALCULATOR'),
                null,
                array('checkbox'),
            ),
            array(
                'belyaev_extra_select_dynamic_carrier_enabled',
                Loc::getMessage('BELYAEV_EXTRA_DYNAMIC_CARRIER'),
                null,
                array('checkbox'),
            ),
            array(
                'belyaev_extra_select_prepayment_assist',
                Loc::getMessage('BELYAEV_EXTRA_PREPAYMENT_ASSIST'),
                null,
                array('checkbox'),
            ),
            array(
                'belyaev_extra_url_to_extrapost',
                Loc::getMessage('BELYAEV_EXTRA_DEFAULT_URL_TO_EXTRAPOST'),
                null,
                array('text')
            ),
            array(
              'belyaev_extra_api_key_extrapost',
              Loc::getMessage('BELYAEV_EXTRA_API_KEY_EXTRAPOST'),
              null,
              array('text')
            ),
            array(
              'belyaev_extra_postal_code_default',
              Loc::getMessage('BELYAEV_EXTRA_POSTAL_CODE_DEFAULT'),
              null,
              array('text', 6)
            )
        )
    )
);

if ($USER->IsAdmin()) {
    if (check_bitrix_sessid() && strlen($_POST['save']) > 0) {
        foreach ($aTabs as $aTab) {
            __AdmSettingsSaveOptions($moduleID, $aTab['OPTIONS']);
        }
        LocalRedirect($APPLICATION->GetCurPageParam());
    }
}

$tabControl = new CAdminTabControl('tabControl', $aTabs);
?>
<form method="POST" action="">
    <? $tabControl->Begin();

    foreach ($aTabs as $aTab) {
        $tabControl->BeginNextTab();
        __AdmSettingsDrawList($moduleID, $aTab['OPTIONS']);
    }

    $tabControl->Buttons(array('btnApply' => false, 'btnCancel' => false, 'btnSaveAndAdd' => false)); ?>

    <?= bitrix_sessid_post(); ?>
    <? $tabControl->End(); ?>
</form>
