<?php

    namespace TelegramFederationPlugin\Classes;

    class Logger
    {
        private static ?\LogLib2\Logger $logger = null;

        /**
         * Returns the plugin's logger instance
         *
         * @return \LogLib2\Logger The logger
         */
        public static function log(): \LogLib2\Logger
        {
            if(self::$logger === null)
            {
                self::$logger = new \LogLib2\Logger('net.nosial.telegram_federation_plugin');
            }

            return self::$logger;
        }
    }
