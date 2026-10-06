<?php

    namespace TelegramFederationPlugin\Helpers;

    use FederationLib\Enums\IncidentType;
    use FederationLib\Exceptions\RequestException;
    use FederationLib\FederationClient;
    use FederationLib\Objects\ContentInput;
    use FederationLib\Objects\EntityRecord;
    use LogLib2\Logger;
    use PHPUnit\Framework\TestCase;
    use Throwable;

    /**
     * A test against the plugin installed in a running FederationLib server (SERVER_ENDPOINT), the test environment
     * started with "make test-env" (see docker-compose.yml). The server's plugin sends REPORT_CLOSED and
     * ENTITY_BLACKLISTED audit log entries, REPORT_CREATED and BLACKLIST_LIFTED record changes, content scans and entity
     * queries to the Telegram test chat (TestChat), and nothing else.
     *
     * Without the test chat the operations are still tested, the plugin must never change, reject or fail them, and
     * the tests of the notifications are skipped. Every record a test creates is deleted afterwards.
     */
    abstract class FederationServerTestCase extends TestCase
    {
        use NotificationAssertions;

        protected FederationClient $client;
        /** @var string[] */
        private array $createdEntities = [];
        /** @var string[] */
        private array $createdEvidence = [];
        /** @var string[] */
        private array $createdReports = [];
        /** @var string[] */
        private array $createdBlacklists = [];
        /** @var string[] */
        private array $createdOperators = [];

        protected function setUp(): void
        {
            $this->client = new FederationClient(self::getServerEndpoint(), getenv('SERVER_ACCESS_TOKEN') ?: null);

            try
            {
                $this->client->getServerInformation();
            }
            catch(RequestException $e)
            {
                $this->fail(sprintf('FederationLib is not reachable at %s (set SERVER_ENDPOINT), start the test environment with "make test-env": %s', self::getServerEndpoint(), $e->getMessage()));
            }
        }

        protected function tearDown(): void
        {
            $cleanups = [
                [$this->createdBlacklists, $this->client->deleteBlacklistRecord(...)],
                [$this->createdReports, $this->client->deleteReport(...)],
                [$this->createdEvidence, $this->client->deleteEvidence(...)],
                [$this->createdEntities, $this->client->deleteEntity(...)],
                [$this->createdOperators, $this->client->deleteOperator(...)],
            ];

            // Records the test already deleted can't be deleted again
            foreach($cleanups as [$uuids, $delete])
            {
                foreach($uuids as $uuid)
                {
                    try { $delete($uuid); } catch(Throwable) {}
                }
            }

            Logger::unregisterHandlers();
        }

        protected function createEntity(): EntityRecord
        {
            $entityUuid = $this->client->pushEntity(TestData::ENTITY_HOST, TestData::localPart());
            $this->createdEntities[] = $entityUuid;
            return $this->client->getEntityRecord($entityUuid);
        }

        protected function submitReport(string $entityUuid): string
        {
            $submission = $this->client->submitReport($entityUuid, new ContentInput(uniqid('Reported content ')), IncidentType::SPAM, uniqid('Report message '));
            $this->createdReports[] = $submission->getReport()->getUuid();
            foreach($submission->getEvidence() as $evidence)
            {
                $this->createdEvidence[] = $evidence->getUuid();
            }

            return $submission->getReport()->getUuid();
        }

        protected function blacklistEntity(string $entityUuid, string $reportUuid): string
        {
            $blacklistUuid = $this->client->blacklistEntity($entityUuid, $reportUuid, IncidentType::SPAM, time() + 3600);
            $this->createdBlacklists[] = $blacklistUuid;
            return $blacklistUuid;
        }

        protected function submitEvidence(string $entityUuid, string $content): string
        {
            $evidenceUuid = $this->client->submitEvidence($entityUuid, $content, uniqid('Note '), uniqid('tag'), true);
            $this->createdEvidence[] = $evidenceUuid;
            return $evidenceUuid;
        }

        protected function createOperator(string $name): string
        {
            $operatorUuid = $this->client->createOperator($name)->getUuid();
            $this->createdOperators[] = $operatorUuid;
            return $operatorUuid;
        }

        protected function getNotificationHint(): string
        {
            return 'The test environment must be started with the same test chat as the tests, "make test-env" takes TELEGRAM_TEST_BOT_TOKEN and TELEGRAM_TEST_CHAT_ID from the environment or phpunit.xml, run it again if they were set after it was started. The plugin also drops a notification Telegram rate limits (HTTP 429), eg; while another test run uses the same test chat, see the log of the server.';
        }

        private static function getServerEndpoint(): string
        {
            return rtrim(getenv('SERVER_ENDPOINT') ?: 'http://172.17.0.1:7000', '/');
        }
    }
