<?php

    namespace TelegramFederationPlugin;

    use RuntimeException;
    use TelegramFederationPlugin\Helpers\PluginTestCase;
    use TelegramFederationPlugin\Objects\Notification;

    class TelegramFederationPluginTest extends PluginTestCase
    {
        public function testNotificationIsSent(): void
        {
            $this->configureUnreachableTelegram();

            TelegramFederationPlugin::notify('TEST', fn() => new Notification(uniqid('Notification '), []));

            $this->assertNotificationSent();
        }

        public function testNothingIsBuiltWhileTelegramIsNotConfigured(): void
        {
            $built = false;

            TelegramFederationPlugin::notify('TEST', function() use (&$built)
            {
                $built = true;
                return new Notification(uniqid('Notification '), []);
            });

            // Building may retrieve records from the database, it's skipped when nothing can be sent
            $this->assertFalse($built);
        }

        public function testNothingIsSentWhenTheBuilderReturnsNull(): void
        {
            $this->configureUnreachableTelegram();

            TelegramFederationPlugin::notify('TEST', fn() => null);

            $this->assertNoNotificationSent();
        }

        public function testFailingBuilderIsNeverThrown(): void
        {
            $this->configureUnreachableTelegram();

            TelegramFederationPlugin::notify('TEST', fn() => throw new RuntimeException(uniqid('Building failed ')));

            $this->assertNoNotificationSent();
        }
    }
