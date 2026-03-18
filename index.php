<?php
require_once __DIR__ . '/config.php';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars(COMPANY_NAME) ?> — Support Chat</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background: #f0f4f8;
      color: #333;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 20px;
    }
    .card {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 4px 24px rgba(0,0,0,0.08);
      padding: 48px 40px;
      max-width: 480px;
      width: 100%;
      text-align: center;
    }
    .icon {
      width: 64px;
      height: 64px;
      background: #0088cc;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 24px;
    }
    .icon svg { fill: #fff; width: 32px; height: 32px; }
    h1 { font-size: 1.5rem; margin-bottom: 12px; color: #111; }
    p  { color: #666; line-height: 1.6; margin-bottom: 8px; }
    .status {
      display: inline-block;
      margin-top: 24px;
      padding: 6px 16px;
      border-radius: 999px;
      font-size: 0.85rem;
      font-weight: 600;
      background: #e6f7ee;
      color: #1a7f4b;
    }
    .footer {
      margin-top: 32px;
      font-size: 0.78rem;
      color: #aaa;
    }
  </style>
</head>
<body>
  <div class="card">
    <div class="icon">
      <!-- Chat bubble icon -->
      <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path d="M20 2H4a2 2 0 0 0-2 2v18l4-4h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/>
      </svg>
    </div>

    <h1><?= htmlspecialchars(COMPANY_NAME) ?></h1>
    <p>This is the backend endpoint for the Telegram support chat widget.</p>
    <p>Embed the widget on your website to start receiving messages.</p>

    <span class="status">&#x2713; Service running</span>

    <div class="footer">
      Powered by <a href="https://telegram.org" style="color:#0088cc;text-decoration:none;">Telegram</a>
    </div>
  </div>
</body>
</html>
