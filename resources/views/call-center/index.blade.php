@extends('layouts.app')

@section('title', 'Колл-центр')

@section('content')
@php
    $tabs = [
        'calls' => ['label' => 'Звонки', 'icon' => 'bi-telephone'],
        'telegram' => ['label' => 'Telegram', 'icon' => 'bi-telegram'],
        'avito' => ['label' => 'Avito', 'icon' => 'bi-bag'],
        'max' => ['label' => 'MAX', 'icon' => 'bi-chat-dots'],
        'whatsapp' => ['label' => 'WhatsApp', 'icon' => 'bi-whatsapp'],
    ];
@endphp

<h1 class="h4 mb-3">Колл-центр</h1>

<ul class="nav nav-tabs mb-3 cc-channel-tabs">
    @foreach($tabs as $key => $meta)
        <li class="nav-item">
            <a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="{{ route('call-center.index', ['tab' => $key]) }}">
                <i class="bi {{ $meta['icon'] }}"></i> {{ $meta['label'] }}
            </a>
        </li>
    @endforeach
</ul>

@if(session('success'))
    <div class="alert alert-success py-2">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger py-2">{{ session('error') }}</div>
@endif
@if(session('info'))
    <div class="alert alert-info py-2">{{ session('info') }}</div>
@endif

@if($tab === 'telegram')
    @include('call-center.partials.telegram-messenger')
@elseif($tab === 'avito')
    @include('call-center.partials.avito-messenger')
@elseif($tab === 'max')
    @include('call-center.partials.channel-placeholder', [
        'title' => 'MAX (VK)',
        'hint' => 'Интеграция с мессенджером MAX в разработке. Пока фиксируйте обращения вручную.',
        'channel' => 'vk',
    ])
@elseif($tab === 'whatsapp')
    @include('call-center.partials.channel-placeholder', [
        'title' => 'WhatsApp',
        'hint' => 'Интеграция WhatsApp в разработке. Ниже — обращения, зафиксированные вручную.',
        'channel' => 'whatsapp',
    ])
@else
    @include('call-center.partials.calls-table')
@endif
@endsection

@push('styles')
<style>
.cc-channel-tabs .nav-link { color: var(--lombard-primary); font-weight: 500; }
.cc-channel-tabs .nav-link.active { color: var(--lombard-primary); font-weight: 600; border-bottom-color: var(--lombard-accent); }
.cc-tg-layout { display: flex; gap: 0; min-height: 65vh; border: 1px solid #dee2e6; border-radius: 12px; overflow: hidden; background: #fff; }
.cc-tg-chats { width: 300px; min-width: 260px; border-right: 1px solid #dee2e6; background: #f8f9fa; display: flex; flex-direction: column; }
.cc-tg-sidebar-tabs { display: flex; border-bottom: 1px solid #dee2e6; background: #fff; }
.cc-tg-sidebar-tab { flex: 1; border: 0; background: transparent; padding: .55rem .5rem; font-size: .82rem; font-weight: 500; color: #6c757d; cursor: pointer; }
.cc-tg-sidebar-tab i { margin-right: .2rem; }
.cc-tg-sidebar-tab.active { color: var(--lombard-primary); box-shadow: inset 0 -2px 0 var(--lombard-accent); }
.cc-tg-pane { display: flex; flex-direction: column; flex: 1; min-height: 0; }
.cc-tg-chats-header { padding: .75rem 1rem; font-weight: 600; border-bottom: 1px solid #dee2e6; background: #fff; }
.cc-tg-search-box { display: flex; align-items: center; gap: .35rem; padding: .5rem .75rem; border-bottom: 1px solid #dee2e6; background: #fff; }
.cc-tg-search-box input { background: #f1f3f5; border-radius: 8px; }
.cc-tg-search-results { overflow-y: auto; flex: 1; }
.cc-tg-search-section-title { font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; color: #9aa5b1; padding: .65rem 1rem .25rem; }
.cc-tg-search-item { display: block; width: 100%; text-align: left; border: 0; border-bottom: 1px solid #eceff1; background: transparent; padding: .65rem 1rem; cursor: pointer; }
.cc-tg-search-item:hover { background: #e7f1f8; }
.cc-tg-search-item.is-muted { opacity: .85; }
.cc-tg-search-item .title { font-weight: 600; font-size: .9rem; color: #1a2b3c; }
.cc-tg-search-item .preview { font-size: .8rem; color: #6c757d; margin-top: .1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cc-tg-search-item .meta { font-size: .72rem; color: #9aa5b1; margin-top: .15rem; }
.cc-tg-chat-list { overflow-y: auto; flex: 1; }
.cc-tg-chat-item { display: block; width: 100%; text-align: left; border: 0; border-bottom: 1px solid #eceff1; background: transparent; padding: .75rem 1rem; cursor: pointer; }
.cc-tg-chat-item:hover, .cc-tg-chat-item.active { background: #e7f1f8; }
.cc-tg-chat-item .title { font-weight: 600; font-size: .92rem; color: #1a2b3c; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cc-tg-chat-item .preview { font-size: .8rem; color: #6c757d; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: .15rem; }
.cc-tg-chat-item .meta { font-size: .72rem; color: #9aa5b1; margin-top: .2rem; }
.cc-tg-thread { flex: 1; display: flex; flex-direction: column; min-width: 0; background: #e9ecef; }
.cc-tg-thread-header { padding: .75rem 1rem; background: #fff; border-bottom: 1px solid #dee2e6; font-weight: 600; }
.cc-tg-messages { flex: 1; overflow-y: auto; padding: 1rem; display: flex; flex-direction: column; gap: .5rem; }
.cc-tg-bubble { max-width: 78%; padding: .55rem .75rem; border-radius: 12px; font-size: .92rem; line-height: 1.35; white-space: pre-wrap; word-break: break-word; }
.cc-tg-bubble.in { align-self: flex-start; background: #fff; border: 1px solid #dee2e6; }
.cc-tg-bubble.out { align-self: flex-end; background: #d9f5d6; border: 1px solid #b8e6b3; }
.cc-tg-bubble .time { display: block; font-size: .7rem; color: #6c757d; margin-top: .25rem; }
.cc-tg-compose { display: flex; gap: .5rem; padding: .75rem; background: #fff; border-top: 1px solid #dee2e6; }
.cc-avito-branch-bar { padding: .65rem .75rem; border-bottom: 1px solid #dee2e6; background: #fff; }
.cc-avito-listing-group { border-bottom: 1px solid #e3e7ea; }
.cc-avito-listing-head { padding: .55rem .75rem .35rem; background: #eef2f5; font-size: .78rem; font-weight: 600; color: #3d4f5f; }
.cc-avito-listing-head .listing-title { color: inherit; text-decoration: none; }
.cc-avito-listing-head .listing-title:hover { text-decoration: underline; }
.cc-avito-listing-head .listing-meta { color: #6c757d; font-weight: 500; }
.cc-avito-chat-item { padding-left: 1.25rem !important; }
.cc-tg-thread-sub a { color: var(--lombard-primary); }
.cc-tg-compose textarea { resize: none; min-height: 42px; max-height: 120px; }
@media (max-width: 767.98px) {
    .cc-tg-layout { flex-direction: column; min-height: 70vh; }
    .cc-tg-chats { width: 100%; max-height: 38vh; border-right: 0; border-bottom: 1px solid #dee2e6; }
    .cc-tg-layout.chat-open .cc-tg-chats { display: none; }
}
</style>
@endpush
