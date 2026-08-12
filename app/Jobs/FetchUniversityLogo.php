<?php

namespace App\Jobs;

use App\Models\User;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FetchUniversityLogo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Max execution time per job (seconds) */
    public $timeout = 60;

    /** Don't retry on failure — just leave photo_profile null for next batch */
    public $tries = 1;

    protected string $userId;
    protected string $universityName;

    public function __construct(string $userId, string $universityName)
    {
        $this->userId          = $userId;
        $this->universityName  = $universityName;
    }

    public function handle(): void
    {
        $user = User::with('school')->find($this->userId);

        if (!$user || !$user->school) {
            Log::warning("[FetchUniversityLogo] User or school not found: {$this->userId}");
            return;
        }

        // Skip if a logo was already set by a previous run
        if ($user->photo_profile) {
            return;
        }

        // ── Step 1: Ask Gemini for the logo URL ─────────────────────────────
        $logoUrl = $this->askGeminiForLogoUrl($this->universityName);

        if (!$logoUrl) {
            Log::info("[FetchUniversityLogo] Gemini returned NONE for: {$this->universityName}");
            return;
        }

        // ── Step 2: Download & validate the image ───────────────────────────
        $imageData = $this->downloadImage($logoUrl);

        if (!$imageData) {
            Log::warning("[FetchUniversityLogo] Failed to download logo for: {$this->universityName} | URL: {$logoUrl}");
            return;
        }

        // ── Step 3: Derive extension from URL or Content-Type ───────────────
        $ext = $this->guessExtension($logoUrl, $imageData['content_type']);

        if (!$ext) {
            Log::warning("[FetchUniversityLogo] Could not determine image extension for: {$this->universityName}");
            return;
        }

        // ── Step 4: Save to public storage ──────────────────────────────────
        $filename = 'ai_logo_' . $this->userId . '.' . $ext;
        Storage::disk('public')->put('photo-profile/' . $filename, $imageData['body']);

        // ── Step 5: Update DB ────────────────────────────────────────────────
        $user->photo_profile       = $filename;
        $user->save();

        $school                    = $user->school;
        $school->is_verified       = true;
        $school->save();

        Log::info("[FetchUniversityLogo] ✅ Done: {$this->universityName} → {$filename}");
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function askGeminiForLogoUrl(string $name): ?string
    {
        try {
            $prompt = <<<PROMPT
Find the official logo image of "{$name}" (an Indonesian university/college).
Return ONLY a direct image URL ending in .png, .jpg, .jpeg, .svg, or .webp.
The URL must point directly to the image file, not a web page.
If you are not confident or cannot find a reliable URL, return exactly: NONE
No explanation. No extra text. Just the URL or NONE.
PROMPT;

            $result = Gemini::generativeModel('gemini-3.1-flash-lite')
                ->generateContent($prompt);

            $raw = trim($result->text());

            if (empty($raw) || strtoupper($raw) === 'NONE') {
                return null;
            }

            // Basic sanity check: must look like a URL
            if (!filter_var($raw, FILTER_VALIDATE_URL)) {
                Log::warning("[FetchUniversityLogo] Gemini returned non-URL for {$name}: {$raw}");
                return null;
            }

            // Must end with a known image extension
            $lower = strtolower(parse_url($raw, PHP_URL_PATH) ?? '');
            $hasImageExt = preg_match('/\.(png|jpg|jpeg|svg|webp)(\?.*)?$/i', $lower);
            if (!$hasImageExt) {
                Log::warning("[FetchUniversityLogo] URL has no image extension for {$name}: {$raw}");
                return null;
            }

            return $raw;
        } catch (\Exception $e) {
            Log::error("[FetchUniversityLogo] Gemini error for {$name}: " . $e->getMessage());
            return null;
        }
    }

    private function downloadImage(string $url): ?array
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; PrakerinBot/1.0)'])
                ->get($url);

            if (!$response->successful()) {
                return null;
            }

            $body        = $response->body();
            $contentType = $response->header('Content-Type') ?? '';

            // Reject if too small (likely an error page) — min 1 KB
            if (strlen($body) < 1024) {
                Log::warning("[FetchUniversityLogo] Image too small (<1KB) at: {$url}");
                return null;
            }

            // Reject non-image content types
            if (!str_contains($contentType, 'image/')) {
                Log::warning("[FetchUniversityLogo] Non-image Content-Type '{$contentType}' at: {$url}");
                return null;
            }

            return ['body' => $body, 'content_type' => $contentType];
        } catch (\Exception $e) {
            Log::warning("[FetchUniversityLogo] Download exception for {$url}: " . $e->getMessage());
            return null;
        }
    }

    private function guessExtension(string $url, string $contentType): ?string
    {
        // Try from URL path first
        $path = strtolower(parse_url($url, PHP_URL_PATH) ?? '');
        if (preg_match('/\.(png|jpg|jpeg|svg|webp)$/i', $path, $matches)) {
            return $matches[1];
        }

        // Fallback: Content-Type header
        $map = [
            'image/png'   => 'png',
            'image/jpeg'  => 'jpg',
            'image/svg+xml' => 'svg',
            'image/webp'  => 'webp',
            'image/gif'   => 'gif',
        ];

        foreach ($map as $mime => $ext) {
            if (str_contains($contentType, $mime)) {
                return $ext;
            }
        }

        return null;
    }
}
