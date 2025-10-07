<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\ChecksAdmin;
use App\Filament\Resources\ZonaAnatomicaResource\Pages;
use App\Models\ZonaAnatomica;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Support\Str;

class ZonaAnatomicaResource extends Resource
{
    use ChecksAdmin;

    protected static ?string $model = ZonaAnatomica::class;

    protected static ?string $navigationGroup = 'Catálogos clínicos';
    protected static ?string $navigationIcon  = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Zonas anatómicas';
    protected static ?string $pluralModelLabel = 'Zonas anatómicas';
    protected static ?string $modelLabel = 'Zona anatómica';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nombre')
                ->label('Nombre')
                ->required()
                ->maxLength(100)
                ->live(debounce: 300)
                ->afterStateUpdated(fn($state, callable $set) => $set('slug', Str::slug($state)))
                // Unicidad por (parent_id, nombre)
                ->rule(function (callable $get) {
                    return Rule::unique('zona_anatomica', 'nombre')
                        ->where(fn($q) => $q->where('parent_id', $get('parent_id')))
                        ->ignore(request()->route('record'));
                }),

            Forms\Components\TextInput::make('slug')
                ->label('Slug')
                ->required()
                ->maxLength(100)
                ->unique(ignoreRecord: true),

            Forms\Components\Select::make('parent_id')
                ->label('Padre')
                ->relationship('parent', 'nombre')
                ->searchable()
                ->preload()
                ->nullable()
                ->helperText('Dejar vacío si es una zona raíz.'),

            Forms\Components\TextInput::make('codigo')
                ->label('Código')
                ->maxLength(50),

            Forms\Components\Toggle::make('requiere_lateralidad')
                ->label('Requiere lateralidad')
                ->default(false),

            Forms\Components\Toggle::make('activo')
                ->label('Activo')
                ->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nombre')
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('parent.nombre')
                    ->label('Padre')
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\IconColumn::make('requiere_lateralidad')
                    ->label('Lat.')
                    ->boolean(),

                Tables\Columns\IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('activo')
                    ->label('Solo activos')
                    ->trueLabel('Activos')
                    ->falseLabel('Inactivos')
                    ->queries(
                        true: fn(Builder $q) => $q->where('activo', true),
                        false: fn(Builder $q) => $q->where('activo', false),
                        blank: fn(Builder $q) => $q
                    ),
                TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListZonaAnatomicas::route('/'),
            'create' => Pages\CreateZonaAnatomica::route('/create'),
            'edit'   => Pages\EditZonaAnatomica::route('/{record}/edit'),
        ];
    }
}
