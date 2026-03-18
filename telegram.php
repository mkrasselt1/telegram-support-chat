<?php
// =============================================================================
// Telegram Bot API Helper Class
// =============================================================================

class TelegramException extends RuntimeException {}

class TelegramBot
{
    private string $token;
    private string $baseUrl;
    private int    $chatId;

    public function __construct(string $token, int $chatId)
    {
        $this->token   = $token;
        $this->chatId  = $chatId;
        $this->baseUrl = "https://api.telegram.org/bot{$token}/";
    }

    // -------------------------------------------------------------------------
    // Forum topic management
    // -------------------------------------------------------------------------

    public function createForumTopic(string $name, int $iconColor = 0x6FB9F0): array
    {
        return $this->request('createForumTopic', [
            'chat_id'    => $this->chatId,
            'name'       => mb_substr($name, 0, 128),
            'icon_color' => $iconColor,
        ]);
    }

    public function closeForumTopic(int $threadId): bool
    {
        $result = $this->request('closeForumTopic', [
            'chat_id'           => $this->chatId,
            'message_thread_id' => $threadId,
        ]);
        return (bool) $result;
    }

    // -------------------------------------------------------------------------
    // Sending messages
    // -------------------------------------------------------------------------

    public function sendMessage(string $text, ?int $threadId = null, string $parseMode = 'HTML'): array
    {
        $params = [
            'chat_id'    => $this->chatId,
            'text'       => $text,
            'parse_mode' => $parseMode,
            'link_preview_options' => ['is_disabled' => false],
        ];
        if ($threadId !== null) {
            $params['message_thread_id'] = $threadId;
        }
        return $this->request('sendMessage', $params);
    }

    public function sendPhoto(string $filePath, ?int $threadId = null, string $caption = ''): array
    {
        $params = ['chat_id' => $this->chatId, 'caption' => $caption];
        if ($threadId !== null) $params['message_thread_id'] = $threadId;
        return $this->request('sendPhoto', $params, ['photo' => $filePath]);
    }

    public function sendDocument(string $filePath, ?int $threadId = null, string $caption = ''): array
    {
        $params = ['chat_id' => $this->chatId, 'caption' => $caption];
        if ($threadId !== null) $params['message_thread_id'] = $threadId;
        return $this->request('sendDocument', $params, ['document' => $filePath]);
    }

    public function sendVoice(string $filePath, ?int $threadId = null): array
    {
        $params = ['chat_id' => $this->chatId];
        if ($threadId !== null) $params['message_thread_id'] = $threadId;
        return $this->request('sendVoice', $params, ['voice' => $filePath]);
    }

    public function sendAudio(string $filePath, ?int $threadId = null, string $caption = ''): array
    {
        $params = ['chat_id' => $this->chatId, 'caption' => $caption];
        if ($threadId !== null) $params['message_thread_id'] = $threadId;
        return $this->request('sendAudio', $params, ['audio' => $filePath]);
    }

    public function sendVideo(string $filePath, ?int $threadId = null, string $caption = ''): array
    {
        $params = ['chat_id' => $this->chatId, 'caption' => $caption];
        if ($threadId !== null) $params['message_thread_id'] = $threadId;
        return $this->request('sendVideo', $params, ['video' => $filePath]);
    }

    public function sendLocation(float $lat, float $lng, ?int $threadId = null): array
    {
        $params = [
            'chat_id'   => $this->chatId,
            'latitude'  => $lat,
            'longitude' => $lng,
        ];
        if ($threadId !== null) $params['message_thread_id'] = $threadId;
        return $this->request('sendLocation', $params);
    }

    // -------------------------------------------------------------------------
    // Receiving updates
    // -------------------------------------------------------------------------

    public function getUpdates(int $offset = 0, int $limit = 100): array
    {
        $params = [
            'offset'          => $offset,
            'limit'           => $limit,
            'timeout'         => 0,
            'allowed_updates' => json_encode(['message', 'edited_message']),
        ];
        return $this->request('getUpdates', $params);
    }

    public function getMe(): array
    {
        return $this->request('getMe');
    }

    // -------------------------------------------------------------------------
    // Internal cURL request
    // -------------------------------------------------------------------------

    private function request(string $method, array $params = [], array $files = []): array
    {
        $url = $this->baseUrl . $method;
        $ch  = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if (!empty($files)) {
            $postFields = $params;
            foreach ($files as $fieldName => $filePath) {
                $postFields[$fieldName] = new CURLFile($filePath);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        } elseif (!empty($params)) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new TelegramException("cURL error: {$error}");
        }

        $data = json_decode($response, true);
        if (!$data) {
            throw new TelegramException("Invalid JSON response (HTTP {$httpCode})");
        }
        if (!($data['ok'] ?? false)) {
            $desc = $data['description'] ?? 'Unknown error';
            throw new TelegramException("Telegram API error: {$desc} (HTTP {$httpCode})");
        }

        return $data['result'];
    }
}
