<?php

    namespace TelegramFederationPlugin\Classes;

    use PHPUnit\Framework\TestCase;

    class RatelimitTest extends TestCase
    {
        private const string KEY = 'test:ratelimit';

        protected function setUp(): void
        {
            RatelimitHandler::clear(self::KEY);
        }

        protected function tearDown(): void
        {
            RatelimitHandler::clear(self::KEY);
        }

        public function testNoRatelimitByDefault(): void
        {
            $this->assertNull(RatelimitHandler::until(self::KEY));
        }

        public function testStartedRatelimitIsActive(): void
        {
            $this->assertTrue(RatelimitHandler::start(self::KEY, 60));

            $until = RatelimitHandler::until(self::KEY);
            $this->assertNotNull($until);
            $this->assertGreaterThan(time(), $until);
            $this->assertLessThanOrEqual(time() + 60, $until);
        }

        public function testActiveRatelimitIsNotStartedAgain(): void
        {
            $this->assertTrue(RatelimitHandler::start(self::KEY, 60));
            $this->assertFalse(RatelimitHandler::start(self::KEY, 60));
        }

        public function testClearedRatelimitEnds(): void
        {
            RatelimitHandler::start(self::KEY, 60);
            RatelimitHandler::clear(self::KEY);

            $this->assertNull(RatelimitHandler::until(self::KEY));
            $this->assertTrue(RatelimitHandler::start(self::KEY, 60));
        }

        public function testRatelimitExpires(): void
        {
            RatelimitHandler::start(self::KEY, 1);
            sleep(2);

            $this->assertNull(RatelimitHandler::until(self::KEY));
        }
    }
