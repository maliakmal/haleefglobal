<?php
declare(strict_types=1);

namespace Haleef\Training;

final class Markdown
{
    private static function inline(string $line): string
    {
        $escape = static fn(string $s) => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $format = static fn(string $s) => preg_replace(
            ['~\*\*([^*]+)\*\*~', '~(?<!\*)\*([^*]+)\*(?!\*)~', '~`([^`]+)`~'],
            ['<strong>$1</strong>', '<em>$1</em>', '<code>$1</code>'], $escape($s)
        );
        $parts = preg_split('~(\[[^\]\n]+\]\(https?://[^\s)]+\))~i', $line, -1, PREG_SPLIT_DELIM_CAPTURE);
        $html = '';
        foreach ($parts as $i=>$part) {
            if ($i % 2 && preg_match('~^\[([^\]]+)\]\((https?://[^\s)]+)\)$~i', $part, $m)) {
                $html .= '<a rel="nofollow noopener" href="' . $escape($m[2]) . '">' . $format($m[1]) . '</a>';
            } else {
                $html .= $format($part);
            }
        }
        return $html;
    }

    public static function render(?string $markdown): string
    {
        $html = '';
        $open = '';
        foreach (preg_split('/\R/', trim((string) $markdown)) as $line) {
            $line = trim($line);
            if (preg_match('/^(#{1,4})\s+(.+)$/', $line, $m)) {
                $tag = 'h' . min(6, strlen($m[1]) + 2);
                $body = self::inline($m[2]);
            } elseif (preg_match('/^([-*+] |\d+\. )(.+)$/', $line, $m)) {
                $tag = ctype_digit($m[1][0]) ? 'ol' : 'ul';
                $body = '<li>' . self::inline($m[2]) . '</li>';
            } else {
                $tag = $line === '' ? '' : 'p';
                $body = self::inline($line);
            }
            if ($tag !== $open || str_starts_with($tag, 'h')) {
                if ($open !== '') $html .= "</$open>";
                if ($tag !== '') $html .= "<$tag>";
            } elseif ($tag === 'p') { $html .= '<br>'; }
            $html .= $body;
            $open = $tag;
        }
        return $html . ($open !== '' ? "</$open>" : '');
    }
}
