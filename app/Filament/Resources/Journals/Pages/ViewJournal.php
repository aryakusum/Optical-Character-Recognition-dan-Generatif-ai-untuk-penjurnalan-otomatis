<?php

namespace App\Filament\Resources\Journals\Pages;

use App\Filament\Resources\Journals\JournalResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewJournal extends ViewRecord
{
    protected static string $resource = JournalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            \Filament\Actions\Action::make('verifyFinance')
                ->label('Verifikasi Keuangan')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->form([
                    \Filament\Forms\Components\Textarea::make('notes')->label('Catatan')->nullable(),
                ])
                ->visible(fn (\App\Models\Journal $record) => in_array($record->status, [\App\Models\Journal::STATUS_DRAFT, \App\Models\Journal::STATUS_VERIFIED_UNIT]))
                ->action(function (\App\Models\Journal $record, array $data) {
                    $record->update([
                        'status' => \App\Models\Journal::STATUS_POSTED,
                        'verified_finance_at' => now(),
                        'verified_finance_by' => \Illuminate\Support\Facades\Auth::id(),
                        'verified_finance_notes' => $data['notes'] ?? null,
                    ]);
                }),
            \Filament\Actions\Action::make('reject')
                ->label('Tolak')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->form([
                    \Filament\Forms\Components\Textarea::make('notes')->label('Alasan Penolakan')->required(),
                ])
                ->visible(fn (\App\Models\Journal $record) => in_array($record->status, [\App\Models\Journal::STATUS_DRAFT, \App\Models\Journal::STATUS_VERIFIED_UNIT]))
                ->action(function (\App\Models\Journal $record, array $data) {
                    $user = \Illuminate\Support\Facades\Auth::user();
                    $sanitizedNotes = 'DITOLAK: ' . strip_tags(trim($data['notes']));
                    $updateData = ['status' => \App\Models\Journal::STATUS_REJECTED];
                    if ($user->isVerifikator() || $user->isAdmin()) {
                        $updateData['verified_finance_notes'] = $sanitizedNotes;
                    } else {
                        $updateData['verified_unit_notes'] = $sanitizedNotes;
                    }
                    $record->update($updateData);
                }),
        ];
    }
}
