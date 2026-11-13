<img src="logo.svg" width="96" height="96" alt="">

# GratBooking

**Termine aus dem Google Kalender buchen – mit automatisch erzeugtem Zoom-Meeting.** Für Contao 5.7, PHP ≥ 8.3.

Deine Kunden sehen auf der Website nur die Zeiten, die in deinem Google Kalender frei sind, und buchen direkt. Bei
jeder Buchung legt GratBooking ein Zoom-Meeting an, trägt den Termin samt Zoom-Link in deinen Google Kalender ein und
schickt die Bestätigung mit Zoom-Link und Kalenderdatei. Verschieben oder absagen per Link – Zoom und Google ziehen
mit. Ideal für Coachings, Beratungen und Erstgespräche per Video. Im Stil von [GratFrame](https://www.gratframe.de),
läuft aber auch ohne (Variablen `--gb-*`, Fallback auf `--gf-*`).

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

## Bezahlte Termine (optional, mit GratShop)

Ist **GratShop** (`gratlinie/contao-gratshop`, Stripe Checkout) installiert, bekommt jede Terminart das Feld
**Preis (GratShop-Produkt)**: ein Produkt vom Typ *Direktkauf* mit Nettopreis. Ohne GratShop bleibt alles kostenlos,
GratBooking braucht es nicht.

- **Buchen:** Das Modul zeigt den Bruttopreis (Steuersatz aus `gratlinie_grat_shop.vat_rate`), fragt zusätzlich die
  Zustimmung zum Beginn vor Ablauf der Widerrufsfrist ab (Dienstleistung, § 356 Abs. 4 BGB; Text und Seite der
  Widerrufsbelehrung im Modul) und leitet zu Stripe weiter. Der Termin ist so lange **reserviert** (Status *wartet auf
  Zahlung*, 32 Minuten), die Checkout-Session endet eine Minute früher.
- **Nach der Zahlung** (Rückkehr von Stripe oder Webhook, je Session nur einmal): Zoom-Meeting, Google-Eintrag,
  Bestätigung mit `##booking_amount##`. Wer abbricht, landet wieder im Kalender mit vorausgewähltem Termin; die
  Reservierung wird frei und die Stripe-Session geschlossen. Abgelaufene Reservierungen blockieren nichts, der
  stündliche Cron setzt sie auf *abgesagt*.
- **Verwalten:** Bezahlte Termine lassen sich online nur **verschieben**. Absagen und erstatten regelt der Gastgeber
  (Backend „Absagen“ löscht Zoom und Google, die Erstattung läuft im Stripe-Dashboard).
- **Zahlarten** stellt man im Stripe-Dashboard ein (Checkout nutzt die dort aktiven). **PayPal**: *Einstellungen →
  Zahlungsmethoden → PayPal* aktivieren und das PayPal-Geschäftskonto verbinden; bei der Auszahlung lässt sich wählen,
  ob das Geld im Stripe-Guthaben oder auf dem PayPal-Konto landet. Zahlarten, die erst Tage später bestätigt
  werden (SEPA-Lastschrift, Überweisung u. Ä.), blendet GratBooking im Checkout aus – die Reservierung wäre dann
  längst abgelaufen.
- Einrichtung von Stripe (Schlüssel in `.env.local`, Webhook `/_gratshop/stripe/webhook`): siehe GratShop. Kunden
  werden als Mitglieder angelegt, die Bestellung steht in GratShop.
