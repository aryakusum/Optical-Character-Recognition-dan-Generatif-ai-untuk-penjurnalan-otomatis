<?php

namespace App\Filament\Resources\Journals\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class JournalInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('journal_number'),
                TextEntry::make('transaction_date')
                    ->date(),
                TextEntry::make('document_type')
                    ->placeholder('-'),
                TextEntry::make('document_number')
                    ->placeholder('-'),
                TextEntry::make('vendor')
                    ->placeholder('-'),
                TextEntry::make('description')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('total_amount')
                    ->numeric(),
                TextEntry::make('currency'),
                TextEntry::make('unit.name')
                    ->label('Unit')
                    ->placeholder('-'),
                TextEntry::make('user.name')
                    ->label('User')
                    ->placeholder('-'),
                TextEntry::make('status')
                    ->badge()
                    ->placeholder('-'),

                \Filament\Infolists\Components\ImageEntry::make('document_path')
                    ->label('Dokumen Transaksi')
                    ->disk('public')
                    ->height(400)
                    ->columnSpanFull(),
                TextEntry::make('document_original_name')
                    ->placeholder('-'),
                TextEntry::make('verified_unit_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('verified_unit_by')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('verified_unit_notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('verified_finance_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('verified_finance_by')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('verified_finance_notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
            ]);
    }
}
