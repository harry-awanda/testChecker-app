import http from 'node:http';
const server=http.createServer(async(request,response)=>{
    response.setHeader('Content-Type','application/json');
    if(request.url==='/health'){response.end('{"ok":true}');return;}
    if(request.headers['x-goog-api-key']!=='browser-test-placeholder-only'){response.writeHead(403);response.end('{}');return;}
    if(request.method==='GET'){response.end(JSON.stringify({models:[{name:'models/gemini-test',displayName:'Model pengujian',supportedGenerationMethods:['generateContent']}]}));return;}
    let raw='';for await(const chunk of request)raw+=chunk;
    const body=JSON.parse(raw);const instruction=body.systemInstruction.parts[0].text;
    let result={ok:true};
    if(instruction.startsWith('Baca satu halaman'))result={
        page_unclear:false,note:'',answers:[
            {question_number:1,transcription:'A',selected_options:['A'],uncertain:false,uncertainty_note:'',location:'Bagian atas'},
            {question_number:2,transcription:'Gagasan ini menjelaskan pokok bacaan.',selected_options:[],uncertain:true,uncertainty_note:'Periksa kata terakhir pada foto.',location:'Bagian bawah'},
        ],
    };
    if(instruction.startsWith('Nilai jawaban'))result={criteria:[{index:0,score:7.5,reason:'Alasan cukup tepat; penjelasan dapat diperdalam.'}],feedback:'Tambahkan bukti dari bacaan untuk memperkuat alasan.'};
    if(instruction.startsWith('Ekstrak soal'))result={questions:[{number:1,type:'essay',prompt:'Jelaskan gagasan utama bacaan.',weight:10,options:[],answer_key:[],reference_answer:'Gagasan utama menjelaskan pokok bacaan.',criteria:[{label:'Ketepatan konsep',description:'Penjelasan sesuai pokok bacaan.',max_score:10}],scoring_mode:'all_or_nothing'}]};
    response.end(JSON.stringify({candidates:[{finishReason:'STOP',content:{parts:[{text:JSON.stringify(result)}]}}]}));
});
server.listen(8012,'127.0.0.1',()=>console.log('Synthetic Gemini test server ready on 8012; no external AI requests.'));
