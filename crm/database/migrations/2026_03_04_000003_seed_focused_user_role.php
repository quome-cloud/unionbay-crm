<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Only create if it doesn't exist
        if (DB::table('roles')->where('name', 'Focused User')->exists()) {
            return;
        }

        DB::table('roles')->insert([
            'name'            => 'Focused User',
            'description'     => 'Can only see their own contacts, deals, activities, and action stream. No access to team stream, reports, or admin settings.',
            'permission_type' => 'custom',
            'permissions'     => json_encode([
                'dashboard',
                'leads',
                'leads.create',
                'leads.view',
                'leads.edit',
                'leads.delete',
                'mail',
                'mail.inbox',
                'mail.draft',
                'mail.outbox',
                'mail.sent',
                'mail.trash',
                'mail.compose',
                'mail.view',
                'mail.edit',
                'mail.delete',
                'activities',
                'activities.create',
                'activities.edit',
                'activities.delete',
                'contacts',
                'contacts.persons',
                'contacts.persons.create',
                'contacts.persons.edit',
                'contacts.persons.delete',
                'contacts.persons.view',
                'contacts.organizations',
                'contacts.organizations.create',
                'contacts.organizations.edit',
                'contacts.organizations.delete',
                'products',
                'products.view',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'Focused User')->delete();
    }
};
