<?php

namespace App\Filament\Kinesiologa\Resources;

use App\Filament\Kinesiologa\Resources\PacienteResource\Pages;
use App\Filament\Kinesiologa\Resources\PacienteResource\RelationManagers;
use App\Filament\Kinesiologa\Resources\PacienteResource\Widgets\PacienteHeader;
use App\Filament\Kinesiologa\Resources\PacienteResource\Widgets\HcStats;
use App\Models\Paciente;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class PacienteResource extends Resource
{
    protected static ?string $model = Paciente::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    /** Solo Kinesiólogas ven el recurso en el panel */
    public static function canViewAny(): bool
    {
        /** @var \App\Models\User|null $u */
        $u = Auth::user();   // 👈 en vez de auth()->user()

        return $u?->hasRole('Kinesiologa') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')
                ->relationship('user', 'name')
                ->default(null),
            Forms\Components\TextInput::make('nombre')->required()->maxLength(255),
            Forms\Components\TextInput::make('dni')->maxLength(255)->default(null),
            Forms\Components\TextInput::make('telefono')->tel()->maxLength(255)->default(null),
            Forms\Components\TextInput::make('direccion')->maxLength(255)->default(null),
            Forms\Components\DatePicker::make('fecha_nacimiento'),
            Forms\Components\Textarea::make('observaciones')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->sortable(),
                Tables\Columns\TextColumn::make('nombre')->searchable(),
                Tables\Columns\TextColumn::make('dni')->searchable(),
                Tables\Columns\TextColumn::make('telefono')->searchable(),
                Tables\Columns\TextColumn::make('direccion')->searchable(),
                Tables\Columns\TextColumn::make('fecha_nacimiento')->date()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** Widgets (index del recurso) */
    public static function getWidgets(): array
    {
        return [
            PacienteHeader::class,
            HcStats::class,
        ];
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Kinesiologa\Resources\PacienteResource\RelationManagers\AntecedentesPersonalesRelationManager::class,
            \App\Filament\Kinesiologa\Resources\PacienteResource\RelationManagers\AntecedentesFamiliaresRelationManager::class,
            // \App\Filament\Kinesiologa\Resources\PacienteResource\RelationManagers\PatologiasRelationManager::class,
            \App\Filament\Kinesiologa\Resources\PacienteResource\RelationManagers\AlergiasRelationManager::class,
            \App\Filament\Kinesiologa\Resources\PacienteResource\RelationManagers\CirugiasRelationManager::class,
            \App\Filament\Kinesiologa\Resources\PacienteResource\RelationManagers\MedicacionesActualesRelationManager::class,
            // \App\Filament\Kinesiologa\Resources\PacienteResource\RelationManagers\EstiloDeVidaRelationManager::class,
            // \App\Filament\Kinesiologa\Resources\PacienteResource\RelationManagers\AntropometriaRelationManager::class,
            // \App\Filament\Kinesiologa\Resources\PacienteResource\RelationManagers\EstudiosImagenRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPacientes::route('/'),
            'create' => Pages\CreatePaciente::route('/create'),
            'view'   => Pages\ViewPaciente::route('/{record}'),
            'edit'   => Pages\EditPaciente::route('/{record}/edit'),
        ];
    }
}
