<?php

    namespace TelegramFederationPlugin\Classes;

    use FederationLib\Classes\Managers\EntitiesManager;
    use FederationLib\Classes\Managers\EvidenceManager;
    use FederationLib\Classes\Managers\OperatorManager;
    use FederationLib\Enums\ClassificationFlag;
    use FederationLib\Enums\EventType;
    use FederationLib\Enums\RecordType;
    use FederationLib\Objects\AuditLog;
    use FederationLib\Objects\BlacklistRecord;
    use FederationLib\Objects\EntityRecord;
    use FederationLib\Objects\EvidenceRecord;
    use FederationLib\Objects\FileAttachmentRecord;
    use FederationLib\Objects\OperatorRecord;
    use FederationLib\Objects\Plugin\ContentScan;
    use FederationLib\Objects\Plugin\EntityQuery;
    use FederationLib\Objects\Plugin\RecordChange;
    use FederationLib\Objects\ReportRecord;
    use FederationLib\Objects\ScannedContent\ResolvedEntity;
    use TelegramFederationPlugin\Objects\Notification;
    use Throwable;

    class NotificationBuilder
    {
        /**
         * The maximum number of entities, blacklist records or evidence items listed in a notification
         */
        private const int MAX_LISTED = 10;

        /**
         * The maximum number of contents listed in a content scan notification
         */
        private const int MAX_SCAN_CONTENTS = 3;

        /**
         * Builds the notification of an audit log entry
         *
         * @param AuditLog $auditLog The audit log entry
         * @return Notification The notification
         */
        public static function fromAuditLog(AuditLog $auditLog): Notification
        {
            $notification = new Notification(Html::humanize($auditLog->getType()->value),
                [EventType::AUDIT_LOG->value, $auditLog->getType()->value], self::getServerName()
            );

            $notification->addQuote('Message', $auditLog->getMessage(), Configuration::getMaxContentLength());
            $notification->addHtmlField('Entry', Html::link($auditLog->getUuid(), RecordLinks::getUrl(RecordType::AUDIT_LOG, $auditLog->getUuid())));
            $notification->addHtmlField('Operator', self::operatorHtml($auditLog->getOperatorUuid()));
            $notification->addHtmlField('Entity', self::entityHtml($auditLog->getEntityUuid()));
            $notification->addHtmlField('Evidence', self::uuidHtml(RecordType::EVIDENCE, $auditLog->getEvidenceUuid()));
            $notification->addHtmlField('Blacklist', self::uuidHtml(RecordType::BLACKLIST, $auditLog->getBlacklistUuid()));
            $notification->addHtmlField('Attachment', self::uuidHtml(RecordType::ATTACHMENT, $auditLog->getFileAttachmentUuid()));

            $notification->addRecordButton('View Audit Entry', RecordType::AUDIT_LOG, $auditLog->getUuid());
            $notification->addRecordButton('View Entity', RecordType::ENTITY, $auditLog->getEntityUuid());
            $notification->addRecordButton('View Evidence', RecordType::EVIDENCE, $auditLog->getEvidenceUuid());
            $notification->addRecordButton('View Blacklist', RecordType::BLACKLIST, $auditLog->getBlacklistUuid());
            $notification->addRecordButton('Download Attachment', RecordType::ATTACHMENT, $auditLog->getFileAttachmentUuid());
            $notification->addRecordButton('View Operator', RecordType::OPERATOR, $auditLog->getOperatorUuid());

            return $notification;
        }

        /**
         * Builds the notification of a change that was written to the database
         *
         * @param RecordChange $change The change
         * @return Notification The notification
         */
        public static function fromRecordChange(RecordChange $change): Notification
        {
            $notification = new Notification(Html::humanize($change->getType()->value),
                [EventType::RECORD_CHANGE->value, $change->getType()->value], self::getServerName()
            );

            $record = null;
            if(!$change->getType()->isDeletion())
            {
                try
                {
                    $record = $change->getRecord();
                }
                catch(Throwable $e)
                {
                    Logger::log()->warning(sprintf('Unable to retrieve the %s record %s: %s', $change->getRecordType()->value, $change->getUuid(), $e->getMessage()));
                }
            }

            match(true)
            {
                $record instanceof EntityRecord => self::describeEntity($notification, $record),
                $record instanceof EvidenceRecord => self::describeEvidence($notification, $record),
                $record instanceof ReportRecord => self::describeReport($notification, $record),
                $record instanceof BlacklistRecord => self::describeBlacklist($notification, $record),
                $record instanceof OperatorRecord => self::describeOperator($notification, $record),
                $record instanceof FileAttachmentRecord => self::describeAttachment($notification, $record),
                default => self::describeMissing($notification, $change)
            };

            return $notification;
        }

        /**
         * Builds the notification of a content scan request. The scanning rules and classifications are the ones
         * added by the plugins that handled the scan before this plugin, FederationLib's own results are only known
         * once the scan completes.
         *
         * @param ContentScan $contentScan The content scan
         * @return Notification The notification
         */
        public static function fromContentScan(ContentScan $contentScan): Notification
        {
            $notification = new Notification('Content Scanned', [EventType::CONTENT_SCAN->value], self::getServerName());
            $notification->addHtmlField('Requested by', self::requesterHtml($contentScan->getAuthenticatedOperator()));

            if($contentScan->getAuthorEntity() !== null)
            {
                $author = $contentScan->getAuthorEntity()->getEntity();
                $notification->addHtmlField('Author', self::resolvedEntityHtml($contentScan->getAuthorEntity()));
                $notification->addRecordButton('View Author', RecordType::ENTITY, $author->getUuid());
            }
            elseif($contentScan->getAuthorIdentifier() !== null)
            {
                $notification->addHtmlField('Author', Html::code($contentScan->getAuthorIdentifier()) . ' <i>(unknown)</i>');
            }

            $notification->addField('Evidence', (string)count($contentScan->getEvidence()));

            // Entities
            $resolvedEntities = $contentScan->getResolvedEntities();
            if(count($resolvedEntities) > 0)
            {
                $notification->addHtmlLine(sprintf('<b>Resolved Entities (%d):</b>', count($resolvedEntities)));
                foreach(array_slice($resolvedEntities, 0, self::MAX_LISTED) as $resolvedEntity)
                {
                    $notification->addHtmlLine('• ' . self::resolvedEntityHtml($resolvedEntity));
                    $notification->addRecordButton('View ' . Html::truncate($resolvedEntity->getEntity()->getAddress(), 32), RecordType::ENTITY, $resolvedEntity->getEntity()->getUuid());
                }

                if(count($resolvedEntities) > self::MAX_LISTED)
                {
                    $notification->addHtmlLine(sprintf('<i>... %d more</i>', count($resolvedEntities) - self::MAX_LISTED));
                }
            }

            // Results added by the plugins so far
            foreach(array_keys($contentScan->getEvidence()) as $index)
            {
                foreach($contentScan->getEvidenceClassifications($index) as $classification)
                {
                    $notification->addHtmlField(sprintf('Classification #%d', $index + 1), sprintf('%s (%s%%)',
                        self::classificationHtml($classification->getClassificationFlag()), number_format($classification->getConfidence() * 100, 1)
                    ));
                }
            }

            foreach($contentScan->getScanResults() as $rule => $points)
            {
                $notification->addHtmlField(Html::humanize((string)$rule), Html::code(sprintf('%+g', $points)));
            }

            // Content, confidential content is only included if configured
            if(Configuration::isContentIncluded())
            {
                $shown = 0;
                foreach($contentScan->getEvidence() as $index => $evidence)
                {
                    if($shown >= self::MAX_SCAN_CONTENTS)
                    {
                        break;
                    }

                    if($evidence->isConfidential() && !Configuration::isConfidentialIncluded())
                    {
                        $notification->addHtmlLine(sprintf('<i>The content of evidence #%d is confidential</i>', $index + 1));
                        continue;
                    }

                    $notification->addQuote(sprintf('Content #%d', $index + 1), $evidence->getTextContent(), intdiv(Configuration::getMaxContentLength(), 2));
                    $shown++;
                }
            }

            return $notification;
        }

        /**
         * Builds the notification of a query entity request, with the changes the plugins that handled the request
         * before this plugin made to the response
         *
         * @param EntityQuery $entityQuery The query entity request
         * @return Notification The notification
         */
        public static function fromEntityQuery(EntityQuery $entityQuery): Notification
        {
            $entity = $entityQuery->getEntityRecord();
            $notification = new Notification('Entity Queried', [EventType::QUERY_ENTITY->value], self::getServerName());

            $notification->addField('Identifier', $entityQuery->getIdentifier(), true);
            $notification->addHtmlField('Entity', Html::link($entity->getAddress(), RecordLinks::getUrl(RecordType::ENTITY, $entity->getUuid())));
            $notification->addField('Reputation', (string)$entity->getReputation());
            $notification->addHtmlField('Requested by', self::requesterHtml($entityQuery->getAuthenticatedOperator()));
            $notification->addField('Related Entities', (string)count($entityQuery->getRelatedEntities()));
            $notification->addRecordButton('View Entity', RecordType::ENTITY, $entity->getUuid());

            $result = $entityQuery->getResult();
            if($result->getSuggestedAction() !== null)
            {
                $notification->addField('Suggested Action', Html::humanize($result->getSuggestedAction()->value));
            }

            $blacklists = $entityQuery->getActiveBlacklists();
            if(count($blacklists) > 0)
            {
                $notification->addHtmlLine(sprintf('<b>Active Blacklists (%d):</b>', count($blacklists)));
                foreach(array_slice($blacklists, 0, self::MAX_LISTED) as $blacklist)
                {
                    $line = '• ' . Html::link(Html::humanize($blacklist->getType()->value), RecordLinks::getUrl(RecordType::BLACKLIST, $blacklist->getUuid()));
                    if($blacklist->getEntityUuid() !== $entity->getUuid())
                    {
                        // A blacklist record of a related entity
                        $line .= ' of ' . self::entityHtml($blacklist->getEntityUuid());
                    }

                    $notification->addHtmlLine($line . ', ' . self::expiresHtml($blacklist->getExpires()));
                    $notification->addRecordButton('View Blacklist', RecordType::BLACKLIST, $blacklist->getUuid());
                }
            }

            return $notification;
        }

        /**
         * Describes an entity record
         *
         * @param Notification $notification The notification
         * @param EntityRecord $entity The entity
         * @return void
         */
        private static function describeEntity(Notification $notification, EntityRecord $entity): void
        {
            $notification->addHtmlField('Entity', Html::link($entity->getAddress(), RecordLinks::getUrl(RecordType::ENTITY, $entity->getUuid())));
            $notification->addField('UUID', $entity->getUuid(), true);
            $notification->addField('Reputation', (string)$entity->getReputation());
            $notification->addField('Whitelisted', $entity->isWhitelisted() ? 'Yes' : 'No');

            if($entity->getRelationshipEntity() !== null)
            {
                $notification->addHtmlField('Relationship', sprintf('%s of %s',
                    Html::escape(Html::humanize($entity->getRelationshipType()?->value ?? 'RELATED')), self::entityHtml($entity->getRelationshipEntity())
                ));
            }

            $notification->addHtmlField('Created', Html::time($entity->getCreated()));
            $notification->addRecordButton('View Entity', RecordType::ENTITY, $entity->getUuid());
            $notification->addRecordButton('View Related Entity', RecordType::ENTITY, $entity->getRelationshipEntity());
        }

        /**
         * Describes an evidence record, its content is only included if configured
         *
         * @param Notification $notification The notification
         * @param EvidenceRecord $evidence The evidence record
         * @return void
         */
        private static function describeEvidence(Notification $notification, EvidenceRecord $evidence): void
        {
            $notification->addHtmlField('Evidence', Html::link($evidence->getUuid(), RecordLinks::getUrl(RecordType::EVIDENCE, $evidence->getUuid())));
            $notification->addHtmlField('Entity', self::entityHtml($evidence->getEntityUuid()));
            $notification->addHtmlField('Submitted by', self::operatorHtml($evidence->getOperatorUuid()));

            if($evidence->getClassificationFlag() !== null)
            {
                $notification->addHtmlField('Classification', self::classificationHtml($evidence->getClassificationFlag()));
            }

            $notification->addField('Confidential', $evidence->isConfidential() ? 'Yes' : 'No');
            $notification->addField('Tag', $evidence->getTag(), true);
            $notification->addHtmlField('Report', self::uuidHtml(RecordType::REPORT, $evidence->getReport()));
            $notification->addHtmlField('Created', Html::time($evidence->getCreated()));

            if(Configuration::isContentIncluded())
            {
                if($evidence->isConfidential() && !Configuration::isConfidentialIncluded())
                {
                    $notification->addHtmlLine('<i>The content of this evidence is confidential</i>');
                }
                else
                {
                    $notification->addQuote('Content', $evidence->getTextContent(), Configuration::getMaxContentLength());
                    $notification->addQuote('Note', $evidence->getNote(), Configuration::getMaxContentLength());
                }
            }

            $notification->addRecordButton('View Evidence', RecordType::EVIDENCE, $evidence->getUuid());
            $notification->addRecordButton('View Entity', RecordType::ENTITY, $evidence->getEntityUuid());
            $notification->addRecordButton('View Report', RecordType::REPORT, $evidence->getReport());
        }

        /**
         * Describes a report record with the entities of its evidence, its message is only included if configured
         *
         * @param Notification $notification The notification
         * @param ReportRecord $report The report
         * @return void
         */
        private static function describeReport(Notification $notification, ReportRecord $report): void
        {
            $notification->addHtmlField('Report', Html::link($report->getUuid(), RecordLinks::getUrl(RecordType::REPORT, $report->getUuid())));
            $notification->addField('Incident Type', Html::humanize($report->getIncidentType()->value));
            $notification->addField('Status', $report->isOpened() ? 'Open' : 'Closed');

            if($report->isAutomated())
            {
                $notification->addField('Automated', 'Yes');
            }

            $notification->addHtmlField('Submitted by', self::operatorHtml($report->getSubmittingOperator()));
            $notification->addHtmlField('Reporter', $report->getReportingEntity() === null ? '<i>Anonymous</i>' : self::entityHtml($report->getReportingEntity()));
            $notification->addHtmlField('Assigned to', $report->getAssignedOperator() === null ? '<i>Unassigned</i>' : self::operatorHtml($report->getAssignedOperator()));
            $notification->addHtmlField('Created', Html::time($report->getCreated()));

            // The entities the report is about are the entities of its evidence
            $reportedEntities = [];
            $evidenceCount = 0;
            try
            {
                foreach(EvidenceManager::getEvidenceByReport($report->getUuid(), 100, 1, true) as $evidence)
                {
                    $evidenceCount++;
                    $reportedEntities[$evidence->getEntityUuid()] = true;
                }
            }
            catch(Throwable $e)
            {
                Logger::log()->debug(sprintf('Unable to retrieve the evidence of report %s: %s', $report->getUuid(), $e->getMessage()));
            }

            if($evidenceCount > 0)
            {
                $notification->addField('Evidence', (string)$evidenceCount);
                $entities = array_map(fn(string $uuid) => self::entityHtml($uuid), array_slice(array_keys($reportedEntities), 0, self::MAX_LISTED));
                $notification->addHtmlField('Reported Entities', implode(', ', $entities) . (count($reportedEntities) > self::MAX_LISTED ? ', ...' : ''));
            }

            if(Configuration::isContentIncluded())
            {
                $notification->addQuote('Message', $report->getMessage(), Configuration::getMaxContentLength());
            }

            $notification->addRecordButton('View Report', RecordType::REPORT, $report->getUuid());
            foreach(array_keys($reportedEntities) as $entityUuid)
            {
                $notification->addRecordButton('View Reported Entity', RecordType::ENTITY, $entityUuid);
            }

            $notification->addRecordButton('View Assigned Operator', RecordType::OPERATOR, $report->getAssignedOperator());
        }

        /**
         * Describes a blacklist record
         *
         * @param Notification $notification The notification
         * @param BlacklistRecord $blacklist The blacklist record
         * @return void
         */
        private static function describeBlacklist(Notification $notification, BlacklistRecord $blacklist): void
        {
            $notification->addHtmlField('Blacklist', Html::link($blacklist->getUuid(), RecordLinks::getUrl(RecordType::BLACKLIST, $blacklist->getUuid())));
            $notification->addHtmlField('Entity', self::entityHtml($blacklist->getEntityUuid()));
            $notification->addField('Incident Type', Html::humanize($blacklist->getType()->value));
            $notification->addHtmlField('Blacklisted by', self::operatorHtml($blacklist->getOperatorUuid()));
            $notification->addHtmlField('Report', self::uuidHtml(RecordType::REPORT, $blacklist->getReportUuid()));
            $notification->addHtmlField('Expires', self::expiresHtml($blacklist->getExpires()));

            if($blacklist->isLifted())
            {
                $notification->addHtmlField('Status', $blacklist->getLiftedBy() === null ? 'Lifted' : 'Lifted by ' . self::operatorHtml($blacklist->getLiftedBy()));
            }
            else
            {
                $notification->addField('Status', 'Active');
            }

            $notification->addHtmlField('Created', Html::time($blacklist->getCreated()));

            $notification->addRecordButton('View Blacklist', RecordType::BLACKLIST, $blacklist->getUuid());
            $notification->addRecordButton('View Entity', RecordType::ENTITY, $blacklist->getEntityUuid());
            $notification->addRecordButton('View Report', RecordType::REPORT, $blacklist->getReportUuid());
        }

        /**
         * Describes an operator record, its access token is never included
         *
         * @param Notification $notification The notification
         * @param OperatorRecord $operator The operator
         * @return void
         */
        private static function describeOperator(Notification $notification, OperatorRecord $operator): void
        {
            $permissions = array_keys(array_filter([
                'Management' => $operator->hasManagementPermissions(),
                'Operator' => $operator->hasOperatorPermissions(),
                'Client' => $operator->hasClientPermissions(),
            ]));

            $notification->addHtmlField('Operator', Html::link($operator->getName(), RecordLinks::getUrl(RecordType::OPERATOR, $operator->getUuid())));
            $notification->addField('UUID', $operator->getUuid(), true);
            $notification->addField('Status', $operator->isDisabled() ? 'Disabled' : 'Enabled');
            $notification->addField('Permissions', count($permissions) > 0 ? implode(', ', $permissions) : 'None');
            $notification->addField('Auto Assigned', $operator->isAutoAssigned() ? 'Yes' : 'No');
            $notification->addHtmlField('Created', Html::time($operator->getCreated()));

            $notification->addRecordButton('View Operator', RecordType::OPERATOR, $operator->getUuid());
        }

        /**
         * Describes a file attachment record, the file name of an attachment of confidential evidence is only included
         * if confidential content is included
         *
         * @param Notification $notification The notification
         * @param FileAttachmentRecord $attachment The file attachment
         * @return void
         */
        private static function describeAttachment(Notification $notification, FileAttachmentRecord $attachment): void
        {
            $evidence = self::getEvidence($attachment->getEvidenceUuid());
            $confidential = $evidence === null || $evidence->isConfidential();

            $notification->addHtmlField('Attachment', Html::link($attachment->getUuid(), RecordLinks::getHostUrl(RecordType::ATTACHMENT, $attachment->getUuid())));
            if(!$confidential || Configuration::isConfidentialIncluded())
            {
                $notification->addField('File Name', $attachment->getFileName(), true);
            }

            $notification->addField('Type', $attachment->getFileMime(), true);
            $notification->addField('Size', Html::fileSize($attachment->getFileSize()));
            $notification->addHtmlField('Evidence', self::uuidHtml(RecordType::EVIDENCE, $attachment->getEvidenceUuid()));

            if($evidence !== null)
            {
                $notification->addHtmlField('Entity', self::entityHtml($evidence->getEntityUuid()));
            }

            $notification->addHtmlField('Created', Html::time($attachment->getCreated()));

            $notification->addRecordButton('Download Attachment', RecordType::ATTACHMENT, $attachment->getUuid());
            $notification->addRecordButton('View Evidence', RecordType::EVIDENCE, $attachment->getEvidenceUuid());
            $notification->addRecordButton('View Entity', RecordType::ENTITY, $evidence?->getEntityUuid());
        }

        /**
         * Describes a record that was deleted or could not be retrieved
         *
         * @param Notification $notification The notification
         * @param RecordChange $change The change
         * @return void
         */
        private static function describeMissing(Notification $notification, RecordChange $change): void
        {
            $notification->addField(Html::humanize($change->getRecordType()->value), $change->getUuid(), true);
            $notification->addHtmlLine($change->getType()->isDeletion() ? '<i>The record was deleted</i>' : '<i>The record no longer exists</i>');
        }

        /**
         * Returns an entity as a link showing its address, or its UUID if it can't be retrieved
         *
         * @param string|null $uuid The UUID of the entity
         * @return string|null The HTML, null if the UUID is null
         */
        private static function entityHtml(?string $uuid): ?string
        {
            if($uuid === null)
            {
                return null;
            }

            try
            {
                $entity = EntitiesManager::getEntityByUuid($uuid);
            }
            catch(Throwable $e)
            {
                Logger::log()->debug(sprintf('Unable to retrieve the entity %s: %s', $uuid, $e->getMessage()));
                $entity = null;
            }

            return Html::link($entity?->getAddress() ?? $uuid, RecordLinks::getUrl(RecordType::ENTITY, $uuid));
        }

        /**
         * Returns a resolved entity of a content scan as a link showing its address, with its active blacklists
         *
         * @param ResolvedEntity $resolvedEntity The resolved entity
         * @return string The HTML
         */
        private static function resolvedEntityHtml(ResolvedEntity $resolvedEntity): string
        {
            $entity = $resolvedEntity->getEntity();
            $html = Html::link($entity->getAddress(), RecordLinks::getUrl(RecordType::ENTITY, $entity->getUuid()));

            $blacklists = $resolvedEntity->getActiveBlacklists();
            if(count($blacklists) > 0)
            {
                $types = array_unique(array_map(fn(BlacklistRecord $blacklist) => Html::humanize($blacklist->getType()->value), $blacklists));
                $html .= sprintf(' ⛔ <i>blacklisted: %s</i>', Html::escape(implode(', ', $types)));
            }
            elseif($entity->isWhitelisted())
            {
                $html .= ' ✅ <i>whitelisted</i>';
            }

            return $html;
        }

        /**
         * Returns an operator as a link showing their name, or their UUID if they can't be retrieved
         *
         * @param string|null $uuid The UUID of the operator
         * @return string|null The HTML, null if the UUID is null
         */
        private static function operatorHtml(?string $uuid): ?string
        {
            if($uuid === null)
            {
                return null;
            }

            try
            {
                $operator = OperatorManager::getOperator($uuid);
            }
            catch(Throwable $e)
            {
                Logger::log()->debug(sprintf('Unable to retrieve the operator %s: %s', $uuid, $e->getMessage()));
                $operator = null;
            }

            return Html::link($operator?->getName() ?? $uuid, RecordLinks::getUrl(RecordType::OPERATOR, $uuid));
        }

        /**
         * Returns the operator that made a request, or anonymous
         *
         * @param OperatorRecord|null $operator The authenticated operator
         * @return string The HTML
         */
        private static function requesterHtml(?OperatorRecord $operator): string
        {
            if($operator === null)
            {
                return '<i>Anonymous</i>';
            }

            return Html::link($operator->getName(), RecordLinks::getUrl(RecordType::OPERATOR, $operator->getUuid()));
        }

        /**
         * Returns a record as a link showing its UUID
         *
         * @param RecordType $type The type of the record
         * @param string|null $uuid The UUID of the record
         * @return string|null The HTML, null if the UUID is null
         */
        private static function uuidHtml(RecordType $type, ?string $uuid): ?string
        {
            return $uuid === null ? null : Html::link($uuid, RecordLinks::getUrl($type, $uuid));
        }

        /**
         * Returns the expiration of a blacklist record
         *
         * @param int|null $expires The Unix timestamp the blacklist record expires at, null if it's permanent
         * @return string The HTML
         */
        private static function expiresHtml(?int $expires): string
        {
            return $expires === null ? '<b>permanent</b>' : 'expires ' . Html::time($expires);
        }

        /**
         * Returns a classification with its color
         *
         * @param ClassificationFlag $flag The classification
         * @return string The HTML
         */
        private static function classificationHtml(ClassificationFlag $flag): string
        {
            return match($flag)
            {
                ClassificationFlag::MALICIOUS => '🔴',
                ClassificationFlag::SUSPICIOUS => '🟡',
                ClassificationFlag::NORMAL => '🟢',
            } . ' ' . Html::escape(Html::humanize($flag->value));
        }

        /**
         * Returns an evidence record
         *
         * @param string $uuid The UUID of the evidence record
         * @return EvidenceRecord|null The evidence record, null if it can't be retrieved
         */
        private static function getEvidence(string $uuid): ?EvidenceRecord
        {
            try
            {
                return EvidenceManager::getEvidence($uuid);
            }
            catch(Throwable $e)
            {
                Logger::log()->debug(sprintf('Unable to retrieve the evidence %s: %s', $uuid, $e->getMessage()));
                return null;
            }
        }

        /**
         * Returns the name of the FederationLib server
         *
         * @return string|null The name, null if it can't be retrieved
         */
        private static function getServerName(): ?string
        {
            try
            {
                return \FederationLib\Classes\Configuration::getServerConfiguration()->getName();
            }
            catch(Throwable)
            {
                return null;
            }
        }
    }
