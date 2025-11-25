<?php

namespace App\Filament\Kinesiologa\Resources\PacienteResource\RelationManagers;

use App\Models\Antropometria;
use App\Models\EntradaHc;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class AntropometriasRelationManager extends RelationManager
{
    protected static string $relationship = 'antropometrias';

    protected static ?string $title = 'Antropometría';
    protected static ?string $icon  = 'heroicon-o-scale';

    public static function getRelationshipName(): string
    {
        return 'antropometrias';
    }

    protected function getTableQuery(): Builder
    {
        return Antropometria::query();
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->antropometrias()->count();
    }

    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        return 'info';
    }

    public static function getBadgeTooltip(Model $ownerRecord, string $pageClass): ?string
    {
        return 'Registros de medidas antropométricas';
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('viewAny', Antropometria::class);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(6)->schema([
                Forms\Components\DatePicker::make('fecha')
                    ->label('Fecha')
                    ->required()
                    ->default(today())
                    ->columnSpan(2),

                Forms\Components\TextInput::make('altura_cm')
                    ->label('Altura (cm)')
                    ->numeric()
                    ->minValue(30)->maxValue(260)
                    ->step('0.1')
                    ->required()
                    ->columnSpan(2),

                Forms\Components\TextInput::make('peso_kg')
                    ->label('Peso (kg)')
                    ->numeric()
                    ->minValue(2)->maxValue(400)
                    ->step('0.1')
                    ->required()
                    ->columnSpan(2),

                Forms\Components\TextInput::make('imc_preview')
                    ->label('IMC (auto)')
                    ->dehydrated(false)
                    ->disabled()
                    ->columnSpanFull()
                    ->formatStateUsing(function (Get $get) {
                        $h = (float) $get('altura_cm');
                        $p = (float) $get('peso_kg');
                        if ($h <= 0 || $p <= 0) return null;
                        $m = $h / 100;
                        return number_format($p / ($m * $m), 2, ',', '.');
                    })
                    ->helperText('Se calcula con Peso / (Altura en metros)^2'),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('fecha')
                    ->label('Fecha')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('altura_cm')
                    ->label('Altura')
                    ->formatStateUsing(fn($state) => $state !== null ? number_format((float) $state, 1, ',', '.') . ' cm' : '-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('peso_kg')
                    ->label('Peso')
                    ->formatStateUsing(fn($state) => $state !== null ? number_format((float) $state, 1, ',', '.') . ' kg' : '-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('imc') // accessor del modelo
                    ->label('IMC')
                    ->badge()
                    ->formatStateUsing(fn($state) => $state !== null ? number_format((float) $state, 2, ',', '.') : '—')
                    ->color(function ($state) {
                        if ($state === null) return 'gray';
                        return match (true) {
                            $state < 18.5       => 'warning',
                            $state < 25         => 'success',
                            $state < 30         => 'warning',
                            default             => 'danger',
                        };
                    })
                    ->tooltip(function ($state) {
                        if ($state === null) return 'Sin datos';
                        return match (true) {
                            $state < 18.5       => 'Bajo peso',
                            $state < 25         => 'Normopeso',
                            $state < 30         => 'Sobrepeso',
                            default             => 'Obesidad',
                        };
                    })
                    ->sortable(),
            ])
            ->modifyQueryUsing(function (Builder $q) {
                /** @var \App\Models\Paciente|null $paciente */
                $paciente = $this->getOwnerRecord();

                if ($paciente) {
                    $q->whereIn('entrada_hc_id', $paciente->entradasHc()->select('entrada_hc_id'))
                        ->orderByDesc('fecha');
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->paginated(false)
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn() => Gate::allows('create', Antropometria::class))
                    ->modalHeading('Registrar antropometría')
                    ->modalWidth('lg')
                    ->using(function (array $data, RelationManager $livewire): Model {
                        /** @var \App\Models\Paciente $paciente */
                        $paciente = $livewire->getOwnerRecord();

                        $entrada = $paciente->entradasHc()
                            ->whereDate('fecha', today())
                            ->latest('entrada_hc_id')
                            ->first();

                        if (! $entrada) {
                            $entrada = EntradaHc::create([
                                'paciente_id'    => $paciente->paciente_id,
                                'fecha'          => today(),
                                'fecha_creacion' => now(),
                                'creado_por'     => auth()->id(),
                            ]);
                        }

                        $data['entrada_hc_id'] = $entrada->entrada_hc_id;

                        return Antropometria::create($data);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => Gate::allows('update', $record)),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn($record) => Gate::allows('delete', $record))
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn() => Gate::allows('deleteAny', Antropometria::class)),
                ]),
            ])
            ->emptyStateHeading('Sin registros')
            ->emptyStateDescription('Agregá la primera medición antropométrica.');
    }
}
