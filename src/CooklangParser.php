<?php

declare(strict_types=1);

namespace Txd\CooklangParser;

use Txd\CooklangParser\Exceptions\FileNotFoundException;
use Txd\CooklangParser\Exceptions\ParseException;
use Txd\CooklangParser\Models\Comment;
use Txd\CooklangParser\Models\Cookware;
use Txd\CooklangParser\Models\Ingredient;
use Txd\CooklangParser\Models\IngredientOccurrence;
use Txd\CooklangParser\Models\Metadata;
use Txd\CooklangParser\Models\Recipe;
use Txd\CooklangParser\Models\Step;
use Txd\CooklangParser\Models\Tokens\CookwareToken;
use Txd\CooklangParser\Models\Tokens\IngredientToken;
use Txd\CooklangParser\Models\Tokens\TextToken;
use Txd\CooklangParser\Models\Tokens\TimerToken;
use Txd\CooklangParser\Models\Tokens\Token;

class CooklangParser
{
    public function parseString(string $source): Recipe
    {
        return $this->doParse($source, null);
    }

    public function parseFile(string $path): Recipe
    {
        if (! is_file($path)) {
            throw new FileNotFoundException(sprintf('Cooklang file "%s" was not found.', $path));
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new FileNotFoundException(sprintf('Cooklang file "%s" could not be read.', $path));
        }

        $slug = pathinfo($path, PATHINFO_FILENAME) ?: null;

        return $this->doParse($contents, $slug);
    }

    private function doParse(string $source, ?string $slug): Recipe
    {
        $source = $this->stripBom($source);

        [$rawMetadata, $body] = $this->extractFrontMatter($source);

        [$stepChunks, $comments, $derivedMetadata] = $this->separateBody($body);

        $metadata = Metadata::fromArray(array_merge($rawMetadata, $derivedMetadata));

        $steps = [];

        foreach ($stepChunks as $index => $stepData) {
            $tokens = $this->tokenizeStep($stepData['text']);
            $steps[] = new Step($index, $tokens, $stepData['section']);
        }

        [$ingredients, $cookware] = $this->summarizeTokens($steps);

        return new Recipe(
            $slug,
            $metadata,
            $steps,
            $ingredients,
            $cookware,
            $comments
        );
    }

    /**
     * @return array{array<string, mixed>, string}
     */
    private function extractFrontMatter(string $source): array
    {
        $trimmed = ltrim($source);

        if (! str_starts_with($trimmed, '---')) {
            return [[], $source];
        }

        if (! preg_match('/\A---\s*\R(.*?)\R---\s*\R?/s', $trimmed, $matches)) {
            throw new ParseException('Front matter starting delimiter found but no closing delimiter detected.');
        }

        $metadataBlock = $matches[1];
        $body = substr($trimmed, strlen($matches[0]));

        return [$this->parseMetadataBlock($metadataBlock), ltrim($body, "\r\n")];
    }

    /**
     * @return array{0: array<int, array{text: string, section: ?string}>, 1: array<int, Comment>, 2: array<string, mixed>}
     */
    private function separateBody(string $body): array
    {
        $lines = preg_split('/\R/', $body) ?: [];

        $comments = [];
        $derivedMetadata = [];
        $steps = [];
        $buffer = [];
        $currentSection = null;

        foreach ($lines as $number => $line) {
            $lineNumber = $number + 1;
            $trimmed = trim($line);

            if ($this->isSectionLine($trimmed)) {
                if ($buffer !== []) {
                    $steps[] = ['text' => implode(' ', $buffer), 'section' => $currentSection];
                    $buffer = [];
                }

                $currentSection = $this->extractSectionName($trimmed);

                continue;
            }

            if ($trimmed === '') {
                if ($buffer !== []) {
                    $steps[] = ['text' => implode(' ', $buffer), 'section' => $currentSection];
                    $buffer = [];
                }

                continue;
            }

            if ($this->isCommentLine($trimmed)) {
                $text = $this->stripCommentPrefix($trimmed);
                $comments[] = new Comment($text, $lineNumber);

                if ($derived = $this->deriveMetadataFromComment($text)) {
                    $derivedMetadata = array_merge($derivedMetadata, $derived);
                }

                continue;
            }

            $buffer[] = $line;
        }

        if ($buffer !== []) {
            $steps[] = ['text' => implode(' ', $buffer), 'section' => $currentSection];
        }

        return [$steps, $comments, $derivedMetadata];
    }

