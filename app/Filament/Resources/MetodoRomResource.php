<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MetodoRomResource\Pages;
use App\Models\MetodoRom;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MetodoRomResource extends Resource
{
    protected static ?string $model = MetodoRom::class;

    protected static ?string $navigationGroup  = 'Catálogos clínicos';
    protected static ?string $navigationIcon   = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel  = 'Métodos ROM';
    protected static ?string $modelLabel       = 'método ROM';
    protected static ?string $pluralModelLabel = 'métodos ROM';
    protected static ?int    $navigationSort   = 20;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('nombre')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(80)
                        ->unique(ignoreRecord: true),

                    Forms\Components\TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->maxLength(80)
                        ->helperText('Usa minúsculas y guiones, ej: gonio-universal'),

                    Forms\Components\TextInput::make('codigo')
                        ->label('Código')
                        ->maxLength(50),

                    Forms\Components\Select::make('tipo')
                        ->label('Tipo')
                        ->options([
                            'manual'        => 'Manual',
                            'digital'       => 'Digital',
                            'inclinometro'  => 'Inclinómetro',
                            'imu'           => 'IMU / wearable',
                            'visual'        => 'Evaluación visual',
                        ])
                        ->native(false),

                    Forms\Components\TextInput::make('precision_decimales')
                        ->label('Precisión (decimales)')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(3),

                    Forms\Components\TextInput::make('unidad_defecto')
                        ->label('Unidad por defecto')
                        ->default('°')
                        ->maxLength(10),

                    Forms\Components\TextInput::make('fabricante')->maxLength(80),
                    Forms\Components\TextInput::make('modelo')->maxLength(80),
                    Forms\Components\DatePicker::make('fecha_calibracion')->label('Fecha de calibración'),

                    Forms\Components\Toggle::make('activo')
                        ->label('Activo')
                        ->inline(false)
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('unidad_defecto')
                    ->label('Unidad'),

                Tables\Columns\IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('activo')
                    ->label('Solo activos'),

                Tables\Filters\SelectFilter::make('tipo')
                    ->label('Tipo de método')
                    ->options([
                        'manual'        => 'Manual',
                        'digital'       => 'Digital',
                        'inclinometro'  => 'Inclinómetro',
                        'imu'           => 'IMU / wearable',
                        'visual'        => 'Evaluación visual',
                    ])
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('nombre');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMetodoRoms::route('/'),
            'create' => Pages\CreateMetodoRom::route('/create'),
            'edit'   => Pages\EditMetodoRom::route('/{record}/edit'),
        ];
    }
}
