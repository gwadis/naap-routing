<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Office extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'department',
        'head',
        'contact',
        'status',
    ];

    public function documents()
    {
        return $this->hasMany(Document::class, 'current_office_id');
    }

    public function originDocuments()
    {
        return $this->hasMany(Document::class, 'origin_office_id');
    }

    public function currentDocuments()
    {
        return $this->hasMany(Document::class, 'current_office_id');
    }

    public function destinationDocuments()
    {
        return $this->hasMany(Document::class, 'destination_office_id');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'office_id');
    }
}
