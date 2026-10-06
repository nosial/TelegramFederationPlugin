<?php

    namespace TelegramFederationPlugin\Objects;

    use FederationLib\Enums\RecordType;
    use TelegramFederationPlugin\Classes\Html;
    use TelegramFederationPlugin\Classes\RecordLinks;

    class Notification
    {
        /**
         * Telegram allows up to 100 inline buttons, a notification is kept far below that so it stays readable
         */
        public const int MAX_BUTTONS = 8;
        private const int BUTTONS_PER_ROW = 2;

        /** @var string[] */
        private array $tags;
        private string $title;
        private ?string $serverName;
        /** @var string[] */
        private array $lines;
        /** @var array<string, string> The labels of the buttons, by their URL */
        private array $buttons;

        /**
         * Notification constructor.
         *
         * @param string $title The title of the notification, eg; Report Closed
         * @param string[] $tags The hashtags of the notification without the #, eg; RECORD_CHANGE, REPORT_CLOSED
         * @param string|null $serverName The name of the FederationLib server the notification is from
         */
        public function __construct(string $title, array $tags, ?string $serverName=null)
        {
            $this->title = $title;
            $this->tags = [];
            $this->serverName = $serverName;
            $this->lines = [];
            $this->buttons = [];

            foreach(array_merge($tags, $serverName !== null ? [$serverName] : []) as $tag)
            {
                $hashtag = Html::hashtag($tag);
                if($hashtag !== null && !in_array($hashtag, $this->tags, true))
                {
                    $this->tags[] = $hashtag;
                }
            }
        }

        /**
         * Adds a field with a plain text value, the value is escaped
         *
         * @param string $label The label of the field
         * @param string|null $value The value, the field is skipped if null
         * @param bool $code True to show the value as inline code
         * @return Notification The notification
         */
        public function addField(string $label, ?string $value, bool $code=false): Notification
        {
            if($value === null)
            {
                return $this;
            }

            return $this->addHtmlField($label, $code ? Html::code($value) : Html::escape($value));
        }

        /**
         * Adds a field with an HTML value, the value must already be escaped
         *
         * @param string $label The label of the field
         * @param string|null $html The value, the field is skipped if null
         * @return Notification The notification
         */
        public function addHtmlField(string $label, ?string $html): Notification
        {
            if($html !== null)
            {
                $this->lines[] = sprintf('<b>%s:</b> %s', Html::escape($label), $html);
            }

            return $this;
        }

        /**
         * Adds a line of HTML, the line must already be escaped
         *
         * @param string $html The line
         * @return Notification The notification
         */
        public function addHtmlLine(string $html): Notification
        {
            $this->lines[] = $html;
            return $this;
        }

        /**
         * Adds quoted text below a label, long text is truncated and collapsed
         *
         * @param string $label The label of the quote
         * @param string|null $text The text, the quote is skipped if null or empty
         * @param int $maxLength The maximum number of characters of the text
         * @return Notification The notification
         */
        public function addQuote(string $label, ?string $text, int $maxLength): Notification
        {
            if($text === null || trim($text) === '')
            {
                return $this;
            }

            $text = Html::truncate(trim($text), $maxLength);
            $this->lines[] = sprintf('<b>%s:</b>', Html::escape($label));
            $this->lines[] = sprintf(mb_strlen($text) > 200 ? '<blockquote expandable>%s</blockquote>' : '<blockquote>%s</blockquote>', Html::escape($text));
            return $this;
        }

        /**
         * Adds a button that opens a record, see RecordLinks::getUrl()
         *
         * @param string $label The label of the button
         * @param RecordType $type The type of the record
         * @param string|null $uuid The UUID of the record, the button is skipped if null
         * @return Notification The notification
         */
        public function addRecordButton(string $label, RecordType $type, ?string $uuid): Notification
        {
            return $this->addButton($label, RecordLinks::getUrl($type, $uuid));
        }

        /**
         * Adds a button that opens the URL, buttons with the same URL are only added once and buttons above the
         * maximum number of buttons are skipped
         *
         * @param string $label The label of the button
         * @param string|null $url The URL, the button is skipped if null
         * @return Notification The notification
         */
        public function addButton(string $label, ?string $url): Notification
        {
            if($url === null || isset($this->buttons[$url]) || count($this->buttons) >= self::MAX_BUTTONS)
            {
                return $this;
            }

            $this->buttons[$url] = $label;
            return $this;
        }

        /**
         * Returns the URLs of the buttons, in the order they were added
         *
         * @return string[] The URLs
         */
        public function getButtonUrls(): array
        {
            return array_keys($this->buttons);
        }

        /**
         * Returns the title of the notification
         *
         * @return string The title
         */
        public function getTitle(): string
        {
            return $this->title;
        }

        /**
         * Returns the notification as a Telegram HTML message
         *
         * @return string The HTML message
         */
        public function getText(): string
        {
            $lines = [];
            if(count($this->tags) > 0)
            {
                $lines[] = implode(' ', $this->tags);
            }

            $lines[] = $this->serverName === null
                ? sprintf('<b>%s</b>', Html::escape($this->title))
                : sprintf('<b>%s</b> on <b>%s</b>', Html::escape($this->title), Html::escape($this->serverName));

            if(count($this->lines) > 0)
            {
                $lines[] = '';
                array_push($lines, ...$this->lines);
            }

            return implode("\n", $lines);
        }

        /**
         * Returns the buttons as the rows of a Telegram inline keyboard
         *
         * @return array The rows of InlineKeyboardButton objects, empty if there are no buttons
         */
        public function getInlineKeyboard(): array
        {
            $buttons = [];
            foreach($this->buttons as $url => $label)
            {
                $buttons[] = ['text' => $label, 'url' => (string)$url];
            }

            return array_chunk($buttons, self::BUTTONS_PER_ROW);
        }
    }
