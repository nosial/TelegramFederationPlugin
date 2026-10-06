<?php

    namespace TelegramFederationPlugin\Classes;

    use PHPUnit\Framework\Attributes\DataProvider;
    use PHPUnit\Framework\TestCase;
    use TelegramFederationPlugin\Helpers\TestData;

    class HtmlTest extends TestCase
    {
        public static function hashtags(): array
        {
            return [
                'words' => ['Federation Server', '#FEDERATION_SERVER'],
                'punctuation and whitespace' => ['  server-1!  ', '#SERVER_1'],
                'letters of any script' => ['сервер', '#СЕРВЕР'],
            ];
        }

        public static function noHashtags(): array
        {
            return [
                'digits only' => [(string)random_int(0, PHP_INT_MAX)],
                'punctuation only' => ['---'],
                'empty' => [''],
            ];
        }

        public static function fileSizes(): array
        {
            return [
                'zero' => [0, '0 B'],
                'bytes' => [1023, '1023 B'],
                'kilobytes' => [1536, '1.5 KB'],
                'megabytes' => [5 * 1024 * 1024, '5.0 MB'],
            ];
        }

        public function testEscape(): void
        {
            $this->assertSame('&lt;b&gt;&quot;a&quot; &amp; &apos;b&apos;&lt;/b&gt;', Html::escape('<b>"a" & \'b\'</b>'));
        }

        public function testCode(): void
        {
            $this->assertSame('<code>&lt;a&gt;</code>', Html::code('<a>'));
        }

        public function testLink(): void
        {
            $url = TestData::url();

            $this->assertSame(sprintf('<a href="%s?a=1&amp;b=2">a &lt;b&gt;</a>', $url), Html::link('a <b>', $url . '?a=1&b=2'));
        }

        public function testLinkWithoutUrlIsCode(): void
        {
            $this->assertSame('<code>a</code>', Html::link('a', null));
        }

        public function testTime(): void
        {
            $timestamp = TestData::timestamp();

            $this->assertSame(gmdate('Y-m-d H:i:s', $timestamp) . ' UTC', Html::time($timestamp));
        }

        public function testHumanize(): void
        {
            $this->assertSame('Report Operator Assigned', Html::humanize('REPORT_OPERATOR_ASSIGNED'));
        }

        #[DataProvider('hashtags')]
        public function testHashtag(string $input, string $hashtag): void
        {
            $this->assertSame($hashtag, Html::hashtag($input));
        }

        /**
         * Telegram only recognizes hashtags with letters
         */
        #[DataProvider('noHashtags')]
        public function testNoHashtag(string $input): void
        {
            $this->assertNull(Html::hashtag($input));
        }

        public function testShortTextIsNotTruncated(): void
        {
            $this->assertSame('abc', Html::truncate('abc', 3));
        }

        public function testLongTextIsTruncated(): void
        {
            $this->assertSame('a...', Html::truncate('abcde', 4));
        }

        public function testTruncateCountsCharacters(): void
        {
            $this->assertSame('ää...', Html::truncate('äääääääää', 5));
        }

        #[DataProvider('fileSizes')]
        public function testFileSize(int $bytes, string $size): void
        {
            $this->assertSame($size, Html::fileSize($bytes));
        }

        public function testToPlainText(): void
        {
            $this->assertSame('a & b ä', Html::toPlainText(sprintf('<b>a &amp; b</b> <a href="%s">ä</a>', TestData::url())));
        }

        public function testVisibleLength(): void
        {
            $this->assertSame(7, Html::visibleLength(sprintf('<b>a &amp; b</b> <a href="%s">ä</a>', TestData::url())));
        }
    }
