<?php
namespace App\Http\Controllers;
use App\Models\{AcademicYear, Classroom, Student, User};
use App\Services\EnrollmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller {
    public function index(Request $request) {
        $section = in_array($request->query('section'), ['teachers','years','classrooms','students'], true) ? $request->query('section') : 'teachers';
        return view('admin.index', [
            'section' => $section,
            'teachers' => User::where('role','teacher')->orderBy('name')->get(),
            'years' => AcademicYear::orderByDesc('starts_on')->get(),
            'classrooms' => Classroom::with(['academicYear','teachers'])->orderBy('name')->get(),
            'students' => Student::with(['enrollments.classroom.academicYear'])->orderBy('name')->paginate(50)->withQueryString(),
        ]);
    }
    public function teacher(Request $request, ?User $teacher = null) {
        if ($teacher) abort_unless($teacher->role === 'teacher', 404);
        $data = $request->validate([
            'name'=>'required|string|max:255', 'email'=>['required','email',Rule::unique('users')->ignore($teacher?->id)],
            'password'=>[$teacher ? 'nullable' : 'required','string','min:12','confirmed'], 'is_active'=>'required|boolean',
        ]);
        if (empty($data['password'])) unset($data['password']);
        if ($teacher) {
            $teacher->update($data);
            if(isset($data['password']) || !$teacher->is_active) DB::table('sessions')->where('user_id',$teacher->id)->delete();
        }
        else User::create([...$data, 'role'=>'teacher']);
        return back()->with('success','Akun guru berhasil disimpan.');
    }
    public function year(Request $request, ?AcademicYear $academicYear = null) {
        $data = $request->validate(['name'=>['required','string','max:100',Rule::unique('academic_years')->ignore($academicYear?->id)],'starts_on'=>'required|date','ends_on'=>'required|date|after_or_equal:starts_on','is_archived'=>'required|boolean']);
        if ($academicYear) $academicYear->update($data); else AcademicYear::create($data);
        return back()->with('success','Tahun ajaran berhasil disimpan.');
    }
    public function classroom(Request $request, ?Classroom $classroom = null) {
        $data = $request->validate([
            'academic_year_id'=>'required|exists:academic_years,id',
            'name'=>['required','string','max:100',Rule::unique('classrooms')->where('academic_year_id', $request->integer('academic_year_id'))->ignore($classroom?->id)],
            'is_archived'=>'required|boolean', 'teacher_ids'=>'nullable|array', 'teacher_ids.*'=>['integer',Rule::exists('users','id')->where('role','teacher')],
        ]);
        if ($classroom && $classroom->academic_year_id !== (int)$data['academic_year_id']) throw ValidationException::withMessages(['academic_year_id'=>'Tahun ajaran kelas tidak dapat diubah. Buat kelas baru untuk tahun berikutnya.']);
        $teacherIds = $data['teacher_ids'] ?? [];
        unset($data['teacher_ids']);
        DB::transaction(function () use ($classroom,$data,$teacherIds) {
            if ($classroom) $classroom->update($data); else $classroom = Classroom::create($data);
            $classroom->teachers()->sync($teacherIds);
        });
        return back()->with('success','Kelas dan penugasan guru berhasil disimpan.');
    }
    public function student(Request $request, EnrollmentService $service, ?Student $student = null) {
        $data = $request->validate(['student_code'=>['required','string','max:100',Rule::unique('students')->ignore($student?->id)],'name'=>'required|string|max:255','is_archived'=>'required|boolean','classroom_id'=>'nullable|exists:classrooms,id']);
        DB::transaction(function () use ($data,$student,$service) {
            $classroomId = $data['classroom_id'] ?? null; unset($data['classroom_id']);
            if ($student) $student->update($data); else $student = Student::create($data);
            if ($classroomId) $service->enroll($student, Classroom::findOrFail($classroomId));
        });
        return back()->with('success','Siswa berhasil disimpan. Riwayat kelas sebelumnya dipertahankan.');
    }
    public function import(Request $request, EnrollmentService $service) {
        $data = $request->validate(['classroom_id'=>'required|exists:classrooms,id','csv'=>'required|string|max:1048576']);
        $classroom = Classroom::findOrFail($data['classroom_id']);
        $lines = preg_split('/\r\n|\r|\n/', trim($data['csv']));
        if (count($lines) > 501) throw ValidationException::withMessages(['csv'=>'Maksimal 500 siswa per impor.']);
        $header = str_getcsv(array_shift($lines));
        $header[0] = ltrim($header[0] ?? '', "\xEF\xBB\xBF");
        if ($header !== ['student_code','name']) throw ValidationException::withMessages(['csv'=>'Header CSV harus student_code,name.']);
        $seen = [];
        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            $row = str_getcsv($line);
            if (count($row) !== 2 || trim($row[0]) === '' || trim($row[1]) === '' || mb_strlen($row[0]) > 100 || mb_strlen($row[1]) > 255) throw ValidationException::withMessages(['csv'=>'Setiap baris harus berisi kode dan nama siswa yang valid.']);
            [$code, $name] = array_map('trim', $row);
            if (isset($seen[$code])) throw ValidationException::withMessages(['csv'=>'Kode siswa berulang dalam CSV.']);
            $seen[$code] = true;
            $rows[] = [$code, $name];
        }
        if (!$rows) throw ValidationException::withMessages(['csv'=>'CSV tidak berisi siswa.']);
        DB::transaction(function () use ($rows, $classroom, $service) {
            foreach ($rows as [$code, $name]) {
                $student = Student::firstOrCreate(['student_code'=>$code], ['name'=>$name]);
                if ($student->name !== $name) throw ValidationException::withMessages(['csv'=>'Nama pada kode siswa yang sudah ada berbeda. Periksa data sebelum impor.']);
                $service->enroll($student, $classroom);
            }
        });
        return back()->with('success',count($rows).' siswa berhasil diimpor.');
    }
}
