<?php

namespace App\Livewire\Backend\Themes;

use Livewire\Component;
use Livewire\WithFileUploads;
use DirectoryIterator;
use ZipArchive;

use App\Models\Setting;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class Manage extends Component {

    use WithFileUploads;

    public $themes, $current, $new, $error, $success;

    public function mount() {
        $directory = new DirectoryIterator(base_path('resources/views/frontend/themes'));
        $this->themes = [];
        foreach ($directory as $file) {
            if ($file->isDot()) continue;
            if ($file->isFile()) continue;
            array_push($this->themes, $file->getFilename());
        }
        $this->current = Setting::pick('theme');
        $this->cleanAlerts();
    }

    public function cleanAlerts() {
        $this->error = null;
        $this->success = null;
    }

    public function setTheme($theme) {
        if (in_array($theme, $this->themes)) {
            Setting::put('theme', $theme);
            $this->current = Setting::pick('theme');
        }
        $this->cleanAlerts();
    }

    public function delete($theme) {
        $path = resource_path('views/frontend/themes/' . $theme);
        if (File::isDirectory($path)) {
            File::deleteDirectory($path);
        } else {
            Storage::disk('themes')->deleteDirectory($theme);
        }
        $this->mount();
    }

    public function save() {
        if ($this->new) {
            $filename = Storage::disk('tmp')->put('/', $this->new);
            $fullPath = Storage::disk('tmp')->path($filename);
            $originalName = method_exists($this->new, 'getClientOriginalName') ? $this->new->getClientOriginalName() : $filename;
            $this->handle($fullPath, $originalName);
            Storage::disk('tmp')->delete($filename);
        } else {
            $this->error = 'Please upload a Theme ZIP file';
        }
    }

    public function version($theme = '') {
        if ($theme) {
            if (File::exists(public_path('themes/' . $theme . '/package.json'))) {
                $info = json_decode(File::get(public_path('themes/' . $theme . '/package.json')));
                if ($info && isset($info->version) && $info->version !== '1.0.0') {
                    return ' - v' . $info->version;
                }
            }
        }
    }

    private function handle($filename, $originalName = '') {
        $file = new ZipArchive;
        if ($file->open($filename) !== TRUE) {
            $this->error = 'Looks like the Theme ZIP file is corrupted';
            return false;
        }

        // Collect all valid files in the zip (excluding macOS/hidden files)
        $entries = [];
        for ($i = 0; $i < $file->numFiles; $i++) {
            $entry = $file->getNameIndex($i);
            if (str_starts_with($entry, '__MACOSX/') || str_contains($entry, '/.DS_Store') || $entry === '.DS_Store') {
                continue;
            }
            $entries[] = $entry;
        }

        // Detect if all files share a common top-level directory
        $commonFolder = null;
        $hasSingleRoot = true;

        foreach ($entries as $entry) {
            $trimmed = trim($entry, '/');
            if (empty($trimmed)) continue;
            $parts = explode('/', $trimmed);
            if ($commonFolder === null) {
                $commonFolder = $parts[0];
            } elseif ($commonFolder !== $parts[0]) {
                $hasSingleRoot = false;
                break;
            }
        }

        // If all files are inside e.g. "groot/...", then prefix is "groot/"
        $folder = ($hasSingleRoot && !empty($commonFolder)) ? $commonFolder : '';
        $prefix = $folder ? $folder . '/' : '';

        // Determine target theme name
        if ($folder) {
            $themeName = $folder;
        } else {
            // Flat zip: infer theme name from original upload name
            $name = pathinfo($originalName, PATHINFO_FILENAME);
            $name = preg_replace('/^\[.*?\]\s*/', '', $name); // Strip [XenVn] or similar tags
            $name = preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower($name));
            $themeName = !empty($name) ? $name : 'custom_theme';
        }

        // Normalize file paths relative to theme root
        $normalizedFiles = [];
        foreach ($entries as $entry) {
            if ($prefix !== '' && str_starts_with($entry, $prefix)) {
                $rel = substr($entry, strlen($prefix));
            } else {
                $rel = $entry;
            }
            if ($rel !== '' && !str_ends_with($rel, '/')) {
                $normalizedFiles[] = $rel;
            }
        }

        // Required theme files (post.blade.php is optional with fallback)
        $verify = [
            'app.blade.php',
            'components/actions.blade.php',
            'components/app.blade.php',
            'components/nav.blade.php',
            'components/page.blade.php',
        ];

        $missing = [];
        foreach ($verify as $item) {
            if (!in_array($item, $normalizedFiles)) {
                $missing[] = $item;
            }
        }

        if (count($missing) > 0) {
            $this->error = 'Incompleted Theme Files. Your ZIP is missing below file(s)<br><br>';
            foreach ($missing as $item) {
                $this->error .= '/' . $item . '<br>';
            }
            $file->close();
            return false;
        }

        $themesDir = resource_path('views/frontend/themes');
        $targetDir = $themesDir . '/' . $themeName;

        if ($prefix !== '') {
            $file->extractTo($themesDir);
        } else {
            if (!File::isDirectory($targetDir)) {
                File::makeDirectory($targetDir, 0775, true, true);
            }
            $file->extractTo($targetDir);
        }
        $file->close();

        // Fallback for components/post.blade.php for legacy themes
        $targetPost = $targetDir . '/components/post.blade.php';
        if (!File::exists($targetPost)) {
            $defaultPost = $themesDir . '/default/components/post.blade.php';
            if (File::exists($defaultPost)) {
                File::ensureDirectoryExists($targetDir . '/components', 0775);
                File::copy($defaultPost, $targetPost);
            }
        }

        // Fix backward-compatibility for legacy themes
        $this->fixLegacyThemeCompat($targetDir);

        // Fix permissions
        @chmod($targetDir, 0775);
        if (File::isDirectory($targetDir . '/components')) {
            @chmod($targetDir . '/components', 0775);
        }

        $this->new = null;
        $count = is_array($this->themes) ? count($this->themes) : 0;
        $this->mount();
        $newCount = is_array($this->themes) ? count($this->themes) : 0;
        if ($count == $newCount) {
            $this->success = 'Theme has been successfully updated.';
        } else {
            $this->success = 'Theme has been successfully added.';
        }
    }

    private function fixLegacyThemeCompat($themeDir) {
        if (!File::isDirectory($themeDir)) {
            return;
        }

        $files = File::allFiles($themeDir);
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $content = File::get($file->getRealPath());
            $modified = false;

            // Fix app.locales -> app.settings.languages
            if (str_contains($content, 'app.locales')) {
                $replacement = "@foreach (config('app.settings.languages', []) as \$code => \$language)\n" .
                    "                                        @if (\$language['is_active'] ?? true)\n" .
                    "                                            <option {{ app()->getLocale() == \$code ? 'selected' : '' }} value=\"{{ \$code }}\">{{ \$language['label'] ?? \$code }}</option>\n" .
                    "                                        @endif\n" .
                    "                                    @endforeach";
                $content = preg_replace(
                    "/@foreach\s*\(\s*config\(['\"]app\.locales['\"]\)\s+as\s+\\\$locale\s*\)[\s\S]*?@endforeach/",
                    $replacement,
                    $content
                );
                $content = str_replace("route('locale', '')", "route('locale')", $content);
                if (!str_contains($content, 'onchange="this.form.submit()"')) {
                    $content = str_replace('id="locale-mobile"', 'id="locale-mobile" onchange="this.form.submit()"', $content);
                }
                $modified = true;
            }

            // Fix App\Models\TMail -> App\Services\TMail
            if (str_contains($content, 'App\Models\TMail')) {
                $content = str_replace('App\Models\TMail', 'App\Services\TMail', $content);
                $modified = true;
            }

            // Fix Jetstream components: x-jet-dropdown -> x-dropdown
            if (str_contains($content, 'x-jet-dropdown')) {
                $content = str_replace('x-jet-dropdown-link', 'x-dropdown-link', $content);
                $content = str_replace('x-jet-dropdown', 'x-dropdown', $content);
                $content = str_replace('width="w-full"', 'width="full"', $content);
                $modified = true;
            }

            if ($modified) {
                File::put($file->getRealPath(), $content);
            }
        }
    }

    public function render() {
        return view('backend.themes.manage');
    }
}
