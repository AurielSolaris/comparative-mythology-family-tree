<?php

namespace App\Citron;

use Closure;
use JsonException;
use LogicException;
use stdClass;

/**
 * Native PHP implementation of the CitronDB file format (version 2).
 *
 * Mirrors src/citron.perl and bindings/c: it reads and writes the same
 * .citron files and follows the same rules, so Perl, C, Python and PHP can
 * use one database at the same time:
 *
 *   - readers take a shared lock and writers an exclusive lock on <db>.lock
 *   - every write goes to <db>.tmp, is fsynced, then renamed over the database
 *   - corrupt files and files from a newer version are refused
 *
 * Values are JSON. Methods that take a PHP value JSON-encode it; the *Raw
 * methods take raw input and follow the `set` command's rule (anything that
 * parses as JSON is stored as JSON, anything else as a plain string).
 *
 * A handle must not be shared between threads. Open one per process; the
 * file locks keep separate handles consistent.
 */
final class CitronDB
{
    public const FORMAT_VERSION = 2;

    private const MAGIC = 'CITRON';

    private const HEADER_SIZE = 14; // magic + version + record count

    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR;

    private readonly string $lockPath;

    /** The bytes the cached records were parsed from (null: nothing cached). */
    private ?string $raw = null;

    /** @var array<string, string> key => stored JSON text */
    private array $records = [];

    /** Lock currently held by this handle: null, LOCK_SH or LOCK_EX. */
    private ?int $held = null;

    private function __construct(private readonly string $path)
    {
        $this->lockPath = $path.'.lock';
    }

    /**
     * Opens a database, creating it if missing and removing a temp file left
     * by a crash.
     */
    public static function open(string $path = 'mainuser.citron'): self
    {
        $db = new self($path);

        $db->withLock(LOCK_EX, function () use ($db) {
            if (is_file($db->path.'.tmp')) {
                @unlink($db->path.'.tmp');
            }

            if (! file_exists($db->path)) {
                $db->save([]);
            }
        });

        return $db;
    }

    public function path(): string
    {
        return $this->path;
    }

    // --- public API ---------------------------------------------------------

    /** Inserts or replaces a key with a PHP value. */
    public function set(string $key, mixed $value): void
    {
        $this->write($key, self::encode($value), false);
    }

    /** Inserts or replaces a key with raw input (JSON or plain text). */
    public function setRaw(string $key, string $input): void
    {
        $this->write($key, self::encode(self::parseRaw($input)), false);
    }

    /** Replaces an existing key. Returns false if the key does not exist. */
    public function update(string $key, mixed $value): bool
    {
        return $this->write($key, self::encode($value), true);
    }

    public function updateRaw(string $key, string $input): bool
    {
        return $this->write($key, self::encode(self::parseRaw($input)), true);
    }

    /** Removes a key. Returns false if it did not exist. */
    public function delete(string $key): bool
    {
        return $this->modify(function (array &$records) use ($key) {
            if (! array_key_exists($key, $records)) {
                return false;
            }

            unset($records[$key]);

            return true;
        });
    }

    public function exists(string $key): bool
    {
        return $this->read(fn (array $records) => array_key_exists($key, $records));
    }

    /**
     * Looks up a key, optionally drilling into a dotted path ("address.city",
     * "tags.0", "tags.-1"). Returns [found, value]; a stored JSON null is
     * found. Objects come back as associative arrays.
     *
     * @return array{0: bool, 1: mixed}
     */
    public function lookup(string $key, ?string $path = null): array
    {
        $text = $this->read(fn (array $records) => $records[$key] ?? null);

        if ($text === null) {
            return [false, null];
        }

        [$found, $value] = self::resolvePath(self::decode($text, false), $path);

        return [$found, $found ? self::toArrays($value) : null];
    }

    /** The decoded value, or $default if the key (or path) is missing. */
    public function get(string $key, ?string $path = null, mixed $default = null): mixed
    {
        [$found, $value] = $this->lookup($key, $path);

        return $found ? $value : $default;
    }

    /** The value as JSON text, or null if missing. */
    public function getJson(string $key, ?string $path = null): ?string
    {
        if ($path === null || $path === '') {
            return $this->read(fn (array $records) => $records[$key] ?? null);
        }

        $text = $this->read(fn (array $records) => $records[$key] ?? null);

        if ($text === null) {
            return null;
        }

        [$found, $value] = self::resolvePath(self::decode($text, false), $path);

        return $found ? self::encode($value) : null;
    }

