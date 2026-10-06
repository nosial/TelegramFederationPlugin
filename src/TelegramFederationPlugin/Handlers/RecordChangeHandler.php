<?php

    namespace TelegramFederationPlugin\Handlers;

    use FederationLib\Enums\EventType;
    use FederationLib\Interfaces\RecordChangeEventHandlerInterface;
    use FederationLib\Objects\Plugin\RecordChange;
    use TelegramFederationPlugin\Classes\Configuration;
    use TelegramFederationPlugin\Classes\NotificationBuilder;
    use TelegramFederationPlugin\TelegramFederationPlugin;

    class RecordChangeHandler implements RecordChangeEventHandlerInterface
    {
        /**
         * @inheritDoc
         */
        public static function handleRecordChange(RecordChange $change): void
        {
            if(!Configuration::isRecordChangeEnabled($change->getType()))
            {
                return;
            }

            TelegramFederationPlugin::notify(EventType::RECORD_CHANGE->value, fn() => NotificationBuilder::fromRecordChange($change));
        }
    }
