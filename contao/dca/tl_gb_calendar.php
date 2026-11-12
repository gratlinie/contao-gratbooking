<?php

declare(strict_types=1);

use Contao\DataContainer;
use Contao\DC_Table;

/*
 * Kalender: Verbindung zu Google (Dienstkonto) und Zoom (Server-to-Server-App), Fristen und die Seite zum
 * Verwalten einer Buchung. Die Terminarten hängen als Kinder daran. Ohne Versionierung, weil die Zugangsdaten
 * sonst in tl_version landen.
 */
$GLOBALS['TL_DCA']['tl_gb_calendar'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'ctable' => ['tl_gb_type', 'tl_gb_absence'],
        'switchToEdit' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
            ],
        ],
    ],
    'list' => [
        'sorting' => [
            'mode' => DataContainer::MODE_SORTED,
            'fields' => ['title'],
            'flag' => DataContainer::SORT_INITIAL_LETTER_ASC,
            'panelLayout' => 'search,limit',
        ],
        'label' => [
            'fields' => ['title', 'googleCalendarId', 'googleAccount'],
            'showColumns' => true,
        ],
        'operations' => [
            'edit',
            'children',
            'absences' => [
                'href' => 'table=tl_gb_absence',
                'icon' => 'bundles/gratliniegratbooking/icons/absence.svg',
                'primary' => true,
            ],
            'delete',
            'toggle',
            'show',
        ],
    ],
    'palettes' => [
        'default' => '{title_legend},title,timezone;{google_legend},googleCredentials,googleAccount,googleCalendarId,googleBusyCalendars;{zoom_legend},zoomAccountId,zoomClientId,zoomClientSecret;{organizer_legend},organizerName,organizerEmail;{rules_legend},cancelHours,reminderHours,managePage;{publish_legend},published',
    ],
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
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
        'timezone' => [
            'inputType' => 'select',
            'options_callback' => static fn (): array => array_combine(DateTimeZone::listIdentifiers(DateTimeZone::EUROPE), DateTimeZone::listIdentifiers(DateTimeZone::EUROPE)),
            'eval' => ['chosen' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(64) NOT NULL default 'Europe/Berlin'",
        ],
        'googleCredentials' => [
            'inputType' => 'textarea',
            'eval' => ['decodeEntities' => true, 'preserveTags' => true, 'doNotShow' => true, 'doNotCopy' => true, 'class' => 'monospace', 'rows' => 4, 'tl_class' => 'clr'],
            'sql' => 'text NULL',
        ],
        'googleAccount' => [
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'doNotCopy' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'googleCalendarId' => [
            'inputType' => 'text',
            'eval' => ['maxlength' => 255, 'decodeEntities' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'googleBusyCalendars' => [
            'inputType' => 'textarea',
            'eval' => ['decodeEntities' => true, 'rows' => 3, 'tl_class' => 'clr'],
            'sql' => 'text NULL',
        ],
        'zoomAccountId' => [
            'inputType' => 'text',
            'eval' => ['maxlength' => 64, 'decodeEntities' => true, 'doNotShow' => true, 'doNotCopy' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(64) NOT NULL default ''",
        ],
        'zoomClientId' => [
            'inputType' => 'text',
            'eval' => ['maxlength' => 64, 'decodeEntities' => true, 'doNotShow' => true, 'doNotCopy' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(64) NOT NULL default ''",
        ],
        'zoomClientSecret' => [
            'inputType' => 'text',
            'eval' => ['maxlength' => 128, 'decodeEntities' => true, 'doNotShow' => true, 'doNotCopy' => true, 'hideInput' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(128) NOT NULL default ''",
        ],
        'organizerName' => [
            'inputType' => 'text',
            'eval' => ['maxlength' => 128, 'tl_class' => 'w50'],
            'sql' => "varchar(128) NOT NULL default ''",
        ],
        'organizerEmail' => [
            'inputType' => 'text',
            'eval' => ['rgxp' => 'email', 'maxlength' => 255, 'decodeEntities' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'cancelHours' => [
            'inputType' => 'text',
            'eval' => ['rgxp' => 'natural', 'maxlength' => 4, 'tl_class' => 'w50'],
            'sql' => 'smallint(5) unsigned NOT NULL default 24',
        ],
        'reminderHours' => [
            'inputType' => 'text',
            'eval' => ['rgxp' => 'natural', 'maxlength' => 4, 'tl_class' => 'w50'],
            'sql' => 'smallint(5) unsigned NOT NULL default 24',
        ],
        'managePage' => [
            'inputType' => 'pageTree',
            'foreignKey' => 'tl_page.title',
            'eval' => ['fieldType' => 'radio', 'tl_class' => 'clr'],
            'sql' => 'int(10) unsigned NOT NULL default 0',
            'relation' => ['type' => 'hasOne', 'load' => 'lazy'],
        ],
        'published' => [
            'toggle' => true,
            'inputType' => 'checkbox',
            'eval' => ['doNotCopy' => true],
            'sql' => ['type' => 'boolean', 'default' => false],
        ],
    ],
];
