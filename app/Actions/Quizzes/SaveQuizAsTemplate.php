<?php

namespace App\Actions\Quizzes;

use App\Models\Quiz;
use App\Models\QuizTemplate;
use App\Models\User;
use App\Services\Quizzes\QuizContentSerializer;

class SaveQuizAsTemplate
{
    public function __construct(
        protected QuizContentSerializer $serializer,
    ) {}

    public function handle(Quiz $quiz, User $user): QuizTemplate
    {
        return QuizTemplate::create([
            'workspace_id' => $quiz->workspace_id,
            'created_by' => $user->id,
            'name' => $quiz->name,
            'description' => $quiz->description,
            'category' => $quiz->type->category(),
            'type' => $quiz->type,
            'content' => $this->serializer->toArray($quiz),
        ]);
    }
}
