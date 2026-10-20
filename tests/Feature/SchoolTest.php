<?php
namespace Tests\Feature;
use App\Models\{AcademicYear,Classroom,Student,User};
use App\Services\EnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class SchoolTest extends TestCase {
    use RefreshDatabase;
    private function classroom(string $name): Classroom {
        $year = AcademicYear::firstOrCreate(['name'=>'2026/2027'],['starts_on'=>'2026-07-01','ends_on'=>'2027-06-30']);
        return Classroom::create(['academic_year_id'=>$year->id,'name'=>$name]);
    }
    public function test_teacher_cannot_manage_school_accounts(): void {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }
    public function test_admin_can_manage_school_but_cannot_change_superadmin_via_teacher_route(): void {
        $admin = User::factory()->create(['role'=>'superadmin']);
        $this->withoutVite()->actingAs($admin)->get('/admin')->assertOk();
        $this->put('/admin/teachers/'.$admin->id,[])->assertNotFound();
    }
    public function test_transfer_preserves_previous_enrollment(): void {
        $first = $this->classroom('X A'); $second = $this->classroom('X B');
        $student = Student::create(['name'=>'Siswa Contoh','student_code'=>'TEST-01']);
        $service = app(EnrollmentService::class);
        $old = $service->enroll($student,$first); $new = $service->enroll($student,$second);
        $this->assertNotNull($old->fresh()->ended_at);
        $this->assertNull($new->ended_at);
        $this->assertDatabaseCount('enrollments',2);
        $this->assertSame($new->id,$service->enroll($student,$second)->id);
    }
    public function test_csv_import_rolls_back_all_rows_when_identity_conflicts(): void {
        $classroom = $this->classroom('X A');
        Student::create(['student_code'=>'EXISTING','name'=>'Nama Lama']);
        $this->actingAs(User::factory()->create(['role'=>'superadmin']))->post('/admin/students/import',[
            'classroom_id'=>$classroom->id,'csv'=>"student_code,name\nNEW,Nama Baru\nEXISTING,Nama Berbeda"
        ])->assertSessionHasErrors('csv');
        $this->assertDatabaseMissing('students',['student_code'=>'NEW']);
        $this->assertDatabaseCount('enrollments',0);
    }
    public function test_archived_records_cannot_be_enrolled(): void {
        $classroom = $this->classroom('X A');
        $student = Student::create(['student_code'=>'TEST-02','name'=>'Siswa','is_archived'=>true]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(EnrollmentService::class)->enroll($student,$classroom);
    }
}
