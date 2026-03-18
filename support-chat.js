/* =============================================================================
   Telegram Support Chat Widget  v1.0
   Include on any page:
     <script>window.SupportChatConfig = { endpoint: '/chat.php', ... };</script>
     <script src="/support-chat.js" async></script>
   ============================================================================= */
(function () {
  'use strict';

  // --------------------------------------------------------------------------
  // Emoji data  (category → array of emoji)
  // --------------------------------------------------------------------------
  const EMOJI_CATEGORIES = [
    { icon: '😀', label: 'Smileys', emoji: ['😀','😁','😂','🤣','😃','😄','😅','😆','😉','😊','😋','😎','😍','🥰','😘','😗','😙','😚','🙂','🤗','🤩','🤔','🤨','😐','😑','😶','🙄','😏','😣','😥','😮','🤐','😯','😪','😫','🥱','😴','😌','😛','😜','😝','🤤','😒','😓','😔','😕','🙃','🤑','😲','😖','😞','😟','😤','😢','😭','😦','😧','😨','😩','🤯','😬','😰','😱','🥵','🥶','😳','🤪','😵','🤫','🤭','🧐','🤓','😠','😡','🤬','😷','🤒','🤕','🤢','🤮','🥴','😇','🥳','🥺','🤡'] },
    { icon: '👍', label: 'Gestures', emoji: ['👍','👎','👌','🤌','✌️','🤞','🤟','🤘','🤙','👈','👉','👆','🖕','👇','☝️','👋','🤚','🖐','✋','🖖','👏','🙌','🤜','🤛','🤝','🙏','✍️','💪','🦾','🦿','🦵','🦶','👂','👃','🫀','🫁','🧠','🦷','🦴','👀','👁','👅','👄'] },
    { icon: '❤️', label: 'Hearts & Symbols', emoji: ['❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❣️','💕','💞','💓','💗','💖','💝','💘','💟','❤️‍🔥','❤️‍🩹','💯','💢','💥','💫','💦','💨','🔥','🌈','⭐','🌟','✨','💫','🎉','🎊','🎈','🎀','🎁','🔔','🔕','🎵','🎶','🎤','📢','📣','🔊','❓','❗','⚡'] },
    { icon: '🐶', label: 'Animals', emoji: ['🐶','🐱','🐭','🐹','🐰','🦊','🐻','🐼','🐨','🐯','🦁','🐮','🐷','🐸','🐵','🙈','🙉','🙊','🐔','🐧','🐦','🐤','🦆','🦅','🦉','🦇','🐺','🐗','🐴','🦄','🐝','🦋','🐛','🐌','🐞','🐜','🦟','🦗','🕷','🦂','🐢','🦎','🐍','🐙','🦑','🦐','🦞','🦀','🐡','🐠','🐟','🐬','🐳','🐋','🦈','🐊','🐅','🐆','🦓','🦍','🐘','🦏','🦛','🦒','🦘','🦬','🐃','🐂','🐄','🐎','🦙','🐖','🐏','🐑','🐐','🦌','🐕','🐩','🦮','🐕‍🦺','🐈','🐈‍⬛','🐓','🦃','🦤','🦚','🦜','🦩','🦢','🕊','🐇','🦝','🦨','🦡','🦫','🦦','🦥','🐁','🐀','🐿','🦔'] },
    { icon: '🍕', label: 'Food', emoji: ['🍕','🍔','🌮','🌯','🥙','🧆','🥚','🍳','🥘','🍲','🥣','🥗','🍿','🧈','🧂','🥫','🍱','🍘','🍙','🍚','🍛','🍜','🍝','🍠','🍢','🍣','🍤','🍥','🥮','🍡','🥟','🥠','🥡','🦀','🦞','🦐','🦑','🦪','🍦','🍧','🍨','🍩','🍪','🎂','🍰','🧁','🥧','🍫','🍬','🍭','🍮','🍯','🍼','🥛','☕','🍵','🧃','🥤','🧋','🍶','🍾','🍷','🍸','🍹','🍺','🍻','🥂','🥃'] },
    { icon: '🏠', label: 'Objects', emoji: ['📱','💻','🖥','🖨','⌨️','🖱','🖲','💾','💿','📀','📷','📸','📹','🎥','📽','🎞','📞','☎️','📟','📠','📺','📻','🎙','🎚','🎛','🧭','⏱','⏲','⏰','🕰','⌚','⏳','⌛','🔋','🔌','💡','🔦','🕯','🪔','🧯','🛢','💰','💴','💵','💶','💷','💸','💳','🪙','💹','📈','📉','📊','📋','🗒','📌','📍','📎','🖇','📏','📐','✂️','🗃','🗄','🗑','🔒','🔓','🔏','🗝','🔑','🪪','🔨','🪓','⛏','⚒','🛠','🗡','⚔️','🛡','🪤','🔧','🔩','⚙️','🗜','🪛','🔗','⛓','🪝','🧲','🪜','🧱','🪞','🪟','🛏','🛋','🪑','🚽','🪠','🚿','🛁','🪤','🧴','🧷','🧹','🧺','🧻','🪣','🧼','🪥','🧽','🧯','🛒'] },
  ];

  // --------------------------------------------------------------------------
  // Translations
  // --------------------------------------------------------------------------
  const TRANSLATIONS = {
    en: {
      statusChecking:        'Checking…',
      statusOnline:          'Online — we reply quickly',
      statusOffline:         'Currently offline',
      placeholder:           'Type a message…',
      launcherAriaLabel:     'Open support chat',
      launcherTitle:         'Chat with us',
      windowAriaLabel:       'Support Chat',
      resolveTitle:          'Mark as resolved',
      resolveAriaLabel:      'Mark chat as resolved',
      notifTitle:            'Toggle notifications',
      notifAriaLabel:        'Toggle notifications',
      removeAttachment:      'Remove attachment',
      emojiTitle:            'Emoji',
      emojiAriaLabel:        'Emoji picker',
      attachTitle:           'Attach file',
      attachAriaLabel:       'Attach file',
      audioTitle:            'Voice message',
      audioAriaLabel:        'Record voice message',
      locationTitle:         'Share location',
      locationAriaLabel:     'Share location',
      screenshotTitle:       'Share screenshot',
      screenshotAriaLabel:   'Share screenshot',
      imagePreviewAriaLabel: 'Image preview',
      resolvedTitle:         'Chat resolved',
      resolvedSub:           'This conversation has been marked as resolved.',
      restartBtn:            'Start new chat',
      offlineBanner:         "We are currently offline — leave a message and we'll reply soon",
      recording:             'Recording…',
      recordSendTitle:       'Send recording',
      recordCancel:          '✕ Cancel',
      resolvedByUser:        'You marked this conversation as resolved.',
      resolvedByAgent:       'Support marked this conversation as resolved. ✅',
      confirmResolve:        'Mark this chat as resolved?',
      errClose:              'Could not close the chat. Please try again.',
      errConnect:            'Could not connect. Retrying…',
      errConnLost:           'Connection lost. Retrying…',
      errSend:               'Failed to send message.',
      errUpload:             'Upload failed.',
      errScreenCapture:      'Screen capture is not supported in this browser.',
      errScreenshot:         'Screenshot failed.',
      errGeoUnsupported:     'Geolocation is not supported by your browser.',
      errLocation:           'Could not send location.',
      errLocationDenied:     'Location permission denied.',
      errMicDenied:          'Microphone access denied.',
      errVoice:              'Failed to send voice message.',
      errNotifUnsupported:   'Notifications not supported in this browser.',
      voice:                 '🎤 Voice message',
      audio:                 '🎵 Audio',
      video:                 '🎬 Video',
      download:              'Download',
      edited:                'edited',
    },
    de: {
      statusChecking:        'Verbinde…',
      statusOnline:          'Online — wir antworten schnell',
      statusOffline:         'Zurzeit offline',
      placeholder:           'Nachricht schreiben…',
      launcherAriaLabel:     'Support-Chat öffnen',
      launcherTitle:         'Mit uns chatten',
      windowAriaLabel:       'Support-Chat',
      resolveTitle:          'Als gelöst markieren',
      resolveAriaLabel:      'Chat als gelöst markieren',
      notifTitle:            'Benachrichtigungen umschalten',
      notifAriaLabel:        'Benachrichtigungen umschalten',
      removeAttachment:      'Anhang entfernen',
      emojiTitle:            'Emoji',
      emojiAriaLabel:        'Emoji-Auswahl',
      attachTitle:           'Datei anhängen',
      attachAriaLabel:       'Datei anhängen',
      audioTitle:            'Sprachnachricht',
      audioAriaLabel:        'Sprachnachricht aufnehmen',
      locationTitle:         'Standort teilen',
      locationAriaLabel:     'Standort teilen',
      screenshotTitle:       'Screenshot teilen',
      screenshotAriaLabel:   'Screenshot teilen',
      imagePreviewAriaLabel: 'Bildvorschau',
      resolvedTitle:         'Chat beendet',
      resolvedSub:           'Dieses Gespräch wurde als gelöst markiert.',
      restartBtn:            'Neuen Chat starten',
      offlineBanner:         'Wir sind gerade offline — hinterlasse eine Nachricht und wir melden uns bald!',
      recording:             'Aufnahme…',
      recordSendTitle:       'Aufnahme senden',
      recordCancel:          '✕ Abbrechen',
      resolvedByUser:        'Du hast dieses Gespräch als gelöst markiert.',
      resolvedByAgent:       'Der Support hat dieses Gespräch als gelöst markiert. ✅',
      confirmResolve:        'Diesen Chat als gelöst markieren?',
      errClose:              'Chat konnte nicht geschlossen werden. Bitte erneut versuchen.',
      errConnect:            'Verbindung fehlgeschlagen. Erneuter Versuch…',
      errConnLost:           'Verbindung unterbrochen. Erneuter Versuch…',
      errSend:               'Nachricht konnte nicht gesendet werden.',
      errUpload:             'Upload fehlgeschlagen.',
      errScreenCapture:      'Bildschirmaufnahme wird in diesem Browser nicht unterstützt.',
      errScreenshot:         'Screenshot fehlgeschlagen.',
      errGeoUnsupported:     'Standortbestimmung wird von diesem Browser nicht unterstützt.',
      errLocation:           'Standort konnte nicht gesendet werden.',
      errLocationDenied:     'Standortfreigabe verweigert.',
      errMicDenied:          'Mikrofonzugriff verweigert.',
      errVoice:              'Sprachnachricht konnte nicht gesendet werden.',
      errNotifUnsupported:   'Benachrichtigungen werden in diesem Browser nicht unterstützt.',
      voice:                 '🎤 Sprachnachricht',
      audio:                 '🎵 Audio',
      video:                 '🎬 Video',
      download:              'Herunterladen',
      edited:                'bearbeitet',
    },
  };

  function t(key) {
    const lang = config.lang || 'en';
    return (TRANSLATIONS[lang] || TRANSLATIONS.en)[key] || TRANSLATIONS.en[key] || key;
  }

  // --------------------------------------------------------------------------
  // State
  // --------------------------------------------------------------------------
  let config       = {};
  let sessionId    = null;
  let pollTimer    = null;
  let lastMsgId    = null;
  let lastPollTs   = 0;
  let isOpen       = false;
  let isAvailable  = false;
  let unreadCount  = 0;
  let swReg        = null;
  let notifEnabled = false;
  let mediaRecorder = null;
  let recordTimer   = null;
  let recordSeconds = 0;
  let pendingFile   = null;   // { file, dataUrl, type }
  let emojiOpen     = false;
  let initialized  = false;
  const pageUrl    = location.href;

  // --------------------------------------------------------------------------
  // Init
  // --------------------------------------------------------------------------
  function init(userConfig) {
    if (initialized) return;
    initialized = true;
    config = Object.assign({
      endpoint:     '/chat.php',
      swPath:       '/sw.js',
      cssPath:      null,           // auto-detected if null
      user:         null,
      pollInterval: 4000,
      theme:        {},
    }, userConfig || window.SupportChatConfig || {});

    injectCSS();
    buildDOM();
    applyTheme();
    attachEvents();

    // Restore open state from previous page
    if (localStorage.getItem('sc_window_open') === '1') {
      openChat();
    }

    // Register service worker
    if ('serviceWorker' in navigator && config.swPath) {
      navigator.serviceWorker.register(config.swPath, { scope: '/' })
        .then((reg) => {
          swReg = reg;
          navigator.serviceWorker.addEventListener('message', onSwMessage);
        })
        .catch(() => {});
    }
  }

  // --------------------------------------------------------------------------
  // CSS injection
  // --------------------------------------------------------------------------
  function injectCSS() {
    // Auto-detect CSS path from this script's src
    let cssPath = config.cssPath;
    if (!cssPath) {
      const scripts = document.querySelectorAll('script[src]');
      for (const s of scripts) {
        if (s.src.includes('support-chat')) {
          cssPath = s.src.replace(/support-chat\.js.*$/, 'support-chat.css');
          break;
        }
      }
    }
    if (cssPath) {
      const link = document.createElement('link');
      link.rel  = 'stylesheet';
      link.href = cssPath;
      document.head.appendChild(link);
    }
  }

  // --------------------------------------------------------------------------
  // DOM construction
  // --------------------------------------------------------------------------
  function buildDOM() {
    const root = document.createElement('div');
    root.id = 'support-chat-root';
    document.body.appendChild(root);

    // Launcher button
    root.innerHTML = `
<button id="sc-launcher" aria-label="${t('launcherAriaLabel')}" title="${t('launcherTitle')}">
  <svg class="sc-icon-chat" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4a2 2 0 0 0-2 2v18l4-4h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zm-2 10H6v-2h12v2zm0-3H6V7h12v2z"/></svg>
  <svg class="sc-icon-close" viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
  <span id="sc-badge"></span>
  <span id="sc-avail-dot"></span>
</button>

<div id="sc-window" role="dialog" aria-label="${t('windowAriaLabel')}" aria-modal="false">
  <div id="sc-header">
    <div id="sc-avatar"></div>
    <div id="sc-header-info">
      <div id="sc-company-name">Support</div>
      <div id="sc-status-text"><span id="sc-status-dot"></span><span id="sc-status-label">${t('statusChecking')}</span></div>
    </div>
    <button id="sc-resolve-btn" title="${t('resolveTitle')}" aria-label="${t('resolveAriaLabel')}">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
    </button>
    <button id="sc-notif-btn" title="${t('notifTitle')}" aria-label="${t('notifAriaLabel')}">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>
    </button>
  </div>
  <div id="sc-resolved-overlay" aria-live="polite">
    <div class="sc-resolved-icon">✅</div>
    <div class="sc-resolved-title">${t('resolvedTitle')}</div>
    <div class="sc-resolved-sub" id="sc-resolved-sub">${t('resolvedSub')}</div>
    <button id="sc-restart-btn">${t('restartBtn')}</button>
  </div>
  <div id="sc-offline-banner">${t('offlineBanner')}</div>
  <div id="sc-messages" role="log" aria-live="polite"></div>
  <div id="sc-input-area">
    <div id="sc-attachment-preview">
      <img id="sc-preview-thumb" alt="Preview" />
      <span id="sc-preview-name"></span>
      <button id="sc-preview-remove" aria-label="${t('removeAttachment')}">✕</button>
    </div>
    <div id="sc-upload-progress"><div id="sc-upload-progress-bar"></div></div>
    <div id="sc-record-bar">
      <span class="sc-record-dot"></span>
      <span id="sc-record-time">0:00</span>
      <span style="font-size:12px;color:#ef4444;flex:1">${t('recording')}</span>
      <button id="sc-record-send" title="${t('recordSendTitle')}" style="background:none;border:none;cursor:pointer;font-size:20px;">✅</button>
      <button id="sc-record-cancel">${t('recordCancel')}</button>
    </div>
    <div id="sc-input-toolbar">
      <button class="sc-toolbar-btn" id="sc-emoji-toggle" title="${t('emojiTitle')}" aria-label="${t('emojiAriaLabel')}">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z"/></svg>
      </button>
      <button class="sc-toolbar-btn" id="sc-attach-btn" title="${t('attachTitle')}" aria-label="${t('attachAriaLabel')}">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M16.5 6v11.5c0 2.21-1.79 4-4 4s-4-1.79-4-4V5c0-1.38 1.12-2.5 2.5-2.5s2.5 1.12 2.5 2.5v10.5c0 .55-.45 1-1 1s-1-.45-1-1V6H10v9.5c0 1.38 1.12 2.5 2.5 2.5s2.5-1.12 2.5-2.5V5c0-2.21-1.79-4-4-4S7 2.79 7 5v12.5c0 3.04 2.46 5.5 5.5 5.5s5.5-2.46 5.5-5.5V6h-1.5z"/></svg>
      </button>
      <button class="sc-toolbar-btn" id="sc-audio-btn" title="${t('audioTitle')}" aria-label="${t('audioAriaLabel')}">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 14c1.66 0 2.99-1.34 2.99-3L15 5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3zm5.3-3c0 3-2.54 5.1-5.3 5.1S6.7 14 6.7 11H5c0 3.41 2.72 6.23 6 6.72V21h2v-3.28c3.28-.48 6-3.3 6-6.72h-1.7z"/></svg>
      </button>
      <button class="sc-toolbar-btn" id="sc-location-btn" title="${t('locationTitle')}" aria-label="${t('locationAriaLabel')}">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
      </button>
      <button class="sc-toolbar-btn" id="sc-screenshot-btn" title="${t('screenshotTitle')}" aria-label="${t('screenshotAriaLabel')}">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 5h-3.17L15 3H9L7.17 5H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 14H4V7h4.05l1.83-2h4.24l1.83 2H20v12zM12 8c-2.76 0-5 2.24-5 5s2.24 5 5 5 5-2.24 5-5-2.24-5-5-5zm0 8c-1.65 0-3-1.35-3-3s1.35-3 3-3 3 1.35 3 3-1.35 3-3 3z"/></svg>
      </button>
    </div>
    <div id="sc-input-row">
      <textarea id="sc-text-input" placeholder="${t('placeholder')}" rows="1" aria-label="${t('placeholder')}"></textarea>
      <button id="sc-send-btn" aria-label="${t('sendAriaLabel')}" disabled>
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
      </button>
    </div>
    <div id="sc-emoji-picker" aria-hidden="true">
      <div id="sc-emoji-tabs"></div>
      <div id="sc-emoji-grid"></div>
    </div>
  </div>
</div>

<div id="sc-lightbox" role="dialog" aria-label="${t('imagePreviewAriaLabel')}" aria-modal="true">
  <img id="sc-lightbox-img" alt="Full size image" />
</div>

<input type="file" id="sc-file-input" accept="image/*,application/pdf,audio/*,video/*" style="display:none" aria-hidden="true" />
`;

    buildEmojiPicker();
  }

  // --------------------------------------------------------------------------
  // Theme application
  // --------------------------------------------------------------------------
  function applyTheme() {
    const t = config.theme || {};
    const root = document.getElementById('support-chat-root');
    if (!root) return;
    if (t.primary) {
      root.style.setProperty('--sc-primary', t.primary);
      // Naive dark variant
      root.style.setProperty('--sc-primary-dark', adjustColor(t.primary, -20));
      root.style.setProperty('--sc-primary-light', adjustColor(t.primary, 90, .08));
      root.style.setProperty('--sc-bubble-user', t.primary);
    }
    if (t.radius) root.style.setProperty('--sc-radius', t.radius);
  }

  function adjustColor(hex, _lightness, alpha) {
    // Very basic: just append alpha for light variant
    if (alpha !== undefined) return hex + '14'; // ~8% opacity approximation
    return hex;
  }

  // --------------------------------------------------------------------------
  // Event wiring
  // --------------------------------------------------------------------------
  function attachEvents() {
    $('sc-launcher').addEventListener('click', toggleChat);
    $('sc-send-btn').addEventListener('click', sendText);
    $('sc-notif-btn').addEventListener('click', toggleNotifications);
    $('sc-emoji-toggle').addEventListener('click', toggleEmojiPicker);
    $('sc-attach-btn').addEventListener('click', () => $('sc-file-input').click());
    $('sc-audio-btn').addEventListener('click', toggleAudioRecording);
    $('sc-location-btn').addEventListener('click', shareLocation);
    $('sc-screenshot-btn').addEventListener('click', captureScreenshot);
    $('sc-preview-remove').addEventListener('click', clearAttachment);
    $('sc-record-cancel').addEventListener('click', stopRecording.bind(null, false));
    $('sc-record-send').addEventListener('click', stopRecording.bind(null, true));
    $('sc-file-input').addEventListener('change', onFileSelected);
    $('sc-lightbox').addEventListener('click', closeLightbox);
    $('sc-resolve-btn').addEventListener('click', resolveChat);
    $('sc-restart-btn').addEventListener('click', restartChat);

    const textInput = $('sc-text-input');
    textInput.addEventListener('input', onTextInput);
    textInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendText();
      }
    });

    // Close emoji picker when clicking outside
    document.addEventListener('click', (e) => {
      if (emojiOpen && !$('sc-emoji-picker').contains(e.target) && e.target !== $('sc-emoji-toggle')) {
        closeEmojiPicker();
      }
    });
  }

  // --------------------------------------------------------------------------
  // Open / close
  // --------------------------------------------------------------------------
  function toggleChat() {
    if (isOpen) closeChat(); else openChat();
  }

  function openChat() {
    isOpen = true;
    localStorage.setItem('sc_window_open', '1');
    $('sc-launcher').classList.add('sc-open');
    $('sc-window').classList.add('sc-open');
    clearUnread();

    if (!sessionId) {
      initSession();
    } else {
      startPolling();
    }
    $('sc-text-input').focus();
  }

  function closeChat() {
    isOpen = false;
    localStorage.removeItem('sc_window_open');
    $('sc-launcher').classList.remove('sc-open');
    $('sc-window').classList.remove('sc-open');
    stopPolling();
    closeEmojiPicker();
  }

  async function resolveChat() {
    if (!sessionId) return;
    if (!confirm(t('confirmResolve'))) return;
    try {
      await api('close', { session_id: sessionId, initiator: 'user' });
      showResolved('user');
    } catch {
      showBanner(t('errClose'));
    }
  }

  function showResolved(initiator) {
    stopPolling();   // authoritative stop — called from every close path
    const sub = $('sc-resolved-sub');
    sub.textContent = initiator === 'user' ? t('resolvedByUser') : t('resolvedByAgent');
    $('sc-resolved-overlay').classList.add('sc-visible');
    $('sc-input-area').style.display  = 'none';
    $('sc-resolve-btn').style.display = 'none';
  }

  function restartChat() {
    // Clear session — next openChat() creates a fresh one
    localStorage.removeItem('sc_session_id');
    sessionId  = null;
    lastMsgId  = null;
    $('sc-messages').innerHTML = '';
    $('sc-resolved-overlay').classList.remove('sc-visible');
    $('sc-input-area').style.display  = '';
    $('sc-resolve-btn').style.display = '';
    initSession();
  }

  // --------------------------------------------------------------------------
  // Session init
  // --------------------------------------------------------------------------
  async function initSession() {
    const stored = localStorage.getItem('sc_session_id');
    const body   = {
      session_id: stored || null,
      user:       config.user || null,
      page_url:   pageUrl,
    };

    try {
      const data = await api('init', body);
      sessionId = data.session_id;
      localStorage.setItem('sc_session_id', sessionId);

      updateAvailability(data.availability, data.availability_label);
      setCompanyInfo(data.company_name, data.company_avatar);

      // Render welcome message if fresh session (no prior session or session changed)
      if (!stored || stored !== sessionId) {
        addSystemMessage(data.welcome_message || 'Hello! How can we help?');
      }

      // Render history
      if (data.history && data.history.length > 0) {
        for (const msg of data.history) renderMessage(msg, false);
        lastMsgId = data.history[data.history.length - 1].id;
        scrollToBottom(false);
      }

      startPolling();
    } catch (e) {
      showBanner(t('errConnect'));
      setTimeout(initSession, 5000);
    }
  }

  // --------------------------------------------------------------------------
  // Polling
  // --------------------------------------------------------------------------
  function startPolling() {
    stopPolling();
    pollTimer = setInterval(poll, config.pollInterval || 4000);
    poll(); // immediate first poll
  }

  function stopPolling() {
    if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
  }

  async function poll() {
    if (!sessionId) return;
    try {
      const thisPollTs = Math.floor(Date.now() / 1000);
      const params = new URLSearchParams({
        action:     'poll',
        session_id: sessionId,
        since_id:   lastMsgId || '',
        since_ts:   lastPollTs,
      });
      const data = await apiFetch(`${config.endpoint}?${params}`);
      lastPollTs = thisPollTs;
      hideBanner();
      updateAvailability(data.availability, data.availability_label);

      if (data.closed) {
        showResolved('agent');
      }

      if (data.edits && data.edits.length > 0) {
        for (const edit of data.edits) applyEdit(edit);
      }

      if (data.messages && data.messages.length > 0) {
        const agentMessages = [];
        for (const msg of data.messages) {
          renderMessage(msg, true);
          lastMsgId = msg.id;
          if (msg.from === 'agent') agentMessages.push(msg);
        }
        scrollToBottom();

        if (agentMessages.length > 0 && (!isOpen || document.hidden)) {
          incrementUnread(agentMessages.length);
          notifyAgent(agentMessages);
        }
      }
    } catch {
      showBanner(t('errConnLost'));
    }
  }

  function applyEdit(edit) {
    const row = $('sc-messages').querySelector(`[data-id="${CSS.escape(edit.id)}"]`);
    if (!row) return;
    const bubble = row.querySelector('.sc-bubble');
    if (!bubble) return;

    // Preserve time element and existing edited badge
    const timeEl    = bubble.querySelector('.sc-bubble-time');
    const badgeEl   = bubble.querySelector('.sc-edited-badge');

    // Clear bubble content, then re-render text
    while (bubble.firstChild) bubble.removeChild(bubble.firstChild);
    renderTextBubble(bubble, edit.content);

    // Re-append time
    if (timeEl) bubble.appendChild(timeEl);

    // Add or re-append edited badge
    if (badgeEl) {
      bubble.appendChild(badgeEl);
    } else {
      const badge = document.createElement('span');
      badge.className   = 'sc-edited-badge';
      badge.textContent = t('edited');
      bubble.appendChild(badge);
    }
  }

  // --------------------------------------------------------------------------
  // Send text
  // --------------------------------------------------------------------------
  async function sendText() {
    const input = $('sc-text-input');
    const text  = input.value.trim();
    if (!text && !pendingFile) return;
    if (!sessionId) return;

    if (pendingFile) {
      await uploadFile();
      return;
    }

    input.value = '';
    autoGrowTextarea(input);
    $('sc-send-btn').disabled = true;

    const optimistic = {
      id:        'opt_' + Date.now(),
      from:      'user',
      type:      'text',
      content:   text,
      timestamp: Math.floor(Date.now() / 1000),
    };
    renderMessage(optimistic, false);
    scrollToBottom();

    try {
      await api('send', { session_id: sessionId, type: 'text', text });
    } catch {
      showBanner(t('errSend'));
    }
  }

  // --------------------------------------------------------------------------
  // Upload file
  // --------------------------------------------------------------------------
  async function uploadFile() {
    if (!pendingFile || !sessionId) return;

    const formData = new FormData();
    formData.append('session_id', sessionId);
    formData.append('file', pendingFile.file);

    showUploadProgress(0);

    const optimistic = {
      id:        'opt_' + Date.now(),
      from:      'user',
      type:      pendingFile.msgType,
      content:   pendingFile.file.name,
      file_url:  pendingFile.dataUrl,
      timestamp: Math.floor(Date.now() / 1000),
    };
    renderMessage(optimistic, false);
    scrollToBottom();
    clearAttachment();

    try {
      await new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', `${config.endpoint}?action=upload`);
        xhr.upload.onprogress = (e) => {
          if (e.lengthComputable) showUploadProgress(e.loaded / e.total);
        };
        xhr.onload  = () => { hideUploadProgress(); resolve(); };
        xhr.onerror = () => { hideUploadProgress(); reject(); };
        xhr.send(formData);
      });
    } catch {
      showBanner(t('errUpload'));
    }
  }

  // --------------------------------------------------------------------------
  // Location sharing
  // --------------------------------------------------------------------------
  // Screen capture
  // --------------------------------------------------------------------------
  async function captureScreenshot() {
    if (!navigator.mediaDevices?.getDisplayMedia) {
      alert(t('errScreenCapture'));
      return;
    }

    let stream;
    try {
      stream = await navigator.mediaDevices.getDisplayMedia({ video: true, audio: false });
    } catch {
      // User cancelled or permission denied — silently bail
      return;
    }

    try {
      const track  = stream.getVideoTracks()[0];
      const { width, height } = track.getSettings();

      const video  = document.createElement('video');
      video.srcObject = stream;
      video.muted     = true;
      await video.play();

      const canvas  = document.createElement('canvas');
      canvas.width  = width  || video.videoWidth;
      canvas.height = height || video.videoHeight;
      canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

      stream.getTracks().forEach(t => t.stop());

      canvas.toBlob(blob => {
        if (!blob) { showBanner(t('errScreenshot')); return; }
        const file    = new File([blob], 'screenshot.png', { type: 'image/png' });
        const dataUrl = canvas.toDataURL('image/png');
        pendingFile   = { file, dataUrl, msgType: 'image' };
        showAttachmentPreview(file, dataUrl, 'image');
        $('sc-send-btn').disabled = false;
      }, 'image/png');
    } catch {
      stream.getTracks().forEach(t => t.stop());
      showBanner(t('errScreenshot'));
    }
  }

  // --------------------------------------------------------------------------
  function shareLocation() {
    if (!navigator.geolocation) {
      alert(t('errGeoUnsupported'));
      return;
    }
    navigator.geolocation.getCurrentPosition(async (pos) => {
      const lat = pos.coords.latitude;
      const lng = pos.coords.longitude;
      try {
        await api('send', { session_id: sessionId, type: 'location', lat, lng });
        const msg = {
          id:        'opt_' + Date.now(),
          from:      'user',
          type:      'location',
          content:   '📍 Location shared',
          lat, lng,
          timestamp: Math.floor(Date.now() / 1000),
        };
        renderMessage(msg, false);
        scrollToBottom();
      } catch {
        showBanner(t('errLocation'));
      }
    }, () => {
      alert(t('errLocationDenied'));
    });
  }

  // --------------------------------------------------------------------------
  // Audio recording
  // --------------------------------------------------------------------------
  async function toggleAudioRecording() {
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
      stopRecording(true);
      return;
    }

    let stream;
    try {
      stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    } catch {
      alert(t('errMicDenied'));
      return;
    }

    // Prefer ogg/opus — Telegram plays it natively as a voice message with waveform.
    // Chrome only supports webm; the PHP backend will convert it to ogg via ffmpeg if available.
    const mimeType = ['audio/ogg;codecs=opus', 'audio/ogg', 'audio/webm;codecs=opus', 'audio/webm']
      .find((m) => MediaRecorder.isTypeSupported(m)) || '';

    const chunks        = [];
    const resolvedMime  = mimeType || 'audio/webm';   // capture now — mediaRecorder is null by the time onstop fires
    mediaRecorder = new MediaRecorder(stream, mimeType ? { mimeType } : {});
    mediaRecorder.ondataavailable = (e) => { if (e.data.size > 0) chunks.push(e.data); };
    mediaRecorder.onstop = () => {
      stream.getTracks().forEach((t) => t.stop());
      if (!chunks.length) return;
      const blob = new Blob(chunks, { type: resolvedMime });
      const ext  = resolvedMime.includes('ogg') ? '.ogg' : '.webm';
      const file = new File([blob], 'voice_message' + ext, { type: resolvedMime });
      pendingFile = { file, dataUrl: null, msgType: 'voice' };
      uploadFile().catch(() => showBanner(t('errVoice')));
    };

    mediaRecorder.start();
    recordSeconds = 0;
    showRecordBar();
    recordTimer = setInterval(() => {
      recordSeconds++;
      const m = Math.floor(recordSeconds / 60);
      const s = String(recordSeconds % 60).padStart(2, '0');
      $('sc-record-time').textContent = `${m}:${s}`;
    }, 1000);
    $('sc-audio-btn').classList.add('sc-active');
  }

  function stopRecording(send) {
    clearInterval(recordTimer);
    hideRecordBar();
    $('sc-audio-btn').classList.remove('sc-active');
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
      if (!send) {
        mediaRecorder.ondataavailable = null;
        mediaRecorder.onstop = null;
      }
      mediaRecorder.stop();
    }
    mediaRecorder = null;
  }

  // --------------------------------------------------------------------------
  // File attachment selection
  // --------------------------------------------------------------------------
  function onFileSelected(e) {
    const file = e.target.files[0];
    if (!file) return;
    e.target.value = '';

    let msgType = 'file';
    if (file.type.startsWith('image/'))       msgType = 'image';
    else if (file.type.startsWith('audio/'))  msgType = 'audio';
    else if (file.type.startsWith('video/'))  msgType = 'video';

    const reader = new FileReader();
    reader.onload = (ev) => {
      pendingFile = { file, dataUrl: ev.target.result, msgType };
      showAttachmentPreview(file, ev.target.result, msgType);
      $('sc-send-btn').disabled = false;
    };
    reader.readAsDataURL(file);
  }

  function showAttachmentPreview(file, dataUrl, type) {
    const thumb = $('sc-preview-thumb');
    $('sc-attachment-preview').classList.add('sc-visible');
    $('sc-preview-name').textContent = file.name;
    if (type === 'image') {
      thumb.src = dataUrl;
      thumb.classList.add('sc-visible');
    } else {
      thumb.classList.remove('sc-visible');
    }
  }

  function clearAttachment() {
    pendingFile = null;
    $('sc-attachment-preview').classList.remove('sc-visible');
    $('sc-preview-thumb').classList.remove('sc-visible');
    $('sc-preview-thumb').src = '';
    $('sc-preview-name').textContent = '';
    if (!$('sc-text-input').value.trim()) $('sc-send-btn').disabled = true;
  }

  // --------------------------------------------------------------------------
  // Emoji picker
  // --------------------------------------------------------------------------
  function buildEmojiPicker() {
    const tabs = $('sc-emoji-tabs');

    EMOJI_CATEGORIES.forEach((cat, idx) => {
      const btn = document.createElement('button');
      btn.className   = 'sc-emoji-tab' + (idx === 0 ? ' sc-active' : '');
      btn.textContent = cat.icon;
      btn.title       = cat.label;
      btn.addEventListener('click', () => {
        tabs.querySelectorAll('.sc-emoji-tab').forEach((b) => b.classList.remove('sc-active'));
        btn.classList.add('sc-active');
        renderEmojiGrid(idx);
      });
      tabs.appendChild(btn);
    });

    renderEmojiGrid(0);
  }

  function renderEmojiGrid(catIdx) {
    const grid = $('sc-emoji-grid');
    grid.innerHTML = '';
    EMOJI_CATEGORIES[catIdx].emoji.forEach((em) => {
      const btn = document.createElement('button');
      btn.className   = 'sc-emoji-btn';
      btn.textContent = em;
      btn.addEventListener('click', () => insertEmoji(em));
      grid.appendChild(btn);
    });
  }

  function insertEmoji(emoji) {
    const input = $('sc-text-input');
    const pos   = input.selectionStart;
    const val   = input.value;
    input.value = val.slice(0, pos) + emoji + val.slice(input.selectionEnd);
    input.setSelectionRange(pos + emoji.length, pos + emoji.length);
    input.focus();
    onTextInput();
  }

  function toggleEmojiPicker() {
    emojiOpen ? closeEmojiPicker() : openEmojiPicker();
  }

  function openEmojiPicker() {
    emojiOpen = true;
    $('sc-emoji-picker').classList.add('sc-visible');
    $('sc-emoji-toggle').classList.add('sc-active');
  }

  function closeEmojiPicker() {
    emojiOpen = false;
    $('sc-emoji-picker').classList.remove('sc-visible');
    $('sc-emoji-toggle').classList.remove('sc-active');
  }

  // --------------------------------------------------------------------------
  // Textarea auto-grow
  // --------------------------------------------------------------------------
  function onTextInput() {
    const input = $('sc-text-input');
    autoGrowTextarea(input);
    $('sc-send-btn').disabled = !input.value.trim() && !pendingFile;
  }

  function autoGrowTextarea(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 100) + 'px';
  }

  // --------------------------------------------------------------------------
  // Message rendering
  // --------------------------------------------------------------------------
  function renderMessage(msg, animate) {
    // System event — render inline banner, not a chat bubble
    if (msg.from === 'system' && msg.type === 'closed') {
      const el = document.createElement('div');
      el.className   = 'sc-system-msg';
      el.textContent = msg.content || 'Chat resolved.';
      $('sc-messages').appendChild(el);
      scrollToBottom(false);
      showResolved(msg.closed_by || 'agent');
      return;
    }

    const isUser  = msg.from === 'user';
    const isAgent = msg.from === 'agent';
    const container = $('sc-messages');

    // Date separator
    const msgDate = new Date((msg.timestamp || 0) * 1000);
    const dateStr = msgDate.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' });
    const lastSep = container.querySelector('.sc-date-sep:last-of-type');
    if (!lastSep || lastSep.dataset.date !== dateStr) {
      const sep = document.createElement('div');
      sep.className = 'sc-date-sep';
      sep.dataset.date = dateStr;
      sep.textContent = dateStr;
      container.appendChild(sep);
    }

    // Agent name above bubble (only first in sequence)
    if (isAgent) {
      const prevRows = container.querySelectorAll('.sc-msg-row');
      const prevRow  = prevRows[prevRows.length - 1];
      const prevFrom = prevRow?.dataset.from;
      if (prevFrom !== 'agent') {
        const nameEl = document.createElement('div');
        nameEl.className = 'sc-agent-name';
        nameEl.textContent = msg.agent_name || 'Support';
        container.appendChild(nameEl);
      }
    }

    const row = document.createElement('div');
    row.className = 'sc-msg-row ' + (isUser ? 'sc-from-user' : 'sc-from-agent');
    row.dataset.from = msg.from;
    row.dataset.id   = msg.id;

    // Avatar
    if (isAgent) {
      const av = document.createElement('div');
      av.className   = 'sc-msg-avatar-sm';
      av.textContent = (msg.agent_name || 'S').charAt(0).toUpperCase();
      row.appendChild(av);
    }

    // Bubble
    const bubble  = document.createElement('div');
    bubble.className = 'sc-bubble';
    const fileUrl = resolveFileUrl(msg.file_url);

    switch (msg.type) {
      case 'text':
        renderTextBubble(bubble, msg.content);
        break;
      case 'image':
        renderImageBubble(bubble, fileUrl, msg.content);
        break;
      case 'voice':
      case 'audio':
        renderAudioBubble(bubble, fileUrl, msg.type);
        break;
      case 'video':
        renderVideoBubble(bubble, fileUrl);
        break;
      case 'file':
        renderFileBubble(bubble, msg.content, fileUrl);
        break;
      case 'location':
        renderLocationBubble(bubble, msg.lat, msg.lng);
        break;
      default:
        bubble.textContent = msg.content || '(message)';
    }

    const timeEl = document.createElement('span');
    timeEl.className   = 'sc-bubble-time';
    timeEl.textContent = formatTime(msg.timestamp);
    bubble.appendChild(timeEl);

    row.appendChild(bubble);
    container.appendChild(row);

    if (animate) {
      row.style.opacity = '0';
      row.style.transform = 'translateY(8px)';
      row.style.transition = 'opacity .2s, transform .2s';
      requestAnimationFrame(() => {
        row.style.opacity  = '1';
        row.style.transform = 'translateY(0)';
      });
    }
  }

  function renderTextBubble(bubble, text) {
    const parts = parseLinks(text || '');
    for (const part of parts) {
      if (part.type === 'link') {
        const a = document.createElement('a');
        a.href   = part.href;
        a.target = '_blank';
        a.rel    = 'noopener noreferrer';
        a.textContent = part.text;
        bubble.appendChild(a);
      } else {
        bubble.appendChild(document.createTextNode(part.text));
      }
    }
  }

  function renderImageBubble(bubble, src, caption) {
    bubble.classList.add('sc-bubble-image');
    if (src) {
      const img = document.createElement('img');
      img.alt   = caption || 'Image';
      img.src   = src;
      img.addEventListener('click', () => openLightbox(src));
      bubble.appendChild(img);
    }
    if (caption) {
      const cap = document.createElement('div');
      cap.textContent = caption;
      bubble.appendChild(cap);
    }
  }

  function renderAudioBubble(bubble, src, type) {
    bubble.classList.add('sc-bubble-audio');
    if (src) {
      const audio = document.createElement('audio');
      audio.controls = true;
      audio.src      = src;
      audio.preload  = 'metadata';
      bubble.appendChild(audio);
    } else {
      const icon = document.createElement('span');
      icon.textContent = type === 'voice' ? t('voice') : t('audio');
      bubble.appendChild(icon);
    }
  }

  function renderVideoBubble(bubble, src) {
    if (src) {
      const video = document.createElement('video');
      video.controls = true;
      video.src      = src;
      video.style.maxWidth = '220px';
      video.style.borderRadius = '10px';
      bubble.appendChild(video);
    } else {
      bubble.textContent = t('video');
    }
  }

  function renderFileBubble(bubble, name, url) {
    bubble.classList.add('sc-bubble-file');
    const icon = document.createElement('div');
    icon.className   = 'sc-bubble-file-icon';
    icon.textContent = '📄';
    bubble.appendChild(icon);

    const info = document.createElement('div');
    info.className = 'sc-bubble-file-info';

    if (url) {
      const link = document.createElement('a');
      link.href   = url;
      link.target = '_blank';
      link.rel    = 'noopener noreferrer';
      link.className   = 'sc-bubble-file-name';
      link.textContent = name || t('download');
      info.appendChild(link);
    } else {
      const nameEl = document.createElement('div');
      nameEl.className   = 'sc-bubble-file-name';
      nameEl.textContent = name || t('download');
      info.appendChild(nameEl);
    }

    bubble.appendChild(info);
  }

  function renderLocationBubble(bubble, lat, lng) {
    bubble.classList.add('sc-bubble-location');
    const a = document.createElement('a');
    a.href   = `https://www.openstreetmap.org/?mlat=${lat}&mlon=${lng}&zoom=15`;
    a.target = '_blank';
    a.rel    = 'noopener noreferrer';

    const pin = document.createTextNode('📍 ');
    const txt = document.createTextNode('View on map');
    a.appendChild(pin);
    a.appendChild(txt);
    bubble.appendChild(a);
  }

  // --------------------------------------------------------------------------
  // Link parser
  // --------------------------------------------------------------------------
  function parseLinks(text) {
    const parts  = [];
    const urlRe  = /https?:\/\/[^\s<>"{}|\\^`[\]]+/g;
    let last = 0, match;
    while ((match = urlRe.exec(text)) !== null) {
      if (match.index > last) parts.push({ type: 'text', text: text.slice(last, match.index) });
      parts.push({ type: 'link', text: match[0], href: match[0] });
      last = match.index + match[0].length;
    }
    if (last < text.length) parts.push({ type: 'text', text: text.slice(last) });
    return parts;
  }

  // --------------------------------------------------------------------------
  // System / welcome message
  // --------------------------------------------------------------------------
  function addSystemMessage(text) {
    const el = document.createElement('div');
    el.className   = 'sc-system-msg';
    el.textContent = text;
    $('sc-messages').appendChild(el);
    scrollToBottom(false);
  }

  // --------------------------------------------------------------------------
  // Availability
  // --------------------------------------------------------------------------
  function updateAvailability(avail, labelText) {
    isAvailable = !!avail;
    const dot      = $('sc-avail-dot');
    const statusDot = $('sc-status-dot');
    const label    = $('sc-status-label');
    const banner   = $('sc-offline-banner');

    dot.className       = 'sc-avail-dot ' + (isAvailable ? 'sc-online' : 'sc-offline');
    statusDot.className = 'sc-status-dot ' + (isAvailable ? 'sc-online' : '');
    label.textContent   = labelText || t(isAvailable ? 'statusOnline' : 'statusOffline');

    if (!isAvailable && sessionId) {
      banner.textContent = config.offlineMessage || t('offlineBanner');
      banner.classList.add('sc-visible');
    } else {
      banner.classList.remove('sc-visible');
    }
  }

  function setCompanyInfo(name, avatarUrl) {
    if (name) $('sc-company-name').textContent = name;
    const av = $('sc-avatar');
    if (avatarUrl) {
      const img = document.createElement('img');
      img.src = avatarUrl;
      img.alt = name || 'Support';
      av.appendChild(img);
    } else {
      av.textContent = (name || 'S').charAt(0).toUpperCase();
    }
  }

  // --------------------------------------------------------------------------
  // Notifications
  // --------------------------------------------------------------------------
  async function toggleNotifications() {
    if (notifEnabled) {
      notifEnabled = false;
      $('sc-notif-btn').classList.remove('sc-active');
      return;
    }

    if (!('Notification' in window)) {
      alert(t('errNotifUnsupported'));
      return;
    }

    if (Notification.permission === 'granted') {
      notifEnabled = true;
      $('sc-notif-btn').classList.add('sc-active');
    } else if (Notification.permission !== 'denied') {
      const perm = await Notification.requestPermission();
      if (perm === 'granted') {
        notifEnabled = true;
        $('sc-notif-btn').classList.add('sc-active');
      }
    }
  }

  function notifyAgent(messages) {
    if (!messages.length) return;

    // Via service worker (when page is in background)
    if (swReg && navigator.serviceWorker.controller) {
      navigator.serviceWorker.controller.postMessage({
        type:      'NEW_MESSAGES',
        messages,
        sessionId,
        pageUrl,
        iconUrl:   '',
      });
    }

    // Direct Notification API (when page is visible but notifications enabled)
    if (notifEnabled && Notification.permission === 'granted' && !document.hidden) {
      const last   = messages[messages.length - 1];
      const body   = last.content || 'New message';
      const title  = last.agent_name || config.companyName || 'Support';
      new Notification(title, { body: body.substring(0, 120), tag: 'support-chat' });
    }
  }

  function onSwMessage(event) {
    if (event.data?.type === 'OPEN_CHAT' && !isOpen) {
      openChat();
    }
  }

  // --------------------------------------------------------------------------
  // Unread badge
  // --------------------------------------------------------------------------
  function incrementUnread(n) {
    unreadCount += n;
    const badge = $('sc-badge');
    badge.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
    badge.classList.add('sc-visible');

    // Update tab title
    if (unreadCount > 0 && !document.title.startsWith('(')) {
      document.title = `(${unreadCount}) ${document.title}`;
    }
  }

  function clearUnread() {
    if (unreadCount === 0) return;
    unreadCount = 0;
    $('sc-badge').classList.remove('sc-visible');
    // Restore title
    document.title = document.title.replace(/^\(\d+\+?\)\s/, '');
  }

  // --------------------------------------------------------------------------
  // UI helpers
  // --------------------------------------------------------------------------
  function scrollToBottom(animate = true) {
    const el = $('sc-messages');
    if (!animate) { el.scrollTop = el.scrollHeight; return; }
    el.scrollTo({ top: el.scrollHeight, behavior: 'smooth' });
  }

  function showBanner(text) {
    const b = $('sc-offline-banner');
    b.textContent = text;
    b.classList.add('sc-visible');
  }

  function hideBanner() {
    if (isAvailable) $('sc-offline-banner').classList.remove('sc-visible');
  }

  function showUploadProgress(ratio) {
    $('sc-upload-progress').style.display = 'block';
    $('sc-upload-progress-bar').style.width = (ratio * 100) + '%';
  }

  function hideUploadProgress() {
    $('sc-upload-progress').style.display = 'none';
    $('sc-upload-progress-bar').style.width = '0';
  }

  function showRecordBar() {
    $('sc-record-bar').classList.add('sc-visible');
    $('sc-input-row').style.display = 'none';
  }

  function hideRecordBar() {
    $('sc-record-bar').classList.remove('sc-visible');
    $('sc-input-row').style.display = '';
  }

  function openLightbox(src) {
    $('sc-lightbox-img').src = src;
    $('sc-lightbox').classList.add('sc-visible');
    document.body.style.overflow = 'hidden';
  }

  function closeLightbox() {
    $('sc-lightbox').classList.remove('sc-visible');
    document.body.style.overflow = '';
  }

  // --------------------------------------------------------------------------
  // API helpers
  // --------------------------------------------------------------------------
  async function api(action, body) {
    return apiFetch(`${config.endpoint}?action=${action}`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(body),
    });
  }

  async function apiFetch(url, opts) {
    const resp = await fetch(url, opts);
    if (!resp.ok) throw new Error('HTTP ' + resp.status);
    const data = await resp.json();
    if (!data.ok) throw new Error(data.error || 'API error');
    return data;
  }

  // --------------------------------------------------------------------------
  // Utility
  // --------------------------------------------------------------------------
  function $(id) { return document.getElementById(id); }

  // Resolve a server-returned file_url like "?action=file&..." to a full URL.
  // The server returns query-string-only URLs; browsers resolve them relative
  // to the current page, not to chat.php — so we prepend the endpoint path.
  function resolveFileUrl(url) {
    if (!url) return url;
    if (url.startsWith('?')) return config.endpoint.split('?')[0] + url;
    return url;
  }

  function formatTime(ts) {
    if (!ts) return '';
    const d = new Date(ts * 1000);
    return d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
  }

  // --------------------------------------------------------------------------
  // Auto-init
  // --------------------------------------------------------------------------
  function autoInit() {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', autoInit);
      return;
    }
    if (window.SupportChatConfig) {
      init(window.SupportChatConfig);
    }
  }

  // Expose public API
  window.SupportChat = { init };

  autoInit();
})();