    /** Like getJson, but strings come back unquoted (as `get` prints them). */
    public function getText(string $key, ?string $path = null): ?string
    {
        $json = $this->getJson($key, $path);

        return $json === null ? null : self::storedToText($json);
    }

    /** @return list<string> every key, sorted */
    public function keys(): array
    {
        return $this->read(fn (array $records) => array_map('strval', array_keys($records)));
    }

    /** @return array<string, mixed> every record, decoded */
    public function all(): array
    {
        return $this->read(function (array $records) {
            $data = [];

            foreach ($records as $key => $text) {
                $data[(string) $key] = self::decode($text);
            }

            return $data;
        });
    }

    /** The whole database as one JSON object. */
    public function dump(): string
    {
        return $this->read(function (array $records) {
            $parts = [];

            foreach ($records as $key => $text) {
                $parts[] = self::encode((string) $key).':'.$text;
            }

            return '{'.implode(',', $parts).'}';
        });
    }

    /**
     * Merges a JSON object (text or PHP array) into the database in a single
     * write; existing keys are replaced. Returns the number of keys imported.
     */
    public function import(string|array $object): int
    {
        if (is_string($object)) {
            try {
                $object = json_decode($object, false, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw new CitronException('Invalid JSON: '.$e->getMessage(), CitronException::INVALID, $e);
            }

            if (! $object instanceof stdClass) {
                throw new CitronException('Import must be a JSON object at the top level', CitronException::INVALID);
            }
        }

        $encoded = [];

        foreach ($object as $key => $value) {
            $encoded[(string) $key] = self::encode($value);
        }

        $this->modify(function (array &$records) use ($encoded) {
            foreach ($encoded as $key => $text) {
                $records[$key] = $text;
            }
        });

        return count($encoded);
    }

    /** Writes every record to $file as pretty-printed JSON. Returns the record count. */
    public function exportJson(string $file): int
    {
        $data = $this->read(function (array $records) {
            $data = new stdClass;

            foreach ($records as $key => $text) {
                $data->{(string) $key} = self::decode($text, false);
            }

            return $data;
        });

        $json = json_encode(self::canonical($data), self::JSON_FLAGS | JSON_PRETTY_PRINT)."\n";

        if (@file_put_contents($file, $json) === false) {
            throw new CitronException("Could not write '{$file}'", CitronException::IO);
        }

        return count((array) $data);
    }

    /** Merges the JSON object in $file into the database. Returns the record count. */
    public function importJson(string $file): int
    {
        $content = @file_get_contents($file);

        if ($content === false) {
            throw new CitronException("Could not read '{$file}'", CitronException::IO);
        }

        return $this->import($content);
    }

    /**
     * Read-modify-write under an exclusive lock. $callback gets the records
     * by reference as [key => stored JSON text] (use encode()/decode()) and
     * its return value is passed back. The file is only rewritten when the
     * records changed.
     *
     * @template T
     *
     * @param  Closure(array<string, string>&): T  $callback
     * @return T
     */
    public function modify(Closure $callback): mixed
    {
        return $this->withLock(LOCK_EX, function () use ($callback) {
            $records = $this->load();
            $before = $records;

            $result = $callback($records);

            if ($records !== $before) {
                $this->save($records);
            }

            return $result;
        });
    }

    /**
     * Runs $callback on the records under a shared lock.
     *
     * @template T
     *
     * @param  Closure(array<string, string>): T  $callback
     * @return T
     */
    public function read(Closure $callback): mixed
    {
        return $this->withLock(LOCK_SH, fn () => $callback($this->load()));
    }

    // --- snapshots ----------------------------------------------------------

    /*
     * A snapshot is a full copy of the database file in "<db>.snapshots/",
     * stored as "<name>.citron". Unnamed snapshots are numbered 1, 2, 3, ...
     * from a counter that never goes down; named ones use letters and digits
     * with at least one letter. Same files and rules as citron.perl.
     */

    /** Saves the current data as a snapshot. Returns the snapshot's name. */
    public function snapshot(?string $name = null): string
    {
        if ($name !== null && ! (self::isSnapshotName($name) && preg_match('/[A-Za-z]/', $name))) {
            throw new CitronException(
                "Invalid snapshot name '{$name}': use letters and digits, with at least one letter",
                CitronException::INVALID,
            );
        }

        return $this->withLock(LOCK_EX, function () use ($name) {
            $records = $this->load(); // refuses a corrupt database
            $raw = $this->raw !== '' ? $this->raw : self::serialize($records);

            $dir = $this->snapshotDir();

            if (! is_dir($dir) && ! @mkdir($dir) && ! is_dir($dir)) {
                throw new CitronException("Could not create '{$dir}'", CitronException::IO);
            }

            if ($name !== null) {
                if (file_exists($this->snapshotPath($name))) {
                    throw new CitronException("Snapshot '{$name}' already exists", CitronException::INVALID);
                }
            } else {
                $name = $this->nextSnapshotNumber();
            }

            self::writeAtomic($this->snapshotPath($name), $raw);

            return $name;
        });
    }

    /** @return list<string> numbered snapshots in order, then named ones */
    public function snapshots(): array
    {
        return $this->withLock(LOCK_SH, fn () => $this->snapshotNames());
    }

    /**
     * Replaces the database with a snapshot (the snapshot is kept). Returns
     * false if there is no such snapshot. A corrupt snapshot is refused.
     */
    public function rollback(string $name): bool
    {
        if (! self::isSnapshotName($name)) {
            return false;
        }

        return $this->withLock(LOCK_EX, function () use ($name) {
            $path = $this->snapshotPath($name);

            if (! file_exists($path)) {
                return false;
            }

            $raw = self::readFile($path);

            try {
                $records = self::deserialize($raw);
            } catch (CitronException $e) {
                throw new CitronException("Snapshot '{$name}' is unusable: ".$e->getMessage(), $e->getCode(), $e);
            }

            self::writeAtomic($this->path, $raw);
            [$this->raw, $this->records] = [$raw, $records];

            return true;
        });
    }

    /** Deletes a snapshot. Returns false if it did not exist. */
    public function dropSnapshot(string $name): bool
    {
        if (! self::isSnapshotName($name)) {
            return false;
        }

        return $this->withLock(LOCK_EX, function () use ($name) {
            $path = $this->snapshotPath($name);

            if (! file_exists($path)) {
                return false;
            }

            if (! @unlink($path)) {
                throw new CitronException("Could not delete '{$path}'", CitronException::IO);
            }

            return true;
        });
    }

    private function snapshotDir(): string
    {
        return $this->path.'.snapshots';
    }

    private function snapshotPath(string $name): string
    {
        return $this->snapshotDir()."/{$name}.citron";
    }

    private static function isSnapshotName(string $name): bool
    {
        return preg_match('/\A[A-Za-z0-9]+\z/', $name) === 1;
    }

    /** Numbers first (by value), then names sorted. */
    private static function snapshotOrder(string $x, string $y): int
    {
        $xn = ctype_digit($x);
        $yn = ctype_digit($y);

        if ($xn !== $yn) {
            return $xn ? -1 : 1;
        }

        if ($xn && strlen($x) !== strlen($y)) {
            return strlen($x) <=> strlen($y);
        }

        return strcmp($x, $y) <=> 0;
    }

    /** @return list<string> */
    private function snapshotNames(): array
    {
        $dir = $this->snapshotDir();

        if (! is_dir($dir)) {
            return [];
        }

        $names = [];

        foreach (scandir($dir) ?: [] as $file) {
            if (preg_match('/\A([A-Za-z0-9]+)\.citron\z/', $file, $m)) {
                $names[] = $m[1];
            }
        }

        usort($names, self::snapshotOrder(...));

        return $names;
    }

    /** Next number: past the counter and past any numbered snapshot. Saves the counter. */
    private function nextSnapshotNumber(): string
    {
        $counter = $this->snapshotDir().'/counter';
        $last = '0';

        if (file_exists($counter)) {
            $last = self::readFile($counter);

            if (! ctype_digit($last)) {
                throw new CitronException("Corrupt snapshot counter '{$counter}'", CitronException::CORRUPT);
            }

            $last = ltrim(substr($last, 0, -1), '0').substr($last, -1);
        }

        foreach ($this->snapshotNames() as $name) {
            if (ctype_digit($name) && self::snapshotOrder($name, $last) > 0) {
                $last = $name;
            }
        }

        // Add one to a decimal string of any length.
        $i = strlen($last) - 1;
        while ($i >= 0 && $last[$i] === '9') {
            $last[$i] = '0';
            $i--;
        }
        $next = $i < 0 ? '1'.$last : substr_replace($last, (string) ((int) $last[$i] + 1), $i, 1);

        self::writeAtomic($counter, $next);

        return $next;
    }

    // --- encoding -----------------------------------------------------------

    /**
     * Canonical JSON as JSON::PP->canonical writes it: sorted keys, no
     * whitespace, raw UTF-8, only quote, backslash and control characters
     * escaped. (Float formatting can differ in rare cases such as 1e+20.)
     */
    public static function encode(mixed $value): string
    {
        try {
            return json_encode(self::canonical($value), self::JSON_FLAGS);
        } catch (JsonException $e) {
            throw new CitronException('Value cannot be stored as JSON: '.$e->getMessage(), CitronException::INVALID, $e);
        }
    }

    /** Decodes stored JSON; objects become associative arrays unless $assoc is false. */
    public static function decode(string $text, bool $assoc = true): mixed
    {
        try {
            return json_decode($text, $assoc, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new CitronException('Corrupt CitronDB value: '.$e->getMessage(), CitronException::CORRUPT, $e);
        }
    }

    /** Raw input: valid JSON is parsed as-is, anything else is a plain string. */
    private static function parseRaw(string $input): mixed
    {
        try {
            return json_decode($input, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return self::toUtf8($input);
        }
    }

    /** Sorts object keys recursively; lists stay lists, other arrays become objects. */
    private static function canonical(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            $value = (array) $value;
            ksort($value, SORT_STRING);

            return (object) array_map(self::canonical(...), $value);
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                return array_map(self::canonical(...), $value);
            }

            ksort($value, SORT_STRING);

            return (object) array_map(self::canonical(...), $value);
        }

        if (is_string($value)) {
            return self::toUtf8($value);
        }

        return $value;
    }

    /** Text as UTF-8: kept if already valid, otherwise read as Latin-1 (as Perl does). */
    private static function toUtf8(string $s): string
    {
        if (preg_match('//u', $s)) {
            return $s;
        }

        $out = '';
        for ($i = 0, $n = strlen($s); $i < $n; $i++) {
            $c = ord($s[$i]);
            $out .= $c < 0x80 ? $s[$i] : chr(0xC0 | $c >> 6).chr(0x80 | ($c & 0x3F));
        }

        return $out;
    }

    /** Readable form of a stored value: strings as-is, everything else as JSON. */
    private static function storedToText(string $text): string
    {
        if ($text === '' || $text[0] !== '"') {
            return $text;
        }

        if (! str_contains($text, '\\')) {
            return substr($text, 1, -1);
        }

        return self::decode($text);
    }

    /**
     * Walks a dotted path into a value decoded with objects as stdClass.
     *
     * @return array{0: bool, 1: mixed}
     */
    private static function resolvePath(mixed $value, ?string $path): array
    {
        if ($path === null || $path === '') {
            return [true, $value];
        }

        foreach (explode('.', $path) as $part) {
            if ($value instanceof stdClass && property_exists($value, $part)) {
                $value = $value->{$part};
            } elseif (is_array($value) && preg_match('/\A-?\d+\z/', $part)
                && (int) $part < count($value) && (int) $part >= -count($value)) {
                $value = $value[(int) $part < 0 ? count($value) + (int) $part : (int) $part];
            } else {
                return [false, null];
            }
        }

        return [true, $value];
    }

    private static function toArrays(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            $value = (array) $value;
        }

        return is_array($value) ? array_map(self::toArrays(...), $value) : $value;
    }

