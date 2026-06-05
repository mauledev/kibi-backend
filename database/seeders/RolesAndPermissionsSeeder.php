<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RolesAndPermissionsSeeder extends Seeder
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
            // Staff scope — Softlinkia internal operational roles
            ['scope' => 'staff',  'name' => 'support'],
            ['scope' => 'staff',  'name' => 'finance'],

            // Tenant scope — tenant-level operational roles
            ['scope' => 'tenant', 'name' => 'finance'],
            ['scope' => 'tenant', 'name' => 'hr'],

            // School scope — school-level operational roles
            ['scope' => 'school', 'name' => 'academic'],
            ['scope' => 'school', 'name' => 'financial'],
            ['scope' => 'school', 'name' => 'hr'],
            ['scope' => 'school', 'name' => 'configuration'],
            ['scope' => 'school', 'name' => 'communication'],
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
        $categoryId = fn (string $scope, string $name): int => (int) DB::table('permission_categories')
            ->where('scope', $scope)->where('name', $name)->value('id');

        $permissions = [
            // School / Academic
            ['scope' => 'school', 'category' => 'academic', 'name' => 'Publicar calificaciones',  'slug' => 'grade.publish'],
            ['scope' => 'school', 'category' => 'academic', 'name' => 'Crear calificaciones',      'slug' => 'grade.create'],
            ['scope' => 'school', 'category' => 'academic', 'name' => 'Actualizar calificaciones', 'slug' => 'grade.update'],
            ['scope' => 'school', 'category' => 'academic', 'name' => 'Eliminar calificaciones',   'slug' => 'grade.delete'],
            ['scope' => 'school', 'category' => 'academic', 'name' => 'Ver calificaciones',        'slug' => 'grade.view'],
            ['scope' => 'school', 'category' => 'academic', 'name' => 'Gestionar grupos',          'slug' => 'group.manage'],
            ['scope' => 'school', 'category' => 'academic', 'name' => 'Ver grupos',                'slug' => 'group.view'],
            ['scope' => 'school', 'category' => 'academic', 'name' => 'Gestionar materias',        'slug' => 'subject.manage'],

            // School / Financial
            ['scope' => 'school', 'category' => 'financial', 'name' => 'Aprobar pagos',    'slug' => 'payment.approve'],
            ['scope' => 'school', 'category' => 'financial', 'name' => 'Crear pagos',      'slug' => 'payment.create'],
            ['scope' => 'school', 'category' => 'financial', 'name' => 'Actualizar pagos', 'slug' => 'payment.update'],
            ['scope' => 'school', 'category' => 'financial', 'name' => 'Eliminar pagos',   'slug' => 'payment.delete'],
            ['scope' => 'school', 'category' => 'financial', 'name' => 'Ver pagos',        'slug' => 'payment.view'],
            ['scope' => 'school', 'category' => 'financial', 'name' => 'Rechazar pagos',   'slug' => 'payment.reject'],

            // School / HR
            ['scope' => 'school', 'category' => 'hr', 'name' => 'Suspender usuarios',  'slug' => 'user.suspend'],
            ['scope' => 'school', 'category' => 'hr', 'name' => 'Crear usuarios',       'slug' => 'user.create'],
            ['scope' => 'school', 'category' => 'hr', 'name' => 'Actualizar usuarios',  'slug' => 'user.update'],
            ['scope' => 'school', 'category' => 'hr', 'name' => 'Eliminar usuarios',    'slug' => 'user.delete'],
            ['scope' => 'school', 'category' => 'hr', 'name' => 'Ver usuarios',         'slug' => 'user.view'],

            // School / Configuration
            ['scope' => 'school', 'category' => 'configuration', 'name' => 'Ver escuelas',                 'slug' => 'school.view'],
            ['scope' => 'school', 'category' => 'configuration', 'name' => 'Crear escuelas',               'slug' => 'school.create'],
            ['scope' => 'school', 'category' => 'configuration', 'name' => 'Actualizar escuelas',          'slug' => 'school.update'],
            ['scope' => 'school', 'category' => 'configuration', 'name' => 'Ver roles',                    'slug' => 'role.view'],
            ['scope' => 'school', 'category' => 'configuration', 'name' => 'Asignar roles',                'slug' => 'role.assign'],
            ['scope' => 'school', 'category' => 'configuration', 'name' => 'Revocar roles',                'slug' => 'role.revoke'],
            ['scope' => 'school', 'category' => 'configuration', 'name' => 'Otorgar permisos',             'slug' => 'permission.grant'],
            ['scope' => 'school', 'category' => 'configuration', 'name' => 'Revocar permisos',             'slug' => 'permission.revoke'],
            ['scope' => 'school', 'category' => 'configuration', 'name' => 'Gestionar permisos',           'slug' => 'manage.permissions'],
            ['scope' => 'school', 'category' => 'configuration', 'name' => 'Crear roles',                  'slug' => 'role.create'],
            ['scope' => 'school', 'category' => 'configuration', 'name' => 'Actualizar roles',             'slug' => 'role.update'],
            ['scope' => 'school', 'category' => 'configuration', 'name' => 'Eliminar roles',               'slug' => 'role.delete'],
            ['scope' => 'school', 'category' => 'configuration', 'name' => 'Crear roles personalizados',   'slug' => 'roles.custom.create'],

            // School / Communication
            ['scope' => 'school', 'category' => 'communication', 'name' => 'Enviar comunicados', 'slug' => 'announcement.send'],
            ['scope' => 'school', 'category' => 'communication', 'name' => 'Ver comunicados',    'slug' => 'announcement.view'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->insertOrIgnore([
                'uuid' => (string) Str::uuid(),
                'category_id' => $categoryId($permission['scope'], $permission['category']),
                'name' => $permission['name'],
                'slug' => $permission['slug'],
                'created_at' => now(),
            ]);
        }
    }

    private function seedRoles(): void
    {
        $catId = fn (string $scope, string $name): ?int => DB::table('permission_categories')->where('scope', $scope)->where('name', $name)->value('id');

        $roles = [
            // Softlinkia staff — is_system_role = true, tenant_id = null
            [
                'tenant_id' => null,
                'category_id' => null,
                'name' => 'Superadmin',
                'slug' => 'superadmin',
                'hierarchy_level' => 1,
                'is_system_role' => true,
            ],
            // Tenant-admin roles — no category, authority by Gate bypass / slug
            [
                'tenant_id' => null,
                'category_id' => null,
                'name' => 'Owner',
                'slug' => 'owner',
                'hierarchy_level' => 2,
                'is_system_role' => false,
            ],
            [
                'tenant_id' => null,
                'category_id' => null,
                'name' => 'Gestor de Escuelas',
                'slug' => 'gestor_escuelas',
                'hierarchy_level' => 3,
                'is_system_role' => false,
            ],
            // School operational roles — bound to a school-scoped category
            [
                'tenant_id' => null,
                'category_id' => $catId('school', 'configuration'),
                'name' => 'Director',
                'slug' => 'director',
                'hierarchy_level' => 4,
                'is_system_role' => false,
            ],
            [
                'tenant_id' => null,
                'category_id' => $catId('school', 'academic'),
                'name' => 'Coordinador Académico',
                'slug' => 'coordinador_academico',
                'hierarchy_level' => 5,
                'is_system_role' => false,
            ],
            [
                'tenant_id' => null,
                'category_id' => $catId('school', 'hr'),
                'name' => 'Control Escolar',
                'slug' => 'control_escolar',
                'hierarchy_level' => 5,
                'is_system_role' => false,
            ],
            [
                'tenant_id' => null,
                'category_id' => $catId('school', 'hr'),
                'name' => 'Prefectura',
                'slug' => 'prefectura',
                'hierarchy_level' => 6,
                'is_system_role' => false,
            ],
            [
                'tenant_id' => null,
                'category_id' => $catId('school', 'financial'),
                'name' => 'Finanzas',
                'slug' => 'finanzas',
                'hierarchy_level' => 6,
                'is_system_role' => false,
            ],
            [
                'tenant_id' => null,
                'category_id' => $catId('school', 'hr'),
                'name' => 'RRHH',
                'slug' => 'rrhh',
                'hierarchy_level' => 6,
                'is_system_role' => false,
            ],
            [
                'tenant_id' => null,
                'category_id' => $catId('school', 'academic'),
                'name' => 'Docente',
                'slug' => 'docente',
                'hierarchy_level' => 7,
                'is_system_role' => false,
            ],
            [
                'tenant_id' => null,
                'category_id' => $catId('school', 'academic'),
                'name' => 'Alumno',
                'slug' => 'alumno',
                'hierarchy_level' => 8,
                'is_system_role' => false,
            ],
            [
                'tenant_id' => null,
                'category_id' => $catId('school', 'academic'),
                'name' => 'Tutor',
                'slug' => 'tutor',
                'hierarchy_level' => 8,
                'is_system_role' => false,
            ],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->insertOrIgnore([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $role['tenant_id'],
                'category_id' => $role['category_id'],
                'name' => $role['name'],
                'slug' => $role['slug'],
                'hierarchy_level' => $role['hierarchy_level'],
                'is_system_role' => $role['is_system_role'],
                'created_at' => now(),
            ]);
        }
    }

    private function seedRolePermissions(): void
    {
        $permissionId = fn (string $slug): int => (int) DB::table('permissions')->where('slug', $slug)->value('id');

        $roleId = fn (string $slug): int => (int) DB::table('roles')->where('slug', $slug)->whereNull('tenant_id')->value('id');

        $assign = function (string $roleSlug, array $permissionSlugs) use ($roleId, $permissionId): void {
            $rid = $roleId($roleSlug);
            foreach ($permissionSlugs as $slug) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $rid,
                    'permission_id' => $permissionId($slug),
                ]);
            }
        };

        // Gestor de Escuelas — full configuration + hr + all school permissions
        $assign('gestor_escuelas', [
            'manage.permissions',
            'role.view', 'role.assign', 'role.revoke', 'role.create', 'role.update', 'role.delete',
            'permission.grant', 'permission.revoke',
            'user.create', 'user.update', 'user.delete', 'user.suspend', 'user.view',
            'grade.publish', 'grade.create', 'grade.update', 'grade.delete', 'grade.view',
            'payment.approve', 'payment.create', 'payment.update', 'payment.delete', 'payment.view', 'payment.reject',
            'group.manage', 'group.view', 'subject.manage',
            'announcement.send', 'announcement.view',
        ]);

        // Director — manages school, permissions, teachers, grades (cross-category via role_permissions)
        $assign('director', [
            'manage.permissions',
            'role.view', 'role.assign', 'role.revoke',
            'permission.grant', 'permission.revoke',
            'user.create', 'user.update', 'user.view', 'user.suspend',
            'grade.publish', 'grade.create', 'grade.update', 'grade.delete', 'grade.view',
            'payment.approve', 'payment.view',
            'group.manage', 'group.view', 'subject.manage',
            'announcement.send', 'announcement.view',
        ]);

        // Coordinador Académico — academic focus
        $assign('coordinador_academico', [
            'role.view',
            'grade.publish', 'grade.create', 'grade.update', 'grade.delete', 'grade.view',
            'group.manage', 'group.view', 'subject.manage',
            'announcement.send', 'announcement.view',
            'user.view',
        ]);

        // Control Escolar — enrollment and records
        $assign('control_escolar', [
            'user.create', 'user.update', 'user.view',
            'grade.view',
            'group.view',
            'announcement.view',
        ]);

        // Prefectura — attendance and discipline
        $assign('prefectura', [
            'user.view',
            'group.view',
            'announcement.view',
        ]);

        // Finanzas — financial operations
        $assign('finanzas', [
            'payment.approve', 'payment.create', 'payment.update', 'payment.delete', 'payment.view', 'payment.reject',
            'user.view',
            'announcement.view',
        ]);

        // RRHH — human resources
        $assign('rrhh', [
            'user.create', 'user.update', 'user.delete', 'user.view', 'user.suspend',
            'announcement.send', 'announcement.view',
        ]);

        // Docente — teacher access
        $assign('docente', [
            'grade.create', 'grade.update', 'grade.view',
            'group.view',
            'announcement.view',
        ]);

        // Alumno — student access
        $assign('alumno', [
            'grade.view',
            'announcement.view',
        ]);

        // Tutor — parent access
        $assign('tutor', [
            'grade.view',
            'announcement.view',
        ]);
    }
}
