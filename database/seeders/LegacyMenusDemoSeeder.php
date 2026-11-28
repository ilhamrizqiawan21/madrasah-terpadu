<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AlumniActivity;
use App\Enums\AlumniIncomeRange;
use App\Enums\CurriculumRelevance;
use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\MeetingAudience;
use App\Enums\MutationType;
use App\Enums\StaffAssignmentType;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\AlumniTracerResponse;
use App\Models\Building;
use App\Models\ClassGroup;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Extracurricular;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\LessonPeriod;
use App\Models\Major;
use App\Models\Meeting;
use App\Models\Room;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\StudentMutation;
use App\Models\Unit;
use App\Models\User;
use App\Services\Students\StudentMutationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Data uji untuk menu hasil duplikasi alur aplikasi lama (Guru & Staf, Undangan Rapat, Siswa, Orang Tua,
 * Mutasi Siswa). Hanya lokal/staging; aman dijalankan berulang.
 */
class LegacyMenusDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('LegacyMenusDemoSeeder dilarang berjalan di lingkungan produksi.');
        }

        $units = Unit::query()->where('is_active', true)->orderBy('id')->get();
        $admin = User::query()->where('username', 'admin.uji')->first()
            ?? User::role('admin')->orderBy('id')->first();

        if ($units->isEmpty() || $admin === null) {
            $this->command?->warn('Jalankan DemoSeeder dulu (butuh unit dan admin).');

            return;
        }

        $this->staffProfiles($units->first());
        $this->meetings($units, $admin);
        $this->studentFamilies();
        $this->guardianCards();
        $this->mutations($units, $admin);
        $this->alumni($admin);
        $this->facilities($units);
        $this->academicMasters($units);
        $this->lessonPeriods($units);
        $this->extracurriculars($units);
    }

    private function staffProfiles(Unit $unit): void
    {
        $profiles = [
            'guru.uji' => [StaffAssignmentType::Teacher, EmploymentStatus::Gty, 1_400_000, 'S.Pd', 'P'],
            'bendahara.uji' => [StaffAssignmentType::Staff, EmploymentStatus::Pty, 2_000_000, 'S.E', 'P'],
            'bk.uji' => [StaffAssignmentType::Teacher, EmploymentStatus::Gtt, 1_200_000, 'S.Psi', 'L'],
            'kepala.uji' => [StaffAssignmentType::UnitAdmin, EmploymentStatus::Gty, 3_000_000, 'M.Pd', 'L'],
            'gerbang.uji' => [StaffAssignmentType::Security, EmploymentStatus::Ptt, 1_000_000, null, 'L'],
            'kantin.uji' => [StaffAssignmentType::Cleaning, EmploymentStatus::Ptt, 900_000, null, 'P'],
        ];

        $i = 1;
        foreach ($profiles as $username => [$assignment, $status, $salary, $suffix, $gender]) {
            $user = User::query()->where('username', $username)->first();
            if ($user === null) {
                continue;
            }

            StaffProfile::query()->updateOrCreate(['user_id' => $user->id], [
                'unit_id' => $unit->id,
                'title_suffix' => $suffix,
                'nik' => '74010100000000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'gender' => $gender,
                'birth_place' => 'Kolaka',
                'birth_date' => '1990-01-'.str_pad((string) (10 + $i), 2, '0', STR_PAD_LEFT),
                'address' => 'Jl. Uji No. '.$i.', Kolaka',
                'assignment_type' => $assignment,
                'employment_status' => $status,
                'base_salary' => $salary,
                'allowance' => 250_000,
                'hourly_rate' => $assignment === StaffAssignmentType::Teacher ? 25_000 : 0,
            ]);
            $i++;
        }
    }

    /**
     * @param  Collection<int, Unit>  $units
     */
    private function meetings($units, User $admin): void
    {
        $today = CarbonImmutable::now('Asia/Makassar');
        $rows = [
            ['Rapat evaluasi kurikulum semester ganjil', $today->subDays(14), MeetingAudience::Teachers, $units->first()?->id, "1. Jadwal PTS disepakati minggu kedua Desember.\n2. Modul ajar dikumpulkan paling lambat Jumat."],
            ['Koordinasi wali kelas: penerimaan rapor', $today->addDays(5), MeetingAudience::Homeroom, $units->first()?->id, null],
            ['Rapat kerja yayasan akhir tahun', $today->addDays(12), MeetingAudience::AllStaff, null, null],
        ];

        foreach ($rows as [$title, $date, $audience, $unitId, $minutes]) {
            Meeting::query()->firstOrCreate(['title' => $title], [
                'held_on' => $date->toDateString(),
                'audience' => $audience,
                'unit_id' => $unitId,
                'minutes' => $minutes,
                'created_by' => $admin->id,
            ]);
        }
    }

    private function studentFamilies(): void
    {
        $jobs = ['Petani', 'Wiraswasta', 'Guru', 'Nelayan', 'Karyawan swasta', 'PNS'];

        Student::query()->whereNull('family_card_number')->orderBy('id')->limit(60)->get()
            ->each(function (Student $student) use ($jobs): void {
                $n = $student->id;
                $student->forceFill([
                    'family_card_number' => '7401'.str_pad((string) $n, 12, '0', STR_PAD_LEFT),
                    'religion' => 'Islam',
                    'father_name' => 'Ayah Uji '.$n,
                    'father_occupation' => $jobs[$n % count($jobs)],
                    'mother_name' => 'Ibu Uji '.$n,
                    'mother_occupation' => $jobs[($n + 2) % count($jobs)],
                ])->save();
            });
    }

    private function guardianCards(): void
    {
        Guardian::query()->whereNull('family_card_number')->with('students:id,family_card_number')->orderBy('id')->limit(60)->get()
            ->each(function (Guardian $guardian): void {
                $card = $guardian->students->first()?->family_card_number;
                $guardian->forceFill([
                    'family_card_number' => $card ?? '7409'.str_pad((string) $guardian->id, 12, '0', STR_PAD_LEFT),
                    'address' => $guardian->address ?? 'Jl. Uji Keluarga No. '.$guardian->id.', Kolaka',
                ])->save();
            });
    }

    /**
     * @param  Collection<int, Unit>  $units
     */
    private function mutations($units, User $admin): void
    {
        $service = app(StudentMutationService::class);
        $date = CarbonImmutable::now('Asia/Makassar')->subDays(3)->toDateString();
        $unit = $units->first();

        $yearId = AcademicYear::query()->where('is_active', true)->value('id');
        $classroomId = $yearId === null ? null : Classroom::query()
            ->where('unit_id', $unit->id)->where('academic_year_id', $yearId)->orderBy('id')->value('id');
        // Siswa uji selalu punya rombel tahun aktif, sama seperti siswa demo lainnya.
        $enroll = function (Student $student) use ($yearId, $classroomId): void {
            if ($yearId !== null && $classroomId !== null) {
                Enrollment::query()->firstOrCreate(
                    ['student_id' => $student->id, 'academic_year_id' => $yearId],
                    ['classroom_id' => $classroomId],
                );
            }
        };

        $cases = [
            ['9901', 'Siswa Pindah Uji', fn (Student $s) => $service->mutateOut($s, $date, 'MI Negeri 1 Kolaka', 'Ikut orang tua pindah tugas', $admin)],
            ['9902', 'Siswa DO Uji', fn (Student $s) => $service->mutateOut($s, $date, null, 'Drop out: tidak hadir lebih dari 3 bulan', $admin, dropout: true)],
            ['9903', 'Siswa Lulus Uji', fn (Student $s) => $service->mutateOut($s, $date, null, 'Lulus', $admin, graduate: true)],
        ];

        foreach ($cases as [$suffix, $name, $mutate]) {
            $student = Student::query()->firstOrCreate(['nis' => 'UJI-'.$suffix], [
                'unit_id' => $unit->id,
                'name' => $name,
                'gender' => Gender::Male,
                'status' => StudentStatus::Active,
            ]);

            $enroll($student);

            if (! StudentMutation::query()->where('student_id', $student->id)->exists() && $student->status === StudentStatus::Active) {
                $mutate($student);
            }
        }

        // Mutasi masuk: dicatat langsung karena siswanya sudah ada di data uji.
        $incoming = Student::query()->firstOrCreate(['nis' => 'UJI-9904'], [
            'unit_id' => $unit->id,
            'name' => 'Siswa Pindahan Uji',
            'gender' => Gender::Female,
            'status' => StudentStatus::Active,
        ]);
        $enroll($incoming);
        StudentMutation::query()->firstOrCreate(['student_id' => $incoming->id, 'type' => MutationType::In], [
            'mutated_on' => $date,
            'school_name' => 'SD Negeri 2 Kolaka',
            'reason' => 'Pindah domisili',
            'recorded_by' => $admin->id,
        ]);
    }

    private function alumni(User $admin): void
    {
        $rows = [
            [AlumniActivity::Study, 'MTs Negeri 1 Kolaka', 'Kelas unggulan', AlumniIncomeRange::None, CurriculumRelevance::Very, 'Program tahfidz sangat membantu.'],
        ];

        Student::query()->where('status', StudentStatus::Graduated->value)->orderBy('id')->limit(5)->get()
            ->each(function (Student $student, int $i) use ($rows, $admin): void {
                [$activity, $institution, $program, $income, $relevance, $feedback] = $rows[$i % count($rows)];
                AlumniTracerResponse::query()->firstOrCreate(['student_id' => $student->id], [
                    'graduation_year' => (int) CarbonImmutable::now('Asia/Makassar')->year,
                    'activity' => $activity,
                    'institution' => $institution,
                    'program' => $program,
                    'income_range' => $income,
                    'relevance' => $relevance,
                    'feedback' => $feedback,
                    'recorded_by' => $admin->id,
                ]);
            });
    }

    /**
     * @param  Collection<int, Unit>  $units
     */
    private function facilities($units): void
    {
        foreach ($units as $unit) {
            $building = Building::query()->firstOrCreate(['unit_id' => $unit->id, 'code' => 'GD-'.$unit->code], ['name' => 'Gedung Utama '.$unit->code]);
            foreach ([['R01', 'Ruang Kelas 1', 28], ['R02', 'Ruang Kelas 2', 28], ['LAB', 'Laboratorium Komputer', 20]] as [$code, $name, $capacity]) {
                Room::query()->firstOrCreate(['building_id' => $building->id, 'code' => $code], ['name' => $name, 'capacity' => $capacity]);
            }
        }
    }

    /**
     * @param  Collection<int, Unit>  $units
     */
    private function academicMasters($units): void
    {
        $levels = ['MI' => ['1', '2', '3', '4', '5', '6'], 'RA' => ['A', 'B'], 'TPQ' => ['Jilid 1', 'Jilid 2', 'Jilid 3']];

        foreach ($units as $unit) {
            foreach ($levels[$unit->code] ?? ['1'] as $order => $name) {
                GradeLevel::query()->firstOrCreate(['unit_id' => $unit->id, 'name' => $name], ['sort_order' => $order + 1]);
            }
            foreach (['A', 'B'] as $group) {
                ClassGroup::query()->firstOrCreate(['unit_id' => $unit->id, 'name' => $group]);
            }
            Major::query()->firstOrCreate(['unit_id' => $unit->id, 'name' => 'Reguler']);
        }
    }

    /**
     * @param  Collection<int, Unit>  $units
     */
    private function lessonPeriods($units): void
    {
        $slots = [
            ['JP 1', '07:30', '08:05', false], ['JP 2', '08:05', '08:40', false], ['JP 3', '08:40', '09:15', false],
            ['Istirahat', '09:15', '09:30', true], ['JP 4', '09:30', '10:05', false], ['JP 5', '10:05', '10:40', false],
        ];

        foreach ($units as $unit) {
            foreach ($slots as $order => [$name, $start, $end, $break]) {
                LessonPeriod::query()->firstOrCreate(['unit_id' => $unit->id, 'name' => $name], [
                    'starts_at' => $start, 'ends_at' => $end, 'sort_order' => $order + 1, 'is_break' => $break,
                ]);
            }
        }
    }

    /**
     * @param  Collection<int, Unit>  $units
     */
    private function extracurriculars($units): void
    {
        $unit = $units->first();
        if ($unit === null) {
            return;
        }

        foreach ([['Pramuka', 'Kak Rahmat'], ['Tahfidz', 'Ust. Hamzah'], ['Futsal', 'Coach Andi']] as [$name, $coach]) {
            Extracurricular::query()->firstOrCreate(['unit_id' => $unit->id, 'name' => $name], ['coach_type' => 'external', 'coach_name' => $coach]);
        }
    }
}
