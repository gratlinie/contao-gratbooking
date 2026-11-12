# GratBooking

Termine über die Website buchen – Contao 5.7, PHP ≥ 8.3. Freie Zeiten aus dem Google Kalender, Zoom-Meeting je
Buchung, Bestätigung mit Kalenderdatei, Erinnerung, Absagen und Umbuchen per Link. Im Stil von
[GratFrame](https://www.gratframe.de), läuft aber auch ohne (Variablen `--gb-*`, Fallback auf `--gf-*`).

## Funktionen

- **Kalender** (Backend → Inhalte → Terminarten): Verbindung zu Google (Dienstkonto) und Zoom (Server-to-Server-App),
  Gastgeber, Fristen für Absagen und Erinnerung, Seite zum Verwalten. Zugangsdaten nur dort, ohne Versionierung; der
  Google-Schlüssel wird nach dem Speichern nicht mehr angezeigt. Beim Speichern werden beide Verbindungen geprüft.
- **Terminarten** je Kalender: Dauer, Raster der Anfangszeiten, Puffer davor/danach, Vorlauf, Horizont, Höchstzahl pro
  Tag, buchbare Zeiten je Wochentag („09:00-12:00, 14:00-18:00“), Zoom ja/nein oder ein Ort.
- **Abwesenheiten** je Kalender (Urlaub, Fortbildung …): Von–Bis per Datumsauswahl, an diesen Tagen bietet keine
  Terminart Termine an; bestehende Buchungen bleiben.
- **Freie Termine** = Wochenzeiten minus Abwesenheiten minus belegte Zeiten aller angegebenen Google-Kalender
  (Free/Busy, Details bleiben privat) minus eigene Buchungen samt Puffer. Achtung: Ganztägige Termine legt Google
  standardmäßig als „Verfügbar“ an – die blockieren nur, wenn sie auf „Beschäftigt“ stehen.
- **Buchen** (Modul *Termin buchen*): Terminart → Monat → Uhrzeit → Kontaktdaten. Vor dem Speichern wird der Termin
  frisch gegen Google geprüft und der Kalender gesperrt (keine Doppelbuchung). Dann Zoom-Meeting, Eintrag im
  Google Kalender (mit Kontaktdaten und Zoom-Link), Mails. Scheitert ein Schritt, wird zurückgerollt.
- **Verwalten** (Modul *Buchung verwalten*, Link aus der Mail): verschieben (Zoom und Google werden angepasst) oder
  absagen (beides wird gelöscht), bis zur Frist des Kalenders. Im Backend: Liste der Buchungen mit „Absagen“.
- **Mails** (Notification Center, Typen *Termin gebucht*, *verschoben*, *abgesagt*, *Erinnerung*): Tokens zu Termin,
  Kontakt, Zoom und Verwaltungs-Link; Kalenderdatei als Anhang (`##booking_ics##` in „Anhänge über Tokens“, gleiche UID
  bei Änderungen, `METHOD:CANCEL` bei Absage).
- **Blättern ohne Neuladen**: `gratbooking.js` lädt beim Klick auf Monat, Tag, Uhrzeit, Verschieben oder Absagen nur den
  Buchungsbereich nach und führt die Adresse mit (Zurück/Neuladen funktionieren). Ohne JavaScript sind es normale
  Links, das Formular ist ein normales Formular. Antworten werden nie zwischengespeichert.

## Google einrichten (je Kunde)

1. In der [Google Cloud Console](https://console.cloud.google.com/) ein Projekt wählen oder anlegen, die
   **Google Calendar API** aktivieren.
2. *IAM & Verwaltung → Dienstkonten → Dienstkonto erstellen* (keine Rollen nötig), dann beim Dienstkonto
   *Schlüssel → Schlüssel hinzufügen → JSON*. Die heruntergeladene Datei öffnen und ihren Inhalt in Contao in
   **Schlüssel des Dienstkontos** einfügen, speichern – darunter steht die **Adresse des Dienstkontos**.
3. In Google Kalender beim Kalender für Buchungen *Einstellungen und Freigabe → Für bestimmte Personen freigeben*:
   die Adresse des Dienstkontos mit **„Änderungen an Terminen vornehmen“** eintragen. Weitere Kalender, die nur
   blockieren sollen, mit mindestens **„Nur Verfügbarkeit (frei/belegt)“** freigeben.
4. **Kalender-ID** eintragen (meist die Gmail-Adresse; sonst *Kalender integrieren → Kalender-ID*), speichern. Die
   Meldung „Google Kalender lesbar“ bestätigt die Freigabe.

Ein Dienstkonto ohne Google Workspace verschickt keine Google-Einladungen an Gäste – nicht nötig, die Bestätigung mit
Kalenderdatei kommt aus dem Notification Center.

## Zoom einrichten

1. Im [Zoom App Marketplace](https://marketplace.zoom.us/) *Develop → Build App → Server-to-Server OAuth App*.
2. Scopes `meeting:write:meeting:admin` (anlegen), `meeting:update:meeting:admin` (verschieben) und
   `meeting:delete:meeting:admin` (absagen) hinzufügen – ältere Apps: `meeting:write:admin` –, „Event Subscriptions“
   aus lassen, App aktivieren.
3. **Account-ID, Client-ID, Client-Secret** in den Kalender eintragen, speichern („Zoom-Anmeldung erfolgreich“).

Im kostenlosen Basic-Konto enden Meetings mit mehreren Teilnehmern nach 40 Minuten.

## Cronjob

Erinnerungen verschickt der Contao-Cron (alle 15 Minuten). Zuverlässig mit einem Cronjob beim Hoster auf
`vendor/bin/contao-console contao:cron` oder `https://<domain>/_contao/cron`.

## Test ohne Google und Zoom

`GRATBOOKING_GOOGLE_URL` und `GRATBOOKING_ZOOM_URL` ersetzen die Basis-URLs der APIs (Mock-Server); die Anmeldung bei
Google läuft über `token_uri` aus dem JSON-Schlüssel.

## Geplant

Termine gegen Bezahlung über [GratShop](https://github.com/gratlinie/contao-gratshop) (Stripe Checkout): Slot
reservieren (Status *wartet auf Zahlung*), nach der Zahlung Zoom, Google und Bestätigung.
