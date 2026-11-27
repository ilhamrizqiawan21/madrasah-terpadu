<?php

declare(strict_types=1);

namespace App\Http\Controllers\Exam;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Services\Cbt\ExamQuestionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExamQuestionImageController extends Controller
{
    public function __invoke(Request $request, Exam $exam, ExamQuestion $question): StreamedResponse
    {
        abort_unless($question->exam_id === $exam->id && $question->image_path !== null, 404);

        $disk = Storage::disk(ExamQuestionService::DISK);
        abort_unless($disk->exists($question->image_path), 404);

        return $disk->response($question->image_path);
    }
}
