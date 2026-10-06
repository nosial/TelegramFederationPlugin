<?php

    namespace TelegramFederationPlugin\Objects;

    use FederationLib\Enums\RecordType;
    use TelegramFederationPlugin\Classes\Html;
    use TelegramFederationPlugin\Helpers\PluginConfiguration;
    use TelegramFederationPlugin\Helpers\PluginTestCase;
    use TelegramFederationPlugin\Helpers\TestData;

    class NotificationTest extends PluginTestCase
    {
        public function testHeader(): void
        {
            $notification = new Notification('Report Closed', ['RECORD_CHANGE', 'REPORT_CLOSED'], 'Federation Server');

            // The server's name is a hashtag as well
            $this->assertSame("#RECORD_CHANGE #REPORT_CLOSED #FEDERATION_SERVER\n<b>Report Closed</b> on <b>Federation Server</b>", $notification->getText());
        }

        public function testHeaderWithoutServerName(): void
        {
            $notification = new Notification('Content Scanned', ['CONTENT_SCAN']);

            $this->assertSame("#CONTENT_SCAN\n<b>Content Scanned</b>", $notification->getText());
        }

        public function testHeaderWithoutTags(): void
        {
            $this->assertSame('<b>Content Scanned</b>', new Notification('Content Scanned', [])->getText());
        }

        public function testDuplicateTagsAreAddedOnce(): void
        {
            $notification = new Notification('Test', ['TEST', 'TEST']);

            $this->assertStringStartsWith("#TEST\n", $notification->getText());
        }

        public function testTagsThatAreNotHashtagsAreLeftOut(): void
        {
            $notification = new Notification('Test', ['TEST', (string)random_int(0, PHP_INT_MAX)]);

            $this->assertStringStartsWith("#TEST\n", $notification->getText());
        }

        public function testFieldsFollowTheHeader(): void
        {
            $notification = new Notification('Test', []);
            $notification->addHtmlField('Field', '<i>value</i>');

            $this->assertSame("<b>Test</b>\n\n<b>Field:</b> <i>value</i>", $notification->getText());
        }

        public function testFieldIsEscaped(): void
        {
            $value = uniqid('<value> & ');
            $notification = new Notification('Test', []);
            $notification->addField('Label <script>', $value);

            $this->assertStringEndsWith(sprintf('<b>Label &lt;script&gt;:</b> %s', Html::escape($value)), $notification->getText());
        }

        public function testCodeField(): void
        {
            $value = uniqid('<value> & ');
            $notification = new Notification('Test', []);
            $notification->addField('Code', $value, true);

            $this->assertStringEndsWith(sprintf('<b>Code:</b> %s', Html::code($value)), $notification->getText());
        }

        public function testFieldWithoutValueIsSkipped(): void
        {
            $notification = new Notification('Test', []);
            $notification->addField('Skipped', null);

            $this->assertSame('<b>Test</b>', $notification->getText());
        }

        public function testHtmlLine(): void
        {
            $notification = new Notification('Test', []);
            $notification->addHtmlLine('<i>line</i>');

            $this->assertStringEndsWith("\n<i>line</i>", $notification->getText());
        }

        public function testQuote(): void
        {
            $text = uniqid('<quoted> ');
            $notification = new Notification('Test', []);
            $notification->addQuote('Quote', "  $text  ", 1000);

            $this->assertStringEndsWith(sprintf("<b>Quote:</b>\n<blockquote>%s</blockquote>", Html::escape($text)), $notification->getText());
        }

        public function testLongQuoteIsTruncatedAndCollapsed(): void
        {
            $notification = new Notification('Test', []);
            $notification->addQuote('Quote', str_repeat('a', 500), 300);

            $this->assertStringEndsWith(sprintf("<blockquote expandable>%s...</blockquote>", str_repeat('a', 297)), $notification->getText());
        }

        public function testBlankQuoteIsSkipped(): void
        {
            $notification = new Notification('Test', []);
            $notification->addQuote('Quote', '   ', 1000);
            $notification->addQuote('Quote', null, 1000);

            $this->assertSame('<b>Test</b>', $notification->getText());
        }

        public function testButtonWithoutUrlIsSkipped(): void
        {
            $notification = new Notification('Test', []);
            $notification->addButton('Skipped', null);

            $this->assertSame([], $notification->getButtonUrls());
        }

        public function testButtonWithTheSameUrlIsAddedOnce(): void
        {
            $url = TestData::url();
            $notification = new Notification('Test', []);
            $notification->addButton('First', $url);
            $notification->addButton('Duplicate', $url);

            $this->assertSame([[['text' => 'First', 'url' => $url]]], $notification->getInlineKeyboard());
        }

        public function testButtonsAreLimited(): void
        {
            $urls = array_map(fn() => TestData::url(), range(0, Notification::MAX_BUTTONS));
            $notification = new Notification('Test', []);
            foreach($urls as $url)
            {
                $notification->addButton('Button', $url);
            }

            $this->assertSame(array_slice($urls, 0, Notification::MAX_BUTTONS), $notification->getButtonUrls());
        }

        public function testInlineKeyboardHasTwoButtonsPerRow(): void
        {
            $urls = array_map(fn() => TestData::url(), range(0, 2));
            $notification = new Notification('Test', []);
            foreach($urls as $index => $url)
            {
                $notification->addButton('Button ' . $index, $url);
            }

            $this->assertSame([
                [['text' => 'Button 0', 'url' => $urls[0]], ['text' => 'Button 1', 'url' => $urls[1]]],
                [['text' => 'Button 2', 'url' => $urls[2]]],
            ], $notification->getInlineKeyboard());
        }

        public function testRecordButton(): void
        {
            $web = TestData::url();
            $uuid = TestData::uuid();
            PluginConfiguration::apply(['federation_web' => $web]);
            $notification = new Notification('Test', []);
            $notification->addRecordButton('View Report', RecordType::REPORT, $uuid);

            $this->assertSame([[['text' => 'View Report', 'url' => $web . 'reports/' . $uuid]]], $notification->getInlineKeyboard());
        }

        public function testRecordButtonWithoutUrlIsSkipped(): void
        {
            $notification = new Notification('Test', []);
            $notification->addRecordButton('View Report', RecordType::REPORT, TestData::uuid());

            $this->assertSame([], $notification->getInlineKeyboard());
        }
    }
