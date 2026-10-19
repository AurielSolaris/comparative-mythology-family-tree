<?php

namespace Tests\Unit;

use App\Enums\RelationKind;
use App\Enums\RelationRole;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RelationRoleTest extends TestCase
{
    /**
     * @return array<string, array{RelationRole}>
     */
    public static function roles(): array
    {
        return collect(RelationRole::cases())->mapWithKeys(fn (RelationRole $role) => [$role->value => [$role]])->all();
    }

    #[DataProvider('roles')]
    public function test_each_role_is_read_back_from_the_side_it_is_stored_on(RelationRole $role): void
    {
        $kind = $role->kind();

        $this->assertSame($role, RelationRole::for($kind, $role->isSuperior()));
        $this->assertSame($kind, $role->inverse()->kind());
        $this->assertSame($role, $role->inverse()->inverse());
    }

    #[DataProvider('roles')]
    public function test_directional_roles_have_opposite_sides_and_symmetric_ones_read_the_same_both_ways(RelationRole $role): void
    {
        if ($role->kind()->isSymmetric()) {
            $this->assertSame($role, $role->inverse());
            $this->assertSame($role, RelationRole::for($role->kind(), true));
            $this->assertSame($role, RelationRole::for($role->kind(), false));
        } else {
            $this->assertNotSame($role->isSuperior(), $role->inverse()->isSuperior());
        }
    }

    public function test_every_kind_can_be_chosen_from_some_role(): void
    {
        $kinds = array_values(array_unique(array_map(fn (RelationRole $role) => $role->kind()->value, RelationRole::cases())));

        $this->assertSame(array_column(RelationKind::cases(), 'value'), $kinds);
    }
}
