<?php

declare(strict_types=1);

use Contao\DataContainer;
use Contao\DC_Table;

/*
 * Gebuchter Termin. Entsteht über das Buchungsmodul; im Backend sieht man Kontaktdaten, Zoom-Link und Status.
 * Absagen und Umbuchen laufen über den Link aus der Bestätigung (token) – oder hier mit „Absagen“, damit auch
 * Google-Eintrag und Zoom-Meeting verschwinden.
 */
$GLOBALS['TL_DCA']['tl_gb_booking'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'closed' => true,
        'notCopyable' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'token' => 'unique',
                'calendar,status,start' => 'index',
                'status,reminderSentAt,start' => 'index',
            ],
        ],
    ],
    'list' => [
        'sorting' => [
            'mode' => DataContainer::MODE_SORTED,
            'fields' => ['start'],
            'flag' => DataContainer::SORT_DAY_DESC,
            'panelLayout' => 'filter;search,limit',
        ],
        'label' => [
            'fields' => ['start', 'type', 'lastname', 'email', 'status'],
            'showColumns' => true,
        ],
        'operations' => [
            'edit',
            'cancel' => [
                'route' => 'gratbooking_cancel',
                'icon' => 'delete.svg',
                'primary' => true,
                'prefetch' => false,
                'attributes' => 'onclick="if(!confirm(\'' . ($GLOBALS['TL_LANG']['tl_gb_booking']['cancelConfirm'] ?? 'Termin absagen?') . '\'))return false"',
            ],
            'show',
        ],
    ],
    'palettes' => [
        'default' => '{booking_legend},type,start,end,status;{contact_legend},firstname,lastname,email,phone,message;{meeting_legend},zoomJoinUrl,zoomPassword,zoomMeetingId,googleEventId;{payment_legend:hide},amount,shopOrder,shopCheckout,expiresAt;{log_legend:hide},createdAt,privacyAt,reminderSentAt,cancelledAt,token',
    ],
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp' => [
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'calendar' => [
            'foreignKey' => 'tl_gb_calendar.title',
            'sql' => 'int(10) unsigned NOT NULL default 0',
            'relation' => ['type' => 'belongsTo', 'load' => 'lazy'],
        ],
        'type' => [
            'filter' => true,
            'inputType' => 'select',
            'foreignKey' => 'tl_gb_type.title',
            'eval' => ['readonly' => true, 'disabled' => true, 'tl_class' => 'w50'],
            'sql' => 'int(10) unsigned NOT NULL default 0',
            'relation' => ['type' => 'belongsTo', 'load' => 'lazy'],
        ],
        'start' => [
            'sorting' => true,
            'flag' => DataContainer::SORT_DAY_DESC,
            'inputType' => 'text',
            'eval' => ['rgxp' => 'datim', 'readonly' => true, 'tl_class' => 'w50'],
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'end' => [
            'inputType' => 'text',
            'eval' => ['rgxp' => 'datim', 'readonly' => true, 'tl_class' => 'w50'],
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'status' => [
            'filter' => true,
            'inputType' => 'select',
            'options' => ['confirmed', 'cancelled', 'pending'],
            'reference' => &$GLOBALS['TL_LANG']['tl_gb_booking']['statusOptions'],
            'eval' => ['readonly' => true, 'disabled' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(16) NOT NULL default 'confirmed'",
        ],
        'firstname' => [
            'search' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 128, 'tl_class' => 'w50'],
            'sql' => "varchar(128) NOT NULL default ''",
        ],
        'lastname' => [
            'search' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 128, 'tl_class' => 'w50'],
            'sql' => "varchar(128) NOT NULL default ''",
        ],
        'email' => [
            'search' => true,
            'inputType' => 'text',
            'eval' => ['rgxp' => 'email', 'maxlength' => 255, 'decodeEntities' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'phone' => [
            'inputType' => 'text',
            'eval' => ['rgxp' => 'phone', 'maxlength' => 64, 'tl_class' => 'w50'],
            'sql' => "varchar(64) NOT NULL default ''",
        ],
        'message' => [
            'inputType' => 'textarea',
            'eval' => ['tl_class' => 'clr'],
            'sql' => 'text NULL',
        ],
        'zoomJoinUrl' => [
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'tl_class' => 'w50'],
            'sql' => 'text NULL',
        ],
        'zoomPassword' => [
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(64) NOT NULL default ''",
        ],
        'zoomMeetingId' => [
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(64) NOT NULL default ''",
        ],
        'googleEventId' => [
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'sequence' => [
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'token' => [
            'eval' => ['readonly' => true, 'doNotShow' => true],
            'sql' => "varchar(64) NOT NULL default ''",
        ],
        'createdAt' => [
            'inputType' => 'text',
            'eval' => ['rgxp' => 'datim', 'readonly' => true, 'tl_class' => 'w50'],
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'privacyAt' => [
            'inputType' => 'text',
            'eval' => ['rgxp' => 'datim', 'readonly' => true, 'tl_class' => 'w50'],
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'reminderSentAt' => [
            'inputType' => 'text',
            'eval' => ['rgxp' => 'datim', 'readonly' => true, 'tl_class' => 'w50'],
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'cancelledAt' => [
            'inputType' => 'text',
            'eval' => ['rgxp' => 'datim', 'readonly' => true, 'tl_class' => 'w50'],
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'amount' => [
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'tl_class' => 'w50'],
            'sql' => "decimal(10,2) NOT NULL default '0.00'",
        ],
        'shopOrder' => [
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'tl_class' => 'w50'],
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'shopCheckout' => [
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'expiresAt' => [
            'inputType' => 'text',
            'eval' => ['rgxp' => 'datim', 'readonly' => true, 'tl_class' => 'w50'],
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
    ],
];
