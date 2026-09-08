# Anleitung: Telegram Support Chat

Diese Anleitung beschreibt die Einrichtung von der Telegram-Gruppe bis zur Einbindung des Chat-Widgets auf einer Website.

## 1. Telegram vorbereiten

### 1.1 Bot erstellen

1. In Telegram `@BotFather` öffnen.
2. `/newbot` senden.
3. Bot-Namen und Benutzernamen festlegen.
4. Den ausgegebenen Bot-Token sicher speichern.

Der Token sieht ungefähr so aus:

```text
123456789:AAExampleToken
```

### 1.2 Support-Gruppe anlegen

1. Eine Telegram-Supergroup erstellen.
2. In den Gruppeneinstellungen **Topics** beziehungsweise **Forum** aktivieren.
3. Den Bot zur Gruppe hinzufügen.
4. Den Bot zum Administrator machen.
5. Dem Bot mindestens diese Rechte geben:
   - Nachrichten senden
   - Medien senden
   - Topics verwalten

### 1.3 Chat-ID herausfinden

Eine Nachricht in der Gruppe senden und anschließend aufrufen:

```text
https://api.telegram.org/botDEIN_TOKEN/getUpdates
```

In der Antwort nach `chat.id` suchen. Eine Supergroup-ID beginnt normalerweise mit `-100`.

### 1.4 Bot-ID herausfinden

```text
https://api.telegram.org/botDEIN_TOKEN/getMe
```

Die ID aus dem Feld `result.id` wird als `BOT_USER_ID` benötigt, damit Bot-Nachrichten nicht erneut als Agentennachrichten verarbeitet werden.

## 2. Dateien auf den Server kopieren

Das Projekt kann zum Beispiel unter `/support-chat/` abgelegt werden:

```text
support-chat/
├── chat.php
├── config.php
├── telegram.php
├── webhook.php
├── register_webhook.php
├── support-chat.js
├── support-chat.css
├── sw.js
└── data/
    ├── sessions/
    ├── updates/
    └── uploads/
```

Die Verzeichnisse unter `data/` müssen für den PHP-Webserver beschreibbar sein:

```bash
chmod 750 data data/sessions data/updates data/uploads
```

Wenn der Webserver unter `www-data` läuft:

```bash
chown -R www-data:www-data data
```

Das Verzeichnis `data/` muss gegen direkten Browserzugriff geschützt werden. Bei Apache sollte die vorhandene `.htaccess` aktiv sein. Bei Nginx muss ein entsprechender `deny all`-Block eingerichtet werden.

## 3. Lokale Konfiguration erstellen

Die deployment-spezifischen Werte gehören nicht direkt in `config.php`. Stattdessen:

```bash
cp config.php config.local.php
```

Beispiel:

```php
<?php
define('TELEGRAM_BOT_TOKEN', 'DEIN_BOT_TOKEN');
define('TELEGRAM_CHAT_ID', -1001234567890);
define('BOT_USER_ID', 987654321);

define('COMPANY_NAME', 'PeanutPay Support');
define('COMPANY_AVATAR', '');
define('LANGUAGE', 'de');

define('ALLOWED_ORIGINS', [
    'https://deine-domain.de',
]);

define('OFFLINE_NOTIFY_EMAIL', 'support@deine-domain.de');

define('TELEGRAM_WEBHOOK_URL', 'https://deine-domain.de/support-chat/webhook.php');
define('TELEGRAM_WEBHOOK_SECRET', 'ein-langes-zufallsgeheimnis');
```

`config.local.php` sollte nicht in Git eingecheckt werden. Die Datei ist für lokale Zugangsdaten vorgesehen.

## 4. Widget einbinden

Vor dem schließenden `</body>`-Tag der Website einfügen:

```html
<script>
window.SupportChatConfig = {
  endpoint: '/support-chat/chat.php',
  swPath: '/support-chat/sw.js',
  lang: 'de',
  pollInterval: 4000,
  user: null,
  theme: {
    primary: '#0088cc',
    radius: '16px'
  }
};
</script>
<script src="/support-chat/support-chat.js" async></script>
```

Bei bekannten Benutzerdaten können Name, E-Mail und ID übergeben werden:

```html
user: {
  name: 'Max Mustermann',
  email: 'max@example.com',
  id: '42'
}
```

