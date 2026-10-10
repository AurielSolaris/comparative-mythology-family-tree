<?php

namespace Tests\Feature;

use App\Cache\CitronStore;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CitronCacheStoreTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = storage_path('framework/testing/citron-'.bin2hex(random_bytes(6)));

        config(['cache.stores.citron.path' => $this->directory.'/cache.citron']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_the_citron_store_is_registered(): void
    {
        $this->assertInstanceOf(CitronStore::class, $this->cache()->getStore());
        $this->assertFileExists($this->directory.'/cache.citron');
    }

    public function test_values_round_trip(): void
    {
        $cache = $this->cache();

        $cache->put('pantheon', ['greek' => ['Zeus', 'Hera'], 'count' => 2], 60);
        $cache->forever('binary', "\xff\x00\xfe");

        $this->assertSame(['greek' => ['Zeus', 'Hera'], 'count' => 2], $cache->get('pantheon'));
        $this->assertSame("\xff\x00\xfe", $cache->get('binary'));
        $this->assertSame(['pantheon' => ['greek' => ['Zeus', 'Hera'], 'count' => 2], 'missing' => null], $cache->many(['pantheon', 'missing']));
    }

    public function test_items_expire(): void
    {
        $cache = $this->cache();

        $cache->put('short', 'lived', 10);

        $this->travel(11)->seconds();

        $this->assertNull($cache->get('short'));
    }

    public function test_add_only_stores_missing_or_expired_items(): void
    {
        $cache = $this->cache();

        $this->assertTrue($cache->add('key', 'first', 10));
        $this->assertFalse($cache->add('key', 'second', 10));

        $this->travel(11)->seconds();

        $this->assertTrue($cache->add('key', 'third', 10));
        $this->assertSame('third', $cache->get('key'));
    }

    public function test_increment_and_decrement_keep_the_expiry(): void
    {
        $cache = $this->cache();

        $cache->put('counter', 5, 10);

        $this->assertSame(8, $cache->increment('counter', 3));
        $this->assertSame(6, $cache->decrement('counter', 2));

        $this->travel(11)->seconds();

        $this->assertNull($cache->get('counter'));
        $this->assertSame(1, $cache->increment('fresh'));
    }

    public function test_touch_extends_an_items_lifetime(): void
    {
        $cache = $this->cache();

        $cache->put('key', 'value', 10);
        $this->assertTrue($cache->touch('key', 100));

        $this->travel(50)->seconds();

        $this->assertSame('value', $cache->get('key'));
    }

    public function test_forget_and_flush(): void
    {
        $cache = $this->cache();

        $cache->forever('a', 1);
        $cache->forever('b', 2);

        $this->assertTrue($cache->forget('a'));
        $this->assertNull($cache->get('a'));
        $this->assertTrue($cache->flush());
        $this->assertNull($cache->get('b'));
    }

    public function test_classes_are_not_unserialized_unless_allowed(): void
    {
        $this->cache()->forever('object', new \ArrayObject([1]));

        $this->assertInstanceOf(\__PHP_Incomplete_Class::class, $this->cache()->get('object'));
    }

    public function test_locks_are_exclusive_and_only_released_by_their_owner(): void
    {
        $cache = $this->cache();

        $lock = $cache->lock('job', 10);
        $other = $cache->lock('job', 10);

        $this->assertTrue($lock->get());
        $this->assertFalse($other->get());
        $this->assertFalse($other->release());
        $this->assertTrue($lock->refresh(20));
        $this->assertTrue($lock->release());
        $this->assertTrue($other->get());

        $cache->restoreLock('job', $other->owner())->forceRelease();

        $this->assertFalse($other->isLocked());
    }

    private function cache(): Repository
    {
        return Cache::store('citron');
    }
}
