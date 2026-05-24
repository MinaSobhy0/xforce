<?php

namespace Modules\Marketing\Services;

use Illuminate\Support\Facades\Log;
use Modules\Marketing\Jobs\DownloadInboundMediaJob;
use Modules\Marketing\Models\WhatsAppConversation;
use Modules\Marketing\Models\WhatsAppMessage;

/**
 * Persists inbound Meta webhook messages into the tenant's whatsapp_*
 * tables. Runs AFTER ResolveTenantFromWabaWebhook has switched schema,
 * so current_tenant() and the tenant-scoped models are usable directly.
 *
 * Idempotent on wamid: Meta retries webhooks aggressively, and a single
 * conversation may receive the same payload more than once.
 *
 * Button-reply messages are persisted here AND still routed through the
 * existing handleButtonCallback path in the controller — appointment
 * confirm/reschedule/cancel logic remains untouched.
 */
class InboundMessageProcessor
{
    public function handle(array $payload): void
    {
        $tenant = current_tenant();
        if (! $tenant) {
            Log::warning('whatsapp.inbound.no_tenant', [
                'reason' => 'middleware did not resolve tenant',
            ]);

            return;
        }

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                $phoneNumberId = (string) ($value['metadata']['phone_number_id'] ?? '');
                $contactsByWaId = $this->indexContacts($value['contacts'] ?? []);

                foreach ($value['messages'] ?? [] as $message) {
                    try {
                        $this->persistMessage($tenant->id, $phoneNumberId, $contactsByWaId, $message);
                    } catch (\Throwable $e) {
                        // Never let one bad message 500 the webhook — Meta retries
                        // 4xx/5xx and we'd loop the failure across all entries.
                        Log::error('whatsapp.inbound.persist_failed', [
                            'wamid' => $message['id'] ?? null,
                            'type' => $message['type'] ?? null,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }
    }

    protected function persistMessage(int $tenantId, string $phoneNumberId, array $contacts, array $message): void
    {
        $wamid = (string) ($message['id'] ?? '');
        if ($wamid === '') {
            Log::warning('whatsapp.inbound.no_wamid', ['type' => $message['type'] ?? null]);

            return;
        }

        if (WhatsAppMessage::where('wamid', $wamid)->exists()) {
            return; // idempotent
        }

        $fromPhone = $this->normalizePhone((string) ($message['from'] ?? ''));
        if ($fromPhone === '') {
            Log::warning('whatsapp.inbound.no_from', ['wamid' => $wamid]);

            return;
        }

        $displayName = $contacts[$message['from'] ?? null]['profile']['name'] ?? null;

        $conversation = $this->findOrCreateConversation(
            $tenantId,
            $fromPhone,
            $phoneNumberId,
            $displayName
        );

        $sentAt = isset($message['timestamp'])
            ? \Carbon\Carbon::createFromTimestamp((int) $message['timestamp'])
            : now();

        $normalized = $this->normalize($message);

        $row = WhatsAppMessage::create([
            'tenant_id' => $tenantId,
            'conversation_id' => $conversation->id,
            'wamid' => $wamid,
            'direction' => WhatsAppMessage::DIRECTION_INBOUND,
            'type' => $normalized['type'],
            'body' => $normalized['body'],
            'media_id' => $normalized['media_id'],
            'media_mime' => $normalized['media_mime'],
            'media_filename' => $normalized['media_filename'],
            'interactive_payload' => $normalized['interactive_payload'],
            'context_wamid' => $message['context']['id'] ?? null,
            'sent_at' => $sentAt,
            'metadata' => $normalized['metadata'],
        ]);

        $conversation->update([
            'last_message_at' => $sentAt,
            'last_inbound_at' => $sentAt,
            'last_message_preview' => $this->buildPreview($normalized),
            'last_message_direction' => 'inbound',
            'unread_count' => $conversation->unread_count + 1,
            'remote_display_name' => $displayName ?: $conversation->remote_display_name,
        ]);

        // Media: dispatch a queued download so the webhook returns fast
        // (Meta media URLs expire in 5 minutes — but we kick off the fetch
        // immediately so the file is local before the agent opens the inbox).
        if ($row->media_id) {
            DownloadInboundMediaJob::dispatch($tenantId, $row->id);
        }
    }

    /**
     * Pluck the message-type-specific fields into a flat shape.
     */
    protected function normalize(array $message): array
    {
        $type = (string) ($message['type'] ?? 'unknown');

        $base = [
            'type' => $type,
            'body' => null,
            'media_id' => null,
            'media_mime' => null,
            'media_filename' => null,
            'interactive_payload' => null,
            'metadata' => null,
        ];

        switch ($type) {
            case 'text':
                $base['body'] = $message['text']['body'] ?? null;
                break;

            case 'image':
            case 'document':
            case 'video':
            case 'audio':
                $media = $message[$type] ?? [];
                $base['media_id'] = $media['id'] ?? null;
                $base['media_mime'] = $media['mime_type'] ?? null;
                $base['media_filename'] = $media['filename'] ?? null;
                $base['body'] = $media['caption'] ?? null; // images/documents/videos can carry a caption
                break;

            case 'interactive':
                $interactive = $message['interactive'] ?? [];
                $base['interactive_payload'] = $interactive;
                // Promote button_reply to its own type so the inbox can render it inline.
                if (($interactive['type'] ?? null) === 'button_reply') {
                    $base['type'] = WhatsAppMessage::TYPE_BUTTON_REPLY;
                    $base['body'] = $interactive['button_reply']['title'] ?? null;
                } elseif (($interactive['type'] ?? null) === 'list_reply') {
                    $base['body'] = $interactive['list_reply']['title'] ?? null;
                }
                break;

            case 'button':
                // Legacy template-button reply (pre-interactive).
                $base['type'] = WhatsAppMessage::TYPE_BUTTON_REPLY;
                $base['body'] = $message['button']['text'] ?? null;
                $base['interactive_payload'] = $message['button'] ?? null;
                break;

            case 'reaction':
                $base['body'] = $message['reaction']['emoji'] ?? null;
                $base['context_wamid'] = $message['reaction']['message_id'] ?? null;
                $base['metadata'] = $message['reaction'] ?? null;
                break;

            case 'location':
                $base['metadata'] = $message['location'] ?? null;
                $base['body'] = trim(($message['location']['name'] ?? '') . ' ' . ($message['location']['address'] ?? '')) ?: null;
                break;

            default:
                $base['type'] = WhatsAppMessage::TYPE_UNKNOWN;
                $base['metadata'] = $message;
                break;
        }

        return $base;
    }

    protected function findOrCreateConversation(int $tenantId, string $remotePhone, string $phoneNumberId, ?string $displayName): WhatsAppConversation
    {
        return WhatsAppConversation::firstOrCreate(
            [
                'remote_phone_e164' => $remotePhone,
                'phone_number_id' => $phoneNumberId,
            ],
            [
                'tenant_id' => $tenantId,
                'remote_display_name' => $displayName,
            ]
        );
    }

    /**
     * Meta sends the from-number as a bare wa_id (no +). Normalize to E.164.
     */
    protected function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/[^0-9]/', '', $raw) ?? '';
        if ($digits === '') {
            return '';
        }

        return str_starts_with($raw, '+') ? '+' . $digits : '+' . $digits;
    }

    /**
     * @return array<string, array> keyed by wa_id
     */
    protected function indexContacts(array $contacts): array
    {
        $out = [];
        foreach ($contacts as $c) {
            if (isset($c['wa_id'])) {
                $out[$c['wa_id']] = $c;
            }
        }

        return $out;
    }

    protected function buildPreview(array $normalized): string
    {
        if (! empty($normalized['body'])) {
            return mb_substr($normalized['body'], 0, 280);
        }

        return match ($normalized['type']) {
            WhatsAppMessage::TYPE_IMAGE => '📷 Image',
            WhatsAppMessage::TYPE_DOCUMENT => '📄 Document',
            WhatsAppMessage::TYPE_VIDEO => '🎬 Video',
            WhatsAppMessage::TYPE_AUDIO => '🎤 Audio',
            WhatsAppMessage::TYPE_LOCATION => '📍 Location',
            WhatsAppMessage::TYPE_REACTION => '👍 Reaction',
            WhatsAppMessage::TYPE_BUTTON_REPLY => '↩︎ Button reply',
            default => ucfirst((string) $normalized['type']),
        };
    }
}
