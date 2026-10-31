<?php

namespace App\Services;

use App\Models\User;

class RoleAccessService
{
    /**
     * @return list<string>
     */
    public function allowedDepartments(User $user): array
    {
        return match ($user->role) {
            'gm', 'admin' => [
                'production',
                'qa',
                'compliance_sustainability',
                'supply_chain_merchandising',
                'hr_training',
                'it_cybersecurity',
                'spinning',
                'weaving',
                'knitting',
                'dyeing_finishing',
                'quality_control',
                'ppc',
                'industrial_engineering',
                'maintenance_engineering',
                'store_warehouse',
                'chemical_store',
                'etp',
                'accounts_finance',
                'commercial_sales',
                'it_edp',
                'general',
            ],
            'qa' => ['qa', 'general'],
            'hr' => ['hr_training', 'general'],
            'compliance' => ['compliance_sustainability', 'general'],
            'employee' => ['general'],
            default => ['general'],
        };
    }

    public function canAccess(?string $department, User $user): bool
    {
        return in_array($department ?? 'general', $this->allowedDepartments($user), true);
    }
}
