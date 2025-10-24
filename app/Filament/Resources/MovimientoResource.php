<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MovimientoResource\Pages;
use App\Models\Movimiento;
use App\Models\ZonaAnatomica;
use Filament\Tables\Grouping\Group;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

class MovimientoResource extends Resource
{
    protected static ?string $model = Movimiento::class;

    protected static ?string $navigationGroup  = 'Catálogos clínicos';
    protected static ?string $navigationIcon   = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationLabel  = 'Movimientos';
    protected static ?string $modelLabel       = 'movimiento';
    protected static ?string $pluralModelLabel = 'movimientos';
    protected static ?int    $navigationSort   = 21;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('id_zona_anatomica')
                        ->label('Zona anatómica')
                        ->options(fn() => ZonaAnatomica::where('activo', 1)
                            ->orderBy('nombre')
                            ->pluck('nombre', 'id_zona_anatomica'))
                        ->searchable()->required()->native(false),

                    Forms\Components\TextInput::make('nombre')
                        ->required()->maxLength(80)
                        ->rule(function (callable $get) {
                            // Unicidad por zona + nombre
                            return Rule::unique('movimiento', 'nombre')
                                ->where('id_zona_anatomica', $get('id_zona_anatomica'))
                                ->ignore(request()->route('record'));
                        })
                        ->helperText('Debe ser único dentro de la misma zona.'),

                    Forms\Components\TextInput::make('slug')->required()->maxLength(80),

                    Forms\Components\TextInput::make('codigo')->maxLength(50),

                    Forms\Components\Select::make('plano')
                        ->options(['sagital' => 'Sagital', 'frontal' => 'Frontal', 'transversal' => 'Transversal'])
                        ->native(false),

                    Forms\Components\Select::make('tipo_movimiento')
                        ->options(['activa' => 'Activa', 'pasiva' => 'Pasiva', 'activa_asistida' => 'Activa asistida'])
                        ->native(false),

                    Forms\Components\TextInput::make('rango_norm_min')->numeric()->minValue(0)->maxValue(200)
                        ->label('Rango normal mín (°)'),

                    Forms\Components\TextInput::make('rango_norm_max')->numeric()->minValue(0)->maxValue(200)
                        ->label('Rango normal máx (°)')
                        ->helperText('Opcional, usado como referencia en la UI.'),

                    Forms\Components\Toggle::make('activo')->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('zona.nombre')->label('Zona')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('nombre')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('plano')->badge()->sortable(),
                Tables\Columns\TextColumn::make('tipo_movimiento')->badge()->label('Tipo'),
                Tables\Columns\TextColumn::make('rango_norm_min')->label('Norm. mín (°)')->alignRight(),
                Tables\Columns\TextColumn::make('rango_norm_max')->label('Norm. máx (°)')->alignRight(),
                Tables\Columns\IconColumn::make('activo')->boolean()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('id_zona_anatomica')
                    ->label('Zona')
                    ->options(fn() => ZonaAnatomica::orderBy('nombre')->pluck('nombre', 'id_zona_anatomica'))
                    ->searchable(),
                Tables\Filters\TernaryFilter::make('activo')->label('Sólo activos'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('zona.nombre')
            ->groups([
                Group::make('zona.nombre')
                    ->label('Zona')
                    ->collapsible()
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMovimientos::route('/'),
            'create' => Pages\CreateMovimiento::route('/create'),
            'edit'   => Pages\EditMovimiento::route('/{record}/edit'),
        ];
    }
}
