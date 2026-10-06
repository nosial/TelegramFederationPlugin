<?php

    namespace TelegramFederationPlugin\Handlers;

    use FederationLib\Enums\EventType;
    use FederationLib\Interfaces\AuditLogEventHandlerInterface;
    use FederationLib\Objects\AuditLog;
    use TelegramFederationPlugin\Classes\Configuration;
    use TelegramFederationPlugin\Classes\NotificationBuilder;
    use TelegramFederationPlugin\TelegramFederationPlugin;

    class AuditLogHandler implements AuditLogEventHandlerInterface
    {
        /**
         * @inheritDoc
         */
        public static function handleAuditLog(AuditLog $auditLog): void
        {
            if(!Configuration::isAuditLogEnabled($auditLog->getType()))
            {
                return;
            }

            TelegramFederationPlugin::notify(EventType::AUDIT_LOG->value, fn() => NotificationBuilder::fromAuditLog($auditLog));
        }
    }
