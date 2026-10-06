<?php

    namespace TelegramFederationPlugin\Helpers;

    use FederationLib\Enums\AuditLogType;
    use FederationLib\Objects\AuditLog;
    use FederationLib\Objects\EntityRecord;
    use FederationLib\Objects\OperatorRecord;
    use Symfony\Component\Uid\Uuid;

    class TestData
    {
        public const string ENTITY_HOST = 'example.com';

        public static function uuid(): string
        {
            return Uuid::v7()->toRfc4122();
        }

        public static function localPart(): string
        {
            return uniqid('user');
        }

        public static function address(): string
        {
            return self::localPart() . '@' . self::ENTITY_HOST;
        }

        public static function host(): string
        {
            return uniqid('telegramplugin') . '.example.com';
        }

        public static function url(string $scheme='https'): string
        {
            return sprintf('%s://%s/', $scheme, self::host());
        }

        public static function botToken(): string
        {
            return sprintf('%d:%s', random_int(100000000, 999999999), bin2hex(random_bytes(17)));
        }

        public static function chatId(): string
        {
            return '-100' . random_int(1000000000, 9999999999);
        }

        public static function timestamp(): int
        {
            return time() - random_int(0, 365 * 86400);
        }

        public static function auditLog(AuditLogType $type, array $data=[]): AuditLog
        {
            return new AuditLog(array_merge([
                'uuid' => self::uuid(),
                'type' => $type,
                'message' => uniqid('Audit log message '),
                'timestamp' => self::timestamp(),
            ], $data));
        }

        public static function entity(array $data=[]): EntityRecord
        {
            return new EntityRecord(array_merge([
                'uuid' => self::uuid(),
                'id' => self::localPart(),
                'host' => self::ENTITY_HOST,
                'reputation' => random_int(-1000, 1000),
                'whitelisted' => false,
                'created' => self::timestamp(),
            ], $data));
        }

        public static function operator(array $data=[]): OperatorRecord
        {
            return new OperatorRecord(array_merge([
                'uuid' => self::uuid(),
                'access_token' => bin2hex(random_bytes(16)),
                'name' => uniqid('operator'),
                'created' => self::timestamp(),
            ], $data));
        }
    }
