<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MessageInteractionService
{
    public const REACTIONS = [
        'sweet' => ['label' => 'Sweet', 'icon' => '🍰'],
        'yummy' => ['label' => 'Yummy', 'icon' => '🧁'],
        'love' => ['label' => 'Love Cake', 'icon' => '💗'],
        'wow' => ['label' => 'Wow', 'icon' => '🕯️'],
        'sad' => ['label' => 'Sad Crumb', 'icon' => '🍪'],
        'burnt' => ['label' => 'Burnt Cake', 'icon' => '🔥'],
        'nope' => ['label' => 'Not Okay', 'icon' => '⚠️'],
    ];

    public function decorate(Collection $messages, string $actorRole, ?string $actorId = null, ?string $guestKey = null): Collection
    {
        if ($messages->isEmpty()) {
            return $messages;
        }

        $ids = $messages->pluck('id')->map(fn ($id) => (int) $id)->filter()->values();
        $replyIds = $messages
            ->pluck('reply_to_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $replyMap = $replyIds->isEmpty()
            ? collect()
            : DB::table('messages')
                ->whereIn('id', $replyIds)
                ->get()
                ->keyBy('id');

        $reactionRows = Schema::hasTable('message_reactions')
            ? DB::table('message_reactions')
                ->whereIn('message_id', $ids)
                ->get()
            : collect();

        $grouped = $reactionRows->groupBy('message_id');
        $actorKey = $this->actorKey($actorRole, $actorId, $guestKey);

        return $messages->map(function ($message) use ($replyMap, $grouped, $actorKey) {
            $message->reply_to = null;
            if (!empty($message->reply_to_id) && $replyMap->has((int) $message->reply_to_id)) {
                $message->reply_to = $this->summary($replyMap[(int) $message->reply_to_id]);
            }

            $rows = $grouped->get((int) $message->id, collect());
            $message->reaction_summary = $this->reactionSummary($rows, $actorKey);
            $message->my_reaction = $rows
                ->first(fn ($row) => $this->actorKey($row->actor_role, $row->actor_id, $row->guest_key) === $actorKey)
                ?->reaction;

            return $message;
        });
    }

    public function validateReply(?int $replyToId, string $orderId): ?int
    {
        if (!$replyToId || !Schema::hasColumn('messages', 'reply_to_id')) {
            return null;
        }

        return DB::table('messages')
            ->where('id', $replyToId)
            ->where('order_id', $orderId)
            ->exists() ? $replyToId : null;
    }

    public function react(string $orderId, int $messageId, string $actorRole, ?string $actorId, ?string $guestKey, string $reaction): array
    {
        if (!isset(self::REACTIONS[$reaction])) {
            return ['ok' => false, 'error' => 'Invalid reaction.'];
        }

        if (!Schema::hasTable('message_reactions')) {
            return ['ok' => false, 'error' => 'Reactions are not ready yet.'];
        }

        $message = DB::table('messages')
            ->where('id', $messageId)
            ->where('order_id', $orderId)
            ->first();

        if (!$message) {
            return ['ok' => false, 'error' => 'Message not found.'];
        }

        $existing = DB::table('message_reactions')
            ->where('message_id', $messageId)
            ->where('actor_role', $actorRole)
            ->where('actor_id', $actorId)
            ->where('guest_key', $guestKey)
            ->first();

        if ($existing && $existing->reaction === $reaction) {
            DB::table('message_reactions')->where('id', $existing->id)->delete();
            $myReaction = null;
        } elseif ($existing) {
            DB::table('message_reactions')->where('id', $existing->id)->update([
                'reaction' => $reaction,
                'updated_at' => now(),
            ]);
            $myReaction = $reaction;
        } else {
            DB::table('message_reactions')->insert([
                'message_id' => $messageId,
                'actor_role' => $actorRole,
                'actor_id' => $actorId,
                'guest_key' => $guestKey,
                'reaction' => $reaction,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $myReaction = $reaction;
        }

        $rows = DB::table('message_reactions')->where('message_id', $messageId)->get();

        return [
            'ok' => true,
            'message_id' => $messageId,
            'my_reaction' => $myReaction,
            'reactions' => $this->reactionSummary($rows, $this->actorKey($actorRole, $actorId, $guestKey)),
        ];
    }

    public function summary(object $message): array
    {
        $text = trim((string) ($message->message ?? ''));
        $hasImage = !empty($message->image_path);

        return [
            'id' => (int) $message->id,
            'sender_role' => (string) $message->sender_role,
            'label' => $this->senderLabel((string) $message->sender_role),
            'snippet' => $text !== '' ? mb_strimwidth($text, 0, 88, '...') : ($hasImage ? 'Photo message' : 'Message'),
        ];
    }

    private function reactionSummary(Collection $rows, string $actorKey): array
    {
        return $rows
            ->groupBy('reaction')
            ->map(function (Collection $items, string $reaction) use ($actorKey) {
                $meta = self::REACTIONS[$reaction] ?? ['label' => $reaction, 'icon' => '•'];

                return [
                    'reaction' => $reaction,
                    'label' => $meta['label'],
                    'icon' => $meta['icon'],
                    'count' => $items->count(),
                    'mine' => $items->contains(fn ($row) => $this->actorKey($row->actor_role, $row->actor_id, $row->guest_key) === $actorKey),
                ];
            })
            ->values()
            ->all();
    }

    private function senderLabel(string $role): string
    {
        return match ($role) {
            'admin' => 'Admin',
            'seller' => 'Seller',
            'customer', 'guest' => 'Customer',
            default => 'Message',
        };
    }

    private function actorKey(string $role, ?string $actorId, ?string $guestKey): string
    {
        return $role . ':' . ($actorId ?: '') . ':' . ($guestKey ?: '');
    }
}
