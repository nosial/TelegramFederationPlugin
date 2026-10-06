<?php

    namespace TelegramFederationPlugin\Helpers;

    use RuntimeException;

    class TestChat
    {
        private const string API_ENDPOINT = 'https://api.telegram.org';
        private const int MESSAGES_PER_MINUTE = 18;
        private const int MAX_CAPTURED = 10;

        /** @var float[] The times of the messages sent to the chat in the last minute */
        private static array $sentTimes = [];
        private static ?int $lastMarker = null;

        public static function isConfigured(): bool
        {
            return self::getBotToken() !== null && self::getChatId() !== null;
        }

        public static function getBotToken(): ?string
        {
            return self::getEnvironment('TELEGRAM_TEST_BOT_TOKEN');
        }

        public static function getChatId(): ?string
        {
            return self::getEnvironment('TELEGRAM_TEST_CHAT_ID');
        }

        public static function getTopicId(): ?string
        {
            return self::getEnvironment('TELEGRAM_TEST_TOPIC_ID');
        }

        public static function options(array $options=[]): array
        {
            return array_merge([
                'bot_token' => self::getBotToken() ?? '',
                'chat_id' => self::getChatId() ?? '',
                'topic_id' => self::getTopicId() ?? '',
                'disable_notification' => 'true',
            ], $options);
        }

        public static function reserve(int $messages): void
        {
            if(self::$lastMarker !== null)
            {
                self::deleteMessage(self::$lastMarker);
                self::$lastMarker = null;
            }

            self::wait($messages);
        }

        public static function capture(callable $operation, int $expected=1): ?array
        {
            if(!self::isConfigured())
            {
                $operation();
                return null;
            }

            // The markers, the expected messages and their forwarded copies
            self::wait((self::$lastMarker === null ? 2 : 1) + $expected * 2);
            $before = self::$lastMarker ?? self::sendMarker();

            try
            {
                $operation();
            }
            finally
            {
                $after = self::sendMarker();
                self::$lastMarker = $after;
            }

            $texts = [];
            for($messageId = $before + 1; $messageId < $after && $messageId <= $before + self::MAX_CAPTURED; $messageId++)
            {
                $text = self::read($messageId);
                if($text !== null)
                {
                    $texts[] = $text;
                }
            }

            self::deleteMessage($before);
            return $texts;
        }

        private static function read(int $messageId): ?string
        {
            $forwarded = self::request('forwardMessage', array_merge(self::getTarget(), [
                'from_chat_id' => self::getChatId(),
                'message_id' => $messageId,
                'disable_notification' => true,
            ]), true);

            if($forwarded === null)
            {
                return null;
            }

            self::deleteMessage((int)$forwarded['message_id']);
            return (string)($forwarded['text'] ?? $forwarded['caption'] ?? '');
        }

        private static function sendMarker(): int
        {
            $message = self::request('sendMessage', array_merge(self::getTarget(), [
                'text' => '#TEST ' . uniqid('marker_'),
                'disable_notification' => true,
            ]));

            // The last marker is kept for the next capture, until the tests are done
            static $cleanup = false;
            if(!$cleanup)
            {
                $cleanup = true;
                register_shutdown_function(function()
                {
                    if(self::$lastMarker !== null)
                    {
                        self::deleteMessage(self::$lastMarker);
                    }
                });
            }

            return (int)$message['message_id'];
        }

        private static function deleteMessage(int $messageId): void
        {
            // A bot can delete its own messages, the message may already be deleted
            self::request('deleteMessage', ['chat_id' => self::getChatId(), 'message_id' => $messageId], true);
        }

        private static function wait(int $messages): void
        {
            while(true)
            {
                self::$sentTimes = array_values(array_filter(self::$sentTimes, fn(float $time) => $time > microtime(true) - 60));
                if(count(self::$sentTimes) + $messages <= self::MESSAGES_PER_MINUTE || count(self::$sentTimes) === 0)
                {
                    break;
                }

                usleep((int)((self::$sentTimes[0] + 60 - microtime(true)) * 1000000) + 100000);
            }

            for($i = 0; $i < $messages; $i++)
            {
                self::$sentTimes[] = microtime(true);
            }
        }

        private static function getTarget(): array
        {
            $target = ['chat_id' => self::getChatId()];
            if(self::getTopicId() !== null)
            {
                $target['message_thread_id'] = (int)self::getTopicId();
            }

            return $target;
        }

        private static function request(string $method, array $parameters, bool $allowRejection=false): mixed
        {
            for($attempt = 1; ; $attempt++)
            {
                $handle = curl_init(sprintf('%s/bot%s/%s', self::API_ENDPOINT, self::getBotToken(), $method));
                curl_setopt_array($handle, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($parameters),
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 30,
                ]);

                $body = curl_exec($handle);
                if($body === false)
                {
                    // The URL contains the bot token, it's never included
                    throw new RuntimeException(sprintf('The Telegram %s request failed: %s', $method, curl_error($handle)));
                }

                $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
                $response = json_decode((string)$body, true);

                if($status === 429 && $attempt < 5)
                {
                    sleep(max(1, (int)($response['parameters']['retry_after'] ?? 5)));
                    continue;
                }

                if(($response['ok'] ?? false) === true)
                {
                    return $response['result'];
                }

                if($status === 400 && $allowRejection)
                {
                    return null;
                }

                throw new RuntimeException(sprintf('The Telegram %s request failed (HTTP %d): %s', $method, $status, $response['description'] ?? 'no description'));
            }
        }

        private static function getEnvironment(string $name): ?string
        {
            $value = getenv($name);
            if($value === false || trim($value) === '')
            {
                return null;
            }

            return trim($value);
        }
    }