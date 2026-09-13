<?php

use Symfony\Component\Process\Process;

/**
 * @return array{exit_code: int, output: string, error_output: string, notes: string, github_output: string}
 */
function executeAndroidReleaseMetadata(string $tag, string $changelog): array
{
    $temporaryDirectory = sys_get_temp_dir().'/laundryhub-release-'.bin2hex(random_bytes(8));
    $changelogPath = $temporaryDirectory.'/CHANGELOG.md';
    $releaseNotesPath = $temporaryDirectory.'/release-notes.md';
    $githubOutputPath = $temporaryDirectory.'/github-output.txt';

    mkdir($temporaryDirectory, 0700, true);
    file_put_contents($changelogPath, $changelog);
    touch($githubOutputPath);

    $process = new Process(
        [
            dirname(__DIR__, 2).'/.github/scripts/prepare-android-release.sh',
            $tag,
            $changelogPath,
            $releaseNotesPath,
        ],
        dirname(__DIR__, 2),
        ['GITHUB_OUTPUT' => $githubOutputPath],
    );
    $process->run();

    $result = [
        'exit_code' => $process->getExitCode() ?? 1,
        'output' => $process->getOutput(),
        'error_output' => $process->getErrorOutput(),
        'notes' => file_exists($releaseNotesPath) ? (string) file_get_contents($releaseNotesPath) : '',
        'github_output' => (string) file_get_contents($githubOutputPath),
    ];

    foreach ([$changelogPath, $releaseNotesPath, $githubOutputPath] as $path) {
        if (file_exists($path)) {
            unlink($path);
        }
    }

    rmdir($temporaryDirectory);

    return $result;
}

it('prepares Android version metadata and release notes from a matching changelog entry', function (): void {
    $result = executeAndroidReleaseMetadata('v1.2.3', <<<'MARKDOWN'
# Changelog

## [Unreleased]

- Pending work.

## [1.2.3] - 2026-09-13

### Added

- Signed Android artifacts.

## [1.2.2] - 2026-09-01

- Previous release.
MARKDOWN);

    expect($result['exit_code'])->toBe(0)
        ->and($result['output'])->toContain('Prepared Android release 1.2.3 (1002003)')
        ->and($result['notes'])->toBe("### Added\n\n- Signed Android artifacts.\n")
        ->and($result['github_output'])->toContain("version_name=1.2.3\n")
        ->and($result['github_output'])->toContain("version_code=1002003\n")
        ->and($result['github_output'])->toContain("release_date=2026-09-13\n");
});

it('rejects tags outside the stable semantic version format', function (): void {
    $result = executeAndroidReleaseMetadata('release-1.2.3', '# Changelog');

    expect($result['exit_code'])->toBe(65)
        ->and($result['error_output'])->toContain('Tag must match vMAJOR.MINOR.PATCH');
});

it('rejects semantic version tags with leading zeroes', function (): void {
    $result = executeAndroidReleaseMetadata('v01.2.3', '# Changelog');

    expect($result['exit_code'])->toBe(65)
        ->and($result['error_output'])->toContain('Tag must match vMAJOR.MINOR.PATCH');
});

it('rejects releases missing their changelog entry', function (): void {
    $result = executeAndroidReleaseMetadata('v1.2.3', <<<'MARKDOWN'
# Changelog

## [Unreleased]

- Pending work.
MARKDOWN);

    expect($result['exit_code'])->toBe(67)
        ->and($result['error_output'])->toContain('CHANGELOG.md must contain: ## [1.2.3] - YYYY-MM-DD');
});

it('rejects releases with an empty changelog entry', function (): void {
    $result = executeAndroidReleaseMetadata('v1.2.3', <<<'MARKDOWN'
# Changelog

## [1.2.3] - 2026-09-13

## [1.2.2] - 2026-09-01

- Previous release.
MARKDOWN);

    expect($result['exit_code'])->toBe(67)
        ->and($result['error_output'])->toContain('Release 1.2.3 must have non-empty notes');
});

it('rejects releases with whitespace-only changelog notes', function (): void {
    $changelog = implode("\n", [
        '# Changelog',
        '',
        '## [1.2.3] - 2026-09-13',
        '   ',
        "\t",
        '## [1.2.2] - 2026-09-01',
        '',
        '- Previous release.',
    ]);

    $result = executeAndroidReleaseMetadata('v1.2.3', $changelog);

    expect($result['exit_code'])->toBe(67)
        ->and($result['error_output'])->toContain('Release 1.2.3 must have non-empty notes');
});
