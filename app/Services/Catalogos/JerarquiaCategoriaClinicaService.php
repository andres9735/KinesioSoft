<?php

namespace App\Services\Catalogos;

use App\Enums\TipoCategoriaClinicaEnum;
use App\Models\CategoriaClinica;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

final class JerarquiaCategoriaClinicaService
{
    public function validarParaCrear(
        TipoCategoriaClinicaEnum|string $tipo,
        int|string|null $categoriaPadreId
    ): void {
        $padreId = $this->normalizarPadreId($categoriaPadreId);

        if ($padreId === null) {
            return;
        }

        $padre = $this->obtenerPadre($padreId);

        $this->validarMismoTipo($padre, $tipo);
        $this->validarPadreActivo($padre);
    }

    public function validarParaActualizar(
        CategoriaClinica $categoriaClinica,
        TipoCategoriaClinicaEnum|string $tipo,
        int|string|null $categoriaPadreId
    ): void {
        $padreId = $this->normalizarPadreId($categoriaPadreId);

        if ($padreId === null) {
            return;
        }

        if ($padreId === (int) $categoriaClinica->getKey()) {
            $this->lanzarError('Una categoría clínica no puede ser padre de sí misma.');
        }

        $padre = $this->obtenerPadre($padreId);

        $this->validarMismoTipo($padre, $tipo);

        if ($padreId !== $this->normalizarPadreId($categoriaClinica->categoria_padre_id)) {
            $this->validarPadreActivo($padre);
        }

        if (in_array($padreId, $this->obtenerIdsDescendientes($categoriaClinica), true)) {
            $this->lanzarError('La categoría seleccionada es descendiente y produciría un ciclo.');
        }
    }

    /**
     * @return list<int>
     */
    public function obtenerIdsDescendientes(CategoriaClinica $categoriaClinica): array
    {
        $raizId = (int) $categoriaClinica->getKey();
        $visitados = [$raizId => true];
        $frontera = [$raizId];
        $descendientes = [];

        while ($frontera !== []) {
            $hijas = CategoriaClinica::query()
                ->whereIn('categoria_padre_id', $frontera)
                ->pluck('id');

            $siguienteFrontera = [];

            foreach ($hijas as $hijaId) {
                $hijaId = (int) $hijaId;

                if (isset($visitados[$hijaId])) {
                    continue;
                }

                $visitados[$hijaId] = true;
                $descendientes[] = $hijaId;
                $siguienteFrontera[] = $hijaId;
            }

            $frontera = $siguienteFrontera;
        }

        return $descendientes;
    }

    /**
     * @return Collection<int, CategoriaClinica>
     */
    public function obtenerCandidatosParaCrear(
        TipoCategoriaClinicaEnum|string|null $tipo = null
    ): Collection {
        return $this->consultaCandidatosActivos($tipo)->get();
    }

    /**
     * @return Collection<int, CategoriaClinica>
     */
    public function obtenerCandidatosParaEditar(
        CategoriaClinica $categoriaClinica,
        TipoCategoriaClinicaEnum|string|null $tipo = null
    ): Collection {
        $idsExcluidos = [
            (int) $categoriaClinica->getKey(),
            ...$this->obtenerIdsDescendientes($categoriaClinica),
        ];

        return $this->consultaCandidatosActivos($tipo)
            ->whereNotIn('id', $idsExcluidos)
            ->get();
    }

    private function consultaCandidatosActivos(
        TipoCategoriaClinicaEnum|string|null $tipo
    ): Builder {
        return CategoriaClinica::query()
            ->where('activo', true)
            ->when(
                $tipo !== null,
                fn ($query) => $query->where('tipo', $this->normalizarTipo($tipo))
            )
            ->orderBy('nombre')
            ->orderBy('id');
    }

    private function obtenerPadre(int $padreId): CategoriaClinica
    {
        $padre = CategoriaClinica::query()->find($padreId);

        if (! $padre) {
            $this->lanzarError('La categoría padre seleccionada no existe.');
        }

        return $padre;
    }

    private function validarMismoTipo(
        CategoriaClinica $padre,
        TipoCategoriaClinicaEnum|string $tipo
    ): void {
        if ($padre->tipo->value !== $this->normalizarTipo($tipo)) {
            $this->lanzarError('La categoría padre debe tener el mismo tipo que la categoría hija.');
        }
    }

    private function validarPadreActivo(CategoriaClinica $padre): void
    {
        if (! $padre->activo) {
            $this->lanzarError('La categoría padre seleccionada debe estar activa.');
        }
    }

    private function normalizarTipo(TipoCategoriaClinicaEnum|string $tipo): string
    {
        return $tipo instanceof TipoCategoriaClinicaEnum ? $tipo->value : $tipo;
    }

    private function normalizarPadreId(int|string|null $categoriaPadreId): ?int
    {
        if ($categoriaPadreId === null || $categoriaPadreId === '') {
            return null;
        }

        return (int) $categoriaPadreId;
    }

    private function lanzarError(string $mensaje): never
    {
        throw ValidationException::withMessages([
            'categoria_padre_id' => $mensaje,
        ]);
    }
}
