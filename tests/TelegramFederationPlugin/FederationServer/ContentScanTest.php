<?php

    /** @noinspection PhpUnhandledExceptionInspection */

    namespace TelegramFederationPlugin\FederationServer;

    use FederationLib\Objects\ContentInput;
    use TelegramFederationPlugin\Helpers\FederationServerTestCase;
    use TelegramFederationPlugin\Helpers\TestChat;

    class ContentScanTest extends FederationServerTestCase
    {
        public function testContentScanIsSent(): void
        {
            $content = uniqid('Scanned content ');

            $messages = TestChat::capture(fn() => $this->client->scanContent([new ContentInput($content)]));

            $this->assertNotified($messages, '#CONTENT_SCAN', $content);
        }

        public function testKnownAuthorIsShownByTheirAddress(): void
        {
            $author = $this->createEntity();
            $content = uniqid('Scanned content ');

            $messages = TestChat::capture(fn() => $this->client->scanContent([new ContentInput($content)], $author->getAddress()));

            $this->assertNotified($messages, $content, 'Author: ' . $author->getAddress());
        }

        public function testConfidentialContentIsNotSent(): void
        {
            $content = uniqid('Confidential content ');

            $messages = TestChat::capture(fn() => $this->client->scanContent([new ContentInput($content, confidential: true)]));

            $this->assertNotNotified($messages, $content);
        }
    }
