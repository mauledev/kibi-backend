<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantSchoolSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPermissionCategories();
        $this->seedPermissions();
        $this->seedRoles();
        $this->seedRolePermissions();
    }

    private function seedPermissionCategories(): void
    {
        $categories = [
            // Tenant scope — tenant-level operational roles
            ['scope' => 'tenant', 'name' => 'finance'],
            ['scope' => 'tenant', 'name' => 'hr'],
            // Permissions readable by any tenant/* role
            ['scope' => 'tenant', 'name' => 'common'],

            // School scope — one category per school role type
            ['scope' => 'school', 'name' => 'director'],
            ['scope' => 'school', 'name' => 'academic_coordinator'],
            ['scope' => 'school', 'name' => 'prefect'],
            ['scope' => 'school', 'name' => 'finance'],
            ['scope' => 'school', 'name' => 'hr'],
            ['scope' => 'school', 'name' => 'teacher'],
            ['scope' => 'school', 'name' => 'student'],
            ['scope' => 'school', 'name' => 'tutor'],
            // Permissions readable by any school/* role
            ['scope' => 'school', 'name' => 'common'],
        ];

        foreach ($categories as $category) {
            DB::table('permission_categories')->insertOrIgnore([
                'uuid' => (string) Str::uuid(),
                'scope' => $category['scope'],
                'name' => $category['name'],
                'created_at' => now(),
            ]);
        }
    }

    private function seedPermissions(): void
    {
        $catId = fn(string $scope, string $name): int => (int) DB::table('permission_categories')
            ->where('scope', $scope)
            ->where('name', $name)
            ->value('id');

        $permissions = [
            // school/director
            ['scope' => 'school', 'category' => 'director', 'name' => 'Manage permissions',  'slug' => 'manage.permissions'],
            ['scope' => 'school', 'category' => 'director', 'name' => 'Create custom roles', 'slug' => 'custom_role.create'],
            ['scope' => 'school', 'category' => 'director', 'name' => 'Update custom roles', 'slug' => 'custom_role.update'],
            ['scope' => 'school', 'category' => 'director', 'name' => 'Delete custom roles', 'slug' => 'custom_role.delete'],
            ['scope' => 'school', 'category' => 'director', 'name' => 'View schools',        'slug' => 'school.view'],
            ['scope' => 'school', 'category' => 'director', 'name' => 'Update schools',      'slug' => 'school.update'],
            ['scope' => 'school', 'category' => 'director', 'name' => 'View roles',          'slug' => 'role.view'],
            ['scope' => 'school', 'category' => 'director', 'name' => 'Assign roles',        'slug' => 'role.assign'],
            ['scope' => 'school', 'category' => 'director', 'name' => 'Revoke roles',        'slug' => 'role.revoke'],
            ['scope' => 'school', 'category' => 'director', 'name' => 'Create users',        'slug' => 'user.create'],
            ['scope' => 'school', 'category' => 'director', 'name' => 'Update users',        'slug' => 'user.update'],
            ['scope' => 'school', 'category' => 'director', 'name' => 'Suspend users',       'slug' => 'user.suspend'],
            ['scope' => 'school', 'category' => 'director', 'name' => 'Delete users',        'slug' => 'user.delete'],
            ['scope' => 'school', 'category' => 'director', 'name' => 'Manage subjects',     'slug' => 'subject.manage'],
            ['scope' => 'school', 'category' => 'director', 'name' => 'Send announcements',  'slug' => 'announcement.send'],

            // school/academic_coordinator
            ['scope' => 'school', 'category' => 'academic_coordinator', 'name' => 'Publish grades', 'slug' => 'grade.publish'],
            ['scope' => 'school', 'category' => 'academic_coordinator', 'name' => 'View all grades', 'slug' => 'grade.view_all'],
            ['scope' => 'school', 'category' => 'academic_coordinator', 'name' => 'Create grades',   'slug' => 'grade.create'],
            ['scope' => 'school', 'category' => 'academic_coordinator', 'name' => 'Update grades',   'slug' => 'grade.update'],
            ['scope' => 'school', 'category' => 'academic_coordinator', 'name' => 'Delete grades',   'slug' => 'grade.delete'],
            ['scope' => 'school', 'category' => 'academic_coordinator', 'name' => 'Create groups',   'slug' => 'group.create'],
            ['scope' => 'school', 'category' => 'academic_coordinator', 'name' => 'Update groups',   'slug' => 'group.update'],
            ['scope' => 'school', 'category' => 'academic_coordinator', 'name' => 'Delete groups',   'slug' => 'group.delete'],
            ['scope' => 'school', 'category' => 'academic_coordinator', 'name' => 'List groups',     'slug' => 'group.list'],

            // school/finance
            ['scope' => 'school', 'category' => 'finance', 'name' => 'Approve payments', 'slug' => 'payment.approve'],
            ['scope' => 'school', 'category' => 'finance', 'name' => 'View payments',    'slug' => 'payment.view'],
            ['scope' => 'school', 'category' => 'finance', 'name' => 'Create payments',  'slug' => 'payment.create'],
            ['scope' => 'school', 'category' => 'finance', 'name' => 'Update payments',  'slug' => 'payment.update'],
            ['scope' => 'school', 'category' => 'finance', 'name' => 'Delete payments',  'slug' => 'payment.delete'],
            ['scope' => 'school', 'category' => 'finance', 'name' => 'Reject payments',  'slug' => 'payment.reject'],

            // school/common — readable by any school/* role
            ['scope' => 'school', 'category' => 'common', 'name' => 'View users',         'slug' => 'user.view'],
            ['scope' => 'school', 'category' => 'common', 'name' => 'View grade',          'slug' => 'grade.view'],
            ['scope' => 'school', 'category' => 'common', 'name' => 'View group',          'slug' => 'group.view'],
            ['scope' => 'school', 'category' => 'common', 'name' => 'View announcements',  'slug' => 'announcement.view'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->insertOrIgnore([
                'uuid' => (string) Str::uuid(),
                'category_id' => $catId($permission['scope'], $permission['category']),
                'name' => $permission['name'],
                'slug' => $permission['slug'],
                'created_at' => now(),
            ]);
        }
    }

    private function seedRoles(): void
    {
        $catId = fn(string $scope, string $name): ?int => DB::table('permission_categories')
            ->where('scope', $scope)
            ->where('name', $name)
            ->value('id');

        $roles = [
            // Tenant-admin — no category, authority by Gate bypass / slug
            [
                'category_id' => null,
                'name' => 'Owner',
                'slug' => 'owner',
                'hierarchy_level' => 2,
                'is_system_role' => false,
            ],
            [
                'category_id' => null,
                'name' => 'School Manager',
                'slug' => 'school_manager',
                'hierarchy_level' => 3,
                'is_system_role' => false,
            ],

            // Tenant operational
            [
                'category_id' => $catId('tenant', 'finance'),
                'name' => 'Tenant Finance',
                'slug' => 'tenant_finance',
                'hierarchy_level' => 4,
                'is_system_role' => false,
            ],
            [
                'category_id' => $catId('tenant', 'hr'),
                'name' => 'Tenant HR',
                'slug' => 'tenant_hr',
                'hierarchy_level' => 4,
                'is_system_role' => false,
            ],

            // School operational
            [
                'category_id' => $catId('school', 'director'),
                'name' => 'Director',
                'slug' => 'director',
                'hierarchy_level' => 5,
                'is_system_role' => false,
            ],
            [
                'category_id' => $catId('school', 'academic_coordinator'),
                'name' => 'Academic Coordinator',
                'slug' => 'academic_coordinator',
                'hierarchy_level' => 6,
                'is_system_role' => false,
            ],
            [
                'category_id' => $catId('school', 'director'),
                'name' => 'School Registrar',
                'slug' => 'school_registrar',
                'hierarchy_level' => 6,
                'is_system_role' => false,
            ],
            [
                'category_id' => $catId('school', 'prefect'),
                'name' => 'Prefect',
                'slug' => 'prefect',
                'hierarchy_level' => 6,
                'is_system_role' => false,
            ],
            [
                'category_id' => $catId('school', 'finance'),
                'name' => 'Finance',
                'slug' => 'finance',
                'hierarchy_level' => 6,
                'is_system_role' => false,
            ],
            [
                'category_id' => $catId('school', 'hr'),
                'name' => 'HR',
                'slug' => 'hr',
                'hierarchy_level' => 6,
                'is_system_role' => false,
            ],
            [
                'category_id' => $catId('school', 'teacher'),
                'name' => 'Teacher',
                'slug' => 'teacher',
                'hierarchy_level' => 7,
                'is_system_role' => false,
            ],
            [
                'category_id' => $catId('school', 'student'),
                'name' => 'Student',
                'slug' => 'student',
                'hierarchy_level' => 8,
                'is_system_role' => false,
            ],
            [
                'category_id' => $catId('school', 'tutor'),
                'name' => 'Tutor',
                'slug' => 'tutor',
                'hierarchy_level' => 8,
                'is_system_role' => false,
            ],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->insertOrIgnore([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => null,
                'category_id' => $role['category_id'],
                'name' => $role['name'],
                'slug' => $role['slug'],
                'hierarchy_level' => $role['hierarchy_level'],
                'is_system_role' => $role['is_system_role'],
                'requires_2fa' => false,
                'created_at' => now(),
            ]);
        }
    }

    private function seedRolePermissions(): void
    {
        $permissionId = fn(string $slug): int => (int) DB::table('permissions')->where('slug', $slug)->value('id');
        $roleId = fn(string $slug): int => (int) DB::table('roles')->where('slug', $slug)->whereNull('tenant_id')->value('id');

        $assign = function (string $roleSlug, array $permissionSlugs) use ($roleId, $permissionId): void {
            $rid = $roleId($roleSlug);
            foreach ($permissionSlugs as $slug) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $rid,
                    'permission_id' => $permissionId($slug),
                ]);
            }
        };

        // owner and school_manager have no role_permissions — authority is handled by Gate bypass.

        $assign('director', [
            'school.view',
            'school.update',
            'role.view',
            'role.assign',
            'role.revoke',
            'manage.permissions',
            'custom_role.create',
            'custom_role.update',
            'custom_role.delete',
            'user.view',
            'user.create',
            'user.update',
            'user.suspend',
            'user.delete',
            'grade.publish',
            'grade.view',
            'payment.approve',
            'payment.view',
            'group.list',
            'group.view',
            'subject.manage',
            'announcement.send',
            'announcement.view',
        ]);

        $assign('academic_coordinator', [
            'grade.publish',
            'grade.view_all',
            'grade.create',
            'grade.update',
            'grade.delete',
            'grade.view',
            'group.create',
            'group.update',
            'group.delete',
            'group.list',
            'group.view',
            'subject.manage',
            'user.view',
            'announcement.send',
            'announcement.view',
        ]);

        $assign('school_registrar', [
            'user.create',
            'user.update',
            'user.view',
            'grade.view',
            'group.view',
            'announcement.view',
        ]);

        $assign('prefect', [
            'user.view',
            'group.view',
            'announcement.view',
        ]);

        $assign('finance', [
            'payment.approve',
            'payment.create',
            'payment.update',
            'payment.delete',
            'payment.view',
            'payment.reject',
            'user.view',
            'announcement.view',
        ]);

        $assign('hr', [
            'user.create',
            'user.update',
            'user.delete',
            'user.suspend',
            'user.view',
            'announcement.send',
            'announcement.view',
        ]);

        $assign('teacher', [
            'grade.create',
            'grade.update',
            'grade.view',
            'group.view',
            'announcement.view',
        ]);

        $assign('student', [
            'grade.view',
            'announcement.view',
        ]);

        $assign('tutor', [
            'grade.view',
            'announcement.view',
        ]);
    }
}
