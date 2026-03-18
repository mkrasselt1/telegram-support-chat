# Deployment-Anleitung — Telegram Support Chat

## Voraussetzungen

| Anforderung | Minimum | Empfohlen |
|---|---|---|
| PHP | 8.1 | 8.2+ |
| PHP-Extensions | `curl`, `json`, `finfo`, `fileinfo` | + `mbstring` |
| Webserver | Apache 2.4 / Nginx | Apache mit mod_rewrite |
| HTTPS | — | Pflicht für Webhooks & Service Worker |
| Schreibrechte | `data/`-Verzeichnis | — |

---

## Schritt 1 — Telegram Bot einrichten

### 1.1 Bot erstellen
1. In Telegram: [@BotFather](https://t.me/BotFather) öffnen
2. `/newbot` senden → Name und Username vergeben
3. Den **Bot-Token** kopieren (Format: `123456789:AAF...`)

### 1.2 Supergroup mit Topics anlegen
1. Neue Gruppe erstellen → Typ: **Supergroup**
2. Gruppe öffnen → Einstellungen → **Topics aktivieren** (Forum-Modus)
3. Den Bot zur Gruppe hinzufügen
4. Bot zum **Admin** machen mit folgenden Rechten:
   - ✅ Nachrichten senden
   - ✅ Medien senden
   - ✅ **Topics verwalten** (`can_manage_topics`)

### 1.3 Chat-ID der Gruppe herausfinden
Entweder:
- Bot [@username_to_id_bot](https://t.me/username_to_id_bot) fragen
- Oder im Browser aufrufen (Bot muss in der Gruppe sein):
  ```
  https://api.telegram.org/bot<TOKEN>/getUpdates
  ```
  Dann eine Nachricht in die Gruppe schreiben → `chat.id` aus der Antwort ablesen.
  Die ID ist **negativ** und beginnt mit `-100`, z. B. `-1001234567890`.

### 1.4 Bot-User-ID herausfinden
```
https://api.telegram.org/bot<TOKEN>/getMe
```
→ `id`-Feld notieren (wird gebraucht damit der Bot seine eigenen Nachrichten filtert).

---

## Schritt 2 — Dateien deployen

### Struktur auf dem Server
```
/var/www/yoursite.com/
└── support-chat/          ← alle Dateien hierhin
    ├── chat.php
    ├── config.php
    ├── telegram.php
    ├── webhook.php
    ├── register_webhook.php
    ├── support-chat.js
    ├── support-chat.css
    ├── sw.js
    └── data/              ← muss beschreibbar sein, darf nicht öffentlich sein
        ├── .htaccess
        ├── sessions/
        ├── updates/
        └── uploads/
```

### Per FTP / SFTP
Alle Dateien in ein Unterverzeichnis hochladen, z. B. `/support-chat/`.

### Per SSH / Git
```bash
cd /var/www/yoursite.com
git clone https://github.com/youruser/telegram-support-chat.git support-chat
cd support-chat
chmod 750 data data/sessions data/updates data/uploads
```

---

## Schritt 3 — Konfiguration

`config.php` öffnen und folgende Werte anpassen:

```php
const TELEGRAM_BOT_TOKEN  = '123456789:AAF...';    // aus Schritt 1.1
const TELEGRAM_CHAT_ID    = -1001234567890;          // aus Schritt 1.3
const BOT_USER_ID         = 987654321;               // aus Schritt 1.4

const COMPANY_NAME        = 'Mein Unternehmen';
const WELCOME_MESSAGE     = 'Hallo! 👋 Wie können wir helfen?';

// Verfügbarkeitszeiten (Zeitzone anpassen!)
const AVAILABILITY_SCHEDULE = [
    'timezone' => 'Europe/Berlin',
    'hours' => [
        'mon' => ['09:00', '18:00'],
        'tue' => ['09:00', '18:00'],
        'wed' => ['09:00', '18:00'],
        'thu' => ['09:00', '18:00'],
        'fri' => ['09:00', '17:00'],
        'sat' => null,
        'sun' => null,
    ],
    'offline_message' => 'Wir sind gerade nicht erreichbar. Hinterlasse eine Nachricht!',
];

// E-Mail wenn Nutzer offline ist (leer lassen zum Deaktivieren)
const OFFLINE_NOTIFY_EMAIL = 'support@yoursite.com';
const OFFLINE_NOTIFY_AFTER = 5 * 60;   // 5 Minuten

// CORS: eigene Domain eintragen
const ALLOWED_ORIGINS = ['https://yoursite.com'];
```

---

## Schritt 4 — Verzeichnisrechte setzen

```bash
# data/ darf vom Webserver geschrieben werden, aber NICHT öffentlich aufgerufen
chmod 750 support-chat/data
chmod 750 support-chat/data/sessions
chmod 750 support-chat/data/updates
chmod 750 support-chat/data/uploads

# Wenn Webserver als www-data läuft:
chown -R www-data:www-data support-chat/data
```

### Apache: .htaccess in data/ prüfen
Die Datei `data/.htaccess` enthält:
```apache
Order Deny,Allow
Deny from all
```
→ Schützt alle Session-Dateien vor direktem Zugriff.

### Nginx: in der Site-Konfiguration ergänzen
```nginx
location /support-chat/data/ {
    deny all;
}
```

---

## Schritt 5 — Webhook einrichten (empfohlen)

> **Ohne Webhook:** Antworten kommen erst beim nächsten Client-Poll an (bis zu 4 Sekunden Verzögerung).
> **Mit Webhook:** Antworten kommen sofort an — Telegram ruft deinen Server aktiv auf.
>
> ⚠️ Webhook erfordert eine **öffentliche HTTPS-URL**.

### 5.1 Secret Token generieren
```bash
php -r "echo bin2hex(random_bytes(16));"
# Beispiel-Output: a3f7b2c1d4e5f6a7b8c9d0e1f2a3b4c5
```

In `config.php` eintragen:
```php
const TELEGRAM_WEBHOOK_URL    = 'https://yoursite.com/support-chat/webhook.php';
const TELEGRAM_WEBHOOK_SECRET = 'a3f7b2c1d4e5f6a7b8c9d0e1f2a3b4c5';
```

### 5.2 Webhook registrieren
```bash
php support-chat/register_webhook.php set https://yoursite.com/support-chat/webhook.php
```

Erwartete Ausgabe:
```
✅ Webhook set: https://yoursite.com/support-chat/webhook.php
```

### 5.3 Webhook-Status prüfen
```bash
php support-chat/register_webhook.php info
```

```
Webhook URL    : https://yoursite.com/support-chat/webhook.php
Pending updates: 0
Last error     : —
```

### 5.4 Webhook deaktivieren (zurück zu getUpdates)
```bash
php support-chat/register_webhook.php delete
# Dann in config.php: TELEGRAM_WEBHOOK_URL = ''
```

---

## Schritt 6 — Widget einbinden

Auf jeder Seite wo der Chat erscheinen soll, vor `</body>` einfügen:

```html
<!-- Support Chat -->
<script>
window.SupportChatConfig = {
  endpoint: '/support-chat/chat.php',
  swPath:   '/support-chat/sw.js',

  // Optional: eingeloggter Nutzer (wird als Kontext an Telegram gesendet)
  user: null,
  // user: { name: 'Max Mustermann', email: 'max@example.com', id: '42' },

  // Optional: Theme anpassen
  theme: { primary: '#0088cc' },
};
</script>
<script src="/support-chat/support-chat.js" async></script>
```

**Wichtig beim Service Worker (`sw.js`):** Dieser muss im **Wurzelverzeichnis** der Domain liegen (oder zumindest oberhalb des Widget-Verzeichnisses), da der SW-Scope auf sein eigenes Verzeichnis begrenzt ist.

```bash
# sw.js muss von /sw.js erreichbar sein, nicht /support-chat/sw.js
cp support-chat/sw.js /var/www/yoursite.com/sw.js
```

Dann in der Konfiguration:
```js
swPath: '/sw.js',   // Wurzel der Domain, nicht /support-chat/sw.js
```

---

## Schritt 7 — Testen

### 7.1 API direkt testen
```bash
# Neuen Chat initialisieren
curl -s -X POST https://yoursite.com/support-chat/chat.php?action=init \
  -H "Content-Type: application/json" \
  -d '{"session_id":null,"user":{"name":"Test","email":"test@test.com"}}' | jq .

# Nachricht senden (session_id aus vorherigem Aufruf einsetzen)
curl -s -X POST https://yoursite.com/support-chat/chat.php?action=send \
  -H "Content-Type: application/json" \
  -d '{"session_id":"DEINE-SESSION-ID","type":"text","text":"Hallo!"}' | jq .
```

Erwartetes Ergebnis:
- In Telegram erscheint ein neuer Thread mit dem Namen `Session XXXXXX | Test`
- Erster Beitrag im Thread: Info-Karte mit Name, E-Mail, URL

### 7.2 Antwort testen
1. Im Telegram-Thread auf die Nachricht antworten
2. Im Browser-Widget sollte die Antwort innerhalb von ~4 Sekunden (polling) bzw. sofort (webhook) erscheinen

### 7.3 Checkliste
- [ ] Chat-Fenster öffnet sich
- [ ] Willkommensnachricht erscheint
- [ ] Nachricht senden → erscheint in Telegram als neuer Thread
- [ ] Telegram-Antwort → erscheint im Widget
- [ ] Datei-Upload funktioniert
- [ ] Verfügbarkeitsanzeige korrekt (online/offline je nach Uhrzeit)
- [ ] Notifications-Button fragt nach Berechtigung

---

## Schritt 8 — PHP für dynamische Seiten (z. B. WordPress / Laravel)

### WordPress
In `functions.php` des Themes:
```php
function add_support_chat() {
    $user = wp_get_current_user();
    $user_data = $user->ID ? json_encode([
        'name'  => $user->display_name,
        'email' => $user->user_email,
        'id'    => $user->ID,
    ]) : 'null';
    echo "<script>window.SupportChatConfig = {
        endpoint: '/support-chat/chat.php',
        swPath:   '/sw.js',
        user:     {$user_data}
    };</script>
    <script src='/support-chat/support-chat.js' async></script>";
}
add_action('wp_footer', 'add_support_chat');
```

### PHP allgemein
```php
<?php
$chatUser = isset($_SESSION['user']) ? json_encode([
    'name'  => $_SESSION['user']['name'],
    'email' => $_SESSION['user']['email'],
    'id'    => $_SESSION['user']['id'],
]) : 'null';
?>
<script>
window.SupportChatConfig = {
    endpoint: '/support-chat/chat.php',
    swPath:   '/sw.js',
    user: <?= $chatUser ?>
};
</script>
<script src="/support-chat/support-chat.js" async></script>
```

---

## Sicherheits-Checkliste

- [ ] `data/.htaccess` vorhanden und aktiv (Apache) / Nginx-Rule gesetzt
- [ ] `ALLOWED_ORIGINS` auf eigene Domain beschränkt
- [ ] `TELEGRAM_WEBHOOK_SECRET` gesetzt (zufälliger langer String)
- [ ] `register_webhook.php` nach einmaliger Nutzung löschen oder mit HTTP-Auth schützen
- [ ] PHP-Fehleranzeige in Production deaktiviert (`display_errors = Off` in php.ini)
- [ ] `data/`-Verzeichnis liegt außerhalb des Document-Roots (optional, aber ideal)
- [ ] Rate-Limit `RATE_LIMIT_PER_MINUTE` auf sinnvollen Wert gesetzt

---

## Troubleshooting

| Problem | Ursache | Lösung |
|---|---|---|
| `createForumTopic` schlägt fehl | Gruppe ist kein Supergroup oder Topics nicht aktiviert | In Telegram: Gruppeneinstellungen → Topics aktivieren |
| Webhook meldet `last_error` | PHP-Fehler in `webhook.php` | PHP-Fehlerlog prüfen: `tail -f /var/log/php/error.log` |
| Nachrichten kommen nicht an | Bot nicht Admin oder fehlende `can_manage_topics`-Rechte | Bot in der Gruppe zum Admin machen |
| Service Worker registriert sich nicht | `sw.js` liegt im falschen Verzeichnis | `sw.js` in das Wurzelverzeichnis der Domain kopieren |
| `data/`-Schreibfehler | Falsche Dateirechte | `chmod 750 data/` und `chown www-data data/` |
| Offline-Mail wird nicht gesendet | `mail()` auf Server deaktiviert | Postfix / SMTP-Relay konfigurieren oder PHP-Mailer-Library verwenden |
