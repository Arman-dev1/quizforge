<?php

namespace App\Enums;

enum QuizStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Published => __('Published'),
            self::Closed => __('Closed'),
            self::Archived => __('Archived'),
        };
    }

    /**
     * Tailwind classes for the status badge (light + dark).
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
            self::Published => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400',
            self::Closed => 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400',
            self::Archived => 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-500',
        };
    }

    /** Dot colour for the status pill — status reads at a glance, before the word. */
    public function dotClass(): string
    {
        return match ($this) {
            self::Draft => 'bg-zinc-400',
            self::Published => 'bg-emerald-500',
            self::Closed => 'bg-amber-500',
            self::Archived => 'bg-zinc-300 dark:bg-zinc-600',
        };
    }

    /** One line explaining what this status means for respondents. */
    public function description(): string
    {
        return match ($this) {
            self::Draft => __('Not published yet — only your team can see it.'),
            self::Published => __('Live and accepting responses.'),
            self::Closed => __('Visitors see a notice instead of the quiz.'),
            self::Archived => __('Hidden from your workspace and not accepting responses.'),
        };
    }
}
