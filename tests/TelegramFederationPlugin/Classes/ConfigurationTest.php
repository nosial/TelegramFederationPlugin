<?php

    namespace TelegramFederationPlugin\Classes;

    use FederationLib\Enums\AuditLogType;
    use FederationLib\Enums\RecordChangeType;
    use PHPUnit\Framework\Attributes\DataProvider;
    use TelegramFederationPlugin\Helpers\PluginConfiguration;
    use TelegramFederationPlugin\Helpers\PluginTestCase;
    use TelegramFederationPlugin\Helpers\TestData;

    /**
     * The options are set with their environment variables, which are strings, the same way as in Docker
     */
    class ConfigurationTest extends PluginTestCase
    {
        public static function auditLogTypes(): array
        {
            return self::cases(AuditLogType::cases());
        }

        public static function recordChangeTypes(): array
        {
            return self::cases(RecordChangeType::cases());
        }

        public static function falseValues(): array
        {
            return ['false' => ['false'], '0' => ['0'], 'no' => ['no'], 'off' => ['off']];
        }

        public static function trueValues(): array
        {
            return ['true' => ['true'], '1' => ['1'], 'yes' => ['yes'], 'on' => ['on']];
        }

        public function testEnabledByDefault(): void
        {
            $this->assertTrue(Configuration::isEnabled());
        }

        public function testTelegramTargetIsNotSetByDefault(): void
        {
            $this->assertNull(Configuration::getBotToken());
            $this->assertNull(Configuration::getChatId());
            $this->assertNull(Configuration::getTopicId());
        }

        public function testRecordUrlsAreNotSetByDefault(): void
        {
            $this->assertNull(Configuration::getFederationWeb());
            $this->assertNull(Configuration::getFederationHost());
        }

        #[DataProvider('auditLogTypes')]
        public function testAuditLogIsSentByDefault(AuditLogType $type): void
        {
            $this->assertTrue(Configuration::isAuditLogEnabled($type));
        }

        #[DataProvider('recordChangeTypes')]
        public function testRecordChangeIsNotSentByDefault(RecordChangeType $type): void
        {
            $this->assertFalse(Configuration::isRecordChangeEnabled($type));
        }

        public function testContentScansAreNotSentByDefault(): void
        {
            $this->assertFalse(Configuration::isContentScanEnabled());
        }

        public function testEntityQueriesAreNotSentByDefault(): void
        {
            $this->assertFalse(Configuration::isEntityQueryEnabled());
        }

        public function testContentIsIncludedByDefault(): void
        {
            $this->assertTrue(Configuration::isContentIncluded());
        }

        public function testConfidentialContentIsNotIncludedByDefault(): void
        {
            $this->assertFalse(Configuration::isConfidentialIncluded());
        }

        public function testBotTokenIsTrimmed(): void
        {
            $botToken = TestData::botToken();
            PluginConfiguration::apply(['bot_token' => " $botToken "]);

            $this->assertSame($botToken, Configuration::getBotToken());
        }

        public function testBlankBotTokenIsNotSet(): void
        {
            PluginConfiguration::apply(['bot_token' => '   ']);

            $this->assertNull(Configuration::getBotToken());
        }

        public function testTopicIdIsAnInteger(): void
        {
            $topicId = random_int(1, PHP_INT_MAX);
            PluginConfiguration::apply(['topic_id' => (string)$topicId]);

            $this->assertSame($topicId, Configuration::getTopicId());
        }

        public function testTopicIdThatIsNotAnIntegerIsNotSet(): void
        {
            PluginConfiguration::apply(['topic_id' => uniqid('topic')]);

            $this->assertNull(Configuration::getTopicId());
        }

        public function testApiEndpointHasNoTrailingSlash(): void
        {
            $url = TestData::url();
            PluginConfiguration::apply(['api_endpoint' => $url]);

            $this->assertSame(rtrim($url, '/'), Configuration::getApiEndpoint());
        }

        public function testFederationWebHasATrailingSlash(): void
        {
            $url = TestData::url();
            PluginConfiguration::apply(['federation_web' => rtrim($url, '/')]);

            $this->assertSame($url, Configuration::getFederationWeb());
        }

        public function testFederationHostHasATrailingSlash(): void
        {
            $url = TestData::url('http');
            PluginConfiguration::apply(['federation_host' => rtrim($url, '/')]);

            $this->assertSame($url, Configuration::getFederationHost());
        }

        public function testUrlThatIsNotHttpIsNotSet(): void
        {
            PluginConfiguration::apply(['federation_web' => TestData::url('ftp')]);

            $this->assertNull(Configuration::getFederationWeb());
        }

        public function testInvalidUrlIsNotSet(): void
        {
            PluginConfiguration::apply(['federation_host' => TestData::host()]);

            $this->assertNull(Configuration::getFederationHost());
        }

        public function testListedTypeIsSelected(): void
        {
            PluginConfiguration::apply(['audit_logs' => 'REPORT_CLOSED,ENTITY_BLACKLISTED']);

            $this->assertTrue(Configuration::isAuditLogEnabled(AuditLogType::ENTITY_BLACKLISTED));
        }

        public function testUnlistedTypeIsNotSelected(): void
        {
            PluginConfiguration::apply(['audit_logs' => 'REPORT_CLOSED,ENTITY_BLACKLISTED']);

            $this->assertFalse(Configuration::isAuditLogEnabled(AuditLogType::OPERATOR_CREATED));
        }

        public function testListsAreCaseInsensitiveAndTrimmed(): void
        {
            PluginConfiguration::apply(['record_changes' => ' report_created ,']);

            $this->assertTrue(Configuration::isRecordChangeEnabled(RecordChangeType::REPORT_CREATED));
        }

        #[DataProvider('recordChangeTypes')]
        public function testWildcardSelectsEveryType(RecordChangeType $type): void
        {
            PluginConfiguration::apply(['record_changes' => '*']);

            $this->assertTrue(Configuration::isRecordChangeEnabled($type));
        }

        #[DataProvider('auditLogTypes')]
        public function testEmptyListSelectsNoType(AuditLogType $type): void
        {
            PluginConfiguration::apply(['audit_logs' => '']);

            $this->assertFalse(Configuration::isAuditLogEnabled($type));
        }

        #[DataProvider('falseValues')]
        public function testFalseValues(string $value): void
        {
            PluginConfiguration::apply(['enabled' => $value]);

            $this->assertFalse(Configuration::isEnabled());
        }

        #[DataProvider('trueValues')]
        public function testTrueValues(string $value): void
        {
            PluginConfiguration::apply(['content_scans' => $value]);

            $this->assertTrue(Configuration::isContentScanEnabled());
        }

        public function testValueThatIsNotABooleanIsTheDefault(): void
        {
            PluginConfiguration::apply(['enabled' => uniqid('value')]);

            $this->assertTrue(Configuration::isEnabled());
        }

        public function testConfidentialContentRequiresContent(): void
        {
            PluginConfiguration::apply(['include_content' => 'false', 'include_confidential' => 'true']);

            $this->assertFalse(Configuration::isConfidentialIncluded());
        }

        public function testMaxContentLength(): void
        {
            $length = random_int(50, 3000);
            PluginConfiguration::apply(['max_content_length' => (string)$length]);

            $this->assertSame($length, Configuration::getMaxContentLength());
        }

        public function testMaxContentLengthIsAtLeast50(): void
        {
            PluginConfiguration::apply(['max_content_length' => (string)random_int(PHP_INT_MIN, 49)]);

            $this->assertSame(50, Configuration::getMaxContentLength());
        }

        public function testMaxContentLengthIsAtMost3000(): void
        {
            PluginConfiguration::apply(['max_content_length' => (string)random_int(3001, PHP_INT_MAX)]);

            $this->assertSame(3000, Configuration::getMaxContentLength());
        }

        private static function cases(array $cases): array
        {
            $data = [];
            foreach($cases as $case)
            {
                $data[$case->value] = [$case];
            }

            return $data;
        }
    }
