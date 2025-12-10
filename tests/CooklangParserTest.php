<?php

declare(strict_types=1);

use Txd\CooklangParser\CooklangParser;
use Txd\CooklangParser\Exceptions\FileNotFoundException;
use Txd\CooklangParser\Exceptions\ParseException;
use Txd\CooklangParser\Models\Tokens\CookwareToken;
use Txd\CooklangParser\Models\Tokens\IngredientToken;
use Txd\CooklangParser\Models\Tokens\TimerToken;

it('parses a recipe with metadata and structured tokens', function (): void {
    $content = <<<'COOK'
---
title: Garlic Bread
servings: 4
tags:
  - snack
  - sharing
---
Melt @butter{50%g} with @garlic{2%clove}.

Brush over #loaf and bake for ~10min.
COOK;

    $parser = new CooklangParser;
    $recipe = $parser->parseString($content);

    expect($recipe->getMetadata()->getTitle())->toBe('Garlic Bread');
    expect($recipe->getMetadata()->getServings())->toBe(4);
    expect($recipe->getTags())->toBe(['snack', 'sharing']);
    expect($recipe->getSteps())->toHaveCount(2);
    expect($recipe->getIngredients())->toHaveCount(2);
    expect($recipe->getIngredients()[0]->getOccurrences()[0]->getQuantity())->toBe(50.0);
    expect($recipe->getCookwareNames())->toBe(['loaf']);
});

it('extracts detailed ingredient information', function (): void {
    $content = 'Combine @flour{200%g}, @milk{1.5%cup}, and @salt?{1%pinch}.';

    $recipe = (new CooklangParser)->parseString($content);
    $step = $recipe->getSteps()[0];
    $ingredients = array_filter(
        $step->getTokens(),
        static fn ($token) => $token instanceof IngredientToken
    );

    expect($ingredients)->toHaveCount(3);

    /** @var IngredientToken $salt */
    $salt = array_values($ingredients)[2];

    expect($salt->isOptional())->toBeTrue();
    expect($salt->getUnit())->toBe('pinch');
    expect($salt->getQuantity())->toBe(1.0);
});

it('parses cookware and timers as dedicated tokens', function (): void {
    $content = 'Heat #skillet and cook for ~sear{2%min} before resting for ~10min.';

    $recipe = (new CooklangParser)->parseString($content);
    $tokens = $recipe->getSteps()[0]->getTokens();

    $cookware = array_values(
        array_filter($tokens, static fn ($token) => $token instanceof CookwareToken)
    );

    $timers = array_values(
        array_filter($tokens, static fn ($token) => $token instanceof TimerToken)
    );

    expect($cookware)->toHaveCount(1);
    expect($cookware[0]->getName())->toBe('skillet');
    expect($timers)->toHaveCount(2);
    expect($timers[0]->getName())->toBe('sear');
    expect($timers[0]->getDuration())->toBe(2.0);
    expect($timers[1]->getDuration())->toBe(10.0);
});

it('captures comments and derives metadata from special comments', function (): void {
    $content = <<<'COOK'
> Source: https://example.test/recipe

// Prep the dough
Mix ingredients gently.
COOK;

    $recipe = (new CooklangParser)->parseString($content);

    expect($recipe->getMetadata()->getSource())->toBe('https://example.test/recipe');
    expect($recipe->getComments())->toHaveCount(2);
    expect($recipe->getComments()[0]->getText())->toContain('Source');
});

it('keeps escaped markers as plain text', function (): void {
    $content = 'Write \\@literal symbols and \\#hash without tokens.';

    $tokens = (new CooklangParser)->parseString($content)->getSteps()[0]->getTokens();

    expect($tokens)->toHaveCount(1);
    expect($tokens[0]->toText())->toContain('@literal');
});

it('derives slug when parsing from a file', function (): void {
    $parser = new CooklangParser;
    $path = tempnam(sys_get_temp_dir(), 'cooklang');

    if ($path === false) {
        test()->fail('Unable to create temporary file for testing.');
    }

    $target = $path.'.cook';
    rename($path, $target);

    file_put_contents($target, 'Mix @water and @flour.');

    $recipe = $parser->parseFile($target);

    expect($recipe->getSlug())->toBe(pathinfo($target, PATHINFO_FILENAME));

    unlink($target);
});

it('parses ingredient names with spaces and requires brace delimiters', function (): void {
    $content = 'Stir in @brown sugar{2%tbsp} and @soy sauce?{} before serving.';

    $recipe = (new CooklangParser)->parseString($content);
    $tokens = $recipe->getSteps()[0]->getTokens();

    /** @var IngredientToken[] $ingredients */
    $ingredients = array_values(
        array_filter($tokens, static fn ($token) => $token instanceof IngredientToken)
    );

    expect($ingredients)->toHaveCount(2);
    expect($ingredients[0]->getName())->toBe('brown sugar');
    expect($ingredients[0]->getQuantity())->toBe(2.0);
    expect($ingredients[0]->getUnit())->toBe('tbsp');
    expect($ingredients[1]->getName())->toBe('soy sauce');
    expect($ingredients[1]->isOptional())->toBeTrue();
    expect($ingredients[1]->getRawQuantity())->toBe('');
});

it('associates steps and ingredient occurrences with sections', function (): void {
    $content = <<<'COOK'
== Dough ==
Mix @bread flour{300%g} with @water{200%ml}.

== Filling ==
Spread @brown sugar{50%g} on the dough.
COOK;

    $recipe = (new CooklangParser)->parseString($content);
    $steps = $recipe->getSteps();

    expect($steps)->toHaveCount(2);
    expect($steps[0]->getSection())->toBe('Dough');
    expect($steps[1]->getSection())->toBe('Filling');

    $ingredients = $recipe->getIngredients();
    $occurrences = $ingredients[0]->getOccurrences();

    expect($occurrences[0]->getSection())->toBe('Dough');
});

it('throws a parse exception for invalid syntax', function (): void {
    $content = 'Add @ {100%g}';

    expect(fn () => (new CooklangParser)->parseString($content))
        ->toThrow(ParseException::class);
});

it('throws a parse exception when ingredient braces are missing', function (): void {
    $content = 'Season with @salt and @pepper{1%tsp}.';

    expect(fn () => (new CooklangParser)->parseString($content))
        ->toThrow(ParseException::class);
});

it('throws when the Cooklang file does not exist', function (): void {
    expect(fn () => (new CooklangParser)->parseFile('/tmp/missing-file.cook'))
        ->toThrow(FileNotFoundException::class);
});
