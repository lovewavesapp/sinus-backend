<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SinusValue extends Model
{
	use HasFactory, SoftDeletes;

    protected $table = 'sinusvalues';

	protected $fillable = [
		'sinus_id',
		'date',
		'value',
		'latitude',
		'longitude',
		'tags',
		'description',
	];
}
