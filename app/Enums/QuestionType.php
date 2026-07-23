<?php

namespace App\Enums;

enum QuestionType: string
{
    case SingleChoice = 'single_choice';
    case MultipleChoice = 'multiple_choice';
    case Dropdown = 'dropdown';
    case ImageChoice = 'image_choice';
    case YesNo = 'yes_no';
    case Ranking = 'ranking';
    case ShortText = 'short_text';
    case LongText = 'long_text';
    case Email = 'email';
    case Phone = 'phone';
    case Website = 'website';
    case Address = 'address';
    case Rating = 'rating';
    case OpinionScale = 'opinion_scale';
    case LinearScale = 'linear_scale';
    case Nps = 'nps';
    case Number = 'number';
    case Date = 'date';
    case Time = 'time';
    case FileUpload = 'file_upload';
    case Signature = 'signature';
    case Matrix = 'matrix';

    public function label(): string
    {
        return match ($this) {
            self::SingleChoice => __('Single Choice'),
            self::MultipleChoice => __('Multiple Choice'),
            self::Dropdown => __('Dropdown'),
            self::ImageChoice => __('Image Choice'),
            self::YesNo => __('Yes / No'),
            self::Ranking => __('Ranking'),
            self::ShortText => __('Short Text'),
            self::LongText => __('Long Text'),
            self::Email => __('Email'),
            self::Phone => __('Phone'),
            self::Website => __('Website'),
            self::Address => __('Address'),
            self::Rating => __('Rating'),
            self::OpinionScale => __('Opinion Scale'),
            self::LinearScale => __('Linear Scale'),
            self::Nps => __('Net Promoter Score'),
            self::Number => __('Number'),
            self::Date => __('Date'),
            self::Time => __('Time'),
            self::FileUpload => __('File Upload'),
            self::Signature => __('Signature'),
            self::Matrix => __('Matrix'),
        };
    }

    /**
     * Short one-line description shown on the type-picker cards.
     */
    public function description(): string
    {
        return match ($this) {
            self::SingleChoice => __('One answer only'),
            self::MultipleChoice => __('Select several'),
            self::Dropdown => __('Compact list'),
            self::ImageChoice => __('Pick a picture'),
            self::YesNo => __('Binary answer'),
            self::Ranking => __('Order by preference'),
            self::ShortText => __('Single line'),
            self::LongText => __('Paragraph answer'),
            self::Email => __('Validated address'),
            self::Phone => __('Number with format'),
            self::Website => __('URL field'),
            self::Address => __('Multi-line location'),
            self::Rating => __('Stars 1–5'),
            self::OpinionScale => __('Agree → disagree'),
            self::LinearScale => __('Numbered range'),
            self::Nps => __('0–10 NPS'),
            self::Number => __('Numeric input'),
            self::Date => __('Calendar picker'),
            self::Time => __('Hour & minute'),
            self::FileUpload => __('Accept documents'),
            self::Signature => __('Draw to sign'),
            self::Matrix => __('Grid of choices'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::SingleChoice => 'check-circle',
            self::MultipleChoice => 'list-bullet',
            self::Dropdown => 'chevron-up-down',
            self::ImageChoice => 'photo',
            self::YesNo => 'hand-thumb-up',
            self::Ranking => 'bars-arrow-up',
            self::ShortText => 'pencil',
            self::LongText => 'bars-3-bottom-left',
            self::Email => 'envelope',
            self::Phone => 'phone',
            self::Website => 'globe-alt',
            self::Address => 'map-pin',
            self::Rating => 'star',
            self::OpinionScale => 'adjustments-horizontal',
            self::LinearScale => 'arrows-right-left',
            self::Nps => 'presentation-chart-line',
            self::Number => 'hashtag',
            self::Date => 'calendar',
            self::Time => 'clock',
            self::FileUpload => 'arrow-up-tray',
            self::Signature => 'pencil-square',
            self::Matrix => 'table-cells',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::SingleChoice, self::MultipleChoice, self::Dropdown,
            self::ImageChoice, self::YesNo, self::Ranking => __('Choice'),
            self::ShortText, self::LongText => __('Text'),
            self::Email, self::Phone, self::Website, self::Address => __('Contact info'),
            self::Rating, self::OpinionScale, self::LinearScale, self::Nps, self::Number => __('Scales & numbers'),
            self::Date, self::Time => __('Date & time'),
            self::FileUpload, self::Signature, self::Matrix => __('Advanced'),
        };
    }

    /**
     * Whether respondents choose from editable answer options.
     */
    public function hasOptions(): bool
    {
        return in_array($this, [
            self::SingleChoice,
            self::MultipleChoice,
            self::Dropdown,
            self::ImageChoice,
            self::Ranking,
        ], true);
    }

    /**
     * Whether the "correct answer" flag applies (scored quizzes).
     */
    public function supportsCorrectAnswers(): bool
    {
        return in_array($this, [self::SingleChoice, self::MultipleChoice, self::Dropdown], true);
    }

    public function supportsPlaceholder(): bool
    {
        return in_array($this, [
            self::ShortText,
            self::LongText,
            self::Email,
            self::Phone,
            self::Website,
            self::Number,
            self::Dropdown,
        ], true);
    }

    public function defaultSettings(): array
    {
        return match ($this) {
            self::Rating => ['max' => 5],
            self::OpinionScale => ['min' => 1, 'max' => 10, 'min_label' => '', 'max_label' => ''],
            self::LinearScale => ['min' => 1, 'max' => 5, 'min_label' => '', 'max_label' => ''],
            self::Nps => ['min_label' => __('Not at all likely'), 'max_label' => __('Extremely likely')],
            self::Matrix => [
                'rows' => [__('Row 1'), __('Row 2')],
                'columns' => [__('Column 1'), __('Column 2')],
            ],
            self::ShortText => ['max_length' => null],
            self::LongText => ['max_length' => null],
            self::Number => ['min' => null, 'max' => null],
            default => [],
        };
    }

    /**
     * Labels for the options created with a new question of this type.
     *
     * @return array<int, string>
     */
    public function defaultOptionLabels(): array
    {
        return match ($this) {
            self::YesNo => [], // fixed answers, not editable options
            self::Ranking => [__('Item 1'), __('Item 2'), __('Item 3')],
            default => $this->hasOptions() ? [__('Option 1'), __('Option 2'), __('Option 3')] : [],
        };
    }

    /**
     * Types grouped by category for the picker, preserving enum order.
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
