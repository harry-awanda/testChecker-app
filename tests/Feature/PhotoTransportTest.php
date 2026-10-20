<?php
namespace Tests\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\{CreatesSchool,SendsEncryptedPhotos,TestCase};
class PhotoTransportTest extends TestCase {
    use RefreshDatabase,CreatesSchool,SendsEncryptedPhotos;
    public function test_unencrypted_photo_endpoint_is_rejected():void {
        $s=$this->school();Http::fake();
        $this->actingAs($s['teacher'])->postJson('/settings/ai/test-image',['document'=>['data'=>'plaintext']])->assertStatus(415);
        Http::assertNothingSent();
    }
    public function test_tampered_ciphertext_is_rejected_before_ai():void {
        $s=$this->school();Http::fake();$this->actingAs($s['teacher']);
        $key=random_bytes(32);$iv=random_bytes(12);
        $this->call('POST','/settings/ai/test-image',[],[],[],['CONTENT_TYPE'=>'application/octet-stream','HTTP_ACCEPT'=>'application/json','HTTP_X_PHOTO_ENCRYPTED'=>'v1','HTTP_X_PHOTO_KEY'=>base64_encode($key),'HTTP_X_PHOTO_IV'=>base64_encode($iv)],random_bytes(80))->assertUnprocessable();
        Http::assertNothingSent();
    }
    public function test_encrypted_payload_is_decrypted_only_for_allowed_routes():void {
        $s=$this->school();$this->actingAs($s['teacher']);
        $this->postPhoto('/settings/ai/test',['anything'=>'test'])->assertUnprocessable();
    }
}
