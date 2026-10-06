<?php

    namespace TelegramFederationPlugin\Classes;

    use FederationLib\Enums\RecordType;
    use TelegramFederationPlugin\Helpers\NotificationAssertions;
    use TelegramFederationPlugin\Helpers\PluginConfiguration;
    use TelegramFederationPlugin\Helpers\PluginTestCase;
    use TelegramFederationPlugin\Helpers\TestChat;
    use TelegramFederationPlugin\Helpers\TestData;
    use TelegramFederationPlugin\Objects\Notification;

    class TelegramClientTest extends PluginTestCase
    {
        use NotificationAssertions;

        public function testUnavailableByDefault(): void
        {
            $this->assertFalse(new TelegramClient()->isAvailable());
        }

        public function testUnavailableWithoutChat(): void
        {
            PluginConfiguration::apply(['bot_token' => TestData::botToken()]);

            $this->assertFalse(new TelegramClient()->isAvailable());
        }

        public function testUnavailableWithoutBotToken(): void
        {
            PluginConfiguration::apply(['chat_id' => TestData::chatId()]);

            $this->assertFalse(new TelegramClient()->isAvailable());
        }

        public function testAvailableWithBotTokenAndChat(): void
        {
            PluginConfiguration::apply(['bot_token' => TestData::botToken(), 'chat_id' => TestData::chatId()]);

            $this->assertTrue(new TelegramClient()->isAvailable());
        }

        public function testUnavailableWhileDisabled(): void
        {
            PluginConfiguration::apply(['enabled' => 'false', 'bot_token' => TestData::botToken(), 'chat_id' => TestData::chatId()]);

            $this->assertFalse(new TelegramClient()->isAvailable());
        }

        public function testUnavailableWithAnInvalidApiEndpoint(): void
        {
            PluginConfiguration::apply(['api_endpoint' => TestData::host(), 'bot_token' => TestData::botToken(), 'chat_id' => TestData::chatId()]);

            $this->assertFalse(new TelegramClient()->isAvailable());
        }

        public function testNothingIsSentWhileUnavailable(): void
        {
            $this->assertFalse(new TelegramClient()->send($this->notification()));
        }

        public function testUnreachableTargetIsDisabled(): void
        {
            $this->configureUnreachableTelegram();
            $client = new TelegramClient();

            $this->assertFalse($client->send($this->notification()));
            $this->assertFalse($client->isAvailable());
        }

        public function testOtherTargetsStayAvailable(): void
        {
            $this->configureUnreachableTelegram();
            $client = new TelegramClient();
            $client->send($this->notification());

            PluginConfiguration::apply(array_merge($this->unreachableTelegram, ['chat_id' => TestData::chatId()]));

            $this->assertTrue($client->isAvailable());
        }

        public function testRejectedBotTokenDisablesTheTarget(): void
        {
            // Telegram rejects a token that isn't one of a bot (401), which doesn't resolve itself
            PluginConfiguration::apply(['bot_token' => TestData::botToken(), 'chat_id' => TestData::chatId()]);
            $client = new TelegramClient();

            $this->assertFalse($client->send($this->notification()));
            $this->assertFalse($client->isAvailable());
        }

        public function testUnknownChatDisablesTheTarget(): void
        {
            $this->requireTestChat();

            // A chat the test bot isn't a member of, Telegram rejects it (400) and the plain text retry as well
            PluginConfiguration::apply(TestChat::options(['chat_id' => TestData::chatId(), 'topic_id' => '']));
            $client = new TelegramClient();

            $this->assertFalse($client->send($this->notification()));
            $this->assertFalse($client->isAvailable());
        }

        public function testSendsFormattedNotification(): void
        {
            $this->requireTestChat();
            PluginConfiguration::apply(TestChat::options());

            // Every kind of formatting the notifications use, Telegram rejects HTML it can't parse
            $url = TestData::url();
            $notification = $this->notification();
            $notification->addField('Escaped', '<b>not bold</b> & "quoted"');
            $notification->addField('Code', TestData::uuid(), true);
            $notification->addHtmlField('Link', Html::link($url, $url . '?a=1&b=2'));
            $notification->addHtmlField('Time', Html::time(TestData::timestamp()));
            $notification->addHtmlLine('<i>Italic</i> ⛔ 🔴 🟡 🟢 ✅');
            $notification->addQuote('Quote', uniqid('Quoted <text> '), 1000);
            $notification->addQuote('Expandable Quote', str_repeat('Long quoted text. ', 30), 1000);

            TestChat::reserve(1);
            $this->assertTrue(new TelegramClient()->send($notification));
        }

        public function testSendsButtons(): void
        {
            $this->requireTestChat();
            PluginConfiguration::apply(TestChat::options(['federation_web' => TestData::url(), 'federation_host' => TestData::url()]));

            $notification = $this->notification();
            foreach(RecordType::cases() as $type)
            {
                $notification->addRecordButton('View ' . Html::humanize($type->value), $type, TestData::uuid());
            }

            TestChat::reserve(1);
            $this->assertTrue(new TelegramClient()->send($notification));
        }

        public function testSendsTooLongNotificationAsPlainText(): void
        {
            $this->requireTestChat();
            PluginConfiguration::apply(TestChat::options());

            // The HTML can't be cut without breaking its tags
            $notification = $this->notification();
            while(Html::visibleLength($notification->getText()) <= 4096)
            {
                $notification->addField(uniqid('Line '), str_repeat('<long> & text ', 5));
            }

            TestChat::reserve(1);
            $this->assertTrue(new TelegramClient()->send($notification));
        }

        public function testRejectedButtonsAreRetriedWithoutThem(): void
        {
            $this->requireTestChat();

            // Telegram only accepts public URLs in buttons
            PluginConfiguration::apply(TestChat::options(['federation_web' => sprintf('http://127.0.0.1:%d/', random_int(1024, 65535))]));
            $notification = $this->notification();
            $notification->addRecordButton('View Report', RecordType::REPORT, TestData::uuid());

            // The rejected notification is sent again as plain text
            TestChat::reserve(2);
            $this->assertTrue(new TelegramClient()->send($notification));
        }

        private function notification(): Notification
        {
            return new Notification(uniqid('Notification '), ['TEST', 'TELEGRAM_FEDERATION_PLUGIN'], 'TelegramFederationPlugin Tests');
        }
    }
