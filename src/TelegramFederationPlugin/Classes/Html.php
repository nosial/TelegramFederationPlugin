<?php

    namespace TelegramFederationPlugin\Classes;

    class Html
    {
        /**
         * Escapes text for use in a Telegram HTML message
         *
         * @param string $text The text to escape
         * @return string The escaped text
         */
        public static function escape(string $text): string
        {
            return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        }

        /**
         * Returns the escaped text as inline code
         *
         * @param string $text The text
         * @return string The HTML
         */
        public static function code(string $text): string
        {
            return '<code>' . self::escape($text) . '</code>';
        }

        /**
         * Returns a link to the URL, or the text as inline code if there is no URL
         *
         * @param string $text The text of the link
         * @param string|null $url The URL
         * @return string The HTML
         */
        public static function link(string $text, ?string $url): string
        {
            if($url === null)
            {
                return self::code($text);
            }

            return sprintf('<a href="%s">%s</a>', self::escape($url), self::escape($text));
        }

        /**
         * Returns the timestamp as a date and time in UTC, eg; 2026-10-06 09:49:35 UTC
         *
         * @param int $timestamp The Unix timestamp
         * @return string The HTML
         */
        public static function time(int $timestamp): string
        {
            return self::escape(gmdate('Y-m-d H:i:s \U\T\C', $timestamp));
        }

        /**
         * Converts an enum value into a readable name, eg; REPORT_OPERATOR_ASSIGNED => Report Operator Assigned
         *
         * @param string $value The enum value
         * @return string The readable name
         */
        public static function humanize(string $value): string
        {
            return ucwords(strtolower(str_replace('_', ' ', $value)));
        }

        /**
         * Converts the input into a Telegram hashtag; Telegram only recognizes letters, digits and underscores as part
         * of a hashtag, so all other characters are replaced with underscores (eg; Federation Server => #FEDERATION_SERVER)
         *
         * @param string $input The input to convert
         * @return string|null The hashtag, null if the input cannot be represented as a hashtag
         */
        public static function hashtag(string $input): ?string
        {
            $tag = trim((string)preg_replace('/[^\p{L}\p{N}_]+/u', '_', $input), '_');

            // Telegram does not recognize hashtags consisting only of digits
            if($tag === '' || ctype_digit($tag))
            {
                return null;
            }

            return '#' . mb_strtoupper($tag);
        }

        /**
         * Truncates text to the given number of characters
         *
         * @param string $text The text to truncate
         * @param int $length The maximum number of characters
         * @return string The truncated text
         */
        public static function truncate(string $text, int $length): string
        {
            if(mb_strlen($text) <= $length)
            {
                return $text;
            }

            return mb_substr($text, 0, max(0, $length - 3)) . '...';
        }

        /**
         * Returns a file size in a readable unit, eg; 1.5 MB
         *
         * @param int $bytes The size in bytes
         * @return string The readable size
         */
        public static function fileSize(int $bytes): string
        {
            $units = ['B', 'KB', 'MB', 'GB', 'TB'];
            $size = (float)max(0, $bytes);
            $unit = 0;

            while($size >= 1024 && $unit < count($units) - 1)
            {
                $size /= 1024;
                $unit++;
            }

            return $unit === 0 ? sprintf('%d B', $bytes) : sprintf('%.1f %s', $size, $units[$unit]);
        }

        /**
         * Converts a Telegram HTML message into plain text
         *
         * @param string $html The HTML message
         * @return string The plain text message
         */
        public static function toPlainText(string $html): string
        {
            return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        /**
         * Returns the number of characters Telegram would count after parsing the HTML message
         *
         * @param string $html The HTML message
         * @return int The visible length of the message
         */
        public static function visibleLength(string $html): int
        {
            return mb_strlen(self::toPlainText($html));
        }
    }
