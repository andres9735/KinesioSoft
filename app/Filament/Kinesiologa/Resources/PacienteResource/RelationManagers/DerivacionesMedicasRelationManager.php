<?php

namespace App\Filament\Kinesiologa\Resources\PacienteResource\RelationManagers;

use App\Enums\EstadoDerivacion;
use App\Models\DerivacionMedica;
use App\Models\EntradaHc;
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
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Illuminate\Support\Str;

class DerivacionesMedicasRelationManager extends RelationManager
{
    protected static string $relationship = 'derivacionesMedicas';
    protected static ?string $title = 'Derivaciones médicas';
    protected static ?string $icon  = 'heroicon-o-clipboard-document-check';

    protected function getTableQuery(): Builder
    {
        // Builder con modelo SIEMPRE para evitar ::class on null
        return DerivacionMedica::query();
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->derivacionesMedicas()->count();
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('viewAny', DerivacionMedica::class);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Datos de la derivación')
                ->columns(4)
                ->schema([
                    Forms\Components\DatePicker::make('fecha_emision')
                        ->label('Fecha emisión')
                        ->required()
                        ->default(today())
                        ->maxDate(today()),

                    Forms\Components\DatePicker::make('fecha_vencimiento')
                        ->label('Vence')
                        ->required()
                        ->minDate(fn(Get $get) => $get('fecha_emision')),

                    Forms\Components\Select::make('estado')
                        ->label('Estado')
                        ->options([
                            'emitida' => 'Emitida',
                            'vigente' => 'Vigente',
                            'vencida' => 'Vencida',
                            'anulada' => 'Anulada',
                        ])
                        ->default('emitida') // si usás enum: EstadoDerivacion::Emitida->value
                        ->required()
                        ->native(false)
                        ->rule(Rule::enum(EstadoDerivacion::class)),

                    Forms\Components\TextInput::make('sesiones_autorizadas')
                        ->label('Sesiones autorizadas')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(200)
                        ->required(),

                    Forms\Components\TextInput::make('medico_nombre')
                        ->label('Médico/a')
                        ->required()
                        ->maxLength(30)
                        ->columnSpan(2),

                    Forms\Components\TextInput::make('medico_matricula')
                        ->label('Matrícula')
                        ->required()
                        ->maxLength(30),

                    Forms\Components\TextInput::make('medico_especialidad')
                        ->label('Especialidad')
                        ->required()
                        ->maxLength(50),

                    Forms\Components\TextInput::make('institucion')
                        ->label('Institución')
                        ->required()
                        ->maxLength(50)
                        ->columnSpan(2),

                    Forms\Components\Textarea::make('diagnostico_texto')
                        ->label('Diagnóstico')
                        ->required()
                        ->rows(3)
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('indicaciones')
                        ->label('Indicaciones')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Archivo')
                ->columns(3)
                ->schema([
                    Forms\Components\Radio::make('origen')
                        ->label('Origen del archivo')
                        ->options(['upload' => 'Subir archivo (PDF)', 'url' => 'URL externa'])
                        ->default('upload')
                        ->live()
                        ->afterStateHydrated(function (Set $set, ?DerivacionMedica $record) {
                            if ($record) {
                                $set('origen', $record->archivo_path ? 'upload' : 'url');
                            }
                        }),

                    Forms\Components\FileUpload::make('archivo_path')
                        ->label('Archivo')
                        ->disk('public')
                        ->directory(fn() => 'pacientes/' . optional($this->getOwnerRecord())->paciente_id . '/derivaciones/' . now()->format('Y-m-d'))
                        ->acceptedFileTypes(['application/pdf'])
                        ->maxSize(10240)
                        ->openable()
                        ->downloadable()
                        ->previewable(true)
                        ->required(fn(Get $get) => $get('origen') === 'upload') // (1) requerido condicional
                        ->visible(fn(Get $get) => $get('origen') === 'upload')
                        ->columnSpan(2)
                        // (3) nombre de archivo robusto (evita .pdf.pdf y nombres raros)
                        ->getUploadedFileNameForStorageUsing(function (TemporaryUploadedFile $file): string {
                            $base = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'archivo';

                            // Limpia sufijos repetidos de ".pdf" en el nombre base
                            while (str_ends_with(strtolower($base), '.pdf')) {
                                $base = substr($base, 0, -4);
                            }

                            // Slug amigable y límite de longitud
                            $base = Str::slug($base) ?: 'archivo';
                            $base = Str::limit($base, 80, '');

                            // Extensión real (forzar pdf ante duda)
                            $ext = strtolower($file->getClientOriginalExtension() ?: 'pdf');

                            return "{$base}.{$ext}";
                        }),

                    Forms\Components\TextInput::make('archivo_url')
                        ->label('URL del archivo')
                        ->url()
                        ->placeholder('https://...')
                        ->required(fn(Get $get) => $get('origen') === 'url') // (1) requerido condicional
                        ->visible(fn(Get $get) => $get('origen') === 'url')
                        ->columnSpan(2),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('fecha_emision')
                    ->label('Emitida')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('fecha_vencimiento')
                    ->label('Vence')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('medico_nombre')
                    ->label('Médico/a')
                    ->searchable()
                    ->limit(28),

                Tables\Columns\TextColumn::make('institucion')
                    ->label('Institución')
                    ->searchable()
                    ->limit(28),

                Tables\Columns\TextColumn::make('sesiones_autorizadas')
                    ->label('Ses.')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('estado')
                    ->label('Estado')
                    ->colors([
                        'success' => fn($state) => $state === 'vigente',
                        'danger'  => fn($state) => $state === 'vencida',
                        'warning' => fn($state) => $state === 'anulada',
                        'gray'    => fn($state) => $state === 'emitida',
                    ])
                    ->sortable(),

                // Columna calculada para mostrar link del archivo
                Tables\Columns\TextColumn::make('archivo_link')
                    ->label('Archivo')
                    ->getStateUsing(function (DerivacionMedica $record): string {
                        if ($record->archivo_path) {
                            return 'Ver / descargar';
                        }
                        if ($record->archivo_url) {
                            return 'Abrir enlace';
                        }
                        return '—';
                    })
                    ->url(function (DerivacionMedica $record): ?string {
                        if ($record->archivo_path) {
                            $disk = $record->archivo_disk ?: 'public';
                            return Storage::disk($disk)->url($record->archivo_path);
                        }
                        return $record->archivo_url ?: null;
                    })
                    ->openUrlInNewTab()
                    // (2) tooltip con nombre de archivo o URL
                    ->tooltip(function (DerivacionMedica $record): ?string {
                        if ($record->archivo_path) {
                            return basename($record->archivo_path);
                        }
                        return $record->archivo_url ?: null;
                    })
                    ->extraAttributes(
                        fn(DerivacionMedica $record) => ($record->archivo_path || $record->archivo_url)
                            ? ['class' => 'text-primary-600 hover:underline']
                            : ['class' => 'text-gray-400']
                    ),
            ])
            ->modifyQueryUsing(function (Builder $q) {
                $paciente = $this->getOwnerRecord();
                if ($paciente) {
                    $q->whereIn('entrada_hc_id', $paciente->entradasHc()->select('entrada_hc_id'))
                        ->orderByDesc('fecha_emision');
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->paginated(false)
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn() => Gate::allows('create', DerivacionMedica::class))
                    ->modalHeading('Registrar derivación médica')
                    ->modalWidth('5xl')
                    ->mutateFormDataUsing(function (array $data): array {
                        if (($data['origen'] ?? 'upload') === 'upload') {
                            $data['archivo_url']  = null;
                            $data['archivo_disk'] = 'public';
                        } else {
                            $data['archivo_path'] = null;
                        }
                        unset($data['origen']);

                        // Estado por defecto según vencimiento si quedó en 'emitida'
                        if (($data['estado'] ?? 'emitida') === 'emitida') {
                            $data['estado'] = (now()->lte($data['fecha_vencimiento'])) ? 'vigente' : 'vencida';
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
                                'creado_por'     => auth()->id(),
                            ]);
                        }

                        $data['entrada_hc_id'] = $entrada->entrada_hc_id;

                        $record = DerivacionMedica::create($data);

                        Notification::make()->title('Derivación registrada')->success()->send();

                        return $record;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn(DerivacionMedica $r) => Gate::allows('update', $r))
                    ->mutateFormDataUsing(function (array $data): array {
                        if (($data['origen'] ?? null) === 'upload') {
                            $data['archivo_url']  = null;
                            $data['archivo_disk'] = 'public';
                        } elseif (($data['origen'] ?? null) === 'url') {
                            $data['archivo_path'] = null;
                        }
                        unset($data['origen']);

                        if (($data['estado'] ?? null) === 'emitida') {
                            $data['estado'] = (now()->lte($data['fecha_vencimiento'])) ? 'vigente' : 'vencida';
                        }

                        return $data;
                    })
                    ->fillForm(fn(DerivacionMedica $r): array => [
                        'fecha_emision'        => $r->fecha_emision,
                        'fecha_vencimiento'    => $r->fecha_vencimiento,
                        'estado'               => $r->estado,
                        'medico_nombre'        => $r->medico_nombre,
                        'medico_matricula'     => $r->medico_matricula,
                        'medico_especialidad'  => $r->medico_especialidad,
                        'institucion'          => $r->institucion,
                        'diagnostico_texto'    => $r->diagnostico_texto,
                        'indicaciones'         => $r->indicaciones,
                        'sesiones_autorizadas' => $r->sesiones_autorizadas,
                        'origen'               => $r->archivo_path ? 'upload' : 'url',
                        'archivo_path'         => $r->archivo_path,
                        'archivo_url'          => $r->archivo_url,
                    ]),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn(DerivacionMedica $r) => Gate::allows('delete', $r))
                    ->requiresConfirmation(),
            ])
            ->emptyStateHeading('Sin derivaciones registradas')
            ->emptyStateDescription('Agregá la primera derivación médica de este paciente.');
    }
}
