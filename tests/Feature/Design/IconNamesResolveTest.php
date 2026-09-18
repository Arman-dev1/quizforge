<?php

namespace Tests\Feature\Design;

use App\Enums\QuestionType;
use App\Enums\QuizType;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Every icon name we hand to Flux has to exist.
 *
 * `<flux:icon :icon="$name">` delegates to the component `icon.{$name}`, and a
 * name with no matching component throws "Flux component [icon.x] does not
 * exist" at *render* time — so one wrong string takes down a whole page and
 * nothing catches it until somebody clicks that tab. The rich-text toolbar
 * shipped `h-3` for the heading button (the Heroicon is `h3`), which blew up
 * the Results tab as soon as score or category mode drew an outcome editor.
 */
class IconNamesResolveTest extends TestCase
{
    public function test_the_rich_text_toolbar_icons_all_exist(): void
    {
        $source = File::get(resource_path('views/components/rich-text.blade.php'));

        preg_match_all("/'icon'\s*=>\s*'([a-z0-9-]+)'/i", $source, $matches);

        $icons = array_values(array_unique($matches[1]));

        $this->assertNotEmpty($icons, 'Found no toolbar icons to check — has the component moved?');

        $this->assertSame([], $this->missing($icons), 'Rich-text toolbar references icons Flux does not have.');
    }

    /**
     * Static `icon="name"` attributes across our views. Dynamic ones
     * (`:icon="$quiz->type->icon()"`) come from enums that own those strings.
     */
    public function test_static_icon_attributes_in_views_all_exist(): void
    {
        $icons = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            preg_match_all(
                '/<(?:flux|x)[:-][a-z0-9.:-]*\s[^>]*?\bicon(?:-trailing)?="([a-z0-9-]+)"/i',
                $file->getContents(),
                $matches,
            );

            foreach ($matches[1] as $icon) {
                $icons[$icon] = $icon;
            }
        }

        $this->assertNotEmpty($icons, 'Found no icon attributes to check.');

        $this->assertSame([], $this->missing(array_values($icons)), 'Views reference icons Flux does not have.');
    }

    /** Icon names returned by the enums, which drive most dynamic usages. */
    public function test_enum_icons_all_exist(): void
    {
        $icons = [];

        foreach ([QuizType::cases(), QuestionType::cases()] as $cases) {
            foreach ($cases as $case) {
                if (method_exists($case, 'icon')) {
                    $icons[] = $case->icon();
                }
            }
        }

        $this->assertNotEmpty($icons);

        $this->assertSame([], $this->missing(array_values(array_unique($icons))), 'An enum returns an icon Flux does not have.');
    }

    /**
     * @param  array<int, string>  $icons
     * @return array<int, string>
     */
    protected function missing(array $icons): array
    {
        return array_values(array_filter($icons, fn (string $icon) => ! $this->iconExists($icon)));
    }

    protected function iconExists(string $icon): bool
    {
        static $dir = null;

        if ($dir === null) {
            $found = File::exists($d = base_path('vendor/livewire/flux/stubs/resources/views/flux/icon'))
                ? $d
                : (File::exists($d = base_path('vendor/livewire/flux/resources/views/flux/icon')) ? $d : '');

            $dir = $found;
        }

        if ($dir === '') {
            $this->markTestSkipped('Flux icon directory not found — is flux installed?');
        }

        return File::exists($dir.'/'.$icon.'.blade.php');
    }
}
