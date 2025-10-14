<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\ChecksAdmin;
use App\Filament\Resources\TecnicaResource\Pages;
use App\Models\Tecnica;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TecnicaResource extends Resource
{
    use ChecksAdmin;

    protected static ?string $model = Tecnica::class;

    protected static ?string $navigationGroup  = 'Catálogos clínicos';
    protected static ?string $navigationIcon   = 'heroicon-o-wrench-screwdriver';
    protected static ?string $navigationLabel  = 'Técnicas';
    protected static ?string $modelLabel       = 'técnica';
    protected static ?string $pluralModelLabel = 'técnicas';
    protected static ?int    $navigationSort   = 9;

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

            Forms\Components\Select::make('id_tecnica_tipo')
                ->label('Tipo de técnica')
                ->relationship('tipo', 'nombre') // <- usa la relación 'tipo' del modelo
                ->required()
                ->preload()
                ->searchable(),

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
                Tables\Columns\TextColumn::make('codigo')
                    ->label('Código')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('tipo.nombre') // <- antes: 'tecnicaTipo.nombre'
                    ->label('Tipo')
                    ->badge()
                    ->sortable(),
                Tables\Columns\IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('id_tecnica_tipo')
                    ->label('Tipo')
                    ->relationship('tipo', 'nombre') // <- antes: 'tecnicaTipo'
                    ->preload(),
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
            'index'  => Pages\ListTecnicas::route('/'),
            'create' => Pages\CreateTecnica::route('/create'),
            'edit'   => Pages\EditTecnica::route('/{record}/edit'),
        ];
    }
}

