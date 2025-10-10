<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\ChecksAdmin;
use App\Filament\Resources\EjercicioResource\Pages;
use App\Models\Ejercicio;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EjercicioResource extends Resource
{
    use ChecksAdmin;

    protected static ?string $model = Ejercicio::class;

    protected static ?string $navigationGroup = 'Catálogos clínicos';
    protected static ?string $navigationIcon  = 'heroicon-o-bolt';
    protected static ?string $navigationLabel = 'Ejercicios';
    protected static ?string $pluralModelLabel = 'Ejercicios';
    protected static ?string $modelLabel = 'Ejercicio';
    protected static ?int $navigationSort = 6;


    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nombre')
                ->label('Nombre')
                ->required()
                ->maxLength(150)
                ->unique(ignoreRecord: true),

            Forms\Components\Textarea::make('descripcion')
                ->label('Descripción')
                ->rows(3)
                ->columnSpanFull(),

            Forms\Components\Select::make('nivel_dificultad_base')
                ->label('Nivel de dificultad')
                ->options([
                    'baja'  => 'Baja',
                    'media' => 'Media',
                    'alta'  => 'Alta',
                ])
                ->default('baja'),

            Forms\Components\TextInput::make('url_recurso')
                ->label('URL del recurso')
                ->maxLength(255),

            Forms\Components\Select::make('categorias')
                ->label('Categorías')
                ->relationship('categorias', 'nombre')
                ->multiple()
                ->preload()
                ->searchable(),

            Forms\Components\Toggle::make('activo')
                ->label('Activo')
                ->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')->label('Nombre')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('nivel_dificultad_base')->label('Dificultad')->badge()->sortable(),
                Tables\Columns\IconColumn::make('activo')->label('Activo')->boolean(),
                Tables\Columns\TextColumn::make('categorias_count')
                    ->counts('categorias')
                    ->label('Categorías')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('updated_at')->label('Actualizado')->dateTime('d/m/Y H:i')->toggleable(isToggledHiddenByDefault: true),
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
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListEjercicios::route('/'),
            'create' => Pages\CreateEjercicio::route('/create'),
            'edit'   => Pages\EditEjercicio::route('/{record}/edit'),
        ];
    }
}
