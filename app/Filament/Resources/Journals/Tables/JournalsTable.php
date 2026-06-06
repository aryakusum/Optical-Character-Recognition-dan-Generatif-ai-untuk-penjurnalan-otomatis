<?php

namespace App\Filament\Resources\Journals\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class JournalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('journal_number')
                    ->searchable(),
                TextColumn::make('transaction_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('document_type')
                    ->searchable(),
                TextColumn::make('document_number')
                    ->searchable(),
                TextColumn::make('vendor')
                    ->searchable(),
                TextColumn::make('total_amount')
                    ->numeric()
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('currency')
                    ->searchable(),
                TextColumn::make('unit.name')
                    ->searchable(),
                TextColumn::make('user.name')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($record) => $record->status_label)
                    ->color(fn ($record) => $record->status_color),
                \Filament\Tables\Columns\ImageColumn::make('document_path')
                    ->label('Dokumen Transaksi')
                    ->disk('public'),
                TextColumn::make('document_original_name')
                    ->searchable(),
                TextColumn::make('verified_unit_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('verified_unit_by')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('verified_finance_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('verified_finance_by')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
