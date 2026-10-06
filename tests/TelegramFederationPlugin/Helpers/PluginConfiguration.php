<?php

    namespace TelegramFederationPlugin\Helpers;

    use TelegramFederationPlugin\Classes\Configuration;

    class PluginConfiguration
    {
        private const array DEFAULTS = [
            'enabled' => 'true',
            'bot_token' => '',
            'chat_id' => '',
            'topic_id' => '',
            'api_endpoint' => 'https://api.telegram.org',
            'disable_notification' => 'false',
            'federation_web' => '',
            'federation_host' => '',
            'audit_logs' => '*',
            'record_changes' => '',
            'content_scans' => 'false',
            'entity_queries' => 'false',
            'include_content' => 'true',
            'include_confidential' => 'false',
            'max_content_length' => '1000',
        ];

        public static function apply(array $options=[]): void
        {
            foreach(array_merge(self::DEFAULTS, $options) as $option => $value)
            {
                putenv(sprintf('TELEGRAM_FEDERATION_PLUGIN_%s=%s', strtoupper($option), $value));
            }

            Configuration::initialize();
        }

        public static function reset(): void
        {
            self::apply();
        }
    }
