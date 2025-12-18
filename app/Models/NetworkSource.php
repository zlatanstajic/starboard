<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @package App\Models
 */
final class NetworkSource extends Model
{
    use SoftDeletes, HasFactory;

    /**
     * @var list<string>
     */
    public $fillable = [
        //
    ];
}
