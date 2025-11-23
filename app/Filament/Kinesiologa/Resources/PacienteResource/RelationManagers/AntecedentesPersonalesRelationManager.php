<?php

namespace App\Filament\Kinesiologa\Resources\PacienteResource\RelationManagers;

use App\Models\AntecedentePersonal;
use App\Models\EntradaHc;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Enums\FiltersLayout;     // filtros arriba
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;    // tipado para evitar [$q] unresolvable
use Illuminate\Support\Facades\Gate;         // checks de permisos

class AntecedentesPersonalesRelationManager extends RelationManager
{
    protected static string $relationship = 'antecedentesPersonales';

    protected static ?string $title = 'Antecedentes personales';
    protected static ?string $icon  = 'heroicon-o-clipboard-document-list';

    /** Badge con el conteo en la pestaña. */
    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->antecedentesPersonales()->count();
    }

    /** Color del badge (firma compatible con Filament v3). */
    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        return 'primary';
    }

    /** Tooltip del badge. */
    public static function getBadgeTooltip(Model $ownerRecord, string $pageClass): ?string
    {
        return 'Cantidad de antecedentes personales';
    }

    /** Oculta la pestaña si el usuario no tiene permiso (policy: viewAny). */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('viewAny', AntecedentePersonal::class);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('tipo_id')
                ->label('Tipo')
                ->relationship('tipo', 'nombre')
                ->searchable()
                ->preload()
                ->required(),

            Forms\Components\Textarea::make('descripcion')
                ->label('Descripción')
                ->rows(3)
                ->maxLength(1000)
                ->columnSpanFull(),

            Forms\Components\Grid::make(2)->schema([
                Forms\Components\DatePicker::make('fecha_inicio')->label('Fecha inicio'),
                Forms\Components\DatePicker::make('fecha_fin')->label('Fecha fin'),
            ]),

            Forms\Components\Select::make('estado')
                ->label('Estado')
                ->options([
                    'activo'   => 'Activo',
                    'resuelto' => 'Resuelto',
                    'crónico'  => 'Crónico',
                ])
                ->default('activo')
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->paginated(false) // ✅ sin paginación (muestra todos los registros)
            ->recordTitleAttribute('titulo')

            // Eager-load y orden (por fecha_inicio y luego por PK)
            ->modifyQueryUsing(
                fn(Builder $query) => $query
                    ->with('tipo')
                    ->orderByDesc('fecha_inicio')
                    ->orderByDesc('antecedente_personal_id')
            )

            // ── Filtros arriba del listado
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filters([
                Tables\Filters\SelectFilter::make('tipo_id')
                    ->label('Tipo')
                    ->relationship('tipo', 'nombre')
                    ->indicator('Tipo'),

                Tables\Filters\TernaryFilter::make('solo_activos')
                    ->label('¿Activo?')
                    ->trueLabel('Solo activos')
                    ->falseLabel('Solo no activos')
                    ->queries(
                        true: fn(Builder $q) => $q->where('estado', 'activo'),
                        false: fn(Builder $q) => $q->where('estado', '!=', 'activo'),
                    )
                    ->indicator('Activos'),
            ])

            ->columns([
                Tables\Columns\TextColumn::make('tipo.nombre')
                    ->label('Tipo')
                    ->badge()
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('descripcion')
                    ->label('Descripción')
                    ->limit(60)
                    ->tooltip(fn($record) => $record->descripcion)
                    ->wrap()
                    ->searchable(),

                Tables\Columns\TextColumn::make('fecha_inicio')
                    ->label('Inicio')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('fecha_fin')
                    ->label('Fin')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('estado')
                    ->badge()
                    ->color(fn(string $state) => match ($state) {
                        'activo'   => 'danger',
                        'resuelto' => 'success',
                        'crónico'  => 'warning',
                        default    => 'gray',
                    }),
            ])

            ->headerActions([
                // Modal centrado con ancho razonable
                Tables\Actions\CreateAction::make()
                    ->visible(fn() => Gate::allows('create', AntecedentePersonal::class))
                    ->modalHeading('Nuevo antecedente personal')
                    ->modalWidth('lg')
                    ->using(function (array $data, RelationManager $livewire): Model {
                        /** @var \App\Models\Paciente $paciente */
                        $paciente = $livewire->getOwnerRecord();

                        // Reutiliza entrada del día o crea una nueva
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

                        $record = AntecedentePersonal::create($data);

                        Notification::make()
                            ->title('Antecedente creado')
                            ->success()
                            ->send();

                        return $record;
                    }),
                // (Se quitó el botón “Agregar Cefalea”)
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
                        ->visible(fn() => Gate::allows('deleteAny', AntecedentePersonal::class)),
                ]),
            ])

            ->emptyStateHeading('Sin antecedentes registrados')
            ->emptyStateDescription('Crea el primer antecedente personal de este paciente.');
    }
}
