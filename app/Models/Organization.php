<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'status'];

    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
