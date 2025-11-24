<?php

namespace App\Filament\Kinesiologa\Resources\PacienteResource\RelationManagers;

use App\Models\MedicacionActual;
use App\Models\EntradaHc;
use Filament\Forms;
use Filament\Facades\Filament;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class MedicacionesActualesRelationManager extends RelationManager
{
    /** Debe coincidir con el método en App\Models\Paciente */
    protected static string $relationship = 'medicacionesActuales';

    protected static ?string $title = 'Medicación actual';
    protected static ?string $icon  = 'heroicon-o-beaker';

    /** Por si algún cache ignora la propiedad estática */
    public static function getRelationshipName(): string
    {
        return 'medicacionesActuales';
    }

    /**
     * Punto clave: SIEMPRE devolver un Builder con modelo,
     * sin depender de ownerRecord.
     */
    protected function getTableQuery(): Builder
    {
        return MedicacionActual::query();
    }

    // ---------- Badges ----------
    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->medicacionesActuales()->count();
    }

    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        return 'success';
    }

    public static function getBadgeTooltip(Model $ownerRecord, string $pageClass): ?string
    {
        return 'Cantidad de registros de medicación';
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('viewAny', MedicacionActual::class);
    }

    // ---------- Form ----------
    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Datos del fármaco')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('farmaco')
                        ->label('Fármaco')
                        ->placeholder('Ej: Ibuprofeno')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('dosis')
                        ->label('Dosis (texto)')
                        ->placeholder('Ej: 600 mg')
                        ->helperText('Si preferís, abajo podés cargar la dosis estructurada.')
                        ->maxLength(50),

                    Forms\Components\TextInput::make('frecuencia')
                        ->label('Frecuencia (texto)')
                        ->placeholder('Ej: cada 8 h')
                        ->helperText('Opcional si usás la frecuencia estructurada.')
                        ->maxLength(50),
                ]),

            Forms\Components\Section::make('Estructurado (opcional)')
                ->columns(6)
                ->collapsible()
                ->schema([
                    // Dosis
                    Forms\Components\TextInput::make('dosis_cantidad')
                        ->label('Dosis')
                        ->numeric()
                        ->minValue(0.01)
                        ->step('0.01')
                        ->columnSpan(2)
                        ->placeholder('p. ej. 0.5, 500'),

                    Forms\Components\Select::make('dosis_unidad')
                        ->label('Unidad')
                        ->options([
                            'mg' => 'mg',
                            'g' => 'g',
                            'mcg' => 'mcg',
                            'ml' => 'ml',
                            'gotas' => 'gotas',
                            'comprimido' => 'comprimido',
                            'capsula' => 'cápsula',
                            'ui' => 'UI',
                            'puff' => 'puff',
                            'otra' => 'otra',
                        ])
                        ->native(false)
                        ->columnSpan(2),

                    Forms\Components\Toggle::make('prn')
                        ->label('PRN (según necesidad)')
                        ->inline(false)
                        ->columnSpan(2),

                    // Frecuencia estructurada (una u otra)
                    Forms\Components\TextInput::make('cada_horas')
                        ->label('Cada (horas)')
                        ->numeric()
                        ->minValue(1)->maxValue(24)
                        ->placeholder('p. ej. 8')
                        ->live()
                        ->afterStateUpdated(fn(Set $set, $state) => $state ? $set('veces_por_dia', null) : null)
                        ->columnSpan(2),

                    Forms\Components\TextInput::make('veces_por_dia')
                        ->label('Veces por día')
                        ->numeric()
                        ->minValue(1)->maxValue(12)
                        ->placeholder('p. ej. 3')
                        ->live()
                        ->afterStateUpdated(fn(Set $set, $state) => $state ? $set('cada_horas', null) : null)
                        ->columnSpan(2),

                    Forms\Components\Select::make('frecuencia_unidad')
                        ->label('Unidad')
                        ->options(['hora' => 'hora', 'dia' => 'día', 'semana' => 'semana', 'mes' => 'mes'])
                        ->native(false)
                        ->placeholder('auto')
                        ->helperText('Usá esto solo si no usás “cada X horas” / “veces por día”.')
                        ->columnSpan(2),

                    Forms\Components\Select::make('via')
                        ->label('Vía de administración')
                        ->options([
                            'oral' => 'Oral',
                            'topica' => 'Tópica',
                            'transdermica' => 'Transdérmica',
                            'inhalatoria' => 'Inhalatoria',
                            'intramuscular' => 'Intramuscular',
                            'intravenosa' => 'Intravenosa',
                            'subcutanea' => 'Subcutánea',
                            'otra' => 'Otra',
                        ])
                        ->native(false)
                        ->columnSpan(3),
                ]),

            Forms\Components\Section::make('Vigencia y notas')
                ->columns(6)
                ->schema([
                    Forms\Components\DatePicker::make('fecha_desde')
                        ->label('Desde')
                        ->required()
                        ->default(today())
                        ->columnSpan(3),

                    Forms\Components\DatePicker::make('fecha_hasta')
                        ->label('Hasta')
                        ->helperText('Dejar vacío si sigue en curso.')
                        ->rule('after_or_equal:fecha_desde')
                        ->columnSpan(3),

                    Forms\Components\Toggle::make('en_curso')
                        ->label('En curso (sin fecha de fin)')
                        ->inline(false)
                        ->dehydrated(false) // no se guarda en DB
                        ->live()
                        ->afterStateUpdated(fn(Set $set, $state) => $state ? $set('fecha_hasta', null) : null),

                    Forms\Components\Textarea::make('observaciones')
                        ->label('Observaciones')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }


    // ---------- Tabla ----------
    public function table(Table $table): Table
    {
        return $table
            // No usamos recordTitleAttribute ni filters para evitar lookups tempranos.
            ->columns([
                Tables\Columns\TextColumn::make('farmaco')
                    ->label('Fármaco')
                    ->searchable()
                    ->sortable(),

                // 🔽 Dosis derivada: usa estructurado y cae al texto si no hay
                Tables\Columns\TextColumn::make('dosis_mostrada')
                    ->label('Dosis')
                    ->state(function (MedicacionActual $r) {
                        if ($r->dosis_cantidad && $r->dosis_unidad) {
                            // 1 ó 1.5, etc. (sin ceros de más)
                            $n = rtrim(rtrim(number_format((float) $r->dosis_cantidad, 2, '.', ''), '0'), '.');
                            return "{$n} {$r->dosis_unidad}";
                        }
                        return $r->dosis ?: '—';
                    })
                    ->tooltip(
                        fn(MedicacionActual $r) =>
                        $r->dosis_cantidad && $r->dosis_unidad
                            ? "{$r->dosis_cantidad} {$r->dosis_unidad}"
                            : ($r->dosis ?: null)
                    )
                    ->sortable(query: function (Builder $q, string $dir) {
                        return $q->orderByRaw("COALESCE(dosis_cantidad, 0) {$dir}")
                            ->orderBy('dosis_unidad', $dir)
                            ->orderBy('dosis', $dir);
                    }),

                // 🔽 Frecuencia derivada: c/horas → veces/día → texto
                Tables\Columns\TextColumn::make('frecuencia_mostrada')
                    ->label('Frecuencia')
                    ->state(function (MedicacionActual $r) {
                        if ($r->cada_horas)     return "cada {$r->cada_horas}hs";
                        if ($r->veces_por_dia)  return "{$r->veces_por_dia}×/día";
                        return $r->frecuencia ?: '—';
                    })
                    ->tooltip(fn(MedicacionActual $r) => $r->frecuencia ?: null)
                    ->sortable(query: function (Builder $q, string $dir) {
                        // primero las que tienen estructura
                        return $q->orderByRaw("CASE WHEN cada_horas IS NULL THEN 1 ELSE 0 END {$dir}")
                            ->orderByRaw("COALESCE(cada_horas, 999) {$dir}")
                            ->orderByRaw("COALESCE(veces_por_dia, 999) {$dir}")
                            ->orderBy('frecuencia', $dir);
                    }),

                Tables\Columns\TextColumn::make('fecha_desde')
                    ->label('Desde')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('fecha_hasta')
                    ->label('Hasta')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('via')
                    ->label('Vía')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\IconColumn::make('prn')
                    ->label('PRN')
                    ->boolean(),
            ])

            ->filters([
                Tables\Filters\TernaryFilter::make('prn')
                    ->label('Solo PRN')
                    ->nullable(), // posición intermedia = “todos”
            ])
            ->filtersLayout(FiltersLayout::AboveContent)

            /**
             * Acá recién “filtramos” por el paciente, cuando ownerRecord ya está seteado.
             * Si aún no está, devolvemos 0 filas, pero el Builder SIGUE teniendo modelo.
             */
            ->modifyQueryUsing(function (Builder $q) {
                /** @var \App\Models\Paciente|null $paciente */
                $paciente = $this->getOwnerRecord();

                if ($paciente) {
                    $q->whereIn('entrada_hc_id', $paciente->entradasHc()->select('entrada_hc_id'))
                        ->orderByRaw("(CASE WHEN fecha_hasta IS NULL OR fecha_hasta >= CURDATE() THEN 1 ELSE 0 END) DESC")
                        ->orderByDesc('fecha_desde')
                        ->orderBy('farmaco');
                } else {
                    $q->whereRaw('1 = 0');
                }
            })

            ->paginated(false)

            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn() => Gate::allows('create', MedicacionActual::class))
                    ->modalHeading('Registrar medicación')
                    ->modalWidth('lg')
                    ->mutateFormDataUsing(function (array $data): array {
                        // En curso => sin fecha_hasta
                        if (!empty($data['en_curso'])) {
                            $data['fecha_hasta'] = null;
                        }
                        unset($data['en_curso']);

                        // Vacíos a null para columnas opcionales
                        foreach (['dosis', 'frecuencia', 'dosis_cantidad', 'dosis_unidad', 'cada_horas', 'veces_por_dia', 'frecuencia_unidad', 'via', 'observaciones'] as $k) {
                            if (isset($data[$k]) && $data[$k] === '') {
                                $data[$k] = null;
                            }
                        }

                        return $data;
                    })
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
                                'creado_por'     => Filament::auth()->id(),
                            ]);
                        }

                        $data['entrada_hc_id'] = $entrada->entrada_hc_id;

                        $record = MedicacionActual::create($data);

                        Notification::make()
                            ->title('Medicación registrada')
                            ->success()
                            ->send();

                        return $record;
                    })
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
                        ->visible(fn() => Gate::allows('deleteAny', MedicacionActual::class)),
                ]),
            ])

            ->emptyStateHeading('Sin medicación registrada')
            ->emptyStateDescription('Agregá la primera medicación actual de este paciente.');
    }
}
