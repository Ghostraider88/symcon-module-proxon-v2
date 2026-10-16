<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];
$guidPattern = '/^\{[A-F0-9]{8}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{12}\}$/D';
$libraryKeys = ['id', 'author', 'name', 'url', 'compatibility', 'version', 'build', 'date'];
$moduleKeys = [
    'id',
    'name',
    'type',
    'vendor',
    'aliases',
    'parentRequirements',
    'childRequirements',
    'implemented',
    'url',
    'prefix'
];

$error = static function (string $message) use (&$errors): void {
    $errors[] = $message;
};

$readJson = static function (string $path) use ($error): ?array {
    if (!is_file($path)) {
        $error('Missing JSON file: ' . $path);
        return null;
    }

    try {
        $value = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        $error('Invalid JSON in ' . $path . ': ' . $exception->getMessage());
        return null;
    }

    if (!is_array($value)) {
        $error('Expected a JSON object in ' . $path);
        return null;
    }

    return $value;
};

$checkKeys = static function (array $value, array $allowed, array $required, string $context) use ($error): void {
    foreach (array_diff(array_keys($value), $allowed) as $key) {
        $error($context . ' contains undocumented field: ' . $key);
    }
    foreach (array_diff($required, array_keys($value)) as $key) {
        $error($context . ' is missing required field: ' . $key);
    }
};

$isGuid = static fn(mixed $value): bool => is_string($value) && preg_match($guidPattern, $value) === 1;
$isName = static fn(mixed $value): bool => is_string($value) && preg_match('/^[A-Za-z0-9]+(?:[ _][A-Za-z0-9]+)*$/D', $value) === 1;
$isUrl = static fn(mixed $value): bool => is_string($value) && ($value === '' || preg_match('/^https?:\/\//i', $value) === 1);

$library = $readJson($root . DIRECTORY_SEPARATOR . 'library.json');
$allGuids = [];
if ($library !== null) {
    $checkKeys($library, $libraryKeys, $libraryKeys, 'library.json');
    if (!$isGuid($library['id'] ?? null)) {
        $error('library.json id must be an uppercase brace-wrapped GUID.');
    } else {
        $allGuids[] = ['id' => $library['id'], 'source' => 'library.json'];
    }
    if (!is_string($library['author'] ?? null) || trim($library['author']) === '') {
        $error('library.json author must be a non-empty string.');
    }
    if (!$isName($library['name'] ?? null)) {
        $error('library.json name contains unsupported characters.');
    }
    if (!$isUrl($library['url'] ?? null)) {
        $error('library.json url must be empty or begin with http:// or https://.');
    }
    if (!is_string($library['version'] ?? null) || trim($library['version']) === '') {
        $error('library.json version must be a non-empty string.');
    }
    if (!is_int($library['build'] ?? null) || $library['build'] < 1) {
        $error('library.json build must be a positive integer.');
    }
    if (!is_int($library['date'] ?? null) || $library['date'] < 1) {
        $error('library.json date must be a positive Unix timestamp.');
    }
    if (!is_array($library['compatibility'] ?? null)) {
        $error('library.json compatibility must be an object.');
    } else {
        $checkKeys($library['compatibility'], ['version', 'date'], [], 'library.json compatibility');
        if (isset($library['compatibility']['version']) && !is_string($library['compatibility']['version'])) {
            $error('library.json compatibility.version must be a string.');
        }
        if (isset($library['compatibility']['date']) && !is_int($library['compatibility']['date'])) {
            $error('library.json compatibility.date must be an integer.');
        }
    }
}

$moduleCount = 0;
foreach (glob($root . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $moduleDirectory) {
    $moduleJsonPath = $moduleDirectory . DIRECTORY_SEPARATOR . 'module.json';
    if (!is_file($moduleJsonPath)) {
        continue;
    }

    $moduleCount++;
    $directoryName = basename($moduleDirectory);
    $context = $directoryName . '/module.json';
    $module = $readJson($moduleJsonPath);
    if ($module === null) {
        continue;
    }

    $checkKeys($module, $moduleKeys, $moduleKeys, $context);
    if (!$isGuid($module['id'] ?? null)) {
        $error($context . ' id must be an uppercase brace-wrapped GUID.');
    } else {
        $allGuids[] = ['id' => $module['id'], 'source' => $context];
    }
    if (!$isName($module['name'] ?? null)) {
        $error($context . ' name contains unsupported characters.');
    } elseif ($module['name'] !== $directoryName) {
        $error($context . ' name must match its module directory.');
    }
    if (!is_int($module['type'] ?? null) || $module['type'] < 0 || $module['type'] > 5) {
        $error($context . ' type must be an integer from 0 to 5.');
    }
    if (!is_string($module['vendor'] ?? null)) {
        $error($context . ' vendor must be a string.');
    }
    if (!is_array($module['aliases'] ?? null) || array_filter($module['aliases'], static fn(mixed $alias): bool => !is_string($alias)) !== []) {
        $error($context . ' aliases must be an array of strings.');
    }
    foreach (['parentRequirements', 'childRequirements', 'implemented'] as $field) {
        if (!is_array($module[$field] ?? null)) {
            $error($context . ' ' . $field . ' must be an array of GUIDs.');
            continue;
        }
        foreach ($module[$field] as $guid) {
            if (!$isGuid($guid)) {
                $error($context . ' ' . $field . ' contains an invalid GUID.');
            }
        }
    }
    if (!$isUrl($module['url'] ?? null)) {
        $error($context . ' url must be empty or begin with http:// or https://.');
    }
    if (!is_string($module['prefix'] ?? null) || preg_match('/^[A-Za-z0-9]+$/D', $module['prefix']) !== 1) {
        $error($context . ' prefix must contain only letters and digits.');
    }

    $modulePhpPath = $moduleDirectory . DIRECTORY_SEPARATOR . 'module.php';
    if (!is_file($modulePhpPath)) {
        $error($directoryName . ' is missing module.php.');
        continue;
    }
    $moduleSource = (string) file_get_contents($modulePhpPath);
    if (preg_match('/\bclass\s+([A-Za-z][A-Za-z0-9_]*)\s+extends\s+IPSModuleStrict\b/', $moduleSource, $matches) !== 1) {
        $error($directoryName . '/module.php must declare a class extending IPSModuleStrict.');
        continue;
    }
    $expectedClass = str_replace(' ', '', (string) ($module['name'] ?? ''));
    if ($matches[1] !== $expectedClass) {
        $error($directoryName . '/module.php class ' . $matches[1] . ' must be ' . $expectedClass . '.');
    }
}

foreach ($allGuids as $index => $guid) {
    for ($otherIndex = $index + 1; $otherIndex < count($allGuids); $otherIndex++) {
        if ($guid['id'] === $allGuids[$otherIndex]['id']) {
            $error('Duplicate GUID in ' . $guid['source'] . ' and ' . $allGuids[$otherIndex]['source'] . '.');
        }
    }
}

if ($moduleCount === 0) {
    $error('No module.json files were found.');
}

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

echo 'Validated library metadata, ' . $moduleCount . ' module manifests, unique GUIDs and IPSModuleStrict class names.' . PHP_EOL;
