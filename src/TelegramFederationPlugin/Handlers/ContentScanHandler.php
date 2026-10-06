<?php

    namespace TelegramFederationPlugin\Handlers;

    use FederationLib\Enums\EventType;
    use FederationLib\Interfaces\ContentScanEventHandlerInterface;
    use FederationLib\Objects\Plugin\ContentScan;
    use TelegramFederationPlugin\Classes\Configuration;
    use TelegramFederationPlugin\Classes\NotificationBuilder;
    use TelegramFederationPlugin\TelegramFederationPlugin;

    class ContentScanHandler implements ContentScanEventHandlerInterface
    {
        /**
         * @inheritDoc
         */
        public static function handleContentScan(ContentScan $contentScan): void
        {
            if(!Configuration::isContentScanEnabled())
            {
                return;
            }

            TelegramFederationPlugin::notify(EventType::CONTENT_SCAN->value, fn() => NotificationBuilder::fromContentScan($contentScan));
        }
    }