    // --- file format --------------------------------------------------------

    /** @param array<string, string> $records */
    private static function serialize(array $records): string
    {
        ksort($records, SORT_STRING);

        $binary = self::MAGIC.pack('NN', self::FORMAT_VERSION, count($records));

        foreach ($records as $key => $value) {
            $key = (string) $key;
            $binary .= pack('N', strlen($key)).$key.pack('N', strlen($value)).$value;
        }

        return $binary;
    }

    /** @return array<string, string> */
    private static function deserialize(string $binary): array
    {
        $total = strlen($binary);

        if ($total === 0) {
            return [];
        }

        if ($total < self::HEADER_SIZE || ! str_starts_with($binary, self::MAGIC)) {
            throw new CitronException('Not a CitronDB file (bad header)', CitronException::CORRUPT);
        }

        $offset = strlen(self::MAGIC);

        $take = function (int $len, string $what) use ($binary, $total, &$offset): string {
            if ($offset + $len > $total) {
                throw new CitronException("Corrupt CitronDB file: truncated while reading {$what}", CitronException::CORRUPT);
            }

            $chunk = substr($binary, $offset, $len);
            $offset += $len;

            return $chunk;
        };
        $int = fn (string $what): int => unpack('N', $take(4, $what))[1];

        $version = $int('version');

        if ($version > self::FORMAT_VERSION) {
            throw new CitronException(
                "CitronDB file version {$version} is newer than supported version ".self::FORMAT_VERSION,
                CitronException::VERSION,
            );
        }

        if ($version < 1) {
            throw new CitronException("Corrupt CitronDB file: invalid version {$version}", CitronException::CORRUPT);
        }

        $records = [];
        $count = $int('record count');

        for ($i = 0; $i < $count; $i++) {
            $key = $take($int('key length'), 'key');
            $value = $take($int('value length'), 'value');

            // v1 stored raw strings; v2 stores JSON
            $records[$key] = $version === 1 ? self::encode(self::toUtf8($value)) : $value;
        }

        if ($offset !== $total) {
            throw new CitronException(
                'Corrupt CitronDB file: '.($total - $offset).' unexpected trailing bytes',
                CitronException::CORRUPT,
            );
        }

        return $records;
    }

