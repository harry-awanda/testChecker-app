<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>{{ $title ?? 'TestChecker' }} · TestChecker</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body><div class="app-shell"><aside class="sidebar">
<a href="{{ route('dashboard') }}" class="brand"><span class="brand-icon">✓</span> TestChecker</a><p class="eyebrow">RUANG KERJA SEKOLAH</p>
@auth<nav><a href="{{ route('dashboard') }}">⌂ &nbsp; Ringkasan</a>
@if(auth()->user()->role === 'superadmin')
@if(Route::has('admin.index'))<a href="{{ route('admin.index') }}">▦ &nbsp; Data sekolah</a>@endif
@else
@if(Route::has('assessments.index'))<a href="{{ route('assessments.index') }}">▤ &nbsp; Tes & penilaian</a>@endif
@if(Route::has('ai.edit'))<a href="{{ route('ai.edit') }}">✦ &nbsp; Pengaturan AI</a>@endif
@endif</nav>
<div class="sidebar-footer"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->role === 'superadmin' ? 'Superadmin' : 'Guru' }}</small><form method="post" action="{{ route('logout') }}">@csrf<button class="link-button">Keluar dari akun</button></form></div>@endauth
</aside><main><header class="topbar"><span>Penilaian yang teliti, keputusan tetap di tangan guru.</span><span class="pill">Satu sekolah</span></header>
<div class="content">@if(session('success'))<div class="notice success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="notice error" role="alert"><strong>Periksa kembali isian Anda.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')</div></main></div>@stack('scripts')</body></html>