Der Name wird im Telegram-Topic und vor der Nachricht angezeigt. Bei einem leeren Namen wird kein Ersatzname gesendet.

## 5. Webhook aktivieren

Der Webhook kann über die Kommandozeile eingerichtet werden:

```bash
php register_webhook.php set https://deine-domain.de/support-chat/webhook.php
```

Bot-Befehle registrieren:

```bash
php register_webhook.php commands
```

Webhook-Status prüfen:

```bash
php register_webhook.php info
```

Webhook entfernen und auf Polling zurückwechseln:

```bash
php register_webhook.php delete
```

Für den produktiven Betrieb sollten `TELEGRAM_WEBHOOK_URL` und `TELEGRAM_WEBHOOK_SECRET` gesetzt sein. Der Secret-Header wird in `webhook.php` geprüft.

## 6. Funktion testen

1. Website öffnen.
2. Chat-Button anklicken.
3. Prüfen, dass ein neuer Chat erst nach der ersten Eingabe angelegt wird.
4. Nachricht senden.
5. Prüfen, ob in Telegram ein neuer Topic-Thread erscheint.
6. In Telegram antworten.
7. Prüfen, ob die Antwort im Widget erscheint.
8. Mit `/close`, `/resolved` oder `/done` schließen.
9. Prüfen, ob der Verlauf nach dem Schließen sichtbar bleibt.
10. Optional ein Transkript per E-Mail anfordern.

## 7. Online- und Offline-Status

Support kann in Telegram mit diesen Befehlen den Status steuern:

```text
/online
/offline
```

Der Status gilt standardmäßig zwölf Stunden. Die regulären Online-Zeiten werden in `AVAILABILITY_SCHEDULE` in `config.local.php` eingestellt.

## 8. Uploads und Limits

Standardmäßig sind Uploads bis zehn Megabyte erlaubt. Die Einstellung kann überschrieben werden:

```php
define('MAX_UPLOAD_BYTES', 20 * 1024 * 1024);
```

Erlaubte MIME-Typen werden über `ALLOWED_MIME_TYPES` konfiguriert. Nur notwendige Dateitypen sollten freigeschaltet werden.

## 9. Fehlersuche

### Kein Chat-Button sichtbar

- Prüfen, ob `support-chat.js` erreichbar ist.
- Browser-Konsole auf JavaScript-Fehler prüfen.
- Prüfen, ob das Script nach dem HTML-Element geladen wird oder `async` verwendet.

### Session kann nicht erstellt werden

- Schreibrechte auf `data/sessions/` prüfen.
- PHP-Fehlerprotokoll prüfen.
- `TELEGRAM_BOT_TOKEN` und `TELEGRAM_CHAT_ID` prüfen.

### Keine Nachricht in Telegram

- Bot muss Mitglied der Gruppe sein.
- Bot muss Nachrichten und Medien senden dürfen.
- Bei Topics muss `message_thread_id` verfügbar sein.
- `php register_webhook.php info` ausführen.

### Telegram-Antwort erscheint nicht im Widget

- Webhook-URL und Secret prüfen.
- `webhook.php` muss über HTTPS erreichbar sein.
- Prüfen, ob der Bot seine eigenen Nachrichten ignoriert.
- Bei Polling `TELEGRAM_WEBHOOK_URL` leer lassen.

### Alte CSS-Version im Browser

Das Widget versieht den automatisch geladenen CSS-Link mit einer Versionsnummer. In den DevTools sollte eine URL wie diese sichtbar sein:

```text
support-chat.css?v=20260908-3
```

Die CSS-Regel für Sprechblasen muss außerdem mit der Reset-Regel konkurrieren können:

```css
#support-chat-root .sc-bubble {
  padding: 8px 12px;
}
```

## 10. Sicherheit vor dem Livegang

- HTTPS aktivieren.
- Echte Tokens nur in `config.local.php` speichern.
- `data/` gegen öffentlichen Zugriff schützen.
- Webhook-Secret setzen.
- `ALLOWED_ORIGINS` auf die eigenen Domains begrenzen.
- Upload-Limits und MIME-Typen kontrollieren.
- PHP-Fehlerausgabe in der Produktion deaktivieren.
- Testdaten und alte Uploads aus `data/` entfernen.
