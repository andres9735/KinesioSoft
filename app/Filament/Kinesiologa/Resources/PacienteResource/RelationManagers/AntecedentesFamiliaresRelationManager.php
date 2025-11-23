<?php

namespace App\Filament\Kinesiologa\Resources\PacienteResource\RelationManagers;

use App\Models\AntecedenteFamiliar;
use App\Models\EntradaHc;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class AntecedentesFamiliaresRelationManager extends RelationManager
{
    /** Debe coincidir con el método en App\Models\Paciente */
    protected static string $relationship = 'antecedentesFamiliares';

    protected static ?string $title = 'Antecedentes familiares';
    protected static ?string $icon  = 'heroicon-o-users';

    /** Badge con el conteo */
    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->antecedentesFamiliares()->count();
    }

    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        return 'primary';
    }

    public static function getBadgeTooltip(Model $ownerRecord, string $pageClass): ?string
    {
        return 'Cantidad de antecedentes familiares';
    }

    /** Ocultar pestaña si no tiene permiso */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('viewAny', AntecedenteFamiliar::class);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('parentesco')
                ->label('Parentesco')
                ->placeholder('Madre, Padre, Hermano/a, Hijo/a, etc.')
                ->required()
                ->maxLength(50),

            Forms\Components\Select::make('lado_familia')
                ->label('Lado de la familia')
                ->options([
                    'materno'       => 'Materno',
                    'paterno'       => 'Paterno',
                    'ambos'         => 'Ambos',
                    'desconocido'   => 'Desconocido',
                    'no_especifica' => 'No especifica',
                ])
                ->default('no_especifica')
                ->required(),

            Forms\Components\Textarea::make('observaciones')
                ->label('Observaciones')
                ->rows(3)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('titulo')

            ->modifyQueryUsing(
                fn(Builder $query) => $query
                    ->orderByDesc('antecedente_familiar_id')
            )

            ->filtersLayout(FiltersLayout::AboveContent)
            ->filters([
                Tables\Filters\SelectFilter::make('lado_familia')
                    ->label('Lado')
                    ->options([
                        'materno'       => 'Materno',
                        'paterno'       => 'Paterno',
                        'ambos'         => 'Ambos',
                        'desconocido'   => 'Desconocido',
                        'no_especifica' => 'No especifica',
                    ])
                    ->indicator('Lado'),
            ])

            ->columns([
                Tables\Columns\TextColumn::make('parentesco')
                    ->label('Parentesco')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('lado_familia')
                    ->label('Lado')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => str_replace('_', ' ', $state))
                    ->color(fn(string $state) => match ($state) {
                        'materno'       => 'info',
                        'paterno'       => 'warning',
                        'ambos'         => 'primary',
                        'desconocido'   => 'gray',
                        'no_especifica' => 'gray',
                        default         => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('observaciones')
                    ->label('Observaciones')
                    ->limit(60)
                    ->tooltip(fn($record) => $record->observaciones)
                    ->wrap(),
            ])

            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn() => Gate::allows('create', AntecedenteFamiliar::class))
                    ->modalHeading('Nuevo antecedente familiar')
                    ->modalWidth('lg')
                    ->using(function (array $data, RelationManager $livewire): Model {
                        /** @var \App\Models\Paciente $paciente */
                        $paciente = $livewire->getOwnerRecord();

                        // Reutiliza la entrada del día o crea una nueva
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

                        $record = AntecedenteFamiliar::create($data);

                        Notification::make()
                            ->title('Antecedente familiar creado')
                            ->success()
                            ->send();

                        return $record;
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
                        ->visible(fn() => Gate::allows('deleteAny', AntecedenteFamiliar::class)),
                ]),
            ])

            ->paginated(false) // sin paginación, como acordamos
            ->emptyStateHeading('Sin antecedentes familiares')
            ->emptyStateDescription('Crea el primer antecedente familiar de este paciente.');
    }
}
