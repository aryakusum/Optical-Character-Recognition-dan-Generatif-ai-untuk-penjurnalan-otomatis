<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Unit;

// Service untuk mapping akun (COA) per unit kerja
class UnitAccountService
{
    // Get akun yang diizinkan untuk unit tertentu
    public function getAccountsByUnit(int $unitId): array
    {
        $unit = Unit::with('accounts')->find($unitId);

        if (!$unit) {
            return [];
        }

        return $unit->accounts->map(function ($account) {
            return [
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
            ];
        })->toArray();
    }

    // Get daftar unit dengan info akun
    public function getUnitsWithAccounts(): array
    {
        return Unit::active()
            ->with('accounts:id,code,name,type')
            ->get()
            ->map(function ($unit) {
                return [
                    'id' => $unit->id,
                    'code' => $unit->code,
                    'name' => $unit->name,
                    'type' => $unit->type,
                    'accounts_count' => $unit->accounts->count(),
                ];
            })
            ->toArray();
    }

    // Format akun untuk prompt AI
    public function formatAccountsForAI(int $unitId): string
    {
        $accounts = $this->getAccountsByUnit($unitId);

        if (empty($accounts)) {
            return "No specific accounts are configured for this unit.";
        }

        $formatted = "Available Chart of Accounts for this unit:\n";
        foreach ($accounts as $account) {
            $formatted .= "- {$account['code']}: {$account['name']} ({$account['type']})\n";
        }

        return $formatted;
    }

    // Cek apakah akun valid untuk unit
    public function isAccountValidForUnit(int $unitId, string $accountCode): bool
    {
        $unit = Unit::with('accounts')->find($unitId);

        if (!$unit) {
            return false;
        }

        return $unit->accounts->contains('code', $accountCode);
    }

    // Get semua akun aktif (fallback jika unit tidak punya mapping)
    public function getAllActiveAccounts(): array
    {
        return Account::active()
            ->orderBy('code')
            ->get()
            ->map(function ($account) {
                return [
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type,
                ];
            })
            ->toArray();
    }
}
