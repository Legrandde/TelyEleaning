<?php
namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
 
class Historique extends Model
{
    protected $fillable = [
        'eleve_id',
        'exercice_id',
        'action',
        'date_consultation',
    ];
 
    protected $casts = [
        'date_consultation' => 'datetime',
    ];
 
    // ─── Relations ───
    public function eleve(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }
 
    public function exercice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }
 
    // ─── Scopes ───
    public function scopeCorrectionsVues($query)
    {
        return $query->where('action', 'correction_vue');
    }
 
    public function scopeRecents($query, int $jours = 7)
    {
        return $query->where('date_consultation', '>=', now()->subDays($jours));
    }
}
 