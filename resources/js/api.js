export async function api(url, method = 'GET', data = undefined) {
    const headers={'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content};
    let body=data===undefined?undefined:JSON.stringify(data);
    if(data?.document?.data) {
        if(!globalThis.crypto?.subtle)throw new Error('Pengiriman foto membutuhkan HTTPS atau localhost. Buka aplikasi melalui koneksi aman.');
        const keyBytes=crypto.getRandomValues(new Uint8Array(32));const iv=crypto.getRandomValues(new Uint8Array(12));
        const key=await crypto.subtle.importKey('raw',keyBytes,{name:'AES-GCM'},false,['encrypt']);
        body=await crypto.subtle.encrypt({name:'AES-GCM',iv},key,new TextEncoder().encode(body));
        headers['Content-Type']='application/octet-stream';headers['X-Photo-Encrypted']='v1';
        headers['X-Photo-Key']=btoa(String.fromCharCode(...keyBytes));headers['X-Photo-IV']=btoa(String.fromCharCode(...iv));
        keyBytes.fill(0);
    }
    let response;
    try {response=await fetch(url,{method,credentials:'same-origin',cache:'no-store',headers,body});}
    catch {throw new Error('Koneksi terputus. Progres teks yang sudah disimpan tetap tersedia. Coba kembali.');}
    delete headers['X-Photo-Key'];delete headers['X-Photo-IV'];body=undefined;
    let result;
    try {result=await response.json();}catch {throw new Error('Server tidak dapat memproses permintaan. Periksa batas ukuran dan koneksi.');}
    if(!response.ok) {
        if(response.status===419)throw new Error('Sesi berakhir. Simpan teks penting lalu masuk kembali; foto perlu diunggah ulang.');
        throw new Error(result.errors?Object.values(result.errors).flat().join(' '):(result.message||'Permintaan tidak dapat diproses.'));
    }
    return result;
}
export async function readDocument(file) {
    if(!file)return null;
    if(file.size>5*1024*1024)throw new Error('Ukuran maksimal dokumen adalah 5 MB.');
    if(!['image/jpeg','image/png','image/webp','application/pdf'].includes(file.type))throw new Error('Gunakan JPG, PNG, WebP, atau PDF.');
    const data=await new Promise((resolve,reject)=>{
        const reader=new FileReader();reader.onload=()=>resolve(String(reader.result).split(',')[1]);
        reader.onerror=()=>reject(new Error('Berkas tidak dapat dibaca.'));reader.readAsDataURL(file);
    });
    return {mime_type:file.type,data};
}
