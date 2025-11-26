<?php

namespace App\Filament\Kinesiologa\Resources\PacienteResource\RelationManagers;

use App\Models\EntradaHc;
use App\Models\EstudioImagen;
use Filament\Forms;
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
use Illuminate\Support\Facades\Storage;

class EstudiosImagenRelationManager extends RelationManager
{
    protected static string $relationship = 'estudiosImagen';
    protected static ?string $title = 'Estudios de imagen';
    protected static ?string $icon  = 'heroicon-o-photo';

    /**
     * Siempre devolvemos un Builder con modelo para evitar evaluaciones
     * tempranas de Filament que esperen ->getModel()::class.
     */
    protected function getTableQuery(): Builder
    {
        return EstudioImagen::query();
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->estudiosImagen()->count();
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('viewAny', EstudioImagen::class);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\Select::make('tipo')
                    ->label('Tipo')
                    ->options([
                        'Radiografía' => 'Radiografía',
                        'Resonancia'  => 'Resonancia (RMN)',
                        'Tomografía'  => 'Tomografía (TAC)',
                        'Ecografía'   => 'Ecografía',
                        'Laboratorio' => 'Laboratorio (PDF)',
                        'Otro'        => 'Otro',
                    ])
                    ->required()
                    ->native(false),

                Forms\Components\DatePicker::make('fecha')
                    ->label('Fecha')
                    ->required()
                    ->default(today())
                    ->maxDate(today())
                    ->displayFormat('d/m/Y'),

                Forms\Components\Radio::make('origen')
                    ->label('Origen del archivo')
                    ->options(['upload' => 'Subir archivo', 'url' => 'URL externa'])
                    ->default('upload')
                    ->live()
                    ->dehydrated(false) // no se guarda; lo normalizamos en mutateFormDataUsing
                    ->afterStateHydrated(function (Set $set, ?EstudioImagen $record) {
                        if ($record) {
                            $set('origen', $record->archivo_path ? 'upload' : 'url');
                        }
                    }),
            ]),

            // Subida local
            Forms\Components\FileUpload::make('archivo_path')
                ->label('Archivo')
                ->disk('public')
                ->directory(fn() => 'pacientes/' . optional($this->getOwnerRecord())->paciente_id . '/estudios/' . now()->format('Y-m-d'))
                ->preserveFilenames()
                ->getUploadedFileNameForStorageUsing(function ($file): string {
                    $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                    $ext  = $file->getClientOriginalExtension();
                    return now()->format('His') . '-' . str($name)->slug()->limit(40, '') . '.' . $ext;
                })
                ->acceptedFileTypes(['application/pdf', 'image/*'])
                ->maxSize(10240) // 10 MB
                ->required(fn(Get $get) => $get('origen') === 'upload')
                ->openable()
                ->downloadable()
                ->previewable(true)
                ->visible(fn(Get $get) => $get('origen') === 'upload'),

            // URL externa
            Forms\Components\TextInput::make('archivo_url')
                ->label('URL del estudio')
                ->url()
                ->placeholder('https://...')
                ->required(fn(Get $get) => $get('origen') === 'url')
                ->visible(fn(Get $get) => $get('origen') === 'url'),

            Forms\Components\Textarea::make('informe')
                ->label('Informe / notas')
                ->rows(4)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('fecha')
                    ->label('Fecha')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('informe')
                    ->label('Informe')
                    ->limit(60)
                    ->tooltip(fn($state) => $state ?: null),

                // Columna virtual que siempre muestra el enlace correcto
                Tables\Columns\TextColumn::make('archivo_label')
                    ->label('Archivo')
                    ->state(
                        fn(EstudioImagen $record) =>
                        $record->archivo_path
                            ? 'Ver / descargar'
                            : ($record->archivo_url ? 'Abrir enlace' : '—')
                    )
                    ->url(
                        fn(EstudioImagen $record) =>
                        $record->archivo_path
                            ? Storage::disk($record->archivo_disk ?: 'public')->url($record->archivo_path)
                            : ($record->archivo_url ?: null)
                    )
                    ->openUrlInNewTab()
                    ->sortable(false)
                    ->searchable(false),
            ])

            ->modifyQueryUsing(function (Builder $q) {
                $paciente = $this->getOwnerRecord();

                if ($paciente) {
                    $q->whereIn('entrada_hc_id', $paciente->entradasHc()->select('entrada_hc_id'))
                        ->latest('fecha');
                } else {
                    $q->whereRaw('1 = 0');
                }
            })

            ->paginated(false)

            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn() => Gate::allows('create', EstudioImagen::class))
                    ->modalHeading('Registrar estudio')
                    ->modalWidth('lg')
                    ->mutateFormDataUsing(function (array $data): array {
                        // Normalizamos según origen
                        if (($data['origen'] ?? 'upload') === 'upload') {
                            $data['archivo_url']  = null;
                            $data['archivo_disk'] = 'public';
                        } else {
                            $data['archivo_path'] = null;
                        }
                        unset($data['origen']);
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
                                'creado_por'     => auth()->id(),
                            ]);
                        }

                        $data['entrada_hc_id'] = $entrada->entrada_hc_id;

                        $record = EstudioImagen::create($data);

                        Notification::make()
                            ->title('Estudio guardado')
                            ->success()
                            ->send();

                        return $record;
                    }),
            ])

            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn(EstudioImagen $record): bool => Gate::allows('update', $record))
                    ->mutateFormDataUsing(function (array $data): array {
                        if (($data['origen'] ?? null) === 'upload') {
                            $data['archivo_url']  = null;
                            $data['archivo_disk'] = 'public';
                        } elseif (($data['origen'] ?? null) === 'url') {
                            $data['archivo_path'] = null;
                        }
                        unset($data['origen']);
                        return $data;
                    }),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn(EstudioImagen $record): bool => Gate::allows('delete', $record))
                    ->requiresConfirmation(),
            ])

            ->emptyStateHeading('Sin estudios cargados')
            ->emptyStateDescription('Registrá el primer estudio para este paciente.');
    }
}
