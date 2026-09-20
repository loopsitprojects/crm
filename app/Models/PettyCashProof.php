<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PettyCashProof extends Model
{
    use HasFactory;

    protected $fillable = [
        'petty_cash_request_id',
        'file_path',
        'file_name',
        'file_type',
    ];

    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        try {
            return route('petty-cash.proofs.show', $this->id);
        } catch (\Throwable $e) {
            return url($this->file_path);
        }
    }

    public function request()
    {
        return $this->belongsTo(PettyCashRequest::class, 'petty_cash_request_id');
    }
}
