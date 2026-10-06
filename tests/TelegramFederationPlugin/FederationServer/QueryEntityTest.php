<?php

    /** @noinspection PhpUnhandledExceptionInspection */

    namespace TelegramFederationPlugin\FederationServer;

    use TelegramFederationPlugin\Helpers\FederationServerTestCase;
    use TelegramFederationPlugin\Helpers\TestChat;

    class QueryEntityTest extends FederationServerTestCase
    {
        public function testEntityQueryIsSent(): void
        {
            $entity = $this->createEntity();

            $messages = TestChat::capture(fn() => $this->client->queryEntity($entity->getAddress()));

            $this->assertNotified($messages, '#QUERY_ENTITY', $entity->getAddress());
        }
    }
