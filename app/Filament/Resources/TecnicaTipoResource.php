<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\ChecksAdmin;
use App\Filament\Resources\TecnicaTipoResource\Pages;
use App\Models\TecnicaTipo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TecnicaTipoResource extends Resource
{
    use ChecksAdmin;

    protected static ?string $model = TecnicaTipo::class;

    protected static ?string $navigationGroup  = 'Catálogos clínicos';
    protected static ?string $navigationIcon   = 'heroicon-o-squares-2x2';
    protected static ?string $navigationLabel  = 'Tipos de técnica';
    protected static ?string $modelLabel       = 'tipo de técnica';
    protected static ?string $pluralModelLabel = 'tipos de técnica';
    protected static ?int    $navigationSort   = 7;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('codigo')
                ->label('Código')
                ->required()
                ->maxLength(30)
                ->unique(ignoreRecord: true),

            Forms\Components\TextInput::make('nombre')
                ->label('Nombre')
                ->required()
                ->maxLength(120)
                ->unique(ignoreRecord: true),

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
            ->columns([
                Tables\Columns\TextColumn::make('codigo')->label('Código')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('nombre')->label('Nombre')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('tecnicas_count')
                    ->counts('tecnicas')
                    ->label('Técnicas')
                    ->badge()
                    ->color('info'),
                Tables\Columns\IconColumn::make('activo')->label('Activo')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('activo')->label('Solo activos'),
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
            'index'  => Pages\ListTecnicaTipos::route('/'),
            'create' => Pages\CreateTecnicaTipo::route('/create'),
            'edit'   => Pages\EditTecnicaTipo::route('/{record}/edit'),
        ];
    }
}
