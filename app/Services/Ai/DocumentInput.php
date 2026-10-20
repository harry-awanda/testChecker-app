<?php
namespace App\Services\Ai;
use Illuminate\Validation\ValidationException;
class DocumentInput {
    public function part(array $document, bool $allowPdf=false): array {
        $data = $document['data'] ?? null; $mime = $document['mime_type'] ?? null;
        $allowed = ['image/jpeg','image/png','image/webp'];
        if ($allowPdf) $allowed[] = 'application/pdf';
        if (!is_string($data) || !in_array($mime,$allowed,true) || strlen($data)>6990510) $this->fail();
        $bytes = base64_decode($data,true);
        if ($bytes === false || strlen($bytes)>5*1024*1024 || strlen($bytes)===0) $this->fail();
        $detected = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if ($detected !== $mime) $this->fail();
        if ($mime !== 'application/pdf') {
            $size = @getimagesizefromstring($bytes);
            if (!$size || $size[0]>12000 || $size[1]>12000 || $size[0]*$size[1]>40000000) $this->fail();
        }
        unset($bytes);
        return ['inlineData'=>['mimeType'=>$mime,'data'=>$data]];
    }
    private function fail(): never { throw ValidationException::withMessages(['document'=>'Dokumen tidak valid. Gunakan JPG, PNG, WebP atau PDF (untuk soal/rubrik), maksimal 5 MB.']); }
}
