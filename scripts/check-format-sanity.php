<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$targets = [
    'src' => ['php'],
    'tests' => ['php'],
    'config' => ['php'],
    'database' => ['php'],
    'docs' => ['md'],
    '.github/workflows' => ['yml', 'yaml'],
    '.github/instructions' => ['md'],
    '.github/skills' => ['md'],
    '.cursor/rules' => ['md', 'mdc'],
];

$explicitFiles = [
    'README.md',
    'AGENTS.md',
    'CLAUDE.md',
    'captainhook.json',
    'composer.json',
    'composer.lock',
    'pint.json',
];

$errors = [];

foreach ($explicitFiles as $file) {
    checkFile($root . '/' . $file, $file, $errors);
}

foreach ($targets as $directory => $extensions) {
    $path = $root . '/' . $directory;

    if (! is_dir($path)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if (! $file instanceof SplFileInfo || ! $file->isFile()) {
            continue;
        }

        $extension = strtolower($file->getExtension());

        if (! in_array($extension, $extensions, true)) {
            continue;
        }

        $relative = substr($file->getPathname(), strlen($root) + 1);
        checkFile($file->getPathname(), $relative, $errors);
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Format sanity check failed:\n");

    foreach ($errors as $error) {
        fwrite(STDERR, "- {$error}\n");
    }

    exit(1);
}

echo "Format sanity check passed.\n";

/**
 * @param  array<int, string>  $errors
 */
function checkFile(string $path, string $relative, array &$errors): void
{
    if (! is_readable($path)) {
        $errors[] = "{$relative} cannot be read.";

        return;
    }

    $contents = file_get_contents($path);

    if ($contents === false) {
        $errors[] = "{$relative} cannot be read.";

        return;
    }

    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $lines = preg_split('/\R/', rtrim($contents, "\r\n"));
    $lineCount = is_array($lines) ? count($lines) : 0;

    if ($extension === 'json') {
        json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $errors[] = "{$relative} contains invalid JSON: " . json_last_error_msg() . '.';
        }
    }

    if ($extension === 'php' && str_contains($contents, "<?php\n") && $lineCount < 4) {
        $errors[] = "{$relative} looks suspiciously collapsed for a PHP file ({$lineCount} lines).";
    }

    if (in_array($extension, ['json', 'md', 'mdc', 'yml', 'yaml'], true) && trim($contents) !== '' && $lineCount < 3) {
        $errors[] = "{$relative} looks suspiciously collapsed for a {$extension} file ({$lineCount} lines).";
    }

    if (in_array($extension, ['json', 'php', 'md', 'mdc', 'yml', 'yaml'], true)) {
        foreach ($lines ?: [] as $number => $line) {
            if (strlen($line) > 240) {
                $errors[] = "{$relative}:" . ($number + 1) . ' is longer than 240 characters.';
            }
        }
    }
}
