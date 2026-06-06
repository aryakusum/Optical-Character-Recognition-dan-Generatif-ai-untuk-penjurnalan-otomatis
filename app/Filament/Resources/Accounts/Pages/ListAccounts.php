<?php

namespace App\Filament\Resources\Accounts\Pages;

use App\Filament\Resources\Accounts\AccountResource;
use App\Imports\AccountsImport;
use Filament\Actions\CreateAction;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListAccounts extends ListRecords
{
    protected static string $resource = AccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Import CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->form([
                    FileUpload::make('file')
                        ->label('File CSV')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv'])
                        ->required()
                        ->disk('public')
                        ->directory('imports')
                        ->helperText('Format CSV: kode_akun, nama_akun, jenis, keterangan, unit'),
                ])
                ->action(function (array $data): void {
                    $filePath = storage_path('app/public/' . $data['file']);

                    try {
                        $importer = new AccountsImport();
                        $count = $importer->import($filePath);

                        // Hapus file setelah import
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }

                        Notification::make()
                            ->title('Import Berhasil')
                            ->body("Berhasil import {$count} akun.")
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Import Gagal')
                            ->body('Error: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            CreateAction::make()
                ->label('Tambah Akun'),
        ];
    }
}