    // --- storage ------------------------------------------------------------

    /**
     * Runs $callback while holding a lock on "<db>.lock". A separate lock file
     * is used because writes replace the database file via rename. On Windows
     * PHP's flock() locks from byte 0, overlapping the range Perl and the C
     * binding lock, so all of them exclude each other.
     */
    private function withLock(int $mode, Closure $callback): mixed
    {
        if ($this->held !== null) {
            if ($mode === LOCK_EX && $this->held === LOCK_SH) {
                throw new LogicException('Cannot write to CitronDB inside a read callback');
            }

            return $callback();
        }

        $handle = @fopen($this->lockPath, 'a');

        if ($handle === false) {
            throw new CitronException("Could not open lock file '{$this->lockPath}'", CitronException::IO);
        }

        if (! flock($handle, $mode)) {
            fclose($handle);

            throw new CitronException("Could not lock '{$this->lockPath}'", CitronException::IO);
        }

        $this->held = $mode;

        try {
            return $callback();
        } finally {
            $this->held = null;
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * The records as [key => stored JSON text]. Parsing is skipped when the
     * file's bytes are identical to the last ones parsed, so writes by other
     * processes are always seen.
     *
     * @return array<string, string>
     */
    private function load(): array
    {
        $raw = file_exists($this->path) ? self::readFile($this->path) : '';

        if ($raw !== $this->raw) {
            $this->records = self::deserialize($raw);
            $this->raw = $raw;
        }

        return $this->records;
    }

    /** @param array<string, string> $records */
    private function save(array $records): void
    {
        ksort($records, SORT_STRING);

        $raw = self::serialize($records);
        self::writeAtomic($this->path, $raw);

        [$this->raw, $this->records] = [$raw, $records];
    }

    private function write(string $key, string $encoded, bool $onlyExisting): bool
    {
        return $this->modify(function (array &$records) use ($key, $encoded, $onlyExisting) {
            if ($onlyExisting && ! array_key_exists($key, $records)) {
                return false;
            }

            $records[$key] = $encoded;

            return true;
        });
    }

    private static function readFile(string $path): string
    {
        $data = @file_get_contents($path);

        if ($data === false) {
            throw new CitronException("Could not open file '{$path}'", CitronException::IO);
        }

        return $data;
    }

    /** Writes "<path>.tmp", fsyncs it, then renames it over $path. */
    private static function writeAtomic(string $path, string $data): void
    {
        $tmp = $path.'.tmp';
        $handle = @fopen($tmp, 'wb');

        $ok = $handle !== false
            && fwrite($handle, $data) === strlen($data)
            && fflush($handle)
            && fsync($handle);

        if ($handle !== false) {
            $ok = fclose($handle) && $ok;
        }

        if (! $ok || ! self::replaceFile($tmp, $path)) {
            @unlink($tmp);

            throw new CitronException("Could not write '{$path}'", CitronException::IO);
        }
    }

    /**
     * Renames $from over $to. On Windows the rename fails while another
     * process (often a virus scanner looking at the fresh file) briefly has
     * either file open, so it is retried for up to about half a second.
     */
    private static function replaceFile(string $from, string $to): bool
    {
        for ($attempt = 1; ; $attempt++) {
            if (@rename($from, $to)) {
                return true;
            }

            if (PHP_OS_FAMILY !== 'Windows' || $attempt === 10) {
                return false;
            }

            usleep(10_000 * $attempt);
        }
    }
}
