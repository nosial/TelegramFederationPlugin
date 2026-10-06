<?php

    /** @noinspection PhpUnhandledExceptionInspection */

    namespace TelegramFederationPlugin\FederationServer;

    use TelegramFederationPlugin\Helpers\FederationServerTestCase;
    use TelegramFederationPlugin\Helpers\TestChat;

    class RecordChangeTest extends FederationServerTestCase
    {
        public function testReportCreatedIsSent(): void
        {
            $entity = $this->createEntity();

            $reportUuid = null;
            $messages = TestChat::capture(function() use ($entity, &$reportUuid)
            {
                $reportUuid = $this->submitReport($entity->getUuid());
            });

            $this->assertNotified($messages, '#RECORD_CHANGE', '#REPORT_CREATED', $reportUuid);
        }

        public function testBlacklistLiftedIsSent(): void
        {
            $entity = $this->createEntity();
            $blacklistUuid = $this->blacklistEntity($entity->getUuid(), $this->submitReport($entity->getUuid()));

            $messages = TestChat::capture(fn() => $this->client->liftBlacklistRecord($blacklistUuid));

            $this->assertNotified($messages, '#RECORD_CHANGE', '#BLACKLIST_LIFTED', $blacklistUuid);
        }

        public function testOperatorIsShownByTheirName(): void
        {
            $entity = $this->createEntity();
            $blacklistUuid = $this->blacklistEntity($entity->getUuid(), $this->submitReport($entity->getUuid()));

            // The operator that lifted the blacklist record is retrieved from the server's database
            $messages = TestChat::capture(fn() => $this->client->liftBlacklistRecord($blacklistUuid));

            $this->assertNotified($messages, '#BLACKLIST_LIFTED', 'Lifted by ' . $this->client->getSelf()->getName());
        }

        public function testEntityCreatedIsNotSent(): void
        {
            $entity = null;
            $messages = TestChat::capture(function() use (&$entity)
            {
                $entity = $this->createEntity();
            }, 0);

            $this->assertNotNotified($messages, $entity->getUuid(), $entity->getAddress());
        }

        public function testEvidenceCreatedIsNotSent(): void
        {
            $entity = $this->createEntity();
            $content = uniqid('Evidence content ');

            $messages = TestChat::capture(fn() => $this->submitEvidence($entity->getUuid(), $content), 0);

            $this->assertNotNotified($messages, $content);
        }
    }
