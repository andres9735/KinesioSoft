<?php

namespace App\Filament\Kinesiologa\Resources\PacienteResource\RelationManagers;

use App\Models\Alergia;
use App\Models\EntradaHc;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class AlergiasRelationManager extends RelationManager
{
    /** Debe coincidir con el método en App\Models\Paciente */
    protected static string $relationship = 'alergias';

    protected static ?string $title = 'Alergias';
    protected static ?string $icon  = 'heroicon-o-exclamation-triangle';

    /** Badge con el conteo */
    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->alergias()->count();
    }

    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        return 'warning';
    }

    public static function getBadgeTooltip(Model $ownerRecord, string $pageClass): ?string
    {
        return 'Cantidad de alergias registradas';
    }

    /** Ocultar pestaña si no tiene permiso */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('viewAny', Alergia::class);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('sustancia')
                ->label('Sustancia')
                ->required()
                ->maxLength(100)
                ->placeholder('Ibuprofeno, Penicilina, Látex, Polen...'),

            Forms\Components\TextInput::make('reaccion')
                ->label('Reacción')
                ->required()
                ->maxLength(100)
                ->placeholder('Urticaria, rinitis, disnea...'),

            Forms\Components\Select::make('gravedad')
                ->label('Gravedad')
                ->options([
                    Alergia::G_LEVE        => 'Leve',
                    Alergia::G_MODERADA    => 'Moderada',
                    Alergia::G_SEVERA      => 'Severa',
                    Alergia::G_ANAFILAXIA  => 'Anafilaxia',
                    Alergia::G_DESCONOCIDA => 'Desconocida',
                ])
                ->default(Alergia::G_DESCONOCIDA)
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
            // Usamos un atributo real del modelo para el título del registro
            ->recordTitleAttribute('sustancia')

            // Igual que en los otros RM: no definimos query(), solo aplicamos scopes/orden.
            ->modifyQueryUsing(
                fn(Builder $query) => $query
                    ->orderByRaw("FIELD(gravedad,'anafilaxia','severa','moderada','leve','desconocida')")
                    ->orderBy('sustancia')
            )

            ->filtersLayout(FiltersLayout::AboveContent)
            ->filters([
                Tables\Filters\SelectFilter::make('gravedad')
                    ->label('Gravedad')
                    ->options([
                        Alergia::G_LEVE        => 'Leve',
                        Alergia::G_MODERADA    => 'Moderada',
                        Alergia::G_SEVERA      => 'Severa',
                        Alergia::G_ANAFILAXIA  => 'Anafilaxia',
                        Alergia::G_DESCONOCIDA => 'Desconocida',
                    ])
                    ->indicator('Gravedad'),
            ])

            ->columns([
                Tables\Columns\TextColumn::make('sustancia')
                    ->label('Sustancia')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('reaccion')
                    ->label('Reacción')
                    ->limit(40)
                    // 👇 Tipamos el parámetro para evitar el error de “unresolvable”
                    ->tooltip(fn(Model $record) => $record->reaccion)
                    ->searchable(),

                Tables\Columns\TextColumn::make('gravedad')
                    ->label('Gravedad')
                    ->badge()
                    ->color(fn(string $state) => match ($state) {
                        'leve'        => 'info',
                        'moderada'    => 'warning',
                        'severa'      => 'danger',
                        'anafilaxia'  => 'danger',
                        default       => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->date(),
            ])

            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn() => Gate::allows('create', Alergia::class))
                    ->modalHeading('Nueva alergia')
                    ->modalWidth('lg')
                    ->using(function (array $data, RelationManager $livewire): Model {
                        /** @var \App\Models\Paciente $paciente */
                        $paciente = $livewire->getOwnerRecord();

                        // Reutiliza/crea entrada HC del día
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

                        $record = Alergia::create($data);

                        Notification::make()
                            ->title('Alergia registrada')
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
                        ->visible(fn() => Gate::allows('deleteAny', Alergia::class)),
                ]),
            ])

            ->paginated(false)
            ->emptyStateHeading('Sin alergias registradas')
            ->emptyStateDescription('Crea la primera alergia para este paciente.');
    }
}
