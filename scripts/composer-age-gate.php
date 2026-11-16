<?php

declare(strict_types=1);

/**
 * Composer release-age gate.
 *
 * Composer has no native "minimum release age" setting (pnpm does, see
 * pnpm-workspace.yaml), so this script enforces one for PHP packages to
 * reduce exposure to freshly published, compromised releases.
 *
 *   php scripts/composer-age-gate.php check            # exit 1 if composer.lock has a too-new package
 *   php scripts/composer-age-gate.php update [args]    # composer update, then downgrade too-new packages until clean
 *
 * Age defaults to 7 days; override with COMPOSER_MIN_RELEASE_AGE_DAYS.
 * Packages listed in COMPOSER_AGE_GATE_ALLOW (comma separated) are exempt.
 */
$root = dirname(__DIR__);
$days = (int) (getenv('COMPOSER_MIN_RELEASE_AGE_DAYS') ?: 7);
$cutoff = time() - ($days * 86400);
$allow = array_filter(array_map('trim', explode(',', (string) getenv('COMPOSER_AGE_GATE_ALLOW'))));
$mode = $argv[1] ?? 'check';
$passthrough = array_slice($argv, 2);

function lockedPackages(string $root): array
{
    $lock = json_decode((string) file_get_contents($root.'/composer.lock'), true, flags: JSON_THROW_ON_ERROR);

    return [...$lock['packages'], ...$lock['packages-dev']];
}

function tooNew(array $packages, int $cutoff, array $allow): array
{
    return array_values(array_filter($packages, function (array $p) use ($cutoff, $allow): bool {
        if (in_array($p['name'], $allow, true) || ! isset($p['time'])) {
            return false;
        }

        return strtotime($p['time']) > $cutoff;
    }));
}

/**
 * Packagist p2 metadata is "minified": each version only lists the keys that
 * differ from the previous entry ("__unset" removes one). Expand it.
 */
function expandMinified(array $versions): array
{
    $expanded = [];
    $previous = [];
    foreach ($versions as $version) {
        foreach ($version as $key => $value) {
            if ($value === '__unset') {
                unset($previous[$key]);
            } else {
                $previous[$key] = $value;
            }
        }
        $expanded[] = $previous;
    }

    return $expanded;
}

/** Newest stable version of $name released before $cutoff, preferring the same major as $current. */
function newestAllowed(string $name, string $current, int $cutoff): ?string
{
    static $cache = [];
    $cache[$name] ??= expandMinified(json_decode(
        (string) @file_get_contents("https://repo.packagist.org/p2/{$name}.json"),
        true
    )['packages'][$name] ?? []);

    $candidates = array_filter($cache[$name], fn (array $v): bool => isset($v['time'])
        && strtotime($v['time']) <= $cutoff
        && preg_match('/^v?\d+(\.\d+)*$/', $v['version']));

    usort($candidates, fn ($a, $b) => version_compare(ltrim($b['version'], 'v'), ltrim($a['version'], 'v')));

    $major = explode('.', ltrim($current, 'v'))[0];
    foreach ($candidates as $c) {
        if (explode('.', ltrim($c['version'], 'v'))[0] === $major) {
            return $c['version'];
        }
    }

    return $candidates[0]['version'] ?? null;
}

function report(array $violations, int $days): void
{
    foreach ($violations as $p) {
        fwrite(STDERR, sprintf("  %-50s %-14s released %s (< %d days)\n", $p['name'], $p['version'], $p['time'], $days));
    }
}

if ($mode === 'check') {
    $violations = tooNew(lockedPackages($root), $cutoff, $allow);
    if ($violations === []) {
        echo "composer.lock: all packages are at least {$days} days old.\n";
        exit(0);
    }
    fwrite(STDERR, "composer.lock contains packages newer than {$days} days:\n");
    report($violations, $days);
    exit(1);
}

if ($mode !== 'update') {
    fwrite(STDERR, "Usage: composer-age-gate.php check|update [composer update args]\n");
    exit(2);
}

$pins = [];
for ($round = 1; $round <= 8; $round++) {
    // Upper bounds (not exact pins) so the solver can still find a mutually compatible set.
    $with = array_map(fn ($n, $c) => '--with='.escapeshellarg("{$n}:".implode(',', $c)), array_keys($pins), $pins);
    $cmd = implode(' ', ['composer update --no-interaction --no-scripts', ...array_map('escapeshellarg', $passthrough), ...$with]);
    echo "\n[age-gate] round {$round}: {$cmd}\n";
    passthru($cmd, $code);
    if ($code !== 0) {
        exit($code);
    }

    $violations = tooNew(lockedPackages($root), $cutoff, $allow);
    if ($violations === []) {
        echo "\n[age-gate] composer.lock is clean (every package >= {$days} days old).\n";
        if ($pins !== []) {
            echo "[age-gate] held back:\n";
            foreach ($pins as $n => $c) {
                echo '  '.$n.' '.implode(',', $c)."\n";
            }
        }
        exit(0);
    }

    report($violations, $days);
    foreach ($violations as $p) {
        $version = newestAllowed($p['name'], $p['version'], $cutoff);
        if ($version === null) {
            fwrite(STDERR, "[age-gate] no release of {$p['name']} is older than {$days} days; add it to COMPOSER_AGE_GATE_ALLOW to accept it.\n");
            exit(1);
        }
        // A repeat offender (e.g. a fresh backport on an older line) gets excluded outright.
        $pins[$p['name']] = isset($pins[$p['name']])
            ? [...$pins[$p['name']], '<'.ltrim($p['version'], 'v')]
            : ['<='.ltrim($version, 'v')];
    }
}

fwrite(STDERR, "[age-gate] could not converge after 8 rounds.\n");
exit(1);
