<?php

    namespace TelegramFederationPlugin\Classes;

    use CurlHandle;
    use TelegramFederationPlugin\Objects\Notification;

    class TelegramClient
    {
        /**
         * Telegram allows up to 4096 characters per message after entity parsing, a margin is kept because Telegram
         * counts UTF-16 code units while mb_strlen() counts code points
         */
        private const int MAX_MESSAGE_LENGTH = 4000;
        private const int CONNECT_TIMEOUT_MS = 3000;
        private const int TIMEOUT_MS = 5000;
        private ?CurlHandle $handle = null;
        /** @var array<string, true> */
        private array $failedTargets = [];

        /**
         * Returns True if notifications can be sent, False if the plugin is disabled, the bot token or chat ID is
         * not set, or sending failed in a way that will not resolve itself
         *
         * @return bool True if notifications can be sent
         */
        public function isAvailable(): bool
        {
            if(!function_exists('curl_init') || !extension_loaded('mbstring') || !Configuration::isEnabled())
            {
                return false;
            }

            if(Configuration::getBotToken() === null || Configuration::getChatId() === null)
            {
                return false;
            }

            if(filter_var(Configuration::getApiEndpoint(), FILTER_VALIDATE_URL) === false)
            {
                return false;
            }

            return !isset($this->failedTargets[$this->getTargetKey()]);
        }

        /**
         * Sends the notification to the configured chat and topic, failures are logged and never thrown, a
         * notification must never affect the operation it is about
         *
         * @param Notification $notification The notification
         * @return bool True if the notification was sent
         */
        public function send(Notification $notification): bool
        {
            if(!$this->isAvailable())
            {
                return false;
            }

            $html = $notification->getText();
            $keyboard = $notification->getInlineKeyboard();

            if(Html::visibleLength($html) > self::MAX_MESSAGE_LENGTH)
            {
                // Cutting HTML may break its tags, a message that is too long is sent as truncated plain text instead
                [$status, $description] = $this->sendMessage(Html::truncate(Html::toPlainText($html), self::MAX_MESSAGE_LENGTH), $keyboard, false);
            }
            else
            {
                [$status, $description] = $this->sendMessage($html, $keyboard, true);
            }

            // Telegram rejects messages it can't parse, and buttons with URLs it doesn't accept (eg; a local address)
            // with a 400, retry once as plain text without the buttons so the notification isn't lost
            if($status === 400)
            {
                Logger::log()->warning(sprintf('Telegram rejected the "%s" notification (%s), retrying as plain text without buttons', $notification->getTitle(), $description ?? 'no description'));
                [$status, $description] = $this->sendMessage(Html::truncate(Html::toPlainText($html), self::MAX_MESSAGE_LENGTH), [], false);
            }

            if($status !== null && $status >= 200 && $status < 300)
            {
                return true;
            }

            // Transport failures and client errors (invalid token, chat or topic) will not resolve themselves, rate
            // limits (429) and server errors (5xx) only drop the current notification
            if($status === null || ($status >= 400 && $status < 500 && $status !== 429))
            {
                $this->failedTargets[$this->getTargetKey()] = true;
                Logger::log()->error(sprintf('Unable to send the "%s" notification to Telegram (%s), no further notifications are sent by this process', $notification->getTitle(), $description ?? sprintf('HTTP %s', $status ?? 'request failed')));
                return false;
            }

            Logger::log()->warning(sprintf('Unable to send the "%s" notification to Telegram (%s)', $notification->getTitle(), $description ?? sprintf('HTTP %d', $status)));
            return false;
        }

        /**
         * Sends a message to the configured chat and topic
         *
         * @param string $text The text of the message
         * @param array $keyboard The rows of the inline keyboard, empty for none
         * @param bool $html True if the text is parsed as HTML
         * @return array{0: int|null, 1: string|null} The HTTP status code (null if the request failed) and Telegram's
         *         description of the error, if any
         */
        private function sendMessage(string $text, array $keyboard, bool $html): array
        {
            if($this->handle === null)
            {
                $handle = curl_init();
                if($handle === false)
                {
                    return [null, 'cURL could not be initialized'];
                }

                curl_setopt($handle, CURLOPT_POST, true);
                curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($handle, CURLOPT_CONNECTTIMEOUT_MS, self::CONNECT_TIMEOUT_MS);
                curl_setopt($handle, CURLOPT_TIMEOUT_MS, self::TIMEOUT_MS);
                curl_setopt($handle, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                $this->handle = $handle;
            }

            $payload = [
                'chat_id' => Configuration::getChatId(),
                'text' => $text,
                'disable_notification' => Configuration::isNotificationDisabled(),
                'link_preview_options' => ['is_disabled' => true],
            ];

            if(Configuration::getTopicId() !== null)
            {
                $payload['message_thread_id'] = Configuration::getTopicId();
            }

            if($html)
            {
                $payload['parse_mode'] = 'HTML';
            }

            if(count($keyboard) > 0)
            {
                $payload['reply_markup'] = ['inline_keyboard' => $keyboard];
            }

            $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            if($body === false)
            {
                return [null, 'The message could not be encoded'];
            }

            // The URL contains the bot token, it must never be logged
            curl_setopt($this->handle, CURLOPT_URL, sprintf('%s/bot%s/sendMessage', Configuration::getApiEndpoint(), Configuration::getBotToken()));
            curl_setopt($this->handle, CURLOPT_POSTFIELDS, $body);

            $response = @curl_exec($this->handle);
            if($response === false)
            {
                $error = curl_error($this->handle);
                $this->handle = null;
                return [null, $error !== '' ? $error : 'The request failed'];
            }

            $status = (int)curl_getinfo($this->handle, CURLINFO_RESPONSE_CODE);
            $decoded = is_string($response) ? json_decode($response, true) : null;
            $description = is_array($decoded) && isset($decoded['description']) && is_string($decoded['description']) ? $decoded['description'] : null;

            return [$status, $description];
        }

        /**
         * Returns a key that uniquely identifies the bot, chat and topic notifications are sent to
         *
         * @return string The target key
         */
        private function getTargetKey(): string
        {
            return hash('sha256', sprintf('%s|%s|%s|%s', Configuration::getApiEndpoint(), Configuration::getBotToken(), Configuration::getChatId(), Configuration::getTopicId() ?? ''));
        }
    }
