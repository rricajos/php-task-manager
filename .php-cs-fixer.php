<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->append([__DIR__ . '/api.php', __DIR__ . '/app.php']);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'single_quote' => true,
        'trailing_comma_in_multiline' => true,
        'no_whitespace_before_comma_in_array' => true,
        'blank_line_before_statement' => ['statements' => ['return', 'throw']],
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(false);
