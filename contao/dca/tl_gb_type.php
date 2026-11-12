<?php

declare(strict_types=1);

use Contao\DataContainer;
use Contao\DC_Table;

/*
 * Terminart eines Kalenders, z. B. „Kostenloses Erstgespräch, 30 Minuten“: Dauer, Raster der Anfangszeiten,
 * Puffer, Vorlauf, Horizont und die buchbaren Zeiten je Wochentag. Belegte Zeiten aus Google werden abgezogen.
 */
$GLOBALS['TL_DCA']['tl_gb_type'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'ptable' => 'tl_gb_calendar',
        'enableVersioning' => true,
        'markAsCopy' => 'title',
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'pid' => 'index',
                'alias' => 'index',
            ],
        ],
    ],
    'list' => [
        'sorting' => [
            'mode' => DataContainer::MODE_PARENT,
            'fields' => ['sorting'],
            'headerFields' => ['title', 'googleCalendarId', 'timezone'],
            'panelLayout' => 'search,limit',
        ],
        'operations' => [
            'edit',
            'copy',
            'cut',
            'delete',
            'toggle',
            'show',
        ],
    ],
    'palettes' => [
        'default' => '{title_legend},title,alias,description;{time_legend},duration,slotStep,bufferBefore,bufferAfter,leadTime,horizon,maxPerDay;{hours_legend},hoursMo,hoursTu,hoursWe,hoursTh,hoursFr,hoursSa,hoursSu;{meeting_legend},zoom,location;{publish_legend},published',
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
        'sorting' => [
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'tstamp' => [
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'title' => [
            'search' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 128, 'tl_class' => 'w50'],
            'sql' => "varchar(128) NOT NULL default ''",
        ],
        'alias' => [
            'inputType' => 'text',
            'eval' => ['rgxp' => 'alias', 'unique' => true, 'maxlength' => 64, 'tl_class' => 'w50'],
            'sql' => "varchar(64) NOT NULL default ''",
        ],
        'description' => [
            'inputType' => 'textarea',
            'eval' => ['rows' => 3, 'tl_class' => 'clr'],
            'sql' => 'text NULL',
        ],
        'duration' => [
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'rgxp' => 'natural', 'minval' => 5, 'maxlength' => 4, 'tl_class' => 'w50'],
            'sql' => 'smallint(5) unsigned NOT NULL default 30',
        ],
        'slotStep' => [
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'rgxp' => 'natural', 'minval' => 5, 'maxlength' => 4, 'tl_class' => 'w50'],
            'sql' => 'smallint(5) unsigned NOT NULL default 30',
        ],
        'bufferBefore' => [
            'inputType' => 'text',
            'eval' => ['rgxp' => 'natural', 'maxlength' => 4, 'tl_class' => 'w50'],
            'sql' => 'smallint(5) unsigned NOT NULL default 0',
        ],
        'bufferAfter' => [
            'inputType' => 'text',
            'eval' => ['rgxp' => 'natural', 'maxlength' => 4, 'tl_class' => 'w50'],
            'sql' => 'smallint(5) unsigned NOT NULL default 15',
        ],
        'leadTime' => [
            'inputType' => 'text',
            'eval' => ['rgxp' => 'natural', 'maxlength' => 4, 'tl_class' => 'w50'],
            'sql' => 'smallint(5) unsigned NOT NULL default 24',
        ],
        'horizon' => [
            'inputType' => 'text',
            'eval' => ['rgxp' => 'natural', 'minval' => 1, 'maxlength' => 4, 'tl_class' => 'w50'],
            'sql' => 'smallint(5) unsigned NOT NULL default 56',
        ],
        'maxPerDay' => [
            'inputType' => 'text',
            'eval' => ['rgxp' => 'natural', 'maxlength' => 3, 'tl_class' => 'w50'],
            'sql' => 'smallint(5) unsigned NOT NULL default 0',
        ],
        'hoursMo' => [
            'inputType' => 'text',
            'eval' => ['maxlength' => 128, 'tl_class' => 'w50', 'placeholder' => '09:00-12:00, 14:00-18:00'],
            'sql' => "varchar(128) NOT NULL default ''",
        ],
        'hoursTu' => [
            'inputType' => 'text',
            'eval' => ['maxlength' => 128, 'tl_class' => 'w50', 'placeholder' => '09:00-12:00, 14:00-18:00'],
            'sql' => "varchar(128) NOT NULL default ''",
        ],
        'hoursWe' => [
            'inputType' => 'text',
            'eval' => ['maxlength' => 128, 'tl_class' => 'w50', 'placeholder' => '09:00-12:00, 14:00-18:00'],
            'sql' => "varchar(128) NOT NULL default ''",
        ],
        'hoursTh' => [
            'inputType' => 'text',
            'eval' => ['maxlength' => 128, 'tl_class' => 'w50', 'placeholder' => '09:00-12:00, 14:00-18:00'],
            'sql' => "varchar(128) NOT NULL default ''",
        ],
        'hoursFr' => [
            'inputType' => 'text',
            'eval' => ['maxlength' => 128, 'tl_class' => 'w50', 'placeholder' => '09:00-12:00, 14:00-18:00'],
            'sql' => "varchar(128) NOT NULL default ''",
        ],
        'hoursSa' => [
            'inputType' => 'text',
            'eval' => ['maxlength' => 128, 'tl_class' => 'w50', 'placeholder' => '09:00-12:00, 14:00-18:00'],
            'sql' => "varchar(128) NOT NULL default ''",
        ],
        'hoursSu' => [
            'inputType' => 'text',
            'eval' => ['maxlength' => 128, 'tl_class' => 'w50', 'placeholder' => '09:00-12:00, 14:00-18:00'],
            'sql' => "varchar(128) NOT NULL default ''",
        ],
        'zoom' => [
            'inputType' => 'checkbox',
            'eval' => ['tl_class' => 'w50 m12'],
            'sql' => ['type' => 'boolean', 'default' => true],
        ],
        'location' => [
            'inputType' => 'text',
            'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'published' => [
            'toggle' => true,
            'inputType' => 'checkbox',
            'eval' => ['doNotCopy' => true],
            'sql' => ['type' => 'boolean', 'default' => false],
        ],
    ],
];
