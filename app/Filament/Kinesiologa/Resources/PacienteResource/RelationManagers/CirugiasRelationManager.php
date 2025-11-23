<?php

namespace App\Filament\Kinesiologa\Resources\PacienteResource\RelationManagers;

use App\Models\Cirugia;
use App\Models\EntradaHc;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class CirugiasRelationManager extends RelationManager
{
    protected static string $relationship = 'cirugias';

    protected static ?string $title = 'Cirugías';
    protected static ?string $icon  = 'heroicon-o-scissors';

    /** ¡Blindaje total! Filament usará este builder SIEMPRE. */
    protected function getTableQuery(): Builder
    {
        /** @var \App\Models\Paciente|null $paciente */
        $paciente = $this->getOwnerRecord();

        if (! $paciente) {
            return Cirugia::query()->whereRaw('1 = 0'); // builder válido pero vacío
        }

        // Usa la relación real (HasManyThrough) y devuelve su Builder Eloquent
        return $paciente->cirugias()->getQuery();
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->cirugias()->count();
    }

    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        return 'primary';
    }

    public static function getBadgeTooltip(Model $ownerRecord, string $pageClass): ?string
    {
        return 'Cantidad de cirugías registradas';
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('viewAny', Cirugia::class);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('procedimiento')
                ->label('Procedimiento')
                ->required()
                ->maxLength(150),

            Forms\Components\DatePicker::make('fecha')
                ->label('Fecha')
                ->required()
                ->default(today()),

            Forms\Components\Select::make('lateralidad')
                ->label('Lateralidad')
                ->options([
                    Cirugia::L_IZQUIERDA   => 'Izquierda',
                    Cirugia::L_DERECHA     => 'Derecha',
                    Cirugia::L_BILATERAL   => 'Bilateral',
                    Cirugia::L_NO_APLICA   => 'No aplica',
                    Cirugia::L_DESCONOCIDA => 'Desconocida',
                ])
                ->default(Cirugia::L_NO_APLICA)
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
            ->columns([
                Tables\Columns\TextColumn::make('procedimiento')
                    ->label('Procedimiento')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('fecha')
                    ->label('Fecha')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('lateralidad')
                    ->label('Lateralidad')
                    ->badge()
                    // 👇 USAR $state (no $s)
                    ->formatStateUsing(fn(string $state): string => str_replace('_', ' ', $state))
                    ->color(fn(string $state) => match ($state) {
                        'bilateral'   => 'primary',
                        'izquierda'   => 'info',
                        'derecha'     => 'warning',
                        'no_aplica'   => 'gray',
                        'desconocida' => 'gray',
                        default       => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('observaciones')
                    ->label('Observaciones')
                    ->limit(60)
                    // 👇 USAR $record (no $r)
                    ->tooltip(fn(Model $record) => $record->observaciones),
            ])
            ->paginated(false)
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn() => Gate::allows('create', Cirugia::class))
                    ->modalHeading('Registrar cirugía')
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

                        $record = Cirugia::create($data);

                        Notification::make()
                            ->title('Cirugía registrada')
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
                        ->visible(fn() => Gate::allows('deleteAny', Cirugia::class)),
                ]),
            ])
            ->emptyStateHeading('Sin cirugías registradas')
            ->emptyStateDescription('Crea la primera cirugía para este paciente.');
    }
}
