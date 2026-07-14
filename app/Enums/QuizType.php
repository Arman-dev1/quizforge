<?php

namespace App\Enums;

enum QuizType: string
{
    case GeneralQuiz = 'general_quiz';
    case Survey = 'survey';
    case Assessment = 'assessment';
    case PersonalityQuiz = 'personality_quiz';
    case ProductRecommendation = 'product_recommendation';
    case LeadGeneration = 'lead_generation';
    case CustomerFeedback = 'customer_feedback';
    case EmployeeSurvey = 'employee_survey';
    case RecruitmentQuiz = 'recruitment_quiz';
    case Exam = 'exam';
    case Poll = 'poll';
    case Contest = 'contest';
    case RegistrationForm = 'registration_form';
    case ApplicationForm = 'application_form';

    public function label(): string
    {
        return match ($this) {
            self::GeneralQuiz => __('General Quiz'),
            self::Survey => __('Survey'),
            self::Assessment => __('Assessment'),
            self::PersonalityQuiz => __('Personality Quiz'),
            self::ProductRecommendation => __('Product Recommendation'),
            self::LeadGeneration => __('Lead Generation Quiz'),
            self::CustomerFeedback => __('Customer Feedback Survey'),
            self::EmployeeSurvey => __('Employee Survey'),
            self::RecruitmentQuiz => __('Recruitment Quiz'),
            self::Exam => __('Exam'),
            self::Poll => __('Poll'),
            self::Contest => __('Contest'),
            self::RegistrationForm => __('Registration Form'),
            self::ApplicationForm => __('Application Form'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::GeneralQuiz => __('Fun or educational quizzes with right and wrong answers.'),
            self::Survey => __('Collect opinions and structured feedback at scale.'),
            self::Assessment => __('Scored evaluations with pass/fail and grading.'),
            self::PersonalityQuiz => __('Outcome-based quizzes that map answers to a result type.'),
            self::ProductRecommendation => __('Guide shoppers to the right product with a few questions.'),
            self::LeadGeneration => __('Capture qualified leads with an engaging quiz.'),
            self::CustomerFeedback => __('Measure satisfaction, NPS, and customer sentiment.'),
            self::EmployeeSurvey => __('Engagement, pulse, and internal feedback surveys.'),
            self::RecruitmentQuiz => __('Screen candidates with skills and fit questions.'),
            self::Exam => __('Formal timed tests with strict scoring.'),
            self::Poll => __('One quick question, instant results.'),
            self::Contest => __('Competitions and giveaways with entries.'),
            self::RegistrationForm => __('Sign-ups for events, courses, or programs.'),
            self::ApplicationForm => __('Structured applications with file uploads.'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::GeneralQuiz => 'puzzle-piece',
            self::Survey => 'chat-bubble-left-right',
            self::Assessment => 'clipboard-document-check',
            self::PersonalityQuiz => 'sparkles',
            self::ProductRecommendation => 'shopping-bag',
            self::LeadGeneration => 'user-plus',
            self::CustomerFeedback => 'face-smile',
            self::EmployeeSurvey => 'building-office',
            self::RecruitmentQuiz => 'briefcase',
            self::Exam => 'academic-cap',
            self::Poll => 'chart-bar',
            self::Contest => 'trophy',
            self::RegistrationForm => 'clipboard-document-list',
            self::ApplicationForm => 'document-text',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::GeneralQuiz, self::PersonalityQuiz, self::Poll, self::Contest => __('Engagement'),
            self::LeadGeneration, self::ProductRecommendation => __('Marketing'),
            self::Assessment, self::Exam, self::RecruitmentQuiz => __('Assessment'),
            self::Survey, self::CustomerFeedback, self::EmployeeSurvey => __('Feedback'),
            self::RegistrationForm, self::ApplicationForm => __('Forms'),
        };
    }

    /**
     * Type-specific default settings applied at creation.
     */
    public function defaultSettings(): array
    {
        return [
            'show_progress_bar' => true,
            'scored' => in_array($this, [self::GeneralQuiz, self::Assessment, self::RecruitmentQuiz, self::Exam], true),
            'collect_leads' => in_array($this, [self::LeadGeneration, self::Contest, self::ProductRecommendation], true),
        ];
    }

    /**
     * Types grouped by category for the type picker, preserving enum order.
     *
     * @return array<string, array<int, self>>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::cases() as $type) {
            $groups[$type->category()][] = $type;
        }

        return $groups;
    }
}
