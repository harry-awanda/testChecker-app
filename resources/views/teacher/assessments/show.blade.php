@extends('layouts.app')
@section('content')
<p class="eyebrow">{{ $assessment->subject }} · VERSI {{ $assessment->version }}</p><h1>{{ $assessment->title }}</h1><p class="lead">{{ $assessment->classroom->name }} · {{ $assessment->classroom->academicYear->name }} · {{ config('testchecker.statuses.'.$assessment->status) }}</p>
<div class="actions">@if(!$assessment->locked_at)<a class="button secondary" href="{{ route('assessments.edit',$assessment) }}">Tinjau soal & rubrik</a>@endif
<form method="post" action="{{ route('assessments.duplicate',$assessment) }}">@csrf<button class="button secondary">Buat versi tes baru</button></form>
@if($assessment->approved_at && Route::has('workspace.show'))<a class="button" href="{{ route('workspace.show',$assessment) }}">Mulai / lanjutkan koreksi →</a>@endif
@if(Route::has('reports.show'))<a class="button secondary" href="{{ route('reports.show',$assessment) }}">Lihat rekap kelas</a>@endif</div>
@foreach($assessment->questions as $question)<div class="card"><h2>{{ $question->number }}. {{ config('testchecker.question_types.'.$question->type) }} <span class="pill">{{ $question->weight }} poin</span></h2><pre>{{ $question->prompt }}</pre>@foreach($question->options as $option)<p>{{ $option['key'] }} · {{ $option['text'] }}</p>@endforeach
@if($question->isObjective())<p class="small">Kunci: {{ implode(', ',$question->answer_key) }}</p>@else<p>{{ $question->reference_answer }}</p>@foreach($question->criteria as $criterion)<p class="small"><strong>{{ $criterion['label'] }} ({{ $criterion['max_score'] }})</strong> · {{ $criterion['description'] }}</p>@endforeach @endif</div>@endforeach
<details class="card"><summary>Hapus tes dan seluruh penilaiannya</summary><p>Penghapusan bersifat permanen. Ekspor rekap terlebih dahulu bila diperlukan.</p><form method="post" action="{{ route('assessments.destroy',$assessment) }}" onsubmit="return confirm('Hapus permanen tes, seluruh jawaban, dan riwayat nilai?')">@csrf @method('DELETE')<label>Ketik HAPUS untuk mengonfirmasi<input name="confirmation" required pattern="HAPUS"></label><button class="button danger">Hapus permanen</button></form></details>
@endsection
