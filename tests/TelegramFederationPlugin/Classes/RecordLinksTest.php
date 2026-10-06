<?php

    namespace TelegramFederationPlugin\Classes;

    use FederationLib\Enums\RecordType;
    use PHPUnit\Framework\Attributes\DataProvider;
    use TelegramFederationPlugin\Helpers\PluginConfiguration;
    use TelegramFederationPlugin\Helpers\PluginTestCase;
    use TelegramFederationPlugin\Helpers\TestData;

    class RecordLinksTest extends PluginTestCase
    {
        public static function federationWebPaths(): array
        {
            return [
                RecordType::ENTITY->value => [RecordType::ENTITY, 'entities'],
                RecordType::EVIDENCE->value => [RecordType::EVIDENCE, 'evidence'],
                RecordType::REPORT->value => [RecordType::REPORT, 'reports'],
                RecordType::BLACKLIST->value => [RecordType::BLACKLIST, 'blacklist'],
                RecordType::OPERATOR->value => [RecordType::OPERATOR, 'operators'],
                RecordType::AUDIT_LOG->value => [RecordType::AUDIT_LOG, 'audit-log'],
            ];
        }

        public static function federationHostPaths(): array
        {
            return [
                RecordType::ENTITY->value => [RecordType::ENTITY, 'entities'],
                RecordType::EVIDENCE->value => [RecordType::EVIDENCE, 'evidence'],
                RecordType::REPORT->value => [RecordType::REPORT, 'reports'],
                RecordType::BLACKLIST->value => [RecordType::BLACKLIST, 'blacklist'],
                RecordType::OPERATOR->value => [RecordType::OPERATOR, 'operators'],
                RecordType::AUDIT_LOG->value => [RecordType::AUDIT_LOG, 'audit'],
                RecordType::ATTACHMENT->value => [RecordType::ATTACHMENT, 'attachments'],
            ];
        }

        public static function recordTypes(): array
        {
            $data = [];
            foreach(RecordType::cases() as $type)
            {
                $data[$type->value] = [$type];
            }

            return $data;
        }

        #[DataProvider('federationWebPaths')]
        public function testFederationWebIsPreferred(RecordType $type, string $path): void
        {
            $web = TestData::url();
            $uuid = TestData::uuid();
            PluginConfiguration::apply(['federation_web' => $web, 'federation_host' => TestData::url()]);

            $this->assertSame($web . $path . '/' . $uuid, RecordLinks::getUrl($type, $uuid));
        }

        #[DataProvider('federationHostPaths')]
        public function testFederationHostIsUsedWithoutFederationWeb(RecordType $type, string $path): void
        {
            $host = TestData::url();
            $uuid = TestData::uuid();
            PluginConfiguration::apply(['federation_host' => $host]);

            $this->assertSame($host . $path . '/' . $uuid, RecordLinks::getUrl($type, $uuid));
        }

        public function testAttachmentsAreDownloadedFromTheFederationHost(): void
        {
            $host = TestData::url();
            $uuid = TestData::uuid();
            PluginConfiguration::apply(['federation_web' => TestData::url(), 'federation_host' => $host]);

            $this->assertSame($host . 'attachments/' . $uuid, RecordLinks::getUrl(RecordType::ATTACHMENT, $uuid));
        }

        public function testFederationWebHasNoAttachments(): void
        {
            PluginConfiguration::apply(['federation_web' => TestData::url()]);

            $this->assertNull(RecordLinks::getUrl(RecordType::ATTACHMENT, TestData::uuid()));
        }

        #[DataProvider('recordTypes')]
        public function testNoLinkWithoutAUrl(RecordType $type): void
        {
            $this->assertNull(RecordLinks::getUrl($type, TestData::uuid()));
        }

        public function testNoLinkWithoutAUuid(): void
        {
            PluginConfiguration::apply(['federation_web' => TestData::url()]);

            $this->assertNull(RecordLinks::getUrl(RecordType::REPORT, null));
        }

        public function testNoLinkWithAnEmptyUuid(): void
        {
            PluginConfiguration::apply(['federation_web' => TestData::url()]);

            $this->assertNull(RecordLinks::getUrl(RecordType::REPORT, ''));
        }

        public function testUuidIsEncoded(): void
        {
            $web = TestData::url();
            PluginConfiguration::apply(['federation_web' => $web]);

            $this->assertSame($web . 'entities/a%2Fb%3Fc', RecordLinks::getUrl(RecordType::ENTITY, 'a/b?c'));
        }
    }
