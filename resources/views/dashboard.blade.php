@extends('layouts.app')
@section('content')
<p class="eyebrow">RUANG KERJA {{ auth()->user()->role === 'superadmin' ? 'SUPERADMIN' : 'GURU' }}</p>
<h1>Selamat datang, {{ auth()->user()->name }}.</h1><p class="lead">Lebih sedikit waktu mengoreksi. Lebih banyak waktu mendampingi siswa.</p>
@if(auth()->user()->role === 'superadmin')
<div class="grid three"><div class="card"><p class="muted">Guru aktif</p><div class="stat">{{ \App\Models\User::where('role','teacher')->where('is_active',true)->count() }}</div></div><div class="card"><p class="muted">Kelas</p><div class="stat">{{ class_exists(\App\Models\Classroom::class) ? \App\Models\Classroom::count() : 0 }}</div></div><div class="card"><p class="muted">Siswa</p><div class="stat">{{ class_exists(\App\Models\Student::class) ? \App\Models\Student::count() : 0 }}</div></div></div>
<div class="card"><h2>Kelola kebutuhan sekolah</h2><p>Siapkan akun guru, tahun ajaran, kelas, dan daftar siswa. Soal serta hasil penilaian tetap menjadi ruang kerja masing-masing guru.</p>@if(Route::has('admin.index'))<a class="button" href="{{ route('admin.index') }}">Buka data sekolah →</a>@endif</div>
@else
<div class="grid three"><div class="card"><p class="muted">Kelas ditugaskan</p><div class="stat">{{ auth()->user()->classrooms()->count() }}</div></div><div class="card"><p class="muted">Tes dibuat</p><div class="stat">{{ class_exists(\App\Models\Assessment::class) ? auth()->user()->assessments()->count() : 0 }}</div></div><div class="card accent"><p>KEPUTUSAN AKHIR</p><h2>Selalu di tangan Anda.</h2><p>AI membantu membaca dan menilai. Anda meninjau dan mengesahkan.</p></div></div>
<div class="card"><p class="eyebrow">MULAI DARI SINI</p><h2>Dari lembar jawaban ke pemahaman.</h2><div class="steps"><span>01 · Siapkan soal & rubrik</span><span>02 · Tinjau pembacaan foto</span><span>03 · Sahkan nilai</span></div>@if(Route::has('assessments.index'))<a class="button" href="{{ route('assessments.index') }}">Buka tes & penilaian →</a>@endif</div>
@endif
@endsection
