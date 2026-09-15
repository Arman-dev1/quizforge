<?php

use App\Models\Quiz;
use App\Services\Billing\UsageLimits;
use App\Services\Design\QuizDesign;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public Quiz $quiz;

    /** @var array<string, mixed> */
    public array $design = [];

    public bool $isPro = false;

    public bool $canRemoveBranding = false;

    // Live URLs for the stored assets (entangled into the Alpine preview).
    public ?string $logoUrl = null;

    public ?string $coverUrl = null;

    public ?string $bgImageUrl = null;

    // Temporary uploads.
    public $logo = null;

    public $cover = null;

    public $bgImage = null;

    public function mount(Quiz $quiz): void
    {
        $this->authorize('update', $quiz);

        $this->quiz = $quiz;
        $this->design = QuizDesign::forQuiz($quiz);
        $this->isPro = app(UsageLimits::class)->feature($quiz->workspace, 'custom_code');
        $this->canRemoveBranding = app(UsageLimits::class)->feature($quiz->workspace, 'remove_branding');

        $this->refreshUrls();
    }

    protected function guard(): void
    {
        $this->authorize('update', $this->quiz);
    }

    protected function refreshUrls(): void
    {
        $disk = Storage::disk('public');
        $this->logoUrl = $this->design['logo'] ? $disk->url($this->design['logo']) : null;
        $this->coverUrl = $this->design['cover'] ? $disk->url($this->design['cover']) : null;
        $this->bgImageUrl = $this->design['background_image'] ? $disk->url($this->design['background_image']) : null;
    }

    protected function persist(array $design): void
    {
        $settings = $this->quiz->settings ?? [];
        $settings['design'] = $design;
        $this->quiz->update(['settings' => $settings]);
    }

    /** Persist a design coming from the editor, keeping asset paths + gating. */
    public function save(array $design): void
    {
        $this->guard();

        $clean = QuizDesign::merge($design);
        $current = QuizDesign::forQuiz($this->quiz);

        // Assets are managed by their own upload handlers, not the editor.
        $clean['logo'] = $current['logo'];
        $clean['cover'] = $current['cover'];
        $clean['background_image'] = $current['background_image'];

        // Custom CSS/JS are a Pro capability — never writable on the free plan.
        if (! $this->isPro) {
            $clean['custom_css'] = $current['custom_css'];
            $clean['custom_js'] = $current['custom_js'];
        }

        // Same for hiding the branding: the toggle is hidden on the free plan,
        // but the payload is client-supplied, so enforce it here too.
        if (! $this->canRemoveBranding) {
            $clean['hide_branding'] = $current['hide_branding'] ?? false;
        }

        $this->persist($clean);
        $this->design = $clean;
        $this->refreshUrls();
        $this->dispatch('design-saved');
    }

    public function resetDesign(): void
    {
        $this->guard();

        foreach (['logo', 'cover', 'background_image'] as $key) {
            if ($this->design[$key] ?? null) {
                Storage::disk('public')->delete($this->design[$key]);
            }
        }

        $this->design = QuizDesign::defaults();
        $this->persist($this->design);
        $this->refreshUrls();
        $this->dispatch('design-reset');
    }

    public function updatedLogo(): void
    {
        $this->storeAsset('logo', 'logo');
    }

    public function updatedCover(): void
    {
        $this->storeAsset('cover', 'cover');
    }

    public function updatedBgImage(): void
    {
        $this->storeAsset('bgImage', 'background_image');
    }

    protected function storeAsset(string $prop, string $key): void
    {
        $this->guard();

        $this->validateOnly($prop, [$prop => ['image', 'max:3072']]);

        $file = $this->{$prop};

        if (! $file) {
            return;
        }

        if ($this->design[$key] ?? null) {
            Storage::disk('public')->delete($this->design[$key]);
        }

        $this->design[$key] = $file->store("quiz-design/{$this->quiz->id}", 'public');
        $this->persist($this->design);
        $this->refreshUrls();

        $this->{$prop} = null;
        $this->dispatch('design-saved');
    }

    public function removeAsset(string $key): void
    {
        $this->guard();

        if (! in_array($key, ['logo', 'cover', 'background_image'], true)) {
            return;
        }

        if ($this->design[$key] ?? null) {
            Storage::disk('public')->delete($this->design[$key]);
        }

        $this->design[$key] = null;
        $this->persist($this->design);
        $this->refreshUrls();
        $this->dispatch('design-saved');
    }

    public function with(): array
    {
        return [
            'catalog' => QuizDesign::catalog(),
            'defaults' => QuizDesign::defaults(),
            'responseCount' => $this->quiz->responses()->count(),
        ];
    }
}; ?>

