<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\InputBag;
class EncryptedPhotoInput {
    public function handle(Request $request,Closure $next) {
        $requiresPhoto=preg_match('~^(revisions/\d+/read|settings/ai/test-image)$~',$request->path());
        if(!$request->hasHeader('X-Photo-Encrypted')) {
            if($requiresPhoto) return response()->json(['message'=>'Foto harus dikirim melalui kanal gambar terenkripsi aplikasi.'],415);
            return $next($request);
        }
        if($request->method()!=='POST' || !preg_match('~^(revisions/\d+/read|settings/ai/test-image|assessments/\d+/draft)$~',$request->path())) return $this->invalid();
        $key=base64_decode($request->header('X-Photo-Key',''),true);
        $iv=base64_decode($request->header('X-Photo-IV',''),true);
        if($request->header('X-Photo-Encrypted')!=='v1' || !is_string($key) || strlen($key)!==32 || !is_string($iv) || strlen($iv)!==12 || (int)$request->header('Content-Length',0)>10*1024*1024) return $this->invalid();
        $ciphertext=$request->getContent();
        if(strlen($ciphertext)<16 || strlen($ciphertext)>10*1024*1024)return $this->invalid();
        $tag=substr($ciphertext,-16);$ciphertext=substr($ciphertext,0,-16);
        $plaintext=openssl_decrypt($ciphertext,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag);
        unset($key,$iv,$ciphertext,$tag);
        $request->headers->remove('X-Photo-Key');$request->headers->remove('X-Photo-IV');
        if($plaintext===false)return $this->invalid();
        try{$data=json_decode($plaintext,true,64,JSON_THROW_ON_ERROR);}catch(\Throwable $e){return $this->invalid();}
        unset($plaintext);
        if(!is_array($data)||array_is_list($data))return $this->invalid();
        $request->headers->set('Content-Type','application/json');
        $request->setJson(new InputBag($data));
        $request->attributes->set('photo_decrypted',true);
        return $next($request);
    }
    private function invalid() {return response()->json(['message'=>'Payload gambar terenkripsi tidak valid. Pilih ulang foto dan coba kembali.'],422);}
}