    /**
     * @return array<int, Token>
     */
    private function tokenizeStep(string $text): array
    {
        $tokens = [];
        $buffer = '';
        $length = strlen($text);

        for ($index = 0; $index < $length; $index++) {
            $char = $text[$index];

            if ($char === '\\') {
                if ($index + 1 < $length) {
                    $buffer .= $text[$index + 1];
                    $index++;

                    continue;
                }

                $buffer .= $char;

                continue;
            }

            if ($char === '@') {
                if ($buffer !== '') {
                    $tokens[] = new TextToken($buffer);
                    $buffer = '';
                }

                $result = $this->parseIngredientToken($text, $index);
                $tokens[] = $result['token'];
                $index = $result['position'];

                continue;
            }

            if ($char === '#') {
                if ($buffer !== '') {
                    $tokens[] = new TextToken($buffer);
                    $buffer = '';
                }

                $result = $this->parseCookwareToken($text, $index);
                $tokens[] = $result['token'];
                $index = $result['position'];

                continue;
            }

            if ($char === '~') {
                if ($buffer !== '') {
                    $tokens[] = new TextToken($buffer);
                    $buffer = '';
                }

                $result = $this->parseTimerToken($text, $index);
                $tokens[] = $result['token'];
                $index = $result['position'];

                continue;
            }

            $buffer .= $char;
        }

        if ($buffer !== '') {
            $tokens[] = new TextToken($buffer);
        }

        return $tokens;
    }

    /**
     * @return array{token: IngredientToken, position: int}
     */
    private function parseIngredientToken(string $text, int $start): array
    {
        $bracePosition = strpos($text, '{', $start + 1);

        if ($bracePosition === false) {
            throw new ParseException(sprintf('Ingredient missing quantity delimiters near position %d.', $start));
        }

        $nameSegment = substr($text, $start + 1, $bracePosition - ($start + 1));
        $optional = false;
        $nameSegment = rtrim($nameSegment);

        if (strpbrk($nameSegment, '@#~') !== false) {
            throw new ParseException(sprintf('Ingredient missing quantity delimiters near position %d.', $start));
        }

        if ($nameSegment === '') {
            throw new ParseException(sprintf('Ingredient missing name at position %d.', $start));
        }

        if (str_ends_with($nameSegment, '?')) {
            $optional = true;
            $nameSegment = rtrim(substr($nameSegment, 0, -1));
        }

        $name = trim($nameSegment);

        if ($name === '') {
            throw new ParseException(sprintf('Ingredient missing name at position %d.', $start));
        }

        [$rawQuantity, $cursor] = $this->consumeBraceValue($text, $bracePosition);

        if ($rawQuantity === null) {
            throw new ParseException(sprintf('Ingredient missing quantity delimiters near position %d.', $start));
        }

        [$quantity, $unit] = $this->splitQuantity($rawQuantity);

        $token = new IngredientToken($name, $quantity, $unit, $optional, $rawQuantity);

        return [
            'token' => $token,
            'position' => $cursor,
        ];
    }

    /**
     * @return array{token: CookwareToken, position: int}
     */
    private function parseCookwareToken(string $text, int $start): array
    {
        [$name, , $offset] = $this->consumeName($text, $start + 1, allowOptional: false);

        if ($name === '') {
            throw new ParseException(sprintf('Cookware missing name at position %d.', $start));
        }

        return [
            'token' => new CookwareToken($name),
            'position' => $offset - 1,
        ];
    }

