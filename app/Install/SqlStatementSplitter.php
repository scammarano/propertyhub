<?php
declare(strict_types=1);

final class SqlStatementSplitter
{
    /** @return string[] */
    public static function split(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $quote = null;
        $escaped = false;
        $lineComment = false;
        $blockComment = false;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($lineComment) {
                if ($char === "\n") { $lineComment = false; $buffer .= $char; }
                continue;
            }
            if ($blockComment) {
                if ($char === '*' && $next === '/') { $blockComment = false; $i++; }
                continue;
            }
            if ($quote === null) {
                if (($char === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($sql[$i + 2]))) || $char === '#') {
                    $lineComment = true;
                    if ($char === '-') $i++;
                    continue;
                }
                if ($char === '/' && $next === '*') { $blockComment = true; $i++; continue; }
                if ($char === "'" || $char === '"' || $char === '`') { $quote = $char; $buffer .= $char; continue; }
                if ($char === ';') {
                    $statement = trim($buffer);
                    if ($statement !== '') $statements[] = $statement;
                    $buffer = '';
                    continue;
                }
                $buffer .= $char;
                continue;
            }

            $buffer .= $char;
            if ($escaped) { $escaped = false; continue; }
            if ($char === '\\' && $quote !== '`') { $escaped = true; continue; }
            if ($char === $quote) {
                if ($next === $quote && $quote !== '`') { $buffer .= $next; $i++; continue; }
                $quote = null;
            }
        }
        $tail = trim($buffer);
        if ($tail !== '') $statements[] = $tail;
        return $statements;
    }
}
