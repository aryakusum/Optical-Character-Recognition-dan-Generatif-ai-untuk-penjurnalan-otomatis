<?php

namespace App\Filament\Resources\Journals\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class JournalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('journal_number')
                    ->required(),
                DatePicker::make('transaction_date')
                    ->required(),
                TextInput::make('document_type')
                    ->default(null),
                TextInput::make('document_number')
                    ->default(null),
                TextInput::make('vendor')
                    ->default(null),
                Textarea::make('description')
                    ->default(null)
                    ->columnSpanFull(),
                TextInput::make('total_amount')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('currency')
                    ->required()
                    ->default('IDR'),
                Select::make('unit_id')
                    ->relationship('unit', 'name')
                    ->default(null),
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->default(null),
                Select::make('status')
                    ->options([
            'draft' => 'Draft',
            'verified_unit' => 'Verified unit',
            'verified_finance' => 'Verified finance',
            'posted' => 'Posted',
            'rejected' => 'Rejected',
            'void' => 'Void',
        ])
                    ->default('draft'),

                TextInput::make('document_path')
                    ->default(null),
                TextInput::make('document_original_name')
                    ->default(null),
                DateTimePicker::make('verified_unit_at'),
                TextInput::make('verified_unit_by')
                    ->numeric()
                    ->default(null),
                Textarea::make('verified_unit_notes')
                    ->default(null)
                    ->columnSpanFull(),
                DateTimePicker::make('verified_finance_at'),
                TextInput::make('verified_finance_by')
                    ->numeric()
                    ->default(null),
                Textarea::make('verified_finance_notes')
                    ->default(null)
                    ->columnSpanFull(),
            ]);
    }
}
