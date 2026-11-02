<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var')
    ->notPath([
        'config/bundles.php',
        'config/reference.php',
    ])
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        // Deliberate deviation from the Symfony standard: `$x === null` reads more naturally;
        // PHPStan finds the typo that Yoda conditions were meant to prevent (`=` instead of `===`).
        'yoda_style' => false,
    ])
    ->setFinder($finder)
;
