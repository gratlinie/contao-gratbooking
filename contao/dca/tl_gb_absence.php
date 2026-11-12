<?php

declare(strict_types=1);

use Contao\DataContainer;
use Contao\DC_Table;

/*
 * Abwesenheit eines Kalenders (Urlaub, Fortbildung …): An diesen Tagen bietet keine Terminart Termine an.
 * Bestehende Buchungen bleiben unberührt.
 */
$GLOBALS['TL_DCA']['tl_gb_absence'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'ptable' => 'tl_gb_calendar',
        'enableVersioning' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'pid,endDate' => 'index',
            ],
        ],
    ],
    'list' => [
        'sorting' => [
            'mode' => DataContainer::MODE_PARENT,
            'fields' => ['startDate DESC'],
            'headerFields' => ['title', 'timezone'],
            'panelLayout' => 'limit',
        ],
        'operations' => [
            'edit',
            'copy',
            'delete',
            'show',
        ],
    ],
    'palettes' => [
        'default' => '{absence_legend},title,startDate,endDate',
    ],
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'pid' => [
            'foreignKey' => 'tl_gb_calendar.title',
            'sql' => 'int(10) unsigned NOT NULL default 0',
            'relation' => ['type' => 'belongsTo', 'load' => 'lazy'],
        ],
        'tstamp' => [
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'title' => [
            'inputType' => 'text',
            'eval' => ['maxlength' => 128, 'tl_class' => 'clr'],
            'sql' => "varchar(128) NOT NULL default ''",
        ],
        'startDate' => [
            'inputType' => 'text',
            'eval' => ['rgxp' => 'date', 'mandatory' => true, 'datepicker' => true, 'tl_class' => 'w50 wizard'],
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'endDate' => [
            'inputType' => 'text',
            'eval' => ['rgxp' => 'date', 'mandatory' => true, 'datepicker' => true, 'tl_class' => 'w50 wizard'],
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
    ],
];
