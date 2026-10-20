<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Question extends Model {
    protected $fillable = ['assessment_id','number','type','prompt','weight','options','answer_key','reference_answer','criteria','scoring_mode'];
    protected function casts(): array { return ['options'=>'array','answer_key'=>'array','criteria'=>'array','weight'=>'float']; }
    public function assessment() { return $this->belongsTo(Assessment::class); }
    public function isObjective(): bool { return in_array($this->type,['true_false','multiple_choice','complex_choice'],true); }
}
