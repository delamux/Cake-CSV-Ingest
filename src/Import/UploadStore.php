<?php
declare(strict_types=1);

namespace CsvIngest\Import;

use Cake\Utility\Text;
use CsvIngest\Exception\UnreadableSourceException;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use function Cake\I18n\__d;

/**
 * Persists uploaded CSV files between wizard steps.
 *
 * Files are addressed by an opaque UUID token (never by a client-supplied
 * path), normalized to UTF-8 without BOM, and purged after a TTL.
 */
final class UploadStore
{
    private const TOKEN_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    /**
     * @param string $directory Storage directory (with trailing separator).
     * @param list<string> $encodings Candidate source encodings, most likely first.
     * @param int $ttl Seconds before a stored file is purged.
     */
    public function __construct(
        private readonly string $directory,
        private readonly array $encodings = ['UTF-8', 'Windows-1252', 'ISO-8859-1'],
        private readonly int $ttl = 86400,
    ) {
    }

    /**
     * Store an uploaded file and return its token.
     *
     * @param \Psr\Http\Message\UploadedFileInterface $file Upload.
     * @return string Token.
     */
    public function store(UploadedFileInterface $file): string
    {
        return $this->storeContents((string)$file->getStream());
    }

    /**
     * Store raw CSV contents and return a token.
     *
     * @param string $contents Raw bytes in any supported encoding.
     * @return string Token.
     */
    public function storeContents(string $contents): string
    {
        $this->ensureDirectory();
        $this->purgeExpired();

        $token = Text::uuid();
        $path = $this->path($token, mustExist: false);
        if (file_put_contents($path, $this->toUtf8($contents), LOCK_EX) === false) {
            throw new RuntimeException(sprintf('Could not write CSV upload to "%s".', $path));
        }
        chmod($path, 0640);

        return $token;
    }

    /**
     * @param string $token Token from {@see self::store()}.
     * @param bool $mustExist Throw when the file does not exist.
     * @return string Absolute path.
     * @throws \CsvIngest\Exception\UnreadableSourceException
     */
    public function path(string $token, bool $mustExist = true): string
    {
        if (preg_match(self::TOKEN_PATTERN, $token) !== 1) {
            throw new UnreadableSourceException(__d('csv_ingest', 'Invalid upload reference.'));
        }
        $path = $this->directory . $token . '.csv';
        if ($mustExist && !is_file($path)) {
            throw new UnreadableSourceException(__d('csv_ingest', 'The uploaded file is no longer available.'));
        }

        return $path;
    }

    /**
     * @param string $token Token.
     * @return void
     */
    public function discard(string $token): void
    {
        try {
            $path = $this->path($token, mustExist: false);
        } catch (UnreadableSourceException) {
            return;
        }
        if (is_file($path)) {
            unlink($path);
        }
    }

    /**
     * Delete stored files older than the TTL.
     *
     * @return int Number of deleted files.
     */
    public function purgeExpired(): int
    {
        $deleted = 0;
        $threshold = time() - $this->ttl;
        foreach (glob($this->directory . '*.csv') ?: [] as $file) {
            if (filemtime($file) < $threshold && unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * @param string $contents Raw bytes.
     * @return string UTF-8 without BOM, LF/CRLF untouched.
     */
    private function toUtf8(string $contents): string
    {
        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            return substr($contents, 3);
        }
        if (mb_check_encoding($contents, 'UTF-8')) {
            return $contents;
        }
        $candidates = array_values(array_filter(
            $this->encodings,
            static fn(string $e): bool => strtoupper($e) !== 'UTF-8',
        ));
        $detected = $candidates === [] ? false : mb_detect_encoding($contents, $candidates, true);

        $converted = mb_convert_encoding($contents, 'UTF-8', $detected ?: 'Windows-1252');
        if ($converted === false) {
            throw new UnreadableSourceException(__d('csv_ingest', 'The file encoding is not supported.'));
        }

        return $converted;
    }

    /**
     * @return void
     */
    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0750, true) && !is_dir($this->directory)) {
            throw new RuntimeException(sprintf('Could not create directory "%s".', $this->directory));
        }
    }
}