<section
    class="w-full"
    x-data="{
        d: @js($design),
        cfg: @js($catalog),
        defaults: @js($defaults),
        logoUrl: @entangle('logoUrl'),
        coverUrl: @entangle('coverUrl'),
        bgImageUrl: @entangle('bgImageUrl'),
        get theme() { return this.cfg.themes[this.d.theme] || this.cfg.themes.light; },
        get radius() { return Math.max(0, Math.min(40, parseInt(this.d.border_radius) || 0)); },
        get btnRadius() {
            if (this.d.button_style === 'square') return '0px';
            if (this.d.button_style === 'pill') return '999px';
            return this.radius + 'px';
        },
        ink(hex) {
            let h = (hex || '').replace('#', '');
            if (h.length === 3) h = h.split('').map(c => c + c).join('');
            if (h.length < 6) return '#ffffff';
            const r = parseInt(h.substr(0,2),16), g = parseInt(h.substr(2,2),16), b = parseInt(h.substr(4,2),16);
            return (0.2126*r + 0.7152*g + 0.0722*b) / 255 > 0.6 ? '#0f172a' : '#ffffff';
        },
        vars() {
            const t = this.theme;
            return {
                '--qf-primary': this.d.primary,
                '--qf-primary-ink': this.ink(this.d.primary),
                '--qf-secondary': this.d.secondary,
                '--qf-text': t.text,
                '--qf-muted': t.muted,
                '--qf-card': t.card,
                '--qf-card-border': t.card_border,
                '--qf-radius': this.radius + 'px',
                '--qf-btn-radius': this.btnRadius,
                '--qf-font': (this.cfg.fonts[this.d.font_family] || this.cfg.fonts.inter).stack,
                '--qf-font-size': (this.cfg.sizes[this.d.font_size] || 16) + 'px',
            };
        },
        background() {
            if (this.d.background_type === 'gradient') return `linear-gradient(135deg, ${this.d.gradient_from} 0%, ${this.d.gradient_to} 100%)`;
            if (this.d.background_type === 'image' && this.bgImageUrl) return `center / cover no-repeat url('${this.bgImageUrl}')`;
            return this.d.background_color;
        },
        cardStyle() {
            const base = { background: 'var(--qf-card)', color: 'var(--qf-text)', borderRadius: 'calc(var(--qf-radius) + 4px)' };
            if (this.d.card_style === 'border') return { ...base, border: '1px solid var(--qf-card-border)' };
            if (this.d.card_style === 'glass') return { ...base, background: 'rgba(255,255,255,.65)', border: '1px solid rgba(255,255,255,.6)', backdropFilter: 'blur(12px)', boxShadow: '0 12px 40px rgba(2,6,23,.14)' };
            return { ...base, border: '1px solid transparent', boxShadow: '0 18px 40px -18px rgba(2,6,23,.35)' };
        },
        optionStyle(active) {
            return {
                borderRadius: 'var(--qf-btn-radius)',
                border: active ? '2px solid var(--qf-primary)' : '1px solid var(--qf-card-border)',
                background: active ? 'color-mix(in srgb, var(--qf-primary) 12%, transparent)' : 'transparent',
                color: 'var(--qf-text)',
            };
        },
        inputStyle() {
            if (this.d.input_style === 'filled') return { border: '1px solid transparent', background: 'color-mix(in srgb, var(--qf-text) 7%, transparent)', borderRadius: 'var(--qf-radius)' };
            if (this.d.input_style === 'underline') return { border: '0', borderBottom: '2px solid var(--qf-card-border)', borderRadius: '0', background: 'transparent' };
            return { border: '1px solid var(--qf-card-border)', background: 'transparent', borderRadius: 'var(--qf-radius)' };
        },
        reset() { this.d = JSON.parse(JSON.stringify(this.defaults)); $wire.resetDesign(); },
    }"
