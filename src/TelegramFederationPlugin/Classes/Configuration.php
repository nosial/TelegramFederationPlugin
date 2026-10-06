<?php

    namespace TelegramFederationPlugin\Classes;

    use FederationLib\Enums\AuditLogType;
    use FederationLib\Enums\RecordChangeType;

    class Configuration
    {
        public const string WILDCARD = '*';
        private static ?\ConfigLib\Configuration $configuration = null;

        /**
         * Initializes the configuration with its default values
         *
         * @return void
         */
        public static function initialize(): void
        {
            self::$configuration = new \ConfigLib\Configuration('telegram_federation_plugin');

            self::$configuration->setDefault('enabled', true, 'TELEGRAM_FEDERATION_PLUGIN_ENABLED');
            self::$configuration->setDefault('bot_token', null, 'TELEGRAM_FEDERATION_PLUGIN_BOT_TOKEN');
            self::$configuration->setDefault('chat_id', null, 'TELEGRAM_FEDERATION_PLUGIN_CHAT_ID');
            self::$configuration->setDefault('topic_id', null, 'TELEGRAM_FEDERATION_PLUGIN_TOPIC_ID');
            self::$configuration->setDefault('api_endpoint', 'https://api.telegram.org', 'TELEGRAM_FEDERATION_PLUGIN_API_ENDPOINT');
            self::$configuration->setDefault('disable_notification', false, 'TELEGRAM_FEDERATION_PLUGIN_DISABLE_NOTIFICATION');
            self::$configuration->setDefault('federation_web', null, 'TELEGRAM_FEDERATION_PLUGIN_FEDERATION_WEB');
            self::$configuration->setDefault('federation_host', null, 'TELEGRAM_FEDERATION_PLUGIN_FEDERATION_HOST');
            self::$configuration->setDefault('audit_logs', [self::WILDCARD], 'TELEGRAM_FEDERATION_PLUGIN_AUDIT_LOGS');
            self::$configuration->setDefault('record_changes', [], 'TELEGRAM_FEDERATION_PLUGIN_RECORD_CHANGES');
            self::$configuration->setDefault('content_scans', false, 'TELEGRAM_FEDERATION_PLUGIN_CONTENT_SCANS');
            self::$configuration->setDefault('entity_queries', false, 'TELEGRAM_FEDERATION_PLUGIN_ENTITY_QUERIES');
            self::$configuration->setDefault('include_content', true, 'TELEGRAM_FEDERATION_PLUGIN_INCLUDE_CONTENT');
            self::$configuration->setDefault('include_confidential', false, 'TELEGRAM_FEDERATION_PLUGIN_INCLUDE_CONFIDENTIAL');
            self::$configuration->setDefault('max_content_length', 1000, 'TELEGRAM_FEDERATION_PLUGIN_MAX_CONTENT_LENGTH');

            // Only save if the configuration file does not exist or we're in CLI mode
            if(!file_exists(self::$configuration->getPath()) || php_sapi_name() === 'cli')
            {
                self::$configuration->save();
            }
        }

        /**
         * Returns True if notifications are sent, they are still only sent once the bot token and chat ID are set
         *
         * @return bool True if enabled
         */
        public static function isEnabled(): bool
        {
            return self::getBoolean('enabled', true);
        }

        /**
         * Returns the token of the Telegram bot that sends the notifications
         *
         * @return string|null The bot token, null if not set
         */
        public static function getBotToken(): ?string
        {
            return self::getString('bot_token');
        }

        /**
         * Returns the ID of the chat the notifications are sent to
         *
         * @return string|null The chat ID (eg; -1001234567890 or @channel), null if not set
         */
        public static function getChatId(): ?string
        {
            return self::getString('chat_id');
        }

        /**
         * Returns the ID of the forum topic of the chat the notifications are sent to
         *
         * @return int|null The topic ID, null to send them to the chat itself
         */
        public static function getTopicId(): ?int
        {
            $value = self::getString('topic_id');
            if($value === null || filter_var($value, FILTER_VALIDATE_INT) === false)
            {
                return null;
            }

            return (int)$value;
        }

        /**
         * Returns the endpoint of the Telegram Bot API, eg; for a local Bot API server
         *
         * @return string The endpoint without a trailing slash
         */
        public static function getApiEndpoint(): string
        {
            return rtrim(self::getString('api_endpoint') ?? 'https://api.telegram.org', '/');
        }

        /**
         * Returns True if the notifications are sent silently
         *
         * @return bool True if notifications are sent without a sound
         */
        public static function isNotificationDisabled(): bool
        {
            return self::getBoolean('disable_notification', false);
        }

        /**
         * Returns the public URL of the FederationWeb instance the records are opened in
         *
         * @return string|null The URL with a trailing slash (eg; https://audit.nosial.net/), null if not set
         */
        public static function getFederationWeb(): ?string
        {
            return self::getUrl('federation_web');
        }

        /**
         * Returns the public URL of the FederationLib server the records are opened in when no FederationWeb instance
         * is set, and the file attachments are downloaded from
         *
         * @return string|null The URL with a trailing slash (eg; https://federation.nosial.net/), null if not set
         */
        public static function getFederationHost(): ?string
        {
            return self::getUrl('federation_host');
        }

        /**
         * Returns True if audit log entries of the given type are sent
         *
         * @param AuditLogType $type The audit log type
         * @return bool True if the entry is sent
         */
        public static function isAuditLogEnabled(AuditLogType $type): bool
        {
            return self::filterMatches(self::getList('audit_logs', [self::WILDCARD]), $type->value);
        }

        /**
         * Returns True if record changes of the given type are sent
         *
         * @param RecordChangeType $type The record change type
         * @return bool True if the change is sent
         */
        public static function isRecordChangeEnabled(RecordChangeType $type): bool
        {
            return self::filterMatches(self::getList('record_changes', []), $type->value);
        }

        /**
         * Returns True if content scans are sent
         *
         * @return bool True if content scans are sent
         */
        public static function isContentScanEnabled(): bool
        {
            return self::getBoolean('content_scans', false);
        }

        /**
         * Returns True if entity queries are sent
         *
         * @return bool True if entity queries are sent
         */
        public static function isEntityQueryEnabled(): bool
        {
            return self::getBoolean('entity_queries', false);
        }

        /**
         * Returns True if the text content and notes of evidence and the messages of reports are included
         *
         * @return bool True if content is included
         */
        public static function isContentIncluded(): bool
        {
            return self::getBoolean('include_content', true);
        }

        /**
         * Returns True if the content of confidential evidence is included, only if content is included at all
         *
         * @return bool True if confidential content is included
         */
        public static function isConfidentialIncluded(): bool
        {
            return self::isContentIncluded() && self::getBoolean('include_confidential', false);
        }

        /**
         * Returns the maximum number of characters of each content included in a notification
         *
         * @return int The maximum length
         */
        public static function getMaxContentLength(): int
        {
            return max(50, min(3000, (int)self::get('max_content_length', 1000)));
        }

        /**
         * Returns True if the filter contains the value or the wildcard
         *
         * @param string[] $filter The filter values
         * @param string $value The value
         * @return bool True if the value matches
         */
        private static function filterMatches(array $filter, string $value): bool
        {
            return in_array(self::WILDCARD, $filter, true) || in_array($value, $filter, true);
        }

        /**
         * Returns a configuration value
         *
         * @param string $key The configuration key
         * @param mixed $default The value to return if the key does not exist
         * @return mixed The configuration value
         */
        private static function get(string $key, mixed $default): mixed
        {
            if(self::$configuration === null)
            {
                self::initialize();
            }

            return self::$configuration->get($key, $default);
        }

        /**
         * Returns a string configuration value, empty values are treated as not set
         *
         * @param string $key The configuration key
         * @return string|null The configuration value, null if not set
         */
        private static function getString(string $key): ?string
        {
            $value = self::get($key, null);
            if($value === null || is_array($value) || is_bool($value))
            {
                return null;
            }

            $value = trim((string)$value);
            return $value === '' ? null : $value;
        }

        /**
         * Returns a URL configuration value with a trailing slash, invalid URLs are treated as not set
         *
         * @param string $key The configuration key
         * @return string|null The URL, null if not set or invalid
         */
        private static function getUrl(string $key): ?string
        {
            $value = self::getString($key);
            if($value === null || filter_var($value, FILTER_VALIDATE_URL) === false || !preg_match('/^https?:\/\//i', $value))
            {
                return null;
            }

            return rtrim($value, '/') . '/';
        }

        /**
         * Returns a list configuration value, values set by environment variables are comma separated strings (eg;
         * "REPORT_CREATED, REPORT_CLOSED") and are parsed accordingly
         *
         * @param string $key The configuration key
         * @param string[] $default The values to return if the key does not exist
         * @return string[] The upper case values
         */
        private static function getList(string $key, array $default): array
        {
            $value = self::get($key, $default);
            if(is_string($value))
            {
                $value = explode(',', $value);
            }

            if(!is_array($value))
            {
                return [];
            }

            $values = [];
            foreach($value as $item)
            {
                if(!is_string($item))
                {
                    continue;
                }

                $item = strtoupper(trim($item));
                if($item !== '')
                {
                    $values[] = $item;
                }
            }

            return $values;
        }

        /**
         * Returns a boolean configuration value, values set by environment variables are strings (eg; "false") and
         * are parsed accordingly
         *
         * @param string $key The configuration key
         * @param bool $default The value to return if the key does not exist or is not a boolean
         * @return bool The configuration value
         */
        private static function getBoolean(string $key, bool $default): bool
        {
            $value = self::get($key, $default);
            if(is_bool($value))
            {
                return $value;
            }

            return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
        }
    }
