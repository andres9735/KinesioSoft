<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\ChecksAdmin;
use App\Filament\Resources\EquipoTerapeuticoResource\Pages;
use App\Models\EquipoTerapeutico;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EquipoTerapeuticoResource extends Resource
{
    use ChecksAdmin;

    protected static ?string $model = EquipoTerapeutico::class;

    // Navegación
    protected static ?string $navigationGroup = 'Catálogos clínicos';
    protected static ?string $navigationIcon  = 'heroicon-o-cpu-chip';
    protected static ?string $navigationLabel = 'Equipos terapéuticos';
    protected static ?string $pluralModelLabel = 'Equipos terapéuticos';
    protected static ?string $modelLabel = 'Equipo terapéutico';
    protected static ?int    $navigationSort = 9;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('codigo')
                ->label('Código')
                ->required()
                ->maxLength(50)
                ->unique(ignoreRecord: true),

            Forms\Components\TextInput::make('nombre')
                ->label('Nombre')
                ->required()
                ->maxLength(150),

            Forms\Components\TextInput::make('marca_modelo')
                ->label('Marca / Modelo')
                ->maxLength(150),

            Forms\Components\Select::make('id_consultorio')
                ->label('Consultorio')
                ->relationship('consultorio', 'nombre')
                ->searchable()
                ->preload()
                ->required(),

            Forms\Components\Select::make('estado')
                ->label('Estado')
                ->options([
                    'operativo' => 'Operativo',
                    'baja'      => 'Baja',
                ])
                ->default('operativo')
                ->required(),

            Forms\Components\Textarea::make('descripcion')
                ->label('Descripción')
                ->rows(4)
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
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('consultorio.nombre')
                    ->label('Consultorio')
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(string $state) => $state === 'operativo' ? 'success' : 'danger')
                    ->sortable(),

                Tables\Columns\IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('activo')->label('Solo activos'),
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        'operativo' => 'Operativo',
                        'baja'      => 'Baja',
                    ]),
                Tables\Filters\SelectFilter::make('id_consultorio')
                    ->label('Consultorio')
                    ->relationship('consultorio', 'nombre')
                    ->searchable()
                    ->preload(),
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

    // Habilita filtro "Eliminados" (soft deletes)
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListEquipoTerapeuticos::route('/'),
            'create' => Pages\CreateEquipoTerapeutico::route('/create'),
            'edit'   => Pages\EditEquipoTerapeutico::route('/{record}/edit'),
        ];
    }
}
