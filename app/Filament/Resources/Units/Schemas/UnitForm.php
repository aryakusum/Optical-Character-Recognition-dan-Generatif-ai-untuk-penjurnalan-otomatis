<?php

namespace App\Filament\Resources\Units\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UnitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Kode Unit')
                    ->required()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->label('Nama Unit')
                    ->required()
                    ->maxLength(255),
                Select::make('type')
                    ->label('Jenis Unit')
                    ->options([
                        'prodi' => 'Program Studi',
                        'fakultas' => 'Fakultas',
                        'direktorat' => 'Direktorat',
                        'lainnya' => 'Lainnya',
                    ])
                    ->default('prodi')
                    ->required(),
                Select::make('accounts')
                    ->label('Akun yang Diizinkan')
                    ->relationship('accounts', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }
}
