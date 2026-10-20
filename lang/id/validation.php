<?php
return [
    'required' => ':attribute wajib diisi.', 'string' => ':attribute harus berupa teks.', 'email' => 'Format :attribute tidak valid.',
    'unique' => ':attribute sudah digunakan.', 'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'min' => ['string' => ':attribute minimal :min karakter.', 'numeric' => ':attribute minimal :min.', 'array' => ':attribute minimal :min item.'],
    'max' => ['string' => ':attribute maksimal :max karakter.', 'numeric' => ':attribute maksimal :max.', 'array' => ':attribute maksimal :max item.', 'file' => ':attribute terlalu besar.'],
    'integer' => ':attribute harus berupa bilangan bulat.', 'numeric' => ':attribute harus berupa angka.', 'array' => ':attribute harus berupa daftar.',
    'boolean' => ':attribute harus bernilai benar atau salah.', 'in' => ':attribute tidak valid.', 'exists' => ':attribute tidak ditemukan.',
    'date' => ':attribute harus berupa tanggal.', 'after_or_equal' => ':attribute harus setelah atau sama dengan :date.',
    'attributes' => ['email' => 'Email', 'password' => 'Kata sandi', 'name' => 'Nama', 'title' => 'Judul', 'classroom_id' => 'Kelas', 'student_id' => 'Siswa', 'api_key' => 'API key', 'questions' => 'Soal', 'answers' => 'Jawaban', 'academic_year_id' => 'Tahun ajaran', 'code' => 'Kode', 'reason' => 'Alasan'],
];
