<?php
namespace Tests\Feature;
use App\Services\Ai\{AiProvider,DocumentInput};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\{CreatesSchool,TestCase};
class GeminiTest extends TestCase {
    use RefreshDatabase, CreatesSchool, \Tests\SendsEncryptedPhotos;
    public function test_generation_uses_header_key_and_inline_image_without_file_api(): void {
        $s=$this->school();
        Http::preventStrayRequests();
        Http::fake(['*generateContent'=>function($request) {
            $this->assertSame('php://memory',$request->toPsrRequest()->getBody()->getMetadata('uri'));
            $this->assertTrue(isset($request['contents'][0]['parts'][1]['inlineData']));
            return Http::response(['candidates'=>[['finishReason'=>'STOP','content'=>['parts'=>[['text'=>'{"ok":true}']]]]]]);
        }]);
        $image=['mime_type'=>'image/png','data'=>base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jP1kAAAAASUVORK5CYII='))];
        $result=app(AiProvider::class)->generate($s['teacher'],'test',[],['type'=>'object'],[app(DocumentInput::class)->part($image)]);
        $this->assertTrue($result['ok']);
        Http::assertSent(fn($request)=>$request->hasHeader('x-goog-api-key','test-placeholder-api-key-only') && !str_contains($request->url(),'key='));
        Http::assertSentCount(1);
    }
    public function test_quota_failure_is_sanitized(): void {
        $s=$this->school(); Http::fake(['*'=>Http::response(['error'=>['message'=>'sensitive-upstream-content']],429)]);
        $this->actingAs($s['teacher'])->postJson('/settings/ai/test')->assertUnprocessable()->assertSee('Kuota')->assertDontSee('sensitive-upstream-content')->assertDontSee('test-placeholder-api-key-only');
    }
    public function test_invalid_ai_json_cannot_be_used(): void {
        $s=$this->school(); Http::fake(['*'=>Http::response(['candidates'=>[['finishReason'=>'STOP','content'=>['parts'=>[['text'=>'not-json']]]]]])]);
        $this->actingAs($s['teacher'])->postJson('/settings/ai/test')->assertUnprocessable();
    }
    public function test_document_mime_spoofing_is_rejected_before_network(): void {
        $s=$this->school(); Http::fake();
        $this->actingAs($s['teacher'])->postPhoto('/settings/ai/test-image',['document'=>['mime_type'=>'image/png','data'=>base64_encode('not-an-image')]])->assertUnprocessable();
        Http::assertNothingSent();
    }
}
