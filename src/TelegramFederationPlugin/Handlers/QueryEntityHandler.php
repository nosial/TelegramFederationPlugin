<?php

    namespace TelegramFederationPlugin\Handlers;

    use FederationLib\Enums\EventType;
    use FederationLib\Interfaces\QueryEntityEventHandlerInterface;
    use FederationLib\Objects\Plugin\EntityQuery;
    use TelegramFederationPlugin\Classes\Configuration;
    use TelegramFederationPlugin\Classes\NotificationBuilder;
    use TelegramFederationPlugin\TelegramFederationPlugin;

    class QueryEntityHandler implements QueryEntityEventHandlerInterface
    {
        /**
         * @inheritDoc
         */
        public static function handleQueryEntity(EntityQuery $entityQuery): void
        {
            if(!Configuration::isEntityQueryEnabled())
            {
                return;
            }

            TelegramFederationPlugin::notify(EventType::QUERY_ENTITY->value, fn() => NotificationBuilder::fromEntityQuery($entityQuery));
        }
    }
