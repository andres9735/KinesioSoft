<?php

namespace App\Models;

use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole implements AuditableContract
{
    use Auditable;

    protected array $auditExclude = ['guard_name', 'created_at', 'updated_at'];
    protected array $auditEvents  = ['created', 'updated', 'deleted'];
    protected $attributes = ['guard_name' => 'web'];
}
