@props([
    'position' => config('laradbchat.widget.position', 'bottom-right'),
    'title' => config('laradbchat.widget.title', 'Database Assistant'),
    'placeholder' => config('laradbchat.widget.placeholder', 'Ask a question about your data...'),
    'showSql' => config('laradbchat.widget.show_sql', true),
    'theme' => config('laradbchat.widget.theme', []),
])

@php
$positionClasses = match($position) {
    'bottom-left' => 'bottom-4 left-4',
    'top-right' => 'top-4 right-4',
    'top-left' => 'top-4 left-4',
    default => 'bottom-4 right-4',
};

$primaryColor = $theme['primary'] ?? '#3B82F6';
$secondaryColor = $theme['secondary'] ?? '#1E40AF';
$bgColor = $theme['background'] ?? '#FFFFFF';
$textColor = $theme['text'] ?? '#1F2937';

$apiPrefix = config('laradbchat.api.prefix', 'api/laradbchat');
// Ensure it starts with /
$apiUrl = '/' . ltrim($apiPrefix, '/');
@endphp

@if(config('laradbchat.widget.enabled', true))
<div
    x-data="laraDBChatWidget({
        apiUrl: '{{ $apiUrl }}',
        csrfToken: '{{ csrf_token() }}',
        showSql: {{ $showSql ? 'true' : 'false' }},
        maxHistory: {{ config('laradbchat.widget.max_history', 50) }}
    })"
    x-cloak
    class="fixed {{ $positionClasses }} z-50"
    style="--ldc-primary: {{ $primaryColor }}; --ldc-secondary: {{ $secondaryColor }}; --ldc-bg: {{ $bgColor }}; --ldc-text: {{ $textColor }};"
>
    {{-- Toggle Button --}}
    <button
        x-show="!isOpen"
        x-on:click="toggle()"
        class="ldc-toggle-btn"
        aria-label="Open chat"
        type="button"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
        </svg>
    </button>

    {{-- Chat Window --}}
    <div
        x-show="isOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 transform scale-95"
        x-transition:enter-end="opacity-100 transform scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 transform scale-100"
        x-transition:leave-end="opacity-0 transform scale-95"
        class="ldc-chat-window"
    >
        {{-- Header --}}
        <div class="ldc-header">
            <h3 class="ldc-title">{{ $title }}</h3>
            <div style="display: flex; align-items: center; gap: 8px;">
                <button
                    x-show="hasMessages()"
                    x-on:click="clearHistory()"
                    class="ldc-clear-btn"
                    type="button"
                    title="Clear history"
                >
                    Clear
                </button>
                <button x-on:click="toggle()" class="ldc-close-btn" aria-label="Close chat" type="button">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
        </div>

        {{-- Messages --}}
        <div class="ldc-messages" x-ref="messages">
            {{-- Welcome message when empty --}}
            <div x-show="!hasMessages() && !isLoading" class="ldc-welcome">
                <div class="ldc-welcome-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mx-auto" style="color: var(--ldc-primary)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                    </svg>
                </div>
                <div class="ldc-welcome-title">{{ $title }}</div>
                <div class="ldc-welcome-text">Ask questions about your data in natural language.</div>
            </div>

            {{-- Message list --}}
            <template x-for="(message, index) in messages" :key="index">
                <div :class="message.type === 'user' ? 'ldc-message-user' : 'ldc-message-bot'">
                    <div class="ldc-message-content" x-html="formatMessage(message)"></div>
                </div>
            </template>

            {{-- Loading indicator --}}
            <div x-show="isLoading" class="ldc-message-bot">
                <div class="ldc-loading">
                    <span class="ldc-dot"></span>
                    <span class="ldc-dot"></span>
                    <span class="ldc-dot"></span>
                </div>
            </div>
        </div>

        {{-- Input --}}
        <div class="ldc-input-area">
            <form x-on:submit.prevent="sendMessage()">
                <input
                    type="text"
                    x-model="currentMessage"
                    x-bind:disabled="isLoading"
                    placeholder="{{ $placeholder }}"
                    class="ldc-input"
                    autocomplete="off"
                >
                <button
                    type="submit"
                    x-bind:disabled="isLoading || !currentMessage.trim()"
                    class="ldc-send-btn"
                    aria-label="Send message"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z" />
                    </svg>
                </button>
            </form>
        </div>
    </div>
</div>
@endif
