<?php

    namespace TelegramFederationPlugin;

    use Closure;
    use Throwable;
    use TelegramFederationPlugin\Classes\Logger;
    use TelegramFederationPlugin\Classes\TelegramClient;
    use TelegramFederationPlugin\Objects\Notification;

    class TelegramFederationPlugin
    {
        private static ?TelegramClient $client = null;

        /**
         * Returns the Telegram client, created from the plugin's configuration on first use
         *
         * @return TelegramClient The client
         */
        public static function getClient(): TelegramClient
        {
            if(self::$client === null)
            {
                self::$client = new TelegramClient();
            }

            return self::$client;
        }

        /**
         * Builds and sends a notification. The notification is only built if it can be sent, as building it may
         * retrieve the related records from the database. Any failure is logged, the event handlers must never
         * affect the operation the notification is about.
         *
         * @param string $event The event the notification is about, used for logging
         * @param Closure(): ?Notification $builder Builds the notification, null to send nothing
         * @return void
         */
        public static function notify(string $event, Closure $builder): void
        {
            try
            {
                $client = self::getClient();
                if(!$client->isAvailable())
                {
                    return;
                }

                $notification = $builder();
                if($notification !== null)
                {
                    $client->send($notification);
                }
            }
            catch(Throwable $e)
            {
                Logger::log()->error(sprintf('Unable to send the %s notification: %s', $event, $e->getMessage()), $e);
            }
        }
    }
