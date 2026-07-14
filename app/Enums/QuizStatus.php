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
            self::Draft => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200',
            self::Published => 'bg-green-100 text-green-800 dark:bg-green-900/60 dark:text-green-300',
            self::Closed => 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300',
            self::Archived => 'bg-red-100 text-red-800 dark:bg-red-900/60 dark:text-red-300',
        };
    }
}
