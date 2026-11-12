<?php

declare(strict_types=1);

$L = &$GLOBALS['TL_LANG']['tl_gb_calendar'];
$L['title_legend'] = 'Kalender';
$L['google_legend'] = 'Google Kalender (Dienstkonto)';
$L['zoom_legend'] = 'Zoom (Server-to-Server-App)';
$L['organizer_legend'] = 'Gastgeber';
$L['rules_legend'] = 'Absagen, Erinnerung, Verwalten';
$L['publish_legend'] = 'Veröffentlichung';
$L['title'] = ['Titel', 'Name im Backend, z. B. „Martin W. Wagner“.'];
$L['timezone'] = ['Zeitzone', 'In dieser Zeitzone gelten die Wochenzeiten der Terminarten.'];
$L['googleCredentials'] = ['Schlüssel des Dienstkontos (JSON)', 'Inhalt der JSON-Datei einfügen, die Google beim Anlegen eines Schlüssels für das Dienstkonto herunterlädt. Nach dem Speichern wird er nicht mehr angezeigt; leer lassen behält den gespeicherten Schlüssel, neuer Inhalt ersetzt ihn. Gespeichert ohne Versionierung.'];
$L['googleAccount'] = ['Adresse des Dienstkontos', 'Für diese Adresse den Kalender in Google freigeben: Einstellungen des Kalenders → „Für bestimmte Personen freigeben“ → „Änderungen an Terminen vornehmen“.'];
$L['googleCalendarId'] = ['Kalender für Buchungen', 'Kalender-ID, in den gebuchte Termine eingetragen werden – meist die Gmail-Adresse, sonst unter „Kalender integrieren“ → „Kalender-ID“.'];
$L['googleBusyCalendars'] = ['Weitere Kalender, die belegt sind', 'Eine Kalender-ID je Zeile; deren Termine blockieren ebenfalls (nur belegt/frei wird gelesen). Jeden davon ebenfalls freigeben. Der Kalender für Buchungen zählt immer mit.'];
$L['zoomAccountId'] = ['Account-ID', 'Aus der Server-to-Server-OAuth-App im Zoom App Marketplace (Develop → Build App).'];
$L['zoomClientId'] = ['Client-ID', ''];
$L['zoomClientSecret'] = ['Client-Secret', 'Scopes der App: meeting:write:meeting:admin (anlegen), meeting:update:meeting:admin (verschieben) und meeting:delete:meeting:admin (absagen); ältere Apps: meeting:write:admin.'];
$L['organizerName'] = ['Name', 'Erscheint in der Kalenderdatei und als ##organizer_name##.'];
$L['organizerEmail'] = ['E-Mail-Adresse', 'Empfänger der Hinweise (##admin_email##) und Organisator in der Kalenderdatei.'];
$L['cancelHours'] = ['Absagen/Verschieben bis', 'Stunden vor dem Termin; danach nur noch direkt.'];
$L['reminderHours'] = ['Erinnerung', 'Stunden vor dem Termin, 0 = keine Erinnerung.'];
$L['managePage'] = ['Seite zum Verwalten', 'Seite mit dem Modul „Buchung verwalten“; der Link landet als ##manage_url## in den Mails.'];
$L['published'] = ['Veröffentlichen', 'Buchungen über die Website zulassen.'];
$L['googleOk'] = 'Google Kalender lesbar (Dienstkonto %s).';
$L['zoomOk'] = 'Zoom-Anmeldung erfolgreich.';
$L['calendarMissing'] = 'Schlüssel gespeichert. Jetzt den Kalender in Google für %s freigeben („Änderungen an Terminen vornehmen“) und hier die Kalender-ID unter „Kalender für Buchungen“ eintragen.';
$L['new'] = ['Neuer Kalender', 'Neuen Kalender anlegen'];
$L['edit'] = ['Bearbeiten', 'Kalender ID %s bearbeiten'];
$L['children'] = ['Terminarten', 'Terminarten des Kalenders ID %s'];
$L['delete'] = ['Löschen', 'Kalender ID %s löschen'];
$L['toggle'] = ['Veröffentlichen', 'Kalender ID %s veröffentlichen/unveröffentlichen'];
$L['show'] = ['Details', 'Details des Kalenders ID %s'];
