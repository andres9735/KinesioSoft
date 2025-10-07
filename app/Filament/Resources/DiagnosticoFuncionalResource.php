<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\ChecksAdmin;
use App\Filament\Resources\DiagnosticoFuncionalResource\Pages;
use App\Models\DiagnosticoFuncional;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\Rules\Unique;

class DiagnosticoFuncionalResource extends Resource
{
    use ChecksAdmin;

    protected static ?string $model = DiagnosticoFuncional::class;

    protected static ?string $navigationGroup = 'Catálogos clínicos';
    protected static ?string $navigationIcon  = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Diagnósticos funcionales';
    protected static ?string $pluralModelLabel = 'Diagnósticos funcionales';
    protected static ?string $modelLabel = 'Diagnóstico funcional';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nombre')
                ->label('Nombre')
                ->required()
                ->maxLength(120)
                ->unique(
                    ignoreRecord: true,
                    modifyRuleUsing: fn(Unique $rule) => $rule // por si el día de mañana agregás multi-guard/catálogo
                ),

            Forms\Components\TextInput::make('codigo')
                ->label('Código')
                ->maxLength(50),

            Forms\Components\Textarea::make('descripcion')
                ->label('Descripción')
                ->columnSpanFull(),

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

                Tables\Columns\TextColumn::make('codigo')
                    ->label('Código')
                    ->toggleable()
                    ->searchable(),

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

    // Habilita TrashedFilter
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListDiagnosticoFuncionals::route('/'),
            'create' => Pages\CreateDiagnosticoFuncional::route('/create'),
            'edit'   => Pages\EditDiagnosticoFuncional::route('/{record}/edit'),
        ];
    }
}
