# Cooklang Parser

`txd/cooklang-parser` is a framework-agnostic PHP 8.3+ parser that converts [Cooklang](https://cooklang.org) recipes into a structured, typed object graph. The package can be dropped into a Laravel 12 application or any other PHP project that relies on Composer.

## Installation

```bash
composer require txd/cooklang-parser
```

## Usage

```php
<?php

use Txd\CooklangParser\CooklangParser;

$cooklang = <<<COOK
---
title: Garlic Bread
servings: 4
tags: [snack, sharing]
---
Melt @butter{50%g} with @garlic{2%clove}.
Brush over #loaf and bake for ~10min.
COOK;

$parser = new CooklangParser();
$recipe = $parser->parseString($cooklang);

$title = $recipe->getMetadata()->getTitle();         // "Garlic Bread"
$ingredients = $recipe->getIngredients();            // Ingredient entities with occurrences
$steps = $recipe->getSteps();                        // Step objects with tokens
$cookware = $recipe->getCookwareNames();             // ['loaf']
$source = $recipe->getMetadata()->getSource();       // Derived from "> Source:" comment lines if present
```

You can also parse a file path and automatically derive a slug from the filename:

```php
$recipe = $parser->parseFile(__DIR__ . '/recipes/garlic-bread.cook');
echo $recipe->getSlug(); // "garlic-bread"
```

### Data Model Overview

- `Recipe`: Holds the slug, metadata, steps, ingredient summary, cookware summary, and captured comments.
- `Metadata`: Value object for front matter (title, servings, tags, source, times, and arbitrary keys).
- `Step`: Contains ordered `Token` objects and can re-render itself as user-facing text.
- `Token` types:
  - `TextToken`: Plain text fragments.
  - `IngredientToken`: Parsed ingredient name, quantity, unit, optional flag, and raw quantity string.
  - `CookwareToken`: Named cookware references.
- `TimerToken`: Named or anonymous timers with duration and units.
- `Ingredient` / `IngredientOccurrence`: Deduplicated ingredient summary with per-step occurrences.
- `Cookware`: Deduplicated cookware references with occurrence indexes.
- `Comment`: Stored for any line beginning with `//` or `>` (special handling for `> Source:`).
- Section headers: Lines like `== Filling ==` set the section for subsequent steps and ingredient occurrences.

### Supported Cooklang Features

- YAML-like front matter between `---` fences (key/value pairs and dash lists).
- Inline ingredients (`@`) with space-friendly names and mandatory `{}` quantity delimiters (quantity/unit optional), plus cookware (`#`) and timers (`~`) with quantity/unit parsing.
- Escaped control characters via `\@`, `\#`, and `\~`.
- Comment lines beginning with `//` or `>` (with `> Source:` promoted to metadata).
- Step splitting on blank lines, blank-line tolerant.

Documented limitations:

- Cookware names currently support alphanumeric characters, underscores, and dashes (no spaces).
- The front matter parser accepts a safe YAML subset (key/value pairs, dash lists, inline arrays).

## Testing

```bash
composer install
./vendor/bin/pest
```

## License

Released under the MIT License. See [LICENSE](LICENSE).
