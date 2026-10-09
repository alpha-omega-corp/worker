<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    /**
     * This laptop's admin, test@alphomega.org / test, so a site's /admin opens without an invite link. Local only: a
     * known password must never reach a server, where admins come from site:admin --invite.
     */
    public function run(): void
    {
        // is_admin is the base's column: a site that dropped the base has no admin to make.
        if (! app()->isLocal() || ! Schema::hasColumn('users', 'is_admin')) {
            return;
        }

        // forceFill: the base's User has is_admin neither fillable nor cast.
        User::query()->firstOrNew(['email' => 'test@alphomega.org'])
            ->forceFill(['name' => 'Test', 'password' => Hash::make('test'), 'is_admin' => true])
            ->save();
    }
}
