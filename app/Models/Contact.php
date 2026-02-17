<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use CrudTrait;

    protected $fillable = [
        'sevdesk_id',
        'name',
        'email',
        'image',
    ];

    public function setImageAttribute($value)
    {
        if ($value) {
            $this->attributes['image'] = $value->store('contacts', 'public');
        }
    }
}
