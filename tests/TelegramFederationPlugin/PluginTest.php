<?php

    /** @noinspection PhpUnhandledExceptionInspection */

    namespace TelegramFederationPlugin;

    use FederationLib\Classes\PluginManager;
    use FederationLib\Enums\AuditLogType;
    use FederationLib\Enums\EventType;
    use FederationLib\Enums\RecordChangeType;
    use FederationLib\Objects\ContentInput;
    use FederationLib\Objects\Plugin;
    use FederationLib\Objects\Plugin\ContentScan;
    use FederationLib\Objects\Plugin\EntityQuery;
    use PHPUnit\Framework\Attributes\DataProvider;
    use TelegramFederationPlugin\Handlers\AuditLogHandler;
    use TelegramFederationPlugin\Handlers\ContentScanHandler;
    use TelegramFederationPlugin\Handlers\QueryEntityHandler;
    use TelegramFederationPlugin\Handlers\RecordChangeHandler;
    use TelegramFederationPlugin\Helpers\PluginConfiguration;
    use TelegramFederationPlugin\Helpers\PluginTestCase;
    use TelegramFederationPlugin\Helpers\TestData;

    class PluginTest extends PluginTestCase
    {
        private const string PACKAGE = 'net.nosial.telegram_federation_plugin';

        protected function setUp(): void
        {
            parent::setUp();
            PluginManager::setPlugins([Plugin::load(self::PACKAGE)]);
        }

        protected function tearDown(): void
        {
            PluginManager::setPlugins([]);
            parent::tearDown();
        }

        public static function eventHandlers(): array
        {
            return [
                EventType::AUDIT_LOG->value => [EventType::AUDIT_LOG, AuditLogHandler::class],
                EventType::RECORD_CHANGE->value => [EventType::RECORD_CHANGE, RecordChangeHandler::class],
                EventType::CONTENT_SCAN->value => [EventType::CONTENT_SCAN, ContentScanHandler::class],
                EventType::QUERY_ENTITY->value => [EventType::QUERY_ENTITY, QueryEntityHandler::class],
            ];
        }

        #[DataProvider('eventHandlers')]
        public function testEventIsHandled(EventType $event, string $class): void
        {
            $handlers = PluginManager::getPlugin(self::PACKAGE)->getEventHandlers($event);

            $this->assertCount(1, $handlers);
            $this->assertSame($class, $handlers[0]->getClass());
            $this->assertSame([], $handlers[0]->getFilter());
        }

        public function testPluginHasNoRoutes(): void
        {
            $this->assertSame([], PluginManager::getPlugin(self::PACKAGE)->getRequestHandlers());
        }

        public function testPluginIsValid(): void
        {
            $result = PluginManager::validateRoutes([PluginManager::getPlugin(self::PACKAGE)]);

            $this->assertSame([], $result['errors']);
            $this->assertSame([], $result['warnings']);
        }

        public function testSelectedAuditLogIsSent(): void
        {
            $this->configureUnreachableTelegram(['audit_logs' => 'REPORT_CLOSED']);

            PluginManager::dispatchAuditLog(TestData::auditLog(AuditLogType::REPORT_CLOSED));

            $this->assertNotificationSent();
        }

        public function testUnselectedAuditLogIsNotSent(): void
        {
            $this->configureUnreachableTelegram(['audit_logs' => 'REPORT_CLOSED']);

            PluginManager::dispatchAuditLog(TestData::auditLog(AuditLogType::OPERATOR_CREATED));

            $this->assertNoNotificationSent();
        }

        public function testSelectedRecordChangeIsSent(): void
        {
            $this->configureUnreachableTelegram(['record_changes' => 'REPORT_DELETED']);

            // A deletion, FederationLib never retrieves a deleted record from the database
            PluginManager::dispatchRecordChange(RecordChangeType::REPORT_DELETED, TestData::uuid());

            $this->assertNotificationSent();
        }

        public function testUnselectedRecordChangeIsNotSent(): void
        {
            $this->configureUnreachableTelegram(['record_changes' => 'REPORT_DELETED']);

            PluginManager::dispatchRecordChange(RecordChangeType::ENTITY_DELETED, TestData::uuid());

            $this->assertNoNotificationSent();
        }

        public function testContentScanIsSent(): void
        {
            $this->configureUnreachableTelegram(['content_scans' => 'true']);

            PluginManager::dispatchContentScan(new ContentScan([new ContentInput(uniqid('Content '))], null, null, []));

            $this->assertNotificationSent();
        }

        public function testContentScanIsNotSentByDefault(): void
        {
            $this->configureUnreachableTelegram();

            PluginManager::dispatchContentScan(new ContentScan([new ContentInput(uniqid('Content '))], null, null, []));

            $this->assertNoNotificationSent();
        }

        public function testContentScanIsOnlyObserved(): void
        {
            $this->configureUnreachableTelegram(['content_scans' => 'true']);
            $contentScan = new ContentScan([new ContentInput(uniqid('Content '))], null, null, []);

            PluginManager::dispatchContentScan($contentScan);

            $this->assertSame([], $contentScan->getScanResults());
            $this->assertSame([], $contentScan->getAddedClassifications());
        }

        public function testEntityQueryIsSent(): void
        {
            $this->configureUnreachableTelegram(['entity_queries' => 'true']);
            $entity = TestData::entity();

            PluginManager::dispatchQueryEntity(new EntityQuery($entity->getAddress(), $entity, [], []));

            $this->assertNotificationSent();
        }

        public function testEntityQueryIsNotSentByDefault(): void
        {
            $this->configureUnreachableTelegram();
            $entity = TestData::entity();

            PluginManager::dispatchQueryEntity(new EntityQuery($entity->getAddress(), $entity, [], []));

            $this->assertNoNotificationSent();
        }

        public function testEntityQueryIsOnlyObserved(): void
        {
            $this->configureUnreachableTelegram(['entity_queries' => 'true']);
            $entity = TestData::entity();
            $entityQuery = new EntityQuery($entity->getAddress(), $entity, [], []);

            PluginManager::dispatchQueryEntity($entityQuery);

            $this->assertSame([], $entityQuery->getAddedEntityMetadata());
            $this->assertFalse($entityQuery->isSuggestedActionOverridden());
        }

        public function testNothingIsSentWhileDisabled(): void
        {
            $this->configureUnreachableTelegram();
            PluginConfiguration::apply(array_merge($this->unreachableTelegram, ['enabled' => 'false']));

            PluginManager::dispatchAuditLog(TestData::auditLog(AuditLogType::REPORT_CLOSED));

            // Enabled again, the target would no longer be available if a notification had been sent to it
            PluginConfiguration::apply($this->unreachableTelegram);
            $this->assertNoNotificationSent();
        }
    }
