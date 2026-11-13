# Changelog

## 0.2.0 (2026-11-13)

- **Bezahlte Termine mit GratShop** (optional): Terminart mit Preis aus einem GratShop-Produkt (Direktkauf), Termin
  wird während der Zahlung bei Stripe reserviert und nach der Zahlung bestätigt (Zoom, Google, Mail mit
  `##booking_amount##`); Abbruch gibt ihn frei. Zustimmung zum vorzeitigen Beginn mit Link zur Widerrufsbelehrung
  (Modul). Nur sofortige Zahlarten (keine SEPA-Lastschrift). Bezahlte Termine lassen sich online nur verschieben. Cron räumt abgelaufene Reservierungen auf.
  Braucht GratShop ab 0.5.2; ohne GratShop ändert sich nichts. `contao:migrate` ausführen (neue Felder an Buchung, Terminart und Modul).

## 0.1.1 (2026-11-12)

- Beschreibung und Stichwörter: Termine aus dem Google Kalender buchen, mit automatisch erzeugtem Zoom-Meeting

## 0.1.0 (2026-11-12)

- Erste Version: Kalender mit Google (Dienstkonto, Free/Busy über mehrere Kalender) und Zoom (Server-to-Server-App),
  Terminarten mit Wochenzeiten, Puffer, Vorlauf, Horizont und Tageslimit; Module *Termin buchen* und *Buchung
  verwalten* (verschieben, absagen); Backend-Liste der Buchungen mit „Absagen“; Benachrichtigungen *gebucht*,
  *verschoben*, *abgesagt*, *Erinnerung* mit Kalenderdatei; Erinnerungs-Cron
- Abwesenheiten je Kalender (Urlaub): keine Termine an diesen Tagen
- Terminauswahl blättert ohne Neuladen der Seite (`gratbooking.js`)
- Puffer gebuchter Termine bleibt frei; „Kalender für Buchungen“ erst nach dem Schlüssel; Zoom-Scope zum Verschieben
