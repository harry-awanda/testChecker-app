@extends('layouts.app')
@section('content')
<p class="eyebrow">KONEKSI & PRIVASI</p><h1>Asisten AI pilihan Anda.</h1><p class="lead">Gunakan Gemini dengan API key pribadi. Tidak ada perpindahan otomatis ke layanan berbayar.</p>
<div class="notice">Gemini menyediakan kuota gratis untuk model tertentu, bukan penggunaan tanpa batas. Data pada layanan gratis dapat digunakan oleh penyedia untuk peningkatan produk. Foto tidak disimpan aplikasi, tetapi ketentuan penyedia tetap berlaku. Potong nama/NIS pada foto sebelum dikirim. <a href="https://ai.google.dev/gemini-api/terms" target="_blank" rel="noopener" class="link-button">Baca ketentuan Gemini ↗</a></div>
<div class="card" x-data="aiSettings({{ \Illuminate\Support\Js::from(auth()->user()->gemini_model) }})"><h2>Pengaturan Gemini</h2><p class="small muted">Status API key: {{ auth()->user()->gemini_api_key ? 'Sudah tersimpan terenkripsi' : 'Belum diatur' }}. API key tidak pernah ditampilkan kembali.</p>
<form method="post" action="{{ route('ai.update') }}">@csrf @method('PUT')
<label>API key baru {{ auth()->user()->gemini_api_key ? '(kosongkan untuk mempertahankan)' : '' }}<input type="password" name="api_key" autocomplete="off" minlength="20" maxlength="255"></label>
<label>ID model<input name="gemini_model" x-model="model" placeholder="Pilih dari daftar model setelah menyimpan API key" pattern="gemini-[a-zA-Z0-9._-]+"></label>
<label class="check-label"><input type="checkbox" name="consent" value="1" required @checked(auth()->user()->ai_consent_at)>Saya memahami jawaban dan materi dikirim ke Gemini sesuai ketentuan penyedia.</label><button class="button">Simpan pengaturan</button></form>
<div class="actions"><button class="button secondary" type="button" @click="loadModels()" :disabled="busy">Muat daftar model</button><button class="button secondary" type="button" @click="testConnection()" :disabled="busy">Uji model tersimpan</button></div>
<p class="small muted">Daftar API menampilkan model generateContent. Dukungan gambar dan kuota gratis tetap perlu diperiksa pada akun Anda; uji gambar untuk memastikan kompatibilitas.</p>
<template x-if="models.length"><label>Pilih model dari API<select @change="model=$event.target.value"><option value="">Pilih lalu simpan pengaturan</option><template x-for="item in models" :key="item.id"><option :value="item.id" x-text="item.name+' · '+item.id"></option></template></select></label></template>
<label>Uji dukungan gambar (maks. 5 MB)<input type="file" accept="image/jpeg,image/png,image/webp" @change="testImage($event.target)" :disabled="busy"></label>
<p class="notice" role="status" x-show="message" x-text="message"></p></div>
<form method="post" action="{{ route('ai.destroy') }}" onsubmit="return confirm('Hapus API key tersimpan?')">@csrf @method('DELETE')<button class="button danger">Hapus API key</button></form>
@endsection
