<?php
namespace Tests\Feature;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\{CreatesSchool,TestCase};
class ReportTest extends TestCase {
    use RefreshDatabase,CreatesSchool;
    public function test_csv_cells_cannot_execute_formulas():void {
        $service=app(ReportService::class);
        foreach(['=1+1','+SUM(A1)','-1+2','@SUM(A1)',"\t=1+1",'  =1+1'] as $value)$this->assertStringStartsWith("'",$service->safeCsvCell($value));
        $this->assertSame('00123',$service->safeCsvCell('00123'));
    }
    public function test_xlsx_is_valid_zip_with_inline_strings_and_numeric_scores():void {
        $s=$this->school();$service=app(ReportService::class);$rows=$service->rows($s['assessment']);
        $rows[0]['student_name']='=1+1';$rows[0]['student_code']='00012';$rows[0]['score']=85.5;
        $bytes=$service->xlsx($rows);$path=tempnam(sys_get_temp_dir(),'testchecker-test-');
        try {
            file_put_contents($path,$bytes);$zip=new \ZipArchive;$this->assertTrue($zip->open($path)===true);
            $sheet=$zip->getFromName('xl/worksheets/sheet1.xml');$this->assertNotFalse(simplexml_load_string($sheet));
            $this->assertStringContainsString('t="inlineStr"',$sheet);$this->assertStringContainsString('00012',$sheet);
            $this->assertStringContainsString('<v>85.5</v>',$sheet);$this->assertStringNotContainsString('<f>',$sheet);$zip->close();
        } finally {unlink($path);}
    }
    public function test_owner_can_download_both_formats_and_ungraded_score_is_blank():void {
        $s=$this->school();$this->actingAs($s['teacher']);
        $this->get('/assessments/'.$s['assessment']->id.'/export/csv')->assertOk()->assertDownload();
        $this->get('/assessments/'.$s['assessment']->id.'/export/xlsx')->assertOk()->assertHeader('Content-Type','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertNull(app(ReportService::class)->rows($s['assessment'])[0]['score']);
    }
}
