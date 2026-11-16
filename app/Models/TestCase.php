<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class TestCase extends Model
{
    use HasFactory;

    public const KIND_SAMPLE = 'sample';
    public const KIND_HIDDEN = 'hidden';

    protected $fillable = [
        'problem_id',
        'ordinal',
        'kind',
        'input_path',
        'output_path',
        'input_sha256',
        'output_sha256',
        'input_size_bytes',
        'output_size_bytes',
    ];

    protected function casts(): array
    {
        return [
            'ordinal'           => 'integer',
            'input_size_bytes'  => 'integer',
            'output_size_bytes' => 'integer',
            'created_at'        => 'datetime',
            'updated_at'        => 'datetime',
        ];
    }

    // ---------- Relationships ----------

    public function problem(): BelongsTo
    {
        return $this->belongsTo(Problem::class);
    }

    public function submissionResults(): HasMany
    {
        return $this->hasMany(SubmissionResult::class);
    }

    // ---------- Storage helpers ----------

    protected function disk(): string
    {
        return config('filesystems.test_cases_disk', 'local');
    }

    public function readInput(): string
    {
        return Storage::disk($this->disk())->get($this->input_path);
    }

    public function readOutput(): string
    {
        return Storage::disk($this->disk())->get($this->output_path);
    }

    public function writeInput(string $content): void
    {
        Storage::disk($this->disk())->put($this->input_path, $content);
        $this->forceFill([
            'input_sha256'      => hash('sha256', $content),
            'input_size_bytes'  => strlen($content),
        ])->save();
    }

    public function writeOutput(string $content): void
    {
        Storage::disk($this->disk())->put($this->output_path, $content);
        $this->forceFill([
            'output_sha256'      => hash('sha256', $content),
            'output_size_bytes'  => strlen($content),
        ])->save();
    }

    public function deleteFiles(): void
    {
        $disk = Storage::disk($this->disk());
        $disk->delete($this->input_path);
        $disk->delete($this->output_path);
    }

    public function isSample(): bool
    {
        return $this->kind === self::KIND_SAMPLE;
    }

    public function isHidden(): bool
    {
        return $this->kind === self::KIND_HIDDEN;
    }

    // ---------- Lifecycle hooks ----------

    protected static function booted(): void
    {
        static::deleting(function (self $testCase) {
            $testCase->deleteFiles();
        });
    }
}
