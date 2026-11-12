<?php

declare(strict_types=1);

$GLOBALS['TL_DCA']['tl_module']['palettes']['gb_booking'] = '{title_legend},name,headline,type;{gb_legend},gbCalendar,gbTypes,gbPrivacy;{gb_payment_legend:hide},gbConsent,gbWithdrawalPage;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes']['gb_manage'] = '{title_legend},name,headline,type;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';

$GLOBALS['TL_DCA']['tl_module']['fields'] += [
    'gbCalendar' => [
        'inputType' => 'select',
        'foreignKey' => 'tl_gb_calendar.title',
        'eval' => ['mandatory' => true, 'includeBlankOption' => true, 'submitOnChange' => true, 'tl_class' => 'w50'],
        'sql' => 'int(10) unsigned NOT NULL default 0',
        'relation' => ['type' => 'hasOne', 'load' => 'lazy'],
    ],
    'gbTypes' => [
        'inputType' => 'checkbox',
        'eval' => ['multiple' => true, 'tl_class' => 'clr'],
        'sql' => 'blob NULL',
    ],
    'gbPrivacy' => [
        'inputType' => 'textarea',
        'eval' => ['allowHtml' => true, 'rows' => 3, 'tl_class' => 'clr'],
        'sql' => 'text NULL',
    ],
    'gbConsent' => [
        'inputType' => 'textarea',
        'eval' => ['allowHtml' => true, 'rows' => 3, 'tl_class' => 'clr'],
        'sql' => 'text NULL',
    ],
    'gbWithdrawalPage' => [
        'inputType' => 'pageTree',
        'foreignKey' => 'tl_page.title',
        'eval' => ['fieldType' => 'radio', 'tl_class' => 'clr'],
        'sql' => 'int(10) unsigned NOT NULL default 0',
        'relation' => ['type' => 'hasOne', 'load' => 'lazy'],
    ],
];
