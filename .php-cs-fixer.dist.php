<?php
$potentialDirs = ['/lib', '/src', '/test', '/tests'];

$finder = (new PhpCsFixer\Finder());
foreach ($potentialDirs as $dir) {
    $full = __DIR__ . $dir;
    if (is_dir($full)) {
        $finder->in($full);
    }
}

$finder->exclude(['fixtures']);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PER-CS' => true,
        '@PHP83Migration' => true,
        'php_unit_test_class_requires_covers' => true,
        'nullable_type_declaration_for_default_null_value' => true,
    ])
    ->setFinder($finder)
;
