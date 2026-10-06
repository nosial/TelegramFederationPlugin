<?php

    namespace TelegramFederationPlugin\Classes;

    use FederationLib\Enums\RecordType;

    class RecordLinks
    {
        /**
         * Returns the URL that opens the record, FederationWeb is preferred over the FederationLib server
         *
         * @param RecordType $type The type of the record
         * @param string|null $uuid The UUID of the record
         * @return string|null The URL, null if neither is configured or the record has no page
         */
        public static function getUrl(RecordType $type, ?string $uuid): ?string
        {
            return self::getWebUrl($type, $uuid) ?? self::getHostUrl($type, $uuid);
        }

        /**
         * Returns the URL of the record's page in FederationWeb
         *
         * @param RecordType $type The type of the record
         * @param string|null $uuid The UUID of the record
         * @return string|null The URL, eg; https://audit.nosial.net/reports/{uuid}, null if FederationWeb is not
         *         configured or has no page for the record type
         */
        public static function getWebUrl(RecordType $type, ?string $uuid): ?string
        {
            $path = match($type)
            {
                RecordType::ENTITY => 'entities',
                RecordType::EVIDENCE => 'evidence',
                RecordType::BLACKLIST => 'blacklist',
                RecordType::REPORT => 'reports',
                RecordType::AUDIT_LOG => 'audit-log',
                RecordType::OPERATOR => 'operators',
                RecordType::ATTACHMENT => null,
            };

            return self::build(Configuration::getFederationWeb(), $path, $uuid);
        }

        /**
         * Returns the URL of the record on the FederationLib server, for file attachments this downloads the file
         *
         * @param RecordType $type The type of the record
         * @param string|null $uuid The UUID of the record
         * @return string|null The URL, eg; https://federation.nosial.net/reports/{uuid}, null if the FederationLib
         *         server is not configured
         */
        public static function getHostUrl(RecordType $type, ?string $uuid): ?string
        {
            $path = match($type)
            {
                RecordType::ENTITY => 'entities',
                RecordType::EVIDENCE => 'evidence',
                RecordType::BLACKLIST => 'blacklist',
                RecordType::REPORT => 'reports',
                RecordType::AUDIT_LOG => 'audit',
                RecordType::OPERATOR => 'operators',
                RecordType::ATTACHMENT => 'attachments',
            };

            return self::build(Configuration::getFederationHost(), $path, $uuid);
        }

        /**
         * Builds the URL of a record
         *
         * @param string|null $baseUrl The base URL with a trailing slash
         * @param string|null $path The path of the record type
         * @param string|null $uuid The UUID of the record
         * @return string|null The URL, null if any part is missing
         */
        private static function build(?string $baseUrl, ?string $path, ?string $uuid): ?string
        {
            if($baseUrl === null || $path === null || $uuid === null || $uuid === '')
            {
                return null;
            }

            return $baseUrl . $path . '/' . rawurlencode($uuid);
        }
    }
