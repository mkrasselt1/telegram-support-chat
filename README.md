# Telegram Support Chat

Ein eigenständiges PHP-Chat-Widget für Websites. Nachrichten aus dem Widget werden an einen Telegram-Bot gesendet. Jede Unterhaltung kann als eigener Topic-Thread in einer Telegram-Supergroup geführt werden.

## Funktionen

- Echtzeit-Kommunikation per Telegram Webhook oder Polling
- Eigener Telegram-Topic-Thread pro Chat-Session
- Text-, Bild-, Datei-, Audio-, Video- und Standortnachrichten
- Sprachnachrichten aus dem Browser
- Screenshots und Datei-Uploads
- Online-/Offline-Status
- Chatabschluss mit sichtbarem Nachrichtenverlauf
- E-Mail-Transkript nach Chatabschluss
- Service-Worker-Benachrichtigungen
- Deutsche und englische Oberfläche
- Responsive Darstellung für Desktop und Mobilgeräte

## Voraussetzungen

- PHP 8.1 oder neuer
- PHP-Erweiterungen: `curl`, `json`, `fileinfo`, `mbstring`
- HTTPS für Webhook und Service Worker
- Eine Telegram-Supergroup mit aktivierten Topics
- Schreibrechte des Webservers auf `data/`

## Schnellstart

1. Telegram-Bot über [@BotFather](https://t.me/BotFather) erstellen.
2. Eine Telegram-Supergroup mit aktivierten Topics anlegen.
3. Den Bot als Administrator hinzufügen und das Verwalten von Topics erlauben.
4. `config.php` nach `config.local.php` kopieren.
5. Bot-Token und Telegram-Chat-ID in `config.local.php` eintragen.
6. Das Verzeichnis `data/` für den Webserver beschreibbar machen.
7. Das Widget in die Zielseite einbinden.

Beispiel für `config.local.php`:

```php
<?php
define('TELEGRAM_BOT_TOKEN', '123456789:DEIN_BOT_TOKEN');
define('TELEGRAM_CHAT_ID', -1001234567890);
define('BOT_USER_ID', 987654321);
define('COMPANY_NAME', 'PeanutPay Support');
define('LANGUAGE', 'de');
define('ALLOWED_ORIGINS', ['https://deine-domain.de']);
```

Widget einbinden:

```html
<script>
window.SupportChatConfig = {
  endpoint: '/support-chat/chat.php',
  swPath: '/support-chat/sw.js',
  lang: 'de',
  user: null
};
</script>
<script src="/support-chat/support-chat.js" async></script>
```

## Webhook einrichten

```bash
php register_webhook.php set https://deine-domain.de/support-chat/webhook.php
php register_webhook.php commands
php register_webhook.php info
```

Ein Webhook-Secret sollte in `config.local.php` gesetzt werden:

```php
define('TELEGRAM_WEBHOOK_SECRET', 'ein-langes-zufallsgeheimnis');
```

Ohne Webhook kann der Fallback über `getUpdates` verwendet werden. Dafür bleibt `TELEGRAM_WEBHOOK_URL` leer.

## Telegram-Befehle

- `/close`, `/resolved` oder `/done`: Chat schließen
- `/online`: Support für zwölf Stunden als online markieren
- `/offline`: Support für zwölf Stunden als offline markieren

## Dokumentation

- [Ausführliche Anleitung](ANLEITUNG.md)
- [Deployment- und Administrationsdetails](DEPLOY.md)

## Sicherheit

- Niemals echte Zugangsdaten in `config.php` oder in Git speichern.
- Für produktive Installationen `config.local.php` verwenden.
- `data/` darf nicht öffentlich abrufbar sein.
- HTTPS und ein Webhook-Secret verwenden.
- Upload-Größen und erlaubte Dateitypen in `config.local.php` prüfen.

## Lizenz

Für dieses Projekt ist in diesem Repository keine Lizenzdatei hinterlegt.
