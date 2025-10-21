<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Models\User;

class TodoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $formatJakarta = function ($value) {
            if (!$value) return null;
            try {
                if ($value instanceof \Carbon\Carbon || $value instanceof \Illuminate\Support\Carbon) {
                    $dt = $value;
                } else {
                    $dt = \Carbon\Carbon::parse($value);
                }
                return $dt->timezone('Asia/Jakarta')->locale('id')->translatedFormat('l, d F Y H:i:s');
            } catch (\Throwable $e) {
                return (string) $value;
            }
        };
        // Handle multiple evidence files (make URLs robust and include metadata)
        $evidenceFiles = [];
        $collectPaths = [];
        // Support multiple storage formats: array, JSON string, or single path
        if ($this->evidence_paths) {
            if (is_array($this->evidence_paths)) {
                $collectPaths = $this->evidence_paths;
            } elseif (is_string($this->evidence_paths)) {
                $decoded = @json_decode($this->evidence_paths, true);
                if (is_array($decoded)) {
                    $collectPaths = $decoded;
                } else {
                    // comma-separated fallback
                    $collectPaths = array_filter(array_map('trim', explode(',', $this->evidence_paths)));
                }
            }
        }

        if (empty($collectPaths) && $this->evidence_path) {
            if (is_array($this->evidence_path)) {
                $collectPaths = $this->evidence_path;
            } elseif (is_string($this->evidence_path)) {
                $collectPaths = [$this->evidence_path];
            }
        }

        foreach ($collectPaths as $path) {
            try {
                $publicPath = Storage::url($path); // may be "/storage/..." or full url for remote disks
            } catch (\Throwable $e) {
                $publicPath = '/' . ltrim($path, '/');
            }

            // Build absolute URL only when publicPath is relative
            if (preg_match('#^https?://#i', (string)$publicPath)) {
                $absoluteUrl = $publicPath;
            } else {
                // Prefer APP_URL (includes port), fallback to request host
                $base = config('app.url') ?: $request->getSchemeAndHttpHost();
                $absoluteUrl = rtrim($base, '/') . '/' . ltrim($publicPath, '/');
            }

            // Encode spaces and other characters to create a safe URL for browsers
            try {
                $absoluteUrl = preg_replace_callback('#([^:/]+)://([^/]+)(/.*)#', function ($m) {
                    // preserve scheme and host, encode path
                    $scheme = $m[1];
                    $host = $m[2];
                    $path = $m[3];
                    // encode each segment except already-encoded
                    $segments = array_map(function ($s) {
                        return rawurlencode(rawurldecode($s));
                    }, explode('/', ltrim($path, '/')));
                    $encodedPath = '/' . implode('/', $segments);
                    return $scheme . '://' . $host . $encodedPath;
                }, $absoluteUrl) ?? $absoluteUrl;
            } catch (\Throwable $_) {
                // best effort only
                $absoluteUrl = str_replace(' ', '%20', $absoluteUrl);
            }

            $exists = false;
            $mime = null;
            $size = null;
            try {
                $exists = Storage::disk('public')->exists($path);
            } catch (\Throwable $_) {
                // ignore storage errors here; keep metadata null
            }

            $evidenceFiles[] = [
                // keep original path value as returned by Storage::url() or raw
                'path' => $publicPath,
                // absolute http(s) url the frontend can open
                'url' => $absoluteUrl,
                'full_url' => $absoluteUrl,
                'exists' => $exists,
                'name' => pathinfo($path, PATHINFO_FILENAME),
                'mime' => $mime,
                'size' => $size,
            ];
        }

        // Resolve checker display
        $checkerDisplay = null;
        if ($this->checked_by) {
            $checker = \App\Models\User::find($this->checked_by);
            if ($checker) {
                $checkerDisplay = "{$checker->name} ({$checker->role})";
            } else {
                $checkerDisplay = $this->checked_by;
            }
        }

        // Get latest warning for this todo
        $latestWarning = null;
        if ($this->relationLoaded('warnings')) {
            $latestWarning = $this->warnings->sortByDesc('created_at')->first();
        }

        $data = [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'status' => $this->status,
            'hold_note' => $this->hold_note,
            'todo_type' => $this->todo_type,
            'target_category' => $this->target_category,
            'checked_by' => $checkerDisplay,
            'checker_display' => $checkerDisplay,
            'notes' => $this->notes,
            'due_date' => $this->due_date,
            'scheduled_date' => $this->scheduled_date,
            'target_start_at' => $formatJakarta($this->target_start_at),
            'target_end_at' => $formatJakarta($this->target_end_at),
            'target_duration_value' => $this->target_duration_value,
            'target_duration_unit' => $this->target_duration_unit,
            'target_duration_formatted' => $this->target_duration_value && $this->target_duration_unit
                ? ($this->target_duration_unit === 'hours'
                    ? $this->target_duration_value . ' jam'
                    : $this->target_duration_value . ' menit')
                : null,
            'started_at' => $formatJakarta($this->started_at),
            'submitted_at' => $formatJakarta($this->submitted_at),
            // Raw ISO timestamps for form editing
            'target_start_at_raw' => $this->target_start_at ? (\Carbon\Carbon::parse($this->target_start_at))->toISOString() : null,
            'target_end_at_raw' => $this->target_end_at ? (\Carbon\Carbon::parse($this->target_end_at))->toISOString() : null,
            'started_at_raw' => $this->started_at ? (\Carbon\Carbon::parse($this->started_at))->toISOString() : null,
            'submitted_at_raw' => $this->submitted_at ? (\Carbon\Carbon::parse($this->submitted_at))->toISOString() : null,
            // Recurrence definition fields (exposed for admin UI grouping/pattern display)
            'recurrence_start_date' => $this->recurrence_start_date,
            'recurrence_interval' => $this->recurrence_interval,
            'recurrence_unit' => $this->recurrence_unit,
            'recurrence_count' => $this->recurrence_count,
            'days_of_week' => is_array($this->days_of_week) ? $this->days_of_week : (empty($this->days_of_week) ? [] : json_decode($this->days_of_week, true)),
            // Expose both numeric minutes and formatted duration (compute fallback if missing)
            // numeric minutes (raw value stored in DB)
            'total_work_time' => $this->total_work_time ?? null,
            'total_work_time_minutes' => $this->total_work_time ?? null,
            // formatted human readable string (frontend looks for this key)
            'total_work_time_formatted' => $this->total_work_time_formatted ?? null,
            'rating' => $this->rating,
            'created_at' => $this->created_at->timezone('Asia/Jakarta')->locale('id')->translatedFormat('l, d F Y H:i:s'),
            'formatted_created_at' => $this->created_at->timezone('Asia/Jakarta')->locale('id')->translatedFormat('l, d F Y H:i:s'),
            'evidence_files' => $evidenceFiles,
            'evidence_note' => $this->evidence_note,
        ];

        // Only include updated_at if it's different from created_at
        if ($this->updated_at && $this->updated_at->gt($this->created_at)) {
            $data['updated_at'] = $this->updated_at->timezone('Asia/Jakarta')->locale('id')->translatedFormat('l, d F Y H:i:s');
        }

        // Add warnings section with report (always present)
        $data['warnings'] = [
            'report' => [
                'points' => $latestWarning ? $latestWarning->points : null,
                'level' => $latestWarning ? $latestWarning->level : null,
                'note' => $latestWarning ? $latestWarning->note : null,
                'published_at' => $latestWarning ? $latestWarning->created_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s') : null
            ]
        ];

        return $data;
    }
}
