<?php

    namespace TelegramFederationPlugin\Classes;

    use FederationLib\Enums\AuditLogType;
    use FederationLib\Enums\IncidentType;
    use FederationLib\Enums\RecordChangeType;
    use FederationLib\Objects\BlacklistRecord;
    use FederationLib\Objects\ContentInput;
    use FederationLib\Objects\EntityRecord;
    use FederationLib\Objects\Plugin\ContentScan;
    use FederationLib\Objects\Plugin\EntityQuery;
    use FederationLib\Objects\Plugin\RecordChange;
    use TelegramFederationPlugin\Helpers\PluginConfiguration;
    use TelegramFederationPlugin\Helpers\PluginTestCase;
    use TelegramFederationPlugin\Helpers\TestData;

    class NotificationFactoryTest extends PluginTestCase
    {
        public function testAuditLogTitle(): void
        {
            $notification = NotificationBuilder::fromAuditLog(TestData::auditLog(AuditLogType::BLACKLIST_LIFTED));

            $this->assertSame('Blacklist Lifted', $notification->getTitle());
        }

        public function testAuditLogHashtags(): void
        {
            $text = NotificationBuilder::fromAuditLog(TestData::auditLog(AuditLogType::BLACKLIST_LIFTED))->getText();

            $this->assertStringStartsWith('#AUDIT_LOG #BLACKLIST_LIFTED', $text);
        }

        public function testAuditLogMessageIsQuoted(): void
        {
            $message = uniqid('Lifted <early> & ');

            $text = NotificationBuilder::fromAuditLog(TestData::auditLog(AuditLogType::BLACKLIST_LIFTED, ['message' => $message]))->getText();

            $this->assertStringContainsString(sprintf("<b>Message:</b>\n<blockquote>%s</blockquote>", Html::escape($message)), $text);
        }

        public function testAuditLogMessageIsTruncated(): void
        {
            $length = random_int(50, 200);
            PluginConfiguration::apply(['max_content_length' => (string)$length]);

            $text = NotificationBuilder::fromAuditLog(TestData::auditLog(AuditLogType::OTHER, ['message' => str_repeat('a', $length + 1)]))->getText();

            $this->assertStringContainsString(sprintf('<blockquote>%s...</blockquote>', str_repeat('a', $length - 3)), $text);
        }

        public function testAuditLogLinksItsRecords(): void
        {
            $web = TestData::url();
            PluginConfiguration::apply(['federation_web' => $web]);
            $auditLog = TestData::auditLog(AuditLogType::BLACKLIST_LIFTED, ['blacklist' => TestData::uuid()]);

            $text = NotificationBuilder::fromAuditLog($auditLog)->getText();

            $this->assertStringContainsString(sprintf('<b>Blacklist:</b> %s', Html::link($auditLog->getBlacklistUuid(), $web . 'blacklist/' . $auditLog->getBlacklistUuid())), $text);
        }

        public function testAuditLogButtons(): void
        {
            $web = TestData::url();
            PluginConfiguration::apply(['federation_web' => $web]);
            $auditLog = TestData::auditLog(AuditLogType::BLACKLIST_LIFTED, ['blacklist' => TestData::uuid()]);

            $notification = NotificationBuilder::fromAuditLog($auditLog);

            $this->assertSame([$web . 'audit-log/' . $auditLog->getUuid(), $web . 'blacklist/' . $auditLog->getBlacklistUuid()], $notification->getButtonUrls());
        }

        public function testRecordChangeHashtags(): void
        {
            $text = NotificationBuilder::fromRecordChange(new RecordChange(RecordChangeType::REPORT_DELETED, TestData::uuid()))->getText();

            $this->assertStringStartsWith('#RECORD_CHANGE #REPORT_DELETED', $text);
        }

        public function testDeletedRecord(): void
        {
            $uuid = TestData::uuid();

            $text = NotificationBuilder::fromRecordChange(new RecordChange(RecordChangeType::REPORT_DELETED, $uuid))->getText();

            $this->assertStringContainsString(sprintf("<b>Report:</b> %s\n<i>The record was deleted</i>", Html::code($uuid)), $text);
        }

        public function testEntity(): void
        {
            $host = TestData::url();
            PluginConfiguration::apply(['federation_host' => $host]);
            $entity = TestData::entity();

            $text = NotificationBuilder::fromRecordChange(new RecordChange(RecordChangeType::ENTITY_CREATED, $entity->getUuid(), $entity))->getText();

            $this->assertStringContainsString(sprintf('<b>Entity:</b> %s', Html::link($entity->getAddress(), $host . 'entities/' . $entity->getUuid())), $text);
        }

        public function testEntityReputation(): void
        {
            $entity = TestData::entity();

            $text = NotificationBuilder::fromRecordChange(new RecordChange(RecordChangeType::ENTITY_REPUTATION_UPDATED, $entity->getUuid(), $entity))->getText();

            $this->assertStringContainsString(sprintf('<b>Reputation:</b> %d', $entity->getReputation()), $text);
        }

        public function testWhitelistedEntity(): void
        {
            $entity = TestData::entity(['whitelisted' => true]);

            $text = NotificationBuilder::fromRecordChange(new RecordChange(RecordChangeType::ENTITY_UPDATED, $entity->getUuid(), $entity))->getText();

            $this->assertStringContainsString('<b>Whitelisted:</b> Yes', $text);
        }

        public function testEntityButton(): void
        {
            $host = TestData::url();
            PluginConfiguration::apply(['federation_host' => $host]);
            $entity = TestData::entity();

            $notification = NotificationBuilder::fromRecordChange(new RecordChange(RecordChangeType::ENTITY_CREATED, $entity->getUuid(), $entity));

            $this->assertSame([$host . 'entities/' . $entity->getUuid()], $notification->getButtonUrls());
        }

        public function testOperator(): void
        {
            $operator = TestData::operator();

            $text = NotificationBuilder::fromRecordChange(new RecordChange(RecordChangeType::OPERATOR_CREATED, $operator->getUuid(), $operator))->getText();

            $this->assertStringContainsString(sprintf('<b>Operator:</b> %s', Html::code($operator->getName())), $text);
        }

        public function testOperatorPermissions(): void
        {
            $operator = TestData::operator(['operator_permissions' => true, 'client_permissions' => true]);

            $text = NotificationBuilder::fromRecordChange(new RecordChange(RecordChangeType::OPERATOR_UPDATED, $operator->getUuid(), $operator))->getText();

            $this->assertStringContainsString('<b>Permissions:</b> Operator, Client', $text);
        }

        public function testDisabledOperator(): void
        {
            $operator = TestData::operator(['disabled' => true]);

            $text = NotificationBuilder::fromRecordChange(new RecordChange(RecordChangeType::OPERATOR_DISABLED, $operator->getUuid(), $operator))->getText();

            $this->assertStringContainsString('<b>Status:</b> Disabled', $text);
        }

        public function testOperatorAccessTokenIsNeverIncluded(): void
        {
            PluginConfiguration::apply(['include_content' => 'true', 'include_confidential' => 'true']);
            $operator = TestData::operator(['management_permissions' => true]);

            $text = NotificationBuilder::fromRecordChange(new RecordChange(RecordChangeType::OPERATOR_CREATED, $operator->getUuid(), $operator))->getText();

            $this->assertStringNotContainsString($operator->getAccessToken(), $text);
        }

        public function testContentScanHashtags(): void
        {
            $text = NotificationBuilder::fromContentScan($this->contentScan(new ContentInput(uniqid('Content '))))->getText();

            $this->assertStringStartsWith('#CONTENT_SCAN', $text);
        }

        public function testContentScanByAnUnknownAuthor(): void
        {
            $contentScan = $this->contentScan(new ContentInput(uniqid('Content ')));

            $text = NotificationBuilder::fromContentScan($contentScan)->getText();

            $this->assertStringContainsString(sprintf('<b>Author:</b> %s <i>(unknown)</i>', Html::code($contentScan->getAuthorIdentifier())), $text);
        }

        public function testAnonymousContentScan(): void
        {
            $text = NotificationBuilder::fromContentScan($this->contentScan(new ContentInput(uniqid('Content '))))->getText();

            $this->assertStringContainsString('<b>Requested by:</b> <i>Anonymous</i>', $text);
        }

        public function testContentScanCountsTheEvidence(): void
        {
            $evidence = array_map(fn() => new ContentInput(uniqid('Content ')), range(1, random_int(1, 5)));

            $text = NotificationBuilder::fromContentScan($this->contentScan(...$evidence))->getText();

            $this->assertStringContainsString(sprintf('<b>Evidence:</b> %d', count($evidence)), $text);
        }

        public function testContentIsQuoted(): void
        {
            $content = uniqid('<Scanned> & content ');

            $text = NotificationBuilder::fromContentScan($this->contentScan(new ContentInput($content)))->getText();

            $this->assertStringContainsString(sprintf("<b>Content #1:</b>\n<blockquote>%s</blockquote>", Html::escape($content)), $text);
        }

        public function testEvidenceWithoutTextContentIsNotQuoted(): void
        {
            $text = NotificationBuilder::fromContentScan($this->contentScan(new ContentInput(null, uniqid('Note '))))->getText();

            $this->assertStringNotContainsString('Content #1', $text);
        }

        public function testConfidentialContentIsLeftOutByDefault(): void
        {
            $content = uniqid('Confidential content ');

            $text = NotificationBuilder::fromContentScan($this->contentScan(new ContentInput($content, confidential: true)))->getText();

            $this->assertStringNotContainsString($content, $text);
        }

        public function testConfidentialContentIsMentioned(): void
        {
            $text = NotificationBuilder::fromContentScan($this->contentScan(new ContentInput(uniqid('Confidential content '), confidential: true)))->getText();

            $this->assertStringContainsString('<i>The content of evidence #1 is confidential</i>', $text);
        }

        public function testConfidentialContentCanBeIncluded(): void
        {
            PluginConfiguration::apply(['include_confidential' => 'true']);
            $content = uniqid('Confidential content ');

            $text = NotificationBuilder::fromContentScan($this->contentScan(new ContentInput($content, confidential: true)))->getText();

            $this->assertStringContainsString($content, $text);
        }

        public function testContentCanBeLeftOut(): void
        {
            PluginConfiguration::apply(['include_content' => 'false']);
            $content = uniqid('Content ');

            $text = NotificationBuilder::fromContentScan($this->contentScan(new ContentInput($content)))->getText();

            $this->assertStringNotContainsString($content, $text);
        }

        public function testScanContentIsTruncatedToHalfOfTheMaximumLength(): void
        {
            $length = random_int(100, 1000);
            PluginConfiguration::apply(['max_content_length' => (string)$length]);

            $text = NotificationBuilder::fromContentScan($this->contentScan(new ContentInput(str_repeat('a', $length))))->getText();

            $this->assertStringContainsString(str_repeat('a', intdiv($length, 2) - 3) . '...</blockquote>', $text);
        }

        public function testEntityQueryHashtags(): void
        {
            $text = NotificationBuilder::fromEntityQuery($this->entityQuery(TestData::entity()))->getText();

            $this->assertStringStartsWith('#QUERY_ENTITY', $text);
        }

        public function testEntityQueryIdentifier(): void
        {
            $entity = TestData::entity();

            $text = NotificationBuilder::fromEntityQuery($this->entityQuery($entity))->getText();

            $this->assertStringContainsString(sprintf('<b>Identifier:</b> %s', Html::code($entity->getAddress())), $text);
        }

        public function testEntityQueryLinksTheEntity(): void
        {
            $web = TestData::url();
            PluginConfiguration::apply(['federation_web' => $web]);
            $entity = TestData::entity();

            $text = NotificationBuilder::fromEntityQuery($this->entityQuery($entity))->getText();

            $this->assertStringContainsString(sprintf('<b>Entity:</b> %s', Html::link($entity->getAddress(), $web . 'entities/' . $entity->getUuid())), $text);
        }

        public function testEntityQueryRequestingOperator(): void
        {
            $entity = TestData::entity();
            $operator = TestData::operator();

            $text = NotificationBuilder::fromEntityQuery(new EntityQuery($entity->getUuid(), $entity, [], [], $operator))->getText();

            $this->assertStringContainsString(sprintf('<b>Requested by:</b> %s', Html::code($operator->getName())), $text);
        }

        public function testEntityQueryNeverIncludesTheAccessToken(): void
        {
            $entity = TestData::entity();
            $operator = TestData::operator();

            $text = NotificationBuilder::fromEntityQuery(new EntityQuery($entity->getUuid(), $entity, [], [], $operator))->getText();

            $this->assertStringNotContainsString($operator->getAccessToken(), $text);
        }

        public function testEntityQueryActiveBlacklists(): void
        {
            $entity = TestData::entity();
            $blacklist = new BlacklistRecord(['uuid' => TestData::uuid(), 'entity' => $entity->getUuid(), 'type' => IncidentType::SPAM]);

            $text = NotificationBuilder::fromEntityQuery(new EntityQuery($entity->getAddress(), $entity, [], [$blacklist]))->getText();

            $this->assertStringContainsString("<b>Active Blacklists (1):</b>\n• <code>Spam</code>, <b>permanent</b>", $text);
        }

        private function contentScan(ContentInput ...$evidence): ContentScan
        {
            return new ContentScan($evidence, TestData::address(), null, []);
        }

        private function entityQuery(EntityRecord $entity): EntityQuery
        {
            return new EntityQuery($entity->getAddress(), $entity, [], []);
        }
    }