    /**
     * @return array{token: TimerToken, position: int}
     */
    private function parseTimerToken(string $text, int $start): array
    {
        $index = $start + 1;
        $length = strlen($text);
        $name = null;
        $raw = null;
        $duration = null;
        $unit = null;

        if ($index < $length && $text[$index] === '{') {
            [$raw, $index] = $this->consumeBraceValue($text, $index);
            [$duration, $unit] = $this->splitQuantity($raw);

            return [
                'token' => new TimerToken($name, $duration, $unit, $raw),
                'position' => $index,
            ];
        }

        $segment = '';

        while ($index < $length) {
            $char = $text[$index];

            if ($char === '{') {
                [$raw, $index] = $this->consumeBraceValue($text, $index);
                [$duration, $unit] = $this->splitQuantity($raw);
                break;
            }

            if ($char === '@' || $char === '#' || $char === '~') {
                $index--;
                break;
            }

            if ($this->isTokenDelimiter($char)) {
                break;
            }

            $segment .= $char;
            $index++;
        }

        if ($raw === null && $segment !== '') {
            if (preg_match('/^([\d\/\.,]+)([A-Za-z]+)?$/', $segment, $matches)) {
                $duration = $this->normalizeNumeric($matches[1]);
                $unit = $matches[2] ?? null;
            } else {
                $name = $segment;
            }
        } elseif ($raw !== null) {
            $name = $segment !== '' ? $segment : null;
        }

        if ($raw === null && $segment === '') {
            throw new ParseException(sprintf('Timer missing value near position %d.', $start));
        }

        $position = $raw !== null ? $index : max($start, $index - 1);

        return [
            'token' => new TimerToken($name, $duration, $unit, $raw),
            'position' => $position,
        ];
    }

    /**
     * @return array{array<int, Ingredient>, array<int, Cookware>}
     */
    private function summarizeTokens(array $steps): array
    {
        $ingredients = [];
        $cookware = [];

        foreach ($steps as $step) {
            $index = $step->getIndex();

            foreach ($step->getTokens() as $token) {
                if ($token instanceof IngredientToken) {
                    $key = strtolower($token->getName());

                    if (! isset($ingredients[$key])) {
                        $ingredients[$key] = new Ingredient($token->getName());
                    }

                    $ingredients[$key]->addOccurrence(
                        new IngredientOccurrence(
                            $index,
                            $token->getQuantity(),
                            $token->getUnit(),
                            $token->isOptional(),
                            $token->getRawQuantity(),
                            $step->getSection()
                        )
                    );
                }

                if ($token instanceof CookwareToken) {
                    $key = strtolower($token->getName());

                    if (! isset($cookware[$key])) {
                        $cookware[$key] = new Cookware($token->getName());
                    }

                    $cookware[$key]->addOccurrence($index);
                }
            }
        }

        return [array_values($ingredients), array_values($cookware)];
    }

    /**
     * @return array{string, bool, int}
     */
    private function consumeName(string $text, int $start, bool $allowOptional = true): array
    {
        $length = strlen($text);
        $name = '';
        $optional = false;
        $index = $start;

        while ($index < $length) {
            $char = $text[$index];

            if ($char === '?' && $allowOptional) {
                $optional = true;
                $index++;
                break;
            }

            if (! $this->isNameChar($char)) {
                break;
            }

            $name .= $char;
            $index++;
        }

        return [$name, $optional, $index];
    }

    /**
     * @return array{?string, int}
     */
    private function consumeBraceValue(string $text, int $start): array
    {
        if (! isset($text[$start]) || $text[$start] !== '{') {
            return [null, $start - 1];
        }

        $length = strlen($text);
        $index = $start + 1;
        $value = '';
        $escaped = false;

        while ($index < $length) {
            $char = $text[$index];

            if ($escaped) {
                $value .= $char;
                $escaped = false;
                $index++;

                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                $index++;

                continue;
            }

            if ($char === '}') {
                return [$value, $index];
            }

            $value .= $char;
            $index++;
        }

        throw new ParseException(sprintf('Unclosed brace value starting at position %d.', $start));
    }

