<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RatingScale extends Model
{
    protected $fillable = ['score', 'label', 'description', 'color'];
}
