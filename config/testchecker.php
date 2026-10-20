<?php
return [
    'gemini_base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
    'gemini_timeout' => (int) env('GEMINI_TIMEOUT', 120),
    'question_types' => [
        'essay'=>'Esai', 'true_false'=>'Benar / salah', 'multiple_choice'=>'Pilihan ganda',
        'complex_choice'=>'Pilihan ganda kompleks', 'diagram'=>'Diagram', 'code'=>'Kode program', 'calculation'=>'Langkah hitungan',
    ],
    'assessment_types' => ['exercise'=>'Latihan','quiz'=>'Ulangan','exam'=>'Ujian'],
    'statuses' => ['draft'=>'Draf','approved'=>'Siap dikoreksi','locked'=>'Koreksi dimulai','pending_review'=>'Perlu ditinjau','ready'=>'Siap dinilai','graded'=>'Draf nilai','finalized'=>'Disahkan'],
];
