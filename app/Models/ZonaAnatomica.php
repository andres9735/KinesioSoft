<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Str;

class ZonaAnatomica extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable, SoftDeletes;

    protected $table = 'zona_anatomica';
    protected $primaryKey = 'id_zona_anatomica';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'nombre',
        'slug',
        'parent_id',
        'codigo',
        'requiere_lateralidad',
        'activo',
    ];

    protected $casts = [
        'requiere_lateralidad' => 'boolean',
        'activo' => 'boolean',
    ];

    // Relaciones jerárquicas (self FK)
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id', 'id_zona_anatomica');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id', 'id_zona_anatomica');
    }

    // Scopes
    public function scopeActivos($q)
    {
        return $q->where('activo', true);
    }
    public function scopeRoots($q)
    {
        return $q->whereNull('parent_id');
    }

    // Generar slug automáticamente si no viene seteado
    protected static function booted()
    {
        static::saving(function (self $model) {
            if (blank($model->slug) && filled($model->nombre)) {
                $model->slug = Str::slug($model->nombre);
            }
        });
    }
}
