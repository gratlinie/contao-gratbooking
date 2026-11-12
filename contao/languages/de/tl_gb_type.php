<?php

declare(strict_types=1);

$L = &$GLOBALS['TL_LANG']['tl_gb_type'];
$L['title_legend'] = 'Terminart';
$L['time_legend'] = 'Dauer und Planung';
$L['hours_legend'] = 'Buchbare Zeiten';
$L['meeting_legend'] = 'Ort';
$L['publish_legend'] = 'Veröffentlichung';
$L['title'] = ['Titel', 'Z. B. „Kostenloses Erstgespräch“.'];
$L['alias'] = ['Alias', 'Für den Link direkt zu dieser Terminart (?termin=…); leer: aus dem Titel.'];
$L['description'] = ['Beschreibung', 'Kurzer Text in der Auswahl und in der Kalenderdatei.'];
$L['duration'] = ['Dauer', 'Minuten.'];
$L['slotStep'] = ['Raster', 'Abstand der möglichen Anfangszeiten in Minuten, z. B. 30 → 9:00, 9:30, 10:00.'];
$L['bufferBefore'] = ['Puffer davor', 'Minuten, die vor dem Termin frei sein müssen.'];
$L['bufferAfter'] = ['Puffer danach', 'Minuten, die nach dem Termin frei sein müssen.'];
$L['leadTime'] = ['Vorlauf', 'Frühestens so viele Stunden ab jetzt buchbar.'];
$L['horizon'] = ['Horizont', 'Höchstens so viele Tage im Voraus buchbar.'];
$L['maxPerDay'] = ['Höchstens pro Tag', 'Termine dieser Art je Tag, 0 = unbegrenzt.'];
foreach (['Mo' => 'Montag', 'Tu' => 'Dienstag', 'We' => 'Mittwoch', 'Th' => 'Donnerstag', 'Fr' => 'Freitag', 'Sa' => 'Samstag', 'Su' => 'Sonntag'] as $key => $day) {
    $L['hours'.$key] = [$day, 'Zeitfenster wie „09:00-12:00, 14:00-18:00“; leer = nicht buchbar.'];
}
$L['zoom'] = ['Zoom-Meeting anlegen', 'Bei jeder Buchung ein Meeting erzeugen; der Link steht in Bestätigung, Erinnerung und Kalender.'];
$L['location'] = ['Ort ohne Zoom', 'Z. B. „Telefon“ oder eine Adresse.'];
$L['published'] = ['Veröffentlichen', 'Im Buchungsmodul anbieten.'];
$L['payment_legend'] = 'Bezahlung';
$L['shopProduct'] = ['Preis (GratShop-Produkt)', 'Produkt vom Typ „Direktkauf“; leer = kostenlos. Gebucht ist der Termin erst nach der Zahlung, bis dahin ist er 30 Minuten reserviert. Bezahlte Termine lassen sich online nur verschieben.'];
$L['free'] = '– kostenlos –';
$L['hoursInvalid'] = 'Ungültige Zeitfenster: %s – bitte wie „09:00-12:00, 14:00-18:00“ angeben.';
$L['new'] = ['Neue Terminart', 'Neue Terminart anlegen'];
$L['edit'] = ['Bearbeiten', 'Terminart ID %s bearbeiten'];
$L['copy'] = ['Duplizieren', 'Terminart ID %s duplizieren'];
$L['cut'] = ['Verschieben', 'Terminart ID %s verschieben'];
$L['delete'] = ['Löschen', 'Terminart ID %s löschen'];
$L['toggle'] = ['Veröffentlichen', 'Terminart ID %s veröffentlichen/unveröffentlichen'];
$L['show'] = ['Details', 'Details der Terminart ID %s'];