    /**
     * @return array{?float, ?string}
     */
    private function splitQuantity(?string $raw): array
    {
        if ($raw === null) {
            return [null, null];
        }

        $trimmed = trim($raw);

        if ($trimmed === '') {
            return [null, null];
        }

        if (str_contains($trimmed, '%')) {
            [$quantity, $unit] = array_pad(explode('%', $trimmed, 2), 2, null);

            return [
                $this->normalizeNumeric((string) ($quantity ?? '')),
                $unit !== null && $unit !== '' ? trim($unit) : null,
            ];
        }

        if (preg_match('/^([\d\/\.,]+)\s*([A-Za-z]+)?$/', $trimmed, $matches)) {
            return [$this->normalizeNumeric($matches[1]), $matches[2] ?? null];
        }

        return [$this->normalizeNumeric($trimmed), null];
    }

    private function normalizeNumeric(string $value): ?float
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d+)\s+(\d+)\/(\d+)$/', $value, $matches)) {
            $whole = (int) $matches[1];
            $numerator = (int) $matches[2];
            $denominator = (int) $matches[3];

            if ($denominator === 0) {
                return null;
            }

            return $whole + ($numerator / $denominator);
        }

        if (preg_match('/^(\d+)\/(\d+)$/', $value, $matches)) {
            $numerator = (int) $matches[1];
            $denominator = (int) $matches[2];

            if ($denominator === 0) {
                return null;
            }

            return $numerator / $denominator;
        }

        $normalized = str_replace(',', '.', $value);

        if (is_numeric($normalized)) {
            return (float) $normalized;
        }

        return null;
    }

    private function isNameChar(string $char): bool
    {
        return ctype_alnum($char) || $char === '_' || $char === '-';
    }

    private function isCommentLine(string $line): bool
    {
        return str_starts_with($line, '//') || str_starts_with($line, '>');
    }

    private function isSectionLine(string $line): bool
    {
        return preg_match('/^==\s*(.+?)\s*==$/', $line) === 1;
    }

    private function extractSectionName(string $line): string
    {
        preg_match('/^==\s*(.+?)\s*==$/', $line, $matches);

        return trim($matches[1]);
    }

    private function stripCommentPrefix(string $line): string
    {
        if (str_starts_with($line, '//')) {
            return trim(substr($line, 2));
        }

        if (str_starts_with($line, '>')) {
            return trim(ltrim($line, '>'));
        }

        return $line;
    }

    /**
     * @return array<string, string>|null
     */
    private function deriveMetadataFromComment(string $comment): ?array
    {
        if (preg_match('/^Source:\s*(.+)$/i', $comment, $matches)) {
            return ['source' => trim($matches[1])];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function parseMetadataBlock(string $block): array
    {
        $lines = preg_split('/\R/', $block) ?: [];
        $result = [];
        $currentKey = null;

        foreach ($lines as $line) {
            if (preg_match('/^\s*([A-Za-z0-9_\-]+):\s*(.*)$/', $line, $matches)) {
                $key = $matches[1];
                $value = $matches[2];

                if ($value === '') {
                    $result[$key] = [];
                    $currentKey = $key;

                    continue;
                }

                $result[$key] = $this->castMetadataValue($value);
                $currentKey = null;

                continue;
            }

            if ($currentKey !== null && preg_match('/^\s*-\s*(.+)$/', $line, $matches)) {
                $result[$currentKey] ??= [];
                $result[$currentKey][] = $this->castMetadataValue($matches[1]);
            }
        }

        return $result;
    }

    private function castMetadataValue(string $value): mixed
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
            return substr($value, 1, -1);
        }

        if (str_starts_with($value, '[') && str_ends_with($value, ']')) {
            $inner = trim(substr($value, 1, -1));

            if ($inner === '') {
                return [];
            }

            return array_map('trim', explode(',', $inner));
        }

        $lower = strtolower($value);

        if ($lower === 'true') {
            return true;
        }

        if ($lower === 'false') {
            return false;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return $value;
    }

    private function stripBom(string $source): string
    {
        if (str_starts_with($source, "\xEF\xBB\xBF")) {
            return substr($source, 3) ?: '';
        }

        return $source;
    }

    private function isTokenDelimiter(string $char): bool
    {
        return ctype_space($char) || in_array($char, [',', '.', ';', ':', '!', '?', ')', '('], true);
    }
}
