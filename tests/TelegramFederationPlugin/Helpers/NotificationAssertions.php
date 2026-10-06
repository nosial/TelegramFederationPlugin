<?php

    namespace TelegramFederationPlugin\Helpers;

    /**
     * Assertions on the messages sent to the Telegram test chat while an operation ran, see TestChat::capture()
     */
    trait NotificationAssertions
    {
        protected function assertNotified(?array $messages, string ...$texts): string
        {
            if($messages === null)
            {
                $this->markTestSkipped('The notification can\'t be checked, the Telegram test chat is not configured (TELEGRAM_TEST_BOT_TOKEN and TELEGRAM_TEST_CHAT_ID)');
            }

            foreach($messages as $message)
            {
                if(count(array_filter($texts, fn(string $text) => !str_contains($message, $text))) === 0)
                {
                    $this->addToAssertionCount(1);
                    return $message;
                }
            }

            $this->fail(sprintf('No notification containing "%s" was sent to the test chat (%d other message(s) were sent). %s',
                implode('", "', $texts), count($messages), $this->getNotificationHint()
            ));
        }

        protected function assertNotNotified(?array $messages, string ...$texts): void
        {
            if($messages !== null)
            {
                $this->addToAssertionCount(1);
            }

            foreach($messages ?? [] as $message)
            {
                foreach($texts as $text)
                {
                    $this->assertStringNotContainsString($text, $message);
                }
            }
        }

        protected function requireTestChat(): void
        {
            if(!TestChat::isConfigured())
            {
                $this->markTestSkipped('The Telegram test chat is not configured (TELEGRAM_TEST_BOT_TOKEN and TELEGRAM_TEST_CHAT_ID)');
            }
        }

        /**
         * Returns what may cause a notification to be missing
         */
        protected function getNotificationHint(): string
        {
            return 'Telegram may have rejected it, see the log of the plugin.';
        }
    }
