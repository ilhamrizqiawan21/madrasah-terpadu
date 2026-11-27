<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Exam;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class ImportExamEssayScoresRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user('web');
        /** @var Exam|null $exam */
        $exam = $this->route('exam');

        if (! $user instanceof User || ! $exam instanceof Exam) {
            return false;
        }

        if ($user->cannot('update', $exam)) {
            abort(404);
        }

        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx', 'max:2048'],
        ];
    }

    public function excelFile(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('file');

        return $file;
    }
}
