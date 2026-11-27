<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use App\Services\Students\StudentImportService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StudentImportRequest extends FormRequest
{
    /**
     * Impor bisa membuat akun wali, jadi butuh izin kelola siswa sekaligus kelola wali.
     */
    public function authorize(): bool
    {
        $user = $this->user('web');

        return $user !== null
            && $user->can(Permission::StudentsManage->value)
            && $user->can(Permission::GuardiansManage->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // CSV sering terdeteksi sebagai text/plain, jadi `txt` diterima selama ekstensinya .csv.
            'file' => [
                'required', 'file',
                'mimes:xlsx,csv,txt', 'extensions:xlsx,csv',
                'max:'.StudentImportService::MAX_KILOBYTES,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Pilih berkas .xlsx atau .csv.',
            'file.file' => 'Pilih berkas .xlsx atau .csv.',
            'file.mimes' => 'Berkas harus berformat .xlsx atau .csv.',
            'file.extensions' => 'Berkas harus berformat .xlsx atau .csv.',
            'file.max' => 'Ukuran berkas paling besar 2 MB.',
        ];
    }

    public function importFile(): UploadedFile
    {
        $file = $this->file('file');

        abort_unless($file instanceof UploadedFile, 422);

        return $file;
    }
}
