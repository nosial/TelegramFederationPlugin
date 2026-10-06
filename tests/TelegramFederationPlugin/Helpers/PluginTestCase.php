<?php

    namespace TelegramFederationPlugin\Helpers;

    use LogLib2\Logger;
    use PHPUnit\Framework\TestCase;
    use TelegramFederationPlugin\TelegramFederationPlugin;

    abstract class PluginTestCase extends TestCase
    {
        /** @var array<string, string> The options of the target of configureUnreachableTelegram() */
        protected array $unreachableTelegram = [];

        protected function setUp(): void
        {
            PluginConfiguration::reset();
        }

        protected function tearDown(): void
        {
            PluginConfiguration::reset();
            Logger::unregisterHandlers();
        }

        protected function configureUnreachableTelegram(array $options=[]): void
        {
            $server = stream_socket_server('tcp://127.0.0.1:0');
            $endpoint = 'http://' . stream_socket_get_name($server, false);
            fclose($server);

            $this->unreachableTelegram = ['api_endpoint' => $endpoint, 'bot_token' => TestData::botToken(), 'chat_id' => TestData::chatId()];
            PluginConfiguration::apply(array_merge($this->unreachableTelegram, $options));
            $this->assertTrue(TelegramFederationPlugin::getClient()->isAvailable(), 'The unreachable Telegram target must be available until a notification is sent to it');
        }

        protected function assertNotificationSent(): void
        {
            $this->assertFalse(TelegramFederationPlugin::getClient()->isAvailable(), 'No notification was sent');
        }

        protected function assertNoNotificationSent(): void
        {
            $this->assertTrue(TelegramFederationPlugin::getClient()->isAvailable(), 'A notification was sent');
        }
    }
