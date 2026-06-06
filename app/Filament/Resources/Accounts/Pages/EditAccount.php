<?php

namespace App\Filament\Resources\Accounts\Pages;

use App\Filament\Resources\Accounts\AccountResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAccount extends EditRecord
{
    protected static string $resource = AccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load units for the form
        $data['units'] = $this->record->units()->pluck('units.id')->toArray();
        return $data;
    }

    protected function afterSave(): void
    {
        // Sync units relationship
        if (isset($this->data['units'])) {
            $this->record->units()->sync($this->data['units'] ?? []);
        }
    }
}
