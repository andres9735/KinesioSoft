<?php

namespace Tests\Support;

use App\Models\User;
use Filament\Facades\Filament;

trait ActsAsKine
{
    protected function actingAsKine(): User
    {
        /** @var User $kine */
        $kine = User::factory()->createOne();

        if (method_exists($kine, 'assignRole')) {
            $kine->assignRole('Kinesiologa');
        }

        $this->actingAs($kine, 'web');
        Filament::auth()->login($kine);

        return $kine;
    }
}