>
    <x-page-header
        :title="$quiz->name"
        :back="route('quizzes.index')"
        :back-label="__('Quizzes')"
    >
        <x-slot:meta>
            <x-status-pill :status="$quiz->status" />
            <span>{{ __('Changes preview instantly on the right.') }}</span>
        </x-slot:meta>

        <x-action-message on="design-saved" class="text-xs font-semibold text-teal-600 dark:text-teal-400">{{ __('Saved') }}</x-action-message>
        <flux:button variant="filled" icon="arrow-uturn-left" x-on:click="reset()">{{ __('Reset') }}</flux:button>
        <flux:button variant="primary" icon="check" x-on:click="$wire.save(d)">{{ __('Save design') }}</flux:button>

        <x-slot:tabs>
            <x-quiz-nav :quiz="$quiz" :response-count="$responseCount" />
        </x-slot:tabs>
    </x-page-header>

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,26rem)] xl:grid-cols-[minmax(0,1fr)_minmax(0,30rem)]">
        {{-- ─────────────── Controls ─────────────── --}}
        <div class="space-y-5">
            {{-- Theme --}}
            <x-design.section :title="__('Theme')" :subtitle="__('A starting point for surfaces and text.')" icon="swatch">
                <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                    @foreach ($catalog['themes'] as $key => $theme)
                        <button type="button" x-on:click="d.theme = '{{ $key }}'"
                            class="rounded-xl border p-3 text-left transition"
                            :class="d.theme === '{{ $key }}' ? 'border-teal-500 ring-1 ring-teal-200 dark:ring-teal-900' : 'border-zinc-200 hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600'">
                            <span class="flex gap-1">
                                <span class="size-4 rounded-full" style="background: {{ $theme['card'] }}; border:1px solid {{ $theme['card_border'] }}"></span>
                                <span class="size-4 rounded-full" style="background: {{ $theme['text'] }}"></span>
                            </span>
                            <span class="mt-2 block text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ $theme['label'] }}</span>
                        </button>
                    @endforeach
                </div>
            </x-design.section>

            {{-- Colors --}}
            <x-design.section :title="__('Colors')" :subtitle="__('Primary drives buttons and accents.')" icon="paint-brush">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-design.color label="{{ __('Primary') }}" model="d.primary" />
                    <x-design.color label="{{ __('Secondary') }}" model="d.secondary" />
                </div>
            </x-design.section>

            {{-- Typography --}}
            <x-design.section :title="__('Typography')" icon="language">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-design.label>{{ __('Font family') }}</x-design.label>
                        <select x-model="d.font_family" class="mt-1.5 w-full rounded-lg border-zinc-300 bg-white text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
                            @foreach ($catalog['fonts'] as $key => $font)
                                <option value="{{ $key }}">{{ $font['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-design.label>{{ __('Font size') }}</x-design.label>
                        <x-design.segmented model="d.font_size" :options="['sm' => __('Small'), 'base' => __('Medium'), 'lg' => __('Large')]" />
                    </div>
                </div>
            </x-design.section>

            {{-- Buttons & shape --}}
            <x-design.section :title="__('Buttons & shape')" icon="cube">
                <x-design.label>{{ __('Button style') }}</x-design.label>
                <x-design.segmented model="d.button_style" :options="$catalog['buttonStyles']" />

                <div class="mt-4">
                    <div class="flex items-center justify-between">
                        <x-design.label>{{ __('Border radius') }}</x-design.label>
                        <span class="text-xs font-medium tabular-nums text-zinc-500 dark:text-zinc-400" x-text="d.border_radius + 'px'"></span>
                    </div>
                    <input type="range" min="0" max="28" x-model="d.border_radius" class="mt-2 w-full accent-teal-600" />
                </div>
            </x-design.section>

            {{-- Background --}}
            <x-design.section :title="__('Background')" icon="photo">
                <x-design.segmented model="d.background_type" :options="$catalog['backgroundTypes']" />

                <div class="mt-4" x-show="d.background_type === 'color'" x-cloak>
                    <x-design.color label="{{ __('Background color') }}" model="d.background_color" />
                </div>
                <div class="mt-4 grid gap-4 sm:grid-cols-2" x-show="d.background_type === 'gradient'" x-cloak>
                    <x-design.color label="{{ __('Gradient start') }}" model="d.gradient_from" />
                    <x-design.color label="{{ __('Gradient end') }}" model="d.gradient_to" />
                </div>
                <div class="mt-4" x-show="d.background_type === 'image'" x-cloak>
                    <x-design.upload model="bgImage" :url-var="'bgImageUrl'" remove-key="background_image" :label="__('Background image')" />
                </div>
            </x-design.section>

            {{-- Card, progress, inputs --}}
            <x-design.section :title="__('Components')" icon="rectangle-group">
                <div class="space-y-4">
                    <div>
                        <x-design.label>{{ __('Card style') }}</x-design.label>
                        <x-design.segmented model="d.card_style" :options="$catalog['cardStyles']" />
                    </div>
                    <div>
                        <x-design.label>{{ __('Progress bar') }}</x-design.label>
                        <x-design.segmented model="d.progress_style" :options="$catalog['progressStyles']" />
                    </div>
                    <div>
                        <x-design.label>{{ __('Input fields') }}</x-design.label>
                        <x-design.segmented model="d.input_style" :options="$catalog['inputStyles']" />
                    </div>
                </div>
            </x-design.section>

            {{-- Branding --}}
            <x-design.section :title="__('Branding')" :subtitle="__('Logo shows on the card; cover sits above it.')" icon="sparkles">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-design.upload model="logo" :url-var="'logoUrl'" remove-key="logo" :label="__('Logo')" />
                    <x-design.upload model="cover" :url-var="'coverUrl'" remove-key="cover" :label="__('Cover image')" />
                </div>
            </x-design.section>

            {{-- Branding (Pro) --}}
            <x-design.section :title="__('Branding')" icon="sparkles" :pro="true">
                @if ($canRemoveBranding)
                    <label class="flex cursor-pointer items-start gap-3">
                        <input type="checkbox" x-model="d.hide_branding" class="mt-0.5 size-4 rounded border-zinc-300 accent-teal-600 dark:border-zinc-600" />
                        <span>
                            <span class="block text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ __('Hide “Powered by QuizForge”') }}</span>
                            <span class="mt-0.5 block text-xs text-zinc-500 dark:text-zinc-400">{{ __('Removes the footer line from your published quiz.') }}</span>
                        </span>
                    </label>
                @else
                    <div class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-zinc-300 p-6 text-center dark:border-zinc-700">
                        <span class="flex size-10 items-center justify-center rounded-full bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400">
                            <flux:icon.lock-closed class="size-5" />
                        </span>
                        <p class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('Removing QuizForge branding is a Pro feature.') }}</p>
                        <flux:button :href="route('settings.billing')" wire:navigate variant="primary" size="sm">{{ __('Upgrade to Pro') }}</flux:button>
                    </div>
                @endif
            </x-design.section>

            {{-- Advanced (Pro) --}}
            <x-design.section :title="__('Custom code')" icon="code-bracket" :pro="true">
                @if ($isPro)
                    <div class="space-y-4">
                        <div>
                            <x-design.label>{{ __('Custom CSS') }}</x-design.label>
                            <textarea x-model="d.custom_css" rows="4" spellcheck="false" placeholder=".qf-card &#123; ... &#125;"
                                class="mt-1.5 w-full rounded-lg border-zinc-300 bg-white font-mono text-xs shadow-sm focus:border-teal-500 focus:ring-teal-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white"></textarea>
                        </div>
                        <div>
                            <x-design.label>{{ __('Custom JavaScript') }}</x-design.label>
                            <textarea x-model="d.custom_js" rows="4" spellcheck="false" placeholder="// runs on the published quiz"
                                class="mt-1.5 w-full rounded-lg border-zinc-300 bg-white font-mono text-xs shadow-sm focus:border-teal-500 focus:ring-teal-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white"></textarea>
                        </div>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Injected only on the published, public quiz — not in this preview.') }}</p>
                    </div>
                @else
                    <div class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-zinc-300 p-6 text-center dark:border-zinc-700">
                        <span class="flex size-10 items-center justify-center rounded-full bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400">
                            <flux:icon.lock-closed class="size-5" />
                        </span>
                        <p class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('Custom CSS & JavaScript are a Pro feature.') }}</p>
                        <flux:button :href="route('settings.billing')" wire:navigate variant="primary" size="sm">{{ __('Upgrade to Pro') }}</flux:button>
                    </div>
                @endif
            </x-design.section>
        </div>

        {{-- ─────────────── Live preview ─────────────── --}}
        <div class="lg:sticky lg:top-6 lg:h-fit">
            <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950">
                <div class="flex items-center gap-1.5 border-b border-zinc-200 bg-white px-4 py-2.5 dark:border-zinc-800 dark:bg-zinc-900">
                    <span class="size-2.5 rounded-full bg-red-400"></span>
                    <span class="size-2.5 rounded-full bg-amber-400"></span>
                    <span class="size-2.5 rounded-full bg-green-400"></span>
                    <span class="ml-2 flex items-center gap-1 text-[11px] font-medium text-zinc-400">
                        <flux:icon.eye class="size-3.5" />{{ __('Live preview') }}
                    </span>
                </div>

                {{-- The quiz surface --}}
                <div class="p-4 sm:p-6" :style="{ ...vars(), background: background(), fontFamily: 'var(--qf-font)', fontSize: 'var(--qf-font-size)' }">
                    <div class="mx-auto max-w-md">
                        {{-- Cover --}}
                        <template x-if="coverUrl">
                            <img :src="coverUrl" alt="" class="mb-4 h-28 w-full object-cover" :style="{ borderRadius: 'calc(var(--qf-radius) + 4px)' }" />
                        </template>

                        <div class="p-5 sm:p-6" :style="cardStyle()">
                            {{-- Logo --}}
                            <template x-if="logoUrl">
                                <img :src="logoUrl" alt="" class="mb-4 h-9 w-auto" />
                            </template>

                            {{-- Progress --}}
                            <div class="mb-5" x-show="d.progress_style !== 'hidden'">
                                <template x-if="d.progress_style === 'bar'">
                                    <div class="h-2 w-full overflow-hidden rounded-full" :style="{ background: 'color-mix(in srgb, var(--qf-text) 10%, transparent)' }">
                                        <div class="h-full" :style="{ width: '60%', background: 'var(--qf-primary)', borderRadius: '999px' }"></div>
                                    </div>
                                </template>
                                <template x-if="d.progress_style === 'dots'">
                                    <div class="flex gap-1.5">
                                        <template x-for="i in 5" :key="i">
                                            <span class="size-2 rounded-full" :style="{ background: i <= 3 ? 'var(--qf-primary)' : 'color-mix(in srgb, var(--qf-text) 15%, transparent)' }"></span>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="d.progress_style === 'steps'">
                                    <div class="flex gap-1">
                                        <template x-for="i in 5" :key="i">
                                            <span class="h-1.5 flex-1 rounded-full" :style="{ background: i <= 3 ? 'var(--qf-primary)' : 'color-mix(in srgb, var(--qf-text) 15%, transparent)' }"></span>
                                        </template>
                                    </div>
                                </template>
                            </div>

                            <p class="text-xs font-semibold uppercase tracking-wide" :style="{ color: 'var(--qf-secondary)' }">{{ __('Question 3 of 5') }}</p>
                            <h3 class="mt-1 text-lg font-bold" :style="{ color: 'var(--qf-text)' }">{{ __('Which planet is known as the Red Planet?') }}</h3>
                            <p class="mt-1 text-sm" :style="{ color: 'var(--qf-muted)' }">{{ __('Pick the answer you think is correct.') }}</p>

                            <div class="mt-4 space-y-2.5">
                                <template x-for="(opt, i) in ['Venus', 'Mars', 'Jupiter']" :key="opt">
                                    <div class="px-4 py-3 text-sm font-medium transition" :style="optionStyle(i === 1)">
                                        <span x-text="opt"></span>
                                    </div>
                                </template>
                            </div>

                            <input type="text" placeholder="{{ __('Type your answer…') }}" class="mt-4 w-full px-3.5 py-2.5 text-sm focus:outline-none" :style="{ ...inputStyle(), color: 'var(--qf-text)' }" />

                            <button class="mt-5 w-full px-4 py-3 text-sm font-semibold transition" :style="{ background: 'var(--qf-primary)', color: 'var(--qf-primary-ink)', borderRadius: 'var(--qf-btn-radius)' }">
                                {{ __('Continue') }}
                            </button>
                        </div>

                        <p x-show="! d.hide_branding" class="mt-4 text-center text-[11px]" :style="{ color: 'var(--qf-muted)' }">{{ __('Powered by QuizForge') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
