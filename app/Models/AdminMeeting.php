<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;

/**
 * Réunion interne : convocation, participants et compte-rendu.
 *
 * Une réunion tenue sans compte-rendu est signalée : c'est le compte-rendu qui
 * fait foi des décisions prises.
 */
class AdminMeeting extends Model
{
    use BelongsToEntreprise;

    public const STATES = ['planned' => 'Prévue', 'held' => 'Tenue', 'cancelled' => 'Annulée'];

    protected $table = 'admin_meetings';
    protected $guarded = ['id'];
    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function stateLabel(): string
    {
        return self::STATES[$this->status] ?? self::STATES['planned'];
    }

    public function durationMinutes(): ?int
    {
        if (! $this->starts_at || ! $this->ends_at) {
            return null;
        }

        return (int) $this->starts_at->diffInMinutes($this->ends_at);
    }

    /** Participants saisis une par ligne ou séparés par des virgules. */
    public function attendeesList(): array
    {
        return collect(preg_split('/[\r\n,;]+/', (string) $this->attendees))
            ->map(fn ($name) => trim($name))
            ->filter()
            ->values()
            ->all();
    }

    public function hasMinutes(): bool
    {
        return trim((string) $this->minutes) !== '';
    }

    /** Réunion tenue dont le compte-rendu manque encore. */
    public function needsMinutes(): bool
    {
        return $this->status === 'held' && ! $this->hasMinutes();
    }

    /** Réunion encore annoncée alors que son heure est passée. */
    public function isOverdue(): bool
    {
        return $this->status === 'planned' && $this->starts_at && $this->starts_at->isPast();
    }
}
