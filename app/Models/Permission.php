<?php

namespace App\Models;

use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission implements AuditableContract
{
    use Auditable;

    protected array $auditExclude = ['guard_name', 'created_at', 'updated_at'];
    protected array $auditEvents  = ['created', 'updated', 'deleted'];
    protected $attributes = ['guard_name' => 'web'];
}
