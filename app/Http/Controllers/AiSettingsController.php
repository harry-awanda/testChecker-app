<?php
namespace App\Http\Controllers;
use App\Services\Ai\AiProvider;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class AiSettingsController extends Controller {
    public function edit() { return view('teacher.ai'); }
    public function update(Request $request) {
        $data = $request->validate(['api_key'=>'nullable|string|min:20|max:255','consent'=>'accepted','gemini_model'=>['nullable','string','max:150','regex:/^gemini-[a-zA-Z0-9._-]+$/']]);
        $user = $request->user();
        if (!empty($data['api_key'])) $user->gemini_api_key = $data['api_key'];
        if (!$user->gemini_api_key) throw ValidationException::withMessages(['api_key'=>'Masukkan API key Gemini.']);
        $user->ai_consent_at = now(); $user->gemini_model = $data['gemini_model'] ?? null; $user->save();
        return back()->with('success','Pengaturan AI disimpan. API key tidak ditampilkan kembali.');
    }
    public function models(Request $request, AiProvider $provider) { return response()->json(['models'=>$provider->models($request->user())]); }
    public function test(Request $request, AiProvider $provider) {
        $result = $provider->generate($request->user(),'Uji koneksi sederhana. Jawab ok=true.',[],['type'=>'object','properties'=>['ok'=>['type'=>'boolean']],'required'=>['ok']]);
        if (($result['ok'] ?? null)!==true) throw ValidationException::withMessages(['ai'=>'Model tidak mengembalikan hasil uji yang valid.']);
        return response()->json(['message'=>'Koneksi berhasil. Model menerima permintaan terstruktur. Untuk gambar, gunakan juga uji gambar di bawah.']);
    }
    public function testImage(Request $request, AiProvider $provider, \App\Services\Ai\DocumentInput $input) {
        $request->validate(['document'=>'required|array']);
        $result = $provider->generate($request->user(),'Jelaskan secara singkat isi gambar untuk menguji dukungan gambar.',['task'=>'image_support_test'],['type'=>'object','properties'=>['description'=>['type'=>'string']],'required'=>['description']],[$input->part($request->input('document'))]);
        if (!is_string($result['description'] ?? null)) throw ValidationException::withMessages(['ai'=>'Uji gambar tidak valid.']);
        return response()->json(['message'=>'Model mendukung gambar: '.mb_substr($result['description'],0,500)]);
    }
    public function destroy(Request $request) {
        $request->user()->update(['gemini_api_key'=>null,'gemini_model'=>null,'ai_consent_at'=>null]);
        return back()->with('success','API key dihapus.');
    }
}
