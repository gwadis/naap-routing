<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Office;
use App\Services\TelegramService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
{
    protected TelegramService $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

    /**
     * Generate secure connection token and Telegram bot deep link.
     */
    public function connect(Request $request)
    {
        $user = auth()->user() ?? User::find(session('user_id'));

        if (!$user) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('login');
        }

        $connectionData = $this->telegramService->generateConnectionToken($user);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'deep_link' => $connectionData['deep_link'],
                'token' => $connectionData['token'],
                'expires_at' => $connectionData['expires_at']->toIso8601String(),
                'bot_username' => $this->telegramService->getBotUsername(),
            ]);
        }

        return redirect()->away($connectionData['deep_link']);
    }

    /**
     * Check current user's Telegram connection status.
     */
    public function status(Request $request)
    {
        $user = auth()->user() ?? User::find(session('user_id'));

        if (!$user) {
            return response()->json(['connected' => false], 401);
        }

        // If the user is currently pending connection, poll Telegram updates to auto-link immediately
        if ($user->telegram_connection_status === \App\Services\TelegramService::STATUS_PENDING) {
            $this->telegramService->pollUpdates();
            $user->refresh();
        }

        return response()->json([
            'connected' => $user->isTelegramConnected(),
            'status' => $user->telegram_connection_status,
            'username' => $user->telegram_username,
            'connected_at' => $user->telegram_connected_at ? $user->telegram_connected_at->format('M j, Y h:i A') : null,
            'preferences' => [
                'announcements' => (bool) $user->telegram_notif_announcements,
                'documents' => (bool) $user->telegram_notif_documents,
                'urgent' => (bool) $user->telegram_notif_urgent,
            ],
            'bot_username' => $this->telegramService->getBotUsername(),
            'configured' => $this->telegramService->isConfigured(),
        ]);
    }

    /**
     * Disconnect Telegram connection from authenticated user account.
     */
    public function disconnect(Request $request)
    {
        $user = auth()->user() ?? User::find(session('user_id'));

        if (!$user) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('login');
        }

        $this->telegramService->disconnectUser($user);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Telegram disconnected successfully.',
            ]);
        }

        return back()->with('success', 'Telegram account disconnected.');
    }

    /**
     * Update user's Telegram notification categories.
     */
    public function updatePreferences(Request $request)
    {
        $user = auth()->user() ?? User::find(session('user_id'));

        if (!$user) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('login');
        }

        $user->update([
            'telegram_notif_announcements' => $request->boolean('telegram_notif_announcements', true),
            'telegram_notif_documents' => $request->boolean('telegram_notif_documents', true),
            'telegram_notif_urgent' => $request->boolean('telegram_notif_urgent', true),
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Telegram notification preferences updated.',
            ]);
        }

        return back()->with('success', 'Notification preferences saved.');
    }

    /**
     * Handle incoming Telegram webhook updates (public endpoint).
     */
    public function webhook(Request $request)
    {
        $payload = $request->all();

        if (empty($payload)) {
            return response()->json(['ok' => true]);
        }

        $result = $this->telegramService->handleWebhookUpdate($payload);

        return response()->json($result);
    }

    /**
     * Send an administrative announcement across channels: In-App, Email, and Telegram (connected users).
     */
    public function sendAnnouncement(Request $request)
    {
        $role = session('user_role') ?? auth()->user()?->role;
        $user = auth()->user() ?? User::find(session('user_id'));
        $isAdmin = ($user && $user->isAdmin()) || User::isRoleAdmin($role);

        if (!$isAdmin) {
            abort(403, 'Administrator access required.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'message' => 'required|string|max:2000',
            'audience' => 'required|in:all,staff,admins,office',
            'office_id' => 'nullable|exists:offices,id',
            'link' => 'nullable|url',
        ]);

        $title = $validated['title'];
        $message = $validated['message'];
        $audience = $validated['audience'];
        $officeId = $validated['office_id'] ?? null;
        $link = $validated['link'] ?? url('/dashboard');

        // Determine recipient users for in-app / email
        $query = User::query()->where('status', 'Active');
        if ($audience === 'admins') {
            $query->whereIn('role', ['ADMIN', 'Administrator', 'Super Administrator']);
        } elseif ($audience === 'staff') {
            $query->whereIn('role', ['Staff', 'Office Head', 'Employee']);
        } elseif ($audience === 'office' && $officeId) {
            $query->where('office_id', $officeId);
        }

        $users = $query->get();

        // 1. In-App Notifications
        foreach ($users as $u) {
            try {
                $u->notify(new \App\Notifications\SystemNotification(
                    "📢 [ANNOUNCEMENT] {$title}: " . \Str::limit($message, 120),
                    $link
                ));
            } catch (\Throwable $e) {
                // Non-blocking
            }
        }

        // 2. Telegram Notifications (Only to connected users in target audience)
        $telegramResult = $this->telegramService->sendAnnouncement($title, $message, $audience, $officeId, $link);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'in_app_count' => $users->count(),
                'telegram' => $telegramResult,
                'message' => "Announcement successfully broadcast to {$users->count()} users (Telegram dispatched to {$telegramResult['sent']} connected accounts).",
            ]);
        }

        return back()->with('success', "Announcement broadcast to {$users->count()} users ({$telegramResult['sent']} via Telegram).");
    }
}
