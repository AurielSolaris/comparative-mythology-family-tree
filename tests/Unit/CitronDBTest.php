<?php

namespace Tests\Unit;

use Citron\CitronDB;
use Citron\CitronException;
use PHPUnit\Framework\TestCase;

class CitronDBTest extends TestCase
{
    private string $directory;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/citron-test-'.bin2hex(random_bytes(6));
        mkdir($this->directory);
        $this->path = $this->directory.'/test.citron';
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);

        parent::tearDown();
    }

    public function test_open_creates_an_empty_database_in_the_documented_format(): void
    {
        CitronDB::open($this->path);

        $this->assertSame('CITRON'.pack('NN', 2, 0), file_get_contents($this->path));
    }

    public function test_records_are_written_sorted_with_big_endian_lengths_and_canonical_json(): void
    {
        $db = CitronDB::open($this->path);
        $db->set('b', ['z' => 1, 'a' => [true, null]]);
        $db->set('a', 'héllo/🍋');

        $expected = 'CITRON'.pack('NN', 2, 2)
            .pack('N', 1).'a'.pack('N', strlen('"héllo/🍋"')).'"héllo/🍋"'
            .pack('N', 1).'b'.pack('N', strlen('{"a":[true,null],"z":1}')).'{"a":[true,null],"z":1}';

        $this->assertSame($expected, file_get_contents($this->path));
    }

    public function test_crud_operations(): void
    {
        $db = CitronDB::open($this->path);

        $db->set('god', ['name' => 'Zeus', 'domains' => ['sky', 'thunder']]);

        $this->assertTrue($db->exists('god'));
        $this->assertSame(['domains' => ['sky', 'thunder'], 'name' => 'Zeus'], $db->get('god'));
        $this->assertTrue($db->update('god', 'Jupiter'));
        $this->assertFalse($db->update('missing', 1));
        $this->assertSame('Jupiter', $db->get('god'));
        $this->assertTrue($db->delete('god'));
        $this->assertFalse($db->delete('god'));
        $this->assertSame('fallback', $db->get('god', default: 'fallback'));
    }

    public function test_raw_input_is_stored_as_json_when_it_parses_and_as_a_string_otherwise(): void
    {
        $db = CitronDB::open($this->path);

        $db->setRaw('json', '{"b":1,"a":[1,2]}');
        $db->setRaw('text', 'not json');

        $this->assertSame('{"a":[1,2],"b":1}', $db->getJson('json'));
        $this->assertSame('"not json"', $db->getJson('text'));
        $this->assertSame('not json', $db->getText('text'));
    }

    public function test_dotted_paths_drill_into_objects_and_lists(): void
    {
        $db = CitronDB::open($this->path);
        $db->set('odin', ['ravens' => ['Huginn', 'Muninn'], 'realm' => ['name' => 'Asgard']]);

        $this->assertSame('Asgard', $db->get('odin', 'realm.name'));
        $this->assertSame('Muninn', $db->get('odin', 'ravens.-1'));
        $this->assertSame([false, null], $db->lookup('odin', 'ravens.5'));
        $this->assertSame('"Huginn"', $db->getJson('odin', 'ravens.0'));
    }

    public function test_numeric_string_keys_stay_strings(): void
    {
        $db = CitronDB::open($this->path);
        $db->set('123', 'numeric');
        $db->set('abc', 'text');

        $this->assertSame(['123', 'abc'], $db->keys());
        $this->assertSame('{"123":"numeric","abc":"text"}', $db->dump());
    }

    public function test_writes_from_another_handle_are_seen(): void
    {
        $first = CitronDB::open($this->path);
        $second = CitronDB::open($this->path);

        $this->assertNull($first->get('key'));

        $second->set('key', 'value');

        $this->assertSame('value', $first->get('key'));
    }

    public function test_import_merges_an_object_in_one_write(): void
    {
        $db = CitronDB::open($this->path);
        $db->set('keep', 1);

        $this->assertSame(2, $db->import('{"a":1,"keep":2}'));
        $this->assertSame(['a' => 1, 'keep' => 2], $db->all());

        $this->expectException(CitronException::class);
        $db->import('[1,2]');
    }

    public function test_version_one_files_are_read_as_strings(): void
    {
        file_put_contents($this->path, 'CITRON'.pack('NN', 1, 1).pack('N', 1).'k'.pack('N', 5).'{"a"}');

        $this->assertSame('{"a"}', CitronDB::open($this->path)->get('k'));
    }

    public function test_corrupt_and_newer_files_are_refused(): void
    {
        file_put_contents($this->path, 'CITRON'.pack('NN', 3, 0));

        try {
            CitronDB::open($this->path)->keys();
            $this->fail('A newer file version was accepted.');
        } catch (CitronException $e) {
            $this->assertSame(CitronException::VERSION, $e->getCode());
        }

        file_put_contents($this->path, 'CITRON'.pack('NN', 2, 1).pack('N', 10).'short');

        $this->expectExceptionCode(CitronException::CORRUPT);
        CitronDB::open($this->path)->keys();
    }

    public function test_open_removes_a_temp_file_left_by_a_crash(): void
    {
        file_put_contents($this->path.'.tmp', 'partial');

        CitronDB::open($this->path);

        $this->assertFileDoesNotExist($this->path.'.tmp');
    }

    public function test_snapshots_can_be_created_listed_rolled_back_and_dropped(): void
    {
        $db = CitronDB::open($this->path);
        $db->set('state', 'first');

        $this->assertSame('1', $db->snapshot());
        $this->assertSame('beforeChange', $db->snapshot('beforeChange'));

        $db->set('state', 'second');

        $this->assertSame('2', $db->snapshot());
        $this->assertSame(['1', '2', 'beforeChange'], $db->snapshots());
        $this->assertTrue($db->rollback('1'));
        $this->assertSame('first', $db->get('state'));
        $this->assertTrue($db->dropSnapshot('2'));
        $this->assertSame('3', $db->snapshot());
        $this->assertFalse($db->rollback('missing'));
    }

    private function removeDirectory(string $directory): void
    {
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory.'/'.$entry;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($directory);
    }
}
