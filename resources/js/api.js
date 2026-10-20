export async function api(url, method = 'GET', data = undefined) {
    let response;
    try {
        response = await fetch(url, {
            method, credentials: 'same-origin', cache: 'no-store',
            headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
            body: data === undefined ? undefined : JSON.stringify(data),
        });
    } catch { throw new Error('Koneksi terputus. Progres teks yang sudah disimpan tetap tersedia. Coba kembali.'); }
    let body;
    try { body = await response.json(); } catch { throw new Error('Server tidak dapat memproses permintaan. Periksa batas ukuran dan koneksi.'); }
    if (!response.ok) {
        if (response.status === 419) throw new Error('Sesi berakhir. Simpan teks penting lalu masuk kembali; foto perlu diunggah ulang.');
        throw new Error(body.errors ? Object.values(body.errors).flat().join(' ') : (body.message || 'Permintaan tidak dapat diproses.'));
    }
    return body;
}
export async function readDocument(file) {
    if (!file) return null;
    if (file.size > 5 * 1024 * 1024) throw new Error('Ukuran maksimal dokumen adalah 5 MB.');
    if (!['image/jpeg','image/png','image/webp','application/pdf'].includes(file.type)) throw new Error('Gunakan JPG, PNG, WebP, atau PDF.');
    const data = await new Promise((resolve,reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(String(reader.result).split(',')[1]);
        reader.onerror = () => reject(new Error('Berkas tidak dapat dibaca.'));
        reader.readAsDataURL(file);
    });
    return {mime_type:file.type, data};
}
