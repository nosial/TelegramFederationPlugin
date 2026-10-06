<?php

    /** @noinspection PhpUnhandledExceptionInspection */

    namespace TelegramFederationPlugin\FederationServer;

    use FederationLib\Enums\ClassificationFlag;
    use TelegramFederationPlugin\Helpers\FederationServerTestCase;
    use TelegramFederationPlugin\Helpers\TestChat;

    class AuditLogTest extends FederationServerTestCase
    {
        public function testReportClosedIsSent(): void
        {
            $reportUuid = $this->submitReport($this->createEntity()->getUuid());

            // Only the operator assigned to the report can close it
            $this->client->assignOperatorToReport($reportUuid, $this->client->getSelf()->getUuid());

            $messages = TestChat::capture(fn() => $this->client->closeReport($reportUuid, ClassificationFlag::MALICIOUS));

            $this->assertNotified($messages, '#AUDIT_LOG', '#REPORT_CLOSED', $reportUuid);
        }

        public function testEntityBlacklistedIsSent(): void
        {
            $entity = $this->createEntity();
            $reportUuid = $this->submitReport($entity->getUuid());

            $blacklistUuid = null;
            $messages = TestChat::capture(function() use ($entity, $reportUuid, &$blacklistUuid)
            {
                $blacklistUuid = $this->blacklistEntity($entity->getUuid(), $reportUuid);
            });

            $this->assertNotified($messages, '#AUDIT_LOG', '#ENTITY_BLACKLISTED', $blacklistUuid);
        }

        public function testEntityIsShownByItsAddress(): void
        {
            $entity = $this->createEntity();
            $reportUuid = $this->submitReport($entity->getUuid());

            // The entity is retrieved from the server's database
            $messages = TestChat::capture(fn() => $this->blacklistEntity($entity->getUuid(), $reportUuid));

            $this->assertNotified($messages, '#ENTITY_BLACKLISTED', 'Entity: ' . $entity->getAddress());
        }

        public function testOperatorCreatedIsNotSent(): void
        {
            $name = substr(uniqid('telegramtest'), 0, 32);

            $messages = TestChat::capture(fn() => $this->createOperator($name), 0);

            $this->assertNotNotified($messages, $name);
        }
    }
